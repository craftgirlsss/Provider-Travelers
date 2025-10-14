<?php
// File: pages/dashboard/order_list.php
// Halaman untuk Provider melihat daftar pemesanan Trip mereka, DIFILTER berdasarkan Trip ID.

global $conn, $actual_provider_id;

// =======================================================================
// Persiapan Data & Filter (KODE PHP TIDAK BERUBAH)
// =======================================================================

if (!isset($actual_provider_id) || !$actual_provider_id) {
    $error = "Error Otorisasi: Data Provider tidak ditemukan. Harap lengkapi profil Anda.";
    $provider_id = null;
} else {
    $provider_id = $actual_provider_id;
}

$trips = []; // Daftar Trip untuk Dropdown
$orders = []; // Daftar Pemesanan yang difilter
// Gunakan 'trip_id' untuk filter
$trip_id_filter = (int)($_GET['trip_id'] ?? 0); 
$current_page = (int)($_GET['page'] ?? 1);
$orders_per_page = 10;
$total_orders = 0;
$total_pages = 1;
$error = null;

// Ambil pesan dari session
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

try {
    if (!$provider_id) {
        $error = "Data Provider tidak ditemukan. Hubungi Admin."; 
    } else {
        // 1. Ambil daftar Trip unik untuk Dropdown
        $sql_trips = "
            SELECT id, title, start_date 
            FROM trips 
            WHERE provider_id = ? 
            AND is_deleted = 0 
            ORDER BY start_date DESC";
        $stmt_trips = $conn->prepare($sql_trips);
        $stmt_trips->bind_param("i", $provider_id);
        $stmt_trips->execute();
        $trips = $stmt_trips->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_trips->close();

        // Tentukan Trip ID yang akan difilter jika belum ada
        if ($trip_id_filter === 0 && !empty($trips)) {
            $trip_id_filter = $trips[0]['id']; // Default ke Trip terbaru
        }

        if ($trip_id_filter > 0) {
            
            // ==========================================================
            // 2A. Hitung Total Pemesanan (untuk Pagination)
            // ==========================================================
            $sql_count = "
                SELECT COUNT(b.id) 
                FROM bookings b
                WHERE b.trip_id = ?";
            
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bind_param("i", $trip_id_filter);
            $stmt_count->execute();
            $stmt_count->bind_result($total_orders);
            $stmt_count->fetch();
            $stmt_count->close();

            $total_pages = ceil($total_orders / $orders_per_page);
            $offset = ($current_page - 1) * $orders_per_page;
            
            // Perbaikan jika current_page melebihi batas atau kurang dari 1
            if ($current_page > $total_pages && $total_pages > 0) {
                $current_page = $total_pages;
                $offset = ($current_page - 1) * $orders_per_page;
            } elseif ($current_page < 1) {
                $current_page = 1;
                $offset = 0;
            }
            
            // ==========================================================
            // 2B. Ambil Data Pemesanan dengan Limit dan Offset
            // ==========================================================
            $sql_orders = "SELECT 
                        b.id AS booking_id, b.num_of_people, b.total_price, b.created_at AS booking_date, 
                        b.status AS booking_status, b.proof_of_payment_path, 
                        u.name AS client_name, u.email AS client_email, u.phone AS client_phone
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    WHERE b.trip_id = ?
                    ORDER BY b.created_at DESC
                    LIMIT ? OFFSET ?";
                    
            $stmt_orders = $conn->prepare($sql_orders);
            $stmt_orders->bind_param("iii", $trip_id_filter, $orders_per_page, $offset);
            $stmt_orders->execute();
            $orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_orders->close();
        }
    }
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan sistem: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Status Pemesanan
 */
function get_booking_status_badge($status) {
    switch ($status) {
        case 'paid': return '<span class="badge bg-success"><i class="bi bi-credit-card-fill me-1"></i> PAID</span>';
        case 'unpaid': return '<span class="badge bg-warning text-dark"><i class="bi bi-wallet-fill me-1"></i> UNPAID</span>';
        case 'cancelled': return '<span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i> Dibatalkan</span>';
        case 'completed': return '<span class="badge bg-primary"><i class="bi bi-trophy-fill me-1"></i> Selesai</span>';
        case 'pending': return '<span class="badge bg-info text-dark"><i class="bi bi-hourglass-split me-1"></i> Menunggu Konfirmasi</span>';
        default: return '<span class="badge bg-secondary">N/A</span>'; 
    }
}

/**
 * Fungsi Pembantu untuk Border Status Card
 */
function get_status_border_class($status) {
    switch ($status) {
        case 'paid':
        case 'completed': return 'border-success';
        case 'pending': return 'border-info';
        case 'unpaid': return 'border-warning';
        case 'cancelled': return 'border-danger';
        default: return 'border-secondary'; 
    }
}
?>

<style>
    /* Styling Card Modern untuk Daftar Pesanan */
    .order-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-left: 5px solid; /* Border untuk status */
        border-radius: 8px;
        background-color: #ffffff;
    }
    .order-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.4rem 1rem rgba(0, 0, 0, 0.1) !important;
    }
    .client-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #212529;
    }
    .detail-label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-bottom: 0;
    }
    .detail-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: #495057;
    }
    .total-price {
        font-size: 1.25rem;
        font-weight: bold;
        color: var(--bs-primary);
    }
</style>

<h1 class="mb-4">Daftar Pemesanan Trip</h1>
<p class="text-muted">Pilih Trip dari dropdown di bawah untuk melihat detail pemesanan.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mt-4 mb-5">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-funnel-fill me-2"></i> Filter Trip</h5>
    </div>
    <div class="card-body">
        <form action="/dashboard" method="GET" class="row align-items-center">
            <input type="hidden" name="p" value="orders">
            
            <div class="col-md-8">
                <select name="trip_id" id="trip_id_filter" class="form-select" onchange="this.form.submit()">
                    <option value="0" disabled <?php echo ($trip_id_filter === 0 ? 'selected' : ''); ?>>-- Pilih Trip --</option>
                    <?php 
                        $selected_trip_title = "Pilih Trip untuk Melihat Pemesanan";
                        foreach ($trips as $trip): 
                            if ((int)$trip['id'] === $trip_id_filter) {
                                $selected_trip_title = htmlspecialchars($trip['title']);
                            }
                    ?>
                        <option value="<?php echo $trip['id']; ?>" 
                                <?php echo ((int)$trip['id'] === $trip_id_filter) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($trip['title']); ?> (<?php echo date('d M Y', strtotime($trip['start_date'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4 text-end">
                <span class="badge bg-primary fs-6">
                    Total Pesanan: <?php echo number_format($total_orders); ?>
                </span>
            </div>
        </form>
    </div>
</div>

<?php if ($trip_id_filter === 0): ?>
    <div class="alert alert-info text-center shadow-sm">
        <i class="bi bi-arrow-up-circle me-2"></i> Silakan pilih **Trip** dari dropdown di atas untuk memuat daftar pemesanan.
    </div>
<?php elseif (empty($orders) && $total_orders == 0): ?>
    <div class="alert alert-info text-center shadow-sm">
        <i class="bi bi-info-circle me-2"></i> Trip **<?php echo htmlspecialchars($selected_trip_title); ?>** belum memiliki pemesanan.
    </div>
<?php else: ?>
    
    <h3 class="mb-3">Daftar Pemesanan untuk Trip: <span class="text-primary"><?php echo htmlspecialchars($selected_trip_title); ?></span></h3>
    
    <div class="d-grid gap-3">
        <?php foreach ($orders as $order): 
            $status = $order['booking_status'];
            $status_class = get_status_border_class($status);
            $has_proof = !empty(trim($order['proof_of_payment_path'] ?? '')); 
        ?>
        <div class="p-3 shadow-sm order-card d-flex flex-column flex-md-row align-items-md-center <?php echo $status_class; ?>">
            
            <div class="flex-fill me-md-4 mb-2 mb-md-0" style="min-width: 250px;">
                <p class="detail-label">CLIENT & ID PESAN</p>
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                         <div class="client-name"><?php echo htmlspecialchars($order['client_name']); ?></div>
                         <div class="detail-value text-muted small">
                            <i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($order['client_email']); ?>
                         </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary-subtle text-secondary fw-bold">#<?php echo $order['booking_id']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="d-flex flex-row flex-md-row justify-content-between align-items-center me-md-4 mb-2 mb-md-0" style="min-width: 350px;">
                <div class="me-3">
                    <p class="detail-label">PESERTA</p>
                    <p class="detail-value text-dark mb-0"><i class="bi bi-person-fill me-1"></i> <?php echo $order['num_of_people']; ?> orang</p>
                </div>
                
                <div class="me-3">
                    <p class="detail-label">TGL. PESAN</p>
                    <p class="detail-value text-dark mb-0"><i class="bi bi-calendar-event me-1"></i> <?php echo date('d M Y H:i', strtotime($order['booking_date'])); ?></p>
                </div>

                <div>
                    <p class="detail-label text-center">BUKTI TRANSFER</p>
                    <div class="text-center">
                        <?php if (($status === 'unpaid' || $status === 'pending') && $has_proof): ?>
                            <span class="badge bg-warning text-dark" title="Menunggu Konfirmasi">
                                <i class="bi bi-clock-history me-1"></i> Uploaded
                            </span>
                        <?php elseif ($status === 'paid' || $status === 'completed'): ?>
                            <span class="text-success fs-5" title="Lunas/Selesai"><i class="bi bi-check-circle-fill"></i></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">
                                Menunggu Client
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="text-md-end text-start d-flex flex-column align-items-start align-items-md-end" style="min-width: 150px;">
                <p class="detail-label">TOTAL HARGA</p>
                <div class="total-price mb-2">Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?></div>
                <div class="mb-2 w-100"><?php echo get_booking_status_badge($status); ?></div>
                
                <a href="/dashboard?p=booking_detail&id=<?php echo $order['booking_id']; ?>" 
                   class="btn btn-sm btn-info w-100" 
                   title="Lihat Detail & Bukti Pembayaran">
                    <i class="bi bi-search me-1"></i> Tinjau Pesanan
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
      <ul class="pagination justify-content-center">
        <?php 
          // PENTING: MENGGANTI PARAMETER P DARI 'order_list' menjadi 'orders'
          $base_url = "/dashboard?p=orders&trip_id=" . $trip_id_filter; 
          $prev_page = max(1, $current_page - 1);
          $next_page = min($total_pages, $current_page + 1);
        ?>
        
        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo $base_url . "&page=" . $prev_page; ?>" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
          </a>
        </li>
        
        <?php 
        // Logic Pagination yang Lebih Ramping
        $start_loop = 1;
        $end_loop = $total_pages;

        if ($total_pages > 5) {
            $start_loop = max(1, $current_page - 2);
            $end_loop = min($total_pages, $current_page + 2);

            if ($start_loop > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=1">1</a></li>';
                if ($start_loop > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
        }
        
        for ($i = $start_loop; $i <= $end_loop; $i++): ?>
          <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo $base_url . "&page=" . $i; ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; 
        
        if ($total_pages > 5 && $end_loop < $total_pages) {
            if ($end_loop < $total_pages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
        }
        ?>
        
        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo $base_url . "&page=" . $next_page; ?>" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
          </a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
    
<?php endif; ?>