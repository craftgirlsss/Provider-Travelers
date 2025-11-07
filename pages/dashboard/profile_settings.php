<?php
// File: pages/dashboard/profile_settings.php

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
            bank_name, bank_account_number, bank_account_name,
            is_charter_available
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

// 2.5 Payment Method
$payment_methods = [];
if (!$error) {
    try {
        $stmt_payment = $conn->prepare("SELECT id, method_type, account_name, bank_name, account_number, qris_image_url, is_main, is_active 
            FROM provider_payment_methods 
            WHERE provider_id = ? 
            ORDER BY is_main DESC, id DESC");
        $stmt_payment->bind_param("i", $actual_provider_id);
        $stmt_payment->execute();
        $result_payment = $stmt_payment->get_result();
        while ($row = $result_payment->fetch_assoc()) {
            $payment_methods[] = $row;
        }
        $stmt_payment->close();
    } catch (Exception $e) {
        $error = "Gagal memuat metode pembayaran: " . $e->getMessage();
    }
}

// 3. Ambil Daftar Bank
$error_bank = null;
if (!$error) {
    try {
        // Ambil data bank_name dari tabel bank_list
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

<div class="card mb-4 shadow-sm">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">Layanan Transportasi Khusus (Charter/Sewa)</h5>
            <p class="mb-0 text-muted">Aktifkan jika Anda menerima permintaan sewa kendaraan selain dari paket trip reguler.</p>
        </div>
        
        <div class="form-check form-switch ms-3">
            <input class="form-check-input" type="checkbox" role="switch" id="charterStatusSwitch"
                   name="charter_status_switch" value="1"
                   <?php echo ($provider_data['is_charter_available'] ?? 0) == 1 ? 'checked' : ''; ?>
                   onchange="updateCharterStatus(this.checked)">
            <label class="form-check-label" for="charterStatusSwitch">Aktifkan Layanan</label>
        </div>
    </div>
</div>

<?php if (($provider_data['is_charter_available'] ?? 0) == 1): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        Layanan Charter Anda aktif. Jangan lupa <a href="/dashboard?page=charter_fleet" class="alert-link fw-bold">kelola armada sewa Anda</a> agar client bisa melihat ketersediaan.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

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
            <i class="bi bi-bank me-2"></i> Rekening Bank Internal (Pencairan Dana)
        </div>
        <div class="card-body">
            <p class="text-muted">Informasi ini digunakan untuk keperluan internal dan pencairan dana Anda. Ini <b>tidak</b> ditampilkan langsung ke pelanggan.</p>
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
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-wallet2 me-2"></i> Metode Pembayaran untuk Pelanggan</span>
            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addPaymentMethodModal">
                <i class="bi bi-plus-circle me-1"></i> Tambah Metode
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($payment_methods)): ?>
                <div class="alert alert-info m-4 text-center">
                    Anda belum menambahkan metode pembayaran yang akan ditampilkan kepada pelanggan.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark">
                                        <?php 
                                            $type_display = strtoupper(str_replace('_', ' ', $method['method_type']));
                                            echo htmlspecialchars($type_display);
                                        ?>
                                        <?php if ($method['is_main'] == 1): ?>
                                            <span class="badge bg-primary ms-2">Utama</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="mb-1 small">
                                        <?php echo htmlspecialchars($method['bank_name'] ?? 'Penyedia Layanan'); ?> - 
                                        <b><?php echo htmlspecialchars($method['account_number']); ?></b>
                                    </p>
                                    <small class="text-muted">A.N: <?php echo htmlspecialchars($method['account_name']); ?></small>
                                    
                                    <div class="mt-1">
                                        <?php if ($method['is_active'] == 1): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-x-circle-fill"></i> Nonaktif</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($method['qris_image_url'])): ?>
                                        <div class="mt-2">
                                            <small class="text-info d-block">QRIS tersedia:</small>
                                            <img src="/<?php echo htmlspecialchars($method['qris_image_url']); ?>" alt="QRIS" style="max-height: 50px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-info edit-method-btn" data-id="<?php echo $method['id']; ?>" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-method-btn" data-id="<?php echo $method['id']; ?>" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

<div class="modal fade" id="addPaymentMethodModal" tabindex="-1" aria-labelledby="addPaymentMethodModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="/process/payment_method_process.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_method">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="addPaymentMethodModalLabel">Tambah Metode Pembayaran Baru</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            
            <div class="mb-3">
                <label for="method_type_modal" class="form-label fw-bold">Tipe Pembayaran (*)</label>
                <select class="form-select" id="method_type_modal" name="method_type" required>
                    <option value="">-- Pilih Tipe --</option>
                    <option value="BANK_TRANSFER">Transfer Bank</option>
                    <option value="E_WALLET">E-Wallet (Dana, GoPay, OVO, dll)</option>
                    <option value="QRIS">QRIS (Code Scan)</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="bank_name_modal" class="form-label fw-bold">Nama Bank/Penyedia Layanan (*)</label>
                <div id="bank_name_container">
                    <input type="text" class="form-control" id="bank_name_modal" name="bank_name" required 
                           placeholder="Pilih Tipe Pembayaran Dahulu">
                </div>
            </div>
            <div class="mb-3">
                <label for="account_name_modal" class="form-label fw-bold">Nama Pemilik Rekening/Akun (*)</label>
                <input type="text" class="form-control" id="account_name_modal" name="account_name" required>
            </div>
            
            <div class="mb-3">
                <label for="account_number_modal" class="form-label fw-bold">No. Rekening/No. Telp/ID Pembayaran (*)</label>
                <input type="text" class="form-control" id="account_number_modal" name="account_number" required>
            </div>
            
            <div id="qris_upload_section" class="mb-3" style="display:none;">
                <label for="qris_image_file" class="form-label fw-bold text-danger">Upload Gambar QRIS (JPG/PNG)</label>
                <input type="file" class="form-control" id="qris_image_file" name="qris_image_file" accept=".jpg,.jpeg,.png">
                <small class="text-muted">Wajib diisi jika tipe pembayaran adalah QRIS.</small>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="is_main" name="is_main">
                <label class="form-check-label" for="is_main">
                    Jadikan sebagai Metode Pembayaran Utama
                </label>
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">Aktifkan Metode Pembayaran ini</label>
            </div>
            
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">Simpan Metode</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const entityTypeSelect = document.getElementById('entity_type');
    const companyFieldsDiv = document.getElementById('company_fields');
    const businessLicenseFile = document.getElementById('business_license_file');
    function toggleCompanyFields() {
        const isCompany = entityTypeSelect.value === 'company';
        companyFieldsDiv.style.display = isCompany ? 'block' : 'none';
        
        // Cek apakah file sudah ada, jika sudah ada, tidak perlu di-required
        const hasExistingLicense = "<?php echo (int)!empty($provider_data['business_license_path']); ?>"; 
        
        if (isCompany) {
            businessLicenseFile.required = hasExistingLicense === '1' ? false : true; 
        } else {
            businessLicenseFile.required = false;
        }
    }
    toggleCompanyFields();
    entityTypeSelect.addEventListener('change', toggleCompanyFields);
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // =======================================================================
    // LOGIKA DINAMIS BANK/E-WALLET/QRIS
    // =======================================================================
    const methodTypeModal = document.getElementById('method_type_modal');
    const qrisSection = document.getElementById('qris_upload_section');
    const qrisInput = document.getElementById('qris_image_file');
    
    const bankNameContainer = document.getElementById('bank_name_container');
    // Ambil daftar bank dari PHP dan encode ke JavaScript
    const phpBankList = <?php echo json_encode($bank_list); ?>; 
    
    // Fungsi untuk mengubah field Nama Bank/Penyedia Layanan
    function updateBankNameField() {
        const selectedType = methodTypeModal.value;
        let htmlContent = '';
        let requiredStatus = true;
        let placeholderText = '';

        if (selectedType === 'BANK_TRANSFER') {
            // Jika Transfer Bank: Tampilkan Dropdown Bank
            htmlContent = `
                <select class="form-select" id="bank_name_modal" name="bank_name" required>
                    <option value="">-- Pilih Bank --</option>
                    ${phpBankList.map(bank => `<option value="${bank}">${bank}</option>`).join('')}
                </select>
            `;
            requiredStatus = true;
            
        } else if (selectedType === 'E_WALLET') {
            // Jika E-Wallet: Tampilkan Text Field dengan placeholder E-Wallet
            placeholderText = 'Contoh: GoPay, Dana, OVO';
            htmlContent = `
                <input type="text" class="form-control" id="bank_name_modal" name="bank_name" required 
                       placeholder="${placeholderText}">
            `;
            requiredStatus = true;

        } else if (selectedType === 'QRIS') {
            // Jika QRIS: Tampilkan Text Field
            placeholderText = 'Contoh: QRIS Bank BCA, QRIS Standard';
            htmlContent = `
                <input type="text" class="form-control" id="bank_name_modal" name="bank_name" required 
                       placeholder="${placeholderText}">
            `;
            requiredStatus = true;
        } else {
            // Default/Pilihan Kosong
             htmlContent = `
                <input type="text" class="form-control" id="bank_name_modal" name="bank_name" 
                       placeholder="Pilih Tipe Pembayaran Dahulu">
            `;
            requiredStatus = false;
        }

        // Terapkan perubahan ke kontainer
        bankNameContainer.innerHTML = htmlContent;
        

        // Logika untuk menampilkan/menyembunyikan field QRIS upload
        if (selectedType === 'QRIS') {
            qrisSection.style.display = 'block';
            qrisInput.required = true; 
        } else {
            qrisSection.style.display = 'none';
            qrisInput.required = false;
        }
        
        // Memastikan field Nama Bank tetap required (kecuali saat value kosong)
        const currentBankInput = document.getElementById('bank_name_modal');
        if (currentBankInput) {
             currentBankInput.required = requiredStatus;
        }
        
        // Update Label/Placeholder untuk Account Number/ID
        const accountNumberLabel = document.querySelector('label[for="account_number_modal"]');
        if (accountNumberLabel) {
            if (selectedType === 'BANK_TRANSFER') {
                 accountNumberLabel.textContent = 'Nomor Rekening Bank (*)';
            } else if (selectedType === 'E_WALLET') {
                 accountNumberLabel.textContent = 'Nomor Telp/ID Akun E-Wallet (*)';
            } else if (selectedType === 'QRIS') {
                 accountNumberLabel.textContent = 'ID Merchant QRIS (Opsional)';
            } else {
                 accountNumberLabel.textContent = 'No. Rekening/No. Telp/ID Pembayaran (*)';
            }
        }
        
    }


    // Panggil saat load dan saat berubah
    updateBankNameField(); 
    methodTypeModal.addEventListener('change', updateBankNameField);
    // =======================================================================
    
    // Tambahkan logika untuk tombol edit/delete di daftar metode pembayaran
    document.querySelectorAll('.delete-method-btn').forEach(button => {
        button.addEventListener('click', function() {
            const methodId = this.getAttribute('data-id');
            if (confirm('Anda yakin ingin menghapus metode pembayaran ini?')) {
                // Kirim permintaan DELETE ke payment_method_process.php
                fetch('/process/payment_method_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_method&method_id=' + methodId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Metode pembayaran berhasil dihapus.');
                        window.location.reload();
                    } else {
                        alert('Gagal menghapus: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan jaringan saat menghapus.');
                });
            }
        });
    });
});
    function updateCharterStatus(isChecked) {
        const status = isChecked ? 1 : 0;
        fetch('/process/profile_process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=update_charter_status&is_charter_available=' + status
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status Layanan Charter berhasil diperbarui.');
                window.location.reload(); // Reload untuk menampilkan alert info baru
            } else {
                alert('Gagal memperbarui status: ' + data.message);
                document.getElementById('charterStatusSwitch').checked = !isChecked; 
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan jaringan.');
            document.getElementById('charterStatusSwitch').checked = !isChecked;
        });
    }
</script>