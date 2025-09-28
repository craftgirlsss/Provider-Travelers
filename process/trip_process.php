<?php
// File: process/trip_process.php
session_start();

// Path relatif ke config dan library
require_once __DIR__ . '/../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    // 1MB = 1048576 bytes
    // Jika content-length terlalu besar, redirect
    if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 1048576 * 8) { 
         $_SESSION['dashboard_message'] = "Ukuran file yang Anda kirim terlalu besar. Batas maksimal adalah 8MB.";
         $_SESSION['dashboard_message_type'] = "danger";
         header("Location: /dashboard?p=trip_create");
         exit();
    }
}

// Cek apakah user sudah login sebagai provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}

$provider_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
echo "Start Date POST: " . var_export($_POST['start_date'], true) . "<br>";
echo "End Date POST: " . var_export($_POST['end_date'], true) . "<br>";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_trip') {

    $errors = [];

    // Ambil dan bersihkan data
    $title = trim($_POST['title'] ?? '');
    // Mapping input 'destination' dari form ke kolom 'location' di DB
    $location = trim($_POST['destination'] ?? ''); 
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    
    // Pastikan tanggal di-trim untuk menghilangkan spasi
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    
    // Konversi dan Validasi numerik
    $max_quota = (int)($_POST['max_quota'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $discount_price = (float)($_POST['discount_price'] ?? 0);
    
    $status = $_POST['status'] ?? 'draft';
    
    // Variabel yang dibutuhkan oleh DB tetapi tidak dari form
    $booked_participants = 0; // Default: 0 saat trip dibuat

    // 1. Validasi Input
    // Tambahkan validasi tanggal untuk mencegah error MySQL yang tidak spesifik
    if (empty($title) || empty($location) || empty($description) || empty($duration) || empty($start_date) || empty($end_date) || $max_quota < 1 || $price <= 0) {
        $errors[] = "Semua kolom dengan tanda (*) harus diisi dengan benar.";
    }
    if ($discount_price > 0 && $discount_price >= $price) {
        $errors[] = "Harga diskon harus lebih rendah dari harga normal.";
    }
    
    $image_path = null;

    // 2. Proses Upload Gambar
    if (empty($errors) && isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['main_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Format file gambar tidak didukung (gunakan JPG atau PNG).";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Ukuran file gambar melebihi batas 2MB.";
        } else {
            // Buat folder uploads/trips jika belum ada (RELATIF DARI ROOT PROYEK)
            $upload_dir = __DIR__ . '/../uploads/trips/'; 
            if (!is_dir($upload_dir)) {
                // Pastikan izin 0777 untuk debugging, ganti 0755 saat produksi
                mkdir($upload_dir, 0777, true); 
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('trip_') . '.' . $file_extension;
            $destination_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file['tmp_name'], $destination_path)) {
                $image_path = 'uploads/trips/' . $new_file_name; // Path yang disimpan di DB
            } else {
                $errors[] = "Gagal memindahkan file gambar yang diupload. Cek izin folder.";
            }
        }
    } else {
        $errors[] = "Gambar utama trip wajib diupload.";
    }

    // 3. Simpan ke Database menggunakan Transaksi
    if (empty($errors)) {
        
        $conn->begin_transaction(); 

        try {
            // A. INSERT ke Tabel trips
            // Urutan Kolom: provider_id, title, description, duration, location, price, 
            //               max_participants, booked_participants, start_date, end_date, 
            //               status, discount_price 
            $stmt = $conn->prepare("INSERT INTO trips (
                provider_id, title, description, duration, location, price, 
                max_participants, booked_participants, start_date, end_date, 
                status, discount_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Tipe Data Bind (Total 12 parameter): 
            // i s s s s d i i s s s d 
            //                               provider  title  desc  dur  loc  price  maxQ  bookQ  startD  endD  status  discP
            $stmt->bind_param("issssdissdsd", 
                $provider_id,           // i (INT)
                $title,                 // s (VARCHAR)
                $description,           // s (TEXT)
                $duration,              // s (VARCHAR)
                $location,              // s (VARCHAR)
                $price,                 // d (DECIMAL)
                $max_quota,             // i (INT) <-- max_participants
                $booked_participants,   // i (INT, 0)
                $start_date,            // s (DATE)
                $end_date,              // s (DATE)
                $status,                // s (VARCHAR)
                $discount_price         // d (DECIMAL)
            );


            if ($stmt->execute()) {
                $trip_id = $conn->insert_id; // Ambil ID Trip yang baru dibuat
                $stmt->close();
                
                // B. INSERT ke Tabel trip_images (Hanya jika gambar berhasil di-upload)
                if ($image_path) {
                    $is_main = 1; // Tentukan sebagai gambar utama
                    
                    $stmt_img = $conn->prepare("INSERT INTO trip_images (trip_id, image_url, is_main) VALUES (?, ?, ?)");
                    
                    // Tipe Data Bind: i s i
                    $stmt_img->bind_param("isi", 
                        $trip_id, 
                        $image_path,
                        $is_main 
                    );
                    
                    if (!$stmt_img->execute()) {
                         throw new Exception("Gagal menyimpan data gambar ke trip_images.");
                    }
                    $stmt_img->close();
                }
                
                $conn->commit(); // Commit hanya jika kedua INSERT sukses

                $_SESSION['dashboard_message'] = "Trip baru '$title' berhasil dibuat dan dipublikasikan.";
                $_SESSION['dashboard_message_type'] = "success";
                
                header("Location: /dashboard?p=trips"); 
                exit();
                
            } else {
                 // MySQL error jika bind_param berhasil tapi ada masalah di DB
                 throw new Exception("Gagal menyimpan data trips: " . $conn->error);
            }

        } catch (Exception $e) {
            $conn->rollback(); 
            // Tangkap error pergeseran bind_param atau kesalahan tanggal di sini
            $errors[] = "Terjadi kesalahan sistem saat menyimpan trip: " . $e->getMessage();
            
            // Hapus file yang mungkin sudah terupload
            if ($image_path && file_exists(__DIR__ . '/../' . $image_path)) {
                unlink(__DIR__ . '/../' . $image_path); 
            }
        }
    }
    
    // Jika ada error (Database/Validasi/Upload Error)
    $_SESSION['dashboard_message'] = implode("<br>", $errors);
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=trip_create"); 
    exit();
}

$conn->close();
?>