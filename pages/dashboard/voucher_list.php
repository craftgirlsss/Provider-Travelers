<?php
// File: pages/dashboard/voucher_list.php

// Panggil variabel yang sudah disiapkan oleh dashboard.php
global $conn, $actual_provider_id; 

require_once __DIR__ . '/../../utils/check_provider_verification.php';

check_provider_verification($conn, $actual_provider_id, "Daftar Voucher");

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
                    is_active,
                    image_path 
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

/**
 * Fungsi Pembantu untuk deskripsi singkat nilai voucher
 */
function get_voucher_description($type, $value) {
    if ($type === 'percentage') {
        return "Diskon " . number_format($value, 0) . "% untuk total transaksi.";
    } else {
        return "Hemat Rp " . number_format($value, 0, ',', '.') . " pada setiap transaksi.";
    }
}

// =======================================================================
// Logika Pengelompokan Data ke dalam Tabs
// =======================================================================
$grouped_vouchers = [
    'active' => [],
    'expired' => [],
    'inactive' => [] 
];

foreach ($voucher_data as $voucher) {
    $is_expired = strtotime($voucher['valid_until']) < time();
    $remaining_usage = $voucher['max_usage'] - $voucher['usage_count'];
    $is_used_up = $remaining_usage <= 0;
    
    // Tentukan status grouping
    if ($voucher['is_active'] == 1 && !$is_expired && !$is_used_up) {
        $grouped_vouchers['active'][] = $voucher;
    } elseif ($is_expired) {
        $grouped_vouchers['expired'][] = $voucher;
    } else {
        $grouped_vouchers['inactive'][] = $voucher;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-success">Daftar Voucher</h3>
    <a href="/dashboard?p=voucher_create" class="btn btn-success">
        <i class="bi bi-truck me-2"></i> Tambah Voucher Baru
    </a>
</div>
<p class="text-muted mb-4">Buat, kelola, dan lacak penggunaan kupon diskon Anda di sini.</p>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-4">
        
        <ul class="nav nav-pills nav-fill mb-4" id="voucherTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true">
                    Aktif & Siap Pakai (<?= count($grouped_vouchers['active']) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button" role="tab" aria-controls="inactive" aria-selected="false">
                    Nonaktif/Habis (<?= count($grouped_vouchers['inactive']) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="expired-tab" data-bs-toggle="tab" data-bs-target="#expired" type="button" role="tab" aria-controls="expired" aria-selected="false">
                    Kedaluwarsa (<?= count($grouped_vouchers['expired']) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="voucherTabsContent">
            
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <?php echo generateVoucherCards($grouped_vouchers['active'], $is_editable = true); ?>
            </div>
            
            <div class="tab-pane fade" id="inactive" role="tabpanel" aria-labelledby="inactive-tab">
                 <?php echo generateVoucherCards($grouped_vouchers['inactive'], $is_editable = false); ?>
            </div>

            <div class="tab-pane fade" id="expired" role="tabpanel" aria-labelledby="expired-tab">
                <?php echo generateVoucherCards($grouped_vouchers['expired'], $is_editable = false); ?>
            </div>

        </div> 
    </div>
</div>

<div class="modal fade" id="deactivateVoucherModal" tabindex="-1" aria-labelledby="deactivateVoucherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deactivateVoucherModalLabel"><i class="bi bi-x-circle me-2"></i> Konfirmasi Nonaktifkan Voucher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin **menonaktifkan** voucher "<span id="voucherCodePlaceholder" class="fw-bold"></span>"? 
                Voucher ini tidak akan bisa digunakan lagi oleh pengguna.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <form id="deactivateVoucherForm" action="/process/voucher_process" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="deactivate_voucher">
                    <input type="hidden" name="voucher_id" id="modalVoucherId">
                    <button type="submit" class="btn btn-danger">Ya, Nonaktifkan</button>
                </form>
            </div>
        </div>
    </div>
</div>


<?php 
// ----------------------------------------------------------------------
// FUNGSI BARU UNTUK MERENDER CARD VOUCHER DENGAN TAMPILAN KEKINIAN
// ----------------------------------------------------------------------

/**
 * Merender grid Card Voucher yang kekinian.
 * @param array $vouchers Data voucher yang akan ditampilkan.
 * @param bool $is_editable Apakah tombol Edit harus aktif.
 * @return string HTML dari grid voucher.
 */
function generateVoucherCards(array $vouchers, $is_editable = true) {
    
    if (empty($vouchers)) {
        return '<div class="alert alert-secondary text-center shadow-sm m-3"><i class="bi bi-tag me-2"></i> Tidak ada voucher dalam kategori ini.</div>';
    }
    
    ob_start(); 
    ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($vouchers as $voucher):
            $remaining_usage = $voucher['max_usage'] - $voucher['usage_count'];
            $is_expired = strtotime($voucher['valid_until']) < time();
            $is_used_up = $remaining_usage <= 0;
            
            // Tentukan status untuk style/opacity
            $status_class = 'active';
            if ($is_expired) {
                $status_class = 'expired';
            } elseif ($voucher['is_active'] == 0 || $is_used_up) {
                $status_class = 'inactive';
            }
            
            $text_color = ($status_class === 'active') ? 'text-dark' : 'text-muted';
            $edit_disabled = ($status_class !== 'active') ? 'disabled' : '';

            // Tentukan deskripsi dan batas
            $value_text = format_voucher_value($voucher['type'], $voucher['value']);
            $description = get_voucher_description($voucher['type'], $voucher['value']);
        ?>
        <div class="col">
            <div class="voucher-card-modern shadow-lg rounded-3 overflow-hidden position-relative 
                 <?php echo $status_class; ?>" 
                 style="opacity: <?php echo ($status_class === 'active' ? '1' : '0.7'); ?>">
                
                <div class="d-flex">
                    
                    <div class="col-3 p-0 voucher-left-panel text-center d-flex flex-column justify-content-center align-items-center">
                        <span class="vertical-text text-white fw-bold fs-6">
                            DISKON
                        </span>
                    </div>

                    <div class="col-8 p-3 d-flex flex-column justify-content-between position-relative">
                        
                        <div class="voucher-separator"></div>

                        <div class="mb-3"> <p class="small text-muted mb-0 fw-semibold">
                                <?php echo $value_text; ?> *
                            </p>

                            <h4 class="fw-bolder mb-2 text-uppercase <?php echo $text_color; ?>">
                                <?= htmlspecialchars($voucher['code']) ?>
                            </h4>
                            
                            <p class="small text-muted mb-2">
                                <?= htmlspecialchars($description) ?>
                            </p>
                            
                            <p class="small text-primary mb-2">
                                <i class="bi bi-tags-fill me-1"></i> Sisa: <?= max(0, $remaining_usage) ?> / <?= $voucher['max_usage'] ?>
                            </p>
                            <p class="small text-muted mb-0">* Min. Rp <?= number_format($voucher['min_purchase'], 0, ',', '.') ?> </p>
                        </div>
                        
                        <div class="d-flex flex-column pt-2 mt-auto border-top">
                            <span class="text-muted small mb-2">
                                Valid Hingga: <?= (new DateTime($voucher['valid_until']))->format('d/m/Y H:i') ?>
                            </span>

                            <div class="d-flex gap-2 align-items-center">
                                <a href="/dashboard?p=voucher_edit&id=<?= $voucher['id'] ?>" 
                                   class="btn btn-sm btn-primary py-2 px-3 fw-bold flex-fill <?= $edit_disabled ?>"
                                   <?= $edit_disabled ? 'title="Voucher tidak dapat diubah saat ini"' : '' ?>>
                                    <i class="bi bi-eye-fill me-1"></i> Lihat Detail
                                </a>

                                <?php if ($status_class === 'active'): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deactivateVoucherModal" 
                                            data-voucher-id="<?= $voucher['id'] ?>"
                                            data-voucher-code="<?= htmlspecialchars($voucher['code']) ?>"
                                            title="Nonaktifkan Voucher">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean(); 
}
?>

<script>
// Logic Modal Nonaktifkan Voucher (Sama seperti sebelumnya)
document.addEventListener('DOMContentLoaded', function() {
    var deactivateVoucherModal = document.getElementById('deactivateVoucherModal');
    
    if (deactivateVoucherModal) {
        deactivateVoucherModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; 
            var voucherId = button.getAttribute('data-voucher-id');
            var voucherCode = button.getAttribute('data-voucher-code');

            var modalVoucherId = document.getElementById('modalVoucherId');
            if (modalVoucherId) {
                modalVoucherId.value = voucherId;
            }

            var voucherCodePlaceholder = document.getElementById('voucherCodePlaceholder');
            if (voucherCodePlaceholder) {
                voucherCodePlaceholder.textContent = voucherCode;
            }
        });
    }
});
</script>

<style>
/* CSS Kustom untuk Tampilan Voucher Card Modern */
.voucher-card-modern {
    /* Hapus Batasan Tinggi agar Card Fleksibel */
    /* height: 250px; <-- DIHAPUS */
    box-sizing: border-box;
    transition: all 0.3s ease;
    min-height: 200px; /* Tambahkan tinggi minimal */
}

.voucher-card-modern:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
}

/* Pastikan row di dalamnya menggunakan d-flex */
.voucher-card-modern > .d-flex {
    /* flex-grow: 1; */ /* Opsional, tergantung parent */
    width: 100%;
}

.voucher-left-panel {
    background-color: #5b28b7; /* Warna ungu gelap */
    border-top-left-radius: 12px;
    border-bottom-left-radius: 12px;
}

.voucher-card-modern.expired .voucher-left-panel,
.voucher-card-modern.inactive .voucher-left-panel {
    background-color: #6c757d; /* Warna abu-abu untuk nonaktif/expired */
}

/* Teks Vertikal "DISKON" */
.vertical-text {
    transform: rotate(-90deg);
    white-space: nowrap;
    letter-spacing: 2px;
    font-size: 0.9rem !important;
}

/* Garis Pemisah Putus-Putus (Mirip Robekan) */
.voucher-separator {
    position: absolute;
    top: 0;
    left: -1px; 
    bottom: 0;
    width: 2px;
    background: repeating-linear-gradient(
        to bottom,
        #fff, 
        #fff 5px,
        transparent 5px,
        transparent 10px
    );
    border-left: 2px dashed #ffffff; 
}
/* Menghapus background-color default dari col-8 agar garis putus-putus terlihat */
.voucher-card-modern .col-8 {
    background-color: #ffffff;
}

/* Pastikan konten col-8 mengambil seluruh tinggi vertikal */
.voucher-card-modern .col-8 {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
</style>