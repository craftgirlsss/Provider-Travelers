<?php
// File: process/login_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Aturan Keamanan
const MAX_ATTEMPTS = 5;
const SUSPEND_EMAIL_CONTACT = "info@karyadeveloperindonesia.com"; // Email kontak untuk pengaktifan akun

// --- Fungsi Pembantu untuk Update attempts ---
function updateLoginAttempts($conn, $uuid, $attempts, $is_suspended = 0) {
    // Gunakan prepared statement untuk keamanan
    $sql = "UPDATE users SET login_attempts = ?, is_suspended = ? WHERE uuid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $attempts, $is_suspended, $uuid);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /login");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$redirect_to = '/login';
$message_type = "danger";

if (empty($email) || empty($password)) {
    $_SESSION['message'] = "Email dan password wajib diisi.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . $redirect_to);
    exit();
}

try {
    // 1. Ambil data user dari DB (Mengambil UUID, bukan ID integer)
    $stmt = $conn->prepare("
        SELECT uuid, name, password, role, status, login_attempts, is_suspended, suspended_until 
        FROM users 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Kredensial tidak valid.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $redirect_to);
        exit();
    }
    
    $user = $result->fetch_assoc();
    $user_uuid = $user['uuid']; // Kunci identifikasi baru
    $current_attempts = (int)$user['login_attempts'];
    $stmt->close(); // Tutup statement pertama

    // 2. CEK SUSPENSI PERMANEN (is_suspended = 1)
    if ($user['is_suspended'] == 1) {
        $message = "Akun Anda telah dinonaktifkan (suspended) karena terlalu banyak percobaan login yang gagal. Silakan kirim email permohonan pengaktifan akun ke **" . SUSPEND_EMAIL_CONTACT . "**.";
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = "danger";
        header("Location: " . $redirect_to);
        exit();
    }

    // 3. VERIFIKASI PASSWORD
    if (password_verify($password, $user['password'])) {
        
        // --- LOGIN BERHASIL ---
        
        // Reset percobaan gagal
        if ($current_attempts > 0) {
            updateLoginAttempts($conn, $user_uuid, 0, 0); // Reset attempts dan is_suspended
        }
        
        // Set session (menggunakan UUID sebagai identifier utama)
        $_SESSION['user_uuid'] = $user_uuid; 
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $email;
        
        // Redirect berdasarkan role
        if ($user['role'] === 'provider') {
            $redirect_to = '/dashboard';
        } elseif ($user['role'] === 'admin') {
            $redirect_to = '/admin/dashboard'; // Ganti sesuai path Admin Anda
        } else {
            $redirect_to = '/'; 
        }

        // Set pesan sukses (opsional)
        // $_SESSION['message'] = "Selamat datang, " . $user['name'] . "!"; 
        // $_SESSION['message_type'] = "success";
        
        header("Location: " . $redirect_to);
        exit();

    } else {
        
        // --- LOGIN GAGAL: PASSWORD SALAH ---
        
        // 4. Tingkatkan hitungan percobaan gagal
        $new_attempts = $current_attempts + 1;
        $message = "Kredensial tidak valid.";
        
        if ($new_attempts >= MAX_ATTEMPTS) {
            
            // Suspensi Permanen: set is_suspended = 1
            updateLoginAttempts($conn, $user_uuid, $new_attempts, 1);
            
            $message = "Login gagal. Akun Anda telah dinonaktifkan (suspended) karena mencapai batas percobaan gagal (" . MAX_ATTEMPTS . " kali). Silakan kirim email permohonan pengaktifan akun ke **" . SUSPEND_EMAIL_CONTACT . "**.";
            $_SESSION['message_type'] = "danger";

        } else {
            // Update hitungan percobaan gagal
            updateLoginAttempts($conn, $user_uuid, $new_attempts, 0);
            
            // Berikan peringatan sisa percobaan
            $remaining = MAX_ATTEMPTS - $new_attempts;
            $message .= " Sisa percobaan: $remaining kali.";
            $_SESSION['message_type'] = "warning";
        }
        
        $_SESSION['message'] = $message;
        header("Location: " . $redirect_to);
        exit();
    }
    
} catch (Exception $e) {
    // Log kesalahan database yang lebih umum
    error_log("Login Error for email $email: " . $e->getMessage());
    $_SESSION['message'] = "Terjadi kesalahan sistem. Silakan coba lagi.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . $redirect_to);
    exit();
}
// Tidak perlu finally { $conn->close() } karena PHP akan menutup koneksi secara otomatis
?>