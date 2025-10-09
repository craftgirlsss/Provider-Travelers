<?php
// File: pages/dashboard/booking_detail.php
// Asumsi: Di-include melalui dashboard.php. $conn dan $actual_provider_id tersedia.

$booking_id = (int)($_GET['id'] ?? 0);
$booking_data = null;
$error = null;

if ($booking_id > 0) {
    try {
        // Ambil data booking, user, dan trip terkait, pastikan milik provider yang login
        $stmt = $conn->prepare("
            SELECT 
                b.*, 
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
    header("Location: /dashboard?p=bookings");
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
        
        <?php if ($booking_data['status'] === 'unpaid' && !empty($booking_data['proof_of_payment_path'])): ?>
            <div class="alert alert-warning">
                <b>Pembayaran Menunggu Konfirmasi!</b>
            </div>
            
            <form action="/process/booking_process.php" method="POST" class="mb-4">
                <input type="hidden" name="action" value="confirm_payment">
                <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                
                <button type="submit" class="btn btn-success btn-lg" 
                        onclick="return confirm('Yakin ingin MENGKONFIRMASI pembayaran ini? Status akan diubah menjadi PAID dan Client akan menerima notifikasi.');">
                    <i class="bi bi-check-circle me-2"></i> Konfirmasi Pembayaran Berhasil
                </button>
            </form>
        <?php elseif ($booking_data['status'] === 'paid'): ?>
            <div class="alert alert-success">
                Pembayaran telah <b>LUNAS</b> dikonfirmasi pada <?php echo date('d M Y H:i', strtotime($booking_data['paid_at'] ?? $booking_data['created_at'])); ?>.
            </div>
        <?php elseif (empty($booking_data['proof_of_payment_path'])): ?>
             <div class="alert alert-info">
                Menunggu Client mengunggah bukti transfer.
            </div>
        <?php endif; ?>

    </div>

    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Bukti Transfer Client</div>
            <div class="card-body text-center">
                <?php if (!empty($booking_data['proof_of_payment_path'])): 
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