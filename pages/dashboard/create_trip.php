<?php
// File: pages/dashboard/create_trip.php

global $conn, $user_id_from_session, $actual_provider_id;

require_once __DIR__ . '/../../utils/check_provider_verification.php';
check_provider_verification($conn, $actual_provider_id, "Buat Trip");

$message = $_SESSION['form_message'] ?? '';
$message_type = $_SESSION['form_message_type'] ?? 'danger';
unset($_SESSION['form_message']);
unset($_SESSION['form_message_type']);

// ==========================================================
// 1. AMBIL DATA MASTER (KENDARAAN & TOUR GUIDE)
// ==========================================================
$vehicles = [];
$guides = [];

try {
    // A. Ambil Daftar Kendaraan (Tersedia)
    $stmt_v = $conn->prepare("SELECT id, name, license_plate, capacity FROM vehicles WHERE provider_id = ? AND status = 'available' ORDER BY name ASC");
    $stmt_v->bind_param("i", $actual_provider_id);
    $stmt_v->execute();
    $result_v = $stmt_v->get_result();
    while ($row = $result_v->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt_v->close();

    // B. Ambil Daftar Tour Guide (Aktif)
    $stmt_g = $conn->prepare("SELECT id, name, specialization FROM tour_guides WHERE provider_id = ? AND status = 'active' ORDER BY name ASC");
    $stmt_g->bind_param("i", $actual_provider_id);
    $stmt_g->execute();
    $result_g = $stmt_g->get_result();
    while ($row = $result_g->fetch_assoc()) {
        $guides[] = $row;
    }
    $stmt_g->close();

} catch (Exception $e) {
    // Jika ada error pengambilan data master
    $message = "Gagal memuat data master (Kendaraan/Pemandu): " . $e->getMessage();
    $message_type = "warning";
}

?>

<style>
    /* Style untuk input waktu yang lebih mencolok (optional) */
    .time-input {
        font-size: 1.1rem;
        font-weight: bold;
    }
</style>

<h1 class="mb-4">Buat Trip Baru</h1>
<p class="text-muted">Isi detail perjalanan Anda. Trip ini akan diajukan untuk moderasi setelah Anda menyimpannya.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="/process/trip_process.php" method="POST" enctype="multipart/form-data" id="tripForm">
    <input type="hidden" name="action" value="create_trip">
    
    <input type="hidden" id="price_hidden" name="price" value="">
    <input type="hidden" id="discount_price_hidden" name="discount_price" value="">

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

                    <div class="mb-3">
                        <label for="trip_type" class="form-label">Jenis Perjalanan <span class="text-danger">*</span></label>
                        <select class="form-select" id="trip_type" name="trip_type" required>
                            <option value="local">Lokal (Domestik)</option>
                            <option value="international">Internasional</option>
                        </select>
                    </div>

                    <div id="international_transport_group" class="card card-body bg-light mb-4" style="display:none;">
                        <h6 class="mb-3">Detail Transportasi Internasional <span class="text-danger">*</span></h6>
                        <div class="mb-3">
                            <label for="international_transport_type" class="form-label">Tipe Transportasi</label>
                            <select class="form-select" id="international_transport_type" name="international_transport_type">
                                <option value="">-- Pilih Tipe --</option>
                                <option value="plane">Pesawat (Plane)</option>
                                <option value="ship">Kapal (Ship)</option>
                                <option value="train">Kereta (Train)</option>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label for="international_transport_detail" class="form-label">Nomor/Detail Transportasi (Contoh: Nomor Penerbangan)</label>
                            <input type="text" class="form-control" id="international_transport_detail" name="international_transport_detail" 
                                placeholder="Contoh: Garuda Indonesia GA-872">
                        </div>
                    </div>
                </div>
            </div>
            

            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-dark fw-bold">Detail Layanan Tambahan (Hotel, Pemandu & Kendaraan)</div>
                <div class="card-body">
                    
                    <h5 class="mb-3 text-dark">Kendaraan Utama <span class="text-danger">*</span></h5>
                    <div class="mb-4">
                        <label for="vehicle_id" class="form-label">Pilih Kendaraan yang Digunakan</label>
                        <select class="form-select" id="vehicle_id" name="vehicle_id" required 
                                <?php echo empty($vehicles) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Kendaraan --</option>
                            <?php if (!empty($vehicles)): ?>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= $v['id'] ?>">
                                        <?= htmlspecialchars($v['name']) ?> (Plat: <?= htmlspecialchars($v['license_plate']) ?> | Kap: <?= $v['capacity'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Anda belum mendaftarkan Kendaraan yang Tersedia.</option>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($vehicles)): ?>
                            <small class="text-danger">Anda harus <a href="/dashboard?p=vehicle_create" class="alert-link">mendaftarkan kendaraan</a> terlebih dahulu.</small>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 text-dark">Akomodasi / Penginapan (Opsional)</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch_hotel" name="has_accommodation">
                            <label class="form-check-label" for="switch_hotel">Aktifkan Hotel</label>
                        </div>
                    </div>
                    
                    <div id="hotel_fields" class="mb-4 collapse">
                        <label for="accommodation_details" class="form-label">Nama / Detail Hotel</label>
                        <input type="text" class="form-control" id="accommodation_details" name="accommodation_details" disabled 
                               placeholder="Contoh: Hotel Bintang 3 Setara X di area Y">
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 text-dark">Pemandu Wisata (Tour Guide) (Opsional)</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch_guide" name="has_tour_guide" 
                                   <?php echo empty($guides) ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="switch_guide">Aktifkan Pemandu</label>
                        </div>
                    </div>
                    
                    <div id="guide_fields" class="collapse">
                        <label for="main_guide_id" class="form-label">Pilih Pemandu Utama</label>
                        <select class="form-select" id="main_guide_id" name="main_guide_id" disabled>
                            <option value="">-- Pilih Pemandu --</option>
                            <?php if (!empty($guides)): ?>
                                <?php foreach ($guides as $g): ?>
                                    <option value="<?= $g['id'] ?>">
                                        <?= htmlspecialchars($g['name']) ?> (Spesialisasi: <?= htmlspecialchars($g['specialization'] ?: 'Umum') ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Anda belum mendaftarkan Tour Guide Aktif.</option>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($guides)): ?>
                            <small class="text-danger">Anda harus <a href="/dashboard?p=tour_guide_create" class="alert-link">mendaftarkan Tour Guide</a> terlebih dahulu untuk menggunakan fitur ini.</small>
                        <?php endif; ?>
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
                            <input type="time" class="form-control time-input" id="departure_time" name="departure_time" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="return_time" class="form-label">Estimasi Jam Pulang <span class="text-danger">*</span></label>
                            <input type="time" class="form-control time-input" id="return_time" name="return_time" required>
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
                        <label for="price_display" class="form-label">Harga per Orang (IDR) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">IDR</span>
                            <input type="text" class="form-control currency-input" id="price_display" required placeholder="Contoh: 1.500.000">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="discount_price_display" class="form-label">Harga Setelah Diskon (Opsional)</label>
                        <div class="input-group">
                            <span class="input-group-text">IDR</span>
                            <input type="text" class="form-control currency-input" id="discount_price_display" placeholder="Biarkan kosong jika tidak ada diskon">
                        </div>
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
                        <select class="form-select" id="status" name="status" required disabled>
                            <option value="available" selected>Available (Siap Dipesan)</option>
                        </select>
                        <input type="hidden" name="status" value="available">
                        <small class="form-text text-muted">Status trip akan menjadi <b>Available</b> secara default, tetapi baru dapat dipublikasikan setelah disetujui Admin.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><i class="bi bi-save me-2"></i> Ajukan Trip untuk Moderasi</button>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const switchHotel = document.getElementById('switch_hotel');
        const hotelFields = document.getElementById('hotel_fields');
        const accommodationDetails = document.getElementById('accommodation_details');

        const tripTypeSelect = document.getElementById('trip_type');
        const internationalTransportGroup = document.getElementById('international_transport_group');
        const internationalTransportType = document.getElementById('international_transport_type');
        const internationalTransportDetail = document.getElementById('international_transport_detail');

        const switchGuide = document.getElementById('switch_guide');
        const guideFields = document.getElementById('guide_fields');
        const mainGuideId = document.getElementById('main_guide_id');
        
        const priceDisplay = document.getElementById('price_display');
        const discountPriceDisplay = document.getElementById('discount_price_display');
        const priceHidden = document.getElementById('price_hidden');
        const discountPriceHidden = document.getElementById('discount_price_hidden');
        const form = document.getElementById('tripForm');

        // ==========================================================
        // LOGIKA HOTEL & GUIDE (EXISTING)
        // ==========================================================

        function toggleInternationalFields() {
            const isInternational = tripTypeSelect.value === 'international';
            
            internationalTransportGroup.style.display = isInternational ? 'block' : 'none';
            
            // Set required/disabled
            internationalTransportType.required = isInternational;
            internationalTransportDetail.required = isInternational;
            
            if (!isInternational) {
                // Clear values if switched back to local
                internationalTransportType.value = '';
                internationalTransportDetail.value = '';
            }
        }

        tripTypeSelect.addEventListener('change', toggleInternationalFields);

        function toggleHotelFields() {
            if (switchHotel.checked) {
                hotelFields.classList.add('show');
                accommodationDetails.disabled = false;
                accommodationDetails.required = true;
            } else {
                hotelFields.classList.remove('show');
                accommodationDetails.disabled = true;
                accommodationDetails.required = false;
                accommodationDetails.value = '';
            }
        }

        function toggleGuideFields() {
            const isGuideSwitchActive = switchGuide.checked;
            const isDropdownAvailable = !mainGuideId.hasAttribute('disabled'); // Cek status awal (jika tidak ada guide, tetap disabled)
            
            if (isGuideSwitchActive && !mainGuideId.hasAttribute('data-initial-disabled')) { // Tambahkan pengecekan ketersediaan pemandu
                guideFields.classList.add('show');
                mainGuideId.disabled = false;
                mainGuideId.required = true; // WAJIB DIISI JIKA AKTIF
            } else {
                guideFields.classList.remove('show');
                // Kuncinya: Set disabled ke TRUE, tapi HAPUS required
                mainGuideId.disabled = true; 
                mainGuideId.required = false; // HAPUS ATRIBUT REQUIRED
                mainGuideId.value = '';
            }
        }

        switchHotel.addEventListener('change', toggleHotelFields);
        switchGuide.addEventListener('change', toggleGuideFields);
        
        // ==========================================================
        // LOGIKA CURRENCY FORMATTER (BARU)
        // ==========================================================

        const currencyInput = document.querySelectorAll('.currency-input');
        
        function formatRupiah(angka, prefix) {
            var number_string = angka.replace(/[^,\d]/g, '').toString(),
            split   = number_string.split(','),
            sisa    = split[0].length % 3,
            rupiah  = split[0].substr(0, sisa),
            ribuan  = split[0].substr(sisa).match(/\d{3}/gi);
    
            if(ribuan){
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
    
            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
        }

        function cleanRupiah(angka) {
            // Menghapus semua karakter kecuali digit (0-9)
            return parseInt(angka.replace(/[^0-9]/g, '')) || 0;
        }

        currencyInput.forEach(input => {
            input.addEventListener('keyup', function(e) {
                // Set nilai input field display
                this.value = formatRupiah(this.value);

                // Update nilai hidden field (nilai mentah/raw)
                const rawValue = cleanRupiah(this.value);
                if (this.id === 'price_display') {
                    priceHidden.value = rawValue;
                } else if (this.id === 'discount_price_display') {
                    discountPriceHidden.value = rawValue;
                }
            });

            // Pastikan nilai mentah dikirim saat form di-submit
            input.addEventListener('blur', function() {
                 const rawValue = cleanRupiah(this.value);
                 if (this.id === 'price_display') {
                    priceHidden.value = rawValue;
                } else if (this.id === 'discount_price_display') {
                    discountPriceHidden.value = rawValue;
                }
            });
        });
        
        // ==========================================================
        // LOGIKA SUBMIT (Memastikan nilai harga terkirim)
        // ==========================================================
        
        form.addEventListener('submit', function(e) {
            // Pastikan hidden fields terisi dengan nilai mentah sebelum submit
            priceHidden.value = cleanRupiah(priceDisplay.value);
            discountPriceHidden.value = cleanRupiah(discountPriceDisplay.value);
            
            // Lakukan validasi harga minimal di client side (optional)
            if (parseInt(priceHidden.value) <= 0) {
                e.preventDefault();
                alert("Harga per Orang wajib diisi dan harus lebih dari 0.");
                priceDisplay.focus();
            }

            // Lanjutkan submit (PHP akan melakukan validasi akhir)
        });


        // Inisialisasi status awal
        toggleHotelFields();
        toggleGuideFields();
        toggleInternationalFields();
    });
</script>