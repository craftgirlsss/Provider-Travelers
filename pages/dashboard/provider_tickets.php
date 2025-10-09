<?php
// File: pages/dashboard/provider_tickets.php
// Halaman Chat Dukungan untuk Provider, menampilkan chat aktif atau formulir buat baru, 
// dan riwayat tiket yang sudah ditutup.

// =======================================================================
// KOREKSI OTORISASI & INISIALISASI
// Panggil variabel dari scope dashboard.php
// =======================================================================
global $conn, $user_id_from_session, $actual_provider_id; 

// Gunakan ID integer yang sudah diolah oleh dashboard.php
$user_id = $user_id_from_session;
$provider_id = $actual_provider_id; 

$active_ticket = null;
$messages = [];
$closed_tickets = [];
$error = null;

// Ambil pesan dari session (setelah proses create/send message)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);


// Cek otorisasi dasar (menggantikan $_SESSION['user_id'])
if (!$user_id) {
    $error = "Kesalahan Otorisasi: ID Pengguna tidak ditemukan. Harap login ulang.";
    goto display_page;
}

// =======================================================================
// LOGIC PENGAMBILAN DATA (MENGGUNAKAN $user_id & $provider_id YANG SUDAH KOREK)
// =======================================================================
try {
    // KOREKSI: FALLBACK Ambil provider_id dari user_id, jika global scope $actual_provider_id kosong
    if (!$provider_id) {
        $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
        // KOREKSI BINDING TIPE: user_id adalah BIGINT/INT, gunakan "i" (bukan "s")
        $stmt_provider->bind_param("i", $user_id);
        $stmt_provider->execute();
        $result_provider = $stmt_provider->get_result();
        if ($row = $result_provider->fetch_assoc()) {
            $provider_id = $row['id'];
        } else {
            $error = "Data Provider tidak ditemukan.";
            goto display_page;
        }
        $stmt_provider->close();
    }

    // 1. QUERY TIKET AKTIF (HANYA 'OPEN')
    $stmt_ticket = $conn->prepare("SELECT 
        t.id, t.subject, t.status, t.created_at
        FROM provider_tickets_new t
        WHERE t.provider_id = ? 
        AND t.status = 'open' 
        ORDER BY t.created_at DESC 
        LIMIT 1");

    // KOREKSI BINDING TIPE: provider_id adalah BIGINT/INT, gunakan "i"
    $stmt_ticket->bind_param("i", $provider_id);
    $stmt_ticket->execute();
    $result_ticket = $stmt_ticket->get_result();
    
    if ($result_ticket->num_rows > 0) {
        $active_ticket = $result_ticket->fetch_assoc();
        
        // Ambil semua pesan terkait tiket aktif
        $stmt_messages = $conn->prepare("SELECT 
            tm.message, tm.sender_role, tm.sent_at
            FROM ticket_messages tm
            WHERE tm.ticket_id = ?
            ORDER BY tm.sent_at ASC");
        
        // KOREKSI BINDING TIPE: active_ticket['id'] adalah BIGINT/INT, gunakan "i"
        $stmt_messages->bind_param("i", $active_ticket['id']);
        $stmt_messages->execute();
        $messages = $stmt_messages->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_messages->close();
        
        $active_ticket['admin_status'] = "Admin"; 

    }
    $stmt_ticket->close();

    // 2. QUERY RIWAYAT TIKET TERTUTUP (Tambahan Baru)
    $stmt_closed = $conn->prepare("SELECT 
        id, subject, status, created_at, last_updated 
        FROM provider_tickets_new 
        WHERE provider_id = ? AND status = 'closed'
        ORDER BY last_updated DESC");

    // KOREKSI BINDING TIPE: provider_id adalah BIGINT/INT, gunakan "i"
    $stmt_closed->bind_param("i", $provider_id);
    $stmt_closed->execute();
    $closed_tickets = $stmt_closed->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_closed->close();
    
} catch (Exception $e) {
    $error = "Gagal memuat data: " . $e->getMessage();
}

display_page:
?>

<h1 class="mb-4">Chat Dukungan & Keluhan</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($active_ticket): ?>
    <div class="alert alert-info shadow-sm">
        <h5 class="alert-heading">Tiket Aktif: #<?php echo $active_ticket['id'] . ' - ' . htmlspecialchars($active_ticket['subject']); ?></h5>
        <p class="mb-0">Status: <strong><?php echo ucfirst($active_ticket['status']); ?></strong> | Ditangani oleh: **<?php echo htmlspecialchars($active_ticket['admin_status']); ?>**</p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-0" style="height: 400px; overflow-y: auto; background-color: #f7f7f7;">
            <div class="p-3">
                <?php if (empty($messages)): ?>
                    <div class="text-center p-5 text-muted">
                        <i class="bi bi-ticket-detailed display-1"></i>
                        <p class="mt-3 fs-5">Tiket sudah dibuat. Silakan kirim pesan Anda!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php
                            $is_provider = ($msg['sender_role'] === 'provider');
                            $bg_class = $is_provider ? 'bg-primary text-white' : 'bg-light text-dark border';
                            $align_class = $is_provider ? 'ms-auto' : 'me-auto';
                            $sender_name = $is_provider ? 'Anda' : 'Admin';
                        ?>
                        <div class="d-flex mb-3 <?php echo $align_class; ?>" style="max-width: 80%;">
                            <div class="card <?php echo $bg_class; ?> p-2 shadow-sm" style="border-radius: 15px;">
                                <small class="fw-bold mb-1 <?php echo $is_provider ? 'text-white-50' : 'text-muted'; ?>">
                                    <?php echo $sender_name; ?>
                                </small>
                                <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($msg['message']); ?></p>
                                <small class="text-end mt-1 <?php echo $is_provider ? 'text-white-50' : 'text-muted'; ?>" style="font-size: 0.75em;">
                                    <?php echo date('H:i', strtotime($msg['sent_at'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-footer bg-white">
            <form action="/process/ticket_process" method="POST">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                <input type="hidden" name="sender_role" value="provider"> 
                <div class="input-group">
                    <textarea class="form-control" name="message_text" rows="2" required 
                        placeholder="Balas pesan atau berikan informasi tambahan..."></textarea>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send-fill"></i> Kirim Balasan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
<?php else: ?>
    <div class="alert alert-success text-center py-5 shadow-sm">
        <i class="bi bi-ticket-fill display-4"></i>
        <h4 class="mt-3">Semua tiket dukungan Anda saat ini sudah ditutup.</h4>
        <p class="fs-5">Silakan buat tiket baru di bawah ini untuk memulai obrolan baru dengan Admin.</p>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Buat Tiket Dukungan Baru</h5>
        </div>
        <div class="card-body">
            <form action="/process/ticket_process" method="POST">
                <input type="hidden" name="action" value="create_ticket"> 
                <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id); ?>">
                
                <div class="mb-3">
                    <label for="subject" class="form-label">Subjek Keluhan/Dukungan (*)</label>
                    <input type="text" class="form-control" id="subject" name="subject" required 
                           placeholder="Contoh: Trip XYZ belum disetujui / Error saat upload gambar">
                </div>
                
                <div class="mb-3">
                    <label for="initial_message" class="form-label">Pesan Awal/Deskripsi Masalah (*)</label>
                    <textarea class="form-control" id="initial_message" name="initial_message" rows="4" required 
                              placeholder="Jelaskan masalah Anda secara detail..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle-fill me-2"></i> Buat Tiket Baru
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<h2 class="mt-5 mb-3">Riwayat Tiket Dukungan (Ditutup)</h2>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($closed_tickets)): ?>
            <div class="alert alert-secondary m-0 text-center">
                Belum ada tiket dukungan yang sudah diselesaikan dan ditutup.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID Tiket</th>
                            <th>Subjek</th>
                            <th>Dibuat</th>
                            <th>Ditutup</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($closed_tickets as $ticket): ?>
                        <tr>
                            <td>#<?php echo $ticket['id']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td><?php echo date('d M Y', strtotime($ticket['created_at'])); ?></td>
                            <td><?php echo date('d M Y', strtotime($ticket['last_updated'])); ?></td>
                            <td><span class="badge bg-danger"><?php echo ucfirst($ticket['status']); ?></span></td>
                            <td>
                                <!-- Tombol ini memicu Modal dengan ID unik -->
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#ticketModal<?php echo $ticket['id']; ?>">
                                    <i class="bi bi-eye"></i> Lihat Riwayat
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ======================================================= -->
            <!-- TAMBAHAN: LOOP UNTUK MEMBUAT MODAL DETAIL RIWAYAT CHAT -->
            <!-- ======================================================= -->
            <?php foreach ($closed_tickets as $ticket): ?>
            <?php
                // AMBIL PESAN KHUSUS UNTUK TIKET INI
                $ticket_messages = [];
                try {
                    $stmt_modal_messages = $conn->prepare("SELECT 
                        tm.message, tm.sender_role, tm.sent_at
                        FROM ticket_messages tm
                        WHERE tm.ticket_id = ?
                        ORDER BY tm.sent_at ASC");
                    
                    // KOREKSI BINDING TIPE: ticket_id adalah BIGINT/INT, gunakan "i"
                    $stmt_modal_messages->bind_param("i", $ticket['id']);
                    $stmt_modal_messages->execute();
                    $ticket_messages = $stmt_modal_messages->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_modal_messages->close();
                } catch (Exception $e) {
                    // Jika gagal, biarkan $ticket_messages kosong
                }
            ?>
            
            <div class="modal fade" id="ticketModal<?php echo $ticket['id']; ?>" tabindex="-1" aria-labelledby="ticketModalLabel<?php echo $ticket['id']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="ticketModalLabel<?php echo $ticket['id']; ?>">
                                Riwayat Tiket #<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="height: 50vh; overflow-y: auto;">
                            <?php if (empty($ticket_messages)): ?>
                                <div class="alert alert-warning text-center">Riwayat pesan tidak ditemukan untuk tiket ini.</div>
                            <?php else: ?>
                                <?php foreach ($ticket_messages as $msg): ?>
                                    <?php
                                        $is_provider = ($msg['sender_role'] === 'provider');
                                        $bg_class = $is_provider ? 'bg-primary text-white' : 'bg-light text-dark border';
                                        $align_class = $is_provider ? 'ms-auto' : 'me-auto';
                                        $sender_name = $is_provider ? 'Anda (Provider)' : 'Admin';
                                    ?>
                                    <div class="d-flex mb-3 <?php echo $align_class; ?>" style="max-width: 90%;">
                                        <div class="card <?php echo $bg_class; ?> p-2 shadow-sm" style="border-radius: 10px;">
                                            <small class="fw-bold mb-1 <?php echo $is_provider ? 'text-white-50' : 'text-muted'; ?>">
                                                <?php echo $sender_name; ?>
                                            </small>
                                            <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($msg['message']); ?></p>
                                            <small class="text-end mt-1 <?php echo $is_provider ? 'text-white-50' : 'text-muted'; ?>" style="font-size: 0.75em;">
                                                <?php echo date('H:i', strtotime($msg['sent_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>
