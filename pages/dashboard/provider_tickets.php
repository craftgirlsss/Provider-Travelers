<?php
// File: pages/dashboard/provider_tickets_new.php (Antarmuka Chat/Dukungan Provider)

$user_id_from_session = $_SESSION['user_id'];
$actual_provider_id = null;
$current_ticket = null;
$messages = [];
$error = null;

// Ambil pesan dari session (setelah submit)
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

try {
    // 1. Ambil ID Provider
    $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("i", $user_id_from_session);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    
    if ($result_provider->num_rows > 0) {
        $row = $result_provider->fetch_assoc();
        $actual_provider_id = $row['id']; 
    }
    $stmt_provider->close();

    if (!$actual_provider_id) {
        $error = "Data provider tidak ditemukan.";
    } else {
        
        // 2. Cari atau Buat Ticket (Thread)
        $stmt_ticket = $conn->prepare("SELECT id, subject, status, created_at FROM provider_tickets_new WHERE provider_id = ?");
        $stmt_ticket->bind_param("s", $actual_provider_id); // provider_id adalah BIGINT UNSIGNED, bind_param bisa pakai 's'
        $stmt_ticket->execute();
        $result_ticket = $stmt_ticket->get_result();
        
        if ($result_ticket->num_rows > 0) {
            $current_ticket = $result_ticket->fetch_assoc();
            
            // 3. Ambil Riwayat Pesan (jika tiket sudah ada)
            $stmt_messages = $conn->prepare("SELECT sender_role, message, sent_at FROM ticket_messages WHERE ticket_id = ? ORDER BY sent_at ASC");
            $stmt_messages->bind_param("s", $current_ticket['id']); // ticket_id adalah BIGINT UNSIGNED
            $stmt_messages->execute();
            $messages = $stmt_messages->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_messages->close();

        } else {
            // Jika belum ada tiket, Provider harus membuat subjek pertama kali
            $current_ticket = ['id' => null, 'subject' => 'Tiket Baru', 'status' => 'open'];
        }
        $stmt_ticket->close();
    }
    
} catch (Exception $e) {
    $error = "Gagal memuat data: " . $e->getMessage();
}

/**
 * Fungsi Pembantu untuk Badge Status
 */
function get_status_badge_chat($status) {
    if ($status === 'open') {
        return '<span class="badge bg-success">Aktif</span>';
    } else {
        return '<span class="badge bg-secondary">Tutup</span>';
    }
}
?>

<h1 class="mb-4">Chat Dukungan & Keluhan</h1>
<p class="text-muted">Komunikasi langsung dengan Super Admin terkait verifikasi, masalah sistem, atau keluhan lainnya.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-5">
    <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
        <h5>
            Tiket ID: <?php echo $current_ticket['id'] ? '#' . htmlspecialchars($current_ticket['id']) : 'Belum Ada'; ?>
        </h5>
        <div>
            Status: <?php echo get_status_badge_chat($current_ticket['status']); ?>
        </div>
    </div>
    <div class="card-body p-0" style="height: 500px; overflow-y: auto; background-color: #f7f7f7;" id="chat-box">
        
        <?php if (empty($messages) && $current_ticket['id'] === null): ?>
            <div class="text-center p-5 text-muted">
                <i class="bi bi-chat-left-dots display-1"></i>
                <p class="mt-3 fs-5">Mulai obrolan baru dengan mengirim pesan pertama Anda.</p>
            </div>
        <?php else: ?>
            <div class="p-3">
                <div class="text-center text-muted small mb-3">
                    Tiket dibuka pada: <?php echo date('d M Y H:i', strtotime($current_ticket['created_at'])); ?>
                </div>
                <?php foreach ($messages as $msg): ?>
                    <?php
                        $is_provider = $msg['sender_role'] === 'provider';
                        $bg_class = $is_provider ? 'bg-primary text-white' : 'bg-light border';
                        $align_class = $is_provider ? 'ms-auto' : 'me-auto';
                        $sender_name = $is_provider ? 'Anda' : 'Admin';
                    ?>
                    <div class="d-flex mb-3 <?php echo $align_class; ?>" style="max-width: 80%;">
                        <div class="card <?php echo $bg_class; ?> p-2 shadow-sm" style="border-radius: 15px;">
                            <small class="fw-bold mb-1 <?php echo $is_provider ? 'text-white-50' : 'text-primary'; ?>">
                                <?php echo $sender_name; ?>
                            </small>
                            <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($msg['message']); ?></p>
                            <small class="text-end mt-1 <?php echo $is_provider ? 'text-white-50' : 'text-muted'; ?>" style="font-size: 0.75em;">
                                <?php echo date('H:i', strtotime($msg['sent_at'])); ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>

    <div class="card-footer bg-white">
        <form action="/process/ticket_process" method="POST" id="chat-form">
            <input type="hidden" name="action" value="<?php echo $current_ticket['id'] ? 'send_message' : 'create_and_send'; ?>">
            <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($actual_provider_id); ?>">
            <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($current_ticket['id'] ?? ''); ?>">
            
            <?php if ($current_ticket['id'] === null): ?>
                <div class="mb-2">
                    <label for="initial_subject" class="form-label small">Subjek Tiket Awal (*)</label>
                    <input type="text" class="form-control" id="initial_subject" name="initial_subject" required 
                        placeholder="Contoh: Minta Konfirmasi Verifikasi Akun">
                </div>
            <?php endif; ?>

            <div class="input-group">
                <textarea class="form-control" id="message_text" name="message_text" rows="2" 
                    placeholder="<?php echo $current_ticket['status'] === 'closed' ? 'Tiket ini sudah ditutup.' : 'Ketik pesan Anda di sini...'; ?>" 
                    <?php echo $current_ticket['status'] === 'closed' ? 'disabled' : 'required'; ?>></textarea>
                <button type="submit" class="btn btn-primary" 
                    <?php echo $current_ticket['status'] === 'closed' ? 'disabled' : ''; ?>>
                    <i class="bi bi-send-fill"></i> Kirim
                </button>
            </div>
            <?php if ($current_ticket['status'] === 'closed'): ?>
                <small class="text-danger mt-2 d-block">Tiket ini telah ditutup oleh Admin. Anda tidak dapat mengirim pesan lagi.</small>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
// Auto-scroll ke bawah saat dimuat
document.addEventListener('DOMContentLoaded', function() {
    var chatBox = document.getElementById('chat-box');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
});
</script>