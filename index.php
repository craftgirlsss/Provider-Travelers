<?php
// File: index.php (Root Router)
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
switch ($path) {
    // --- Pages/Views ---
    case 'login':
        require __DIR__ . '/pages/login.php';
        break;
        
    case 'register':
        require __DIR__ . '/pages/register.php';
        break;

    case 'forgot-password':
        require __DIR__ . '/pages/forgot_password.php';
        break;
        
    case 'otp-confirm': // <--- Rute Baru untuk konfirmasi OTP
        require __DIR__ . '/pages/otp_confirm.php';
        break;

    case 'new-password': // <--- Rute Baru untuk set password baru
        require __DIR__ . '/pages/new_password.php';
        break;
    
    case 'dashboard':
        // Ini adalah rute yang seharusnya memuat dashboard.php
        require __DIR__ . '/dashboard.php';
        break;

    case 'process/booking_chat_process':
        require __DIR__ . '/process/booking_chat_process.php';
        break;
        
    case 'process/profile_process': // <--- Rute BARU untuk Profile & Settings
        require __DIR__ . '/process/profile_process.php';
        break;

    // --- Process/Logic ---
    case 'process/login_process':
        require __DIR__ . '/process/login_process.php';
        break;

    case 'process/register_process':
        require __DIR__ . '/process/register_process.php';
        break;
    
    case 'process/auth_process': // <--- Rute Baru untuk semua logic OTP/Reset
        require __DIR__ . '/process/auth_process.php';
        break;

    case 'logout_process':
        require __DIR__ . '/process/logout_process.php';
        break;

    case 'process/trip_process': // <--- Rute Baru untuk semua logic Trip
        require __DIR__ . '/process/trip_process.php';
        break;
    
    // --- Default / Index ---
    case '':
        // URL Root (domain.com/), arahkan ke Login
        header("Location: /login");
        exit();

    default:
        // Halaman 404 Not Found
        http_response_code(404);
        echo "<h1>404 Not Found</h1><p>Halaman tidak ditemukan.</p>";
        break;
}
