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

<?php 
// Ambil pesan dari session (setelah create/edit/delete)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);
if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <a href="/dashboard?p=driver_management" class="btn btn-secondary">Kembali ke Daftar Driver</a>
<?php elseif (!$driver_data): ?>
    <div class="alert alert-warning">Driver tidak ditemukan.</div>
    <a href="/dashboard?p=driver_management" class="btn btn-secondary">Kembali ke Daftar Driver</a>
<?php else: ?>

<div class="d-flex justify-content-end mb-3">
    <?php if (($driver_data['is_active'] ?? 1) == 1): ?>
        <button 
            type="button" 
            class="btn btn-danger btn-sm" 
            data-bs-toggle="modal" 
            data-bs-target="#archiveDriverModal"
        >
            <i class="bi bi-archive"></i> Arsipkan Driver
        </button>
    <?php else: ?>
        <button 
            type="button" 
            class="btn btn-success btn-sm" 
            data-bs-toggle="modal" 
            data-bs-target="#restoreDriverModal"
        >
            <i class="bi bi-arrow-counterclockwise"></i> Aktifkan Kembali Driver
        </button>
    <?php endif; ?>
</div>

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

<div class="modal fade" id="archiveDriverModal" tabindex="-1" aria-labelledby="archiveDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="archiveDriverModalLabel">Konfirmasi Arsip Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Anda akan menonaktifkan driver <b><?php echo htmlspecialchars($driver_data['name']); ?></b>.
                Driver yang dinonaktifkan tidak akan muncul di daftar driver aktif dan tidak dapat dipilih untuk jadwal keberangkatan.
                <p class="mt-2 fw-bold text-danger">Apakah Anda yakin ingin melanjutkan?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <form action="/process/driver_process.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="deactivate_driver">
                    <input type="hidden" name="driver_id" value="<?php echo $driver_data['id']; ?>">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-archive"></i> Ya, Arsipkan Driver</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="restoreDriverModal" tabindex="-1" aria-labelledby="restoreDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="restoreDriverModalLabel">Konfirmasi Aktifkan Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Anda akan mengaktifkan kembali driver **<?php echo htmlspecialchars($driver_data['name']); ?>**.
                Driver akan muncul kembali di daftar driver aktif dan dapat dipilih untuk jadwal keberangkatan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <form action="/process/driver_process.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="activate_driver">
                    <input type="hidden" name="driver_id" value="<?php echo $driver_data['id']; ?>">
                    <button type="submit" class="btn btn-success"><i class="bi bi-arrow-counterclockwise"></i> Ya, Aktifkan Kembali</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>