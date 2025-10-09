<?php
// File: pages/dashboard/trip_edit.php
// Asumsi: File ini dimuat melalui dashboard.php. $conn tersedia.
// ASUMSI KRUSIAL: $actual_provider_id sudah didapatkan dan tersedia di sini.

// --- BAGIAN OTORISASI & PENGAMBILAN DATA PROVIDER (MENGGUNAKAN LOGIC DASHBOARD) ---
// Jika Anda sudah memigrasi dashboard.php, variabel ini seharusnya sudah tersedia.
if (!isset($actual_provider_id) || !$actual_provider_id) {
    // Fallback: Lakukan pengecekan seperti di dashboard.php (tetap pakai user_id yang divalidasi)
    $user_id_from_session = $_SESSION['user_id'] ?? 0;
    $actual_provider_id = 0;

    if ($user_id_from_session > 0) {
        try {
            $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
            $stmt_provider->bind_param("i", $user_id_from_session);
            $stmt_provider->execute();
            $result_provider = $stmt_provider->get_result();
            
            if ($result_provider->num_rows > 0) {
                $actual_provider_id = $result_provider->fetch_assoc()['id']; 
            }
            $stmt_provider->close();
        } catch (Exception $e) {
            // Abaikan, error akan muncul di bagian pengecekan data trip
        }
    }
}
// --- END OTORISASI ---


// 1. Ambil UUID Trip dari URL
$trip_uuid = $_GET['id'] ?? ''; // Mengambil sebagai string (UUID/Code)
$trip_data = null;
$main_image_url = null;
$additional_images = [];
$errors = [];


if (!empty($trip_uuid) && $actual_provider_id > 0) {
    // 2. Ambil data Trip dan pastikan milik provider yang sedang login
    // PERUBAHAN KRUSIAL: Menggunakan 'uuid' dan bind_param 'si'
    $stmt = $conn->prepare("SELECT * FROM trips WHERE uuid = ? AND provider_id = ?");
    $stmt->bind_param("si", $trip_uuid, $actual_provider_id); // 's' untuk string (UUID), 'i' untuk integer (provider_id)
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $trip_data = $result->fetch_assoc();
        $stmt->close();

        // KARENA QUERY DIBAWAH MENGGUNAKAN 'trip_id' INTEGER, KITA HARUS AMBIL DARI $trip_data
        $trip_id_internal = $trip_data['id']; // ID integer internal

        // 3. Ambil semua gambar terkait
        $stmt_img = $conn->prepare("SELECT image_url, is_main FROM trip_images WHERE trip_id = ? ORDER BY is_main DESC");
        // PERHATIKAN: Kita menggunakan ID integer internal di sini
        $stmt_img->bind_param("i", $trip_id_internal); 
        $stmt_img->execute();
        $result_img = $stmt_img->get_result();
        
        while($row = $result_img->fetch_assoc()) {
            if ($row['is_main'] == 1) {
                $main_image_url = $row['image_url'];
            } else {
                $additional_images[] = $row['image_url'];
            }
        }
        $stmt_img->close();

    } else {
        $errors[] = "Trip tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.";
    }
} else {
    $errors[] = "UUID Trip tidak valid atau ID Provider tidak ditemukan.";
}

// Jika trip tidak ditemukan, redirect ke daftar trip
if (!$trip_data) {
    $_SESSION['dashboard_message'] = $errors[0] ?? "Gagal memuat data trip.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=trips");
    exit();
}

// Tampilkan form
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);
?>

<h1 class="mb-4">Edit Trip: <?php echo htmlspecialchars($trip_data['title']); ?></h1>
<p class="text-muted">Perbarui detail perjalanan Anda.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="/process/trip_process" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_trip">
    <input type="hidden" name="trip_uuid" value="<?php echo htmlspecialchars($trip_data['uuid']); ?>">
    <input type="hidden" name="trip_id_internal" value="<?php echo htmlspecialchars($trip_data['id']); ?>">


    <div class="row">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">Informasi Dasar Trip</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Nama Trip / Judul Paket <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($trip_data['title']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Tujuan Utama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="location" name="location" required value="<?php echo htmlspecialchars($trip_data['location']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi Lengkap Trip <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($trip_data['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duration" class="form-label">Durasi (Contoh: 3 Hari 2 Malam) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="duration" name="duration" required value="<?php echo htmlspecialchars($trip_data['duration']); ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">Titik Kumpul & Jadwal</div>
                <div class="card-body">
                    <h6 class="mb-3">Lokasi Titik Kumpul</h6>
                    <div class="mb-3">
                        <label for="gathering_point_name" class="form-label">Nama Titik Kumpul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="gathering_point_name" name="gathering_point_name" required 
                               value="<?php echo htmlspecialchars($trip_data['gathering_point_name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="gathering_point_url" class="form-label">URL Google Maps Titik Kumpul <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="gathering_point_url" name="gathering_point_url" required 
                               value="<?php echo htmlspecialchars($trip_data['gathering_point_url']); ?>">
                        <small class="form-text text-muted">Contoh: https://maps.app.goo.gl/...</small>
                    </div>

                    <h6 class="mt-4 mb-3">Waktu Pelaksanaan</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departure_time" class="form-label">Estimasi Jam Berangkat <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="departure_time" name="departure_time" required 
                                   value="<?php echo htmlspecialchars($trip_data['departure_time']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="return_time" class="form-label">Estimasi Jam Pulang <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="return_time" name="return_time" required 
                                   value="<?php echo htmlspecialchars($trip_data['return_time']); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">Tanggal dan Kuota</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required value="<?php echo htmlspecialchars($trip_data['start_date']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required value="<?php echo htmlspecialchars($trip_data['end_date']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="max_participants" class="form-label">Kuota Maksimal Peserta <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_participants" name="max_participants" required min="<?php echo $trip_data['booked_participants']; ?>" value="<?php echo htmlspecialchars($trip_data['max_participants']); ?>">
                        <small class="form-text text-muted">Kuota tidak boleh kurang dari jumlah peserta yang sudah booking (<?php echo $trip_data['booked_participants']; ?>).</small>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">Pricing</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="price" class="form-label">Harga per Orang (IDR) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="price" name="price" required min="0" value="<?php echo htmlspecialchars($trip_data['price']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="discount_price" class="form-label">Harga Diskon (Opsional)</label>
                        <input type="number" class="form-control" id="discount_price" name="discount_price" value="<?php echo htmlspecialchars($trip_data['discount_price']); ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">Media & Status</div>
                <div class="card-body">
                    
                    <?php if ($main_image_url): ?>
                        <div class="mb-3">
                            <label class="form-label">Foto Utama Saat Ini</label>
                            <img src="/<?php echo htmlspecialchars($main_image_url); ?>" alt="Main Trip Image" class="img-fluid mb-2 border rounded" style="max-height: 150px;">
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="main_image" class="form-label">Ganti Foto Utama Trip</label>
                        <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*">
                        <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah gambar.</small>
                    </div>
                    
                    <?php if (!empty($additional_images)): ?>
                        <div class="mb-3">
                            <label class="form-label">Foto Tambahan Saat Ini</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($additional_images as $img_url): ?>
                                    <img src="/<?php echo htmlspecialchars($img_url); ?>" alt="Additional Image" class="border rounded" style="max-height: 80px;">
                                <?php endforeach; ?>
                            </div>
                            <small class="form-text text-danger">Saat ini, Anda hanya bisa MENGHAPUS foto tambahan melalui database. Upload di form bawah akan MENAMBAHKAN foto baru.</small>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="additional_images" class="form-label">Tambahkan Foto Baru (Opsional)</label>
                        <input type="file" class="form-control" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                        <small class="form-text text-muted">Pilih hingga 5 foto baru. Foto lama tidak akan hilang.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status Trip</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="draft" <?php echo ($trip_data['status'] === 'draft' ? 'selected' : ''); ?>>Draft (Belum Dipublikasikan)</option>
                            <option value="available" <?php echo ($trip_data['status'] === 'available' ? 'selected' : ''); ?>>Available (Aktif)</option>
                            <option value="cancelled" <?php echo ($trip_data['status'] === 'cancelled' ? 'selected' : ''); ?>>Cancelled</option>
                        </select>
                        <small class="form-text text-info">Status persetujuan Admin: <?php echo htmlspecialchars(ucfirst($trip_data['approval_status'])); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <button type="submit" class="btn btn-success btn-lg mt-3"><i class="bi bi-pencil-square me-2"></i> Perbarui Trip</button>
    <a href="/dashboard?p=trips" class="btn btn-secondary btn-lg mt-3">Batalkan</a>
</form>