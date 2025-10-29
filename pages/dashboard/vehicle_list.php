<?php
// Asumsi: $conn (koneksi) dan $actual_provider_id sudah tersedia dari file induk

global $conn, $user_id_from_session, $actual_provider_id; 

// 1. Sertakan file helper
// Pastikan path ke check_provider_verification.php sudah benar
require_once __DIR__ . '/../../utils/check_provider_verification.php'; 

// 2. Jalankan Fungsi Validasi
check_provider_verification($conn, $actual_provider_id, "Daftar Kendaraan");

// 1. Ambil data Kendaraan untuk Provider yang sedang login
$vehicles = [];
$default_photo = '/assets/default_vehicle.png'; // Path default jika foto tidak ada

try {
    // MODIFIKASI QUERY: Tambahkan kolom vehicle_photo_path
    $stmt = $conn->prepare("SELECT id, name, license_plate, capacity, type, status, created_at, vehicle_photo_path FROM vehicles WHERE provider_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $actual_provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Handle error database
    echo "<div class='alert alert-danger'>Gagal memuat data Kendaraan: " . htmlspecialchars($e->getMessage()) . "</div>";
    $vehicles = [];
}

// Fungsi helper untuk ikon kendaraan (Dibiarkan, berguna untuk fallback jika foto tidak dimuat)
function get_vehicle_icon($type) {
    switch ($type) {
        case 'bus': return 'bi-bus-front-fill';
        case 'van': return 'bi-truck-flatbed';
        case 'car': return 'bi-car-front-fill';
        case 'boat': return 'bi-water';
        default: return 'bi-truck';
    }
}
?>

<style>
    .vehicle-card {
        transition: transform 0.3s, box-shadow 0.3s;
        border: 1px solid #e0e0e0;
    }
    .vehicle-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 128, 0, 0.15); /* Bayangan hijau lembut */
    }
    .vehicle-image {
        width: 100%;
        height: 180px; /* Tinggi tetap untuk konsistensi */
        object-fit: cover;
        border-top-left-radius: var(--bs-card-border-radius);
        border-top-right-radius: var(--bs-card-border-radius);
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-success">Daftar Kendaraan Operasional</h3>
    <a href="/dashboard?p=vehicle_create" class="btn btn-success shadow-sm">
        <i class="bi bi-truck me-2"></i> Tambah Kendaraan Baru
    </a>
</div>

<?php if (empty($vehicles)): ?>
    <div class="card p-5 text-center shadow-sm">
        <i class="bi bi-car-front-fill display-4 text-secondary mb-3"></i>
        <p class="lead">Anda belum memiliki Kendaraan operasional terdaftar.</p>
        <small class="text-muted">Daftarkan kendaraan Anda untuk dapat digunakan di Trip.</small>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($vehicles as $vehicle): 
            $photo_src = !empty($vehicle['vehicle_photo_path']) && file_exists(__DIR__ . '/../../' . $vehicle['vehicle_photo_path']) 
                         ? '/' . $vehicle['vehicle_photo_path'] 
                         : $default_photo;
            
            $status_color = ($vehicle['status'] === 'available') ? 'bg-success' : 'bg-warning text-dark';
            $icon = get_vehicle_icon($vehicle['type']);
        ?>
            <div class="col">
                <div class="card vehicle-card h-100 shadow-sm border-0">
                    
                    <img src="<?= $photo_src ?>" 
                         alt="Foto <?= htmlspecialchars($vehicle['name']) ?>" 
                         class="vehicle-image">
                         
                    <div class="card-body d-flex flex-column">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-dark"><?= htmlspecialchars($vehicle['name']) ?></h5>
                                <p class="text-muted small mb-0 fw-bold">
                                    <i class="bi bi-bookmark-fill me-1 text-secondary"></i> 
                                    <?= htmlspecialchars(strtoupper($vehicle['license_plate'])) ?>
                                </p>
                            </div>
                            <span class="badge rounded-pill status-badge <?= $status_color ?>">
                                <i class="bi bi-circle-fill small me-1"></i> <?= ucfirst($vehicle['status']) ?>
                            </span>
                        </div>
                        
                        <hr class="mt-0 mb-2">

                        <ul class="list-unstyled small mb-3">
                            <li><i class="bi bi-people-fill me-2 text-primary"></i> Kapasitas: <b><?= htmlspecialchars($vehicle['capacity']) ?></b> Penumpang</li>
                            <li><i class="bi <?= $icon ?> me-2 text-primary"></i> Tipe: <?= ucfirst($vehicle['type']) ?></li>
                            <li><i class="bi bi-calendar-check me-2 text-primary"></i> Terdaftar: <?= date('d M Y', strtotime($vehicle['created_at'])) ?></li>
                        </ul>
                    </div>
                    
                    <div class="card-footer bg-light border-0 d-flex justify-content-end">
                        <a href="/dashboard?p=vehicle_edit&id=<?= $vehicle['id'] ?>" class="btn btn-sm btn-outline-success me-2">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>