<?php
// File: process/booking_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/email_config.php'; // Asumsi: Konfigurasi email tersedia

// Cek login & role (Hanya Provider yang bisa konfirmasi)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}

$user_id_from_session = $_SESSION['user_id'];
$redirect_to = '/dashboard?p=bookings'; 
$message = "Gagal memproses permintaan.";
$message_type = "danger";
$actual_provider_id = null; // ID Provider

// Ambil Provider ID (asumsi sudah ada atau ambil dari DB)
// Anda harus memastikan $actual_provider_id diambil di dashboard.php atau di sini.
try {
    $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("i", $user_id_from_session);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    if ($result_provider->num_rows > 0) {
        $actual_provider_id = $result_provider->fetch_assoc()['id']; 
    }
    $stmt_provider->close();
} catch (Exception $e) {
    // Abaikan, akan dicek di bawah
}

if (!$actual_provider_id) {
    $_SESSION['dashboard_message'] = "Error Otorisasi: Data provider tidak ditemukan.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=bookings");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // ==========================================================
    // --- AKSI KONFIRMASI PEMBAYARAN OLEH PROVIDER ---
    // ==========================================================
    if ($action === 'confirm_payment') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);

        if ($booking_id <= 0) {
            $message = "ID Pemesanan tidak valid.";
            goto end_process;
        }

        $conn->begin_transaction();
        try {
            // 1. Verifikasi kepemilikan booking dan status saat ini
            $stmt_check = $conn->prepare("
                SELECT 
                    b.status, b.user_id, u.email AS client_email, u.name AS client_name
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN users u ON b.user_id = u.id
                WHERE b.id = ? AND t.provider_id = ? AND b.status = 'unpaid'
                FOR UPDATE
            ");
            $stmt_check->bind_param("ii", $booking_id, $actual_provider_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                throw new Exception("Pemesanan tidak ditemukan, bukan milik Anda, atau statusnya sudah tidak 'unpaid'.");
            }
            $booking_info = $result_check->fetch_assoc();
            $stmt_check->close();

            // 2. Update status pembayaran menjadi PAID
            // Asumsi: Anda telah menambahkan kolom 'paid_at' di tabel bookings
            $stmt_update = $conn->prepare("
                UPDATE bookings 
                SET status = 'paid', paid_at = NOW() 
                WHERE id = ?
            ");
            $stmt_update->bind_param("i", $booking_id);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Gagal mengupdate status pembayaran.");
            }
            $stmt_update->close();
            
            $conn->commit();
            
            // 3. Kirim Email Notifikasi ke Client (setelah commit)
            
            $client_email = $booking_info['client_email'];
            $client_name = $booking_info['client_name'];
            
            $subject = "Pembayaran Pemesanan #$booking_id Berhasil Dikonfirmasi";
            $body = "Yth. $client_name,<br><br>"
                    . "Pembayaran Anda untuk pemesanan trip #$booking_id telah berhasil dikonfirmasi oleh Provider.<br>"
                    . "Status pemesanan Anda kini **PAID**.<br>"
                    . "Silakan cek detail pemesanan Anda di dashboard. Terima kasih.<br><br>"
                    . "Salam,<br>Tim Travel App";
            
            // Asumsi: Fungsi send_email_notification() tersedia di email_config.php atau library terkait
            if (function_exists('send_email_notification')) {
                send_email_notification($client_email, $subject, $body);
            }
            
            $message = "Pembayaran untuk Pemesanan #$booking_id berhasil dikonfirmasi dan Client telah diemail.";
            $message_type = "success";
            $redirect_to = "/dashboard?p=booking_detail&id=$booking_id";


        } catch (Exception $e) {
            $conn->rollback();
            $message = "Konfirmasi gagal: " . $e->getMessage();
            $redirect_to = "/dashboard?p=booking_detail&id=$booking_id";
        }
    } 
    
    // ==========================================================
    // --- AKSI UPLOAD BUKTI TRANSFER OLEH CLIENT (DIPERLUKAN) ---
    // ==========================================================
    /* Jika Anda ingin menambahkan proses di mana Client meng-upload bukti transfer:
    if ($action === 'upload_proof_by_client') {
        // ... (Logika upload file dan update proof_of_payment_path di tabel bookings)
    }
    */
}

end_process:
$_SESSION['dashboard_message'] = $message;
$_SESSION['dashboard_message_type'] = $message_type;
header("Location: " . $redirect_to);
exit();
?>