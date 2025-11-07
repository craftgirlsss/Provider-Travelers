<?php
// File: pages/dashboard/trip_archive.php
// Menampilkan daftar Trip yang sudah terlewat tanggalnya (Riwayat Selesai),
// Trip yang sudah dihapus/diarsipkan manual, dan Trip Pending yang sudah lewat tanggal mulai.

global $conn, $actual_provider_id;

// =======================================================================
// KONFIGURASI PAGINATION & FILTER
// =======================================================================
$limit = 10; // Maksimal 10 item per halaman
$provider_id = $actual_provider_id ?? 0;
$current_tab = $_GET['tab'] ?? 'completed'; // Default tab aktif
$page = (int)($_GET['page'] ?? 1);
$page = max(1, $page);
$offset = ($page - 1) * $limit;
$search_query = trim($_GET['search'] ?? '');
$search_param = '%' . $search_query . '%';

// Inisialisasi variabel hasil
$error = null;
$results = [
    'completed' => ['data' => [], 'total' => 0, 'current_page' => 1],
    'deleted' => ['data' => [], 'total' => 0, 'current_page' => 1],
    'pending-risky' => ['data' => [], 'total' => 0, 'current_page' => 1]
];

if (!$provider_id) {
    $error = "Akses Ditolak: ID Provider tidak ditemukan. Mohon login ulang.";
}

// Fungsi untuk membangun ulang URL query string (penting untuk pagination/filter)
function build_query($tab, $page_num = null, $search = null) {
    $params = $_GET;
    $params['tab'] = $tab;
    
    if ($page_num !== null) {
        $params['page'] = max(1, $page_num);
    } else {
        unset($params['page']);
    }
    
    if ($search !== null) {
        $params['search'] = $search;
    }
    
    return http_build_query($params);
}

// =======================================================================
// LOGIC PENGAMBILAN DATA
// =======================================================================

if (!$error && $provider_id) {
    try {
        // --- BASE CONDITIONS & BINDING ---
        $base_where = "provider_id = ? AND is_deleted = 0 AND t.title LIKE ? ";
        $base_params = [$provider_id, $search_param];
        $base_types = "is";
        
        // Cek tab mana yang sedang aktif untuk mengambil data lengkapnya
        if ($current_tab == 'completed') {
            $current_data_key = 'completed';
        } elseif ($current_tab == 'deleted') {
            // Deleted memiliki kondisi base yang berbeda (is_deleted = 1)
            $base_where = "provider_id = ? AND is_deleted = 1 AND t.title LIKE ? ";
            $current_data_key = 'deleted';
        } elseif ($current_tab == 'pending-risky') {
            $base_where .= " AND approval_status = 'pending' AND start_date <= CURDATE() ";
            $current_data_key = 'pending-risky';
        } else {
            // Default ke completed jika tab tidak valid
            $current_data_key = 'completed';
        }

        // --- 1. Hitung Total Trip untuk Tab Aktif ---
        // (Menggunakan Alias t untuk konsistensi jika JOIN ditambahkan nanti, tapi saat ini hanya trip)
        $count_sql = "SELECT COUNT(id) AS total FROM trips t WHERE {$base_where}";
        $stmt_count = $conn->prepare($count_sql);
        
        // Bind parameter untuk count: [i, s] (provider_id, search_param)
        $stmt_count->bind_param($base_types, ...$base_params);
        $stmt_count->execute();
        $total_trips = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();
        
        $total_pages = ceil($total_trips / $limit);
        
        // Sesuaikan halaman saat ini dan offset
        $page = min($page, max(1, $total_pages)); // Pastikan page tidak melebihi total pages
        $offset = ($page - 1) * $limit;
        
        $results[$current_data_key]['total'] = $total_trips;
        $results[$current_data_key]['current_page'] = $page;


        // --- 2. Ambil Data Trip untuk Tab Aktif ---
        $main_sql = "
            SELECT 
                id, uuid, title, location, start_date, end_date, price, approval_status
            FROM 
                trips t
            WHERE 
                {$base_where}
            ORDER BY 
                end_date DESC
            LIMIT ? OFFSET ?
        ";
        
        // Tambahkan LIMIT dan OFFSET ke parameter
        $main_params = array_merge($base_params, [$limit, $offset]);
        $main_types = $base_types . "ii"; 

        $stmt_main = $conn->prepare($main_sql);
        
        // Bind parameter untuk main query
        $stmt_main->bind_param($main_types, ...$main_params);
        $stmt_main->execute();
        $results[$current_data_key]['data'] = $stmt_main->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_main->close();

    } catch (Exception $e) {
        $error = "Gagal memuat riwayat trip: " . $e->getMessage();
    }
}

// Ambil data dari array hasil agar mudah diakses
$completed_trips = $results['completed']['data'];
$deleted_trips = $results['deleted']['data'];
$pending_risky_trips = $results['pending-risky']['data'];

// Ambil total dan page dari tab yang sedang aktif untuk rendering pagination
$active_total = $results[$current_tab]['total'];
$active_page = $results[$current_tab]['current_page'];
$active_total_pages = ceil($active_total / $limit);

// Fungsi untuk Badge Status Persetujuan
function get_approval_badge_html($approval_status) {
    switch ($approval_status) {
        case 'approved': return '<span class="badge bg-primary-subtle text-primary border border-primary">Disetujui</span>';
        case 'suspended': return '<span class="badge bg-danger-subtle text-danger border border-danger">Ditangguhkan</span>';
        case 'pending':
        default: return '<span class="badge bg-warning-subtle text-warning border border-warning">Menunggu Persetujuan</span>';
    }
}
?>

<style>
    /* Styling Card Modern Tanpa Foto */
    .trip-list-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-left: 4px solid; /* Border untuk status */
        border-radius: 8px;
        background-color: #ffffff;
    }
    .trip-list-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.3rem 0.8rem rgba(0, 0, 0, 0.1) !important;
    }
    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .trip-info-item {
        font-size: 0.85rem;
        color: #6c757d; /* text-secondary */
    }
    .status-area {
        min-width: 100px;
    }
    .price-tag {
        font-size: 1rem;
        font-weight: bold;
        color: var(--bs-primary);
    }

    /* Warna border untuk status */
    .border-approved { border-left-color: #198754 !important; } /* success */
    .border-pending { border-left-color: #ffc107 !important; } /* warning */
    .border-rejected { border-left-color: #dc3545 !important; } /* danger */
    .border-deleted { border-left-color: #6c757d !important; } /* secondary */
</style>

<h1 class="h3 mb-4 fw-bold text-primary">Riwayat Trip & Arsip</h1>
<p class="text-muted">Kelola trip yang sudah selesai, diarsipkan, atau memiliki status pending yang berisiko.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="archiveTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?php echo ($current_tab == 'completed' || $current_tab == '') ? 'active' : ''; ?>" 
           href="?p=trip_archive&tab=completed&search=<?php echo htmlspecialchars($search_query); ?>" 
           role="tab" aria-controls="completed" aria-selected="<?php echo ($current_tab == 'completed' || $current_tab == '') ? 'true' : 'false'; ?>">
            <i class="bi bi-clock-history me-1"></i> Riwayat Trip Selesai (<?php echo $results['completed']['total']; ?>)
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?php echo $current_tab == 'deleted' ? 'active' : ''; ?>" 
           href="?p=trip_archive&tab=deleted&search=<?php echo htmlspecialchars($search_query); ?>" 
           role="tab" aria-controls="deleted" aria-selected="<?php echo $current_tab == 'deleted' ? 'true' : 'false'; ?>">
            <i class="bi bi-trash me-1"></i> Trip Dihapus (<?php echo $results['deleted']['total']; ?>)
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?php echo $current_tab == 'pending-risky' ? 'active' : ''; ?>" 
           href="?p=trip_archive&tab=pending-risky&search=<?php echo htmlspecialchars($search_query); ?>" 
           role="tab" aria-controls="pending-risky" aria-selected="<?php echo $current_tab == 'pending-risky' ? 'true' : 'false'; ?>">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Pending Berisiko (<?php echo $results['pending-risky']['total']; ?>)
        </a>
    </li>
</ul>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="/dashboard" class="row g-3 align-items-end">
            <input type="hidden" name="p" value="trip_archive">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
            
            <div class="col-md-8">
                <label for="search" class="form-label fw-bold small text-muted">Cari Nama Trip</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Cari trip dalam tab ini..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                    <i class="bi bi-search me-1"></i> Cari
                </button>
                <a href="/dashboard?p=trip_archive&tab=<?php echo htmlspecialchars($current_tab); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="tab-content">
    
    <?php
    // Fungsi pembantu untuk merender daftar
    function render_trip_list($trips, $tab_key) {
        if (empty($trips)): ?>
            <div class="alert alert-info text-center m-0 shadow-sm">
                <i class="bi bi-info-circle me-2"></i> Tidak ada Trip yang ditemukan di tab ini.
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($trips as $trip): 
                    $status_text = ucfirst($trip['approval_status']);
                    $status_class = 'border-' . strtolower($trip['approval_status']);
                    
                    if ($tab_key == 'deleted') {
                        $status_class = 'border-deleted';
                        $status_text = 'Dihapus';
                        $badge_bg = 'bg-secondary';
                    } elseif ($tab_key == 'pending-risky') {
                        $status_class = 'border-pending';
                        $status_text = 'PENDING';
                        $badge_bg = 'bg-warning text-dark';
                    } else { // completed
                        $badge_bg = ($trip['approval_status'] == 'approved') ? 'bg-success' : 
                                     (($trip['approval_status'] == 'pending') ? 'bg-warning text-dark' : 'bg-danger');
                    }
                ?>
                <div class="p-3 shadow-sm trip-list-card d-flex align-items-center <?php echo $status_class; ?>">
                    
                    <div class="flex-grow-1 me-3">
                        <h5 class="card-title text-dark"><?php echo htmlspecialchars($trip['title']); ?></h5>
                        <div class="trip-info-item">
                            <i class="bi bi-geo-alt-fill me-1"></i> <?php echo htmlspecialchars($trip['location']); ?>
                        </div>
                    </div>
                    
                    <div class="text-nowrap me-3 d-none d-md-block" style="min-width: 200px;">
                        <div class="trip-info-item">
                            <i class="bi bi-calendar-check me-1"></i> Periode:
                        </div>
                        <div class="fw-bold text-success small">
                            <?php echo date('d M Y', strtotime($trip['start_date'])) . " - " . date('d M Y', strtotime($trip['end_date'])); ?>
                        </div>
                    </div>

                    <div class="text-end me-3 d-none d-sm-block" style="min-width: 120px;">
                        <div class="trip-info-item">Harga Dasar:</div>
                        <div class="price-tag"><?php echo "Rp " . number_format($trip['price'], 0, ',', '.'); ?></div>
                    </div>
                    
                    <div class="status-area text-center me-3">
                        <span class="badge <?php echo $badge_bg; ?> py-2 px-3">
                            <?php echo $status_text; ?>
                        </span>
                    </div>

                    <div class="text-end">
                        <?php if ($tab_key == 'deleted'): ?>
                            <form action="/process/trip_process.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin mengembalikan Trip ini ke daftar aktif?');">
                                <input type="hidden" name="action" value="restore_trip">
                                <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-arrow-counterclockwise"></i> Pulihkan
                                </button>
                            </form>
                        <?php elseif ($tab_key == 'pending-risky'): ?>
                             <form action="/process/trip_process.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin MENGARSIPKAN Trip yang sudah lewat ini?');">
                                <input type="hidden" name="action" value="delete_trip">
                                <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Arsipkan Trip ini">
                                    <i class="bi bi-archive-fill"></i> Arsipkan
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }
    ?>
    
    <div class="tab-pane fade <?php echo ($current_tab == 'completed' || $current_tab == '') ? 'show active' : ''; ?>" id="completed-content" role="tabpanel" aria-labelledby="completed-tab">
        <p class="text-muted small mb-3">Daftar Trip yang tanggal selesainya telah terlewat (otomatis masuk riwayat).</p>
        <?php render_trip_list($completed_trips, 'completed'); ?>
    </div>
    
    <div class="tab-pane fade <?php echo $current_tab == 'deleted' ? 'show active' : ''; ?>" id="deleted-content" role="tabpanel" aria-labelledby="deleted-tab">
        <p class="text-muted small mb-3">Daftar Trip yang Anda hapus atau arsipkan secara manual. Anda dapat mengembalikannya.</p>
        <?php render_trip_list($deleted_trips, 'deleted'); ?>
    </div>
    
    <div class="tab-pane fade <?php echo $current_tab == 'pending-risky' ? 'show active' : ''; ?>" id="pending-risky-content" role="tabpanel" aria-labelledby="pending-risky-tab">
        <p class="text-muted small mb-3">Daftar Trip yang tanggal mulainya sudah lewat/tiba, namun <b>masih menunggu persetujuan Admin</b>.</p>
        <?php if (!empty($pending_risky_trips)): ?>
            <div class="alert alert-danger shadow-sm">
                <i class="bi bi-lightning-fill me-2"></i> <b>Peringatan!</b> Trip ini sudah lewat tanggal mulai. Segera hubungi Admin untuk persetujuan atau arsipkan.
            </div>
        <?php endif; ?>
        <?php render_trip_list($pending_risky_trips, 'pending-risky'); ?>
    </div>

</div>

<?php if ($active_total_pages > 1): ?>
<nav aria-label="Pagination Arsip" class="mt-4">
  <ul class="pagination justify-content-center">
    
    <li class="page-item <?php echo $active_page <= 1 ? 'disabled' : ''; ?>">
      <a class="page-link" href="/dashboard?<?php echo build_query($current_tab, $active_page - 1, $search_query); ?>" aria-label="Previous">
        <span aria-hidden="true">&laquo;</span>
      </a>
    </li>
    
    <?php 
    // Tampilkan hingga 5 halaman di sekitar halaman saat ini
    $start_page = max(1, $active_page - 2);
    $end_page = min($active_total_pages, $active_page + 2);
    
    // Sesuaikan jika di awal/akhir
    if ($start_page == 1) $end_page = min($active_total_pages, 5);
    if ($end_page == $active_total_pages) $start_page = max(1, $active_total_pages - 4);

    for ($i = $start_page; $i <= $end_page; $i++): 
    ?>
    <li class="page-item <?php echo $i === $active_page ? 'active' : ''; ?>">
        <a class="page-link" href="/dashboard?<?php echo build_query($current_tab, $i, $search_query); ?>"><?php echo $i; ?></a>
    </li>
    <?php endfor; ?>
    
    <li class="page-item <?php echo $active_page >= $active_total_pages ? 'disabled' : ''; ?>">
      <a class="page-link" href="/dashboard?<?php echo build_query($current_tab, $active_page + 1, $search_query); ?>" aria-label="Next">
        <span aria-hidden="true">&raquo;</span>
      </a>
    </li>
  </ul>
</nav>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Script untuk memastikan tab aktif saat navigasi
    document.addEventListener('DOMContentLoaded', function() {
        // Ambil URL saat ini
        const urlParams = new URLSearchParams(window.location.search);
        let activeTab = urlParams.get('tab') || 'completed';
        
        // Atur kelas 'show active' pada konten tab yang sesuai
        const targetContent = document.getElementById(activeTab + '-content');
        if (targetContent) {
            targetContent.classList.add('show', 'active');
        }
        
        // Karena kita menggunakan <a> sebagai navigasi tab, kita perlu memastikan form filter
        // juga mengirimkan tab yang benar. Ini sudah diatasi di PHP dengan hidden input 'tab'.
    });
</script>