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
$actual_provider_id = null; 

// ==========================================================
// --- FUNGSI PEMBANTU ---
// ==========================================================
/**
 * Fungsi dummy untuk generate nomor invoice unik.
 */
function generateUniqueInvoiceNumber($conn) {
    $prefix = "INV/" . date("Y") . "/";
    $result = $conn->query("SELECT COUNT(id) AS total FROM bookings");
    $row = $result->fetch_assoc();
    $sequence = $row['total'] + 1;
    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Ambil Provider ID
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
    // Error handling provider ID
}

if (!$actual_provider_id) {
    $_SESSION['dashboard_message'] = "Error Otorisasi: Data provider tidak ditemukan.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=bookings");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $redirect_to = "/dashboard?p=booking_detail&id=$booking_id";


    // ==========================================================
    // --- AKSI APPROVE PEMBAYARAN OLEH PROVIDER ---
    // ==========================================================
    if ($action === 'confirm_payment_and_invoice') { // Nama aksi harus disesuaikan dengan form di booking_detail.php
        
        if ($booking_id <= 0) {
            $message = "ID Pemesanan tidak valid.";
            goto end_process;
        }

        $conn->begin_transaction();
        try {
            // 1. Verifikasi kepemilikan booking dan status saat ini
            $stmt_check = $conn->prepare("
                SELECT 
                    b.status, b.user_id, u.email AS client_email, u.name AS client_name,
                    b.total_price, b.amount_paid, b.discount_amount, b.num_of_people
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN users u ON b.user_id = u.id
                WHERE b.id = ? AND t.provider_id = ? AND b.status = 'waiting_confirmation'
                FOR UPDATE
            ");
            $stmt_check->bind_param("ii", $booking_id, $actual_provider_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                throw new Exception("Pemesanan tidak ditemukan, bukan milik Anda, atau statusnya sudah tidak 'waiting_confirmation'.");
            }
            $booking_info = $result_check->fetch_assoc();
            $stmt_check->close();

            // Cek Harga Aktual vs Dibayar
            $price_after_discount_per_person = $booking_info['total_price'] - $booking_info['discount_amount'];
            $actual_price = $booking_info['num_of_people'] * $price_after_discount_per_person;
            $amount_paid = $booking_info['amount_paid'];

            if ($amount_paid < $actual_price) {
                throw new Exception("Jumlah yang dibayar Client (Rp " . number_format($amount_paid, 0, ',', '.') . ") kurang dari harga aktual (Rp " . number_format($actual_price, 0, ',', '.') . "). Tolak pembayaran ini.");
            }

            // 2. Generate Invoice & Update status pembayaran menjadi PAID
            $new_invoice_number = generateUniqueInvoiceNumber($conn);
            $paid_at = date('Y-m-d H:i:s');

            $stmt_update = $conn->prepare("
                UPDATE bookings 
                SET status = 'paid', invoice_number = ?, payment_confirmation_at = ?, admin_verification_note = NULL
                WHERE id = ?
            ");
            $stmt_update->bind_param("ssi", $new_invoice_number, $paid_at, $booking_id);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Gagal mengupdate status pembayaran.");
            }
            $stmt_update->close();
            
            // 3. Update/Insert Payment Record di tabel payments
            $stmt_check_payment = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
            $stmt_check_payment->bind_param("i", $booking_id);
            $stmt_check_payment->execute();
            $result_payment = $stmt_check_payment->get_result();
            $stmt_check_payment->close();

            if ($result_payment->num_rows > 0) {
                // UPDATE: Jika sudah ada record
                $stmt_update_payment = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = ? WHERE booking_id = ?");
                $stmt_update_payment->bind_param("si", $paid_at, $booking_id);
                $stmt_update_payment->execute();
                $stmt_update_payment->close();
            } else {
                // INSERT: Buat record payments baru
                $stmt_insert_payment = $conn->prepare("INSERT INTO payments (booking_id, amount, method, status, paid_at, uuid) 
                                                      VALUES (?, ?, 'Transfer Bank (Konfirmasi Admin)', 'paid', ?, UUID())");
                // Gunakan amount_paid yang diinput client
                $stmt_insert_payment->bind_param("ids", $booking_id, $amount_paid, $paid_at); 
                $stmt_insert_payment->execute();
                $stmt_insert_payment->close();
            }
            
            $conn->commit();
            
            // 4. Kirim Email Notifikasi ke Client
            $client_email = $booking_info['client_email'];
            $client_name = $booking_info['client_name'];
            $subject = "Pembayaran Pemesanan #$booking_id Berhasil Dikonfirmasi ($new_invoice_number)";
            $body = "Yth. $client_name,<br><br>"
                    . "Pembayaran Anda untuk pemesanan trip #$booking_id telah berhasil dikonfirmasi oleh Provider.<br>"
                    . "Status pemesanan Anda kini **PAID**. Invoice Anda: **$new_invoice_number**.<br><br>"
                    . "Salam,<br>Tim Travel App";
            
            if (function_exists('send_email_notification')) {
                send_email_notification($client_email, $subject, $body);
            }
            
            $message = "Pembayaran untuk Pemesanan #$booking_id berhasil dikonfirmasi dan Invoice diterbitkan.";
            $message_type = "success";


        } catch (Exception $e) {
            $conn->rollback();
            $message = "Konfirmasi gagal: " . $e->getMessage();
        }
    } 
    
    // ==========================================================
    // --- AKSI DECLINE PEMBAYARAN OLEH PROVIDER ---
    // ==========================================================
    elseif ($action === 'decline_payment') {
        
        $admin_note = trim($_POST['admin_verification_note'] ?? '');

        if ($booking_id <= 0 || empty($admin_note)) {
            $message = "ID Pemesanan tidak valid atau Catatan Penolakan wajib diisi.";
            goto end_process;
        }

        $conn->begin_transaction();
        try {
            // 1. Verifikasi kepemilikan booking dan status saat ini
            $stmt_check = $conn->prepare("
                SELECT b.status, u.email AS client_email, u.name AS client_name
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN users u ON b.user_id = u.id
                WHERE b.id = ? AND t.provider_id = ? AND b.status = 'waiting_confirmation'
                FOR UPDATE
            ");
            $stmt_check->bind_param("ii", $booking_id, $actual_provider_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                throw new Exception("Pemesanan tidak ditemukan, bukan milik Anda, atau statusnya sudah tidak 'waiting_confirmation'.");
            }
            $booking_info = $result_check->fetch_assoc();
            $stmt_check->close();
            
            $paid_at_null = NULL;
            // 2. Update status kembali ke 'unpaid', tambahkan catatan admin, dan HAPUS bukti/notes client
            $stmt_update = $conn->prepare("
                UPDATE bookings 
                SET 
                    status = 'unpaid', 
                    admin_verification_note = ?, 
                    payment_confirmation_at = ?,
                    proof_of_payment_path = NULL, 
                    payment_notes = NULL
                WHERE id = ?
            ");
            $stmt_update->bind_param("ssi", $admin_note, $paid_at_null, $booking_id);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Gagal mengupdate status penolakan pembayaran.");
            }
            $stmt_update->close();
            
            // Opsional: Update status payment di tabel payments (misal: 'rejected')
            // ...

            $conn->commit();
            
            // 3. Kirim Email Notifikasi ke Client
            $client_email = $booking_info['client_email'];
            $client_name = $booking_info['client_name'];
            $subject = "Pembayaran Pemesanan #$booking_id DITOLAK";
            $body = "Yth. $client_name,<br><br>"
                    . "Bukti pembayaran Anda untuk pemesanan trip #$booking_id **DITOLAK** oleh Provider.<br>"
                    . "Status pemesanan Anda kini **UNPAID**.<br>"
                    . "Alasan Penolakan: **" . htmlspecialchars($admin_note) . "**<br><br>"
                    . "Harap segera mengunggah bukti transfer yang valid.<br><br>"
                    . "Salam,<br>Tim Travel App";
            
            if (function_exists('send_email_notification')) {
                send_email_notification($client_email, $subject, $body);
            }
            
            $message = "Pembayaran Pemesanan #$booking_id berhasil DITOLAK. Status diubah menjadi UNPAID.";
            $message_type = "info";


        } catch (Exception $e) {
            $conn->rollback();
            $message = "Penolakan gagal: " . $e->getMessage();
        }
    } 
    
    // ... (Aksi POST lainnya, seperti upload bukti oleh client, bisa ditambahkan di sini)
}

end_process:
$_SESSION['dashboard_message'] = $message;
$_SESSION['dashboard_message_type'] = $message_type;
header("Location: " . $redirect_to);
exit();
?>