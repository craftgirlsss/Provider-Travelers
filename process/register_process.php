<?php
session_start();

// 1. Sertakan konfigurasi database
// Pastikan path ini benar relatif terhadap lokasi file saat ini (process/register_process.php)
require_once __DIR__ . '/../config/db_config.php';

// Cek apakah request dikirim melalui POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Jika tidak POST, redirect kembali
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

// 3. Validasi Data
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
    // Redirect kembali dengan pesan error jika checkbox tidak dicentang
    $_SESSION['message'] = "Anda harus menyetujui Syarat dan Ketentuan untuk mendaftar.";
    $_SESSION['message_type'] = "danger";
    header("Location: /register.php");
    exit();
}

// 4. Cek Keberadaan Email di Database
if (empty($errors)) {
    // Menggunakan Prepared Statements untuk mencegah SQL Injection
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
    // Hash Password dengan Bcrypt (sangat disarankan)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Default status dan role untuk provider
    $role = 'provider';
    $status = 1; // 1 = Aktif, 0 = Nonaktif/Pending

    // Mulai transaksi untuk memastikan kedua tabel (users dan providers) terisi atau tidak sama sekali
    $conn->begin_transaction();
    $registration_success = false;
    
    try {
        // A. Insert ke tabel users
        $stmt_user = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt_user->bind_param("sssssi", $name, $email, $phone, $hashed_password, $role, $status);
        
        if ($stmt_user->execute()) {
            $user_id = $conn->insert_id; // Ambil ID yang baru saja dibuat
            $stmt_user->close();

            // B. Insert ke tabel providers (Data provider minimal, sesuai skema Anda)
            // Asumsi: company_name akan diisi dengan name dari users untuk sementara
            $stmt_provider = $conn->prepare("INSERT INTO providers (user_id, company_name, status, created_at) VALUES (?, ?, ?, NOW())");
            $stmt_provider->bind_param("isi", $user_id, $name, $status);
            
            if ($stmt_provider->execute()) {
                $stmt_provider->close();
                $conn->commit(); // Commit transaksi jika kedua query sukses
                $registration_success = true;
            } else {
                throw new Exception("Gagal mendaftarkan data provider.");
            }
        } else {
            throw new Exception("Gagal mendaftarkan user.");
        }

    } catch (Exception $e) {
        $conn->rollback(); // Rollback jika ada error
        $errors[] = "Pendaftaran gagal: " . $e->getMessage();
        // Untuk production, ganti ini: $errors[] = "Terjadi kesalahan sistem, silakan coba lagi.";
    }
    
    if ($registration_success) {
        // Pendaftaran SUKSES!
        $_SESSION['message'] = "Pendaftaran Provider berhasil! Silakan Login.";
        $_SESSION['message_type'] = "success";
        header("Location: ../pages/login.php"); // Arahkan ke halaman Login
        exit();
    }
}

// 6. Jika ada Error, kirim pesan error kembali ke halaman register
if (!empty($errors)) {
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    header("Location: ../pages/register.php"); // Arahkan kembali ke halaman Register
    exit();
}

// Tutup koneksi
$conn->close();
?>