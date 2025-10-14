<?php
// File: pages/dashboard/driver_edit.php
// Form untuk mengedit data Driver yang sudah ada.

global $conn; 
$actual_provider_id = $_SESSION['actual_provider_id'] ?? 0;
$driver_id = (int)($_GET['id'] ?? 0);

if (!$actual_provider_id || $driver_id <= 0) {
    echo '<div class="alert alert-danger">Akses Ditolak: ID Driver tidak valid atau otorisasi gagal.</div>';
    exit();
}

// 1. Ambil data driver yang akan diedit
$stmt = $conn->prepare("
    SELECT id, provider_id, name, phone_number, license_number, photo_url, license_photo_url, driver_uuid, is_active 
    FROM drivers 
    WHERE id = ? AND provider_id = ?
");
$stmt->bind_param("ii", $driver_id, $actual_provider_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();
$stmt->close();

if (!$driver) {
    echo '<div class="alert alert-danger">Driver tidak ditemukan atau tidak di bawah otorisasi Anda.</div>';
    exit();
}

// 2. Ambil data user (email) berdasarkan driver_uuid
$stmt_user = $conn->prepare("
    SELECT email, status 
    FROM users 
    WHERE uuid = ? AND role = 'driver'
");
$stmt_user->bind_param("s", $driver['driver_uuid']);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

$driver_email = $user['email'] ?? 'Email tidak ditemukan'; // Digunakan untuk display saja

// Ambil data form dari session jika ada error
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); 

// Gunakan data driver jika tidak ada error form sebelumnya
$data = empty($form_data) ? $driver : array_merge($driver, $form_data);
$is_active_display = (int)$data['is_active'];

?>

<h1 class="h3 mb-4">Edit Driver: <?php echo htmlspecialchars($driver['name']); ?></h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/process/driver_process.php" method="POST" enctype="multipart/form-data" id="driverEditForm">
            <input type="hidden" name="action" value="update_driver">
            <input type="hidden" name="driver_id" value="<?php echo $driver_id; ?>">
            <input type="hidden" name="existing_photo_url" value="<?php echo htmlspecialchars($driver['photo_url'] ?? ''); ?>">
            <input type="hidden" name="existing_license_photo_url" value="<?php echo htmlspecialchars($driver['license_photo_url'] ?? ''); ?>">
            
            <div class="row g-3">
                
                <div class="col-12"><h5 class="text-primary mt-2">1. Detail & Profil Driver</h5></div>

                <div class="col-md-6">
                    <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo htmlspecialchars($data['name']); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="phone_number" class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" 
                           value="<?php echo htmlspecialchars($data['phone_number'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="license_number" class="form-label">Nomor SIM <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="license_number" name="license_number" 
                           value="<?php echo htmlspecialchars($data['license_number'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="is_active" class="form-label">Status Driver <span class="text-danger">*</span></label>
                    <select class="form-select" id="is_active" name="is_active" required>
                        <option value="1" <?php echo $is_active_display == 1 ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo $is_active_display == 0 ? 'selected' : ''; ?>>Non-Aktif/Arsip</option>
                    </select>
                    <small class="text-muted">Status Non-Aktif akan memblokir login Driver ke aplikasi.</small>
                </div>
                
                <div class="col-md-6">
                    <label for="driver_uuid" class="form-label">UUID Driver</label>
                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($driver['driver_uuid']); ?>" readonly>
                    <small class="text-muted">Kode unik untuk integrasi *tracking*.</small>
                </div>
                
                <div class="col-md-6">
                    <label for="driver_email" class="form-label">Email Login Driver</label>
                    <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($driver_email); ?>" readonly>
                    <small class="text-muted">Email ini digunakan untuk login dan notifikasi.</small>
                </div>


                <hr class="mt-4">

                <div class="col-12"><h5 class="text-primary mt-2">2. Ganti Password Login (Opsional)</h5></div>

                <div class="col-md-6">
                    <label for="new_password" class="form-label">Password Baru (Kosongkan jika tidak diganti)</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" value="" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('new_password')">
                            <i class="bi bi-eye" id="icon_new_password"></i>
                        </button>
                    </div>
                    <small class="text-muted">Abaikan jika tidak ingin mengubah password.</small>
                </div>
                
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" value="" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirm_password')">
                            <i class="bi bi-eye" id="icon_confirm_password"></i>
                        </button>
                    </div>
                    <small class="text-muted">Masukkan kembali password baru di atas.</small>
                </div>
                
                <hr class="mt-4">
                
                <div class="col-12"><h5 class="text-primary mt-2">3. Dokumen Driver (Ubah Jika Perlu)</h5></div>

                <div class="col-md-6">
                    <label for="photo_file" class="form-label">Ganti Foto Driver (Saat Ini: <a href="/<?php echo htmlspecialchars($driver['photo_url'] ?? '#'); ?>" target="_blank">Lihat</a>)</label>
                    <input class="form-control" type="file" id="photo_file" name="photo_file" accept="image/*">
                    <small class="text-muted">Unggah file baru jika ingin mengganti foto yang sudah ada.</small>
                </div>

                <div class="col-md-6">
                    <label for="license_photo_file" class="form-label">Ganti Foto SIM Driver (Saat Ini: <a href="/<?php echo htmlspecialchars($driver['license_photo_url'] ?? '#'); ?>" target="_blank">Lihat</a>)</label>
                    <input class="form-control" type="file" id="license_photo_file" name="license_photo_file" accept="image/*">
                    <small class="text-muted">Unggah file baru jika ingin mengganti foto SIM.</small>
                </div>

            </div>
            
            <div class="mt-5">
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-save me-2"></i> Simpan Perubahan</button>
                <a href="/dashboard?p=driver_management" class="btn btn-outline-secondary btn-lg">Kembali ke Daftar</a>
            </div>
        </form>

        <?php if ($is_active_display == 1): ?>
        <form action="/process/driver_process.php" method="POST" class="mt-4" onsubmit="return confirm('Yakin ingin menonaktifkan Driver ini? Driver tidak akan bisa login.');">
            <input type="hidden" name="action" value="deactivate_driver">
            <input type="hidden" name="driver_id" value="<?php echo $driver_id; ?>">
            <button type="submit" class="btn btn-warning"><i class="bi bi-person-x me-2"></i> Non-Aktifkan Driver</button>
        </form>
        <?php else: ?>
        <form action="/process/driver_process.php" method="POST" class="mt-4" onsubmit="return confirm('Yakin ingin mengaktifkan kembali Driver ini?');">
            <input type="hidden" name="action" value="activate_driver">
            <input type="hidden" name="driver_id" value="<?php echo $driver_id; ?>">
            <button type="submit" class="btn btn-success"><i class="bi bi-person-check me-2"></i> Aktifkan Kembali Driver</button>
        </form>
        <?php endif; ?>

    </div>
</div>

<script>
    // Validasi Sisi Klien untuk Pergantian Password
    const form = document.getElementById('driverEditForm');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    form.addEventListener('submit', function(event) {
        const newPass = newPasswordInput.value;
        const confirmPass = confirmPasswordInput.value;

        // Jika salah satu field diisi, pastikan keduanya diisi dan cocok
        if (newPass !== '' || confirmPass !== '') {
            if (newPass.length < 8) {
                alert('Password baru harus memiliki minimal 8 karakter.');
                newPasswordInput.focus();
                event.preventDefault();
                return false;
            }
            if (newPass !== confirmPass) {
                alert('Konfirmasi password tidak cocok dengan password baru.');
                confirmPasswordInput.focus();
                event.preventDefault();
                return false;
            }
        }
        
        return true;
    });

    // Fungsi Show/Hide Password
    function togglePasswordVisibility(id) {
        const input = document.getElementById(id);
        const icon = document.getElementById('icon_' + id);
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>