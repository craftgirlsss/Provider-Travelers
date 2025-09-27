<?php
// File: pages/dashboard/trip_list.php
// Halaman ini di-include oleh dashboard.php, sehingga $conn dan $_SESSION sudah tersedia.

// 1. Dapatkan Provider ID
$provider_id = $_SESSION['user_id'];
$trips = [];
$error = null;

try {
    // 2. Query untuk mengambil data trip yang dimiliki provider ini
    $stmt = $conn->prepare("SELECT 
                            id, 
                            title, 
                            location AS location,
                            start_date, 
                            end_date, 
                            max_participants,      
                            booked_participants,
                            price, 
                            discount_price, 
                            status 
                        FROM trips 
                        WHERE provider_id = ? 
                        ORDER BY created_at DESC");
    
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $trips = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
    
} catch (Exception $e) {
    $error = "Gagal memuat data trip: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Status
 */
function get_status_badge($status) {
    switch ($status) {
        case 'published': return '<span class="badge bg-success">Aktif</span>';
        case 'draft': return '<span class="badge bg-secondary">Draft</span>';
        case 'closed': return '<span class="badge bg-warning text-dark">Penuh/Tutup</span>';
        case 'cancelled': return '<span class="badge bg-danger">Dibatalkan</span>';
        default: return '<span class="badge bg-info text-dark">N/A</span>';
    }
}
?>

<h1 class="mb-4">Manajemen Trip</h1>
<p class="text-muted">Kelola semua daftar paket perjalanan yang Anda miliki di sini.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between mb-3">
    <a href="/dashboard?p=trip_create" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i> Tambah Trip Baru</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($trips)): ?>
            <div class="alert alert-info text-center">
                Anda belum memiliki trip. Silakan <a href="/dashboard?p=trip_create" class="alert-link">buat trip pertama Anda</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Trip ID</th>
                            <th>Judul & Tujuan</th>
                            <th>Jadwal</th>
                            <th>Kuota</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td><?php echo $trip['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($trip['title']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($trip['location']); ?></small>
                            </td>
                            <td>
                                <?php echo date('d M Y', strtotime($trip['start_date'])); ?> s/d 
                                <?php echo date('d M Y', strtotime($trip['end_date'])); ?>
                            </td>
                            <td>
                                <strong><?php echo $trip['booked_participants']; ?></strong> / <?php echo $trip['max_participants']; ?>
                            </td>
                            <td>
                                Rp <?php echo number_format($trip['price'], 0, ',', '.'); ?>
                                <?php if ($trip['discount_price'] > 0): ?>
                                    <br><small class="text-danger text-decoration-line-through">Rp <?php echo number_format($trip['discount_price'], 0, ',', '.'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo get_status_badge($trip['status']); ?>
                            </td>
                            <td class="text-center">
                                <a href="/dashboard?p=trip_edit&id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-info text-white me-2" title="Edit Trip"><i class="bi bi-pencil"></i></a>
                                <button type="button" class="btn btn-sm btn-danger" title="Hapus Trip"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>