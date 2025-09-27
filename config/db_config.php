<?php
// Konfigurasi Database
$host = 'localhost';
$db   = 'sql_api_traveler'; // Ganti dengan nama DB Anda
$user = 'sql_api_traveler'; // Ganti dengan username DB Anda
$pass = 'a7f136533cbbf8';   // Ganti dengan password DB Anda
$port = '2323';              // ← titik koma di sini penting!

define('SMTP_HOST', 'mail.karyadeveloperindonesia.com');  // Contoh: 'smtp.gmail.com' atau host provider SMTP Anda
define('SMTP_USER', 'no-reply@karyadeveloperindonesia.com'); // Email yang akan digunakan untuk mengirim
define('SMTP_PASS', 'Justformeokay23'); // Password email atau App Key
define('SMTP_PORT', 587); // Biasanya 587 (TLS) atau 465 (SSL)
define('MAIL_FROM_NAME', 'Travelers'); // Nama pengirim

// Buat koneksi MySQLi
$conn = new mysqli($host, $user, $pass, $db, $port);

// Cek Koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set karakter untuk menghindari masalah encoding
$conn->set_charset("utf8mb4");

// Fungsi untuk membersihkan input (opsional)
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}
?>