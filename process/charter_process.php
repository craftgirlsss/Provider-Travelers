<?php
// File: process/charter_process.php
session_start();
// Pastikan path ke db_config.php sudah benar: keluar dari 'process', lalu masuk ke 'config'
require_once __DIR__ . '/../config/db_config.php';

// PERBAIKAN KRITIS 1: Ubah parameter redirect ke 'p' sesuai dashboard.php
$redirect_page_param = 'p'; 
$redirect_page_name = 'charter_fleet'; // Halaman tujuan setelah proses selesai

// 1. Cek Login & Role
if (!isset($_SESSION['user_uuid']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}

$user_uuid_from_session = $_SESSION['user_uuid'];
$user_id_from_session = null;
$actual_provider_id = null;

// --- Ambil ID Integer dari UUID dan Provider ID ---
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
} catch (Exception $e) {
    // Log error
}

if (!$actual_provider_id) {
    $_SESSION['charter_message'] = "Error Otorisasi: Data provider tidak ditemukan.";
    $_SESSION['charter_message_type'] = "danger";
    // Menggunakan parameter 'p'
    header("Location: /dashboard?{$redirect_page_param}=" . $redirect_page_name);
    exit();
}

// ==========================================================
// --- FUNGSI PEMBANTU UPLOAD ---
// ==========================================================
function upload_charter_photo($file_key, &$errors, $is_required = true) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($is_required) {
            $errors[] = "Foto Armada wajib diunggah.";
        }
        return null;
    }

    $file = $_FILES[$file_key];
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Format file foto armada tidak didukung (Hanya JPG/PNG).";
        return null;
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = "Ukuran file foto armada melebihi batas 2MB.";
        return null;
    }
    
    // Path folder upload relatif terhadap root proyek (keluar 1 kali dari 'process')
    $upload_dir = __DIR__ . '/../uploads/charter_photos/';
    
    // PENTING: Pastikan folder memiliki izin tulis (755/777)
    if (!is_dir($upload_dir)) { 
        if (!mkdir($upload_dir, 0777, true)) {
            $errors[] = "Gagal membuat direktori upload. Cek izin server.";
            return null;
        }
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid('charter_' . time() . '_') . '.' . $file_extension;
    $destination_path = $upload_dir . $new_file_name;
    
    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        // Return path relatif dari root proyek
        return 'uploads/charter_photos/' . $new_file_name;
    } else {
        $errors[] = "Gagal memindahkan file foto armada ke folder upload. Cek izin tulis (write permission) pada folder 'uploads/charter_photos'.";
    }
    return null;
}

// ==========================================================
// --- PENANGANAN POST REQUEST ---
// ==========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST['action'] ?? '';
    $errors = [];
    $message = "Gagal memproses data.";
    $message_type = "danger";

    // --- Ambil Data Dasar Form (digunakan oleh ADD dan UPDATE) ---
    $vehicle_name = trim($_POST['vehicle_name'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $type = trim($_POST['type'] ?? '');
    
    // Ambil nilai 'base_price_per_day' dari hidden field yang berisi angka murni
    $base_price_per_day = floatval($_POST['base_price_per_day'] ?? 0); 
    
    $description = trim($_POST['description'] ?? '');
    $is_available = isset($_POST['is_available']) ? 1 : 0; 
    
    
    // ==========================================================
    // --- AKSI 1: TAMBAH ARMADA (add_fleet) ---
    // ==========================================================
    if ($action === 'add_fleet') {
        
        // 1. Validasi
        if (empty($vehicle_name) || $capacity <= 0 || empty($type) || $base_price_per_day <= 0 || empty($description)) {
            $errors[] = "Semua field wajib diisi dengan benar.";
        }
        if (!in_array($type, ['bus', 'van', 'car', 'other'])) {
            $errors[] = "Tipe kendaraan tidak valid.";
        }

        // 2. Proses Upload Foto (Wajib)
        $photo_path = upload_charter_photo('photo_file', $errors, true); 

        if (empty($errors) && $photo_path) {
            
            try {
                $stmt = $conn->prepare("INSERT INTO charter_fleet 
                    (provider_id, vehicle_name, capacity, type, base_price_per_day, description, photo_path, is_available) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("isissdsi",
                    $actual_provider_id, $vehicle_name, $capacity, $type, 
                    $base_price_per_day, $description, $photo_path, $is_available
                );
                
                if ($stmt->execute()) {
                    $message = "Armada sewa '{$vehicle_name}' berhasil ditambahkan!";
                    $message_type = "success";
                } else {
                    throw new Exception("Gagal menyimpan data ke database: " . $stmt->error);
                }
                $stmt->close();

            } catch (Exception $e) {
                $message = "Terjadi kesalahan sistem: " . $e->getMessage();
                // Hapus file yang sudah terupload jika ada error DB
                if ($photo_path && file_exists(__DIR__ . '/../' . $photo_path)) {
                    unlink(__DIR__ . '/../' . $photo_path);
                }
            }
        } else {
            // Jika upload gagal, $photo_path akan null dan error sudah ditambahkan
            $message = implode("<br>", $errors);
        }
    }


    // ==========================================================
    // --- AKSI 2: UPDATE ARMADA (update_fleet) ---
    // ==========================================================
    elseif ($action === 'update_fleet') {
        
        $fleet_id = (int)($_POST['fleet_id'] ?? 0);
        $old_photo_path = $_POST['old_photo_path'] ?? null;
        $photo_path = $old_photo_path; // Default: gunakan path lama

        // 1. Validasi
        if ($fleet_id <= 0) {
            $errors[] = "ID Armada tidak valid.";
        }
        if (empty($vehicle_name) || $capacity <= 0 || empty($type) || $base_price_per_day <= 0 || empty($description)) {
            $errors[] = "Semua field wajib diisi dengan benar.";
        }
        
        // 2. Proses Upload Foto (Tidak Wajib)
        $new_photo = upload_charter_photo('photo_file', $errors, false);
        
        if ($new_photo) {
            $photo_path = $new_photo; // Gunakan path baru
        }

        if (empty($errors)) {
            try {
                // Query UPDATE: 9 Tanda tanya: 7 SET + 2 WHERE
                $stmt = $conn->prepare("UPDATE charter_fleet SET 
                    vehicle_name = ?, capacity = ?, type = ?, base_price_per_day = ?, 
                    description = ?, photo_path = ?, is_available = ?, updated_at = NOW()
                    WHERE id = ? AND provider_id = ?"); 
                                
                // PERBAIKAN KRITIS 1: String Tipe Data Dikoreksi menjadi 9 karakter: sisdsisii
                $stmt->bind_param("sisdsisii", 
                    $vehicle_name, 
                    $capacity, 
                    $type, 
                    $base_price_per_day, 
                    $description, 
                    $photo_path, // s (String)
                    $is_available, // i
                    $fleet_id,     // i (WHERE id)
                    $actual_provider_id // i (WHERE provider_id)
                );

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $message = "Armada sewa '{$vehicle_name}' berhasil diperbarui!";
                        $message_type = "success";
                        
                        // Hapus file lama jika ada file baru yang diupload
                        if ($new_photo && $old_photo_path && file_exists(__DIR__ . '/../' . $old_photo_path)) {
                            unlink(__DIR__ . '/../' . $old_photo_path);
                        }
                    } else {
                        $message = "Tidak ada perubahan data, atau Armada tidak ditemukan.";
                        $message_type = "warning";
                         // Hapus file baru jika tidak ada baris yang diubah
                        if ($new_photo && file_exists(__DIR__ . '/../' . $new_photo)) {
                            unlink(__DIR__ . '/../' . $new_photo);
                        }
                    }
                } else {
                    throw new Exception("Gagal menyimpan data ke database: " . $stmt->error);
                }
                $stmt->close();
                
            } catch (Exception $e) {
                $message = "Terjadi kesalahan sistem saat update: " . $e->getMessage();
                 // Hapus file baru jika ada error DB
                if ($new_photo && file_exists(__DIR__ . '/../' . $new_photo)) {
                    unlink(__DIR__ . '/../' . $new_photo);
                }
            }
        } else {
            $message = implode("<br>", $errors);
        }
        
        // Redirect kembali ke halaman edit
        $redirect_page_name = 'charter_fleet&action=edit&id=' . $fleet_id;
    }


    // ==========================================================
    // --- AKSI 3: HAPUS ARMADA (delete_fleet) ---
    // ==========================================================
    elseif ($action === 'delete_fleet') {
        
        $fleet_id = (int)($_POST['fleet_id'] ?? 0);
        $photo_to_delete = null;

        if ($fleet_id > 0) {
            $conn->begin_transaction();
            try {
                // 1. Ambil path foto sebelum menghapus record (untuk otorisasi dan penghapusan fisik)
                $stmt_get_photo = $conn->prepare("SELECT photo_path, vehicle_name FROM charter_fleet WHERE id = ? AND provider_id = ?");
                $stmt_get_photo->bind_param("ii", $fleet_id, $actual_provider_id);
                $stmt_get_photo->execute();
                $result_photo = $stmt_get_photo->get_result();

                $data_found = false; 
                if ($row = $result_photo->fetch_assoc()) {
                    $photo_to_delete = $row['photo_path'];
                    $vehicle_name = $row['vehicle_name'];
                    $data_found = true; 
                }
                $stmt_get_photo->close(); // <-- PENUTUPAN STATEMENT PERTAMA (HANYA SEKALI)
                
                // Cek Otorisasi/Eksistensi
                if (!$data_found) { 
                    throw new Exception("Armada tidak ditemukan atau Anda tidak memiliki otorisasi untuk menghapusnya.");
                }

                // 2. Hapus record dari database
                $stmt_delete = $conn->prepare("DELETE FROM charter_fleet WHERE id = ? AND provider_id = ?");
                $stmt_delete->bind_param("ii", $fleet_id, $actual_provider_id);
                
                if ($stmt_delete->execute() && $stmt_delete->affected_rows > 0) {
                    // 3. Hapus file fisik
                    if ($photo_to_delete && file_exists(__DIR__ . '/../' . $photo_to_delete)) {
                        // Path file relatif dari root proyek
                        unlink(__DIR__ . '/../' . $photo_to_delete);
                    }
                    
                    $conn->commit();
                    $message = "Armada '{$vehicle_name}' berhasil dihapus.";
                    $message_type = "success";
                } else {
                    throw new Exception("Gagal menghapus data dari database.");
                }
                $stmt_delete->close();
                
            } catch (Exception $e) {
                // Pastikan statement yang mungkin masih terbuka ditutup di sini jika terjadi error sebelum penutupan
                if (isset($stmt_get_photo) && method_exists($stmt_get_photo, 'close') && !$stmt_get_photo->close()) {
                    // Jika statement 'select' belum tertutup karena error, tutup di sini.
                }
                if (isset($stmt_delete) && method_exists($stmt_delete, 'close') && !$stmt_delete->close()) {
                    // Jika statement 'delete' belum tertutup karena error, tutup di sini.
                }
                
                $conn->rollback();
                $message = "Gagal menghapus armada: " . $e->getMessage();
            }
        } else {
            $message = "ID Armada tidak valid.";
        }
    }
}


// --- REDIRECT AKHIR ---
$_SESSION['charter_message'] = $message ?? "Aksi tidak dikenal.";
$_SESSION['charter_message_type'] = $message_type ?? "danger";

// Menggunakan parameter 'p'
header("Location: /dashboard?{$redirect_page_param}=" . $redirect_page_name);
exit();
?>