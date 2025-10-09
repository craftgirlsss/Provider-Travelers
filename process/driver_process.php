<?php
// File: process/driver_process.php
// Menangani proses pembuatan dan update data driver.

session_start();
require_once __DIR__ . '/../config/db_config.php'; 

// Cek Otorisasi
$actual_provider_id = $_SESSION['actual_provider_id'] ?? null; 
$user_role = $_SESSION['user_role'] ?? null;

if (!$actual_provider_id || $user_role !== 'provider') {
    header("Location: /login"); 
    exit();
}

$errors = [];
$action = $_POST['action'] ?? '';
$redirect_url = "/dashboard?p=driver_management"; 

// Konfigurasi Upload
$upload_dir = __DIR__ . '/../uploads/drivers/';
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
$max_size = 2 * 1024 * 1024; // 2MB

// Pastikan folder upload ada
if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

// ==========================================================
// --- FUNGSI HELPER: UPLOAD FILE ---
// ==========================================================
/**
 * Menangani upload file dan mengembalikan path baru relatif atau pesan error.
 */
function handle_driver_upload($file_array, $upload_dir, $allowed_types, $max_size, $prefix) {
    global $errors;
    
    if (!isset($file_array) || $file_array['error'] !== UPLOAD_ERR_OK) {
        return "File tidak diupload atau terjadi error.";
    }

    $file_type = $file_array['type'];
    $file_size = $file_array['size'];

    if (!in_array($file_type, $allowed_types)) {
        return "Format file tidak didukung (harus JPG/PNG).";
    }
    if ($file_size > $max_size) {
        return "Ukuran file melebihi batas 2MB.";
    }

    $file_extension = pathinfo($file_array['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid($prefix . '_') . '.' . $file_extension;
    $destination_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($file_array['tmp_name'], $destination_path)) {
        // Mengembalikan path relatif untuk disimpan di DB
        return 'uploads/drivers/' . $new_file_name;
    } else {
        return "Gagal memindahkan file ke direktori server.";
    }
}
// ==========================================================


// ==========================================================
// --- AKSI: CREATE DRIVER ---
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_driver') {
    
    // Ambil input
    $name = trim($_POST['name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $driver_uuid = trim($_POST['driver_uuid'] ?? '');
    
    $_SESSION['form_data'] = $_POST;
    
    // Validasi Input Teks
    if (empty($name) || empty($phone_number) || empty($license_number) || empty($driver_uuid)) {
        $errors[] = "Semua kolom teks wajib diisi.";
    }

    $photo_url = null;
    $license_photo_url = null;
    $uploaded_paths = []; // Untuk melacak file yang berhasil diupload jika terjadi error DB

    // --- Proses Upload Foto Driver ---
    $result_photo = handle_driver_upload($_FILES['photo_file'] ?? null, $upload_dir, $allowed_types, $max_size, 'driver_photo');
    if (strpos($result_photo, 'uploads/') === 0) {
        $photo_url = $result_photo;
        $uploaded_paths[] = $photo_url;
    } else {
        $errors[] = "Foto Driver: " . $result_photo;
    }
    
    // --- Proses Upload Foto SIM ---
    $result_license_photo = handle_driver_upload($_FILES['license_photo_file'] ?? null, $upload_dir, $allowed_types, $max_size, 'driver_sim');
    if (strpos($result_license_photo, 'uploads/') === 0) {
        $license_photo_url = $result_license_photo;
        $uploaded_paths[] = $license_photo_url;
    } else {
        $errors[] = "Foto SIM Driver: " . $result_license_photo;
    }
    

    // Jika ada error (baik teks maupun upload), redirect kembali
    if (!empty($errors)) {
        // Hapus file yang mungkin sudah terupload jika ada error validasi lain
        foreach ($uploaded_paths as $path) {
            if (file_exists(__DIR__ . '/../' . $path)) {
                unlink(__DIR__ . '/../' . $path);
            }
        }
        
        $_SESSION['dashboard_message'] = implode("<br>", $errors);
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=driver_create");
        exit();
    }

    // --- Insert ke Database ---
    try {
        $stmt = $conn->prepare("
            INSERT INTO drivers 
            (provider_id, name, phone_number, license_number, photo_url, license_photo_url, driver_uuid)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        // i s s s s s s (Provider ID, Name, Phone, License, Photo URL, SIM URL, UUID)
        $stmt->bind_param("issssss",
            $actual_provider_id,
            $name,
            $phone_number,
            $license_number,
            $photo_url,
            $license_photo_url,
            $driver_uuid
        );

        if ($stmt->execute()) {
            $stmt->close();
            
            // Sukses
            unset($_SESSION['form_data']); 
            $_SESSION['dashboard_message'] = "Driver '{$name}' berhasil didaftarkan. Data dan dokumen telah disimpan.";
            $_SESSION['dashboard_message_type'] = "success";
            header("Location: " . $redirect_url);
            exit();
        } else {
             throw new Exception("Gagal menyimpan driver: " . $stmt->error);
        }

    } catch (Exception $e) {
        // Hapus file yang sudah terupload jika terjadi error DB
        foreach ($uploaded_paths as $path) {
            if (file_exists(__DIR__ . '/../' . $path)) {
                unlink(__DIR__ . '/../' . $path);
            }
        }
        
        if ($conn->errno == 1062) {
             $_SESSION['dashboard_message'] = "Gagal: UUID yang dibuat duplikat atau Nomor SIM sudah terdaftar.";
        } else {
             $_SESSION['dashboard_message'] = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=driver_create");
        exit();
    }
}elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'update_driver') {
    
    $driver_id = (int)($_POST['driver_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 0); // 1 atau 0
    $existing_photo_url = trim($_POST['existing_photo_url'] ?? '');
    $existing_license_photo_url = trim($_POST['existing_license_photo_url'] ?? '');

    $_SESSION['form_data'] = $_POST;
    
    // 1. Validasi
    if ($driver_id <= 0) {
        $errors[] = "ID Driver tidak valid.";
    }
    if (empty($name) || empty($phone_number) || empty($license_number)) {
        $errors[] = "Semua kolom teks wajib diisi.";
    }

    $photo_url = $existing_photo_url;
    $license_photo_url = $existing_license_photo_url;
    $uploaded_paths = [];

    // 2. Proses Ganti Foto Driver (Opsional)
    if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
        $result_photo = handle_driver_upload($_FILES['photo_file'], $upload_dir, $allowed_types, $max_size, 'driver_photo');
        if (strpos($result_photo, 'uploads/') === 0) {
            $photo_url = $result_photo;
            // Tandai file lama untuk dihapus jika update berhasil
            if ($existing_photo_url) $uploaded_paths['delete_old_photo'] = $existing_photo_url;
            $uploaded_paths['new_photo'] = $photo_url;
        } else {
            $errors[] = "Foto Driver Baru: " . $result_photo;
        }
    }

    // 3. Proses Ganti Foto SIM (Opsional)
    if (isset($_FILES['license_photo_file']) && $_FILES['license_photo_file']['error'] === UPLOAD_ERR_OK) {
        $result_license_photo = handle_driver_upload($_FILES['license_photo_file'], $upload_dir, $allowed_types, $max_size, 'driver_sim');
        if (strpos($result_license_photo, 'uploads/') === 0) {
            $license_photo_url = $result_license_photo;
            // Tandai file lama untuk dihapus jika update berhasil
            if ($existing_license_photo_url) $uploaded_paths['delete_old_sim'] = $existing_license_photo_url;
            $uploaded_paths['new_sim'] = $license_photo_url;
        } else {
            $errors[] = "Foto SIM Driver Baru: " . $result_license_photo;
        }
    }
    

    // 4. Jika ada error, redirect kembali
    if (!empty($errors)) {
        // Hapus file baru yang mungkin sudah diupload karena validasi gagal
        if (isset($uploaded_paths['new_photo']) && file_exists(__DIR__ . '/../' . $uploaded_paths['new_photo'])) {
            unlink(__DIR__ . '/../' . $uploaded_paths['new_photo']);
        }
        if (isset($uploaded_paths['new_sim']) && file_exists(__DIR__ . '/../' . $uploaded_paths['new_sim'])) {
            unlink(__DIR__ . '/../' . $uploaded_paths['new_sim']);
        }
        
        $_SESSION['dashboard_message'] = implode("<br>", $errors);
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=driver_edit&id=" . $driver_id);
        exit();
    }

    // 5. Update ke Database
    try {
        $stmt = $conn->prepare("
            UPDATE drivers 
            SET name = ?, phone_number = ?, license_number = ?, is_active = ?, 
                photo_url = ?, license_photo_url = ?
            WHERE id = ? AND provider_id = ?
        ");
        
        // s s s i s s i i 
        $stmt->bind_param("sssiisii",
            $name,
            $phone_number,
            $license_number,
            $is_active,
            $photo_url,
            $license_photo_url,
            $driver_id,
            $actual_provider_id
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Hapus file lama yang berhasil diganti
            if (isset($uploaded_paths['delete_old_photo']) && file_exists(__DIR__ . '/../' . $uploaded_paths['delete_old_photo'])) {
                unlink(__DIR__ . '/../' . $uploaded_paths['delete_old_photo']);
            }
            if (isset($uploaded_paths['delete_old_sim']) && file_exists(__DIR__ . '/../' . $uploaded_paths['delete_old_sim'])) {
                unlink(__DIR__ . '/../' . $uploaded_paths['delete_old_sim']);
            }
            
            // Sukses
            unset($_SESSION['form_data']); 
            $_SESSION['dashboard_message'] = "Data Driver '{$name}' berhasil diperbarui.";
            $_SESSION['dashboard_message_type'] = "success";
            header("Location: " . $redirect_url);
            exit();
        } else {
             throw new Exception("Gagal menyimpan perubahan driver: " . $stmt->error);
        }

    } catch (Exception $e) {
        // Hapus file baru jika terjadi error DB
        if (isset($uploaded_paths['new_photo']) && file_exists(__DIR__ . '/../' . $uploaded_paths['new_photo'])) {
            unlink(__DIR__ . '/../' . $uploaded_paths['new_photo']);
        }
        if (isset($uploaded_paths['new_sim']) && file_exists(__DIR__ . '/../' . $uploaded_paths['new_sim'])) {
            unlink(__DIR__ . '/../' . $uploaded_paths['new_sim']);
        }
        
        if ($conn->errno == 1062) {
             $_SESSION['dashboard_message'] = "Gagal: Nomor SIM sudah terdaftar pada driver lain.";
        } else {
             $_SESSION['dashboard_message'] = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=driver_edit&id=" . $driver_id);
        exit();
    }
}


// Jika tidak ada aksi yang valid, kembalikan ke daftar driver
header("Location: " . $redirect_url);
exit();