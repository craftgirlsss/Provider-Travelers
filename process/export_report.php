<?php
// File: /process/export_report.php
// Digunakan untuk mengekspor data laporan transaksi ke format CSV.

session_start();
// PAHAMI: Ganti path ini sesuai struktur folder Anda
require_once __DIR__ . '/../config/db_config.php'; // Asumsi ini mendefinisikan $conn

// =========================================================================
// OTORISASI & INISIALISASI
// =========================================================================

// Cek Otorisasi - Ambil ID Provider dari Session
$actual_provider_id = $_SESSION['actual_provider_id'] ?? null; 
$user_role = $_SESSION['user_role'] ?? null;

if (!$actual_provider_id || $user_role !== 'provider') {
    http_response_code(403);
    die("Akses Ditolak: Anda tidak memiliki otorisasi untuk mengakses laporan ini.");
}

// DEFINE KOMISI (HARUS SAMA DENGAN reports.php)
if (!defined('PLATFORM_COMMISSION_RATE')) {
    define('PLATFORM_COMMISSION_RATE', 0.10); // Contoh: 10%
}

// Ambil parameter bulan dan tahun dari URL
$selected_month = (int)($_GET['month'] ?? date('m'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

// Validasi input
if ($selected_month < 1 || $selected_month > 12) {
    die("Bulan tidak valid.");
}
if (!isset($conn)) {
    die("Kesalahan Koneksi Database.");
}

// Tentukan rentang tanggal laporan
$start_date = date("Y-m-d 00:00:00", mktime(0, 0, 0, $selected_month, 1, $selected_year));
$end_date = date("Y-m-d 23:59:59", mktime(0, 0, 0, $selected_month + 1, 0, $selected_year));

$month_name = date('M', mktime(0, 0, 0, $selected_month, 10)); 

// =========================================================================
// EKSPOR CSV
// =========================================================================

// Header untuk memaksa download file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Laporan_Transaksi_Provider_' . $actual_provider_id . '_' . $selected_year . '_' . $month_name . '.csv');

// Buka output stream (mengarah ke browser)
$output = fopen('php://output', 'w');

// 1. Tulis Header Kolom
fputcsv($output, [
    'ID Booking',
    'Nomor Invoice',
    'Tanggal Lunas',
    'Nama Trip',
    'Nama Klien',
    'Penjualan Kotor (Rp)',
    'Persentase Komisi (%)',
    'Nominal Komisi (Rp)',
    'Pendapatan Bersih Provider (Rp)'
], ';'); // Menggunakan titik koma (;) sebagai delimiter

// 2. Query Data
try {
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
    
    // 3. Tulis Baris Data
    while ($row = $result->fetch_assoc()) {
        $gross_amount = (float)$row['total_price'];
        $commission_rate = PLATFORM_COMMISSION_RATE;
        $commission_fee = $gross_amount * $commission_rate;
        $net_amount = $gross_amount - $commission_fee;
        
        $data_row = [
            $row['booking_id'],
            $row['invoice_number'],
            date('Y-m-d H:i:s', strtotime($row['paid_at'])),
            $row['trip_title'],
            $row['client_name'],
            // Format angka untuk CSV
            number_format($gross_amount, 2, ',', ''), 
            $commission_rate * 100, 
            number_format($commission_fee, 2, ',', ''),
            number_format($net_amount, 2, ',', '')
        ];
        
        fputcsv($output, $data_row, ';');
    }

    $stmt->close();
    
} catch (Exception $e) {
    fputcsv($output, ["ERROR MEMUAT DATA: " . $e->getMessage()], ';');
}

fclose($output);
exit(); 
