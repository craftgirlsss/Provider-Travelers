<?php
// File: pages/dashboard/reports.php
// Asumsi: Di-include melalui dashboard.php. $conn (koneksi DB) dan 
//         $actual_provider_id (dari SESSION) sudah tersedia.

// =========================================================================
// KONSTANTA DAN FUNGSI PEMBANTU
// =========================================================================

global $conn, $user_id_from_session, $actual_provider_id; 

// 1. Sertakan file helper
require_once __DIR__ . '/../../utils/check_provider_verification.php';

// 2. Jalankan Fungsi Validasi
check_provider_verification($conn, $actual_provider_id, "Laporan Keuangan");

// DEFINE KOMISI (HARUS SAMA DENGAN export_report.php)
// Ganti 0.10 dengan persentase komisi yang sebenarnya (misal: 0.08 untuk 8%)
if (!defined('PLATFORM_COMMISSION_RATE')) {
    define('PLATFORM_COMMISSION_RATE', 0.10); 
}

/**
 * Fungsi pembantu untuk format mata uang
 */
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// =========================================================================
// LOGIC PENGAMBILAN DATA
// =========================================================================

// 1. Ambil Bulan dan Tahun yang dipilih (Default ke bulan dan tahun saat ini)
$current_month = date('m');
$current_year = date('Y');

$selected_month = (int)($_GET['month'] ?? $current_month);
$selected_year = (int)($_GET['year'] ?? $current_year);

// Validasi input
if ($selected_month < 1 || $selected_month > 12) $selected_month = $current_month;
if ($selected_year < 2000 || $selected_year > date('Y') + 1) $selected_year = $current_year;

// Tentukan rentang tanggal laporan (Berdasarkan tanggal lunas/paid_at)
$start_date = date("Y-m-d 00:00:00", mktime(0, 0, 0, $selected_month, 1, $selected_year));
$end_date = date("Y-m-d 23:59:59", mktime(0, 0, 0, $selected_month + 1, 0, $selected_year));

$report_data = [];
$total_gross_sales = 0;
$total_commission = 0;
$total_net_income = 0;
$error = null;


try {
    // Query untuk mengambil semua transaksi yang statusnya 'paid' dalam rentang waktu yang dipilih
    $stmt = $conn->prepare("
        SELECT 
            b.id AS booking_id,
            b.invoice_number,
            b.total_price,
            t.title AS trip_title,
            u.name AS client_name,
            p.paid_at
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN users u ON b.user_id = u.id
        JOIN payments p ON b.id = p.booking_id
        WHERE t.provider_id = ? 
          AND p.status = 'paid'
          AND p.paid_at BETWEEN ? AND ?
        ORDER BY p.paid_at DESC
    ");
    $stmt->bind_param("iss", $actual_provider_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $gross_amount = $row['total_price'];
        $commission_fee = $gross_amount * PLATFORM_COMMISSION_RATE;
        $net_amount = $gross_amount - $commission_fee;
        
        $total_gross_sales += $gross_amount;
        $total_commission += $commission_fee;
        $total_net_income += $net_amount;

        $row['commission_fee'] = $commission_fee;
        $row['net_amount'] = $net_amount;
        
        $report_data[] = $row;
    }

    $stmt->close();

} catch (Exception $e) {
    $error = "Gagal memuat laporan transaksi: " . $e->getMessage();
}

// Array Bulan untuk Dropdown
$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$report_title = $month_names[$selected_month] . " " . $selected_year;
?>

<h1 class="mb-4 text-primary fw-bold">Laporan Keuangan</h1>
<p class="text-muted">Pantau ringkasan dan detail transaksi yang telah <b>Lunas (PAID)</b> di platform ini.</p>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Bagian 1: Pemilihan Periode dan Export -->
<div class="card shadow-sm mb-5">
    <div class="card-body">
        <form method="GET" action="/dashboard" class="row g-3 align-items-center justify-content-between">
            <input type="hidden" name="p" value="reports">
            
            <div class="col-auto d-flex align-items-center">
                <label for="month" class="col-form-label me-2 fw-semibold">Periode:</label>
                <select id="month" name="month" class="form-select me-2" style="width: auto;">
                    <?php 
                    for ($m = 1; $m <= 12; $m++): 
                        $selected = ($m == $selected_month) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $m; ?>" <?php echo $selected; ?>>
                            <?php echo $month_names[$m]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                
                <select id="year" name="year" class="form-select me-3" style="width: auto;">
                    <?php 
                    $current_y = date('Y');
                    for ($y = $current_y; $y >= $current_y - 3; $y--): 
                        $selected = ($y == $selected_year) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $y; ?>" <?php echo $selected; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
            </div>
            
            <div class="col-auto">
                 <button type="button" class="btn btn-success" id="exportCsvButton">
                    <i class="bi bi-download me-1"></i> Export Data CSV
                </button>
            </div>
        </form>
    </div>
</div>

<h3 class="mb-3 text-secondary">Ringkasan untuk <?php echo $report_title; ?></h3>

<?php if (empty($report_data)): ?>
    <div class="alert alert-info shadow-sm">
        <i class="bi bi-info-circle me-2"></i> Tidak ada transaksi yang lunas pada bulan <?php echo $report_title; ?>.
    </div>
<?php else: ?>

<!-- Bagian 2: Kartu Ringkasan (Key Performance Indicators) -->
<div class="row mb-5">
    
    <!-- Total Penjualan Kotor -->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-white" style="border-left: 5px solid #0d6efd;">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-bar-chart-fill text-primary me-3" style="font-size: 2rem;"></i>
                    <div>
                        <p class="text-uppercase text-muted mb-1 small fw-bold">Total Omzet Kotor</p>
                        <h4 class="mb-0 text-primary fw-bolder"><?php echo format_rupiah($total_gross_sales); ?></h4>
                        <small class="text-muted"><?php echo count($report_data); ?> Transaksi Lunas</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Komisi -->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-white" style="border-left: 5px solid #dc3545;">
            <div class="card-body">
                 <div class="d-flex align-items-center">
                    <i class="bi bi-cash-stack text-danger me-3" style="font-size: 2rem;"></i>
                    <div>
                        <p class="text-uppercase text-muted mb-1 small fw-bold">Biaya Komisi (<?php echo (PLATFORM_COMMISSION_RATE * 100); ?>%)</p>
                        <h4 class="mb-0 text-danger fw-bolder">- <?php echo format_rupiah($total_commission); ?></h4>
                        <small class="text-muted">Total biaya platform</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pendapatan Bersih -->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-white" style="border-left: 5px solid #198754;">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-wallet-fill text-success me-3" style="font-size: 2rem;"></i>
                    <div>
                        <p class="text-uppercase text-muted mb-1 small fw-bold">Pendapatan Bersih Anda</p>
                        <h4 class="mb-0 text-success fw-bolder"><?php echo format_rupiah($total_net_income); ?></h4>
                        <small class="text-muted">Jumlah yang seharusnya Anda terima</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bagian 3: Tabel Detail Transaksi -->
<div class="card shadow-sm">
    <div class="card-header bg-light fw-bold text-secondary">
        Detail Transaksi Lunas Bulan <?php echo $report_title; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">#ID</th>
                        <th>Invoice</th>
                        <th>Trip</th>
                        <th>Tanggal Lunas</th>
                        <th class="text-end">Penjualan Kotor</th>
                        <th class="text-end">Komisi</th>
                        <th class="text-end">Pendapatan Bersih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $data): ?>
                    <tr>
                        <td class="text-center text-muted"><?php echo htmlspecialchars($data['booking_id']); ?></td>
                        <td class="fw-semibold text-primary"><?php echo htmlspecialchars($data['invoice_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($data['trip_title']); ?></td>
                        <td class="text-muted"><?php echo date('d M Y H:i', strtotime($data['paid_at'])); ?></td>
                        <td class="text-end text-primary fw-bold"><?php echo format_rupiah($data['total_price']); ?></td>
                        <td class="text-end text-danger"><?php echo format_rupiah($data['commission_fee']); ?></td>
                        <td class="text-end text-success fw-bold"><?php echo format_rupiah($data['net_amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Script untuk Export CSV -->
<script>
document.getElementById('exportCsvButton').addEventListener('click', function() {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    // Panggil script export. TIDAK PERLU mengirim provider_id, diambil dari SESSION di backend.
    window.location.href = '/process/export_report.php?month=' + month + '&year=' + year;
});
</script>
