<?php
// File: pages/dashboard/departure_edit.php
// Halaman untuk mengedit detail jadwal keberangkatan tertentu.

global $conn;

// 1. Ambil ID dari URL
$schedule_id = $_GET['id'] ?? 0;
$error = null;
$schedule_data = [];

if (empty($schedule_id) || !is_numeric($schedule_id)) {
    $error = "ID Jadwal tidak valid.";
}

if (!$error) {
    // 2. Query untuk mengambil data jadwal berdasarkan ID
    try {
        $sql = "
            SELECT 
                td.*, t.title AS trip_title
            FROM 
                trip_departures td
            JOIN 
                trips t ON td.trip_id = t.id
            WHERE 
                td.id = ?";
            
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Jadwal keberangkatan tidak ditemukan.";
        } else {
            $schedule_data = $result->fetch_assoc();
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Gagal memuat data: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Edit Jadwal Keberangkatan</h1>
    <a href="/dashboard?p=departure_schedule" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i> Kembali ke Daftar
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-white">
            <h5 class="mb-0">Mengedit Trip: <?php echo htmlspecialchars($schedule_data['trip_title']); ?></h5>
        </div>
        <div class="card-body">
            
            <?php 
                // TAMPILKAN FORM EDIT DI SINI
                // echo '<p>ID Jadwal yang diedit: <strong>' . htmlspecialchars($schedule_data['id']) . '</strong></p>';
                echo '<p>Tanggal Keberangkatan: ' . htmlspecialchars($schedule_data['departure_date']) . '</p>';
                
                // Tambahkan pesan sementara untuk memastikan logic H-2 bekerja
                $departure_datetime = strtotime($schedule_data['departure_date'] . ' ' . $schedule_data['departure_time']);
                $edit_deadline_time = $departure_datetime - (48 * 3600);
                $current_time = time();
                
                if ($current_time > $edit_deadline_time) {
                    echo '<div class="alert alert-danger">PERINGATAN: Trip ini sudah melewati batas H-2, seharusnya tombol Edit dinonaktifkan di halaman sebelumnya.</div>';
                }
            ?>
            
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="vehicle_type" class="form-label">Tipe Kendaraan</label>
                    <input type="text" class="form-control" id="vehicle_type" name="vehicle_type" 
                           value="<?php echo htmlspecialchars($schedule_data['vehicle_type']); ?>">
                </div>

                <button type="submit" class="btn btn-warning mt-3">Simpan Perubahan</button>
            </form>

        </div>
    </div>
<?php endif; ?>