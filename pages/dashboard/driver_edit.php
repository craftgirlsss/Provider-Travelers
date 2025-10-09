<?php
// File: pages/dashboard/driver_edit.php
// Form untuk mengedit data driver.

global $conn;
$actual_provider_id = $_SESSION['actual_provider_id'] ?? 0; 
$driver_id = (int)($_GET['id'] ?? 0);

$driver_data = null;
$error = null;

if (!$actual_provider_id || $driver_id <= 0) {
    $error = "ID Driver tidak valid atau otorisasi gagal.";
}

// Ambil data driver berdasarkan ID dan provider_id (untuk otorisasi)
if (!$error) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                id, name, phone_number, license_number, photo_url, license_photo_url, is_active, driver_uuid
            FROM 
                drivers 
            WHERE 
                id = ? AND provider_id = ?
        ");
        $stmt->bind_param("ii", $driver_id, $actual_provider_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $driver_data = $result->fetch_assoc();
        } else {
            $error = "Driver tidak ditemukan atau bukan milik Anda.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Gagal memuat data driver: " . $e->getMessage();
    }
}

// Jika ada data form yang tersimpan di sesi setelah gagal submit
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); 

// Gunakan data driver jika tidak ada form data error
if (!$form_data && $driver_data) {
    $form_data = $driver_data;
}
?>

<h1 class="h3 mb-4">Edit Driver: <?php echo htmlspecialchars($driver_data['name'] ?? 'Tidak Ditemukan'); ?></h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <a href="/dashboard?p=driver_management" class="btn btn-secondary">Kembali ke Daftar Driver</a>
<?php elseif (!$driver_data): ?>
    <div class="alert alert-warning">Driver tidak ditemukan.</div>
    <a href="/dashboard?p=driver_management" class="btn btn-secondary">Kembali ke Daftar Driver</a>
<?php else: ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/process/driver_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_driver">
            <input type="hidden" name="driver_id" value="<?php echo $driver_data['id']; ?>">
            
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="phone_number" class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" 
                           value="<?php echo htmlspecialchars($form_data['phone_number'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="license_number" class="form-label">Nomor SIM <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="license_number" name="license_number" 
                           value="<?php echo htmlspecialchars($form_data['license_number'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="is_active" class="form-label">Status Driver <span class="text-danger">*</span></label>
                    <select class="form-select" id="is_active" name="is_active" required>
                        <option value="1" <?php echo ($form_data['is_active'] ?? 1) == 1 ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo ($form_data['is_active'] ?? 1) == 0 ? 'selected' : ''; ?>>Tidak Aktif</option>
                    </select>
                </div>

                <hr class="mt-4">
                <p class="fw-bold">Ganti Dokumen (Opsional: Kosongkan jika tidak ingin diubah)</p>
                
                <div class="col-md-6">
                    <label for="photo_file" class="form-label">Foto Driver Baru</label>
                    <input class="form-control" type="file" id="photo_file" name="photo_file" accept="image/*">
                    <small class="text-muted">Foto lama: 
                        <a href="<?php echo htmlspecialchars('/' . $driver_data['photo_url']); ?>" target="_blank">Lihat</a>
                    </small>
                    <input type="hidden" name="existing_photo_url" value="<?php echo htmlspecialchars($driver_data['photo_url']); ?>">
                </div>

                <div class="col-md-6">
                    <label for="license_photo_file" class="form-label">Foto SIM Driver Baru</label>
                    <input class="form-control" type="file" id="license_photo_file" name="license_photo_file" accept="image/*">
                    <small class="text-muted">Foto SIM lama: 
                        <a href="<?php echo htmlspecialchars('/' . $driver_data['license_photo_url']); ?>" target="_blank">Lihat</a>
                    </small>
                    <input type="hidden" name="existing_license_photo_url" value="<?php echo htmlspecialchars($driver_data['license_photo_url']); ?>">
                </div>
                
                <input type="hidden" name="driver_uuid" value="<?php echo htmlspecialchars($driver_data['driver_uuid']); ?>">

            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Simpan Perubahan</button>
                <a href="/dashboard?p=driver_management" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>