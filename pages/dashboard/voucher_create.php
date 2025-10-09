<?php
// File: pages/dashboard/voucher_create.php
// Form untuk Provider membuat voucher diskon baru

// Pastikan variabel otorisasi dari dashboard.php tersedia
global $actual_provider_id;

if (!$actual_provider_id) {
    echo "<div class='alert alert-danger'>Akses Ditolak: ID Provider tidak ditemukan.</div>";
    return;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Buat Voucher Diskon Baru</h1>
    <a href="/dashboard?p=vouchers" class="btn btn-secondary">
        <i class="bi bi-chevron-left"></i> Kembali ke Daftar Voucher
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/process/voucher_process.php" method="POST">
            <input type="hidden" name="action" value="create_voucher">
            
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label for="code" class="form-label">Kode Voucher <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="code" name="code" 
                           placeholder="Contoh: LIBURANKUY" required maxlength="50"
                           value="<?= htmlspecialchars($_SESSION['form_data']['code'] ?? '') ?>">
                    <small class="form-text text-muted">Kode unik yang akan dimasukkan pelanggan (maks. 50 karakter, Kapital).</small>
                </div>
                
                <div class="col-md-6">
                    <label for="max_usage" class="form-label">Batas Maksimal Penggunaan <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="max_usage" name="max_usage" 
                           required min="1" placeholder="Cth: 100"
                           value="<?= htmlspecialchars($_SESSION['form_data']['max_usage'] ?? '') ?>">
                    <small class="form-text text-muted">Total berapa kali voucher ini dapat diklaim.</small>
                </div>

                <div class="col-md-4">
                    <label for="type" class="form-label">Tipe Diskon <span class="text-danger">*</span></label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="percentage" <?= (($_SESSION['form_data']['type'] ?? '') == 'percentage') ? 'selected' : '' ?>>Persentase (%)</option>
                        <option value="fixed" <?= (($_SESSION['form_data']['type'] ?? '') == 'fixed') ? 'selected' : '' ?>>Nilai Tetap (Rp)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="value" class="form-label">Nilai Diskon <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="value" name="value" 
                           required min="1" step="0.01" placeholder="Cth: 10 atau 50000"
                           value="<?= htmlspecialchars($_SESSION['form_data']['value'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label for="min_purchase" class="form-label">Minimum Pembelian (Rp)</label>
                    <input type="number" class="form-control" id="min_purchase" name="min_purchase" 
                           min="0" step="1000" placeholder="Cth: 500000"
                           value="<?= htmlspecialchars($_SESSION['form_data']['min_purchase'] ?? '0') ?>">
                    <small class="form-text text-muted">Total harga minimal agar voucher berlaku.</small>
                </div>

                <div class="col-md-6">
                    <label for="valid_until" class="form-label">Valid Hingga <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" id="valid_until" name="valid_until" required
                           value="<?= htmlspecialchars($_SESSION['form_data']['valid_until'] ?? '') ?>">
                    <small class="form-text text-muted">Voucher akan nonaktif setelah tanggal dan jam ini.</small>
                </div>

                <div class="col-md-6">
                    <label for="is_active" class="form-label">Status Awal Voucher</label>
                    <select class="form-select" id="is_active" name="is_active" required>
                        <option value="1" selected>Aktif</option>
                        <option value="0">Draft / Nonaktif</option>
                    </select>
                </div>

            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Voucher</button>
            </div>
        </form>
    </div>
</div>

<?php 
// Bersihkan form_data setelah ditampilkan
unset($_SESSION['form_data']); 
?>