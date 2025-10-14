<?php
// File: pages/dashboard/trip_list.php

// Panggil variabel yang sudah disiapkan oleh dashboard.php
global $conn, $user_id_from_session, $actual_provider_id; 

// =======================================================================
// LOGIC PENGAMBILAN DATA (Tidak berubah, karena sudah benar)
// =======================================================================

$error = null;
$trips = [];
$provider_id_used = null;

// Pastikan variabel utama sudah tersedia dari dashboard.php.
if (!isset($user_id_from_session) || !$user_id_from_session || !isset($actual_provider_id) || !$actual_provider_id) {
    $error = "Error Otorisasi: ID Pengguna atau Provider tidak tersedia.";
} else {
    $provider_id_used = $actual_provider_id;
}


$verification_status = 'unverified'; // Default status


// Ambil pesan dari session (setelah create/edit/delete)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);


try {
    // 1. Cari Status Verifikasi Provider
    if ($provider_id_used) {
        $stmt_status = $conn->prepare("SELECT verification_status FROM providers WHERE id = ?");
        $stmt_status->bind_param("i", $provider_id_used);
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();
        
        if ($result_status->num_rows > 0) {
            $verification_status = $result_status->fetch_assoc()['verification_status'];
        }
        $stmt_status->close();


        // 2. Ambil data trip (Query sudah benar)
        $stmt = $conn->prepare("SELECT 
                            t.id,
                            t.uuid,       
                            t.title, 
                            t.location,
                            t.start_date, 
                            t.end_date, 
                            t.max_participants,      
                            t.booked_participants,
                            t.price, 
                            t.discount_price, 
                            t.status,
                            t.approval_status,
                            ti.image_url 
                        FROM trips t 
                        LEFT JOIN trip_images ti ON t.id = ti.trip_id AND ti.is_main = 1 
                        WHERE t.provider_id = ?
                        AND t.is_deleted = 0
                        AND t.end_date >= CURDATE()
                        ORDER BY t.created_at ASC");
        
        $stmt->bind_param("i", $provider_id_used);
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
 * Fungsi Pembantu untuk Badge Status Trip oleh Provider (Ditingkatkan)
 */
function get_status_badge($status) {
    switch ($status) {
        case 'published': return '<span class="badge bg-success-subtle text-success border border-success">Aktif</span>';
        case 'draft': return '<span class="badge bg-secondary-subtle text-secondary border border-secondary">Draft</span>';
        case 'closed': return '<span class="badge bg-warning-subtle text-warning border border-warning">Penuh/Tutup</span>';
        case 'cancelled': return '<span class="badge bg-danger-subtle text-danger border border-danger">Dibatalkan</span>';
        default: return '<span class="badge bg-info-subtle text-info border border-info">N/A</span>';
    }
}

/**
 * Fungsi Pembantu untuk Badge Status Persetujuan oleh Admin (Ditingkatkan)
 */
function get_approval_badge($approval_status) {
    switch ($approval_status) {
        case 'approved': return '<span class="badge bg-primary-subtle text-primary border border-primary">Disetujui</span>';
        case 'suspended': return '<span class="badge bg-danger-subtle text-danger border border-danger">Ditangguhkan</span>';
        case 'pending':
        default: return '<span class="badge bg-warning-subtle text-warning border border-warning">Menunggu Persetujuan</span>';
    }
}

/**
 * Fungsi Pembantu untuk format mata uang
 */
function format_rupiah($angka) {
    return 'Rp' . number_format($angka, 0, ',', '.');
}

?>

<h1 class="mb-4 text-primary fw-bold">Manajemen Trip Aktif</h1>
<p class="text-muted">Kelola semua daftar paket perjalanan Anda yang masih aktif dan belum diarsipkan.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between mb-4 flex-wrap">
    
    <?php if ($verification_status === 'verified'): ?>
        <a href="/dashboard?p=trip_create" class="btn btn-lg btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-2"></i> Tambah Trip Baru
        </a>
    <?php else: ?>
        <button type="button" class="btn btn-lg btn-secondary shadow-sm" disabled title="Harap verifikasi profil Anda untuk membuat trip baru">
            <i class="bi bi-lock me-2"></i> Tambah Trip (Verifikasi Dibutuhkan)
        </button>
        <div class="alert alert-warning p-3 m-0 ms-md-3 d-flex align-items-center flex-grow-1 mt-2 mt-md-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> 
            Akun Anda belum diverifikasi. Untuk membuat trip baru, silakan <a href="/dashboard?p=profile" class="alert-link ms-1 fw-bold">lengkapi dan verifikasi</a> profil Anda.
        </div>
    <?php endif; ?>
</div>

<h4 class="mb-3">Daftar Trip (<?php echo count($trips); ?>)</h4>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
    <?php if (empty($trips)): ?>
        <div class="col-12">
            <div class="alert alert-info text-center shadow-sm">
                <i class="bi bi-box me-2"></i> Anda belum memiliki trip aktif. Silakan cek <a href="/dashboard?p=trip_archive" class="alert-link">Arsip Trip</a> Anda.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($trips as $trip): ?>
            <?php 
                $image_path = htmlspecialchars($trip['image_url'] ?? '');
                $default_image = 'assets/default_trip.jpg'; // Pastikan Anda memiliki gambar default ini
                $final_image = (!empty($image_path) && file_exists(__DIR__ . '/../../' . $image_path)) ? '/' . $image_path : '/' . $default_image;
                $is_full = $trip['booked_participants'] >= $trip['max_participants'];
                $progress_bar = round(($trip['booked_participants'] / $trip['max_participants']) * 100);
            ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0 position-relative <?php echo $is_full ? 'border-warning' : 'border-success'; ?>">
                    
                    <div class="position-absolute top-0 end-0 m-2 z-index-1">
                        <?php echo get_approval_badge($trip['approval_status'] ?? 'pending'); ?>
                    </div>

                    <img src="<?php echo $final_image; ?>" 
                         class="card-img-top" 
                         alt="Gambar Trip: <?php echo htmlspecialchars($trip['title']); ?>"
                         style="height: 200px; object-fit: cover;">
                         
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title text-primary fw-bold mb-0">
                                <?php echo htmlspecialchars($trip['title']); ?>
                            </h5>
                            <div>
                                <?php echo get_status_badge($trip['status'] ?? 'draft'); ?>
                            </div>
                        </div>
                        
                        <p class="card-text text-muted mb-3 small">
                            <i class="bi bi-geo-alt-fill me-1"></i> <?php echo htmlspecialchars($trip['location']); ?>
                        </p>

                        <div class="mb-3">
                            <h6 class="text-success fw-bold mb-0">
                                <?php echo format_rupiah($trip['price']); ?>
                            </h6>
                            <?php if ($trip['discount_price'] > 0 && $trip['discount_price'] < $trip['price']): ?>
                                <small class="text-danger text-decoration-line-through">
                                    <?php echo format_rupiah($trip['discount_price']); ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <p class="mb-1 small"><i class="bi bi-calendar me-1"></i> 
                                <b>Jadwal: </b> <?php echo date('d M', strtotime($trip['start_date'])); ?> - 
                                <?php echo date('d M Y', strtotime($trip['end_date'])); ?>
                            </p>
                            <p class="mb-1 small">
                                <i class="bi bi-people-fill me-1"></i> <b>Kuota: </b> <span class="fw-bold <?php echo $is_full ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $trip['booked_participants']; ?>
                                </span> / <?php echo $trip['max_participants']; ?> Terisi
                            </p>
                            
                            <div class="progress mt-1" style="height: 6px;">
                                <div class="progress-bar <?php echo $is_full ? 'bg-danger' : 'bg-success'; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $progress_bar; ?>%" 
                                     aria-valuenow="<?php echo $progress_bar; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto d-flex justify-content-between pt-2 border-top">
                            <a href="/dashboard?p=trip_edit&id=<?php echo htmlspecialchars($trip['uuid']); ?>" class="btn btn-sm btn-outline-primary flex-fill me-2">
                                <i class="bi bi-pencil me-1"></i> Edit Detail
                            </a>
                            <button 
                                type="button" 
                                class="btn btn-sm btn-outline-danger" 
                                title="Arsipkan Trip"
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteTripModal"
                                data-trip-id="<?php echo $trip['id']; ?>"           
                                data-trip-title="<?php echo htmlspecialchars($trip['title']); ?>" 
                            >
                                <i class="bi bi-archive-fill"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="deleteTripModal" tabindex="-1" aria-labelledby="deleteTripModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteTripModalLabel"><i class="bi bi-trash me-2"></i> Konfirmasi Arsip Trip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus trip "<span id="tripTitlePlaceholder" class="fw-bold text-primary"></span>"? 
                Trip ini akan dipindahkan ke **Arsip Trip** dan **dapat dikembalikan** (*Restore*) kapan saja.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <form id="deleteTripForm" action="/process/trip_process" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_trip">
                    <input type="hidden" name="trip_id" id="modalTripId">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-archive-fill me-1"></i> Ya, Arsipkan Trip</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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