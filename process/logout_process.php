<?php
// File: process/logout_process.php
session_start();

// PASTIKAN FILE KONFIGURASI DAN GLOBAL FUNCTIONS DI-INCLUDE
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/global_functions.php'; 

// 1. AMBIL DATA PENTING SEBELUM SESSION DIHANCURKAN
$provider_id_to_log = $_SESSION['actual_provider_id'] ?? null;
$user_id_to_log = $_SESSION['user_id'] ?? null; 
$is_provider = (($_SESSION['user_role'] ?? '') === 'provider');

// 2. HANCURKAN SESSION SEBELUM REDIRECT
// Hancurkan semua variabel session
$_SESSION = array();

// Jika menggunakan session cookie, hapus juga
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan sesi
session_destroy();

// Mulai sesi baru untuk menampung pesan setelah destroy
session_start(); 

// 3. LOGGING AKTIVITAS
if ($is_provider && $provider_id_to_log) {
    // Hanya log aktivitas jika pengguna yang keluar teridentifikasi sebagai Provider
    
    // Perhatikan: Karena sesi sudah dihancurkan, kita tidak bisa menggunakan $conn di sini 
    // jika $conn didefinisikan di global. Pastikan $conn didefinisikan setelah include db_config
    // dan sebelum baris ini.
    
    log_provider_activity(
        $conn,
        (int)$provider_id_to_log,
        'LOGOUT',
        'users', 
        (int)$user_id_to_log, 
        "Logout berhasil dari Dashboard Provider."
    );
}

// 4. REDIRECT KE HALAMAN LOGIN
$_SESSION['message'] = "Anda telah berhasil Logout.";
$_SESSION['message_type'] = "success";
header("Location: /login");
exit();
?>