<?php
// File: pages/dashboard/driver_list.php
// Menampilkan daftar driver yang dikelola oleh provider.

global $conn, $actual_provider_id;

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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Manajemen Driver</h1>
    <a href="/dashboard?p=driver_create" class="btn btn-primary">
        <i class="bi bi-person-plus-fill me-2"></i> Tambah Driver Baru
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($drivers)): ?>
            <div class="alert alert-info text-center m-0">
                Anda belum mendaftarkan driver.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 10%;">Foto</th>
                            <th style="width: 30%;">Nama Driver</th>
                            <th style="width: 20%;">Nomor SIM</th>
                            <th style="width: 20%;">No. Telepon</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 10%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $driver): 
                            // Tentukan path lengkap foto
                            $photo_path = $driver['photo_url'] ? '/' . $driver['photo_url'] : '/assets/img/placeholder.png'; 
                        ?>
                        <tr>
                            <td>
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                                     alt="Foto Driver" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                            </td>
                            <td><?php echo htmlspecialchars($driver['name']); ?></td>
                            <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                            <td><?php echo htmlspecialchars($driver['phone_number']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $driver['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $driver['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="/dashboard?p=driver_edit&id=<?php echo $driver['id']; ?>" class="btn btn-sm btn-warning" title="Edit Driver">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>