<?php
// File: generate_invoice.php
// TUGAS: Mengambil data booking, merender HTML invoice, dan mengubahnya menjadi PDF.

// 1. Inisialisasi Autoload Composer
// Pastikan path ini benar sesuai lokasi file vendor/autoload.php Anda.
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// =========================================================================
// 1. VALIDASI DAN KONEKSI DATABASE
// =========================================================================

// ASUMSI: File ini diakses langsung dan perlu inisialisasi koneksi DB.
// Ganti dengan logic koneksi DB Anda yang sebenarnya.
// --- START: Contoh Inisialisasi Koneksi ---
// global $conn; // Jika Anda bisa mengakses global scope
// Jika tidak, inisialisasi koneksi MySQLi baru:
$db_host = 'localhost'; // Ganti
$db_user = 'root';      // Ganti
$db_pass = 'password';  // Ganti
$db_name = 'nama_database_anda'; // Ganti

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
// --- END: Contoh Inisialisasi Koneksi ---

$booking_id = (int)($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    http_response_code(400);
    die("Akses ditolak: ID Pemesanan tidak valid.");
}

// Data Provider (Anda harus mengambil ini dari DB berdasarkan provider_id dari booking)
// Untuk contoh, kita gunakan data statis
$provider_info = [
    'name' => 'Nama Provider Perjalanan Anda',
    'address' => 'Jl. Contoh No. 123, Kota Anda',
    'phone' => '+62 812 345 678',
    'email' => 'finance@yourtravel.com',
    'logo_path' => '/assets/images/logo.png' // Pastikan path ini absolut atau relatif yang benar
];

try {
    // Query untuk mengambil data lengkap (Booking, Trip, Client)
    $stmt = $conn->prepare("
        SELECT 
            b.*, b.invoice_number, 
            t.title AS trip_title, t.duration AS trip_duration,
            u.name AS client_name, u.email AS client_email, u.phone AS client_phone
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN users u ON b.user_id = u.id
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

// =========================================================================
// 2. GENERATE HTML INVOICE
// =========================================================================

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
                            <td class="title">
                                <h2>INVOICE</h2>
                            </td>
                            <td>
                                Invoice #: <b>' . htmlspecialchars($data['invoice_number']) . '</b><br>
                                Diterbitkan: ' . date('d M Y', strtotime($data['paid_at'] ?? $data['created_at'])) . '<br>
                                ID Pemesanan: #' . htmlspecialchars($data['id']) . '
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
                                **Dari (Penyedia Jasa):**<br>
                                <b>' . htmlspecialchars($provider_info['name']) . '</b><br>
                                ' . htmlspecialchars($provider_info['address']) . '<br>
                                Telp: ' . htmlspecialchars($provider_info['phone']) . '
                            </td>
                            <td>
                                **Kepada (Pelanggan):**<br>
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
                    Peserta: ' . (int)$data['num_of_people'] . ' orang<br>
                    Durasi: ' . htmlspecialchars($data['trip_duration']) . '
                </td>
                <td>Rp ' . number_format($data['total_price'], 0, ',', '.') . '</td>
            </tr>
            
            <tr class="total">
                <td></td>
                <td>Total Dibayar: Rp ' . number_format($data['total_price'], 0, ',', '.') . '</td>
            </tr>
        </table>

        <div class="footer">
            <p>Invoice ini adalah bukti pembayaran resmi untuk pemesanan trip Anda. Terima kasih telah memilih layanan kami.</p>
            <p>Status: LUNAS | Tanggal Pembayaran Dikonfirmasi: ' . date('d F Y H:i', strtotime($data['paid_at'] ?? $data['created_at'])) . '</p>
        </div>
    </div>
</body>
</html>
';

// =========================================================================
// 3. RENDER PDF MENGGUNAKAN DOMPDF
// =========================================================================

// Konfigurasi Dompdf (Opsional, tapi disarankan)
$options = new Options();
$options->set('defaultFont', 'sans-serif');
$options->set('isRemoteEnabled', TRUE); // Izinkan loading asset eksternal (logo, dll.)

$dompdf = new Dompdf($options);

// Load HTML ke Dompdf
$dompdf->loadHtml($html);

// Set ukuran kertas dan orientasi
$dompdf->setPaper('A4', 'portrait');

// Render HTML menjadi PDF
$dompdf->render();

// Nama file yang akan diunduh
$file_name = "INVOICE-" . str_replace('/', '-', $data['invoice_number']) . ".pdf";

// Output the generated PDF (Attachment = true akan memaksa unduh)
$dompdf->stream($file_name, array("Attachment" => true));

// Tutup koneksi DB setelah selesai
$conn->close();

exit();
?>