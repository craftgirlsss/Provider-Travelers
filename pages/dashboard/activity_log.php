<?php
// File: pages/dashboard/activity_log.php
// Menampilkan riwayat aktivitas provider dari tabel provider_logs

global $conn, $actual_provider_id; 

// ==========================================================
// 1. KONFIGURASI DAN INISIALISASI PAGINASI
// ==========================================================
$logs_per_page = 10;
$current_page = (int)($_GET['page'] ?? 1);
$offset = ($current_page - 1) * $logs_per_page;

// ==========================================================
// 2. FILTER PENCARIAN DAN TANGGAL
// ==========================================================
$search_term = trim($_GET['search'] ?? '');
$filter_date = trim($_GET['date'] ?? '');
$where_clauses = ["provider_id = ?"];
$params = [$actual_provider_id];
$param_types = "i";

// Filter Search (Aksi atau Deskripsi)
if (!empty($search_term)) {
    $where_clauses[] = "(action_type LIKE ? OR description LIKE ?)";
    $params[] = "%" . $search_term . "%";
    $params[] = "%" . $search_term . "%";
    $param_types .= "ss";
}

// Filter Tanggal
if (!empty($filter_date)) {
    // Validasi format tanggal (YYYY-MM-DD)
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_date)) {
        // Mencari log pada tanggal spesifik tersebut
        $where_clauses[] = "DATE(created_at) = ?";
        $params[] = $filter_date;
        $param_types .= "s";
    } else {
        $filter_date = ''; // Reset jika format tidak valid
    }
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// ==========================================================
// 3. PENGAMBILAN DATA (TOTAL COUNT & LOGS)
// ==========================================================
$total_logs = 0;
$logs = [];
$error = null;

try {
    // A. Hitung Total Log
    $stmt_count = $conn->prepare("SELECT COUNT(id) FROM provider_logs " . $where_sql);
    $stmt_count->bind_param($param_types, ...$params);
    $stmt_count->execute();
    $total_logs = $stmt_count->get_result()->fetch_row()[0];
    $stmt_count->close();
    
    // Hitung total halaman
    $total_pages = ceil($total_logs / $logs_per_page);
    
    // B. Ambil Data Log
    // Tambahkan LIMIT dan OFFSET
    $stmt_logs = $conn->prepare("SELECT 
        id, action_type, table_name, record_id, description, ip_address, created_at 
        FROM provider_logs 
        " . $where_sql . "
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");

    // Tipe data untuk LIMIT dan OFFSET (integer)
    $param_types_logs = $param_types . "ii";
    $params_logs = array_merge($params, [$logs_per_page, $offset]);

    $stmt_logs->bind_param($param_types_logs, ...$params_logs);
    $stmt_logs->execute();
    $logs = $stmt_logs->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_logs->close();

} catch (Exception $e) {
    $error = "Gagal memuat log aktivitas: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Tipe Aksi
 */
function get_action_badge($action) {
    switch (strtoupper($action)) {
        case 'CREATE': return '<span class="badge bg-success-subtle text-success fw-bold">CREATE</span>';
        case 'UPDATE': return '<span class="badge bg-primary-subtle text-primary fw-bold">UPDATE</span>';
        case 'DELETE': return '<span class="badge bg-danger-subtle text-danger fw-bold">DELETE/ARSIP</span>';
        case 'LOGIN': return '<span class="badge bg-info-subtle text-info fw-bold">LOGIN</span>';
        case 'LOGOUT': return '<span class="badge bg-secondary-subtle text-secondary fw-bold">LOGOUT</span>';
        case 'ERROR': return '<span class="badge bg-dark-subtle text-dark fw-bold">ERROR</span>';
        default: return '<span class="badge bg-light text-dark border fw-bold">'. strtoupper($action) .'</span>';
    }
}

/**
 * Fungsi Pembantu untuk Link Paginasi
 */
function get_pagination_link($page, $search, $date) {
    $base = "/dashboard?p=activity_log";
    $query = [];
    if (!empty($search)) $query['search'] = urlencode($search);
    if (!empty($date)) $query['date'] = urlencode($date);
    $query['page'] = $page;
    return $base . "&" . http_build_query($query);
}

// Menghilangkan parameter 'page' dari query string saat ini
$current_query = $_GET;
unset($current_query['page']);
$base_pagination_url = "/dashboard?" . http_build_query($current_query) . "&p=activity_log";
?>

<h1 class="mb-4 text-primary fw-bold">Riwayat Aktivitas</h1>
<p class="text-muted">Jejak audit (log) semua aksi kritis yang dilakukan oleh akun Provider Anda.</p>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="/dashboard" class="row g-3 align-items-end">
            <input type="hidden" name="p" value="activity_log">
            
            <div class="col-md-5 col-lg-4">
                <label for="search" class="form-label small text-muted">Cari Aksi atau Deskripsi</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Cari: CREATE trip, UPDATE harga..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
            </div>
            
            <div class="col-md-4 col-lg-3">
                <label for="date" class="form-label small text-muted">Filter Tanggal</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
            </div>
            
            <div class="col-md-3 col-lg-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
            </div>
            
            <?php if (!empty($search_term) || !empty($filter_date)): ?>
                <div class="col-md-1 col-lg-1">
                    <a href="/dashboard?p=activity_log" class="btn btn-outline-secondary w-100" title="Reset Filter">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-secondary">Total <?php echo $total_logs; ?> Log Ditemukan</h5>
    </div>
    
    <?php if (empty($logs)): ?>
        <div class="card-body">
            <div class="alert alert-info text-center mb-0">
                <i class="bi bi-info-circle me-2"></i> Tidak ada aktivitas yang terekam dengan filter ini.
            </div>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($logs as $log): ?>
            <li class="list-group-item d-flex justify-content-between align-items-start py-3 log-item">
                <div class="me-auto">
                    <div class="d-flex align-items-center mb-1">
                        <?php echo get_action_badge($log['action_type']); ?>
                        <span class="ms-3 text-dark fw-semibold">
                            <?php echo htmlspecialchars($log['description']); ?>
                        </span>
                    </div>
                    
                    <p class="mb-0 small text-muted ps-1">
                        <span class="text-secondary me-3" title="Tabel yang terpengaruh">
                            <i class="bi bi-table me-1"></i> <?php echo htmlspecialchars($log['table_name']); ?>
                        </span>
                        <?php if ($log['record_id']): ?>
                            <span class="text-secondary me-3" title="ID Record yang terpengaruh">
                                <i class="bi bi-hash me-1"></i> ID #<?php echo htmlspecialchars($log['record_id']); ?>
                            </span>
                        <?php endif; ?>
                        <span class="text-secondary" title="Alamat IP">
                            <i class="bi bi-hdd-network-fill me-1"></i> IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                        </span>
                    </p>
                </div>
                <div class="text-end ms-3">
                    <small class="text-success d-block fw-semibold">
                        <?php echo date('d M Y', strtotime($log['created_at'])); ?>
                    </small>
                    <small class="text-muted d-block">
                        <?php echo date('H:i:s', strtotime($log['created_at'])); ?> WIB
                    </small>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="card-footer bg-white">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo get_pagination_link($current_page - 1, $search_term, $filter_date); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php 
                    // Tampilkan hanya sekitar 5 halaman di sekitar halaman saat ini
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="'. get_pagination_link(1, $search_term, $filter_date) .'">1</a></li>'; }
                    if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo get_pagination_link($i, $search_term, $filter_date); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php 
                    if ($end_page < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    if ($end_page < $total_pages) { echo '<li class="page-item"><a class="page-link" href="'. get_pagination_link($total_pages, $search_term, $filter_date) .'">' . $total_pages . '</a></li>'; }
                    ?>

                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo get_pagination_link($current_page + 1, $search_term, $filter_date); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<style>
/* CSS Tambahan untuk tampilan log yang lebih halus */
.log-item:hover {
    background-color: #f5f5f5;
    transition: background-color 0.3s ease;
}
.badge-subtle {
    padding: 0.4em 0.6em;
    border-radius: 5px;
}
.bg-success-subtle { background-color: #d1e7dd; }
.bg-primary-subtle { background-color: #cfe2ff; }
.bg-danger-subtle { background-color: #f8d7da; }
.bg-info-subtle { background-color: #cff4fc; }
.bg-secondary-subtle { background-color: #e2e3e5; }
.bg-dark-subtle { background-color: #d3d3d4; }
</style>