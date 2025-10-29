<?php
session_start();

// 1. Sertakan konfigurasi database dan PHPMailer
require_once __DIR__ . '/../config/db_config.php';

// Pastikan PHPMailer di-load (SESUAIKAN PATH VENDOR JIKA BERBEDA!)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Jika tidak menggunakan Composer, load file PHPMailer manual:
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php'; 
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php'; 
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php'; 

// =========================================================================================
// FUNGSI PEMBANTU: PENGIRIMAN EMAIL SELAMAT DATANG (Tidak berubah)
// =========================================================================================
function send_welcome_email($recipient_email, $recipient_name) {
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->addReplyTo(SMTP_USER, MAIL_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = 'Selamat Datang di Travelers! - Akun Provider Anda Telah Dibuat';
        
        $body = "
            <html>
            <head>
                <style>body{font-family: Arial, sans-serif;}</style>
            </head>
            <body>
                <h2>Halo, " . htmlspecialchars($recipient_name) . "!</h2>
                <p>Terima kasih telah mendaftar sebagai Provider di Travelers.</p>
                <p>Akun Anda telah berhasil dibuat. Anda dapat segera Login dan mulai melengkapi profil perusahaan/individu Anda untuk proses verifikasi.</p>
                
                <p><b>Langkah Selanjutnya:</b></p>
                <ol>
                    <li>Login ke Dashboard Provider.</li>
                    <li>Lengkapi detail profil, termasuk informasi bank dan dokumen verifikasi.</li>
                    <li>Setelah verifikasi selesai, Anda dapat mulai membuat dan mempublikasikan Trip pertama Anda!</li>
                </ol>
                
                <p>Salam hangat,<br>" . MAIL_FROM_NAME . " Team</p>
                <hr><small>Email ini dikirim otomatis, mohon tidak membalas email ini.</small>
            </body>
            </html>
        ";
        
        $mail->Body    = $body;
        $mail->AltBody = 'Halo, ' . $recipient_name . '! Terima kasih telah mendaftar. Akun Anda telah berhasil dibuat. Silakan Login untuk melengkapi profil Anda.';

        $mail->send();
        return true; 
        
    } catch (Exception $e) {
        error_log("Gagal mengirim email ke {$recipient_email}. Mailer Error: {$mail->ErrorInfo}");
        return false; 
    }
}
// =========================================================================================


// Cek apakah request dikirim melalui POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../pages/register.php");
    exit();
}

// 2. Ambil dan Bersihkan Data Input
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Array untuk menampung error
$errors = [];

// 3. Validasi Data (Tidak berubah)
if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($password_confirm)) {
    $errors[] = "Semua kolom harus diisi.";
}

if ($password !== $password_confirm) {
    $errors[] = "Konfirmasi password tidak cocok.";
}

if (strlen($password) < 8) {
    $errors[] = "Password minimal harus 8 karakter.";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Format email tidak valid.";
}

if (!isset($_POST['terms_agree'])) {
    $_SESSION['message'] = "Anda harus menyetujui Syarat dan Ketentuan untuk mendaftar.";
    $_SESSION['message_type'] = "danger";
    header("Location: /register.php");
    exit();
}

// 4. Cek Keberadaan Email di Database (Tidak berubah)
if (empty($errors)) {
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $errors[] = "Email ini sudah terdaftar. Silakan gunakan email lain atau login.";
    }
    $stmt_check->close();
}


// 5. Proses Pendaftaran Jika Tidak Ada Error
if (empty($errors)) {
    // Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $role = 'provider';
    $status = 1; // 1 = Aktif

    // Mulai transaksi
    $conn->begin_transaction();
    $registration_success = false;
    
    try {
        // A. Insert ke tabel users - MENGGUNAKAN UUID() DARI MYSQL (Tidak Berubah)
        $stmt_user = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status, uuid, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, UUID(), NOW())");
        $stmt_user->bind_param("sssssi", $name, $email, $phone, $hashed_password, $role, $status);
        
        if ($stmt_user->execute()) {
            $user_id = $conn->insert_id; 
            $stmt_user->close();

            // B. Insert ke tabel providers - DITAMBAHKAN UUID()
            $stmt_provider = $conn->prepare("INSERT INTO providers (user_id, company_name, status, uuid, created_at) 
                                              VALUES (?, ?, ?, UUID(), NOW())");
            // Perhatikan: UUID() dipanggil di SQL, jadi tidak ada placeholder (?)
            $stmt_provider->bind_param("isi", $user_id, $name, $status); 
            
            if ($stmt_provider->execute()) {
                $stmt_provider->close();
                $conn->commit(); // Commit transaksi
                $registration_success = true;
            } else {
                throw new Exception("Gagal mendaftarkan data provider.");
            }
        } else {
            throw new Exception("Gagal mendaftarkan user.");
        }

    } catch (Exception $e) {
        $conn->rollback(); // Rollback
        $errors[] = "Pendaftaran gagal: " . $e->getMessage();
    }
    
    if ($registration_success) {
        
        // EKSEKUSI PENGIRIMAN EMAIL DI SINI (Tidak Berubah)
        $email_sent = send_welcome_email($email, $name);
        
        if (!$email_sent) {
            error_log("Gagal mengirim email selamat datang untuk user: $email");
        }
        
        // Pendaftaran SUKSES! (Tidak Berubah)
        $_SESSION['message'] = "Pendaftaran Provider berhasil! Silakan Login. (Cek email Anda untuk informasi lebih lanjut)";
        $_SESSION['message_type'] = "success";
        header("Location: ../pages/login.php"); 
        exit();
    }
}

// 6. Jika ada Error, kirim pesan error kembali ke halaman register (Tidak Berubah)
if (!empty($errors)) {
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    header("Location: ../pages/register.php"); 
    exit();
}

// Tutup koneksi
$conn->close();
?>