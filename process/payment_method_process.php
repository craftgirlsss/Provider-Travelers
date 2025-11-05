<?php
// File: process/payment_method_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';
$redirect_page = 'profile'; 
if (!isset($_SESSION['user_uuid']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}
$user_uuid_from_session = $_SESSION['user_uuid'];
$user_id_from_session = null; 
$actual_provider_id = null; 
$errors = [];
try {
    $stmt_user = $conn->prepare("SELECT u.id, p.id as provider_id
        FROM users u
        JOIN providers p ON u.id = p.user_id
        WHERE u.uuid = ?");
    $stmt_user->bind_param("s", $user_uuid_from_session); 
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows > 0) {
        $data = $result_user->fetch_assoc();
        $user_id_from_session = $data['id'];
        $actual_provider_id = $data['provider_id'];
    }
    $stmt_user->close();
} catch (Exception $e) {}
if (!$actual_provider_id) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Otorisasi Gagal: ID Provider tidak ditemukan.']);
        exit();
    }
    $_SESSION['dashboard_message'] = "Error Otorisasi: Data provider tidak ditemukan.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}
function upload_qris_photo($file_key, &$errors, $is_required = true) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 1 * 1024 * 1024; // 1MB untuk QRIS
    $prefix = 'qris';   
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($is_required) {
            $errors[] = "Gambar QRIS wajib diunggah untuk tipe QRIS.";
        }
        return null;
    }
    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Gagal mengunggah file QRIS (Error Code: " . $_FILES[$file_key]['error'] . ").";
        return null;
    }
    $file = $_FILES[$file_key];
    
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Format file QRIS tidak didukung (Hanya JPG/PNG).";
        return null;
    }
    if ($file['size'] > $max_size) {
        $errors[] = "Ukuran file QRIS melebihi batas 1MB.";
        return null;
    }
    $upload_dir = __DIR__ . '/../uploads/qris/';
    if (!is_dir($upload_dir)) { 
        if (!mkdir($upload_dir, 0777, true)) {
            $errors[] = "Gagal membuat direktori upload QRIS.";
            return null;
        }
    }
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid($prefix . '_' . time() . '_') . '.' . $file_extension;
    $destination_path = $upload_dir . $new_file_name;
    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        return 'uploads/qris/' . $new_file_name;
    } else {
        $errors[] = "Gagal memindahkan file QRIS ke folder upload.";
    }
    return null;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $message = "Gagal memproses data.";
    $message_type = "danger";
    $method_id = (int)($_POST['method_id'] ?? 0);
    $method_type = trim($_POST['method_type'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $is_main = isset($_POST['is_main']) ? 1 : 0; 
    $is_active = isset($_POST['is_active']) ? 1 : 0; 
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($action === 'add_method') {
        $qris_image_url = null;
        if (empty($method_type) || empty($bank_name) || empty($account_name) || empty($account_number)) {
            $errors[] = "Semua field bertanda (*) wajib diisi.";
        }
        if (!in_array($method_type, ['BANK_TRANSFER', 'E_WALLET', 'QRIS'])) {
            $errors[] = "Tipe metode pembayaran tidak valid.";
        }
        if ($method_type === 'QRIS') {
            $qris_image_url = upload_qris_photo('qris_image_file', $errors, true); 
        }
        if (empty($errors)) {
            try {
                $conn->begin_transaction();
                if ($is_main == 1) {
                    $stmt_main_reset = $conn->prepare("UPDATE provider_payment_methods SET is_main = 0 WHERE provider_id = ?");
                    $stmt_main_reset->bind_param("i", $actual_provider_id);
                    $stmt_main_reset->execute();
                    $stmt_main_reset->close();
                }
                $stmt = $conn->prepare("INSERT INTO provider_payment_methods 
                    (provider_id, method_type, account_name, bank_name, account_number, qris_image_url, is_main, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssii",
                    $actual_provider_id, $method_type, $account_name, $bank_name, 
                    $account_number, $qris_image_url, $is_main, $is_active
                );
                if ($stmt->execute()) {
                    $conn->commit();
                    $message = "Metode pembayaran '{$bank_name}' berhasil ditambahkan!";
                    $message_type = "success";
                } else {
                    throw new Exception("Gagal menyimpan data ke database: " . $stmt->error);
                }
                $stmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Terjadi kesalahan sistem: " . $e->getMessage();
                if ($qris_image_url && file_exists(__DIR__ . '/../' . $qris_image_url)) {
                    unlink(__DIR__ . '/../' . $qris_image_url);
                }
            }
        } else {
            $message = implode("<br>", $errors);
        }
    }
    elseif ($action === 'delete_method' && $is_ajax) {
        header('Content-Type: application/json');   
        if ($method_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Metode tidak valid.']);
            exit();
        }
        $conn->begin_transaction();
        try {
            $stmt_get_photo = $conn->prepare("SELECT qris_image_url FROM provider_payment_methods WHERE id = ? AND provider_id = ?");
            $stmt_get_photo->bind_param("ii", $method_id, $actual_provider_id);
            $stmt_get_photo->execute();
            $result_photo = $stmt_get_photo->get_result();
            $photo_to_delete = null;
            if ($row = $result_photo->fetch_assoc()) {
                $photo_to_delete = $row['qris_image_url'];
            }
            $stmt_get_photo->close();
            if (!$photo_to_delete && $result_photo->num_rows == 0) { // Cek apakah data tidak ditemukan
                throw new Exception("Metode pembayaran tidak ditemukan atau Anda tidak memiliki otorisasi.");
            }
            $stmt_delete = $conn->prepare("DELETE FROM provider_payment_methods WHERE id = ? AND provider_id = ?");
            $stmt_delete->bind_param("ii", $method_id, $actual_provider_id);
            if ($stmt_delete->execute() && $stmt_delete->affected_rows > 0) {
                if ($photo_to_delete && file_exists(__DIR__ . '/../' . $photo_to_delete)) {
                    unlink(__DIR__ . '/../' . $photo_to_delete);
                }
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Metode pembayaran berhasil dihapus.']);
            } else {
                throw new Exception("Gagal menghapus data dari database.");
            }
            $stmt_delete->close();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => "Gagal menghapus: " . $e->getMessage()]);
        }
        exit();
    }
}
if (!$is_ajax) {
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $message_type;
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}