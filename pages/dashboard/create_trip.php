<?php
// File: pages/dashboard/create_trip.php

$message = $_SESSION['form_message'] ?? '';
$message_type = $_SESSION['form_message_type'] ?? 'danger';
unset($_SESSION['form_message']);
unset($_SESSION['form_message_type']);
?>

<h1 class="mb-4">Buat Trip Baru</h1>
<p class="text-muted">Isi detail perjalanan Anda. Trip ini akan diajukan untuk moderasi setelah Anda menyimpannya.</p>

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
                        <label for="location" class="form-label">Tujuan Utama (Lokasi di Tabel) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="location" name="location" required placeholder="Contoh: Gunung Bromo, Pulau Seribu">
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
                <div class="card-header bg-warning text-dark">Titik Kumpul & Jadwal</div>
                <div class="card-body">
                    <h6 class="mb-3">Lokasi Titik Kumpul</h6>
                    <div class="mb-3">
                        <label for="gathering_point_name" class="form-label">Nama Titik Kumpul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="gathering_point_name" name="gathering_point_name" required 
                               placeholder="Contoh: Stasiun Pasar Senen Jakarta / Bandara Juanda Surabaya">
                    </div>
                    <div class="mb-3">
                        <label for="gathering_point_url" class="form-label">URL Google Maps Titik Kumpul <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="gathering_point_url" name="gathering_point_url" required 
                               placeholder="Paste URL Google Maps di sini">
                        <small class="form-text text-muted">Contoh: https://maps.app.goo.gl/...</small>
                    </div>

                    <h6 class="mt-4 mb-3">Waktu Pelaksanaan</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departure_time" class="form-label">Estimasi Jam Berangkat <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="departure_time" name="departure_time" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="return_time" class="form-label">Estimasi Jam Pulang <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="return_time" name="return_time" required>
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
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="max_participants" class="form-label">Kuota Maksimal Peserta <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_participants" name="max_participants" required min="1" placeholder="Masukkan angka">
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
                <div class="card-header bg-info text-white">Media Trip</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="main_image" class="form-label">Foto Utama Trip <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*" required>
                        <small class="form-text text-muted">Hanya satu file, dijadikan cover.</small>
                    </div>

                    <div class="mb-3">
                        <label for="additional_images" class="form-label">Foto Tambahan (Opsional)</label>
                        <input type="file" class="form-control" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                        <small class="form-text text-muted">Pilih beberapa foto tujuan wisata atau banner sekaligus (Max 5 file).</small>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">Status</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status Awal Trip</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="draft" selected>Draft (Belum Dipublikasikan)</option>
                            <option value="available">Available (Siap Dipesan, setelah disetujui Admin)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><i class="bi bi-save me-2"></i> Ajukan Trip untuk Moderasi</button>
</form>