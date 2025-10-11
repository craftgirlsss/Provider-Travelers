<?php
// File: pages/dashboard/voucher_list.php

// Panggil variabel yang sudah disiapkan oleh dashboard.php
global $conn, $actual_provider_id; 

// =======================================================================
// Otorisasi & Cek Lingkungan
// =======================================================================

$error = null;
$voucher_data = [];
$provider_id = $actual_provider_id ?? null;

if (!isset($conn) || !is_object($conn)) {
    $error = "Kesalahan Fatal: Koneksi database (\$conn) hilang dari global scope.";
} elseif (!$provider_id) {
    $error = "Kesalahan Otorisasi: ID Provider tidak ditemukan. Mohon login ulang.";
}

// Ambil pesan dari session (setelah create/update)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);


// =======================================================================
// Ambil Data Voucher (DIPERBARUI: Ambil image_path)
// =======================================================================
if (!$error) {
    try {
        $sql = "SELECT 
                    id, 
                    code, 
                    type, 
                    value, 
                    max_usage, 
                    usage_count, 
                    min_purchase, 
                    valid_until, 
                    is_active,
                    image_path /* BARU */
                FROM 
                    vouchers
                WHERE 
                    provider_id = ?
                ORDER BY 
                    created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $provider_id);
        $stmt->execute();
        $voucher_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } catch (Exception $e) {
        $error = "Gagal memuat data voucher: " . $e->getMessage();
    }
}


/**
 * Fungsi Pembantu untuk Format Nilai Voucher
 */
function format_voucher_value($type, $value) {
    if ($type === 'percentage') {
        return number_format($value, 0) . '%';
    } else {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}

// =======================================================================
// Logika Pengelompokan Data ke dalam Tabs
// =======================================================================
$grouped_vouchers = [
    'active' => [],
    'expired' => [],
    'inactive' => [] // Termasuk yang dinonaktifkan/dihapus
];

foreach ($voucher_data as $voucher) {
    $is_expired = strtotime($voucher['valid_until']) < time();
    $remaining_usage = $voucher['max_usage'] - $voucher['usage_count'];
    $is_used_up = $remaining_usage <= 0;
    
    // Tentukan status grouping
    if ($voucher['is_active'] == 1 && !$is_expired && !$is_used_up) {
        $grouped_vouchers['active'][] = $voucher;
    } elseif ($is_expired) {
        // Expired selalu ditaruh di tab expired, terlepas dari is_active-nya
        $grouped_vouchers['expired'][] = $voucher;
    } else {
        // is_active = 0, atau is_used_up (tetapi belum expired)
        $grouped_vouchers['inactive'][] = $voucher;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Manajemen Voucher Diskon</h1>
    <a href="/dashboard?p=voucher_create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Buat Voucher Baru
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

<div class="card shadow-sm">
    <div class="card-body">
        
        <ul class="nav nav-tabs mb-3" id="voucherTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true">
                    Aktif (<?= count($grouped_vouchers['active']) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="expired-tab" data-bs-toggle="tab" data-bs-target="#expired" type="button" role="tab" aria-controls="expired" aria-selected="false">
                    Expired (<?= count($grouped_vouchers['expired']) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button" role="tab" aria-controls="inactive" aria-selected="false">
                    Dinonaktifkan / Habis (<?= count($grouped_vouchers['inactive']) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="voucherTabsContent">
            
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <?php echo generateVoucherTable($grouped_vouchers['active']); ?>
            </div>
            
            <div class="tab-pane fade" id="expired" role="tabpanel" aria-labelledby="expired-tab">
                <?php echo generateVoucherTable($grouped_vouchers['expired'], $is_editable = false); ?>
            </div>

            <div class="tab-pane fade" id="inactive" role="tabpanel" aria-labelledby="inactive-tab">
                 <?php echo generateVoucherTable($grouped_vouchers['inactive'], $is_editable = false); ?>
            </div>

        </div> </div>
</div>

<?php 
// ----------------------------------------------------------------------
// FUNGSI UNTUK MERENDER TABEL VOUCHER
// Ini digunakan untuk menghindari duplikasi kode tabel di setiap tab
// ----------------------------------------------------------------------

/**
 * Merender tabel HTML untuk daftar voucher yang diberikan.
 * @param array $vouchers Data voucher yang akan ditampilkan.
 * @param bool $is_editable Apakah tombol Edit harus aktif.
 * @return string HTML dari tabel.
 */
function generateVoucherTable(array $vouchers, $is_editable = true) {
    
    // Jika tidak ada data, tampilkan pesan
    if (empty($vouchers)) {
        return '<p class="text-center p-3 text-muted">Tidak ada voucher dalam kategori ini.</p>';
    }
    
    // Mulai buffer output
    ob_start(); 
    ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 5%;"></th> <th style="width: 15%;">Kode Voucher</th>
                    <th style="width: 15%;">Tipe & Nilai</th>
                    <th style="width: 15%;">Min. Pembelian</th>
                    <th style="width: 15%;">Batas Penggunaan</th>
                    <th style="width: 15%;">Valid Hingga</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vouchers as $voucher):
                    $remaining_usage = $voucher['max_usage'] - $voucher['usage_count'];
                    $is_expired = strtotime($voucher['valid_until']) < time();
                    $is_used_up = $remaining_usage <= 0;
                    
                    // Logic Badge Status
                    $status_badge = '<span class="badge bg-secondary">Tidak Diketahui</span>';
                    
                    if ($is_expired) {
                        $status_badge = '<span class="badge bg-secondary">Expired</span>';
                    } elseif ($voucher['is_active'] == 0) {
                        $status_badge = '<span class="badge bg-danger">Dinonaktifkan</span>';
                    } elseif ($is_used_up) {
                        $status_badge = '<span class="badge bg-danger">Habis</span>';
                    } elseif ($voucher['is_active'] == 1) {
                         $status_badge = '<span class="badge bg-success">Aktif</span>';
                    }
                    
                    $is_disabled = (!$is_editable || $voucher['is_active'] == 0 || $is_expired || $is_used_up) ? 'disabled' : '';
                ?>
                    <tr>
                        <td>
                            <?php 
                            $image_path = htmlspecialchars($voucher['image_path'] ?? '');
                            if (!empty($image_path) && file_exists($image_path)): ?>
                                <img src="/<?= $image_path ?>" alt="Voucher Pic" 
                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <i class="bi bi-gift-fill text-muted" style="font-size: 24px;"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($voucher['code']) ?></strong>
                        </td>
                        <td>
                            <?= format_voucher_value($voucher['type'], $voucher['value']) ?>
                            <br><small class="text-muted">(<?= ucfirst($voucher['type']) ?>)</small>
                        </td>
                        <td>
                            Rp <?= number_format($voucher['min_purchase'], 0, ',', '.') ?>
                        </td>
                        <td>
                            <?= $voucher['usage_count'] ?> / <?= $voucher['max_usage'] ?>
                            <?php if ($remaining_usage <= 5 && $remaining_usage > 0): ?>
                                <span class="badge bg-warning text-dark ms-1">Hampir Habis</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= (new DateTime($voucher['valid_until']))->format('d M Y H:i') ?>
                        </td>
                        <td>
                            <?= $status_badge ?>
                        </td>
                        <td>
                            <a href="/dashboard?p=voucher_edit&id=<?= $voucher['id'] ?>" 
                               class="btn btn-warning btn-sm <?= $is_disabled ?>"
                               <?= $is_disabled ?>>
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <?php if ($voucher['is_active'] == 1 && !$is_expired && !$is_used_up): ?>
                                <button type="button" 
                                        class="btn btn-danger btn-sm mt-1 mt-md-0" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deactivateVoucherModal" 
                                        data-voucher-id="<?= $voucher['id'] ?>"
                                        data-voucher-code="<?= htmlspecialchars($voucher['code']) ?>">
                                    <i class="bi bi-x-circle"></i> Nonaktifkan
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean(); // Ambil dan bersihkan buffer
}
?>

<script>
// ... (JavaScript modal di sini jika tidak di-*include*)
</script>