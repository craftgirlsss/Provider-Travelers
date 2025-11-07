<?php
// File: generate_invoice.php (LOKASI: HARUS DI DALAM FOLDER PUBLIC/)
// TUGAS: Mengambil data booking, merender HTML invoice, dan mengubahnya menjadi PDF.

// 1. Inisialisasi Autoload Composer
// PATH KOREKSI: Naik satu level karena file ini ada di public/
require 'vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// =========================================================================
// 1. VALIDASI DAN KONEKSI DATABASE
// =========================================================================
// ASUMSI: Ganti dengan logic koneksi DB Anda yang sebenarnya.
$db_host = 'localhost'; // Ganti
$db_user = 'sql_api_traveler';      // Ganti
$db_pass = 'a7f136533cbbf8';  // Ganti
$db_name = 'sql_api_traveler'; // Ganti
$db_port = '2323';    

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    // Fatal error di baris ini jika kredensial salah
    die("Koneksi database gagal: " . $conn->connect_error);
}

$booking_id = (int)($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    http_response_code(400);
    die("Akses ditolak: ID Pemesanan tidak valid.");
}

$platform_name = 'VLYTRIP';

try {
    // Query untuk mengambil data lengkap (Booking, Trip, Client, dan Provider)
    $stmt = $conn->prepare("
        SELECT 
            b.*, b.invoice_number, b.amount_paid, b.discount_amount, b.uuid AS booking_uuid,
            t.title AS trip_title, t.duration AS trip_duration,
            u.name AS client_name, u.email AS client_email, u.phone AS client_phone,
            p.company_name AS provider_name,   -- KOREKSI: Menggunakan company_name
            p.address AS provider_address,       -- KOREKSI: Menggunakan address
            p.phone_number AS provider_phone     -- KOREKSI: Menggunakan phone_number
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN users u ON b.user_id = u.id
        JOIN providers p ON t.provider_id = p.id 
        WHERE b.id = ? AND b.status = 'paid'
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $data = $result->fetch_assoc();
    } else {
        http_response_code(404);
        die("Error: Pemesanan tidak ditemukan atau status belum PAID. Invoice hanya diterbitkan setelah lunas.");
    }
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    die("Gagal memuat data: " . $e->getMessage());
}

if (empty($data['invoice_number'])) {
     http_response_code(400);
     die("Error: Pemesanan ini belum memiliki Nomor Invoice.");
}

// PERHITUNGAN HARGA FINAL UNTUK INVOICE
$price_after_discount_per_person = $data['total_price'] - $data['discount_amount'];
$actual_total_price = $data['num_of_people'] * $price_after_discount_per_person;

// FORMAT UUID BOOKING (8 digit, Uppercase)
$short_booking_id = strtoupper(substr($data['booking_uuid'], 0, 8));

// =========================================================================
// 2. GENERATE HTML INVOICE
// =========================================================================

// PATH LOGO: Relatif dari file generate_invoice.php (di root project)
$logo_url = 'https://provider-travelers.karyadeveloperindonesia.com/assets/vly.png'; 
// Jika menggunakan URL absolut, gunakan: $logo_url = 'https://domainanda.com/assets/vly.png';

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice ' . htmlspecialchars($data['invoice_number']) . '</title>
    <style>
        /* Gaya dasar untuk rendering Dompdf */
        body { font-family: sans-serif; font-size: 12px; margin: 0; padding: 0; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); font-size: 14px; line-height: 24px; color: #555; }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .invoice-box table td { padding: 5px; vertical-align: top; }
        .invoice-box table tr td:nth-child(2) { text-align: right; }
        .invoice-box table tr.top table td { padding-bottom: 20px; }
        .invoice-box table tr.top table td.title { font-size: 30px; line-height: 30px; color: #333; }
        .invoice-box table tr.information table td { padding-bottom: 20px; }
        .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
        .invoice-box table tr.item td{ border-bottom: 1px solid #eee; }
        .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
        .footer { text-align: center; margin-top: 50px; font-size: 10px; color: #aaa; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title" style="display: flex; align-items: center;">
                                <img src="' . $logo_url . '" style="width: 200px; margin-right: 10px;">
                                <h1>INVOICE</h1>
                            </td>
                            <td>
                                Invoice #: <b>' . htmlspecialchars($data['invoice_number']) . '</b><br>
                                Diterbitkan: ' . date('d M Y', strtotime($data['payment_confirmation_at'] ?? $data['created_at'])) . '<br>
                                ID Pemesanan: <b>' . $short_booking_id . '</b>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                <b>Dari (Penyedia Jasa):</b><br>
                                <b>' . htmlspecialchars($data['provider_name']) . '</b><br>
                                ' . htmlspecialchars($data['provider_address']) . '<br>
                                Telp: ' . htmlspecialchars($data['provider_phone']) . '
                            </td>
                            <td>
                                <b>Kepada (Customer):</b><br>
                                <b>' . htmlspecialchars($data['client_name']) . '</b><br>
                                ' . htmlspecialchars($data['client_email']) . '<br>
                                Telp: ' . htmlspecialchars($data['client_phone']) . '
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="heading">
                <td>Deskripsi</td>
                <td>Total</td>
            </tr>
            
            <tr class="item">
                <td>
                    <b>Trip: ' . htmlspecialchars($data['trip_title']) . '</b><br>
                    Harga Satuan: Rp ' . number_format($price_after_discount_per_person, 0, ',', '.') . '<br>
                    Jumlah Peserta: ' . (int)$data['num_of_people'] . ' orang<br>
                    Durasi: ' . htmlspecialchars($data['trip_duration']) . '
                </td>
                <td>Rp ' . number_format($actual_total_price, 0, ',', '.') . '</td>
            </tr>
            
            <tr class="total">
                <td></td>
                <td>Total Dibayar: Rp ' . number_format($data['amount_paid'], 0, ',', '.') . '</td>
            </tr>
        </table>

        <div class="footer">
            <p>Invoice ini adalah bukti pembayaran resmi untuk pemesanan trip Anda. Terima kasih telah memilih layanan kami.</p>
            <p>Status: LUNAS | Tanggal Pembayaran Dikonfirmasi: ' . date('d F Y H:i', strtotime($data['payment_confirmation_at'] ?? $data['created_at'])) . '</p>
        </div>
    </div>
</body>
</html>
';

// =========================================================================
// 3. RENDER PDF MENGGUNAKAN DOMPDF
// =========================================================================

$options = new Options();
$options->set('defaultFont', 'sans-serif');
// Perlu disetel ke TRUE agar Dompdf bisa memuat gambar logo
$options->set('isRemoteEnabled', TRUE); 
// Perlu disetel path dasar jika isRemoteEnabled TRUE, agar Dompdf tahu dari mana mulai mencari assets/vly.png
// Mengasumsikan file diakses melalui URL:
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/"; 
$options->set('base_path', $base_url);


$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$file_name = "INVOICE-" . str_replace('/', '-', $data['invoice_number']) . ".pdf";
$dompdf->stream($file_name, array("Attachment" => true));

$conn->close();

exit();
?>