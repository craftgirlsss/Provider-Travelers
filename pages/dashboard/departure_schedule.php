<?php
// File: pages/dashboard/departure_schedule.php
// Menampilkan daftar jadwal keberangkatan yang telah dibuat.

global $conn, $actual_provider_id;

$error = null;
$schedules = [];
$provider_id = $actual_provider_id ?? 0;

if (!$provider_id) {
    $error = "Akses Ditolak: ID Provider tidak ditemukan. Mohon login ulang.";
}

if (!$error) {
    try {
        $sql = "
            SELECT 
                td.id, td.vehicle_type, td.license_plate, td.departure_date, td.departure_time, td.status,
                t.title AS trip_title, td.tracking_link
            FROM 
                trip_departures td
            JOIN 
                trips t ON td.trip_id = t.id
            WHERE 
                td.provider_id = ?
            ORDER BY 
                td.departure_date DESC, td.departure_time DESC";
            
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $provider_id);
        $stmt->execute();
        $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        $error = "Gagal memuat jadwal keberangkatan: " . $e->getMessage();
    }
}

function get_departure_status_badge($status) {
    switch ($status) {
        case 'Scheduled': return '<span class="badge bg-primary">Terjadwal</span>';
        case 'Departed': return '<span class="badge bg-success">Berangkat</span>';
        case 'Arrived': return '<span class="badge bg-secondary">Tiba</span>';
        case 'Canceled': return '<span class="badge bg-danger">Dibatalkan</span>';
        default: return '<span class="badge bg-info text-dark">Draft</span>';
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Manajemen Jadwal Keberangkatan</h1>
    <a href="/dashboard?p=departure_create" class="btn btn-primary">
        <i class="bi bi-calendar-plus me-2"></i> Tambah Jadwal Baru
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php // Tampilkan pesan success/error dari proses (jika ada) ?>
<?php // $message = $_SESSION['dashboard_message'] ?? ''; ... (Tambahkan logic ini jika diperlukan) ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($schedules)): ?>
            <div class="alert alert-info text-center m-0">
                Belum ada jadwal keberangkatan yang dibuat.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Trip</th>
                            <th>Jadwal</th>
                            <th>Kendaraan</th>
                            <th>Nomor Polisi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Dapatkan waktu saat ini
                        $current_time = time();
                        ?>
                        <?php foreach ($schedules as $schedule): 
                            // 1. Hitung Waktu Keberangkatan Penuh (Timestamp)
                            $departure_datetime = strtotime($schedule['departure_date'] . ' ' . $schedule['departure_time']);

                            // 2. Hitung Batas Waktu Edit (Waktu Keberangkatan dikurangi 48 jam)
                            // 48 * 3600 adalah jumlah detik dalam 48 jam
                            $edit_deadline_time = $departure_datetime - (48 * 3600);

                            // 3. Tentukan apakah tombol Edit harus dinonaktifkan
                            // Jika waktu saat ini SUDAH MELEWATI batas waktu edit (H-2)
                            $is_edit_disabled = $current_time > $edit_deadline_time;

                            // Tentukan kelas tombol dan atribut disabled
                            $edit_btn_class = $is_edit_disabled ? 'btn-secondary' : 'btn-outline-warning';
                            $edit_btn_disabled_attr = $is_edit_disabled ? 'disabled' : '';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($schedule['trip_title']); ?></td>
                            <td><?php echo date('d M Y', strtotime($schedule['departure_date'])) . ' ' . date('H:i', strtotime($schedule['departure_time'])); ?></td>
                            <td><?php echo htmlspecialchars($schedule['vehicle_type']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['license_plate']); ?></td>
                            <td><?php echo get_departure_status_badge($schedule['status']); ?></td>
                            <td class="text-nowrap">
                                <a href="/dashboard?p=departure_edit&id=<?php echo $schedule['id']; ?>" 
                                   class="btn btn-sm <?php echo $edit_btn_class; ?> me-1" 
                                   <?php echo $edit_btn_disabled_attr; ?>
                                   <?php if ($is_edit_disabled): ?>title="Trip ini sudah H-2 keberangkatan, tidak bisa diedit."<?php endif; ?>>
                                    Edit
                                </a>
                                
                                <?php if ($schedule['tracking_link']): ?>
                                    <a href="<?php echo htmlspecialchars($schedule['tracking_link']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-geo-alt"></i> Tracking
                                    </a>
                                <?php else: ?>
                                     <button class="btn btn-sm btn-outline-secondary" disabled>No Tracking</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>