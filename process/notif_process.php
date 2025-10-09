<?php
// File: process/notif_process.php
// Menangani aksi AJAX dari Notifikasi (Mark as Read).

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php'; // Pastikan path ini benar

$response = ['success' => false, 'message' => 'Invalid request.'];

// Cek Otorisasi - Menggunakan variabel sesi yang sudah tersedia dan terjamin dari dashboard
$actual_provider_id = $_SESSION['actual_provider_id'] ?? null; // <<< Asumsi variabel ini ada dari dashboard.php
$user_role = $_SESSION['user_role'] ?? null;

if (!$actual_provider_id || $user_role !== 'provider') {
    $response['message'] = "Otorisasi gagal atau Provider ID tidak ditemukan.";
    echo json_encode($response);
    exit();
}

$action = $_POST['action'] ?? '';
$notif_id = (int)($_POST['notif_id'] ?? 0);

if ($action === 'read' && $notif_id > 0) {
    try {
        // UPDATE status is_read menjadi TRUE
        // Tambahkan provider_id ke klausa WHERE untuk keamanan, memastikan hanya provider yang bersangkutan yang dapat mengubah status notifnya.
        $stmt = $conn->prepare("
            UPDATE provider_notifications 
            SET is_read = TRUE 
            WHERE id = ? AND provider_id = ?
        ");
        $stmt->bind_param("ii", $notif_id, $actual_provider_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "Notifikasi berhasil ditandai sudah dibaca.";
        } else {
            $response['message'] = "Notifikasi tidak ditemukan, sudah dibaca, atau bukan milik Anda.";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = "Error DB: " . $e->getMessage();
    }
}

echo json_encode($response);