<?php
// File: pages/dashboard/trip_list.php

// =======================================================================
// Perbaikan Utama: Menggunakan variabel yang sudah di-set di dashboard.php
// $user_id_from_session (int)
// $actual_provider_id (int)
// =======================================================================

// Tambahkan inisialisasi untuk variabel $error agar tidak Undefined.
$error = null;

// Pastikan variabel utama sudah tersedia dari dashboard.php.
if (!isset($user_id_from_session) || !$user_id_from_session || !isset($actual_provider_id) || !$actual_provider_id) {
    // Fallback error, meskipun seharusnya dicegah di dashboard.php
    $error = "Error Otorisasi: ID Pengguna atau Provider tidak tersedia.";
    $provider_id_used = null;
} else {
    $provider_id_used = $actual_provider_id;
}


$verification_status = 'unverified'; // Default status
$trips = [];

// Ambil pesan dari session (setelah create/edit/delete)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);


try {
    // 1. Cari Status Verifikasi Provider
    if ($provider_id_used) {
        $stmt_status = $conn->prepare("SELECT verification_status FROM providers WHERE id = ?");
        $stmt_status->bind_param("i", $provider_id_used); // Gunakan ID provider (integer)
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();
        
        if ($result_status->num_rows > 0) {
            $verification_status = $result_status->fetch_assoc()['verification_status']; // <-- Ambil status verifikasi
        }
        $stmt_status->close();


        // 2. Ambil data trip
        $stmt = $conn->prepare("SELECT 
                            id,
                            uuid,       
                            title, 
                            location AS location,
                            start_date, 
                            end_date, 
                            max_participants,      
                            booked_participants,
                            price, 
                            discount_price, 
                            status,
                            approval_status
                        FROM trips 
                        WHERE provider_id = ?
                        AND is_deleted = 0
                        AND end_date >= CURDATE()
                        ORDER BY created_at ASC");
        
        $stmt->bind_param("i", $provider_id_used); // Gunakan ID provider (integer)
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $trips = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    } else {
        if (!$error) {
             $error = "Data provider Anda tidak ditemukan. Pastikan akun terdaftar di tabel providers.";
        }
    }
    
} catch (Exception $e) {
    $error = "Gagal memuat data trip: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Status Trip oleh Provider
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

/**
 * Fungsi Pembantu untuk Badge Status Persetujuan oleh Admin
 */
function get_approval_badge($approval_status) {
    switch ($approval_status) {
        case 'approved': return '<span class="badge bg-primary">Disetujui</span>';
        case 'suspended': return '<span class="badge bg-danger">Ditangguhkan</span>';
        case 'pending':
        default: return '<span class="badge bg-warning text-dark">Menunggu</span>';
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
    
    <?php if ($verification_status === 'verified'): ?>
        <a href="/dashboard?p=trip_create" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i> Tambah Trip Baru
        </a>
    <?php else: ?>
        <button type="button" class="btn btn-secondary" disabled title="Harap verifikasi profil Anda untuk membuat trip baru">
            <i class="bi bi-lock me-2"></i> Tambah Trip (Verifikasi Dibutuhkan)
        </button>
        <div class="alert alert-info p-2 m-0 ms-3 d-flex align-items-center">
            <i class="bi bi-info-circle me-2"></i> 
            Untuk membuat trip, silakan <a href="/dashboard?p=profile" class="alert-link ms-1 fw-bold">lengkapi dan verifikasi </a> profil Anda.
        </div>
    <?php endif; ?>

</div>

<div class="card shadow-sm mt-4">
    <div class="card-body">
        <?php if (empty($trips)): ?>
            <div class="alert alert-info text-center">
                Anda belum memiliki trip aktif. Silakan cek <a href="/dashboard?p=trip_archive" class="alert-link">Arsip Trip</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Trip ID</th>
                            <th>Judul & Tujuan</th>
                            <th>Jadwal</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th>Status Admin</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td>
                                <span title="<?php echo htmlspecialchars($trip['uuid']); ?>">
                                    #<?php echo strtoupper(substr($trip['uuid'] ?? '', 0, 5)); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($trip['title']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($trip['location']); ?></small>
                            </td>
                            <td>
                                <?php echo date('d M Y', strtotime($trip['start_date'])); ?> s/d 
                                <?php echo date('d M Y', strtotime($trip['end_date'])); ?>
                            </td>
                            <td>
                                Rp <?php echo number_format($trip['price'], 0, ',', '.'); ?>
                                <?php if ($trip['discount_price'] > 0): ?>
                                    <br><small class="text-danger text-decoration-line-through">Rp <?php echo number_format($trip['discount_price'], 0, ',', '.'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo $trip['booked_participants']; ?> / <?php echo $trip['max_participants']; ?> Kuota</small>
                            </td>
                            <td>
                                <?php echo get_approval_badge($trip['approval_status'] ?? 'pending'); ?>
                            </td>
                            <td class="text-center">
                                <a href="/dashboard?p=trip_edit&id=<?php echo htmlspecialchars($trip['uuid']); ?>" class="btn btn-sm btn-info text-white me-2" title="Edit Trip"><i class="bi bi-pencil"></i> Edit</a>
                                
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
// Logic JavaScript/jQuery untuk mengisi ID Trip ke dalam modal (Tidak Berubah)
document.addEventListener('DOMContentLoaded', function() {
    var deleteTripModal = document.getElementById('deleteTripModal');
    
    if (deleteTripModal) {
        deleteTripModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; 
            var tripId = button.getAttribute('data-trip-id');
            var tripTitle = button.getAttribute('data-trip-title');

            var modalTripId = document.getElementById('modalTripId');
            if (modalTripId) {
                modalTripId.value = tripId;
            }

            var tripTitlePlaceholder = document.getElementById('tripTitlePlaceholder');
            if (tripTitlePlaceholder) {
                tripTitlePlaceholder.textContent = tripTitle;
            }
        });
    }
});
</script>