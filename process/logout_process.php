<?php
// File: process/logout_process.php
session_start();

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

// Redirect ke halaman login
$_SESSION['message'] = "Anda telah berhasil Logout.";
$_SESSION['message_type'] = "success";
header("Location: /login");
exit();
?>