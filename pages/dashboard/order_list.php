<?php
// File: pages/dashboard/order_list.php
// Halaman untuk Provider melihat daftar pemesanan Trip mereka.

$user_id = $_SESSION['user_id'];
$orders = [];
$error = null;

// Ambil pesan dari session (setelah proses apa pun)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

try {
    // 1. Dapatkan provider_id dari user_id yang sedang login
    $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("s", $user_id); // BIGINT UNSIGNED user_id = 's'
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    
    if ($result_provider->num_rows === 0) {
        $error = "Data Provider tidak ditemukan. Harap lengkapi profil Anda.";
    } else {
        $provider_data = $result_provider->fetch_assoc();
        $provider_id = $provider_data['id'];
        
        // 2. Ambil semua pemesanan yang terkait dengan trip milik Provider ini
        $sql = "SELECT 
                    b.id AS booking_id, b.user_id AS client_user_id, b.num_of_people, b.total_price, b.created_at AS booking_date, b.status AS booking_status,
                    t.id AS trip_id, t.title AS trip_title, t.start_date, t.end_date, t.duration,
                    u.name AS client_name, u.email AS client_email, u.phone AS client_phone,
                    p.status AS payment_status, p.method AS payment_method, p.paid_at
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN providers pr ON t.provider_id = pr.id
                JOIN users u ON b.user_id = u.id
                LEFT JOIN payments p ON b.id = p.booking_id -- Gunakan LEFT JOIN karena pembayaran bisa jadi belum ada
                WHERE pr.id = ?
                ORDER BY b.created_at DESC";
                
        $stmt_orders = $conn->prepare($sql);
        $stmt_orders->bind_param("s", $provider_id); // BIGINT UNSIGNED provider_id = 's'
        $stmt_orders->execute();
        $result_orders = $stmt_orders->get_result();
        
        if ($result_orders) {
            $orders = $result_orders->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = "Gagal memuat data pemesanan: " . $conn->error;
        }
        $stmt_orders->close();
    }
    $stmt_provider->close();
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan sistem: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Status Pemesanan
 */
function get_booking_status_badge($status) {
    switch ($status) {
        case 'pending': return '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Pending</span>';
        case 'confirmed': return '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Dikonfirmasi</span>';
        case 'cancelled': return '<span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i> Dibatalkan</span>';
        case 'completed': return '<span class="badge bg-primary"><i class="bi bi-trophy-fill me-1"></i> Selesai</span>';
        default: return '<span class="badge bg-secondary">N/A</span>';
    }
}

/**
 * Fungsi Pembantu untuk Badge Status Pembayaran
 */
function get_payment_status_badge($status, $paid_at) {
    if ($status === 'paid' && $paid_at) {
        return '<span class="badge bg-success"><i class="bi bi-credit-card-fill me-1"></i> Lunas</span>';
    } elseif ($status === 'pending') {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-wallet-fill me-1"></i> Menunggu Pembayaran</span>';
    } elseif ($status === 'refunded') {
        return '<span class="badge bg-info text-dark"><i class="bi bi-arrow-return-left me-1"></i> Dikembalikan</span>';
    } else {
        return '<span class="badge bg-secondary"><i class="bi bi-question-circle-fill me-1"></i> Belum Ada</span>';
    }
}
?>

<h1 class="mb-4">Daftar Pemesanan Trip</h1>
<p class="text-muted">Kelola semua pemesanan yang masuk untuk Trip Anda.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mt-4">
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <div class="alert alert-info text-center m-0">
                Belum ada pemesanan Trip yang masuk saat ini.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID Pemesanan</th>
                            <th>Trip</th>
                            <th>Client</th>
                            <th>Peserta</th>
                            <th>Total Harga</th>
                            <th>Tgl Pemesanan</th>
                            <th>Status Pemesanan</th>
                            <th>Status Pembayaran</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['booking_id']; ?></td>
                            <td>
                                <a href="/dashboard?p=trip_edit&id=<?php echo $order['trip_id']; ?>" class="text-primary text-decoration-none fw-bold">
                                    <?php echo htmlspecialchars($order['trip_title']); ?>
                                </a><br>
                                <small class="text-muted"><?php echo date('d M y', strtotime($order['start_date'])); ?> - <?php echo date('d M y', strtotime($order['end_date'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($order['client_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($order['client_email']); ?></small>
                            </td>
                            <td><?php echo $order['num_of_people']; ?> orang</td>
                            <td>Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($order['booking_date'])); ?></td>
                            <td><?php echo get_booking_status_badge($order['booking_status']); ?></td>
                            <td><?php echo get_payment_status_badge($order['payment_status'], $order['paid_at']); ?></td>
                            <td class="text-center">
                                <a href="/dashboard?p=booking_chat&booking_id=<?php echo $order['booking_id']; ?>" class="btn btn-sm btn-info text-dark me-1" title="Chat dengan Client">
                                    <i class="bi bi-chat-dots-fill"></i> Chat
                                </a>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>