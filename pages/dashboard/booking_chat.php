<?php
// File: pages/dashboard/booking_chat.php
// Halaman untuk chat Provider dengan Client terkait pemesanan spesifik.

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['booking_id'] ?? null;
$messages = [];
$booking_info = null;
$error = null;

// Ambil pesan dari session (setelah submit)
$message_alert = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

if (empty($booking_id)) {
    $error = "ID Pemesanan tidak ditemukan.";
}

try {
    if (!$error) {
        // 1. Validasi Pemesanan: Pastikan pemesanan ini milik Provider yang sedang login
        $stmt_booking = $conn->prepare("SELECT 
            b.id, b.total_price, t.title AS trip_title, p.user_id AS provider_user_id
            FROM bookings b
            JOIN trips t ON b.trip_id = t.id
            JOIN providers p ON t.provider_id = p.id
            WHERE b.id = ? AND p.user_id = ?");

        // booking_id dan user_id adalah BIGINT UNSIGNED, kita gunakan 'ss' untuk bind
        $stmt_booking->bind_param("ss", $booking_id, $user_id);
        $stmt_booking->execute();
        $result_booking = $stmt_booking->get_result();
        
        if ($result_booking->num_rows === 0) {
            $error = "Pemesanan tidak ditemukan atau Anda tidak berhak mengakses chat ini.";
        } else {
            $booking_info = $result_booking->fetch_assoc();
            
            // 2. Ambil Riwayat Pesan
            $stmt_messages = $conn->prepare("SELECT 
                bm.sender_role, bm.message, bm.file_path, bm.file_mime_type, bm.sent_at, u.name AS sender_name
                FROM booking_messages bm
                JOIN users u ON bm.sender_id = u.id
                WHERE bm.booking_id = ?
                ORDER BY bm.sent_at ASC");
                
            $stmt_messages->bind_param("s", $booking_id);
            $stmt_messages->execute();
            $messages = $stmt_messages->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_messages->close();
            
            // 3. (Opsional) Update status pesan Client menjadi 'dibaca'
            // Kita tidak perlu melakukannya di sini, biarkan Admin/Provider menandai dibaca jika diperlukan.
        }
        $stmt_booking->close();
    }
    
} catch (Exception $e) {
    $error = "Gagal memuat data chat: " . $e->getMessage();
}

?>

<h1 class="mb-4">Chat Pemesanan: <?php echo htmlspecialchars($booking_info['trip_title'] ?? 'N/A'); ?></h1>
<p class="text-muted">ID Pemesanan: #<?php echo htmlspecialchars($booking_id); ?></p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message_alert): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message_alert); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-5">
    <div class="card-body p-0" style="height: 500px; overflow-y: auto; background-color: #f7f7f7;" id="chat-box">
        
        <?php if (empty($messages)): ?>
            <div class="text-center p-5 text-muted">
                <i class="bi bi-chat-left-dots display-1"></i>
                <p class="mt-3 fs-5">Belum ada pesan dalam pemesanan ini. Mulai obrolan dengan mengirim pesan pertama.</p>
            </div>
        <?php else: ?>
            <div class="p-3">
                <?php foreach ($messages as $msg): ?>
                    <?php
                        $is_provider = $msg['sender_role'] === 'provider';
                        $bg_class = $is_provider ? 'bg-success text-white' : 'bg-info text-dark';
                        $align_class = $is_provider ? 'ms-auto' : 'me-auto';
                        $sender_name = $is_provider ? 'Anda (Provider)' : htmlspecialchars($msg['sender_name']) . ' (Client)';
                    ?>
                    <div class="d-flex mb-3 <?php echo $align_class; ?>" style="max-width: 80%;">
                        <div class="card <?php echo $bg_class; ?> p-2 shadow-sm" style="border-radius: 15px;">
                            <small class="fw-bold mb-1 <?php echo $is_provider ? 'text-white-50' : 'text-dark-50'; ?>">
                                <?php echo $sender_name; ?>
                            </small>
                            
                            <?php if ($msg['file_path']): ?>
                                <div class="file-attachment mb-1">
                                    <i class="bi bi-file-earmark-<?php echo strpos($msg['file_mime_type'], 'pdf') !== false ? 'pdf' : 'image'; ?> me-1"></i>
                                    <a href="/uploads/<?php echo htmlspecialchars($msg['file_path']); ?>" target="_blank" class="text-decoration-underline <?php echo $is_provider ? 'text-white' : 'text-dark'; ?>">
                                        Lihat File (<?php echo strtoupper(pathinfo($msg['file_path'], PATHINFO_EXTENSION)); ?>)
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($msg['message']): ?>
                                <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($msg['message']); ?></p>
                            <?php endif; ?>

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
        <form action="/process/booking_chat_process" method="POST" enctype="multipart/form-data" id="chat-form">
            <input type="hidden" name="action" value="send_message">
            <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking_id); ?>">
            <input type="hidden" name="sender_id" value="<?php echo htmlspecialchars($user_id); ?>">
            <input type="hidden" name="sender_role" value="provider">
            
            <div class="mb-2">
                <label for="file_upload" class="form-label small">Unggah File (Max 5MB, Gambar/PDF):</label>
                <input class="form-control form-control-sm" type="file" id="file_upload" name="file_upload" accept="image/*,application/pdf">
            </div>

            <div class="input-group">
                <textarea class="form-control" id="message_text" name="message_text" rows="2" 
                    placeholder="Ketik pesan Anda di sini... (atau kirim file tanpa teks)"></textarea>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-send-fill"></i> Kirim
                </button>
            </div>
            <small class="text-muted mt-2 d-block">Pesan atau file harus diisi salah satu (atau keduanya).</small>
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