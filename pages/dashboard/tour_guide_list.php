<?php
// Asumsi: $conn (koneksi) dan $actual_provider_id sudah tersedia dari file induk
global $conn, $user_id_from_session, $actual_provider_id; 

// 1. Sertakan file helper (Pastikan path ke check_provider_verification.php sudah benar)
require_once __DIR__ . '/../../utils/check_provider_verification.php'; // Menggunakan nama file yang kita sepakati sebelumnya

// 2. Jalankan Fungsi Validasi
check_provider_verification($conn, $actual_provider_id, "Daftar Pemandu Wisata");

// 3. Ambil data Tour Guide, termasuk profile_photo_path
$guides = [];
$default_photo = '/assets/default_profile.png'; // Pastikan path ini valid dari root
try {
    // TAMBAHKAN kolom profile_photo_path di SELECT
    $stmt = $conn->prepare("SELECT id, name, phone_number, specialization, status, created_at, profile_photo_path FROM tour_guides WHERE provider_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $actual_provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $guides[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Handle error database
    echo "<div class='alert alert-danger'>Gagal memuat data Tour Guide: " . htmlspecialchars($e->getMessage()) . "</div>";
    $guides = [];
}
?>

<style>
    .guide-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 15px;
        border: 3px solid #0d6efd; /* Warna border sesuai tema */
    }
    .card-guide {
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .card-guide:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-primary">Daftar Pemandu Wisata</h3>
    <a href="/dashboard?p=tour_guide_create" class="btn btn-primary shadow-sm">
        <i class="bi bi-person-plus-fill me-2"></i> Tambah Pemandu Baru
    </a>
</div>

<?php if (empty($guides)): ?>
    <div class="card p-5 text-center shadow-sm">
        <i class="bi bi-person-badge-fill display-4 text-secondary mb-3"></i>
        <p class="lead">Anda belum memiliki Pemandu Wisata terdaftar.</p>
        <small class="text-muted">Tambahkan Pemandu Anda untuk memudahkan pengaturan Trip.</small>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($guides as $guide): 
            $photo_src = !empty($guide['profile_photo_path']) && file_exists(__DIR__ . '/../../' . $guide['profile_photo_path']) 
                         ? '/' . $guide['profile_photo_path'] 
                         : $default_photo;
            $status_color = $guide['status'] === 'active' ? 'success' : 'danger';
            $specialization_text = htmlspecialchars($guide['specialization'] ?: 'Umum');
        ?>
            <div class="col">
                <div class="card card-guide h-100 shadow border-0 text-center">
                    <div class="card-body d-flex flex-column align-items-center">
                        <img src="<?= $photo_src ?>" 
                             alt="Foto Profil <?= htmlspecialchars($guide['name']) ?>" 
                             class="guide-avatar">
                             
                        <h5 class="card-title mb-1 fw-bolder text-dark"><?= htmlspecialchars($guide['name']) ?></h5>
                        <span class="badge bg-secondary mb-3"><?= $specialization_text ?></span>

                        <div class="text-start w-100 mb-3 small">
                            <p class="mb-1 text-muted"><i class="bi bi-phone me-2 text-info"></i> <?= htmlspecialchars($guide['phone_number']) ?></p>
                            <p class="mb-1 text-muted"><i class="bi bi-calendar-event me-2 text-info"></i> Sejak: <?= date('d M Y', strtotime($guide['created_at'])) ?></p>
                        </div>

                        <span class="badge rounded-pill bg-<?= $status_color ?> mt-auto">
                            <i class="bi bi-circle-fill small me-1"></i> <?= ucfirst($guide['status']) ?>
                        </span>
                    </div>
                    
                    <div class="card-footer bg-light border-0 d-flex justify-content-center pt-3 pb-3">
                        <button class="btn btn-sm btn-outline-primary me-2"><i class="bi bi-pencil"></i> Edit</button>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>