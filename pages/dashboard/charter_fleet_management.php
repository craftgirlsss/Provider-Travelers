<?php
// File: pages/dashboard/charter_fleet_management.php
// Di-include oleh dashboard.php

// Pastikan $actual_provider_id sudah tersedia dari dashboard.php
if (!isset($actual_provider_id) || !$actual_provider_id) {
    echo '<div class="alert alert-danger">Error: ID Provider tidak ditemukan.</div>';
    return;
}

$action = $_GET['action'] ?? 'list'; // Default action adalah menampilkan daftar
$fleet_id = $_GET['id'] ?? null;
$error = null;
$success = null;
$charter_fleet = [];
$edit_data = [];

// === LOGIKA FETCH DATA ===

// 1. Ambil Data Armada Charter (Diperlukan untuk 'list', dan juga 'edit' untuk otorisasi)
try {
    // Ambil semua data jika mode list, atau ambil data spesifik jika mode edit
    $sql = "SELECT id, vehicle_name, capacity, type, base_price_per_day, 
            description, photo_path, is_available 
            FROM charter_fleet 
            WHERE provider_id = ?";
            
    if ($action === 'edit' && $fleet_id) {
        $sql .= " AND id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $actual_provider_id, $fleet_id);
    } else {
        $sql .= " ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $actual_provider_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($action === 'edit' && $result->num_rows > 0) {
        // Jika mode edit, ambil hanya satu baris
        $edit_data = $result->fetch_assoc();
    } else {
        // Jika mode list, ambil semua baris
        while ($row = $result->fetch_assoc()) {
            $charter_fleet[] = $row;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    $error = "Gagal memuat data armada: " . $e->getMessage();
}

// Cek jika edit data tidak ditemukan (dan mode adalah edit)
if ($action === 'edit' && empty($edit_data)) {
    $error = "Data armada yang ingin diedit tidak ditemukan atau bukan milik Anda.";
    $action = 'list';
}


// === TAMPILKAN PESAN DARI PROCESS FILE ===
if (isset($_SESSION['charter_message'])) {
    if ($_SESSION['charter_message_type'] === 'success') {
        $success = $_SESSION['charter_message'];
    } else {
        $error = $_SESSION['charter_message'];
    }
    unset($_SESSION['charter_message']);
    unset($_SESSION['charter_message_type']);
}

?>

<h1 class="mb-4 text-primary">Kelola Armada Sewa <i class="bi bi-bus-front"></i></h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php 
// -----------------------------------------------------
// --- TAMPILAN FORM TAMBAH / EDIT (TIDAK BERUBAH) ---
// -----------------------------------------------------
if ($action === 'add' || $action === 'edit'): 
    $is_edit = $action === 'edit';
    $form_title = $is_edit ? 'Edit Armada: ' . htmlspecialchars($edit_data['vehicle_name'] ?? 'N/A') : 'Tambahkan Armada Baru';
    $submit_action = $is_edit ? 'update_fleet' : 'add_fleet';

    // Format harga untuk ditampilkan (Hanya untuk edit)
    $display_price = $edit_data['base_price_per_day'] ?? '';
    if ($display_price) {
        // Terapkan format Rupiah awal ke nilai yang sudah ada di DB
        $display_price = number_format($display_price, 0, ',', '.');
    }
?>

<div class="card shadow-lg mb-5">
    <div class="card-header bg-dark text-white fw-bold h5">
        <?php echo $form_title; ?>
    </div>
    <div class="card-body">
        <form id="fleetForm" action="/process/charter_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $submit_action; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="fleet_id" value="<?php echo htmlspecialchars($fleet_id); ?>">
                <input type="hidden" name="old_photo_path" value="<?php echo htmlspecialchars($edit_data['photo_path'] ?? ''); ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="vehicle_name" class="form-label fw-bold">Nama Armada (Contoh: Big Bus, Toyota Hiace)</label>
                    <input type="text" class="form-control" id="vehicle_name" name="vehicle_name" 
                           value="<?php echo htmlspecialchars($edit_data['vehicle_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="capacity" class="form-label fw-bold">Kapasitas Penumpang</label>
                    <input type="number" class="form-control" id="capacity" name="capacity" min="1"
                           value="<?php echo htmlspecialchars($edit_data['capacity'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="type" class="form-label fw-bold">Tipe Kendaraan</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">-- Pilih Tipe --</option>
                        <?php 
                        $types = ['bus', 'van', 'car', 'other'];
                        $current_type = $edit_data['type'] ?? '';
                        foreach ($types as $t): 
                        ?>
                            <option value="<?php echo $t; ?>" <?php echo $current_type === $t ? 'selected' : ''; ?>>
                                <?php echo ucfirst($t); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="base_price_per_day" class="form-label fw-bold">Harga Sewa Dasar Harian (Rp)</label>
                    <input type="text" class="form-control" id="base_price_per_day" name="base_price_per_day_display" 
                           value="<?php echo htmlspecialchars($display_price); ?>" required
                           placeholder="Masukkan harga TANPA BBM/Supir/Tol"
                           oninput="formatRupiah(this, 'Rp ')" 
                           onkeyup="formatRupiah(this, 'Rp ')">
                           
                    <input type="hidden" id="base_price_per_day_clean" name="base_price_per_day" value="<?php echo htmlspecialchars($edit_data['base_price_per_day'] ?? ''); ?>">
                    
                    <small class="text-muted">Ini adalah harga dasar yang akan dilihat client untuk memulai negosiasi.</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="photo_file" class="form-label fw-bold">Foto Armada</label>
                    <input type="file" class="form-control" id="photo_file" name="photo_file" accept=".jpg,.jpeg,.png" <?php echo !$is_edit ? 'required' : ''; ?>>
                    <?php if (!empty($edit_data['photo_path'])): ?>
                        <div class="mt-2">
                            <small class="text-success me-3">Foto saat ini:</small>
                            <img src="/<?php echo htmlspecialchars($edit_data['photo_path']); ?>" alt="Foto Armada" style="max-height: 50px; border-radius: 5px;">
                        </div>
                    <?php endif; ?>
                    <small class="text-muted d-block">Maks 2MB. Hanya JPG/PNG.</small>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-bold">Deskripsi & Fasilitas Armada</label>
                <textarea class="form-control" id="description" name="description" rows="4" required
                          placeholder="Contoh: Full AC, Reclining Seat, USB Charger, Karaoke Set."><?php echo htmlspecialchars($edit_data['description'] ?? ''); ?></textarea>
                <small class="text-muted">Jelaskan fasilitas yang ditawarkan kendaraan ini.</small>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-bold">Status Armada</label>
                <div class="form-check form-switch">
                    <?php $is_available = ($edit_data['is_available'] ?? 1) == 1; ?>
                    <input class="form-check-input" type="checkbox" role="switch" id="statusSwitch" name="is_available" value="1" <?php echo $is_available ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="statusSwitch">
                        <?php echo $is_available ? '<span class="text-success">Tersedia untuk disewa</span>' : '<span class="text-danger">Sedang Maintenance</span>'; ?>
                    </label>
                </div>
            </div>
            
            <hr>

            <div class="d-flex justify-content-end">
                <a href="?p=charter_fleet" class="btn btn-outline-secondary me-2">Batal</a>
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="bi bi-save me-2"></i> <?php echo $is_edit ? 'Simpan Perubahan' : 'Tambahkan Armada'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function formatRupiah(field, prefix) {
        let input = field.value;
        let number_string = input.replace(/[^,\d]/g, '').toString();
        let split = number_string.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        
        field.value = rupiah ? prefix + rupiah : '';

        let cleanValue = number_string.replace(/[^0-9]/g, '');
        document.getElementById('base_price_per_day_clean').value = cleanValue;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const priceField = document.getElementById('base_price_per_day');
        if (priceField && priceField.value) {
            formatRupiah(priceField, 'Rp ');
        }
    });
</script>

<?php 
// -----------------------------------------------------
// --- TAMPILAN DAFTAR ARMADA (DIKOREKSI KE CARD) ---
// -----------------------------------------------------
else: // $action === 'list' 
?>

<div class="d-flex justify-content-end mb-4">
    <a href="?p=charter_fleet&action=add" class="btn btn-primary fw-bold shadow-sm">
        <i class="bi bi-plus-circle me-2"></i> Tambah Armada Baru
    </a>
</div>

<div class="card shadow-lg">
    <div class="card-header bg-light fw-bold text-dark h5">
        Daftar Kendaraan Sewa (Total: <?php echo count($charter_fleet); ?>)
    </div>
    <div class="card-body p-0">
        
        <?php if (empty($charter_fleet)): ?>
            <div class="alert alert-info text-center m-4">
                Anda belum mendaftarkan armada untuk layanan sewa. Silakan klik "Tambah Armada Baru".
            </div>
        <?php else: ?>
            
            <div class="list-group list-group-flush">
            <?php foreach ($charter_fleet as $fleet): 
                $status_class = $fleet['is_available'] == 1 ? 'border-success' : 'border-danger';
                $status_text = $fleet['is_available'] == 1 ? 'Tersedia' : 'Maintenance';
                
                // --- Menentukan Foto atau Placeholder ---
                $photo_url = !empty($fleet['photo_path']) ? '/' . htmlspecialchars($fleet['photo_path']) : 'https://via.placeholder.com/80x50?text=No+Photo';
                $photo_style = 'style="width: 80px; height: 50px; object-fit: cover;"';
            ?>
                <div class="list-group-item list-group-item-action py-3 border-start-0 border-end-0 border-bottom-0 <?php echo $status_class; ?>" style="border-left: 6px solid; border-right: 6px solid;">
                    
                    <div class="row align-items-center">
                        <div class="col-md-9 col-sm-8">
                            <div class="d-flex align-items-center mb-1">
                                <?php if ($fleet['is_available'] == 1): ?>
                                    <span class="badge bg-success me-2"><i class="bi bi-check-circle"></i> <?php echo $status_text; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger me-2"><i class="bi bi-wrench"></i> <?php echo $status_text; ?></span>
                                <?php endif; ?>
                                
                                <span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($fleet['type'])); ?></span>
                            </div>
                            
                            <h5 class="fw-bold text-primary mb-1">
                                <?php echo htmlspecialchars($fleet['vehicle_name']); ?>
                            </h5>
                            
                            <p class="text-muted small mb-0">
                                <i class="bi bi-people me-1"></i> Kapasitas: <b><?php echo htmlspecialchars($fleet['capacity']); ?></b> orang
                            </p>
                            
                            </div>
                        
                        <div class="col-md-3 col-sm-4 text-end">
                            <div class="d-flex flex-column align-items-end">
                                
                                <img src="<?php echo $photo_url; ?>" alt="Foto Armada" class="img-fluid rounded mb-2" <?php echo $photo_style; ?>>

                                <span class="fw-bold h6 text-success d-block mb-2">
                                    Rp <?php echo number_format($fleet['base_price_per_day'], 0, ',', '.'); ?>
                                </span>
                                
                                <div class="btn-group" role="group">
                                    <a href="?p=charter_fleet&action=edit&id=<?php echo $fleet['id']; ?>" class="btn btn-sm btn-info" title="Edit Armada">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <button type="button" onclick="confirmDelete(<?php echo $fleet['id']; ?>)" class="btn btn-sm btn-danger" title="Hapus Armada">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            
        <?php endif; ?>

    </div>
</div>

<form id="delete-form" method="POST" action="/process/charter_process.php" style="display:none;">
    <input type="hidden" name="action" value="delete_fleet">
    <input type="hidden" name="fleet_id" id="delete-fleet-id">
    <input type="hidden" name="photo_path" id="delete-photo-path">
</form>

<script>
function confirmDelete(fleetId) {
    if (confirm('Anda yakin ingin menghapus armada ini? Aksi ini tidak dapat dibatalkan.')) {
        document.getElementById('delete-fleet-id').value = fleetId;
        document.getElementById('delete-form').submit();
    }
}
</script>

<?php endif; // End of list/add/edit view logic ?>