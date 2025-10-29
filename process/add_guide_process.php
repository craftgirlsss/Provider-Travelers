<?php
session_start();

// Asumsi db_config berada di root/config/db_config.php
require_once __DIR__ . '/../config/db_config.php'; 

// Fungsi untuk redirect dengan pesan
function redirect_with_message($message, $type = "danger") {
    $_SESSION['guide_message'] = $message;
    $_SESSION['guide_message_type'] = $type;
    header("Location: ../dashboard.php?p=tour_guides"); // Redirect ke daftar pemandu
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_message("Metode permintaan tidak valid.", "warning");
}

// 1. Ambil dan Bersihkan Data Input
$provider_id = $_POST['provider_id'] ?? null;
$name = trim($_POST['name'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$specialization = trim($_POST['specialization'] ?? null);
$status = $_POST['status'] ?? 'active';

// 2. Validasi Dasar
if (!$provider_id || $_SESSION['actual_provider_id'] != $provider_id) {
    redirect_with_message("Error otorisasi: ID Provider tidak valid.", "danger");
}
if (empty($name) || empty($phone_number)) {
    redirect_with_message("Nama dan Nomor Telepon pemandu wajib diisi.");
}
if (!in_array($status, ['active', 'inactive'])) {
    $status = 'active';
}

$photo_path = null;

// 3. Proses Upload Foto Profil
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_photo'];
    $max_size = 2 * 1024 * 1024; // 2 MB
    $allowed_types = ['image/jpeg', 'image/png'];
    $upload_dir = __DIR__ . '/../uploads/guide_photos/';

    // Cek ukuran file
    if ($file['size'] > $max_size) {
        redirect_with_message("Ukuran foto profil maksimal 2MB.");
    }

    // Cek tipe file
    if (!in_array($file['type'], $allowed_types)) {
        redirect_with_message("Format foto tidak didukung. Gunakan JPG atau PNG.");
    }
    
    // Pastikan direktori ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate nama file unik
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('guide_') . '.' . $extension;
    $target_file = $upload_dir . $file_name;
    
    // Pindahkan file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Path relatif untuk disimpan di database
        $photo_path = 'uploads/guide_photos/' . $file_name;
    } else {
        redirect_with_message("Gagal mengupload file foto. Periksa izin folder.");
    }
} else {
    // Jika tidak ada file dan ini wajib
    redirect_with_message("Foto profil wajib diupload.");
}

// 4. Insert ke Database
try {
    $stmt = $conn->prepare("INSERT INTO tour_guides (provider_id, name, phone_number, specialization, profile_photo_path, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("isssss", $provider_id, $name, $phone_number, $specialization, $photo_path, $status);

    if ($stmt->execute()) {
        $stmt->close();
        redirect_with_message("Pemandu Wisata **" . htmlspecialchars($name) . "** berhasil ditambahkan!", "success");
    } else {
        $stmt->close();
        throw new Exception("Gagal menyimpan data ke database.");
    }

} catch (Exception $e) {
    // Hapus file yang sudah diupload jika insert gagal
    if ($photo_path && file_exists(__DIR__ . '/../' . $photo_path)) {
        unlink(__DIR__ . '/../' . $photo_path);
    }
    redirect_with_message("Pendaftaran gagal: " . $e->getMessage());
}

$conn->close();
?>