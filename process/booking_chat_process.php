<?php
// File: process/booking_chat_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$redirect_page = 'dashboard'; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST['action'] ?? '';
    $message = "Gagal memproses pesan.";
    $message_type = "danger";

    if ($action === 'send_message') {
        
        $booking_id = $_POST['booking_id'] ?? null;
        $sender_id = $_POST['sender_id'] ?? null;
        $sender_role = $_POST['sender_role'] ?? 'client'; // Bisa 'provider' atau 'client'
        $message_text = trim($_POST['message_text'] ?? '');
        $file = $_FILES['file_upload'] ?? null;

        $file_path = null;
        $file_mime_type = null;
        $error_upload = false;

        // Tentukan redirect page
        // Karena ini proses Provider, kita redirect ke chat pemesanan tersebut
        $redirect_page = 'booking_chat&booking_id=' . urlencode($booking_id); 
        
        // 1. Validasi Input
        if (empty($booking_id) || empty($sender_id) || (empty($message_text) && (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE))) {
            $message = "Pesan atau file harus diisi.";
            $_SESSION['dashboard_message'] = $message;
            $_SESSION['dashboard_message_type'] = $message_type;
            header("Location: /dashboard?p=" . $redirect_page);
            exit();
        }

        // 2. Proses Upload File (jika ada)
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $allowed_mimes = [
                'image/jpeg', 'image/png', 'image/gif', 'application/pdf'
            ];
            $max_size = 5 * 1024 * 1024; // 5 MB

            $file_mime = mime_content_type($file['tmp_name']); // Membutuhkan ekstensi Fileinfo
            
            if ($file['size'] > $max_size) {
                $message = "Gagal: Ukuran file terlalu besar (Max 5MB).";
                $error_upload = true;
            } elseif (!in_array($file_mime, $allowed_mimes)) {
                $message = "Gagal: Format file tidak didukung (Hanya JPG, PNG, GIF, PDF).";
                $error_upload = true;
            } else {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid('chat_', true) . '.' . $file_extension;
                $upload_dir = __DIR__ . '/../uploads/'; 
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_file_name)) {
                    $file_path = $new_file_name;
                    $file_mime_type = $file_mime;
                } else {
                    $message = "Gagal memindahkan file yang diunggah.";
                    $error_upload = true;
                }
            }
        }
        
        // 3. Simpan Pesan ke Database
        if (!$error_upload) {
            try {
                // Gunakan NULL jika file_path atau message_text kosong
                $msg_to_save = empty($message_text) ? NULL : $message_text;
                $file_path_to_save = empty($file_path) ? NULL : $file_path;
                $mime_to_save = empty($file_mime_type) ? NULL : $file_mime_type;

                $stmt = $conn->prepare("INSERT INTO booking_messages 
                    (booking_id, sender_id, sender_role, message, file_path, file_mime_type) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                    
                // booking_id dan sender_id adalah BIGINT UNSIGNED, gunakan 'ss'
                // sisanya adalah string (s)
                $stmt->bind_param("ssssss", 
                    $booking_id, 
                    $sender_id, 
                    $sender_role, 
                    $msg_to_save, 
                    $file_path_to_save,
                    $mime_to_save
                );
                
                if ($stmt->execute()) {
                    $message = "Pesan berhasil terkirim!";
                    $message_type = "success";
                } else {
                    throw new Exception("Gagal menyimpan pesan ke database: " . $conn->error);
                }
                $stmt->close();

            } catch (Exception $e) {
                $message = "Terjadi kesalahan sistem: " . $e->getMessage();
            }
        }
    } 
}

// Set pesan di session dan redirect
$_SESSION['dashboard_message'] = $message;
$_SESSION['dashboard_message_type'] = $message_type;
header("Location: /dashboard?p=" . $redirect_page);
exit();
?>