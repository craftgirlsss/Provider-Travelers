<?php
// File: pages/dashboard/driver_create.php
// Form untuk membuat Driver baru.

global $conn; 
$actual_provider_id = $_SESSION['actual_provider_id'] ?? 0;

if (!$actual_provider_id) {
    echo '<div class="alert alert-danger">Akses Ditolak: ID Provider tidak ditemukan. Mohon login ulang.</div>';
    exit();
}

// Ambil data form dari session jika ada error
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); 
?>

<h1 class="h3 mb-4">Tambah Driver Baru</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/process/driver_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_driver">
            
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="phone_number" class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" 
                           value="<?php echo htmlspecialchars($form_data['phone_number'] ?? ''); ?>" 
                           placeholder="Contoh: 081234567890" required>
                </div>
                
                <div class="col-md-6">
                    <label for="license_number" class="form-label">Nomor SIM <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="license_number" name="license_number" 
                           value="<?php echo htmlspecialchars($form_data['license_number'] ?? ''); ?>" 
                           required>
                </div>

                <div class="col-md-6">
                    <label for="driver_uuid" class="form-label">UUID Driver (Kode Unik) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="driver_uuid" name="driver_uuid" 
                           value="<?php echo htmlspecialchars($form_data['driver_uuid'] ?? (bin2hex(random_bytes(10)))); ?>" 
                           placeholder="Kode unik untuk GPS/Tracking" required readonly>
                    <small class="text-muted">Kode ini dibuat otomatis dan harus unik.</small>
                </div>

                <hr class="mt-4">

                <div class="col-md-6">
                    <label for="photo_file" class="form-label">Foto Driver <span class="text-danger">*</span></label>
                    <input class="form-control" type="file" id="photo_file" name="photo_file" accept="image/*" required>
                    <small class="text-muted">Maksimal 2MB, format JPG/PNG.</small>
                </div>

                <div class="col-md-6">
                    <label for="license_photo_file" class="form-label">Foto SIM Driver <span class="text-danger">*</span></label>
                    <input class="form-control" type="file" id="license_photo_file" name="license_photo_file" accept="image/*" required>
                    <small class="text-muted">Maksimal 2MB, format JPG/PNG.</small>
                </div>

            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Simpan Driver</button>
                <a href="/dashboard?p=driver_management" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>