<?php
session_start();

require_once __DIR__ . '/../config/db_config.php'; 

// Fungsi untuk redirect dengan pesan
function redirect_with_message($message, $type = "danger", $vehicle_id = null) {
    $_SESSION['vehicle_message'] = $message;
    $_SESSION['vehicle_message_type'] = $type;
    $location = "../dashboard.php?p=vehicles";
    if ($vehicle_id) {
        // Redirect kembali ke halaman edit jika gagal update
        $location = "../dashboard.php?p=vehicle_edit&id=" . $vehicle_id;
    }
    header("Location: " . $location); 
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_message("Metode permintaan tidak valid.", "warning");
}

// 1. Ambil dan Bersihkan Data Input
$vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$license_plate = strtoupper(trim($_POST['license_plate'] ?? '')); 
$capacity = (int)($_POST['capacity'] ?? 0);
$type = $_POST['type'] ?? 'car';
$status = $_POST['status'] ?? 'available';
$old_photo_path = $_POST['old_photo_path'] ?? null;

// Ambil provider_id dari session untuk validasi otorisasi
$provider_id = $_SESSION['actual_provider_id'] ?? null;


// 2. Validasi Dasar
if (!$provider_id) {
    redirect_with_message("Sesi Provider tidak valid. Silakan login ulang.", "danger");
}
if ($vehicle_id === 0) {
    redirect_with_message("ID Kendaraan untuk update tidak valid.", "danger");
}
if (empty($name) || empty($license_plate) || $capacity <= 0 || strlen($license_plate) < 3) {
    redirect_with_message("Semua field wajib diisi. Pastikan Plat Nomor diisi dengan benar.", "danger", $vehicle_id);
}

$photo_path_to_save = $old_photo_path; // Default: pertahankan path lama

// 3. Proses Upload Foto BARU (Opsional)
if (isset($_FILES['vehicle_photo']) && $_FILES['vehicle_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['vehicle_photo'];
    $max_size = 5 * 1024 * 1024; // 5 MB
    $allowed_types = ['image/jpeg', 'image/png'];
    $upload_dir = __DIR__ . '/../uploads/vehicle_photos/'; 

    if ($file['size'] > $max_size) {
        redirect_with_message("Ukuran foto kendaraan maksimal 5MB.", "danger", $vehicle_id);
    }
    if (!in_array($file['type'], $allowed_types)) {
        redirect_with_message("Format foto tidak didukung. Gunakan JPG atau PNG.", "danger", $vehicle_id);
    }
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate nama file unik dan pindahkan
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('vehicle_') . '.' . $extension;
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Path baru yang akan disimpan
        $photo_path_to_save = 'uploads/vehicle_photos/' . $file_name;
        
        // Hapus foto lama jika ada dan valid
        if ($old_photo_path && file_exists(__DIR__ . '/../' . $old_photo_path)) {
            unlink(__DIR__ . '/../' . $old_photo_path);
        }
    } else {
        redirect_with_message("Gagal mengupload file foto. Periksa izin folder.", "danger", $vehicle_id);
    }
}

// 4. Update ke Database
try {
    $stmt = $conn->prepare("UPDATE vehicles SET 
                              name = ?, 
                              license_plate = ?, 
                              capacity = ?, 
                              type = ?, 
                              vehicle_photo_path = ?, 
                              status = ?
                              WHERE id = ? AND provider_id = ?");
    
    // String format: s (name), s (license_plate), i (capacity), s (type), s (photo_path), s (status), i (id), i (provider_id)
    $stmt->bind_param("ssisssii", 
        $name, 
        $license_plate, 
        $capacity, 
        $type, 
        $photo_path_to_save, 
        $status, 
        $vehicle_id,
        $provider_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_with_message("Kendaraan **" . htmlspecialchars($name) . "** berhasil diperbarui!", "success");
    } else {
        $stmt->close();
        // Cek jika error karena license_plate sudah ada
        if ($conn->errno == 1062) {
             throw new Exception("Plat nomor **" . htmlspecialchars($license_plate) . "** sudah terdaftar pada kendaraan lain.");
        }
        throw new Exception("Gagal memperbarui data ke database.");
    }

} catch (Exception $e) {
    // Jika update gagal dan ada foto baru diupload, hapus foto barunya
    if ($photo_path_to_save !== $old_photo_path && file_exists(__DIR__ . '/../' . $photo_path_to_save)) {
        unlink(__DIR__ . '/../' . $photo_path_to_save);
    }
    redirect_with_message("Update gagal: " . $e->getMessage(), "danger", $vehicle_id);
}

$conn->close();
?>