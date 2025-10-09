<?php
// File: pages/dashboard/voucher_edit.php
// Halaman untuk mengedit Voucher yang sudah ada.

// Panggil variabel yang sudah disiapkan oleh dashboard.php
global $conn, $actual_provider_id; 

$voucher_id = (int)($_GET['id'] ?? 0);
$error = null;
$voucher = null; // Data voucher yang akan dimuat
$redirect_page = 'vouchers';

// 1. Cek Otorisasi Dasar
if (!isset($conn) || !is_object($conn)) {
    $error = "Kesalahan Fatal: Koneksi database (\$conn) hilang.";
} elseif (!$actual_provider_id) {
    $error = "Akses Ditolak: ID Provider tidak ditemukan. Mohon login ulang.";
} elseif ($voucher_id <= 0) {
    $error = "Kesalahan: ID Voucher tidak valid.";
}


// 2. Ambil Data Voucher
if (!$error) {
    try {
        $sql = "SELECT 
                    id, uuid, code, type, value, max_usage, min_purchase, valid_until, is_active
                FROM 
                    vouchers
                WHERE 
                    id = ? AND provider_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $voucher_id, $actual_provider_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Voucher tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.";
        } else {
            $voucher = $result->fetch_assoc();
            
            // Format valid_until ke format datetime-local (YYYY-MM-DDTHH:MM)
            $valid_until_dt = new DateTime($voucher['valid_until']);
            $voucher['valid_until_form'] = $valid_until_dt->format('Y-m-d\TH:i');
        }
        $stmt->close();

    } catch (Exception $e) {
        $error = "Gagal memuat data voucher: " . $e->getMessage();
    }
}


// 3. Muat Data Form dari Session jika Gagal Submit
// Ini memastikan data yang diinput user tidak hilang jika terjadi error validasi
$form_data = $_SESSION['form_data'] ?? [];
if (!empty($form_data)) {
    // Jika ada error, gunakan data dari session
    $voucher = array_merge($voucher, $form_data);
    
    // Perlu re-format tanggal jika diambil dari session (raw POST)
    if (isset($form_data['valid_until'])) {
         $voucher['valid_until_form'] = $form_data['valid_until'];
    }
    unset($_SESSION['form_data']); 
}

// Ambil pesan dari session (dari voucher_process.php)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Edit Voucher: <?php echo htmlspecialchars($voucher['code'] ?? 'N/A'); ?></h1>
    <a href="/dashboard?p=vouchers" class="btn btn-secondary">
        <i class="bi bi-chevron-left"></i> Kembali ke Daftar Voucher
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!$error && $voucher): ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="/process/voucher_process.php" method="POST">
                <input type="hidden" name="action" value="update_voucher">
                <input type="hidden" name="voucher_id" value="<?= htmlspecialchars($voucher['id']) ?>">
                <input type="hidden" name="voucher_uuid" value="<?= htmlspecialchars($voucher['uuid']) ?>">
                
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <label for="code" class="form-label">Kode Voucher</label>
                        <input type="text" class="form-control" id="code" name="code" 
                               value="<?= htmlspecialchars($voucher['code'] ?? '') ?>" disabled>
                        <small class="form-text text-muted">Kode tidak dapat diubah setelah dibuat.</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="max_usage" class="form-label">Batas Maksimal Penggunaan <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_usage" name="max_usage" 
                               required min="1" placeholder="Cth: 100"
                               value="<?= htmlspecialchars($voucher['max_usage'] ?? '') ?>">
                        <small class="form-text text-muted">Total berapa kali voucher ini dapat diklaim.</small>
                    </div>

                    <div class="col-md-4">
                        <label for="type" class="form-label">Tipe Diskon</label>
                        <select class="form-select" id="type" name="type" disabled>
                            <option value="percentage" <?= ($voucher['type'] == 'percentage') ? 'selected' : '' ?>>Persentase (%)</option>
                            <option value="fixed" <?= ($voucher['type'] == 'fixed') ? 'selected' : '' ?>>Nilai Tetap (Rp)</option>
                        </select>
                         <input type="hidden" name="type" value="<?= htmlspecialchars($voucher['type']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="value" class="form-label">Nilai Diskon <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="value" name="value" 
                               required min="1" step="0.01" placeholder="Cth: 10 atau 50000"
                               value="<?= htmlspecialchars($voucher['value'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="min_purchase" class="form-label">Minimum Pembelian (Rp)</label>
                        <input type="number" class="form-control" id="min_purchase" name="min_purchase" 
                               min="0" step="1000" placeholder="Cth: 500000"
                               value="<?= htmlspecialchars($voucher['min_purchase'] ?? '0') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="valid_until" class="form-label">Valid Hingga <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="valid_until" name="valid_until" required
                               value="<?= htmlspecialchars($voucher['valid_until_form'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="is_active" class="form-label">Status Voucher</label>
                        <select class="form-select" id="is_active" name="is_active" required>
                            <option value="1" <?= ($voucher['is_active'] == 1) ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= ($voucher['is_active'] == 0) ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>

                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Perbarui Voucher</button>
                    <a href="/dashboard?p=vouchers" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>