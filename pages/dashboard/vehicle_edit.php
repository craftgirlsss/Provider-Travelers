<?php
// Pastikan variabel global $conn dan $actual_provider_id tersedia.
global $conn, $user_id_from_session, $actual_provider_id; 

// Menggunakan helper verifikasi
require_once __DIR__ . '/../../utils/check_provider_verification.php'; 
check_provider_verification($conn, $actual_provider_id, "Edit Kendaraan");

$vehicle_id = (int)($_GET['id'] ?? 0);

if ($vehicle_id === 0) {
    echo "<div class='alert alert-danger'>ID Kendaraan tidak valid.</div>";
    return;
}

$vehicle = null;
$default_photo = '/assets/default_vehicle.png';

// 1. Ambil Data Kendaraan yang Akan Diedit
try {
    // Pastikan kendaraan dimiliki oleh Provider yang sedang login
    $stmt = $conn->prepare("SELECT id, name, license_plate, capacity, type, status, vehicle_photo_path FROM vehicles WHERE id = ? AND provider_id = ?");
    $stmt->bind_param("ii", $vehicle_id, $actual_provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();
    $stmt->close();

    if (!$vehicle) {
        echo "<div class='alert alert-danger'>Kendaraan tidak ditemukan atau Anda tidak memiliki akses untuk mengedit.</div>";
        return;
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Gagal memuat data kendaraan: " . htmlspecialchars($e->getMessage()) . "</div>";
    return;
}

// Logika tampilan pesan sukses/error dari proses update
$message = $_SESSION['vehicle_message'] ?? null;
$message_type = $_SESSION['vehicle_message_type'] ?? 'info';
unset($_SESSION['vehicle_message']);
unset($_SESSION['vehicle_message_type']);

// Tentukan path foto saat ini
$current_photo_src = (!empty($vehicle['vehicle_photo_path']) && file_exists(__DIR__ . '/../../' . $vehicle['vehicle_photo_path'])) 
                     ? '/' . $vehicle['vehicle_photo_path'] 
                     : $default_photo;
?>

<style>
    .vehicle-form-card {
        border-left: 5px solid #ffc107; /* Border kiri kuning (warning/edit) */
    }
    .form-control:focus, .form-select:focus {
        border-color: #ffc107;
        box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <h3 class="fw-bold mb-4 text-warning"><i class="bi bi-pencil-square me-2"></i> Edit Kendaraan: <?= htmlspecialchars($vehicle['name']) ?></h3>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow vehicle-form-card">
            <div class="card-body p-4">
                <form action="/process/update_vehicle_process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($vehicle['id']) ?>">
                    <input type="hidden" name="old_photo_path" value="<?= htmlspecialchars($vehicle['vehicle_photo_path']) ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3 text-warning border-bottom pb-2">Detail Teknis & Identitas</h5>

                            <div class="mb-3">
                                <label for="vehicleName" class="form-label fw-bold">Nama/Model Kendaraan</label>
                                <input type="text" class="form-control" id="vehicleName" name="name" required value="<?= htmlspecialchars($vehicle['name']) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="licensePlate" class="form-label fw-bold">Plat Nomor (Contoh: B 1234 ABC)</label>
                                <input type="text" class="form-control text-uppercase" id="licensePlate" name="license_plate" 
                                       required pattern="[A-Z]{1,2}\s\d{1,4}\s[A-Z]{1,3}" 
                                       title="Format Plat Nomor tidak valid. Contoh: B 1234 ABC" 
                                       placeholder="Contoh: B 1234 ABC"
                                       value="<?= htmlspecialchars($vehicle['license_plate']) ?>">
                                <div class="form-text">Gunakan huruf kapital dan spasi yang benar.</div>
                            </div>

                            <div class="mb-3">
                                <label for="capacity" class="form-label fw-bold">Kapasitas Penumpang</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" required min="1" 
                                       placeholder="Maks. jumlah penumpang" value="<?= htmlspecialchars($vehicle['capacity']) ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vehicleType" class="form-label fw-bold">Tipe</label>
                                    <select class="form-select" id="vehicleType" name="type">
                                        <?php 
                                            $types = ['car' => 'Mobil/MPV', 'van' => 'Van/Mini Bus', 'bus' => 'Bus', 'boat' => 'Kapal/Perahu', 'other' => 'Lainnya'];
                                            foreach ($types as $val => $label) {
                                                $selected = ($vehicle['type'] === $val) ? 'selected' : '';
                                                echo "<option value='{$val}' {$selected}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="vehicleStatus" class="form-label fw-bold">Status Operasional</label>
                                    <select class="form-select" id="vehicleStatus" name="status">
                                        <option value="available" <?= ($vehicle['status'] === 'available') ? 'selected' : '' ?>>Tersedia</option>
                                        <option value="maintenance" <?= ($vehicle['status'] === 'maintenance') ? 'selected' : '' ?>>Perawatan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="mb-3 text-warning border-bottom pb-2">Foto Utama Kendaraan</h5>
                            <div class="text-center mb-3 p-2 border rounded" style="background-color: #f8f9fa;">
                                <img id="photo-preview" src="<?= $current_photo_src ?>" 
                                     alt="Foto Kendaraan" 
                                     class="img-fluid rounded shadow-sm" 
                                     style="max-height: 250px; object-fit: cover; width: 100%; border: 1px solid #dee2e6;">
                            </div>
                            
                            <div class="mb-3">
                                <label for="vehiclePhoto" class="form-label">Ganti Foto (Opsional, Max 5MB)</label>
                                <input class="form-control" type="file" id="vehiclePhoto" name="vehicle_photo" accept="image/jpeg, image/png">
                                <div class="form-text text-muted">Biarkan kosong jika tidak ingin mengganti foto.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <a href="/dashboard?p=vehicles" class="btn btn-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-warning text-dark shadow-sm">
                            <i class="bi bi-arrow-repeat me-2"></i> Update Kendaraan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Script untuk preview foto yang diupload
    document.getElementById('vehiclePhoto').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photo-preview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
</script>