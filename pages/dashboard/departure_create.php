<?php
// File: pages/dashboard/departure_create.php
// Form untuk membuat Jadwal Keberangkatan (Trip Departure) baru.

global $conn; // Koneksi DB
// Ambil $actual_provider_id dari session, yang sudah dijamin di dashboard.php
$actual_provider_id = $_SESSION['actual_provider_id'] ?? 0; 

$error = null;
$trips = [];
$drivers = []; 
$provider_id = $actual_provider_id;

// Ambil data form dari session jika ada error
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); 

if (!$provider_id) {
    $error = "Akses Ditolak: ID Provider tidak ditemukan. Mohon login ulang.";
}

// Ambil semua Trip aktif dan Driver aktif milik Provider
if (!$error) {
    try {
        // Ambil Trips yang sudah approved dan belum dihapus
        $stmt_trips = $conn->prepare("
            SELECT id, title 
            FROM trips 
            WHERE provider_id = ? 
            AND approval_status = 'approved' 
            AND is_deleted = 0 
            ORDER BY title ASC
        ");
        $stmt_trips->bind_param("i", $provider_id);
        $stmt_trips->execute();
        $trips = $stmt_trips->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_trips->close();

        // Ambil Drivers Aktif
        $stmt_drivers = $conn->prepare("
            SELECT id, name 
            FROM drivers 
            WHERE provider_id = ? 
            AND is_active = 1 
            ORDER BY name ASC
        ");
        $stmt_drivers->bind_param("i", $provider_id);
        $stmt_drivers->execute();
        $drivers = $stmt_drivers->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_drivers->close();

    } catch (Exception $e) {
        $error = "Gagal memuat data Trip/Driver: " . $e->getMessage();
    }
}
?>

<h1 class="h3 mb-4">Buat Jadwal Keberangkatan Baru</h1>

<?php 
// Tampilkan pesan error/success dari proses
if (isset($_SESSION['dashboard_message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['dashboard_message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_SESSION['dashboard_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
    unset($_SESSION['dashboard_message']);
    unset($_SESSION['dashboard_message_type']);
endif;
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/process/departure_process.php" method="POST">
            <input type="hidden" name="action" value="create_schedule">

            <div class="mb-3">
                <label for="trip_id" class="form-label">Pilih Trip <span class="text-danger">*</span></label>
                <select class="form-select" id="trip_id" name="trip_id" required 
                    <?php echo empty($trips) ? 'disabled' : ''; ?>>
                    <option value="">-- Pilih Trip Aktif --</option>
                    <?php foreach ($trips as $trip): ?>
                        <option value="<?php echo $trip['id']; ?>" 
                            <?php echo ($form_data['trip_id'] ?? '') == $trip['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($trip['title']); ?> (ID: <?php echo $trip['id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($trips)): ?>
                    <small class="text-danger mt-1 d-block">Anda belum memiliki Trip yang disetujui. Trip harus berstatus "approved" untuk dibuatkan jadwal.</small>
                <?php endif; ?>
            </div>

            <hr>
            
            <div class="row g-3">
                <div class="col-md-6 mb-3">
                    <label for="vehicle_type" class="form-label">Jenis Kendaraan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="vehicle_type" name="vehicle_type" 
                           value="<?php echo htmlspecialchars($form_data['vehicle_type'] ?? ''); ?>" 
                           placeholder="Contoh: Hiace Commuter / ELF Long" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="license_plate" class="form-label">Nomor Polisi (Plat Nomor) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="license_plate" name="license_plate" 
                           value="<?php echo htmlspecialchars($form_data['license_plate'] ?? ''); ?>" 
                           placeholder="Contoh: B 1234 XYZ" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="departure_date" class="form-label">Tanggal Keberangkatan <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="departure_date" name="departure_date" 
                           value="<?php echo htmlspecialchars($form_data['departure_date'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="departure_time" class="form-label">Waktu Keberangkatan <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="departure_time" name="departure_time" 
                           value="<?php echo htmlspecialchars($form_data['departure_time'] ?? ''); ?>" required>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="driver_id" class="form-label">Pilih Driver <span class="text-danger">*</span></label>
                    <select class="form-select" id="driver_id" name="driver_id" required 
                            <?php echo empty($drivers) ? 'disabled' : ''; ?>>
                        <option value="">-- Pilih Driver --</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?php echo $driver['id']; ?>" 
                                <?php echo ($form_data['driver_id'] ?? '') == $driver['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($driver['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($drivers)): ?>
                        <small class="text-danger mt-1 d-block">Anda belum mendaftarkan Driver aktif. Silakan ke <a href="/dashboard?p=driver_create" class="text-decoration-none">Manajemen Driver</a> untuk menambahkan Driver baru.</small>
                    <?php endif; ?>
                </div>

            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Simpan Jadwal</button>
                <a href="/dashboard?p=departures" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>