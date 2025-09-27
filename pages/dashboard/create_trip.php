<?php
// File: pages/dashboard/create_trip.php

// Anda dapat menambahkan logika untuk mengambil data dari DB jika diperlukan
// Misalnya, daftar kategori atau tujuan yang sudah didefinisikan sebelumnya.

// Tampilkan pesan sukses/gagal dari proses sebelumnya (jika ada)
$message = $_SESSION['form_message'] ?? '';
$message_type = $_SESSION['form_message_type'] ?? 'danger';
unset($_SESSION['form_message']);
unset($_SESSION['form_message_type']);
?>

<h1 class="mb-4">Buat Trip Baru</h1>
<p class="text-muted">Isi detail perjalanan Anda. Trip ini akan dipublikasikan setelah Anda menyimpannya.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="/process/trip_process" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="create_trip">

    <div class="row">
        <div class="col-md-7">
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">Informasi Dasar Trip</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Nama Trip / Judul Paket <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required placeholder="Contoh: Open Trip Bromo 3D2N">
                    </div>

                    <div class="mb-3">
                        <label for="destination" class="form-label">Tujuan Utama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="destination" name="destination" required placeholder="Contoh: Gunung Bromo, Pulau Seribu">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi Lengkap Trip <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required placeholder="Jelaskan detail itinerary, fasilitas, dan poin menarik lainnya."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duration" class="form-label">Durasi (Contoh: 3 Hari 2 Malam) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="duration" name="duration" required placeholder="Contoh: 3D2N">
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">Tanggal dan Kuota</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="max_quota" class="form-label">Kuota Maksimal Peserta <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_quota" name="max_quota" required min="1" placeholder="Masukkan angka">
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
                        <input type="number" class="form-control" id="price" name="price" required min="0" placeholder="Contoh: 1500000">
                    </div>
                    
                    <div class="mb-3">
                        <label for="discount_price" class="form-label">Harga Diskon (Opsional)</label>
                        <input type="number" class="form-control" id="discount_price" name="discount_price" placeholder="Biarkan kosong jika tidak ada diskon">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">Media & Status</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="main_image" class="form-label">Foto Utama Trip <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*" required>
                        <small class="form-text text-muted">Format: JPG, PNG. Max 2MB.</small>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status Awal Trip</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="draft" selected>Draft (Belum Dipublikasikan)</option>
                            <option value="published">Published (Langsung Aktif)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><i class="bi bi-save me-2"></i> Simpan & Publikasikan Trip</button>
</form>