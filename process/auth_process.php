<?php
// File: process/auth_process.php (Hanya Logic: Forgot Password Request)
session_start();

// Sertakan konfigurasi database dan kredensial mail
require_once __DIR__ . '/../config/db_config.php';

// --- INI ADALAH AUTOLOADER COMPOSER ---
// Baris ini yang akan memuat semua library, termasuk PHPMailer.
require_once __DIR__ . '/../vendor/autoload.php';

// Sertakan PHPMailer (Hanya namespace, bukan require file)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Cek apakah request POST dan action yang diminta adalah forgot_password_request
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'forgot_password_request') {
    
    $email = trim($_POST['email'] ?? '');
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }

    if (empty($errors)) {
        
        // 1. Cek Email dan Role di Database
        $stmt_check = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'provider'");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $user_name = $user['name'];

            // 2. Generate OTP dan Waktu Kedaluwarsa
            $otp_code = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT); // 6 digit
            $expires_at = date('Y-m-d H:i:s', time() + (15 * 60)); // Kedaluwarsa 15 menit
            $created_at = date('Y-m-d H:i:s');
            
            // 3. Simpan OTP ke Tabel password_resets
            // Hapus OTP lama yang mungkin masih ada untuk user ini
            $conn->query("DELETE FROM password_resets WHERE user_id = '$user_id'");

            $stmt_insert = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at, created_at) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("isss", $user_id, $otp_code, $expires_at, $created_at);
            
            if ($stmt_insert->execute()) {
                
                // 4. Kirim Email OTP menggunakan PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->SMTPDebug = 0; // 0 = nonaktifkan debugging (harus disetel ke 0 di produksi)
                    $mail->Debugoutput = 'html'; // Pastikan output bukan ke echo
                    // Konfigurasi Server
                    $mail->isSMTP();
                    $mail->SMTPDebug  = SMTP::DEBUG_OFF;
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                    
                    // Gunakan TLS untuk port 587 (disarankan)
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                    $mail->Port = SMTP_PORT;

                    // --- TAMBAHKAN KODE DEBUGGING INI ---
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    // Penerima dan Pengirim
                    $mail->setFrom(SMTP_USER, MAIL_FROM_NAME); // Menggunakan konstanta
                    $mail->addAddress($email, $user_name);

                    // Konten
                    $mail->isHTML(true);
                    $mail->Subject = 'Kode OTP Reset Password Akun Provider Anda';
                    $mail->Body    = "Halo <b>$user_name</b>,<br><br>"
                                   . "Ini adalah Kode OTP (One-Time Password) untuk reset password Anda:<br>"
                                   . "<h2 style='color:#0d6efd;'>$otp_code</h2>"
                                   . "Kode ini akan kedaluwarsa dalam 15 menit. Jangan bagikan kode ini kepada siapa pun.<br><br>"
                                   . "Terima kasih,<br>Tim Support Provider Travelers";
                    $mail->AltBody = "Kode OTP Reset Password Anda: $otp_code. Kode ini akan kedaluwarsa dalam 15 menit.";

                    $mail->send();
                    
                    // Sukses: Redirect ke halaman konfirmasi OTP
                    $_SESSION['message'] = "Kode OTP telah dikirim ke email Anda...";
                    $_SESSION['message_type'] = "success";
                    $_SESSION['reset_email'] = $email; 
                    
                    // Baris ini (kemungkinan baris 99)
                    header("Location: /otp-confirm"); 
                    exit();

                } catch (Exception $e) {
                    $errors[] = "Gagal mengirim email OTP. Mailer Error: {$mail->ErrorInfo}";
                    // Jika gagal kirim email, hapus OTP dari DB untuk menghindari masalah keamanan
                    $conn->query("DELETE FROM password_resets WHERE user_id = '$user_id'"); 
                }
            } else {
                $errors[] = "Gagal menyimpan data OTP. Silakan coba lagi.";
            }

        } else {
            $errors[] = "Email tidak terdaftar sebagai Akun Provider.";
        }
    }
    
    // Jika ada error (Database/Validasi/Mailer Error)
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    header("Location: /forgot-password");
    exit();
    
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'verify_otp') {
    
    $email = trim($_POST['email'] ?? '');
    $otp_code = trim($_POST['otp_code'] ?? '');
    $errors = [];

    // Validasi dasar
    if (empty($email) || empty($otp_code) || !is_numeric($otp_code) || strlen($otp_code) !== 6) {
        $errors[] = "Data input tidak valid.";
    }

    if (empty($errors)) {
        // 1. Cari User ID berdasarkan Email
        $stmt_user = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'provider'");
        $stmt_user->bind_param("s", $email);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();
        
        if ($user_result->num_rows === 1) {
            $user = $user_result->fetch_assoc();
            $user_id = $user['id'];
            $stmt_user->close();

            // 2. Cek Kode OTP di Tabel password_resets
            $current_time = date('Y-m-d H:i:s');
            
            $stmt_otp = $conn->prepare("SELECT id, otp_code FROM password_resets WHERE user_id = ? AND otp_code = ? AND expires_at > ?");
            $stmt_otp->bind_param("iss", $user_id, $otp_code, $current_time);
            $stmt_otp->execute();
            $otp_result = $stmt_otp->get_result();
            
            if ($otp_result->num_rows === 1) {
                // OTP BERHASIL dan BELUM KEDALUWARSA!
                $otp_data = $otp_result->fetch_assoc();
                $otp_record_id = $otp_data['id'];

                // 3. Buat Token Reset Permanen
                // Buat token yang akan dibawa ke halaman new_password (lebih aman daripada membawa email)
                $reset_token = bin2hex(random_bytes(32)); 
                
                // Update record OTP dengan token reset dan hapus kode OTP (kode OTP sudah terpakai)
                // Kita akan menggunakan token ini untuk otorisasi di halaman new_password
                $stmt_update = $conn->prepare("UPDATE password_resets SET otp_code = NULL, reset_token = ? WHERE id = ?");
                $stmt_update->bind_param("si", $reset_token, $otp_record_id);
                $stmt_update->execute();
                $stmt_update->close();
                
                // 4. Sukses: Redirect ke halaman set password baru
                $_SESSION['message'] = "Verifikasi sukses. Silakan atur password baru Anda.";
                $_SESSION['message_type'] = "success";
                $_SESSION['reset_token'] = $reset_token; // Simpan token di session
                
                header("Location: /new-password");
                exit();
                
            } else {
                $errors[] = "Kode OTP tidak valid atau sudah kedaluwarsa.";
            }
            $stmt_otp->close();

        } else {
            $errors[] = "Email tidak valid."; // Error umum untuk keamanan
        }
    }
    
    // Jika ada Error
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    header("Location: /otp-confirm"); // Kembali ke halaman konfirmasi OTP
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'set_new_password') {
    
    $reset_token = $_POST['reset_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $errors = [];

    // Validasi data input
    if ($password !== $password_confirm) {
        $errors[] = "Konfirmasi password tidak cocok.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password minimal harus 8 karakter.";
    }
    if (empty($reset_token)) {
        $errors[] = "Token reset tidak ditemukan. Silakan ulangi proses dari awal.";
    }

    if (empty($errors)) {
        
        // 1. Cari Record Reset di Database menggunakan Token
        $stmt_check = $conn->prepare("SELECT user_id FROM password_resets WHERE reset_token = ? AND reset_token IS NOT NULL");
        $stmt_check->bind_param("s", $reset_token);
        $stmt_check->execute();
        $reset_result = $stmt_check->get_result();

        if ($reset_result->num_rows === 1) {
            $data = $reset_result->fetch_assoc();
            $user_id = $data['user_id'];
            $stmt_check->close();

            // 2. Hash Password Baru
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Mulai Transaksi
            $conn->begin_transaction();
            $success = false;
            
            try {
                // A. Update Password di tabel users
                $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update_pass->bind_param("si", $hashed_password, $user_id);
                $stmt_update_pass->execute();
                
                // B. Hapus record dari tabel password_resets (membersihkan token)
                $stmt_delete_token = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt_delete_token->bind_param("i", $user_id);
                $stmt_delete_token->execute();
                
                $conn->commit();
                $success = true;

            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Gagal menyimpan password baru. Error sistem: " . $e->getMessage();
            }
            
            if ($success) {
                // Sukses: Hapus token dari session dan redirect ke halaman login
                unset($_SESSION['reset_token']);
                unset($_SESSION['reset_email']);
                $_SESSION['message'] = "Password Anda telah berhasil diubah! Silakan Login.";
                $_SESSION['message_type'] = "success";
                header("Location: /login");
                exit();
            }

        } else {
            $errors[] = "Token reset tidak valid atau sudah digunakan. Silakan ulangi proses lupa password.";
        }
    }
    
    // Jika ada Error
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    header("Location: /new-password"); // Kembali ke halaman set password
    exit();

} else {
    // Akses langsung atau action salah, redirect ke halaman forgot password
    header("Location: /forgot-password");
    exit();
}

$conn->close();
?>