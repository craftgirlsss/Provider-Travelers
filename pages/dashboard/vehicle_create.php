<?php
// Pastikan variabel global $conn dan $actual_provider_id tersedia.
global $conn, $user_id_from_session, $actual_provider_id; 

// Menggunakan helper verifikasi
require_once __DIR__ . '/../../utils/check_provider_verification.php'; 
check_provider_verification($conn, $actual_provider_id, "Kelola Kendaraan");

// Logika tampilan pesan sukses/error dari proses
// Pastikan pesan yang dikirim dari process/add_vehicle_process.php dapat ditangkap di sini
$message = $_SESSION['vehicle_message'] ?? null;
$message_type = $_SESSION['vehicle_message_type'] ?? 'info';
unset($_SESSION['vehicle_message']);
unset($_SESSION['vehicle_message_type']);
?>

<style>
    .vehicle-form-card {
        border-left: 5px solid #198754; /* Border kiri hijau (success) */
    }
    .form-control:focus, .form-select:focus {
        border-color: #198754;
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <h3 class="fw-bold mb-4 text-success">Tambah Kendaraan Baru</h3>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow vehicle-form-card">
            <div class="card-body p-4">
                <form action="/process/add_vehicle_process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="provider_id" value="<?= htmlspecialchars($actual_provider_id) ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3 text-success border-bottom pb-2"><i class="bi bi-car-front-fill me-2"></i> Detail Teknis & Identitas</h5>

                            <div class="mb-3">
                                <label for="vehicleName" class="form-label fw-bold">Nama/Model Kendaraan</label>
                                <input type="text" class="form-control" id="vehicleName" name="name" required placeholder="Contoh: Toyota Hiace Commuter">
                            </div>

                            <div class="mb-3">
                                <label for="licensePlate" class="form-label fw-bold">Plat Nomor (Contoh: B 1234 ABC)</label>
                                <input type="text" class="form-control text-uppercase" id="licensePlate" name="license_plate" 
                                       required pattern="[A-Z]{1,2}\s\d{1,4}\s[A-Z]{1,3}" 
                                       title="Format Plat Nomor tidak valid. Contoh: B 1234 ABC" 
                                       placeholder="Contoh: B 1234 ABC">
                                <div class="form-text">Gunakan huruf kapital dan spasi yang benar.</div>
                            </div>

                            <div class="mb-3">
                                <label for="capacity" class="form-label fw-bold">Kapasitas Penumpang</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" required min="1" placeholder="Maks. jumlah penumpang">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vehicleType" class="form-label fw-bold">Tipe</label>
                                    <select class="form-select" id="vehicleType" name="type">
                                        <option value="car">Mobil/MPV</option>
                                        <option value="van" selected>Van/Mini Bus</option>
                                        <option value="bus">Bus</option>
                                        <option value="boat">Kapal/Perahu</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="vehicleStatus" class="form-label fw-bold">Status Operasional</label>
                                    <select class="form-select" id="vehicleStatus" name="status">
                                        <option value="available" selected>Tersedia</option>
                                        <option value="maintenance">Perawatan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="mb-3 text-success border-bottom pb-2"><i class="bi bi-image-fill me-2"></i> Foto Utama Kendaraan</h5>
                            <div class="text-center mb-3 p-2 border rounded" style="background-color: #f8f9fa;">
                                <img id="photo-preview" src="/assets/default_vehicle.png" 
                                     alt="Foto Kendaraan" 
                                     class="img-fluid rounded shadow-sm" 
                                     style="max-height: 250px; object-fit: cover; width: 100%; border: 1px solid #dee2e6;">
                            </div>
                            
                            <div class="mb-3">
                                <label for="vehiclePhoto" class="form-label">Upload Foto (Max 5MB, JPG/PNG)</label>
                                <input class="form-control" type="file" id="vehiclePhoto" name="vehicle_photo" accept="image/jpeg, image/png" required>
                                <div class="form-text text-muted">Foto tampak depan atau samping kendaraan yang jelas.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <a href="/dashboard?p=vehicles" class="btn btn-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-success shadow-sm">
                            <i class="bi bi-car-front-fill me-2"></i> Simpan Kendaraan
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