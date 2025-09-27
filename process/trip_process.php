<?php
// File: process/trip_process.php
session_start();

// Path relatif ke config dan library
require_once __DIR__ . '/../config/db_config.php';

// Cek apakah user sudah login sebagai provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}

$provider_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_trip') {

    $errors = [];

    // Ambil dan bersihkan data
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['destination'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $max_quota = (int)($_POST['max_quota'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $discount_price = (float)($_POST['discount_price'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $booked_participants = 0;

    // 1. Validasi Input
    if (empty($title) || empty($location) || empty($description) || empty($start_date) || empty($end_date) || $max_quota < 1 || $price <= 0) {
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
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate nama file unik
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('trip_') . '.' . $file_extension;
            $destination_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file['tmp_name'], $destination_path)) {
                $image_path = 'uploads/trips/' . $new_file_name; // Path yang disimpan di DB
            } else {
                $errors[] = "Gagal memindahkan file gambar yang diupload.";
            }
        }
    } else {
        $errors[] = "Gambar utama trip wajib diupload.";
    }

    // 3. Simpan ke Database menggunakan Transaksi
    if (empty($errors)) {
        
        $conn->begin_transaction(); // Mulai Transaksi

        try {
            // A. INSERT ke Tabel trips
            // Hapus kolom main_image dari query INSERT trips
            $stmt = $conn->prepare("INSERT INTO trips (
                provider_id, 
                title, 
                description, 
                duration, 
                location, 
                price, 
                max_participants, 
                booked_participants, 
                start_date, 
                end_date, 
                status, 
                discount_price 
                /* Kolom 'created_at', 'is_approved', 'rejection_reason' tidak perlu dimasukkan karena punya nilai default/akan diisi belakangan */
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Tipe Data Bind (i:int, s:string, d:decimal)
            // Urutan harus SAMA dengan kolom di atas:
            $stmt->bind_param("issssdissdsd", 
                $provider_id,           // i (INT)
                $title,                 // s (VARCHAR)
                $description,           // s (TEXT)
                $duration,              // s (VARCHAR)
                $location,              // s (VARCHAR)
                $price,                 // d (DECIMAL)
                $max_quota,             // i (INT)
                $booked_participants,   // i (INT, default 0)
                $start_date,            // s (DATE) <--- PASTIKAN INI 's'
                $end_date,              // s (DATE) <--- PASTIKAN INI 's'
                $status,                // s (VARCHAR)
                $discount_price         // d (DECIMAL)
            );

            // Karena kita menggunakan booked_participants di query, kita perlu mendefinisikannya.
            $booked_participants = 0; // Tambahkan ini sebelum $stmt->prepare()

            if ($stmt->execute()) {
                $trip_id = $conn->insert_id; // Ambil ID Trip yang baru dibuat
                $stmt->close();
                
                // B. INSERT ke Tabel trip_images (Hanya jika gambar berhasil di-upload)
                if ($image_path) {
                    $is_main = 1; // Tentukan sebagai gambar utama
                    
                    $stmt_img = $conn->prepare("INSERT INTO trip_images (trip_id, image_url, is_main) VALUES (?, ?, ?)");
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

                $_SESSION['dashboard_message'] = "Trip baru '$title' berhasil dibuat dan gambar terkait disimpan.";
                $_SESSION['dashboard_message_type'] = "success";
                
                header("Location: /dashboard?p=trips"); 
                exit();
                
            } else {
                 throw new Exception("Gagal menyimpan data trips: " . $conn->error);
            }

        } catch (Exception $e) {
            $conn->rollback(); // Rollback jika ada error di manapun
            $errors[] = "Terjadi kesalahan sistem saat menyimpan trip: " . $e->getMessage();
            
            // Hapus file yang mungkin sudah terupload
            if ($image_path && file_exists(__DIR__ . '/../' . $image_path)) {
                unlink(__DIR__ . '/../' . $image_path); 
            }
        }
    }
    
    // ... (Logic error di bagian bawah tetap sama) ...
    $_SESSION['dashboard_message'] = implode("<br>", $errors);
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=trip_create"); 
    exit();
}

$conn->close();
?>