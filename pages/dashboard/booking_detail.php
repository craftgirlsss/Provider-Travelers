<?php
// File: pages/dashboard/booking_detail.php
// Asumsi: Di-include melalui dashboard.php. $conn dan $actual_provider_id tersedia.

$booking_id = (int)($_GET['id'] ?? 0);
$booking_data = null;
$error = null;

// =========================================================================
// FUNGSI PEMBANTU
// =========================================================================

/**
 * Fungsi dummy untuk generate nomor invoice unik.
 */
function generateUniqueInvoiceNumber($conn) {
    // Implementasi sederhana: Ambil jumlah total bookings, lalu tambahkan 1
    $prefix = "INV/" . date("Y") . "/";
    
    // Perhatikan: Dalam produksi, gunakan sequence yang aman dari race condition.
    $result = $conn->query("SELECT COUNT(id) AS total FROM bookings");
    $row = $result->fetch_assoc();
    $sequence = $row['total'] + 1;

    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Fungsi untuk mengambil total harga dari sebuah booking (opsional, sebagai safety)
function getBookingPrice($conn, $booking_id) {
    $stmt = $conn->prepare("SELECT total_price FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data ? $data['total_price'] : 0;
}


// =========================================================================
// LOGIC KONFIRMASI PEMBAYARAN & INVOICE GENERATION (HEADER SAFE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'confirm_payment_and_invoice') {
    $booking_id_to_confirm = (int)($_POST['booking_id'] ?? 0);
    $redirect_url = "/dashboard?p=orders"; 

    if ($booking_id_to_confirm > 0) {
        $redirect_url = "/dashboard?p=booking_detail&id=" . $booking_id_to_confirm;
        $conn->begin_transaction(); // Mulai transaksi

        try {
            // 1. Cek kepemilikan, status, dan ambil harga
            $stmt_check = $conn->prepare("
                SELECT b.status, b.total_price 
                FROM bookings b 
                JOIN trips t ON b.trip_id = t.id 
                WHERE b.id = ? AND t.provider_id = ?
            ");
            $stmt_check->bind_param("ii", $booking_id_to_confirm, $actual_provider_id);
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
            
            $total_price = $current_booking['total_price']; // Ambil harga total

            // 2. Generate Invoice
            $new_invoice_number = generateUniqueInvoiceNumber($conn);
            $paid_at = date('Y-m-d H:i:s');
            
            // 3. Update status dan simpan nomor invoice di tabel bookings (gunakan b.invoice_number)
            $stmt_update_booking = $conn->prepare("UPDATE bookings SET status = 'paid', invoice_number = ? WHERE id = ?");
            $stmt_update_booking->bind_param("si", $new_invoice_number, $booking_id_to_confirm);
            $stmt_update_booking->execute();
            $stmt_update_booking->close();
            
            // 4. Update/Insert Payment Record di tabel payments
            
            // Periksa apakah sudah ada record payments untuk booking ini
            $stmt_check_payment = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
            $stmt_check_payment->bind_param("i", $booking_id_to_confirm);
            $stmt_check_payment->execute();
            $result_payment = $stmt_check_payment->get_result();
            $stmt_check_payment->close();

            if ($result_payment->num_rows > 0) {
                // UPDATE: Jika sudah ada record (mungkin 'pending' karena upload bukti), UPDATE status dan paid_at
                $stmt_update_payment = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = ? WHERE booking_id = ?");
                $stmt_update_payment->bind_param("si", $paid_at, $booking_id_to_confirm);
                $stmt_update_payment->execute();
                $stmt_update_payment->close();
            } else {
                // INSERT: Buat record payments baru (jika tidak ada record sama sekali, atau konfirmasi manual tanpa upload bukti)
                // UUID() harus tersedia di MySQL Anda.
                $stmt_insert_payment = $conn->prepare("INSERT INTO payments (booking_id, amount, method, status, paid_at, uuid) 
                                                      VALUES (?, ?, 'Transfer Bank (Konfirmasi Admin)', 'paid', ?, UUID())");
                $stmt_insert_payment->bind_param("ids", $booking_id_to_confirm, $total_price, $paid_at);
                $stmt_insert_payment->execute();
                $stmt_insert_payment->close();
            }

            $conn->commit(); // Commit transaksi
            
            $_SESSION['dashboard_message'] = "Pembayaran berhasil dikonfirmasi. Status diubah menjadi PAID, dan Invoice **" . htmlspecialchars($new_invoice_number) . "** siap didownload.";
            $_SESSION['dashboard_message_type'] = 'success';
            
        } catch (Exception $e) {
            $conn->rollback(); // Rollback jika terjadi error
            $_SESSION['dashboard_message'] = "Gagal memproses konfirmasi: " . $e->getMessage();
            $_SESSION['dashboard_message_type'] = 'danger';
            $redirect_url = "/dashboard?p=orders"; 
        }

        // REDIRECT DAN EXIT SELALU DI AKHIR BLOK POST
        header("Location: " . $redirect_url);
        exit();
    }
}
// =========================================================================


// =========================================================================
// LOGIC PENGAMBILAN DATA
// =========================================================================
if ($booking_id > 0) {
    try {
        // Query Final: Mengambil invoice_number dari bookings dan paid_at dari payments (p.paid_at)
        $stmt = $conn->prepare("
            SELECT 
                b.*, b.invoice_number, 
                t.title AS trip_title, 
                u.name AS client_name, u.email AS client_email,
                p.paid_at /* Ambil paid_at dari tabel payments */
            FROM bookings b
            JOIN trips t ON b.trip_id = t.id
            JOIN users u ON b.user_id = u.id
            LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'paid'
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

// =========================================================================
// REDIRECT JIKA TERJADI ERROR (HEADER SAFE)
// =========================================================================
if ($error) {
    $_SESSION['dashboard_message'] = $error;
    $_SESSION['dashboard_message_type'] = "danger";
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
                                else if ($status === 'pending') $badge_class = 'info text-dark';
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst(htmlspecialchars($status)); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Tanggal Pesan</th>
                        <td><?php echo date('d M Y H:i', strtotime($booking_data['created_at'])); ?></td>
                    </tr>
                     <?php if (!empty($booking_data['paid_at'])): // Tampilkan hanya jika ada data paid_at dari tabel payments ?>
                    <tr>
                        <th>Tanggal Lunas</th>
                        <td><?php echo date('d M Y H:i', strtotime($booking_data['paid_at'])); ?></td>
                    </tr>
                    <?php endif; ?>
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