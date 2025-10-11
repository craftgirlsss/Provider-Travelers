<?php
// File: process/trip_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php'; 
// !!! PERBAIKAN 1: INCLUDE GENERATOR UUID !!!
require_once __DIR__ . '/../includes/uuid_generator.php'; 

// Validasi ukuran file POST (max 8MB)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 1048576 * 8) {
        $_SESSION['dashboard_message'] = "Ukuran file yang Anda kirim terlalu besar. Batas maksimal adalah 8MB.";
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=trip_create");
        exit();
    }
}

// Cek login & role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}

$user_id_from_session = $_SESSION['user_id'];
$actual_provider_id = null; 

// ----------------------------------------------------
// LOGIKA KRUSIAL: MENDAPATKAN ID PROVIDER YANG BENAR
// ----------------------------------------------------
try {
    $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("i", $user_id_from_session);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    
    if ($result_provider->num_rows > 0) {
        $row = $result_provider->fetch_assoc();
        $actual_provider_id = $row['id']; 
    }
    $stmt_provider->close();
} catch (Exception $e) {
    $_SESSION['dashboard_message'] = "Error Otorisasi: Gagal mengambil ID provider.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=trips");
    exit();
}

if (!$actual_provider_id) {
    $_SESSION['dashboard_message'] = "Error Otorisasi: Akun provider tidak terdaftar dengan benar di tabel providers.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=trips");
    exit();
}
// ----------------------------------------------------

$action = $_POST['action'] ?? '';
$redirect_page = 'trips'; 

if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_trip') {

    $errors = [];
    
    // !!! PERBAIKAN 2: GENERATE UUID !!!
    $uuid = generate_uuid(); 

    // Ambil semua input
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? ''); 
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    
    // KOLOM BARU
    $gathering_point_name = trim($_POST['gathering_point_name'] ?? '');
    $gathering_point_url = trim($_POST['gathering_point_url'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? ''); 
    $return_time = trim($_POST['return_time'] ?? '');       

    $raw_start_date = trim($_POST['start_date'] ?? '');
    $raw_end_date = trim($_POST['end_date'] ?? '');

    $start_date = '';
    $end_date = '';

    if (!empty($raw_start_date)) {
        try {
            $start_date_obj = new DateTime($raw_start_date);
            $start_date = $start_date_obj->format('Y-m-d');
        } catch (Exception $e) {
            $errors[] = "Format tanggal mulai tidak valid.";
        }
    }

    if (!empty($raw_end_date)) {
        try {
            $end_date_obj = new DateTime($raw_end_date);
            $end_date = $end_date_obj->format('Y-m-d');
        } catch (Exception $e) {
            $errors[] = "Format tanggal berakhir tidak valid.";
        }
    }


    $max_quota = (int)($_POST['max_participants'] ?? 0); 
    $price = (float)($_POST['price'] ?? 0);
    $discount_price = (float)($_POST['discount_price'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $booked_participants = 0;
    $approval_status = 'pending'; 

    // Validasi input wajib
    if (empty($title) || empty($location) || empty($description) || empty($duration) ||
        empty($start_date) || empty($end_date) || $max_quota < 1 || $price <= 0 ||
        empty($gathering_point_name) || empty($gathering_point_url) || 
        empty($departure_time) || empty($return_time)) {
        $errors[] = "Semua kolom dengan tanda (*) wajib diisi dengan benar.";
    }

    if ($discount_price > 0 && $discount_price >= $price) {
        $errors[] = "Harga diskon harus lebih rendah dari harga normal.";
    }
    
    // ==========================================================
    // --- PROSES UPLOAD GAMBAR UTAMA ---
    // ==========================================================
    $main_image_path = null;
    $upload_dir = __DIR__ . '/../uploads/trips/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; 
    
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['main_image'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Format file gambar utama tidak didukung.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Ukuran file gambar utama melebihi batas 2MB.";
        } else {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            // Gunakan $uuid di nama file agar unik dan mudah diidentifikasi (opsional, tapi baik)
            $new_file_name = 'trip_' . $uuid . '_main.' . $file_extension;
            $destination_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file['tmp_name'], $destination_path)) {
                $main_image_path = 'uploads/trips/' . $new_file_name;
            } else {
                $errors[] = "Gagal memindahkan file gambar utama.";
            }
        }
    } else {
        $errors[] = "Gambar utama trip wajib diupload.";
    }

    // ==========================================================
    // --- PROSES UPLOAD GAMBAR TAMBAHAN (Multiple) ---
    // ==========================================================
    $additional_images = [];
    if (isset($_FILES['additional_images']) && count($_FILES['additional_images']['name']) > 0) {
        $file_array = $_FILES['additional_images'];
        $num_files = count($file_array['name']);
        
        if ($num_files > 5) {
            $errors[] = "Maksimal hanya 5 foto tambahan yang diperbolehkan.";
            $num_files = 5; 
        }

        for ($i = 0; $i < $num_files; $i++) {
            if ($file_array['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp_name = $file_array['tmp_name'][$i];
                $file_type = $file_array['type'][$i];
                $file_size = $file_array['size'][$i];

                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "Format file gambar tambahan ke-" . ($i+1) . " tidak didukung.";
                    continue;
                }
                if ($file_size > $max_size) {
                    $errors[] = "Ukuran file gambar tambahan ke-" . ($i+1) . " melebihi batas 2MB.";
                    continue;
                }

                $file_extension = pathinfo($file_array['name'][$i], PATHINFO_EXTENSION);
                // Gunakan $uuid di nama file tambahan
                $new_file_name = 'trip_' . $uuid . '_add_' . ($i + 1) . '.' . $file_extension;
                $destination_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp_name, $destination_path)) {
                    $additional_images[] = 'uploads/trips/' . $new_file_name;
                } else {
                    $errors[] = "Gagal memindahkan file gambar tambahan ke-" . ($i+1) . ".";
                }
            }
        }
    }


    // Jika error, kembali ke form
    if (!empty($errors)) {
        if ($main_image_path && file_exists(__DIR__ . '/../' . $main_image_path)) {
            unlink(__DIR__ . '/../' . $main_image_path);
        }
        foreach ($additional_images as $path) {
            if (file_exists(__DIR__ . '/../' . $path)) {
                unlink(__DIR__ . '/../' . $path);
            }
        }

        $_SESSION['dashboard_message'] = implode("<br>", $errors);
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=trip_create");
        exit();
    }

    // Transaksi simpan ke DB
    $conn->begin_transaction();

    try {
        // !!! PERBAIKAN 3: TAMBAHKAN KOLOM UUID DI QUERY !!!
        // Query dengan 18 placeholders (?)
        $stmt = $conn->prepare("INSERT INTO trips (
            uuid, provider_id, title, description, duration, location, 
            gathering_point_name, gathering_point_url, departure_time, return_time, 
            price, max_participants, booked_participants, start_date, end_date, 
            status, approval_status, discount_price
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // !!! PERBAIKAN 4: TAMBAHKAN 's' UNTUK UUID DI BIND_PARAM (18 parameter) !!!
        // Tipe Data Bind: s i s s s s s s s s d i i s s s s d
        $stmt->bind_param("sissssssssdiissdsd", 
            $uuid, // <-- UUID Parameter
            $actual_provider_id,
            $title,
            $description,
            $duration,
            $location,
            $gathering_point_name,
            $gathering_point_url,
            $departure_time,
            $return_time,
            $price,
            $max_quota,
            $booked_participants,
            $start_date,
            $end_date,
            $status,
            $approval_status,
            $discount_price
        );

        if ($stmt->execute()) {
            $trip_id = $conn->insert_id;
            $stmt->close();

            // Simpan Gambar Utama (is_main = 1)
            if ($main_image_path) {
                $is_main = 1;
                $stmt_img = $conn->prepare("INSERT INTO trip_images (trip_id, image_url, is_main) VALUES (?, ?, ?)");
                $stmt_img->bind_param("isi", $trip_id, $main_image_path, $is_main);
                $stmt_img->execute();
                $stmt_img->close();
            }

            // Simpan Gambar Tambahan (is_main = 0)
            if (!empty($additional_images)) {
                $is_main = 0;
                $stmt_add_img = $conn->prepare("INSERT INTO trip_images (trip_id, image_url, is_main) VALUES (?, ?, ?)");
                foreach ($additional_images as $path) {
                    $stmt_add_img->bind_param("isi", $trip_id, $path, $is_main);
                    $stmt_add_img->execute();
                }
                $stmt_add_img->close();
            }


            $conn->commit();

            $_SESSION['dashboard_message'] = "Trip baru '$title' berhasil diajukan untuk moderasi.";
            $_SESSION['dashboard_message_type'] = "success";
            header("Location: /dashboard?p=trips");
            exit();
        } else {
            throw new Exception("Gagal menyimpan data trips: " . $conn->error);
        }

    } catch (Exception $e) {
        $conn->rollback();
        
        $all_uploaded_files = array_merge((array)$main_image_path, $additional_images);
        foreach ($all_uploaded_files as $path) {
            if ($path && file_exists(__DIR__ . '/../' . $path)) {
                unlink(__DIR__ . '/../' . $path);
            }
        }

        $_SESSION['dashboard_message'] = "Terjadi kesalahan sistem: " . $e->getMessage();
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=trip_create");
        exit();
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'delete_trip') {
    // ... (Logika delete_trip tetap sama) ...
    
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    $redirect_page = 'trips';
    $message = "Aksi gagal.";
    $message_type = "danger";

    if ($trip_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE trips SET is_deleted = 1, updated_at = NOW() WHERE id = ? AND provider_id = ?");
            $stmt->bind_param("ii", $trip_id, $actual_provider_id); 
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Trip berhasil diarsip dan disembunyikan dari daftar aktif.";
                $message_type = "success";
            } else {
                $message = "Gagal mengarsip trip. ID tidak ditemukan atau Anda tidak memiliki izin.";
                $message_type = "danger";
            }
            $stmt->close();

        } catch (Exception $e) {
            $message = "Kesalahan database saat mengarsip trip: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "ID Trip tidak valid.";
        $message_type = "danger";
    }
    
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $message_type;
    header("Location: /dashboard?p=" . $redirect_page);
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'restore_trip') {
    // ... (Logika restore_trip tetap sama) ...
    
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    $redirect_page = 'trip_archive';
    $message = "Aksi gagal.";
    $message_type = "danger";

    if ($trip_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE trips SET is_deleted = 0, updated_at = NOW() WHERE id = ? AND provider_id = ?");
            $stmt->bind_param("ii", $trip_id, $actual_provider_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Trip berhasil dikembalikan ke daftar aktif.";
                $message_type = "success";
            } else {
                $message = "Gagal mengembalikan trip. ID tidak ditemukan atau Anda tidak memiliki izin.";
                $message_type = "danger";
            }
            $stmt->close();

        } catch (Exception $e) {
            $message = "Kesalahan database saat mengembalikan trip: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "ID Trip tidak valid.";
        $message_type = "danger";
    }
    
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $message_type;
    header("Location: /dashboard?p=" . $redirect_page);
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'update_trip') {
    // ... (Logika update_trip tetap sama) ...

    $errors = [];
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    $existing_image_url = null;
    $redirect_to_form = "trip_edit&id=" . $trip_id; // Set redirect form
    
    if ($trip_id <= 0) {
        $errors[] = "ID Trip tidak valid untuk pembaruan.";
    } else {
        $stmt_check = $conn->prepare("SELECT trips.id, booked_participants, image_url FROM trips LEFT JOIN trip_images ON trips.id = trip_images.trip_id AND trip_images.is_main = 1 WHERE trips.id = ? AND provider_id = ?");
        $stmt_check->bind_param("ii", $trip_id, $actual_provider_id); 
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows !== 1) {
            $errors[] = "Trip tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.";
        } else {
            $old_data = $result_check->fetch_assoc();
            $booked_participants = $old_data['booked_participants'];
            $existing_image_url = $old_data['image_url']; 
        }
        $stmt_check->close();
    }


    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? ''); // Perbaikan: gunakan 'location'
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');

    // Kebutuhan baru di update
    $gathering_point_name = trim($_POST['gathering_point_name'] ?? '');
    $gathering_point_url = trim($_POST['gathering_point_url'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? ''); 
    $return_time = trim($_POST['return_time'] ?? '');

    $raw_start_date = $_POST['start_date'] ?? '';
    $raw_end_date = $_POST['end_date'] ?? '';

    $start_date = ''; 
    $end_date = '';
    if (!empty($raw_start_date) && !empty($raw_end_date)) {
        try {
            $start_date = (new DateTime($raw_start_date))->format('Y-m-d');
            $end_date = (new DateTime($raw_end_date))->format('Y-m-d');
        } catch (Exception $e) {
            $errors[] = "Format tanggal mulai atau akhir tidak valid.";
        }
    }

    $max_quota = (int)($_POST['max_participants'] ?? 0); // Perbaikan: gunakan 'max_participants'
    $price = (float)($_POST['price'] ?? 0);
    $discount_price = (float)($_POST['discount_price'] ?? 0);
    $status = $_POST['status'] ?? 'draft';

    // 2. Validasi Input
    if (empty($title) || empty($location) || empty($description) || empty($duration) || empty($start_date) || empty($end_date) || $max_quota < 1 || $price <= 0 ||
        empty($gathering_point_name) || empty($gathering_point_url) || empty($departure_time) || empty($return_time)) {
        $errors[] = "Semua kolom dengan tanda (*) harus diisi dengan benar.";
    }
    if ($max_quota < $booked_participants) {
        $errors[] = "Kuota maksimal tidak boleh kurang dari jumlah peserta yang sudah booking ($booked_participants).";
    }
    if ($discount_price > 0 && $discount_price >= $price) {
        $errors[] = "Harga diskon harus lebih rendah dari harga normal.";
    }
    
    $image_path = null;
    $new_image_uploaded = false;

    // 3. Proses Upload Gambar BARU (Opsional)
    if (empty($errors) && isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        // (Gunakan logic upload yang sama dari create_trip.php)
        $file = $_FILES['main_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; 
        $upload_dir = __DIR__ . '/../uploads/trips/'; 
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Format file gambar tidak didukung.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Ukuran file gambar melebihi batas 2MB.";
        } else {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('trip_') . '.' . $file_extension;
            $destination_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file['tmp_name'], $destination_path)) {
                $image_path = 'uploads/trips/' . $new_file_name;
                $new_image_uploaded = true;
            } else {
                $errors[] = "Gagal memindahkan file gambar yang diupload.";
            }
        }
    }


    // 4. Simpan ke Database menggunakan Transaksi
    if (empty($errors)) {
        
        $conn->begin_transaction();

        try {
            // A. UPDATE ke Tabel trips (Tambahkan kolom baru di SET)
            $stmt = $conn->prepare("UPDATE trips SET 
                title = ?, description = ?, duration = ?, location = ?, 
                gathering_point_name = ?, gathering_point_url = ?, departure_time = ?, return_time = ?, /* BARU */
                price = ?, max_participants = ?, start_date = ?, end_date = ?, 
                status = ?, discount_price = ?, updated_at = NOW()
                WHERE id = ? AND provider_id = ?");
            
            // Tipe Data Bind (14 kolom di SET + 2 kolom di WHERE = 16 parameter)
            // String: s s s s s s s s d i s s s d i i
            $stmt->bind_param("ssssssssdsdsii", 
                $title, $description, $duration, $location, 
                $gathering_point_name, $gathering_point_url, $departure_time, $return_time, // BARU
                $price, $max_quota, $start_date, $end_date, 
                $status, $discount_price,
                $trip_id, $actual_provider_id
            );

            if ($stmt->execute()) {
                $stmt->close();
                
                // B. UPDATE/INSERT ke Tabel trip_images
                if ($new_image_uploaded) {
                    $image_path_full = __DIR__ . '/../' . $existing_image_url;

                    $stmt_del = $conn->prepare("DELETE FROM trip_images WHERE trip_id = ? AND is_main = 1");
                    $stmt_del->bind_param("i", $trip_id);
                    $stmt_del->execute();
                    $stmt_del->close();
                    
                    if ($existing_image_url && file_exists($image_path_full)) {
                        unlink($image_path_full);
                    }
                    
                    $is_main = 1; 
                    $stmt_img = $conn->prepare("INSERT INTO trip_images (trip_id, image_url, is_main) VALUES (?, ?, ?)");
                    $stmt_img->bind_param("isi", $trip_id, $image_path, $is_main);
                    
                    if (!$stmt_img->execute()) {
                         throw new Exception("Gagal menyimpan data gambar baru.");
                    }
                    $stmt_img->close();
                }
                
                $conn->commit();

                $_SESSION['dashboard_message'] = "Trip '$title' berhasil diperbarui.";
                $_SESSION['dashboard_message_type'] = "success";
                
                header("Location: /dashboard?p=trips"); 
                exit();
                
            } else {
                 throw new Exception("Gagal memperbarui data trips: " . $conn->error);
            }

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Terjadi kesalahan sistem saat memperbarui trip: " . $e->getMessage();
            
            if ($image_path && file_exists(__DIR__ . '/../' . $image_path) && $new_image_uploaded) {
                unlink(__DIR__ . '/../' . $image_path); 
            }
        }
    }
    
    $_SESSION['dashboard_message'] = implode("<br>", $errors);
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=" . $redirect_to_form); 
    exit();
}
// ... (Logic submit_for_verification tetap sama) ...
elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'submit_for_verification') {
        
    $redirect_page = 'profile';
    $message = "Gagal mengajukan verifikasi.";
    $message_type = "danger";
    
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $conn->prepare("UPDATE providers 
                               SET verification_status = 'pending', updated_at = NOW() 
                               WHERE user_id = ? 
                               AND verification_status IN ('unverified', 'rejected')"); 
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "Pengajuan verifikasi berhasil dikirim! Admin akan meninjau data Anda.";
            $message_type = "success";
        } else {
            $message = "Pengajuan gagal. Status verifikasi Anda saat ini adalah 'pending' atau 'verified'. Harap tunggu respons Admin.";
            $message_type = "info";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $message = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
    
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $message_type;
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}


$conn->close();
?>