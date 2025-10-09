<?php
// File: pages/dashboard/order_list.php
// Halaman untuk Provider melihat daftar pemesanan Trip mereka.
// **Catatan:** Menggunakan kolom 'proof_of_payment_path' di tabel 'bookings'.

// =======================================================================
// Perbaikan Utama: Menggunakan variabel yang sudah di-set di dashboard.php
// $actual_provider_id (int)
// =======================================================================

// Pastikan variabel utama sudah tersedia.
if (!isset($actual_provider_id) || !$actual_provider_id) {
    $error = "Error Otorisasi: Data Provider tidak ditemukan. Harap lengkapi profil Anda.";
    $provider_id = null;
} else {
    $provider_id = $actual_provider_id;
}

$orders = [];
$error = null; // Reset error, karena otorisasi awal sudah dicek di atas.

// Ambil pesan dari session (setelah proses apa pun)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

// PENGGANTIAN LOGIC DARI SINI:
try {
    // HILANGKAN: Logic 1. Dapatkan provider_id dari user_id yang sedang login
    
    if (!$provider_id) {
        // Jika ID masih null (seperti yang diatur di awal jika ada error otorisasi)
        $error = "Data Provider tidak ditemukan. Hubungi Admin."; 
    } else {
        // 2. Ambil semua pemesanan yang terkait dengan trip milik Provider ini
        $sql = "SELECT 
                    b.id AS booking_id, b.num_of_people, b.total_price, b.created_at AS booking_date, 
                    b.status AS booking_status, b.proof_of_payment_path, 
                    t.id AS trip_id, t.title AS trip_title, t.start_date, t.end_date, t.duration,
                    u.name AS client_name, u.email AS client_email, u.phone AS client_phone
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN providers pr ON t.provider_id = pr.id
                JOIN users u ON b.user_id = u.id
                WHERE pr.id = ?
                ORDER BY b.created_at DESC";
                
        $stmt_orders = $conn->prepare($sql);
        $stmt_orders->bind_param("i", $provider_id); // Gunakan $provider_id (integer)
        $stmt_orders->execute();
        $result_orders = $stmt_orders->get_result();
        
        if ($result_orders) {
            $orders = $result_orders->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = "Gagal memuat data pemesanan: " . $conn->error;
        }
        $stmt_orders->close();
    }
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan sistem: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Status Pemesanan
 */
function get_booking_status_badge($status) {
    // Jika status kosong/null, anggap unpaid (default awal pemesanan)
    if (empty($status) || $status == 'pending') { 
        // Mengganti 'pending' ke 'unpaid' jika logika DB Anda menganggap 'unpaid' sebagai status default awal.
        // Jika 'pending' adalah status yang berarti menanti konfirmasi bukti bayar, biarkan 'pending'.
        // Saya asumsikan 'unpaid' adalah status default awal pemesanan.
        $status = 'unpaid'; 
    }
    
    switch ($status) {
        case 'paid': return '<span class="badge bg-success"><i class="bi bi-credit-card-fill me-1"></i> PAID</span>';
        case 'unpaid': return '<span class="badge bg-warning text-dark"><i class="bi bi-wallet-fill me-1"></i> UNPAID</span>';
        case 'cancelled': return '<span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i> Dibatalkan</span>';
        case 'completed': return '<span class="badge bg-primary"><i class="bi bi-trophy-fill me-1"></i> Selesai</span>';
        default: return '<span class="badge bg-secondary">N/A - Cek DB</span>'; // Nilai darurat
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
                            <th>ID Pesan</th>
                            <th>Trip</th>
                            <th>Client</th>
                            <th>Peserta</th>
                            <th>Total Harga</th>
                            <th>Tgl Pesan</th>
                            <th>Status Pemesanan</th>
                            <th class="text-center">Bukti Transfer</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            $status = $order['booking_status'];
                            // Menggunakan trim() untuk memastikan tidak ada spasi kosong yang dianggap 'not empty'
                            $has_proof = !empty(trim($order['proof_of_payment_path'] ?? '')); 
                        ?>
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
                            <td><?php echo get_booking_status_badge($status); ?></td>
                            <td class="text-center">
                                <?php if ($status === 'unpaid' && $has_proof): ?>
                                    <span class="badge bg-warning text-dark" title="Menunggu Konfirmasi">
                                        <i class="bi bi-clock-history me-1"></i> Uploaded
                                    </span>
                                <?php elseif ($status === 'paid' || $status === 'completed'): ?>
                                    <span class="text-success" title="Lunas/Selesai"><i class="bi bi-check-lg"></i></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        Menunggu Client
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="/dashboard?p=booking_detail&id=<?php echo $order['booking_id']; ?>" 
                                   class="btn btn-sm btn-info" 
                                   title="Lihat Detail & Bukti Pembayaran">
                                    <i class="bi bi-eye"></i> Detail
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