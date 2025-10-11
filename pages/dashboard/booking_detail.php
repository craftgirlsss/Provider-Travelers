<?php
// File: pages/dashboard/booking_detail.php
// Asumsi: Di-include melalui dashboard.php. $conn dan $actual_provider_id tersedia.

$booking_id = (int)($_GET['id'] ?? 0);
$booking_data = null;
$error = null;

// =========================================================================
// FUNGSI PEMBANTU (MIMIC LOGIC)
// =========================================================================

/**
 * Fungsi dummy untuk generate nomor invoice unik.
 * Dalam sistem nyata, ini harus mengambil nomor urut terakhir dari DB
 * atau menggunakan sequence.
 */
function generateUniqueInvoiceNumber($conn) {
    // Implementasi sederhana: Ambil jumlah total bookings, lalu tambahkan 1
    // Dalam produksi, gunakan tabel sequence terpisah dan locking yang aman.
    $prefix = "INV/" . date("Y") . "/";
    
    $result = $conn->query("SELECT COUNT(id) AS total FROM bookings");
    $row = $result->fetch_assoc();
    $sequence = $row['total'] + 1;

    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}


// =========================================================================
// LOGIC KONFIRMASI PEMBAYARAN & INVOICE GENERATION
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'confirm_payment_and_invoice') {
    $booking_id_to_confirm = (int)($_POST['booking_id'] ?? 0);
    
    if ($booking_id_to_confirm === $booking_id) {
        try {
            // 1. Cek apakah booking ini benar-benar milik Provider yang login
            $stmt_check = $conn->prepare("SELECT status FROM bookings b JOIN trips t ON b.trip_id = t.id WHERE b.id = ? AND t.provider_id = ?");
            $stmt_check->bind_param("ii", $booking_id, $actual_provider_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $current_booking = $result_check->fetch_assoc();
            $stmt_check->close();

            if ($result_check->num_rows === 0) {
                throw new Exception("Akses ditolak atau pemesanan tidak ditemukan.");
            }
            if ($current_booking['status'] === 'paid' || $current_booking['status'] === 'completed') {
                throw new Exception("Pembayaran sudah dikonfirmasi sebelumnya.");
            }

            // 2. Generate Nomor Invoice Unik
            $new_invoice_number = generateUniqueInvoiceNumber($conn);
            $paid_at = date('Y-m-d H:i:s');
            
            // 3. Update status, tanggal lunas, dan simpan nomor invoice ke DB
            // Catatan: Asumsi kolom 'invoice_number' dan 'paid_at' sudah ada di tabel 'bookings'.
            $stmt_update = $conn->prepare("UPDATE bookings SET status = 'paid', invoice_number = ?, paid_at = ? WHERE id = ?");
            $stmt_update->bind_param("ssi", $new_invoice_number, $paid_at, $booking_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            // 4. Panggil skrip generator PDF (INI BAGIAN KRUSIAL)
            // Note: Kita akan menggunakan URL untuk menggenerate, lalu mengambil hasilnya (seperti file),
            // atau menggunakan include jika generate_invoice.php adalah fungsi yang mengembalikan data.
            // Untuk kesederhanaan, kita akan menggunakan include (asumsi generate_invoice.php mengembalikan path file PDF sementara).
            
            // Asumsi: Skrip generate_invoice.php ada di folder yang sama atau diakses via path relatif.
            // Dalam sistem nyata, ini sering dilakukan asinkron (job queue) atau via curl/API internal.
            // Untuk contoh ini, kita anggap script generate_invoice.php akan menggenerate dan menyimpan file sementara.
            
            // $pdf_path = include('generate_invoice.php'); 
            // Namun, untuk menghindari output HTML bercampur PDF, kita akan berikan tombol download segera
            // setelah konfirmasi. Logic generate PDF harus diakses melalui link atau include terisolasi.

            $_SESSION['dashboard_message'] = "Pembayaran berhasil dikonfirmasi. Status diubah menjadi PAID, dan Invoice **" . htmlspecialchars($new_invoice_number) . "** siap didownload/dikirim ke klien.";
            $_SESSION['dashboard_message_type'] = 'success';
            
        } catch (Exception $e) {
            $_SESSION['dashboard_message'] = "Gagal memproses konfirmasi: " . $e->getMessage();
            $_SESSION['dashboard_message_type'] = 'danger';
        }

        // Redirect untuk mencegah POST submission ulang
        header("Location: /dashboard?p=orders&id=" . $booking_id); // Redirect ke halaman booking list
        exit();
    }
}
// =========================================================================


if ($booking_id > 0) {
    try {
        // Ambil data booking, user, dan trip terkait, pastikan milik provider yang login
        // TAMBAHKAN kolom 'invoice_number'
        $stmt = $conn->prepare("
            SELECT 
                b.*, b.invoice_number, 
                t.title AS trip_title, 
                u.name AS client_name, u.email AS client_email 
            FROM bookings b
            JOIN trips t ON b.trip_id = t.id
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND t.provider_id = ?
        ");
        $stmt->bind_param("ii", $booking_id, $actual_provider_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $booking_data = $result->fetch_assoc();
        } else {
            $error = "Pemesanan tidak ditemukan atau bukan milik Anda.";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Gagal memuat detail pemesanan: " . $e->getMessage();
    }
} else {
    $error = "ID Pemesanan tidak valid.";
}

// Redirect jika error
if ($error) {
    $_SESSION['dashboard_message'] = $error;
    $_SESSION['dashboard_message_type'] = "danger";
    // Mengganti 'bookings' ke 'orders' agar sesuai dengan peta file Anda
    header("Location: /dashboard?p=orders"); 
    exit();
}

// Tampilkan pesan notifikasi dari session
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

?>

<h1 class="mb-4">Detail Pemesanan #<?php echo htmlspecialchars($booking_data['id']); ?></h1>
<p class="text-muted">Trip: <b><?php echo htmlspecialchars($booking_data['trip_title']); ?></b></p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Ringkasan Pemesanan</div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Client</th>
                        <td><?php echo htmlspecialchars($booking_data['client_name']); ?> (<?php echo htmlspecialchars($booking_data['client_email']); ?>)</td>
                    </tr>
                    <tr>
                        <th>Nomor Invoice</th>
                        <td>
                            <?php if (!empty($booking_data['invoice_number'])): ?>
                                <b><?php echo htmlspecialchars($booking_data['invoice_number']); ?></b>
                            <?php else: ?>
                                <span class="text-secondary">Belum Terbit</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Jumlah Peserta</th>
                        <td><?php echo (int)$booking_data['num_of_people']; ?> orang</td>
                    </tr>
                    <tr>
                        <th>Total Harga</th>
                        <td><b>Rp <?php echo number_format($booking_data['total_price'], 0, ',', '.'); ?></b></td>
                    </tr>
                    <tr>
                        <th>Status Pembayaran</th>
                        <td>
                            <?php 
                                $status = $booking_data['status'];
                                $badge_class = 'secondary';
                                if ($status === 'paid') $badge_class = 'success';
                                else if ($status === 'unpaid') $badge_class = 'warning text-dark';
                                else if ($status === 'cancelled') $badge_class = 'danger';
                                // Tambahkan status 'pending' untuk masa transisi upload bukti
                                else if ($status === 'pending') $badge_class = 'info text-dark';
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst(htmlspecialchars($status)); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Tanggal Pesan</th>
                        <td><?php echo date('d M Y H:i', strtotime($booking_data['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php 
        $has_proof = !empty(trim($booking_data['proof_of_payment_path'] ?? ''));
        
        // 1. Kondisi: UNPAID/PENDING dengan bukti transfer (BUTUH KONFIRMASI)
        if (($booking_data['status'] === 'unpaid' || $booking_data['status'] === 'pending') && $has_proof): ?>
            <div class="alert alert-warning">
                <b>Pembayaran Menunggu Konfirmasi!</b> Bukti transfer sudah diunggah.
            </div>
            
            <!-- FORM BARU UNTUK MEMICU LOGIC INVOICE -->
            <form method="POST" class="mb-4" onsubmit="return confirm('Yakin ingin MENGKONFIRMASI pembayaran ini? Status akan diubah menjadi PAID, Invoice akan diterbitkan, dan Client akan menerima notifikasi.');">
                <input type="hidden" name="action" value="confirm_payment_and_invoice">
                <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-check-circle me-2"></i> Konfirmasi Pembayaran & Terbitkan Invoice
                </button>
            </form>
            
        <?php 
        // 2. Kondisi: PAID (SUDAH LUNAS)
        elseif ($booking_data['status'] === 'paid'): ?>
            <div class="alert alert-success d-flex justify-content-between align-items-center">
                <div>
                    Pembayaran telah <b>LUNAS</b> dikonfirmasi pada <?php echo date('d M Y H:i', strtotime($booking_data['paid_at'] ?? $booking_data['created_at'])); ?>.
                </div>
                <!-- Tombol Download Invoice -->
                <a href="/generate_invoice.php?id=<?php echo $booking_data['id']; ?>" target="_blank" class="btn btn-sm btn-light">
                     <i class="bi bi-file-earmark-pdf me-1"></i> Download Invoice
                </a>
            </div>
            
        <?php 
        // 3. Kondisi: UNPAID tanpa bukti (MENUNGGU CLIENT)
        elseif (!$has_proof): ?>
             <div class="alert alert-info">
                Menunggu Client mengunggah bukti transfer.
            </div>
        <?php endif; ?>

    </div>

    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Bukti Transfer Client</div>
            <div class="card-body text-center">
                <?php if ($has_proof): 
                    $proof_url = '/' . htmlspecialchars($booking_data['proof_of_payment_path']);
                ?>
                    <p class="text-success">Bukti transfer sudah diunggah oleh Client.</p>
                    <a href="<?php echo $proof_url; ?>" target="_blank" class="btn btn-sm btn-info mb-3">
                        <i class="bi bi-eye"></i> Lihat Bukti Transfer
                    </a>
                    <div class="border rounded p-1">
                         <img src="<?php echo $proof_url; ?>" alt="Bukti Transfer" class="img-fluid" style="max-height: 300px; object-fit: contain;">
                    </div>
                <?php else: ?>
                    <p class="text-muted">Bukti transfer belum diunggah oleh Client.</p>
                    <i class="bi bi-file-earmark-image" style="font-size: 5rem; color: #ccc;"></i>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
