<?php
// Pastikan variabel global $conn dan $actual_provider_id tersedia.
global $conn, $actual_provider_id; 

// Menggunakan helper verifikasi karena ini adalah fitur utama Trip
require_once __DIR__ . '/../../utils/check_provider_verification.php'; 
check_provider_verification($conn, $actual_provider_id, "Kelola Pemandu");

// Logika tampilan pesan sukses/error dari proses (jika ada)
$message = $_SESSION['guide_message'] ?? null;
$message_type = $_SESSION['guide_message_type'] ?? 'info';
unset($_SESSION['guide_message']);
unset($_SESSION['guide_message_type']);
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h3 class="fw-bold mb-4 text-primary">Tambah Pemandu Wisata Baru</h3>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form action="/process/add_guide_process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="provider_id" value="<?= htmlspecialchars($actual_provider_id) ?>">

                    <div class="row">
                        <div class="col-md-7">
                            <h5 class="mb-3 text-secondary">Informasi Utama</h5>

                            <div class="mb-3">
                                <label for="guideName" class="form-label fw-bold">Nama Lengkap</label>
                                <input type="text" class="form-control" id="guideName" name="name" required placeholder="Nama sesuai identitas">
                            </div>

                            <div class="mb-3">
                                <label for="guidePhone" class="form-label fw-bold">Nomor Telepon</label>
                                <input type="tel" class="form-control" id="guidePhone" name="phone_number" required placeholder="+62xxxxxxxxxx">
                            </div>

                            <div class="mb-3">
                                <label for="guideSpecialization" class="form-label fw-bold">Spesialisasi / Bahasa Dikuasai</label>
                                <input type="text" class="form-control" id="guideSpecialization" name="specialization" placeholder="Contoh: Inggris, Jepang, Sejarah">
                            </div>

                            <div class="mb-3">
                                <label for="guideStatus" class="form-label fw-bold">Status Ketersediaan</label>
                                <select class="form-select" id="guideStatus" name="status">
                                    <option value="active" selected>Aktif (Siap Ditugaskan)</option>
                                    <option value="inactive">Nonaktif (Cuti/Libur)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <h5 class="mb-3 text-secondary">Foto Profil (Wajib)</h5>
                            <div class="text-center mb-3">
                                <img id="profile-preview" src="/assets/default_profile.png" 
                                     alt="Foto Profil" 
                                     class="img-thumbnail" 
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            </div>
                            
                            <div class="mb-3">
                                <label for="profilePhoto" class="form-label">Upload Foto (Max 2MB, JPG/PNG)</label>
                                <input class="form-control" type="file" id="profilePhoto" name="profile_photo" accept="image/jpeg, image/png" required>
                                <div class="form-text">Foto akan membantu identifikasi pemandu di Trip.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <a href="/dashboard?p=tour_guides" class="btn btn-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i> Simpan Pemandu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Script untuk preview foto yang diupload
    document.getElementById('profilePhoto').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profile-preview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
</script>