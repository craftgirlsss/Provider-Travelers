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

// Lakukan pengecekan minimum dan tampilkan pesan kesalahan sistem yang lebih spesifik
if (!isset($conn) || !is_object($conn)) {
    $error = "Kesalahan Fatal: Koneksi database (\$conn) hilang dari global scope.";
} elseif (!$provider_id) {
    // Jika $actual_provider_id hilang (padahal dashboard.php sudah melakukan pengecekan)
    $error = "Kesalahan Otorisasi: ID Provider tidak ditemukan. Mohon login ulang.";
}


// Ambil pesan dari session (setelah create/update)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);


// =======================================================================
// Ambil Data Voucher
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
                    is_active
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
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 15%;">Kode Voucher</th>
                        <th style="width: 15%;">Tipe & Nilai</th>
                        <th style="width: 15%;">Min. Pembelian</th>
                        <th style="width: 15%;">Batas Penggunaan</th>
                        <th style="width: 15%;">Valid Hingga</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 15%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($voucher_data)): 
                        foreach ($voucher_data as $voucher):
                            $remaining_usage = $voucher['max_usage'] - $voucher['usage_count'];
                            $is_expired = strtotime($voucher['valid_until']) < time();
                            
                            $status_badge = '<span class="badge bg-success">Aktif</span>';
                            $action_button = '<a href="/dashboard?p=voucher_edit&id=' . $voucher['id'] . '" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Edit</a>';
                            $is_disabled = '';
                            
                            if ($voucher['is_active'] == 0) {
                                $status_badge = '<span class="badge bg-danger">Dinonaktifkan</span>';
                                $action_button = '<button class="btn btn-secondary btn-sm" disabled><i class="bi bi-pencil"></i> Edit</button>';
                                $is_disabled = 'disabled';
                            } elseif ($is_expired) {
                                $status_badge = '<span class="badge bg-secondary">Expired</span>';
                                $action_button = '<button class="btn btn-secondary btn-sm" disabled><i class="bi bi-pencil"></i> Edit</button>';
                                $is_disabled = 'disabled';
                            } elseif ($remaining_usage <= 0) {
                                $status_badge = '<span class="badge bg-danger">Habis</span>';
                                $action_button = '<button class="btn btn-secondary btn-sm" disabled><i class="bi bi-pencil"></i> Edit</button>';
                                $is_disabled = 'disabled';
                            }
                    ?>
                        <tr>
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
                                <?php elseif ($remaining_usage <= 0): ?>
                                    <span class="badge bg-danger ms-1">Habis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= (new DateTime($voucher['valid_until']))->format('d M Y H:i') ?>
                            </td>
                            <td>
                                <?= $status_badge ?>
                            </td>
                            <td>
                                <?= $action_button ?>
                                <button type="button" 
                                        class="btn btn-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deactivateVoucherModal" 
                                        data-voucher-id="<?= $voucher['id'] ?>"
                                        data-voucher-code="<?= htmlspecialchars($voucher['code']) ?>"
                                        <?= $is_disabled ?>>
                                    <i class="bi bi-x-circle"></i> Nonaktifkan
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Anda belum membuat voucher diskon apa pun.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Pastikan modal di-*include* atau diletakkan di bagian bawah *dashboard.php*
// include_once __DIR__ . '/../../includes/modals/deactivate_voucher_modal.php'; 
?>
<script>
// ... (JavaScript modal di sini jika tidak di-*include*)
</script>