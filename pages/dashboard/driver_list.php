<?php
// File: pages/dashboard/driver_list.php
// Menampilkan daftar driver yang dikelola oleh provider.

global $conn, $actual_provider_id;
require_once __DIR__ . '/../../utils/check_provider_verification.php';

check_provider_verification($conn, $actual_provider_id, "Daftar Driver");

$error = null;
$drivers = [];
$provider_id = $actual_provider_id ?? 0;

if (!$provider_id) {
    $error = "Akses Ditolak: ID Provider tidak ditemukan. Mohon login ulang.";
}

if (!$error) {
    try {
        $sql = "
            SELECT 
                id, name, phone_number, license_number, is_active, photo_url 
            FROM 
                drivers
            WHERE 
                provider_id = ?
            ORDER BY 
                is_active DESC, name ASC";
            
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $provider_id);
        $stmt->execute();
        $drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        $error = "Gagal memuat data driver: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <h1 class="text-primary fw-bold">Manajemen Driver</h1>
    <a href="/dashboard?p=driver_create" class="btn btn-primary shadow-sm mt-2 mt-md-0">
        <i class="bi bi-person-plus-fill me-2"></i> Tambah Driver Baru
    </a>
</div>
<p class="text-muted mb-4">Kelola daftar driver yang siap Anda tugaskan ke dalam perjalanan.</p>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
    <?php if (empty($drivers)): ?>
        <div class="col-12">
            <div class="alert alert-info text-center shadow-sm m-0">
                <i class="bi bi-person-fill-exclamation me-2"></i> Anda belum mendaftarkan driver.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($drivers as $driver): 
            // Tentukan path lengkap foto
            $photo_path = $driver['photo_url'] ? '/' . htmlspecialchars($driver['photo_url']) : '/assets/img/placeholder.png';
            $status_class = $driver['is_active'] ? 'bg-success text-white' : 'bg-secondary text-white'; 
            $status_text = $driver['is_active'] ? 'Aktif' : 'Tidak Aktif';
        ?>
        <div class="col">
            <div class="card h-100 rounded-3 shadow-sm driver-card border-0 <?php echo $driver['is_active'] ? 'border-success' : 'border-secondary'; ?>" 
                 style="border-left: 5px solid; transition: all 0.3s;">
                
                <div class="card-body p-4 d-flex flex-column align-items-center text-center">
                    
                    <div class="position-relative mb-3">
                        <img src="<?php echo $photo_path; ?>" 
                             alt="Foto Driver" 
                             class="rounded-circle border border-2"
                             style="width: 80px; height: 80px; object-fit: cover;">
                        
                        <span class="badge position-absolute bottom-0 end-0 translate-middle-y rounded-pill <?php echo $status_class; ?>" 
                              title="Status Driver">
                            <i class="bi bi-<?php echo $driver['is_active'] ? 'check-circle-fill' : 'x-circle-fill'; ?>"></i>
                        </span>
                    </div>

                    <h5 class="card-title mb-1 fw-bold text-dark">
                        <?php echo htmlspecialchars($driver['name']); ?>
                    </h5>
                    
                    <p class="mb-3 small">
                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </p>

                    <div class="text-start w-100 mb-3 small text-muted">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-telephone-fill me-2" style="width: 15px;"></i>
                            <span><?php echo htmlspecialchars($driver['phone_number']); ?></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-credit-card-2-front-fill me-2" style="width: 15px;"></i>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($driver['license_number']); ?></span>
                            <span class="ms-1">(SIM)</span>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-0 pt-0 pb-3">
                    <a href="/dashboard?p=driver_edit&id=<?php echo $driver['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-pencil me-1"></i> Lihat & Edit Detail
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
/* Efek Hover untuk Card yang lebih interaktif */
.driver-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15) !important;
}
</style>