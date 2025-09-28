<?php
// File: pages/dashboard/trip_list.php
// Halaman ini di-include oleh dashboard.php, sehingga $conn dan $_SESSION sudah tersedia.

// 1. Dapatkan USER ID dari sesi
$user_id_from_session = $_SESSION['user_id'];
$actual_provider_id = null; // ID yang sebenarnya digunakan untuk query ke trips

$trips = [];
$error = null;

// Ambil pesan dari session (setelah create/edit/delete)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

// --- DEBUG OTOARISASI: CEK USER ID YANG DIGUNAKAN ---
// Buka 'View Page Source' di browser. Ini harusnya ID dari tabel 'users'.
echo "<!-- DEBUG OTOARISASI: User ID dari Sesi = " . htmlspecialchars($user_id_from_session) . " -->";
// ----------------------------------------------------


try {
    // 2. Cari ID Provider (Primary Key di tabel 'providers') berdasarkan USER ID dari sesi
    $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("i", $user_id_from_session);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    
    if ($result_provider->num_rows > 0) {
        $row = $result_provider->fetch_assoc();
        $actual_provider_id = $row['id']; // Dapatkan ID Provider yang BENAR (misal: ID 1 untuk PT Jasa Tour Abdimas)
    }
    $stmt_provider->close();

    // 3. Jika ID Provider ditemukan, lanjutkan ambil data trip
    if ($actual_provider_id) {
        echo "<!-- DEBUG OTOARISASI: Provider ID Sejati (PK Providers) = " . htmlspecialchars($actual_provider_id) . " -->";

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
                            AND is_deleted = 0  -- <<< HANYA TAMPILKAN TRIP YANG BELUM DIHAPUS (AKTIF)
                            ORDER BY created_at DESC");
        
        $stmt->bind_param("i", $actual_provider_id); // Mengikat ID Provider yang SEJATI
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $trips = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    } else {
        $error = "Data provider Anda tidak ditemukan. Pastikan akun terdaftar di tabel providers.";
    }
    
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

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between mb-3">
    <a href="/dashboard?p=trip_create" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i> Tambah Trip Baru</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($trips)): ?>
            <div class="alert alert-info text-center">
                Anda belum memiliki trip aktif. Silakan <a href="/dashboard?p=trip_create" class="alert-link">buat trip pertama Anda</a> atau cek <a href="/dashboard?p=trip_archive" class="alert-link">Arsip Trip</a>.
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
                                <a href="/dashboard?p=trip_edit&id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-info text-white me-2" title="Edit Trip"><i class="bi bi-pencil"></i> Edit</a>
                                
                                <!-- Tombol Hapus: Memicu Modal (Ini adalah Soft Delete) -->
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-danger" 
                                    title="Hapus Trip"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteTripModal"
                                    data-trip-id="<?php echo $trip['id']; ?>"           
                                    data-trip-title="<?php echo htmlspecialchars($trip['title']); ?>" 
                                >
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Trip (Ditambahkan di akhir file) -->
<div class="modal fade" id="deleteTripModal" tabindex="-1" aria-labelledby="deleteTripModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteTripModalLabel">Konfirmasi Hapus Trip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus trip "<span id="tripTitlePlaceholder" class="fw-bold"></span>"? 
                Trip ini akan dipindahkan ke **Arsip Trip** dan **dapat dikembalikan** (*Restore*) kapan saja.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <!-- Form untuk mengirim permintaan Hapus ke trip_process.php -->
                <form id="deleteTripForm" action="/process/trip_process" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_trip">
                    <input type="hidden" name="trip_id" id="modalTripId">
                    <button type="submit" class="btn btn-danger">Ya, Arsipkan Trip</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Logic JavaScript/jQuery untuk mengisi ID Trip ke dalam modal
document.addEventListener('DOMContentLoaded', function() {
    // Pastikan Bootstrap dan elemen tersedia
    var deleteTripModal = document.getElementById('deleteTripModal');
    
    if (deleteTripModal) {
        // Ketika modal tampil (event show.bs.modal)
        deleteTripModal.addEventListener('show.bs.modal', function (event) {
            // Ambil tombol yang memicu modal
            var button = event.relatedTarget; 
            
            // Ambil data dari atribut data-* tombol
            var tripId = button.getAttribute('data-trip-id');
            var tripTitle = button.getAttribute('data-trip-title');

            // Update input hidden di form modal (mengirim ID)
            var modalTripId = document.getElementById('modalTripId');
            if (modalTripId) {
                modalTripId.value = tripId;
            }

            // Update placeholder Judul Trip (untuk konfirmasi visual)
            var tripTitlePlaceholder = document.getElementById('tripTitlePlaceholder');
            if (tripTitlePlaceholder) {
                tripTitlePlaceholder.textContent = tripTitle;
            }
        });
    }
});
</script>
