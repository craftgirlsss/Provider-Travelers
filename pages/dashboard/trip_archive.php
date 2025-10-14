<?php
// File: pages/dashboard/trip_archive.php
// Menampilkan daftar Trip yang sudah terlewat tanggalnya (Riwayat Selesai)
// dan Trip yang sudah dihapus/diarsipkan manual (Trip Dihapus).

global $conn, $actual_provider_id;

$error = null;
$completed_trips = []; // Trip yang sudah selesai (end_date < CURDATE())
$deleted_trips = [];    // Trip yang dihapus/diarsipkan manual (is_deleted = 1)
$provider_id = $actual_provider_id ?? 0;

if (!$provider_id) {
    $error = "Akses Ditolak: ID Provider tidak ditemukan. Mohon login ulang.";
}

if (!$error) {
    try {
        // ==========================================================
        // 1. QUERY: RIWAYAT TRIP SELESAI (Completed Trips)
        // ==========================================================
        $sql_completed = "
            SELECT 
                id, title, location, start_date, end_date, price, approval_status
            FROM 
                trips 
            WHERE 
                provider_id = ? 
                AND is_deleted = 0 
                AND end_date < CURDATE()
            ORDER BY 
                end_date DESC
        ";
            
        $stmt_completed = $conn->prepare($sql_completed);
        $stmt_completed->bind_param("i", $provider_id);
        $stmt_completed->execute();
        $completed_trips = $stmt_completed->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_completed->close();


        // ==========================================================
        // 2. QUERY: TRIP DIHAPUS MANUAL (Deleted/Archived Trips)
        // ==========================================================
        $sql_deleted = "
            SELECT 
                id, title, location, start_date, end_date, price, approval_status
            FROM 
                trips 
            WHERE 
                provider_id = ? 
                AND is_deleted = 1
            ORDER BY 
                end_date DESC
        ";
            
        $stmt_deleted = $conn->prepare($sql_deleted);
        $stmt_deleted->bind_param("i", $provider_id);
        $stmt_deleted->execute();
        $deleted_trips = $stmt_deleted->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_deleted->close();

    } catch (Exception $e) {
        $error = "Gagal memuat riwayat trip: " . $e->getMessage();
    }
}
?>

<style>
    /* Styling Card Modern Tanpa Foto */
    .trip-list-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-left: 4px solid; /* Border untuk status */
        border-radius: 8px;
        background-color: #ffffff;
    }
    .trip-list-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.3rem 0.8rem rgba(0, 0, 0, 0.1) !important;
    }
    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .trip-info-item {
        font-size: 0.85rem;
        color: #6c757d; /* text-secondary */
    }
    .status-area {
        min-width: 100px;
    }
    .price-tag {
        font-size: 1rem;
        font-weight: bold;
        color: var(--bs-primary);
    }

    /* Warna border untuk status */
    .border-approved { border-left-color: #198754 !important; } /* success */
    .border-pending { border-left-color: #ffc107 !important; } /* warning */
    .border-rejected { border-left-color: #dc3545 !important; } /* danger */
    .border-deleted { border-left-color: #6c757d !important; } /* secondary */
</style>

<h1 class="h3 mb-4">Riwayat Trip & Arsip</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="archiveTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="true">
            <i class="bi bi-clock-history me-1"></i> Riwayat Trip Selesai (<?php echo count($completed_trips); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="deleted-tab" data-bs-toggle="tab" data-bs-target="#deleted" type="button" role="tab" aria-controls="deleted" aria-selected="false">
            <i class="bi bi-trash me-1"></i> Trip Dihapus (<?php echo count($deleted_trips); ?>)
        </button>
    </li>
</ul>

<div class="tab-content">
    
    <div class="tab-pane fade show active" id="completed" role="tabpanel" aria-labelledby="completed-tab">
        <p class="text-muted small mb-3">Daftar Trip yang tanggal selesainya telah terlewat (otomatis masuk riwayat).</p>

        <?php if (empty($completed_trips)): ?>
            <div class="alert alert-info text-center m-0 shadow-sm">
                <i class="bi bi-info-circle me-2"></i> Belum ada Trip yang selesai.
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($completed_trips as $trip): 
                    $status_text = ucfirst($trip['approval_status']);
                    $status_class = 'border-' . strtolower($trip['approval_status']);
                    $badge_bg = ($trip['approval_status'] == 'approved') ? 'bg-success' : 
                                 (($trip['approval_status'] == 'pending') ? 'bg-warning text-dark' : 'bg-danger');
                ?>
                <div class="p-3 shadow-sm trip-list-card d-flex align-items-center <?php echo $status_class; ?>">
                    
                    <div class="flex-grow-1 me-3">
                        <h5 class="card-title text-dark"><?php echo htmlspecialchars($trip['title']); ?></h5>
                        <div class="trip-info-item">
                            <i class="bi bi-geo-alt-fill me-1"></i> <?php echo htmlspecialchars($trip['location']); ?>
                            <span class="text-muted ms-3">ID: #<?php echo htmlspecialchars($trip['id']); ?></span>
                        </div>
                    </div>
                    
                    <div class="text-nowrap me-3 d-none d-md-block" style="min-width: 200px;">
                        <div class="trip-info-item">
                            <i class="bi bi-calendar-check me-1"></i> Periode:
                        </div>
                        <div class="fw-bold text-success small">
                            <?php echo date('d M Y', strtotime($trip['start_date'])) . " - " . date('d M Y', strtotime($trip['end_date'])); ?>
                        </div>
                    </div>

                    <div class="text-end me-3 d-none d-sm-block" style="min-width: 120px;">
                        <div class="trip-info-item">Harga Dasar:</div>
                        <div class="price-tag"><?php echo "Rp " . number_format($trip['price'], 0, ',', '.'); ?></div>
                    </div>
                    
                    <div class="status-area text-center me-3">
                        <span class="badge <?php echo $badge_bg; ?> py-2 px-3">
                            <?php echo $status_text; ?>
                        </span>
                    </div>

                    <div class="text-end">
                        <a href="/dashboard?p=trip_detail&id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Detail
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-pane fade" id="deleted" role="tabpanel" aria-labelledby="deleted-tab">
        <p class="text-muted small mb-3">Daftar Trip yang Anda hapus atau arsipkan secara manual. Anda dapat mengembalikannya.</p>

        <?php if (empty($deleted_trips)): ?>
            <div class="alert alert-info text-center m-0 shadow-sm">
                <i class="bi bi-info-circle me-2"></i> Belum ada Trip yang dihapus atau diarsipkan manual.
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($deleted_trips as $trip): ?>
                <div class="p-3 shadow-sm trip-list-card d-flex align-items-center border-deleted">
                    
                    <div class="flex-grow-1 me-3">
                        <h5 class="card-title text-dark"><?php echo htmlspecialchars($trip['title']); ?></h5>
                        <div class="trip-info-item">
                            <i class="bi bi-geo-alt-fill me-1"></i> <?php echo htmlspecialchars($trip['location']); ?>
                            <span class="text-muted ms-3">ID: #<?php echo htmlspecialchars($trip['id']); ?></span>
                        </div>
                    </div>
                    
                    <div class="text-nowrap me-3 d-none d-md-block" style="min-width: 200px;">
                        <div class="trip-info-item">
                            <i class="bi bi-calendar-x me-1"></i> Periode Hapus:
                        </div>
                        <div class="fw-bold text-danger small">
                            <?php echo date('d M Y', strtotime($trip['start_date'])) . " - " . date('d M Y', strtotime($trip['end_date'])); ?>
                        </div>
                    </div>

                    <div class="text-end me-3 d-none d-sm-block" style="min-width: 120px;">
                        <div class="trip-info-item">Harga Dasar:</div>
                        <div class="price-tag"><?php echo "Rp " . number_format($trip['price'], 0, ',', '.'); ?></div>
                    </div>

                    <div class="status-area text-center me-3">
                        <span class="badge bg-secondary py-2 px-3">
                            Dihapus
                        </span>
                    </div>
                    
                    <div class="text-end">
                        <form action="/process/trip_process.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin mengembalikan Trip ini ke daftar aktif?');">
                            <input type="hidden" name="action" value="restore_trip">
                            <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-arrow-counterclockwise"></i> Pulihkan
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>