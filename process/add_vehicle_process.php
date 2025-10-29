<?php
session_start();

require_once __DIR__ . '/../config/db_config.php'; 

// Fungsi untuk redirect dengan pesan
function redirect_with_message($message, $type = "danger") {
    $_SESSION['vehicle_message'] = $message;
    $_SESSION['vehicle_message_type'] = $type;
    // Redirect ke halaman daftar kendaraan
    header("Location: ../dashboard.php?p=vehicles"); 
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_message("Metode permintaan tidak valid.", "warning");
}

// 1. Ambil dan Bersihkan Data Input
$provider_id = $_POST['provider_id'] ?? null;
$name = trim($_POST['name'] ?? '');

// Membersihkan dan mengubah Plat Nomor menjadi huruf kapital untuk konsistensi di database
$license_plate = strtoupper(trim($_POST['license_plate'] ?? '')); 

$capacity = (int)($_POST['capacity'] ?? 0);
$type = $_POST['type'] ?? 'car';
$status = $_POST['status'] ?? 'available';

// 2. Validasi Dasar
if (!$provider_id || $_SESSION['actual_provider_id'] != $provider_id) {
    redirect_with_message("Error otorisasi: ID Provider tidak valid.", "danger");
}

// Validasi Plat Nomor: memastikan tidak kosong dan minimal panjang 3 karakter
if (empty($name) || empty($license_plate) || $capacity <= 0 || strlen($license_plate) < 3) {
    redirect_with_message("Semua field wajib diisi. Pastikan Plat Nomor diisi dengan benar.");
}

if (!in_array($status, ['available', 'maintenance'])) {
    $status = 'available';
}

$photo_path = null;

// 3. Proses Upload Foto Kendaraan
if (isset($_FILES['vehicle_photo']) && $_FILES['vehicle_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['vehicle_photo'];
    $max_size = 5 * 1024 * 1024; // 5 MB
    $allowed_types = ['image/jpeg', 'image/png'];
    $upload_dir = __DIR__ . '/../uploads/vehicle_photos/'; 

    if ($file['size'] > $max_size) {
        redirect_with_message("Ukuran foto kendaraan maksimal 5MB.");
    }

    if (!in_array($file['type'], $allowed_types)) {
        redirect_with_message("Format foto tidak didukung. Gunakan JPG atau PNG.");
    }
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('vehicle_') . '.' . $extension;
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Path relatif untuk disimpan di database. Contoh: uploads/vehicle_photos/guide_6531f821e2c60.png
        $photo_path = 'uploads/vehicle_photos/' . $file_name; 
    } else {
        redirect_with_message("Gagal mengupload file foto. Periksa izin folder.");
    }
} else {
    redirect_with_message("Foto kendaraan wajib diupload.");
}

// 4. Insert ke Database
try {
    $stmt = $conn->prepare("INSERT INTO vehicles (provider_id, name, license_plate, capacity, type, vehicle_photo_path, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    // PERBAIKAN KRUSIAL: String format "ississs"
    // provider_id (i), name (s), license_plate (s), capacity (i), type (s), vehicle_photo_path (s), status (s)
    $stmt->bind_param("ississs", 
        $provider_id, 
        $name, 
        $license_plate, // <--- S di sini (mengatasi masalah Plat Nomor 0)
        $capacity,      // <--- I di sini (capacity adalah integer)
        $type, 
        $photo_path,    // <--- S di sini (mengatasi masalah Path Foto 0)
        $status
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_with_message("Kendaraan " . htmlspecialchars($name) . " berhasil ditambahkan!", "success");
    } else {
        $stmt->close();
        
        // Logika error tetap sama
        if ($conn->errno == 1062) {
             throw new Exception("Plat nomor " . htmlspecialchars($license_plate) . " sudah terdaftar. Silakan gunakan plat nomor lain.");
        }
        throw new Exception("Gagal menyimpan data ke database. Error Code: " . $conn->errno);
    }

} catch (Exception $e) {
    // Hapus file yang sudah diupload jika insert gagal
    if ($photo_path && file_exists(__DIR__ . '/../' . $photo_path)) {
        // Hapus file yang sudah terupload jika ada kegagalan SQL
        unlink(__DIR__ . '/../' . $photo_path);
    }
    redirect_with_message("Pendaftaran gagal: " . $e->getMessage());
}
