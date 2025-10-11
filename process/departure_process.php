<?php
// File: process/departure_process.php
// Menangani proses pembuatan dan update jadwal keberangkatan (Trip Departures).

session_start();
require_once __DIR__ . '/../config/db_config.php'; 

// Cek Otorisasi - Menggunakan variabel sesi yang sudah tersedia dan terjamin dari dashboard
$actual_provider_id = $_SESSION['actual_provider_id'] ?? null; 
$user_role = $_SESSION['user_role'] ?? null;

if (!$actual_provider_id || $user_role !== 'provider') {
    $_SESSION['dashboard_message'] = "Akses Ditolak: Anda harus login sebagai Provider.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /login.php"); 
    exit();
}

$errors = [];
$action = $_POST['action'] ?? '';
$redirect_url = "/dashboard?p=departures"; 

// ==========================================================
// --- AKSI: CREATE SCHEDULE ---
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_schedule') {
    
    // Ambil input dari form
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $license_plate = strtoupper(trim($_POST['license_plate'] ?? ''));
    $departure_date = trim($_POST['departure_date'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? '');
    $driver_id = (int)($_POST['driver_id'] ?? 0); // Mengambil ID Driver yang dipilih
    
    // Simpan data POST ke session untuk redisplay jika terjadi error
    $_SESSION['form_data'] = $_POST;
    
    // 1. Validasi Input Data Jadwal Dasar
    if ($trip_id <= 0) $errors[] = "Trip wajib dipilih.";
    if (empty($vehicle_type)) $errors[] = "Jenis kendaraan wajib diisi.";
    if (empty($license_plate)) $errors[] = "Nomor Polisi wajib diisi.";
    if ($driver_id <= 0) $errors[] = "Driver wajib dipilih.";
    
    // Validasi Tanggal dan Waktu
    if (empty($departure_date) || empty($departure_time)) {
        $errors[] = "Tanggal dan Waktu keberangkatan wajib diisi.";
    } else {
        $departure_datetime_str = $departure_date . ' ' . $departure_time;
        $departure_timestamp = strtotime($departure_datetime_str);
        $now_timestamp = time();

        if ($departure_timestamp === false) {
            $errors[] = "Format Tanggal/Waktu tidak dapat diproses.";
        } elseif ($departure_timestamp < $now_timestamp) {
            $errors[] = "Jadwal keberangkatan tidak boleh berada di masa lalu.";
        }
    }
    
    // ==========================================================
    // --- 1.5 VALIDASI DUPLIKASI JADWAL KEBERANGKATAN (BARU) ---
    // ==========================================================
    if (empty($errors) && $trip_id > 0) {
        try {
            // Asumsi nama tabel adalah trip_departures
            $stmt_check = $conn->prepare("
                SELECT COUNT(id) FROM trip_departures 
                WHERE trip_id = ? AND provider_id = ?
            ");
            $stmt_check->bind_param("ii", $trip_id, $actual_provider_id);
            $stmt_check->execute();
            $result_count = $stmt_check->get_result()->fetch_row();
            $count = $result_count[0];
            $stmt_check->close();

            if ($count > 0) {
                $errors[] = "Trip ini ($trip_id) sudah memiliki jadwal keberangkatan yang dibuat oleh Anda. Satu Trip hanya dapat memiliki satu jadwal.";
            }
        } catch (Exception $e) {
            $errors[] = "Gagal memverifikasi duplikasi jadwal: " . $e->getMessage();
        }
    }
    // ==========================================================


    $driver_uuid = null;
    
    // 2. Ambil Driver UUID (Hanya jika tidak ada error validasi lain)
    if (empty($errors) && $driver_id > 0) {
        try {
            $stmt_driver_uuid = $conn->prepare("SELECT driver_uuid FROM drivers WHERE id = ? AND provider_id = ? AND is_active = 1");
            $stmt_driver_uuid->bind_param("ii", $driver_id, $actual_provider_id);
            $stmt_driver_uuid->execute();
            $result_uuid = $stmt_driver_uuid->get_result();
            
            if ($row = $result_uuid->fetch_assoc()) {
                $driver_uuid = $row['driver_uuid']; 
            } else {
                $errors[] = "Driver ID tidak valid, tidak aktif, atau bukan milik Anda.";
            }
            $stmt_driver_uuid->close();
        } catch (Exception $e) {
            $errors[] = "Gagal mengambil data driver: " . $e->getMessage();
        }
    }

    // 3. Jika ada error, redirect kembali ke form create
    if (!empty($errors)) {
        $_SESSION['dashboard_message'] = implode("<br>", $errors);
        $_SESSION['dashboard_message_type'] = "danger";
        // Redirect kembali ke form create
        $redirect_url_error = "/dashboard?p=departure_create" . ($trip_id ? "&trip_id=" . $trip_id : "");
        header("Location: " . $redirect_url_error);
        exit();
    }

    // 4. Proses Insert ke Database
    try {
        // --- Pembuatan Tracking Link Unik ---
        $unique_hash = bin2hex(random_bytes(8)); 
        $full_tracking_link = "/tracking?trip_id=" . $trip_id . "&ref=" . $unique_hash; 

        $stmt = $conn->prepare("
            INSERT INTO trip_departures 
            (trip_id, provider_id, vehicle_type, license_plate, departure_date, departure_time, driver_uuid, tracking_link)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("iissssss", // Tipe Binding Sesuai Perbaikan Sebelumnya
            $trip_id,
            $actual_provider_id,
            $vehicle_type,
            $license_plate,
            $departure_date,
            $departure_time,
            $driver_uuid, 
            $full_tracking_link
        );

        if ($stmt->execute()) {
            $departure_id = $conn->insert_id; // Dapatkan ID Departure yang baru dibuat
            $stmt->close();
            
            // 5. LOGIC NOTIFIKASI PENGINGAT H-5
            $reminder_datetime = date('Y-m-d H:i:s', $departure_timestamp - (5 * 24 * 60 * 60)); 
            
            $notification_message = "⚠️ Peringatan Jadwal H-5: Trip ID #{$trip_id} (Kendaraan: {$license_plate}) akan berangkat pada " . date('d M Y H:i', $departure_timestamp) . ". Mohon pastikan semua data logistik sudah final.";
            $notification_link = "/dashboard?p=departure_edit&id=" . $departure_id; 

            $stmt_notif = $conn->prepare("
                INSERT INTO provider_notifications 
                (provider_id, type, related_id, message, link, scheduled_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $type = 'schedule_reminder';
            // Tipe binding "isssss" atau "isissi" untuk 6 variabel
            $stmt_notif->bind_param("isissi", // Menggunakan isissi (provider_id, type, departure_id, message, link, scheduled_at)
                $actual_provider_id,
                $type,
                $departure_id,
                $notification_message,
                $notification_link,
                $reminder_datetime
            );
            $stmt_notif->execute();
            $stmt_notif->close();
            
            // 6. Sukses dan Redirect
            unset($_SESSION['form_data']); 
            $_SESSION['dashboard_message'] = "Jadwal keberangkatan ID #{$departure_id} berhasil dibuat. Pengingat H-5 telah diatur.";
            $_SESSION['dashboard_message_type'] = "success";
            header("Location: " . $redirect_url);
            exit();
        } else {
             throw new Exception("Gagal menyimpan jadwal: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['dashboard_message'] = "Terjadi kesalahan sistem saat menyimpan data: " . $e->getMessage();
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: " . $redirect_url);
        exit();
    }
}

// Jika tidak ada aksi yang valid, kembalikan ke daftar jadwal
header("Location: " . $redirect_url);
exit();