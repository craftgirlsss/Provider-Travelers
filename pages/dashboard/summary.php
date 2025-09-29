<?php
// File: pages/dashboard/summary.php
// Halaman Ringkasan/Dashboard Utama untuk Provider.

$user_id = $_SESSION['user_id'];
$provider_id = null;
$summary_data = [
    'total_trips' => 0,
    'trips_approved' => 0,
    'trips_pending' => 0,
    'total_bookings' => 0,
    'bookings_pending_confirm' => 0,
    'total_revenue' => 0,
    'provider_status' => 'N/A',
    'recent_trips' => []
];
$error = null;

// FIX UNTUK MENGHILANGKAN "Undefined variable $message"
$message = '';
$message_type = 'danger';

if (isset($_SESSION['dashboard_message'])) {
    $message = $_SESSION['dashboard_message'];
    $message_type = $_SESSION['dashboard_message_type'] ?? 'danger'; 
    
    unset($_SESSION['dashboard_message']);
    unset($_SESSION['dashboard_message_type']);
}

try {
    // 1. Dapatkan provider_id dan status verifikasi
    $stmt_provider = $conn->prepare("SELECT id, verification_status FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("s", $user_id);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    
    if ($result_provider->num_rows === 0) {
        $error = "Data Provider tidak ditemukan. Harap lengkapi profil Anda.";
    } else {
        $provider_data = $result_provider->fetch_assoc();
        $provider_id = $provider_data['id'];
        $summary_data['provider_status'] = $provider_data['verification_status'];
        
        // --- 2. QUERY RINGKASAN DATA TRIP ---
        // Menggunakan COALESCE untuk memastikan nilai numerik jika COUNT/SUM kosong
        $sql_trip_summary = "
            SELECT 
                COALESCE(COUNT(id), 0) AS total_trips,
                COALESCE(SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END), 0) AS trips_approved,
                COALESCE(SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END), 0) AS trips_pending
            FROM trips
            WHERE provider_id = ?";
            
        $stmt_trip = $conn->prepare($sql_trip_summary);
        $stmt_trip->bind_param("s", $provider_id);
        $stmt_trip->execute();
        $trip_summary_result = $stmt_trip->get_result()->fetch_assoc();
        $summary_data = array_merge($summary_data, $trip_summary_result);
        $stmt_trip->close();

        // --- 3. QUERY RINGKASAN DATA PEMESANAN & PENDAPATAN (Baris 48) ---
        // Menggunakan COALESCE untuk memaksa hasil NULL menjadi 0.
        $sql_booking_summary = "
            SELECT 
                COALESCE(COUNT(b.id), 0) AS total_bookings,
                COALESCE(SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END), 0) AS bookings_pending_confirm,
                COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND p.status = 'paid' THEN b.total_price ELSE 0 END), 0) AS total_revenue
            FROM bookings b
            JOIN trips t ON b.trip_id = t.id
            LEFT JOIN payments p ON b.id = p.booking_id
            WHERE t.provider_id = ?";

        $stmt_booking = $conn->prepare($sql_booking_summary);
        $stmt_booking->bind_param("s", $provider_id);
        $stmt_booking->execute();
        $booking_summary_result = $stmt_booking->get_result()->fetch_assoc();
        $summary_data = array_merge($summary_data, $booking_summary_result);
        $stmt_booking->close();
        
        // --- 4. QUERY TRIP TERBARU ---
        $sql_recent_trips = "
            SELECT title, location, start_date, price, approval_status 
            FROM trips 
            WHERE provider_id = ?
            ORDER BY created_at DESC 
            LIMIT 5";
            
        $stmt_recent = $conn->prepare($sql_recent_trips);
        $stmt_recent->bind_param("s", $provider_id);
        $stmt_recent->execute();
        $summary_data['recent_trips'] = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_recent->close();
    }
    $stmt_provider->close();
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan sistem saat memuat ringkasan: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Status Verifikasi
 */
function get_verification_badge($status) {
    switch ($status) {
        case 'verified': return '<span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i> Verified</span>';
        case 'pending': return '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Pending Review</span>';
        case 'rejected': return '<span class="badge bg-danger"><i class="bi bi-x-octagon-fill me-1"></i> Ditolak</span>';
        default: return '<span class="badge bg-secondary"><i class="bi bi-exclamation-triangle-fill me-1"></i> Belum Verifikasi</span>';
    }
}
/**
 * Fungsi Pembantu untuk Badge Status Persetujuan Trip
 */
function get_approval_badge($status) {
    switch ($status) {
        case 'approved': return '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Disetujui</span>';
        case 'pending': return '<span class="badge bg-warning text-dark"><i class="bi bi-clock-fill me-1"></i> Menunggu</span>';
        case 'rejected': return '<span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i> Ditolak</span>';
        default: return '<span class="badge bg-secondary">Draft</span>';
    }
}
?>

<h1 class="mb-4">Ringkasan Dashboard</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-5">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="card-title text-muted mb-3"><i class="bi bi-person-badge-fill me-2"></i> Status Akun Provider</h6>
                <div class="fs-4 fw-bold">
                    <?php echo get_verification_badge($summary_data['provider_status']); ?>
                </div>
                <p class="card-text small mt-2">Status verifikasi dokumen bisnis Anda oleh Admin.</p>
            </div>
        </div>
    </div>
</div>

---

<h2 class="mb-4">Metrik Kinerja Trip</h2>
<div class="row mb-5">
    
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card bg-success text-white shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="card-title text-white-50"><i class="bi bi-currency-dollar me-2"></i> Total Pendapatan (Lunas)</h6>
                <div class="display-6 fw-bold">
                    Rp <?php echo number_format((float)$summary_data['total_revenue'], 0, ',', '.'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card bg-warning text-dark shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="card-title text-dark-50"><i class="bi bi-box-seam me-2"></i> Pemesanan Baru</h6>
                <div class="display-6 fw-bold">
                    <?php echo number_format((float)$summary_data['bookings_pending_confirm']); ?>
                </div>
                <small>Menunggu Konfirmasi</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card bg-primary text-white shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="card-title text-white-50"><i class="bi bi-compass-fill me-2"></i> Trip Disetujui</h6>
                <div class="display-6 fw-bold">
                    <?php echo number_format((float)$summary_data['trips_approved']); ?>
                </div>
                <small>Aktif & Siap Dipesan</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card bg-info text-dark shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="card-title text-dark-50"><i class="bi bi-clock-history me-2"></i> Trip Pending</h6>
                <div class="display-6 fw-bold">
                    <?php echo number_format((float)$summary_data['trips_pending']); ?>
                </div>
                <small>Menunggu Persetujuan Admin</small>
            </div>
        </div>
    </div>
</div>

---

<h2 class="mb-4">Trip Terbaru Anda (5 Terakhir)</h2>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($summary_data['recent_trips'])): ?>
            <div class="alert alert-info text-center m-0">
                Anda belum membuat Trip. <a href="/dashboard?p=trip_create" class="alert-link">Buat Trip pertama Anda sekarang!</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Judul Trip</th>
                            <th>Lokasi</th>
                            <th>Tanggal Mulai</th>
                            <th>Harga</th>
                            <th>Status Persetujuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary_data['recent_trips'] as $trip): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trip['title']); ?></td>
                            <td><?php echo htmlspecialchars($trip['location']); ?></td>
                            <td><?php echo date('d M Y', strtotime($trip['start_date'])); ?></td>
                            <td>Rp <?php echo number_format((float)$trip['price'], 0, ',', '.'); ?></td>
                            <td><?php echo get_approval_badge($trip['approval_status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="/dashboard?p=trips" class="btn btn-sm btn-outline-secondary">Lihat Semua Trip <i class="bi bi-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>
</div>