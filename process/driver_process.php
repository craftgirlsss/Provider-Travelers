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
        if (!isset($file_array) || $file_array['error'] === UPLOAD_ERR_NO_FILE) {
             return "File tidak ditemukan.";
        }
        return "File tidak diupload atau terjadi error: " . $file_array['error'];
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
// ... (Bagian ini tidak berubah dari yang terakhir Anda setujui)
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_driver') {
    
    // --- Data Driver
    $name = trim($_POST['name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $driver_uuid = trim($_POST['driver_uuid'] ?? '');

    // --- Data User (Kredensial Login)
    $email = trim($_POST['email'] ?? ''); 
    $plain_password = $_POST['generated_password'] ?? ''; 
    $user_role_driver = 'driver'; 
    $user_status_active = 'active'; 

    
    $_SESSION['form_data'] = $_POST;
    
    // Validasi Input Teks & Kredensial
    if (empty($name) || empty($phone_number) || empty($license_number) || empty($driver_uuid)) {
        $errors[] = "Semua kolom teks wajib diisi.";
    }
    // Cek Email dan Password
    if (empty($email)) {
        $errors[] = "Email wajib diisi.";
    }
    if (empty($plain_password)) {
        $errors[] = "Password login tidak tergenerate dengan benar. Mohon ulangi proses pembuatan driver.";
    }

    // Hash Password
    $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        $errors[] = "Gagal membuat password hash.";
    }

    // --- VALIDASI DOMAIN SERVER-SIDE ---
    $required_domain_php = '@karyadeveloperindonesia.com';
    if (!str_ends_with(strtolower($email), $required_domain_php)) {
        $errors[] = "Domain Email harus menggunakan {$required_domain_php}.";
    }
    // ------------------------------------


    $photo_url = null;
    $license_photo_url = null;
    $uploaded_paths = []; 

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
    

    // Jika ada error (baik teks, upload, maupun hashing/domain), redirect kembali
    if (!empty($errors)) {
        // Hapus file yang mungkin sudah terupload
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

    // --- Insert ke Database Menggunakan TRANSACTION ---
    try {
        // Mulai Transaksi
        $conn->begin_transaction();

        // 1. INSERT KE TABEL USERS (Kredensial Login)
        $stmt_user = $conn->prepare("
            INSERT INTO users 
            (name, email, password, phone, role, uuid, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt_user->bind_param("sssssss", 
            $name, 
            $email, 
            $password_hash, 
            $phone_number, 
            $user_role_driver, 
            $driver_uuid, 
            $user_status_active 
        );

        if (!$stmt_user->execute()) {
             throw new Exception("Gagal menyimpan kredensial user: " . $stmt_user->error);
        }
        $stmt_user->close();


        // 2. INSERT KE TABEL DRIVERS (Profil Driver)
        $stmt_driver = $conn->prepare("
            INSERT INTO drivers 
            (provider_id, name, phone_number, license_number, photo_url, license_photo_url, driver_uuid)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt_driver->bind_param("issssss",
            $actual_provider_id,
            $name,
            $phone_number,
            $license_number,
            $photo_url,
            $license_photo_url,
            $driver_uuid
        );

        if (!$stmt_driver->execute()) {
             throw new Exception("Gagal menyimpan data driver: " . $stmt_driver->error);
        }
        $stmt_driver->close();

        // Commit Transaksi
        $conn->commit();
        
        // Sukses
        unset($_SESSION['form_data']); 
        $_SESSION['dashboard_message'] = "Driver '{$name}' berhasil didaftarkan. Akun login ({$email}) dan dokumen telah disimpan.";
        $_SESSION['dashboard_message_type'] = "success";
        header("Location: " . $redirect_url);
        exit();

    } catch (Exception $e) {
        // Rollback Transaksi
        $conn->rollback();

        // Hapus file yang sudah terupload
        foreach ($uploaded_paths as $path) {
            if (file_exists(__DIR__ . '/../' . $path)) {
                unlink(__DIR__ . '/../' . $path);
            }
        }
        
        // Penanganan error spesifik
        if ($conn->errno == 1062) {
             $message = "Gagal: Email, UUID, atau Nomor SIM sudah terdaftar pada sistem.";
        } else {
             $message = "Terjadi kesalahan sistem saat menyimpan: " . $e->getMessage();
        }

        $_SESSION['dashboard_message'] = $message;
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=driver_create");
        exit();
    }
}
// ==========================================================

// --- AKSI UPDATE DRIVER ---
elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'update_driver') {
    
    $driver_id = (int)($_POST['driver_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 0); 
    $existing_photo_url = trim($_POST['existing_photo_url'] ?? '');
    $existing_license_photo_url = trim($_POST['existing_license_photo_url'] ?? '');
    
    // --- LOGIKA PASSWORD BARU ---
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $password_update_needed = false;
    $password_hash = null;

    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $errors[] = "Password baru dan konfirmasi password tidak cocok.";
        }
        if (strlen($new_password) < 8) {
            $errors[] = "Password baru minimal harus 8 karakter.";
        }
        
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        if ($password_hash === false) {
            $errors[] = "Gagal membuat password hash baru.";
        }
        $password_update_needed = true;
    }
    // ------------------------------------------

    $_SESSION['form_data'] = $_POST;
    
    // 1. Validasi Input Dasar
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
            if ($existing_license_photo_url) $uploaded_paths['delete_old_sim'] = $existing_license_photo_url;
            $uploaded_paths['new_sim'] = $license_photo_url;
        } else {
            $errors[] = "Foto SIM Driver Baru: " . $result_license_photo;
        }
    }
    

    // 4. Jika ada error, redirect kembali
    if (!empty($errors)) {
        // Hapus file baru jika ada
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

    // 5. Update ke Database Menggunakan TRANSACTION
    try {
        $conn->begin_transaction();
        
        // --- Ambil driver_uuid untuk update users
        $stmt_get_uuid = $conn->prepare("SELECT driver_uuid FROM drivers WHERE id = ? AND provider_id = ?");
        $stmt_get_uuid->bind_param("ii", $driver_id, $actual_provider_id);
        $stmt_get_uuid->execute();
        $result_uuid = $stmt_get_uuid->get_result();
        $driver_data = $result_uuid->fetch_assoc();
        $target_driver_uuid = $driver_data['driver_uuid'] ?? '';
        $stmt_get_uuid->close();

        if (empty($target_driver_uuid)) {
             throw new Exception("UUID Driver tidak ditemukan untuk pembaruan user.");
        }
        
        // 5a. Update TABEL DRIVERS
        $stmt = $conn->prepare("
            UPDATE drivers 
            SET name = ?, phone_number = ?, license_number = ?, is_active = ?, 
                photo_url = ?, license_photo_url = ?
            WHERE id = ? AND provider_id = ?
        ");
        
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

        if (!$stmt->execute()) {
             throw new Exception("Gagal menyimpan perubahan driver: " . $stmt->error);
        }
        $stmt->close();
        
        // 5b. Update TABEL USERS (Sinkronisasi Phone dan Password jika ada)
        
        // Update Phone Number di tabel users
        $stmt_user_phone = $conn->prepare("
            UPDATE users SET name = ?, phone = ? WHERE uuid = ? AND role = 'driver'
        ");
        $stmt_user_phone->bind_param("sss", $name, $phone_number, $target_driver_uuid);
        if (!$stmt_user_phone->execute()) {
             throw new Exception("Gagal memperbarui nomor telepon user: " . $stmt_user_phone->error);
        }
        $stmt_user_phone->close();
        
        // Update Password jika diisi
        if ($password_update_needed) {
            $stmt_user_password = $conn->prepare("
                UPDATE users SET password = ? WHERE uuid = ? AND role = 'driver'
            ");
            $stmt_user_password->bind_param("ss", $password_hash, $target_driver_uuid);
            if (!$stmt_user_password->execute()) {
                 throw new Exception("Gagal memperbarui password user: " . $stmt_user_password->error);
            }
            $stmt_user_password->close();
        }

        // Commit Transaksi
        $conn->commit();
        
        // Hapus file lama yang berhasil diganti
        if (isset($uploaded_paths['delete_old_photo']) && file_exists(__DIR__ . '/../' . $uploaded_paths['delete_old_photo'])) {
            unlink(__DIR__ . '/../' . $uploaded_paths['delete_old_photo']);
        }
        if (isset($uploaded_paths['delete_old_sim']) && file_exists(__DIR__ . '/../' . $uploaded_paths['delete_old_sim'])) {
            unlink(__DIR__ . '/../' . $uploaded_paths['delete_old_sim']);
        }
        
        // Sukses
        unset($_SESSION['form_data']); 
        $success_msg = $password_update_needed ? "Password dan data Driver '{$name}' berhasil diperbarui." : "Data Driver '{$name}' berhasil diperbarui.";
        $_SESSION['dashboard_message'] = $success_msg;
        $_SESSION['dashboard_message_type'] = "success";
        header("Location: " . $redirect_url);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
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
// ==========================================================

// --- AKSI DEACTIVATE/ACTIVATE DRIVER ---
// ... (Logika Deactivate/Activate tidak berubah, tetap menggunakan UUID)
// ==========================================================

elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'deactivate_driver') {
    // Soft Delete (is_active = 0 di drivers dan status = inactive di users)
    $driver_id_to_deactivate = (int)($_POST['driver_id'] ?? 0);
    $message = "Gagal menonaktifkan driver.";
    $message_type = "danger";

    if ($driver_id_to_deactivate > 0) {
        try {
            $conn->begin_transaction();
            
            // 1. Ambil driver_uuid dari tabel drivers
            $stmt_get_uuid = $conn->prepare("SELECT driver_uuid FROM drivers WHERE id = ? AND provider_id = ?");
            $stmt_get_uuid->bind_param("ii", $driver_id_to_deactivate, $actual_provider_id);
            $stmt_get_uuid->execute();
            $result = $stmt_get_uuid->get_result();
            if ($result->num_rows === 0) {
                 throw new Exception("Driver tidak ditemukan atau tidak di bawah otorisasi Anda.");
            }
            $driver_data = $result->fetch_assoc();
            $target_driver_uuid = $driver_data['driver_uuid'];
            $stmt_get_uuid->close();
            
            // 2. Nonaktifkan di tabel drivers
            $stmt_driver = $conn->prepare("UPDATE drivers SET is_active = 0 WHERE id = ? AND provider_id = ?");
            $stmt_driver->bind_param("ii", $driver_id_to_deactivate, $actual_provider_id);
            if (!$stmt_driver->execute()) {
                 throw new Exception("Gagal menonaktifkan data driver.");
            }
            
            // 3. Nonaktifkan di tabel users (untuk mencegah login) menggunakan UUID
            $new_status = 'inactive';
            $stmt_user = $conn->prepare("UPDATE users SET status = ? WHERE uuid = ? AND role = 'driver'");
            $stmt_user->bind_param("ss", $new_status, $target_driver_uuid);
             if (!$stmt_user->execute()) {
                 throw new Exception("Gagal menonaktifkan akun user.");
            }
            
            $conn->commit();
            $message = "Driver berhasil dinonaktifkan dan diarsipkan. Akun login telah diblokir.";
            $message_type = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Kesalahan database: " . $e->getMessage();
        }
    }
    
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $message_type;
    header("Location: /dashboard?p=driver_edit&id=" . $driver_id_to_deactivate);
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'activate_driver') {
    // Restore (is_active = 1 di drivers dan status = active di users)
    $driver_id_to_activate = (int)($_POST['driver_id'] ?? 0);
    $message = "Gagal mengaktifkan kembali driver.";
    $message_type = "danger";

    if ($driver_id_to_activate > 0) {
        try {
            $conn->begin_transaction();
            
            // 1. Ambil driver_uuid dari tabel drivers
            $stmt_get_uuid = $conn->prepare("SELECT driver_uuid FROM drivers WHERE id = ? AND provider_id = ?");
            $stmt_get_uuid->bind_param("ii", $driver_id_to_activate, $actual_provider_id);
            $stmt_get_uuid->execute();
            $result = $stmt_get_uuid->get_result();
            if ($result->num_rows === 0) {
                 throw new Exception("Driver tidak ditemukan atau tidak di bawah otorisasi Anda.");
            }
            $driver_data = $result->fetch_assoc();
            $target_driver_uuid = $driver_data['driver_uuid'];
            $stmt_get_uuid->close();

            // 2. Aktifkan di tabel drivers
            $stmt_driver = $conn->prepare("UPDATE drivers SET is_active = 1 WHERE id = ? AND provider_id = ?");
            $stmt_driver->bind_param("ii", $driver_id_to_activate, $actual_provider_id);
             if (!$stmt_driver->execute()) {
                 throw new Exception("Gagal mengaktifkan data driver.");
            }
            
            // 3. Aktifkan di tabel users (untuk mengizinkan login) menggunakan UUID
            $new_status = 'active';
            $stmt_user = $conn->prepare("UPDATE users SET status = ? WHERE uuid = ? AND role = 'driver'");
            $stmt_user->bind_param("ss", $new_status, $target_driver_uuid);
             if (!$stmt_user->execute()) {
                 throw new Exception("Gagal mengaktifkan akun user.");
            }
            
            $conn->commit();
            $message = "Driver berhasil diaktifkan kembali. Akun login telah diizinkan.";
            $message_type = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Kesalahan database: " . $e->getMessage();
        }
    }
    
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $message_type;
    header("Location: /dashboard?p=driver_edit&id=" . $driver_id_to_activate);
    exit();
}


// Jika tidak ada aksi yang valid, kembalikan ke daftar driver
header("Location: " . $redirect_url);
exit();