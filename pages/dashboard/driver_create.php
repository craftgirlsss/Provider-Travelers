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

/**
 * Fungsi untuk menghasilkan password acak yang aman.
 * Panjang 8 karakter, mengandung huruf kapital, huruf kecil, angka, dan simbol.
 * @return string Password yang dihasilkan.
 */
function generate_secure_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    $caps = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $nums = '0123456789';
    $symbols = '!@#$%^&*()-_+=';

    // Pastikan password mengandung minimal satu dari setiap jenis karakter
    $password = [];
    $password[] = $chars[rand(0, strlen($chars) - 1)];
    $password[] = $caps[rand(0, strlen($caps) - 1)];
    $password[] = $nums[rand(0, strlen($nums) - 1)];
    $password[] = $symbols[rand(0, strlen($symbols) - 1)];

    // Gabungkan semua karakter yang mungkin
    $all = $chars . $caps . $nums . $symbols;
    $all_length = strlen($all);

    // Isi sisa panjang password
    for ($i = count($password); $i < $length; $i++) {
        $password[] = $all[rand(0, $all_length - 1)];
    }

    // Acak urutan karakter
    shuffle($password);

    return implode($password);
}

// Hasilkan password baru (otomatis)
$generated_password = generate_secure_password(8); 

// Ambil data UUID dari form_data atau generate baru
$generated_uuid = $form_data['driver_uuid'] ?? (bin2hex(random_bytes(10)));
// Ambil email yang sudah diinput jika ada error sebelumnya
$manual_email = $form_data['email'] ?? '';

?>

<h1 class="h3 mb-4">Tambah Driver Baru</h1>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i> **Penting:** Sistem akan otomatis membuat akun pengguna (`users` table) untuk Driver ini. Kredensial *login* aplikasi Driver harus dicatat dan diberikan kepada Driver.
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/process/driver_process.php" method="POST" enctype="multipart/form-data" id="driverCreateForm">
            <input type="hidden" name="action" value="create_driver">
            
            <div class="row g-3">
                
                <div class="col-12"><h5 class="text-primary mt-2">1. Detail Driver</h5></div>

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
                    <input type="text" class="form-control bg-light" id="driver_uuid" name="driver_uuid" 
                           value="<?php echo htmlspecialchars($generated_uuid); ?>" 
                           placeholder="Kode unik untuk GPS/Tracking" required readonly>
                    <small class="text-muted">Kode ini dibuat otomatis dan harus unik untuk mengidentifikasi perangkat GPS.</small>
                </div>

                <hr class="mt-4">

                <div class="col-12"><h5 class="text-primary mt-2">2. Kredensial Login Aplikasi Driver</h5></div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email Login Driver <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($manual_email); ?>" 
                           placeholder="nama.driver@karyadeveloperindonesia.com" required>
                    <small class="text-muted" id="emailHelp">Domain harus **@karyadeveloperindonesia.com**.</small>
                </div>
                
                <div class="col-md-6">
                    <label for="password_display" class="form-label">Password Login (Otomatis) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-light" id="password_display" 
                               value="<?php echo htmlspecialchars($generated_password); ?>" required readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyPasswordToClipboard('password_display')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <small class="text-muted">Password yang aman (8 karakter: A, a, 1, @).</small>
                    
                    <input type="hidden" name="generated_password" value="<?php echo htmlspecialchars($generated_password); ?>">
                </div>
                
                <input type="hidden" name="generated_email" value="<?php echo htmlspecialchars($manual_email); ?>">

                <hr class="mt-4">
                
                <div class="col-12"><h5 class="text-primary mt-2">3. Dokumen Driver</h5></div>

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
            
            <div class="mt-5">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-person-plus me-2"></i> Simpan Driver</button>
                <a href="/dashboard?p=driver_management" class="btn btn-outline-secondary btn-lg">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
    const REQUIRED_DOMAIN = '@karyadeveloperindonesia.com';
    const emailInput = document.getElementById('email');
    const emailHelp = document.getElementById('emailHelp');
    const form = document.getElementById('driverCreateForm');

    // Fungsi Validasi Domain
    function validateEmailDomain() {
        const emailValue = emailInput.value.trim();
        
        // Cek apakah email kosong
        if (emailValue === '') {
            emailInput.classList.remove('is-invalid');
            emailHelp.textContent = `Domain harus ${REQUIRED_DOMAIN}.`;
            return true;
        }

        // Cek domain email
        if (!emailValue.endsWith(REQUIRED_DOMAIN)) {
            emailInput.classList.add('is-invalid');
            emailHelp.textContent = `Email tidak valid. Domain wajib ${REQUIRED_DOMAIN}.`;
            return false;
        } else {
            emailInput.classList.remove('is-invalid');
            emailHelp.textContent = `Domain harus ${REQUIRED_DOMAIN}.`;
            return true;
        }
    }

    // Listener saat input email berubah dan saat form disubmit
    emailInput.addEventListener('blur', validateEmailDomain);
    form.addEventListener('submit', function(event) {
        if (!validateEmailDomain()) {
            event.preventDefault(); // Mencegah submit jika validasi gagal
            alert(`Harap gunakan domain email ${REQUIRED_DOMAIN}.`);
        }
    });

    // Fungsi untuk menyalin password ke clipboard (tidak berubah)
    function copyPasswordToClipboard(elementId) {
        const copyText = document.getElementById(elementId);
        
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            const btn = copyText.nextElementSibling;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Disalin!';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
            
        } catch (err) {
            console.error('Gagal menyalin:', err);
        }
    }

    // Pastikan UUID tidak berubah ketika terjadi validasi error
    document.getElementById('driver_uuid').value = '<?php echo $generated_uuid; ?>';
</script>