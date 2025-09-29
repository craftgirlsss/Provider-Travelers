<?php
// File: process/ticket_process.php (Disesuaikan untuk Chat Dukungan Provider)
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Cek login & role (Hanya Provider)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}

$redirect_page = 'provider_tickets_new'; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST['action'] ?? '';
    $message = "Gagal memproses pesan.";
    $message_type = "danger";
    // provider_id dan ticket_id adalah BIGINT UNSIGNED, kita biarkan sebagai string/null
    $provider_id = $_POST['provider_id'] ?? null; 
    $message_text = trim($_POST['message_text'] ?? '');
    $ticket_id = $_POST['ticket_id'] ?? null; 
    
    if (empty($provider_id) || empty($message_text)) {
        $message = "Pesan tidak boleh kosong.";
        $_SESSION['dashboard_message'] = $message;
        $_SESSION['dashboard_message_type'] = $message_type;
        header("Location: /dashboard?p=" . $redirect_page);
        exit();
    }

    // ==========================================================
    // --- AKSI 1: BUAT TIKET BARU & KIRIM PESAN PERTAMA ---
    // ==========================================================
    if ($action === 'create_and_send') {
        
        $subject = trim($_POST['initial_subject'] ?? '');

        if (empty($subject)) {
            $message = "Subjek awal tiket wajib diisi.";
        } else {
            $conn->begin_transaction();
            try {
                // A. Buat thread tiket baru (provider_id dan subject, keduanya di-bind sebagai string 'ss')
                $stmt_create = $conn->prepare("INSERT INTO provider_tickets_new (provider_id, subject) VALUES (?, ?)");
                $stmt_create->bind_param("ss", $provider_id, $subject); 
                $stmt_create->execute();
                $new_ticket_id = $conn->insert_id; // ID baru dikembalikan sebagai string
                $stmt_create->close();
                
                if (!$new_ticket_id) {
                    throw new Exception("Gagal mendapatkan ID tiket baru.");
                }
                
                // B. Simpan pesan pertama (ticket_id dan message_text, keduanya di-bind sebagai string 'ss')
                $stmt_message = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_role, message) VALUES (?, 'provider', ?)");
                $stmt_message->bind_param("ss", $new_ticket_id, $message_text); 
                $stmt_message->execute();
                $stmt_message->close();

                $conn->commit();
                $message = "Tiket baru berhasil dibuat dan pesan Anda terkirim!";
                $message_type = "success";
                
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Terjadi kesalahan sistem saat membuat tiket: " . $e->getMessage();
            }
        }
    } 
    
    // ==========================================================
    // --- AKSI 2: KIRIM PESAN KE TIKET YANG SUDAH ADA ---
    // ==========================================================
    elseif ($action === 'send_message') {
        
        if (empty($ticket_id)) {
             $message = "ID Tiket tidak valid.";
        } else {
            try {
                // Cek status tiket (pastikan tidak closed)
                $stmt_check = $conn->prepare("SELECT status FROM provider_tickets_new WHERE id = ?");
                $stmt_check->bind_param("s", $ticket_id); // ticket_id (BIGINT UNSIGNED) = 's'
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $status = $result_check->fetch_assoc()['status'] ?? 'closed';
                $stmt_check->close();
                
                if ($status === 'closed') {
                    $message = "Gagal: Tiket ini sudah ditutup oleh Admin.";
                } else {
                    // Simpan pesan (ticket_id dan message_text, keduanya di-bind sebagai string 'ss')
                    $stmt_message = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_role, message) VALUES (?, 'provider', ?)");
                    $stmt_message->bind_param("ss", $ticket_id, $message_text); 
                    $stmt_message->execute();
                    $stmt_message->close();

                    $message = "Pesan Anda berhasil terkirim!";
                    $message_type = "success";
                }

            } catch (Exception $e) {
                $message = "Terjadi kesalahan sistem saat mengirim pesan: " . $e->getMessage();
            }
        }
    }
    // ==========================================================
    
}


// Set pesan di session dan redirect
$_SESSION['dashboard_message'] = $message;
$_SESSION['dashboard_message_type'] = $message_type;
header("Location: /dashboard?p=" . $redirect_page);
exit();
?>
