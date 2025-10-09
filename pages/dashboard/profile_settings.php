<?php
// File: pages/dashboard/profile_settings.php
// Di-include oleh dashboard.php

// MENGHILANGKAN: $user_id_from_session = $_SESSION['user_id'];
// MENGGUNAKAN: Variabel yang sudah tersedia dari dashboard.php
// $user_id_from_session (int)
// $actual_provider_id (int)
// Jika Anda mengikuti langkah sebelumnya, variabel ini sudah ada di sini.

if (!isset($user_id_from_session) || !$user_id_from_session) {
    // Fallback jika dashboard.php gagal setting ID. Seharusnya tidak terjadi.
    $error = "Error Otorisasi: ID Pengguna tidak tersedia.";
}


$provider_data = [];
$user_data = [];
$bank_list = [];
$error = null;

// Default Status Verifikasi
$verification_status = 'unverified';
$verification_note = '';

// 1. Ambil Data User (Nama, Email)
if (!$error) {
    try {
        // Query menggunakan $user_id_from_session yang sudah divalidasi
        $stmt_user = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $user_id_from_session); // ID integer
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows > 0) {
            $user_data = $result_user->fetch_assoc();
        }
        $stmt_user->close();
    } catch (Exception $e) {
        $error = "Gagal memuat data pengguna: " . $e->getMessage();
    }
}

// 2. Ambil Data Provider (Termasuk Kolom Bank BARU)
if (!$error) {
    try {
        $stmt_provider = $conn->prepare("SELECT 
            id, entity_type, company_name, owner_name, business_license_path, 
            ktp_path, company_logo_path, address, rt, rw, phone_number, postal_code, 
            province, city, district, village, verification_status, verification_note,
            bank_name, bank_account_number, bank_account_name 
        FROM providers WHERE user_id = ?");
        
        $stmt_provider->bind_param("i", $user_id_from_session); // ID integer
        $stmt_provider->execute();
        $result_provider = $stmt_provider->get_result();
        
        if ($result_provider->num_rows > 0) {
            $provider_data = $result_provider->fetch_assoc();
            $verification_status = $provider_data['verification_status'];
            $verification_note = htmlspecialchars($provider_data['verification_note'] ?? '');
        } else {
            // Ini akan terdeteksi di dashboard.php, tapi tetap sebagai pencegahan
            $error = "Data provider tidak ditemukan. Hubungi Admin."; 
        }
        $stmt_provider->close();
    } catch (Exception $e) {
        $error = "Gagal memuat data perusahaan: " . $e->getMessage();
    }
}

// 3. Ambil Daftar Bank
if (!$error) {
    try {
        $result_banks = $conn->query("SELECT bank_name FROM bank_list ORDER BY bank_name ASC");
        if ($result_banks) {
            while ($row = $result_banks->fetch_assoc()) {
                $bank_list[] = $row['bank_name'];
            }
        }
    } catch (Exception $e) {
        $error_bank = "Gagal memuat daftar bank: " . $e->getMessage();
    }
}


// Fungsi Pembantu untuk Badge Verifikasi (Dibiarkan sama)
function get_verification_badge($status) {
    switch ($status) {
        case 'verified': return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Disetujui</span>';
        case 'pending': return '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history"></i> Menunggu Tinjauan</span>';
        case 'rejected': return '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Ditolak</span>';
        case 'unverified':
        default: return '<span class="badge bg-secondary"><i class="bi bi-exclamation-triangle"></i> Belum Diajukan</span>';
    }
}

// Cek apakah semua field wajib sudah terisi
$required_fields = ['company_name', 'owner_name', 'phone_number', 'address', 'postal_code', 'province', 'city', 'district', 'village', 'entity_type']; // Menambahkan owner_name, phone_number, entity_type
$is_profile_complete = true;
foreach ($required_fields as $field) {
    if (empty($provider_data[$field])) {
        $is_profile_complete = false;
        break;
    }
}

$is_verifiable = in_array($verification_status, ['unverified', 'rejected']);

?>

<h1 class="mb-4">Profil & Pengaturan Perusahaan</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mb-4 shadow-sm">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">Status Verifikasi Akun:</h5>
            <?php echo get_verification_badge($verification_status); ?>
        </div>
        
        <?php if ($verification_status === 'rejected' && !empty($verification_note)): ?>
            <div class="alert alert-danger p-2 m-0">
                <strong>Catatan Admin:</strong> <?php echo $verification_note; ?>
            </div>
        <?php endif; ?>

        <?php if ($verification_status === 'unverified' || $verification_status === 'rejected'): ?>
            <?php 
                // Cek dokumen wajib (KTP dan Izin jika Company)
                $has_ktp = !empty($provider_data['ktp_path']);
                $has_license = $provider_data['entity_type'] === 'umkm' || !empty($provider_data['business_license_path']);
                $is_ready_to_submit = $is_profile_complete && $has_ktp && $has_license;
            ?>

            <?php if ($is_ready_to_submit): ?>
                <form action="/process/profile_process" method="POST" onsubmit="return confirm('Anda yakin ingin mengajukan verifikasi? Pastikan semua data sudah benar.');">
                    <input type="hidden" name="action" value="submit_for_verification">
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="bi bi-send-fill me-2"></i> Ajukan Verifikasi
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" disabled data-bs-toggle="tooltip" title="Lengkapi data profil, KTP, dan Izin Usaha (jika PT/CV) terlebih dahulu.">Lengkapi Data Dulu</button>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($verification_status === 'pending'): ?>
             <span class="text-info fw-bold">Pengajuan sedang ditinjau.</span>
        <?php endif; ?>
    </div>
</div>

<form action="/process/profile_process" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_profile_data">
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-info-circle me-2"></i> Data Akun & Perusahaan
        </div>
        <div class="card-body">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Email Akun</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" disabled>
                    <small class="text-muted">Email tidak dapat diubah di sini.</small>
                </div>
                <div class="col-md-6">
                    <label for="name" class="form-label">Nama Kontak Akun</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>
                </div>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <label for="entity_type" class="form-label">Tipe Entitas Bisnis (*)</label>
                <select class="form-select" id="entity_type" name="entity_type" required>
                    <option value="">-- Pilih Tipe --</option>
                    <option value="company" <?php echo ($provider_data['entity_type'] ?? '') == 'company' ? 'selected' : ''; ?>>PT / CV (Perusahaan Berbadan Hukum)</option>
                    <option value="umkm" <?php echo ($provider_data['entity_type'] ?? '') == 'umkm' ? 'selected' : ''; ?>>UMKM / Perorangan</option>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="company_name" class="form-label">Nama Perusahaan / Nama Usaha (*)</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($provider_data['company_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="owner_name" class="form-label">Nama Pemilik/Penanggung Jawab (*)</label>
                    <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($provider_data['owner_name'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="phone_number" class="form-label">Nomor HP Aktif (*)</label>
                    <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($provider_data['phone_number'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="company_logo_file" class="form-label">Upload Logo Perusahaan</label>
                    <input type="file" class="form-control" id="company_logo_file" name="company_logo_file" accept=".jpg,.jpeg,.png">
                    <?php if (!empty($provider_data['company_logo_path'])): ?>
                        <small class="text-success">Logo sudah terupload. Upload file baru untuk mengganti.</small><br>
                        <img src="/<?php echo htmlspecialchars($provider_data['company_logo_path']); ?>" alt="Logo Perusahaan" style="max-height: 50px; margin-top: 5px; border: 1px solid #ccc;">
                    <?php endif; ?>
                    <small class="text-muted d-block">Maks 500KB. Format PNG/JPG (Idealnya rasio 1:1).</small>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="ktp_file" class="form-label">Upload KTP Pemilik (*)</label>
                    <input type="file" class="form-control" id="ktp_file" name="ktp_file" accept=".jpg,.jpeg,.png">
                    <?php if (!empty($provider_data['ktp_path'])): ?>
                        <small class="text-success">KTP sudah terupload. Upload file baru untuk mengganti.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div id="company_fields" style="display: none;">
                <hr>
                <div class="mb-3">
                    <label for="business_license_file" class="form-label">Upload Surat Izin Berusaha (NIB/SIUP/OSS) (*)</label>
                    <input type="file" class="form-control" id="business_license_file" name="business_license_file" accept=".pdf,.jpg,.jpeg,.png">
                    <?php if (!empty($provider_data['business_license_path'])): ?>
                        <small class="text-success">Surat Izin sudah terupload. Upload file baru untuk mengganti.</small>
                    <?php endif; ?>
                    <small class="text-danger d-block">Wajib diisi jika entitas PT/CV.</small>
                </div>
            </div>

        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-bank me-2"></i> Informasi Rekening Bank
        </div>
        <div class="card-body">
            <?php if (!empty($error_bank)): ?>
                <div class="alert alert-warning">
                    <?php echo htmlspecialchars($error_bank); ?>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="bank_name" class="form-label">Nama Bank</label>
                <select class="form-select" id="bank_name" name="bank_name">
                    <option value="">-- Pilih Bank --</option>
                    <?php foreach ($bank_list as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank); ?>"
                            <?php echo (isset($provider_data['bank_name']) && $provider_data['bank_name'] === $bank ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($bank); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Opsional, tapi wajib diisi jika ingin menerima pembayaran.</small>
            </div>
            
            <div class="mb-3">
                <label for="bank_account_number" class="form-label">Nomor Rekening</label>
                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number"
                    value="<?php echo htmlspecialchars($provider_data['bank_account_number'] ?? ''); ?>"
                    placeholder="Masukkan Nomor Rekening">
            </div>

            <div class="mb-3">
                <label for="bank_account_name" class="form-label">Nama Pemilik Rekening</label>
                <input type="text" class="form-control" id="bank_account_name" name="bank_account_name"
                    value="<?php echo htmlspecialchars($provider_data['bank_account_name'] ?? ''); ?>"
                    placeholder="Masukkan Nama Pemilik Rekening">
                <small class="form-text text-muted">Nama ini harus sesuai dengan nama di rekening bank.</small>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <i class="bi bi-geo-alt me-2"></i> Alamat Perusahaan / Usaha
        </div>
        <div class="card-body">
            <h6 class="mt-4 mb-3">Alamat Perusahaan / Usaha (*):</h6>
            <div class="mb-3">
                <label for="address" class="form-label">Jalan dan Detail Lainnya (*)</label>
                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($provider_data['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="rt" class="form-label">RT</label>
                    <input type="text" class="form-control" id="rt" name="rt" value="<?php echo htmlspecialchars($provider_data['rt'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="rw" class="form-label">RW</label>
                    <input type="text" class="form-control" id="rw" name="rw" value="<?php echo htmlspecialchars($provider_data['rw'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="postal_code" class="form-label">Kode Pos (*)</label>
                    <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($provider_data['postal_code'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="province" class="form-label">Provinsi (*)</label>
                    <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($provider_data['province'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="city" class="form-label">Kabupaten/Kota (*)</label>
                    <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($provider_data['city'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="district" class="form-label">Kecamatan (*)</label>
                    <input type="text" class="form-control" id="district" name="district" value="<?php echo htmlspecialchars($provider_data['district'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="village" class="form-label">Kelurahan/Desa (*)</label>
                    <input type="text" class="form-control" id="village" name="village" value="<?php echo htmlspecialchars($provider_data['village'] ?? ''); ?>" required>
                </div>
            </div>
        </div>
    </div>
    
    <button type="submit" class="btn btn-success btn-lg mb-5"><i class="bi bi-save me-2"></i> Simpan Perubahan Profil</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const entityTypeSelect = document.getElementById('entity_type');
    const companyFieldsDiv = document.getElementById('company_fields');
    const businessLicenseFile = document.getElementById('business_license_file');

    function toggleCompanyFields() {
        const isCompany = entityTypeSelect.value === 'company';
        
        companyFieldsDiv.style.display = isCompany ? 'block' : 'none';
        
        if (isCompany) {
            // Cek apakah sudah ada lisensi lama
            const hasExistingLicense = "<?php echo (int)!empty($provider_data['business_license_path']); ?>"; 
            
            // Jika belum ada lisensi dan tidak ada file baru di-upload, maka wajib.
            // Tapi karena file input bisa di-skip, kita biarkan logic required di server side (profile_process.php)
            // Cukup atur di sini untuk visualisasi (atau tinggalkan kosong jika Anda mengandalkan server)
            businessLicenseFile.required = hasExistingLicense === '1' ? false : true; 

        } else {
            businessLicenseFile.required = false;
        }
    }

    toggleCompanyFields();
    entityTypeSelect.addEventListener('change', toggleCompanyFields);

    // Bootstrap Tooltip initialization (opsional, jika Anda menggunakan Bootstrap 5)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>