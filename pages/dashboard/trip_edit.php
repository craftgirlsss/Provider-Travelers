<?php
// File: pages/dashboard/trip_edit.php
// Asumsi: File ini dimuat melalui dashboard.php, jadi session dan $conn sudah tersedia

// 1. Ambil ID Trip dari URL
$trip_id = (int)($_GET['id'] ?? 0);
$provider_id = $_SESSION['user_id'];
$trip_data = null;
$main_image_url = null;
$errors = [];

echo $trip_id;

if ($trip_id > 0) {
    // 2. Ambil data Trip dan pastikan milik provider yang sedang login
    $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ? AND provider_id = ?");
    $stmt->bind_param("ii", $trip_id, $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $trip_data = $result->fetch_assoc();
        $stmt->close();
        
        // 3. Ambil gambar utama terkait
        $stmt_img = $conn->prepare("SELECT image_url FROM trip_images WHERE trip_id = ? AND is_main = 1 LIMIT 1");
        $stmt_img->bind_param("i", $trip_id);
        $stmt_img->execute();
        $result_img = $stmt_img->get_result();
        if ($result_img->num_rows === 1) {
            $main_image_url = $result_img->fetch_assoc()['image_url'];
        }
        $stmt_img->close();

    } else {
        $errors[] = "Trip tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.";
    }
} else {
    $errors[] = "ID Trip tidak valid.";
}

// Jika trip tidak ditemukan, redirect ke daftar trip
if (!$trip_data) {
    $_SESSION['dashboard_message'] = $errors[0] ?? "Gagal memuat data trip.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=trips");
    // if (!$trip_data) {
    //     die("ERROR: Trip tidak ditemukan atau ID tidak valid. Cek koneksi DB atau kepemilikan.");
    // }
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
    <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">

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
                        <label for="destination" class="form-label">Tujuan Utama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="destination" name="destination" required value="<?php echo htmlspecialchars($trip_data['location']); ?>">
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
                        <label for="max_quota" class="form-label">Kuota Maksimal Peserta <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_quota" name="max_quota" required min="<?php echo $trip_data['booked_participants']; ?>" value="<?php echo htmlspecialchars($trip_data['max_participants']); ?>">
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

                    <div class="mb-3">
                        <label for="status" class="form-label">Status Trip</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="draft" <?php echo ($trip_data['status'] === 'draft' ? 'selected' : ''); ?>>Draft (Belum Dipublikasikan)</option>
                            <option value="published" <?php echo ($trip_data['status'] === 'published' ? 'selected' : ''); ?>>Published (Aktif)</option>
                            <option value="cancelled" <?php echo ($trip_data['status'] === 'cancelled' ? 'selected' : ''); ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <button type="submit" class="btn btn-success btn-lg mt-3"><i class="bi bi-pencil-square me-2"></i> Perbarui Trip</button>
    <a href="/dashboard?p=trips" class="btn btn-secondary btn-lg mt-3">Batalkan</a>
</form>

<?php 
// Kita akan menambahkan MODAL DELETE di trip_list.php, bukan di sini.
// ... (Tutup koneksi jika diperlukan)
?>