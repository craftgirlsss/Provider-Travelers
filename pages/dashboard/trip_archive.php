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
        // Kondisi: Trip belum dihapus (is_deleted = 0) DAN tanggal selesai sudah terlewat (end_date < CURDATE())
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
        // Kondisi: Trip dihapus (is_deleted = 1)
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

<h1 class="h3 mb-4">Riwayat Trip & Arsip</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<ul class="nav nav-tabs" id="archiveTabs" role="tablist">
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

<div class="tab-content border border-top-0 p-3 bg-white shadow-sm">
    
    <div class="tab-pane fade show active" id="completed" role="tabpanel" aria-labelledby="completed-tab">
        <p class="text-muted small">Daftar Trip yang tanggal selesainya telah terlewat (otomatis masuk riwayat).</p>

        <?php if (empty($completed_trips)): ?>
            <div class="alert alert-info text-center m-0">
                Belum ada Trip yang selesai.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Judul Trip</th>
                            <th>Destinasi</th>
                            <th>Periode</th>
                            <th>Harga Dasar</th>
                            <th>Status Persetujuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completed_trips as $trip): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($trip['id']); ?></td>
                            <td><?php echo htmlspecialchars($trip['title']); ?></td>
                            <td><?php echo htmlspecialchars($trip['location']); ?></td>
                            <td><?php echo date('d M Y', strtotime($trip['start_date'])) . " s/d " . date('d M Y', strtotime($trip['end_date'])); ?></td>
                            <td><?php echo "Rp " . number_format($trip['price'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo ($trip['approval_status'] == 'approved') ? 'success' : 
                                         (($trip['approval_status'] == 'pending') ? 'warning' : 'danger');
                                ?>">
                                    <?php echo ucfirst($trip['approval_status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="/dashboard?p=trip_detail&id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">Lihat Detail</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-pane fade" id="deleted" role="tabpanel" aria-labelledby="deleted-tab">
        <p class="text-muted small">Daftar Trip yang Anda hapus atau arsipkan secara manual. Anda dapat mengembalikannya.</p>

        <?php if (empty($deleted_trips)): ?>
            <div class="alert alert-info text-center m-0">
                Belum ada Trip yang dihapus atau diarsipkan manual.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Judul Trip</th>
                            <th>Destinasi</th>
                            <th>Periode</th>
                            <th>Harga Dasar</th>
                            <th>Status Persetujuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deleted_trips as $trip): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($trip['id']); ?></td>
                            <td><?php echo htmlspecialchars($trip['title']); ?></td>
                            <td><?php echo htmlspecialchars($trip['location']); ?></td>
                            <td><?php echo date('d M Y', strtotime($trip['start_date'])) . " s/d " . date('d M Y', strtotime($trip['end_date'])); ?></td>
                            <td><?php echo "Rp " . number_format($trip['price'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge bg-danger">Dihapus</span>
                            </td>
                            <td>
                                <form action="/process/trip_process.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin mengembalikan Trip ini ke daftar aktif?');">
                                    <input type="hidden" name="action" value="restore_trip">
                                    <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-arrow-counterclockwise"></i> Pulihkan
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>