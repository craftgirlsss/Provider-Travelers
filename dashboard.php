<?php
// File: dashboard.php (Root Folder)
session_start();
require_once __DIR__ . '/config/db_config.php'; 
// Asumsi: db_config.php sudah me-load $conn

// Inisialisasi variabel otorisasi
$user_uuid_from_session = $_SESSION['user_uuid'] ?? null;
$user_role_from_session = $_SESSION['user_role'] ?? null;
$user_id_from_session = null; // ID integer dari tabel users
$actual_provider_id = null; // ID integer dari tabel providers

// 1. Logic Perlindungan Halaman & Otorisasi
if (!$user_uuid_from_session || $user_role_from_session !== 'provider') {
    $_SESSION['message'] = "Anda harus login sebagai Provider untuk mengakses Dashboard.";
    $_SESSION['message_type'] = "danger";
    // Hapus session yang mungkin salah
    session_unset();
    session_destroy();
    session_start();
    header("Location: /login");
    exit();
}

// --- Ambil ID Integer (id) dan Provider ID dari UUID ---
try {
    // A. Ambil ID integer dari tabel users menggunakan UUID
    $stmt_user = $conn->prepare("SELECT id FROM users WHERE uuid = ?");
    $stmt_user->bind_param("s", $user_uuid_from_session); 
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows > 0) {
        $user_id_from_session = $result_user->fetch_assoc()['id'];
    }
    $stmt_user->close();

    if ($user_id_from_session) {
        // B. Ambil provider_id dari tabel providers menggunakan user_id integer
        $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
        // Gunakan binding "i" karena $user_id_from_session adalah integer (BIGINT)
        $stmt_provider->bind_param("i", $user_id_from_session); 
        $stmt_provider->execute();
        $result_provider = $stmt_provider->get_result();
        
        if ($result_provider->num_rows > 0) {
            $actual_provider_id = $result_provider->fetch_assoc()['id'];
        }
        $stmt_provider->close();
    }

} catch (Exception $e) {
    // Jika ada error DB, anggap otorisasi gagal
    $_SESSION['message'] = "Terjadi kesalahan otorisasi sistem. Silakan coba login lagi.";
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
// <<< LOGIC PENTING UNTUK FILE PROCESS: START >>>
// ==========================================================
// Simpan ID yang sudah terverifikasi ke sesi agar file proses (trip, driver, departure) 
// tidak perlu mengulang query DB dan otorisasi.
$_SESSION['user_id'] = $user_id_from_session; 
$_SESSION['actual_provider_id'] = $actual_provider_id;
// ==========================================================
// <<< LOGIC PENTING UNTUK FILE PROCESS: END >>>
// ==========================================================

// 2. LOGIC PENGAMBILAN NOTIFIKASI AKTIF (H-5 Reminder, dll.)
$pending_notifications = [];
try {
    // Ambil notifikasi yang belum dibaca dan waktu tampilnya sudah terlewat
    $stmt_notif = $conn->prepare("
        SELECT id, message, link 
        FROM provider_notifications 
        WHERE provider_id = ? 
          AND is_read = FALSE 
          AND scheduled_at <= NOW()
        ORDER BY scheduled_at ASC
        LIMIT 5
    ");
    // Gunakan binding "i" untuk $actual_provider_id (integer)
    $stmt_notif->bind_param("i", $actual_provider_id);
    $stmt_notif->execute();
    $pending_notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_notif->close();
} catch (Exception $e) {
    // Jika ada error DB saat mengambil notif, biarkan $pending_notifications kosong.
}
// ------------------------------------------------------------------


// 3. Tentukan Konten yang Akan Dimuat
$page = $_GET['p'] ?? 'summary'; // Default ke halaman 'summary'

// Peta file konten dashboard
$allowed_pages = [
    'orders' => 'dashboard/order_list.php',
    'booking_detail' => 'dashboard/booking_detail.php', 
    'booking_chat' => 'dashboard/booking_chat.php',
    'summary' => 'dashboard/summary.php',
    'trips' => 'dashboard/trip_list.php', 
    'trip_create' => 'dashboard/create_trip.php',
    'trip_edit' => 'dashboard/trip_edit.php', 
    'trip_archive' => 'dashboard/trip_archive.php',     
    'departures' => 'dashboard/departure_schedule.php', // List Jadwal
    'departure_create' => 'dashboard/departure_create.php', // Form Tambah Jadwal  
    'driver_management' => 'dashboard/driver_list.php', // List Driver
    'driver_create' => 'dashboard/driver_create.php', // Form Tambah Driver
    'driver_edit' => 'dashboard/driver_edit.php', // Form Edit Driver
    'vouchers' => 'dashboard/voucher_list.php', 
    'voucher_create' => 'dashboard/voucher_create.php', 
    'voucher_edit' => 'dashboard/voucher_edit.php',
    'profile' => 'dashboard/profile_settings.php',  
    'provider_tickets' => 'dashboard/provider_tickets.php', 
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
                    <a class="nav-link <?php echo ($page == 'trip_archive' ? 'active' : ''); ?>" href="/dashboard?p=trip_archive">
                        <i class="bi bi-archive me-2"></i> Riwayat Trip & Arsip
                    </a>
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
                    if ($page === 'trip_archive') $breadcrumb_name = 'Riwayat Trip & Arsip';
                    if ($page === 'summary') $breadcrumb_name = 'Ringkasan';
                    if ($page === 'provider_tickets') $breadcrumb_name = 'Dukungan & Chat'; 
                    if ($page === 'orders') $breadcrumb_name = 'Daftar Pemesanan';
                    if ($page === 'booking_detail') $breadcrumb_name = 'Detail Pemesanan';
                    if ($page === 'driver_management') $breadcrumb_name = 'Manajemen Driver';
                    if ($page === 'driver_create') $breadcrumb_name = 'Tambah Driver';
                    
                    echo $breadcrumb_name;
                ?></li>
              </ol>
            </nav>
            
            <?php 
            // 4. Tampilkan Pesan Sesi Umum
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
    function markNotificationAsRead(notifId) {
        // Hapus notifikasi secara visual
        const alertElement = document.getElementById('notif-' + notifId);
        if (alertElement) {
            // Hilangkan tampilan alert dengan transisi (opsional)
            alertElement.classList.add('fade-out'); 
            setTimeout(() => {
                alertElement.remove();
            }, 300);
        }

        // KIRIM AJAX REQUEST KE SERVER UNTUK UPDATE DB
        fetch('/process/notif_process.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            // Kirim ID Notifikasi dan Aksi
            body: 'action=read&notif_id=' + notifId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Notifikasi #' + notifId + ' berhasil ditandai sudah dibaca.');
            } else {
                console.error('Gagal menandai notifikasi di DB:', data.message);
                // Jika gagal di DB, mungkin munculkan kembali alert di UI
            }
        })
        .catch(error => console.error('Error saat mengirim AJAX:', error));
    }
    </script>
</body>
</html>