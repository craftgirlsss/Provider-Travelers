<?php
// File: process/login_process.php
session_start();

// Sertakan konfigurasi database
// Path: ../config/db_config.php relatif dari folder process/
require_once __DIR__ . '/../config/db_config.php';

// Pastikan hanya request POST yang diproses
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Jika akses langsung, arahkan ke halaman login
    header("Location: /login");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Pesan error umum untuk keamanan (tidak spesifik ke email/password)
$error_message = "Email atau Password salah, atau Akun tidak memiliki akses Provider.";

// Validasi input dasar
if (empty($email) || empty($password)) {
    $_SESSION['message'] = "Email dan Password harus diisi.";
    $_SESSION['message_type'] = "danger";
    header("Location: /login");
    exit();
}

// 1. Cari User di Database
// Menggunakan Prepared Statement untuk mencegah SQL Injection
$stmt = $conn->prepare("SELECT id, password, role, status FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $hashed_password = $user['password'];

    // 2. Verifikasi Password yang di-Hash
    // HANYA jika password_verify berhasil, kita lanjutkan
    if (password_verify($password, $hashed_password)) {
        
        // 3. Verifikasi Peran (Role) dan Status
        if ($user['role'] === 'provider') {
            
            if ($user['status'] != 1) { // 1 = Aktif
                 $_SESSION['message'] = "Akun Anda belum aktif. Silakan hubungi admin.";
                 $_SESSION['message_type'] = "danger";
                 header("Location: /login");
                 exit();
            }

            // Login BERHASIL
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $user['role']; 

            // Arahkan ke dashboard provider
            header("Location: /dashboard");
            exit();

        } 
        // Jika password benar tapi role bukan provider
        // Lanjutkan ke logic error di bawah

    } 
    // Jika password_verify GAGAL
    // Lanjutkan ke logic error di bawah
    
} 
// Jika num_rows != 1 (Email tidak ditemukan)
// Lanjutkan ke logic error di bawah

// --- Logic Gagal (Semua kondisi yang tidak mengarah ke exit() sukses) ---
$_SESSION['message'] = $error_message;
$_SESSION['message_type'] = "danger";
header("Location: /login");
exit();

$stmt->close();
$conn->close();
?>