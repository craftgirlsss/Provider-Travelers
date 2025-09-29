<?php
// File: dashboard.php (Root Folder)
session_start();
require_once __DIR__ . '/config/db_config.php';

// 1. Logic Perlindungan Halaman
// Jika user belum login atau role bukan 'provider', redirect ke halaman login.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    $_SESSION['message'] = "Anda harus login sebagai Provider untuk mengakses Dashboard.";
    $_SESSION['message_type'] = "danger";
    header("Location: /login");
    exit();
}

// 2. Tentukan Konten yang Akan Dimuat
$page = $_GET['p'] ?? 'summary'; // Default ke halaman 'summary'

// Peta file konten dashboard
$allowed_pages = [
    'orders' => 'dashboard/order_list.php',
    'booking_chat' => 'dashboard/booking_chat.php',
    'summary' => 'dashboard/summary.php',
    'trips' => 'dashboard/trip_list.php', 
    'trip_create' => 'dashboard/create_trip.php',
    'trip_edit' => 'dashboard/trip_edit.php', 
    'trip_archive' => 'dashboard/trip_archive.php',        
    'profile' => 'dashboard/profile_settings.php',  
    'provider_tickets' => 'dashboard/provider_tickets.php', // <-- TAMBAHAN UNTUK CHAT
];

$content_path = 'pages/' . ($allowed_pages[$page] ?? $allowed_pages['summary']);

// Jika file tidak ada, alihkan ke summary atau error 404
if (!file_exists(__DIR__ . '/' . $content_path)) { 
    $content_path = 'pages/' . $allowed_pages['summary'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Provider - Open Trip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .sidebar {
            width: 250px;
            min-height: 100vh;
            background-color: #343a40; /* Dark sidebar */
        }
        .sidebar .nav-link {
            color: #adb5bd;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            color: #fff;
            background-color: #0d6efd;
        }
        /* Tambahan styling untuk sub-menu, jika ada */
        .sub-menu .nav-link {
            padding-left: 2rem !important; /* Indentasi */
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar text-white p-3">
            <h4 class="mb-4 text-center text-primary">Provider Panel</h4>
            <div class="mb-4">
                <small class="text-light">Halo, <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Provider'); ?></small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'summary' ? 'active' : ''); ?>" href="/dashboard">
                        <i class="bi bi-speedometer2 me-2"></i> Ringkasan
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'trips' || $page == 'trip_create' || $page == 'trip_edit' ? 'active' : ''); ?>" href="/dashboard?p=trips">
                        <i class="bi bi-compass me-2"></i> Trip Aktif
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'trip_archive' ? 'active' : ''); ?>" href="/dashboard?p=trip_archive">
                        <i class="bi bi-archive me-2"></i> Arsip Trip
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'orders' ? 'active' : ''); ?>" href="/dashboard?p=orders">
                        <i class="bi bi-box-seam me-2"></i> Pemesanan
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'provider_tickets' ? 'active' : ''); ?>" href="/dashboard?p=provider_tickets">
                        <i class="bi bi-chat-left-text me-2"></i> Dukungan & Chat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'profile' ? 'active' : ''); ?>" href="/dashboard?p=profile">
                        <i class="bi bi-gear me-2"></i> Profil & Pengaturan
                    </a>
                </li>
                <li class="nav-item mt-3 pt-3 border-top">
                    <a class="nav-link text-danger" href="/logout_process">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        <div class="flex-grow-1 p-4">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php 
                    // Logika untuk menampilkan nama halaman yang lebih user-friendly
                    $breadcrumb_name = ucwords(str_replace('_', ' ', $page));
                    if ($page === 'trips') $breadcrumb_name = 'Trip Aktif';
                    if ($page === 'trip_archive') $breadcrumb_name = 'Arsip Trip';
                    if ($page === 'summary') $breadcrumb_name = 'Ringkasan';
                    if ($page === 'provider_tickets') $breadcrumb_name = 'Dukungan & Chat'; // <-- TAMBAHAN BREADCRUMB
                    
                    echo $breadcrumb_name;
                ?></li>
              </ol>
            </nav>
            
            <?php 
            if (isset($_SESSION['dashboard_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['dashboard_message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['dashboard_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                unset($_SESSION['dashboard_message']);
                unset($_SESSION['dashboard_message_type']);
            endif;
            ?>

            <?php require_once $content_path; ?> 
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>