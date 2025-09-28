<?php
// File: pages/dashboard/trip_archive.php
// Pastikan file ini di-include setelah inisialisasi sesi dan koneksi DB

// Cek otorisasi seperti di file lain
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider' || !isset($conn)) {
    echo "<p class='text-danger'>Akses tidak diizinkan atau koneksi database belum siap.</p>";
    return;
}

// Ambil ID Provider yang benar (asumsi ini sudah dilakukan di dashboard/index.php atau di file proses)
// Jika tidak, kita harus mengulang logika dari trip_process.php untuk mendapatkan $actual_provider_id
$user_id_from_session = $_SESSION['user_id'];
$actual_provider_id = null;

try {
    $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("i", $user_id_from_session);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    
    if ($result_provider->num_rows > 0) {
        $row = $result_provider->fetch_assoc();
        $actual_provider_id = $row['id']; 
    }
    $stmt_provider->close();
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error Otorisasi: Gagal mengambil ID provider.</div>";
    return;
}

if (!$actual_provider_id) {
    echo "<div class='alert alert-danger'>Akun provider tidak terdaftar dengan benar.</div>";
    return;
}


// Ambil Trip yang Sudah Dihapus (is_deleted = 1)
try {
    $sql = "SELECT 
                t.id, 
                t.title, 
                t.location, 
                t.start_date, 
                t.price,
                t.updated_at,
                i.image_url
            FROM 
                trips t
            LEFT JOIN 
                trip_images i ON t.id = i.trip_id AND i.is_main = 1
            WHERE 
                t.provider_id = ? AND t.is_deleted = 1
            ORDER BY 
                t.updated_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $actual_provider_id);
    $stmt->execute();
    $result = $stmt->get_result();

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Gagal memuat data arsip trip: " . $e->getMessage() . "</div>";
    $result = null;
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Arsip Trip Saya</h1>
    <a href="/dashboard?p=trips" class="btn btn-secondary">
        <i class="fas fa-chevron-left"></i> Kembali ke Trip Aktif
    </a>
</div>

<?php 
// Tampilkan pesan dari session jika ada
if (isset($_SESSION['dashboard_message'])): ?>
    <div class="alert alert-<?= $_SESSION['dashboard_message_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['dashboard_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
    unset($_SESSION['dashboard_message']);
    unset($_SESSION['dashboard_message_type']);
endif; 
?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 15%;">Gambar</th>
                        <th style="width: 30%;">Judul & Lokasi</th>
                        <th style="width: 15%;">Harga</th>
                        <th style="width: 15%;">Diarsip Pada</th>
                        <th style="width: 20%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): 
                        $counter = 1;
                        while ($trip = $result->fetch_assoc()):
                        // Format harga ke Rupiah
                        $formatted_price = "Rp" . number_format($trip['price'], 0, ',', '.');
                        // Format tanggal arsip
                        $archived_date = (new DateTime($trip['updated_at']))->format('d M Y H:i');
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td>
                                <img src="/<?= $trip['image_url'] ?? 'assets/placeholder.jpg' ?>" 
                                     alt="<?= htmlspecialchars($trip['title']) ?>" 
                                     class="img-fluid rounded" 
                                     style="max-height: 50px; object-fit: cover;">
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($trip['title']) ?></strong>
                                <br><small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($trip['location']) ?></small>
                            </td>
                            <td><?= $formatted_price ?></td>
                            <td><?= $archived_date ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-success btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#restoreTripModal" 
                                        data-trip-id="<?= $trip['id'] ?>"
                                        data-trip-title="<?= htmlspecialchars($trip['title']) ?>">
                                    <i class="fas fa-undo"></i> Restore
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; 
                    else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada trip yang saat ini diarsipkan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Memuat Modal Restore Trip
include_once __DIR__ . '/../../includes/modals/restore_trip_modal.php'; 
?>
