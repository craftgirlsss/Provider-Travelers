<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// ob_start(); // Hapus atau gabungkan, karena sudah ada session_start() di awal
session_start();
require_once __DIR__ . '/config/db_config.php'; 
// ==============================================================================
// INTEGRASI HELPER VERIFIKASI
// File ini harus ada di utils/verification_helper.php
require_once __DIR__ . '/utils/check_provider_verification.php'; 
// ==============================================================================

// Inisialisasi variabel otorisasi & data provider
$user_uuid_from_session = $_SESSION['user_uuid'] ?? null;
$user_role_from_session = $_SESSION['user_role'] ?? null;
$user_id_from_session = null; 
$actual_provider_id = null; 

// --- Data Provider Lengkap ---
$provider_data = [
    'name' => 'Provider', // Default
    'logo_path' => 'assets/default_logo.png', // Default
    'email' => $_SESSION['user_email'] ?? 'unknown@example.com'
];


// 1. Logic Perlindungan Halaman & Otorisasi (Tidak Berubah)
if (!$user_uuid_from_session || $user_role_from_session !== 'provider') {
    $_SESSION['message'] = "Anda harus login sebagai Provider untuk mengakses Dashboard.";
    $_SESSION['message_type'] = "danger";
    // Menggunakan header("Location: /login") setelah session_destroy() sudah benar untuk keamanan
    session_unset();
    session_destroy();
    session_start();
    header("Location: /login");
    exit();
}

// --- Ambil ID Integer (id), Provider ID, dan Data Lengkap (Tidak Berubah) ---
try {
    // A. Ambil Data User (ID & Email)
    $stmt_user = $conn->prepare("SELECT u.id, u.email, p.id AS provider_id, p.company_name, p.company_logo_path
                                 FROM users u
                                 JOIN providers p ON u.id = p.user_id
                                 WHERE u.uuid = ?");
    $stmt_user->bind_param("s", $user_uuid_from_session); 
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows > 0) {
        $data = $result_user->fetch_assoc();
        
        $user_id_from_session = $data['id'];
        $actual_provider_id = $data['provider_id']; // ID dari tabel providers

        // Isi data provider
        $provider_data['name'] = htmlspecialchars($data['company_name']);
        $provider_data['email'] = htmlspecialchars($data['email']);
        
        if (!empty($data['company_logo_path']) && file_exists($data['company_logo_path'])) {
            $provider_data['logo_path'] = htmlspecialchars($data['company_logo_path']);
        } else {
             $provider_data['logo_path'] = 'assets/default_logo.png'; 
        }
    }
    $stmt_user->close();

} catch (Exception $e) {
    $_SESSION['message'] = "Terjadi kesalahan otorisasi sistem: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    header("Location: /login");
    exit();
}

// Final Cek: Pastikan semua ID valid
if (!$user_id_from_session || !$actual_provider_id) {
    $_SESSION['message'] = "Error otorisasi: Data Provider Anda tidak ditemukan. Harap lengkapi profil atau hubungi Admin.";
    $_SESSION['message_type'] = "danger";
    header("Location: /login");
    exit();
}

// ==========================================================
// LOGIC PENTING UNTUK FILE PROCESS:
// ==========================================================
// Simpan ID yang sudah terverifikasi dan data utama ke sesi
$_SESSION['user_id'] = $user_id_from_session; 
$_SESSION['actual_provider_id'] = $actual_provider_id; // ID Integer dari tabel providers
$_SESSION['provider_name'] = $provider_data['name'];
$_SESSION['user_email'] = $provider_data['email'];
// ==========================================================

// 2. LOGIC PENGAMBILAN NOTIFIKASI AKTIF (Tidak Berubah)
$pending_notifications = [];
try {
    $stmt_notif = $conn->prepare("
        SELECT id, message, link 
        FROM provider_notifications 
        WHERE provider_id = ? 
          AND is_read = FALSE 
          AND scheduled_at <= NOW()
        ORDER BY scheduled_at ASC
        LIMIT 5
    ");
    $stmt_notif->bind_param("i", $actual_provider_id);
    $stmt_notif->execute();
    $pending_notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_notif->close();
} catch (Exception $e) {
    // Error notifikasi diabaikan.
}
// ------------------------------------------------------------------


// 3. Tentukan Konten yang Akan Dimuat
$page = $_GET['p'] ?? 'summary'; 

// Penambahan 'tour_guides' dan 'vehicles'
$allowed_pages = [
    'reports' => 'dashboard/reports.php', 
    'activity_log' => 'dashboard/activity_log.php',
    'orders' => 'dashboard/order_list.php',
    'booking_detail' => 'dashboard/booking_detail.php', 
    'booking_chat' => 'dashboard/booking_chat.php',
    'summary' => 'dashboard/summary.php',
    'trips' => 'dashboard/trip_list.php', 
    'trip_create' => 'dashboard/create_trip.php',
    'trip_edit' => 'dashboard/trip_edit.php', 
    'trip_archive' => 'dashboard/trip_archive.php',     
    'departures' => 'dashboard/departure_schedule.php',
    'departure_create' => 'dashboard/departure_create.php', 
    'departure_edit' => 'dashboard/departure_edit.php', 
    'driver_management' => 'dashboard/driver_list.php',
    'driver_create' => 'dashboard/driver_create.php',
    'driver_edit' => 'dashboard/driver_edit.php',
    'vouchers' => 'dashboard/voucher_list.php', 
    'voucher_create' => 'dashboard/voucher_create.php', 
    'voucher_edit' => 'dashboard/voucher_edit.php',
    'profile' => 'dashboard/profile_settings.php',  
    'provider_tickets' => 'dashboard/provider_tickets.php', 
    'tour_guide_create' => 'dashboard/tour_guide_create.php',
    'donation' => 'donation.php', 
    // ==========================================================
    // PENAMBAHAN TAB BARU
    'tour_guides' => 'dashboard/tour_guide_list.php', 
    'vehicles' => 'dashboard/vehicle_list.php', 
    'vehicle_create' => 'dashboard/vehicle_create.php', // Tambahkan ini
    'vehicle_edit' => 'dashboard/vehicle_edit.php', // Tambahkan ini
    // ==========================================================
];

// Logika penentuan content_path (Tidak Berubah)
if ($page === 'donation') {
    $content_path = 'pages/donation.php';
} else {
    $content_path = 'pages/' . ($allowed_pages[$page] ?? $allowed_pages['summary']);
}

if (!file_exists(__DIR__ . '/' . $content_path)) { 
    $content_path = 'pages/' . $allowed_pages['summary'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Provider - <?= $provider_data['name'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Modernized Styling (Tidak Berubah) */
        body { background-color: #f8f9fa; }
        .sidebar {
            width: 280px; 
            min-height: 100vh;
            background-color: #212529;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #dee2e6;
            padding: 10px 1.5rem;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            color: #fff;
            background-color: #0d6efd; 
            border-radius: 5px;
        }
        .sidebar-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #343a40;
            margin-bottom: 1rem;
        }
        .provider-logo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #fff;
        }
        .content-area {
            flex-grow: 1;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar text-white">
            
            <div class="sidebar-header d-flex flex-column align-items-center">
                <img src="/<?= $provider_data['logo_path'] ?>" 
                     alt="Logo <?= $provider_data['name'] ?>" 
                     class="provider-logo mb-2">
                <h5 class="m-0 text-white text-center"><?= $provider_data['name'] ?></h5>
                <small class="text-muted text-center"><?= $provider_data['email'] ?></small>
            </div>
            
            <div class="p-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'summary' ? 'active' : ''); ?>" href="/dashboard">
                            <i class="bi bi-speedometer2 me-2"></i> Ringkasan
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'reports' ? 'active' : ''); ?>" href="/dashboard?p=reports">
                            <i class="bi bi-graph-up me-2"></i> Laporan Keuangan
                        </a>
                    </li>

                    <li class="nav-item mt-3 pt-3 border-top border-secondary">
                        <span class="text-uppercase text-muted small fw-bold ms-3">Layanan Trip</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'trips' || $page == 'trip_create' || $page == 'trip_edit' ? 'active' : ''); ?>" href="/dashboard?p=trips">
                            <i class="bi bi-compass me-2"></i> Trip Aktif
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page === 'departures' || $page === 'departure_create') ? 'active' : ''; ?>" href="/dashboard?p=departures">
                            <i class="bi bi-clock-history me-2"></i>
                            Jadwal Keberangkatan
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page === 'driver_management' || $page === 'driver_create' || $page === 'driver_edit') ? 'active' : ''; ?>" href="/dashboard?p=driver_management">
                            <i class="bi bi-person-badge me-2"></i>
                            Manajemen Driver
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'tour_guides' ? 'active' : ''); ?>" href="/dashboard?p=tour_guides">
                            <i class="bi bi-person-badge-fill me-2"></i>
                            Kelola Pemandu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'vehicles' ? 'active' : ''); ?>" href="/dashboard?p=vehicles">
                            <i class="bi bi-truck-flatbed me-2"></i>
                            Kelola Kendaraan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'trip_archive' ? 'active' : ''); ?>" href="/dashboard?p=trip_archive">
                            <i class="bi bi-archive me-2"></i> Riwayat Trip & Arsip
                        </a>
                    </li>

                    <li class="nav-item mt-3 pt-3 border-top border-secondary">
                        <span class="text-uppercase text-muted small fw-bold ms-3">Transaksi & Operasional</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'orders' || $page == 'booking_detail' ? 'active' : ''); ?>" href="/dashboard?p=orders">
                            <i class="bi bi-box-seam me-2"></i> Pemesanan
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'vouchers' || $page == 'voucher_create' || $page == 'voucher_edit' ? 'active' : ''); ?>" href="/dashboard?p=vouchers">
                            <i class="bi bi-tags-fill me-2"></i> Voucher & Diskon
                        </a>
                    </li>
                    
                    <li class="nav-item mt-3 pt-3 border-top border-secondary">
                        <span class="text-uppercase text-muted small fw-bold ms-3">Akun & Sistem</span>
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

                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'activity_log' ? 'active' : ''); ?>" href="/dashboard?p=activity_log">
                            <i class="bi bi-clock-history me-2"></i> Riwayat Aktivitas
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'donation' ? 'active' : ''); ?>" href="/dashboard?p=donation">
                            <i class="bi bi-gift-fill me-2"></i> Dukung Kami (Donasi)
                        </a>
                    </li>
                    
                    <li class="nav-item mt-3 pt-3 border-top border-secondary">
                        <a class="nav-link text-danger" href="/logout_process">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="content-area">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php 
                    $breadcrumb_name = ucwords(str_replace('_', ' ', $page));
                    
                    // Breadcrumb logic
                    if ($page === 'reports') $breadcrumb_name = 'Laporan Keuangan';
                    if ($page === 'activity_log') $breadcrumb_name = 'Riwayat Aktivitas';
                    if ($page === 'trips') $breadcrumb_name = 'Trip Aktif';
                    if ($page === 'tour_guides') $breadcrumb_name = 'Kelola Pemandu';
                    if ($page === 'vehicles') $breadcrumb_name = 'Kelola Kendaraan';
                    if ($page === 'trip_archive') $breadcrumb_name = 'Riwayat Trip & Arsip';
                    if ($page === 'summary') $breadcrumb_name = 'Ringkasan';
                    if ($page === 'provider_tickets') $breadcrumb_name = 'Dukungan & Chat'; 
                    if ($page === 'orders') $breadcrumb_name = 'Daftar Pemesanan';
                    if ($page === 'booking_detail') $breadcrumb_name = 'Detail Pemesanan';
                    if ($page === 'driver_management') $breadcrumb_name = 'Manajemen Driver';
                    if ($page === 'driver_create') $breadcrumb_name = 'Tambah Driver';
                    if ($page === 'vouchers') $breadcrumb_name = 'Voucher & Diskon';
                    if ($page === 'donation') $breadcrumb_name = 'Dukung Kami'; 
                    
                    echo $breadcrumb_name;
                ?></li>
              </ol>
            </nav>
            
            <?php 
            // 4. Tampilkan Pesan Sesi Umum (Tidak Berubah)
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

            <?php if (!empty($pending_notifications)): ?>
                <?php foreach ($pending_notifications as $notif): ?>
                    <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert" id="notif-<?php echo $notif['id']; ?>">
                        <p class="mb-0">
                            <i class="bi bi-bell-fill me-2"></i>
                            <?php echo htmlspecialchars($notif['message']); ?>
                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="alert-link ms-2 fw-bold">
                                [Tinjau Sekarang]
                            </a>
                        </p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" 
                                onclick="markNotificationAsRead(<?php echo $notif['id']; ?>)"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php require_once $content_path; // 6. Load konten halaman utama ?> 
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // ... (Fungsi markNotificationAsRead tetap sama) ...
    function markNotificationAsRead(notifId) {
        const alertElement = document.getElementById('notif-' + notifId);
        if (alertElement) {
            alertElement.classList.add('fade-out'); 
            setTimeout(() => {
                alertElement.remove();
            }, 300);
        }

        fetch('/process/notif_process.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=read&notif_id=' + notifId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Notifikasi #' + notifId + ' berhasil ditandai sudah dibaca.');
            } else {
                console.error('Gagal menandai notifikasi di DB:', data.message);
            }
        })
        .catch(error => console.error('Error saat mengirim AJAX:', error));
    }
    </script>
</body>
</html>

<?php
// ob_end_flush(); // Hapus karena session_start() di awal sudah ditambahkan ob_start() jika diperlukan.
?>