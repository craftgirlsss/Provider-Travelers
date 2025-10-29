<?php
// File: pages/dashboard/departure_schedule.php
// Menampilkan daftar jadwal keberangkatan yang telah dibuat.
global $conn, $user_id_from_session, $actual_provider_id; 

// 1. Sertakan file helper
require_once __DIR__ . '/../../utils/check_provider_verification.php';

// 2. Jalankan Fungsi Validasi
check_provider_verification($conn, $actual_provider_id, "Jadwal Keberangkatan");

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
        case 'Scheduled': return '<span class="badge bg-primary px-3 py-2 fw-semibold">Terjadwal</span>';
        case 'Departed': return '<span class="badge bg-success px-3 py-2 fw-semibold">Berangkat</span>';
        case 'Arrived': return '<span class="badge bg-secondary px-3 py-2 fw-semibold">Tiba</span>';
        case 'Canceled': return '<span class="badge bg-danger px-3 py-2 fw-semibold">Dibatalkan</span>';
        default: return '<span class="badge bg-info text-dark px-3 py-2 fw-semibold">Draft</span>';
    }
}
?>

<style>
    /* ------------------------------------------- */
    /* CSS untuk Card Jadwal Keberangkatan Modern */
    /* ------------------------------------------- */
    .schedule-card {
        border: none;
        border-radius: 12px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        border-left: 6px solid var(--bs-primary); /* Default border color */
    }

    .schedule-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
    }
    
    .schedule-card.status-Departed { border-left-color: #198754; } /* success */
    .schedule-card.status-Canceled { border-left-color: #dc3545; } /* danger */
    .schedule-card.status-Arrived { border-left-color: #6c757d; } /* secondary */
    
    .card-header-trip {
        font-size: 1rem;
        font-weight: 600;
        color: #343a40;
    }

    .time-badge {
        background-color: #f8f9fa;
        color: #000;
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .action-group {
        min-width: 150px;
    }
    
    /* Responsive adjustment */
    @media (max-width: 768px) {
        .time-info {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .card-header-trip {
            font-size: 0.95rem;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <h1 class="h3 fw-bold text-primary">Manajemen Jadwal Keberangkatan</h1>
    <a href="/dashboard?p=departure_create" class="btn btn-primary shadow-sm mt-2 mt-md-0">
        <i class="bi bi-calendar-plus me-2"></i> Tambah Jadwal Baru
    </a>
</div>
<p class="text-muted mb-4">Daftar semua jadwal keberangkatan yang telah Anda buat dan statusnya.</p>


<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php // Tampilkan pesan success/error dari proses (jika ada) ?>
<?php // $message = $_SESSION['dashboard_message'] ?? ''; ... (Tambahkan logic ini jika diperlukan) ?>

<?php if (empty($schedules)): ?>
    <div class="alert alert-info text-center m-0 shadow-sm">
        <i class="bi bi-info-circle me-2"></i> Belum ada jadwal keberangkatan yang dibuat.
    </div>
<?php else: ?>
    <div class="d-grid gap-3">
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
        <div class="card shadow-sm schedule-card status-<?php echo $schedule['status']; ?>">
            <div class="card-body p-3">
                
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    
                    <!-- KIRI: Detail Trip dan Kendaraan -->
                    <div class="flex-grow-1 mb-3 mb-md-0 me-md-4">
                        <span class="text-muted small fw-semibold">TRIP</span>
                        <h5 class="card-header-trip mb-1">
                            <?php echo htmlspecialchars($schedule['trip_title']); ?>
                        </h5>
                        <p class="text-secondary small mb-0">
                            <i class="bi bi-bus-fill me-1"></i> <?php echo htmlspecialchars($schedule['vehicle_type']); ?> (<?php echo htmlspecialchars($schedule['license_plate']); ?>)
                        </p>
                    </div>

                    <!-- TENGAH: Jadwal dan Status -->
                    <div class="time-info d-flex align-items-center me-md-4">
                        <div class="text-center me-4">
                            <div class="small text-muted mb-1">Status</div>
                            <?php echo get_departure_status_badge($schedule['status']); ?>
                        </div>
                        
                        <div class="text-start border-start ps-4">
                            <div class="small text-muted mb-1">Waktu Keberangkatan</div>
                            <div class="time-badge">
                                <i class="bi bi-calendar me-1"></i> <?php echo date('d M Y', strtotime($schedule['departure_date'])); ?><br class="d-md-none">
                                <i class="bi bi-clock me-1"></i> <?php echo date('H:i', strtotime($schedule['departure_time'])); ?> WIB
                            </div>
                        </div>
                    </div>

                    <!-- KANAN: Aksi -->
                    <div class="action-group d-flex gap-2 mt-3 mt-md-0 justify-content-md-end">
                        <a href="/dashboard?p=departure_edit&id=<?php echo $schedule['id']; ?>" 
                           class="btn btn-sm py-2 px-3 <?php echo $edit_btn_class; ?>" 
                           <?php echo $edit_btn_disabled_attr; ?>
                           <?php if ($is_edit_disabled): ?>title="Trip ini sudah H-2 keberangkatan, tidak bisa diedit."<?php endif; ?>>
                            <i class="bi bi-pencil-square"></i> Edit
                        </a>
                        
                        <?php if ($schedule['tracking_link']): ?>
                            <a href="<?php echo htmlspecialchars($schedule['tracking_link']); ?>" target="_blank" class="btn btn-sm py-2 px-3 btn-outline-info">
                                <i class="bi bi-geo-alt"></i> Tracking
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm py-2 px-3 btn-outline-secondary" disabled>
                                <i class="bi bi-geo-alt"></i> No Tracking
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
