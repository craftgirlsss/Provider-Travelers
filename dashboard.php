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
    'summary' => 'dashboard/summary.php',
    'trips' => 'dashboard/trip_list.php', // <-- Pastikan KEY ini ('trips') cocok dengan URL ('?p=trips')
    'trip_create' => 'dashboard/create_trip.php',
    'orders' => 'dashboard/order_list.php',         // Daftar Pemesanan
    'profile' => 'dashboard/profile_settings.php',  // Pengaturan Profil
];

$content_path = 'pages/' . ($allowed_pages[$page] ?? $allowed_pages['summary']);

// Jika file tidak ada, alihkan ke summary atau error 404
if (!file_exists(__DIR__ . '/' . $content_path)) { // <-- PASTIKAN PATH ABSOLUT INI BENAR
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
                    <a class="nav-link <?php echo ($page == 'trips' || $page == 'trip_create' ? 'active' : ''); ?>" href="/dashboard?p=trips">
                        <i class="bi bi-compass me-2"></i> Manajemen Trip
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'orders' ? 'active' : ''); ?>" href="/dashboard?p=orders">
                        <i class="bi bi-box-seam me-2"></i> Pemesanan
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
                <li class="breadcrumb-item active" aria-current="page"><?php echo ucwords(str_replace('_', ' ', $page)); ?></li>
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