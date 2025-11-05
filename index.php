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
        
    case 'otp-confirm':
        require __DIR__ . '/pages/otp_confirm.php';
        break;

    case 'new-password':
        require __DIR__ . '/pages/new_password.php';
        break;
    
    case 'donation': // <--- RUTE BARU UNTUK HALAMAN DONASI
        require __DIR__ . '/pages/donation.php';
        break;
    
    case 'dashboard':
        // Ini adalah rute yang seharusnya memuat dashboard.php
        require __DIR__ . '/dashboard.php';
        break;
        
    // --- Process/Logic ---
    case 'process/login_process':
        require __DIR__ . '/process/login_process.php';
        break;

    case 'process/register_process':
        require __DIR__ . '/process/register_process.php';
        break;
    
    case 'process/auth_process':
        require __DIR__ . '/process/auth_process.php';
        break;

    case 'logout_process':
        require __DIR__ . '/process/logout_process.php';
        break;

    case 'process/trip_process':
        require __DIR__ . '/process/trip_process.php';
        break;
        
    case 'process/profile_process':
        require __DIR__ . '/process/profile_process.php';
        break;
        
    case 'process/ticket_process':
        require __DIR__ . '/process/ticket_process.php';
        break;
        
    case 'process/booking_chat_process':
        require __DIR__ . '/process/booking_chat_process.php';
        break;

    case 'process/booking_process': // <--- BARU: Untuk konfirmasi Pembayaran oleh Provider
        require __DIR__ . '/process/booking_process.php';
        break;

    case 'process/booking_process_client': // <--- BARU: Untuk upload bukti transfer oleh Client (akan dibuat nanti)
        require __DIR__ . '/process/booking_process_client.php';
        break;

    // Tambahkan Rute Ini:
    case 'process/charter_process':
        require __DIR__ . '/process/charter_process.php';
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