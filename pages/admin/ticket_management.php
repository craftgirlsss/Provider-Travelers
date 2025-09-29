<?php
// File: pages/admin/ticket_management.php
// Halaman untuk Super Admin melihat dan merespons tiket Provider

// Cek Otorisasi Admin
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$tickets = [];
$error = null;

// Ambil pesan dari session (setelah merespons)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

try {
    // Ambil semua tiket, JOIN dengan tabel providers dan users
    $sql = "SELECT 
                t.id, t.subject, t.message, t.status, t.priority, t.created_at, t.admin_response, 
                p.company_name, 
                u.name AS user_name,
                p.verification_status 
            FROM provider_tickets t
            JOIN providers p ON t.provider_id = p.id
            JOIN users u ON p.user_id = u.id
            ORDER BY FIELD(t.status, 'open', 'in_progress', 'closed'), t.priority DESC, t.created_at ASC";
            
    $result = $conn->query($sql);
    
    if ($result) {
        $tickets = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Gagal memuat data tiket: " . $conn->error;
    }
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan sistem: " . $e->getMessage();
}

// Fungsi Pembantu untuk Badge Status
function get_status_badge_admin($status) {
    switch ($status) {
        case 'open': return '<span class="badge bg-danger">⏳ Baru/Open</span>';
        case 'in_progress': return '<span class="badge bg-info text-dark">⚙️ Diproses</span>';
        case 'closed': return '<span class="badge bg-success">✅ Selesai</span>';
        default: return '<span class="badge bg-secondary">N/A</span>';
    }
}
function get_priority_badge_admin($priority) {
    switch ($priority) {
        case 'high': return '<span class="badge bg-danger fw-bold">TINGGI</span>';
        case 'medium': return '<span class="badge bg-warning text-dark">Sedang</span>';
        case 'low': 
        default: return '<span class="badge bg-secondary">Rendah</span>';
    }
}
function get_verification_badge_admin($status) {
    switch ($status) {
        case 'verified': return '<span class="badge bg-success">Verified</span>';
        case 'pending': return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'rejected': return '<span class="badge bg-danger">Rejected</span>';
        case 'unverified':
        default: return '<span class="badge bg-secondary">Unverified</span>';
    }
}
?>

<h1 class="mb-4">Manajemen Tiket Keluhan Provider</h1>
<p class="text-muted">Daftar semua permintaan dukungan dan keluhan dari mitra Provider.</p>

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
        <?php if (empty($tickets)): ?>
            <div class="alert alert-info text-center m-0">
                Tidak ada tiket dukungan yang aktif saat ini.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Subjek</th>
                            <th>Provider (Perusahaan)</th>
                            <th>Diajukan</th>
                            <th>Status Verifikasi</th>
                            <th>Prioritas</th>
                            <th>Status Tiket</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>#<?php echo $ticket['id']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($ticket['company_name']); ?></strong><br>
                                <small class="text-muted">(<?php echo htmlspecialchars($ticket['user_name']); ?>)</small>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($ticket['created_at'])); ?></td>
                            <td><?php echo get_verification_badge_admin($ticket['verification_status']); ?></td>
                            <td><?php echo get_priority_badge_admin($ticket['priority']); ?></td>
                            <td><?php echo get_status_badge_admin($ticket['status']); ?></td>
                            <td class="text-center">
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#respondTicketModal"
                                    data-ticket-id="<?php echo $ticket['id']; ?>"           
                                    data-ticket-subject="<?php echo htmlspecialchars($ticket['subject']); ?>" 
                                    data-ticket-message="<?php echo htmlspecialchars($ticket['message']); ?>"
                                    data-ticket-response="<?php echo htmlspecialchars($ticket['admin_response'] ?? ''); ?>"
                                    data-ticket-status="<?php echo htmlspecialchars($ticket['status']); ?>"
                                >
                                    Respons
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="respondTicketModal" tabindex="-1" aria-labelledby="respondTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="respondTicketModalLabel">Respons Tiket #<span id="modalTicketId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/process/admin_process" method="POST">
                <input type="hidden" name="action" value="respond_ticket">
                <input type="hidden" name="ticket_id" id="modalTicketIdInput">
                <div class="modal-body">
                    <h6>Subjek: <span id="modalSubject" class="fw-bold"></span></h6>
                    
                    <h6 class="mt-3">Pesan Provider:</h6>
                    <div class="alert alert-light border p-3" style="white-space: pre-wrap;" id="modalMessage"></div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label for="admin_response" class="form-label">Tulis Respons Admin (*)</label>
                        <textarea class="form-control" id="admin_response" name="admin_response" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Ubah Status Tiket (*)</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="open">Open (Biarkan Terbuka)</option>
                            <option value="in_progress">In Progress (Sedang Dikerjakan)</option>
                            <option value="closed">Closed (Masalah Teratasi)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-reply me-2"></i> Kirim Respons & Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Logic JavaScript/jQuery untuk mengisi data ke modal
document.addEventListener('DOMContentLoaded', function() {
    var respondTicketModal = document.getElementById('respondTicketModal');
    
    if (respondTicketModal) {
        respondTicketModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; 
            var ticketId = button.getAttribute('data-ticket-id');
            var subject = button.getAttribute('data-ticket-subject');
            var message = button.getAttribute('data-ticket-message');
            var response = button.getAttribute('data-ticket-response');
            var status = button.getAttribute('data-ticket-status');

            document.getElementById('modalTicketId').textContent = ticketId;
            document.getElementById('modalTicketIdInput').value = ticketId;
            document.getElementById('modalSubject').textContent = subject;
            document.getElementById('modalMessage').textContent = message; //textContent agar baris baru tetap terlihat
            document.getElementById('admin_response').value = response; // Isi dengan respons lama jika ada

            // Set status yang sedang berlaku
            document.getElementById('new_status').value = status;
        });
    }
});
</script>