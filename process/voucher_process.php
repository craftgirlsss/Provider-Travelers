<?php
// File: process/voucher_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/uuid_generator.php'; 

$user_uuid_from_session = $_SESSION['user_uuid'] ?? null;
$user_role_from_session = $_SESSION['user_role'] ?? null;

// Cek login & role (Provider)
if (!$user_uuid_from_session || $user_role_from_session !== 'provider') {
    $_SESSION['message'] = "Akses ditolak. Silakan login sebagai Provider.";
    $_SESSION['message_type'] = "danger";
    header("Location: /login");
    exit();
}

$user_id_from_session = null; // ID integer dari tabel users
$actual_provider_id = null; // ID integer dari tabel providers
$redirect_page = 'vouchers'; 

// --- 1. MENDAPATKAN ID PROVIDER (HARUS DICARI LAGI karena ini file terpisah) ---
try {
    // A. Ambil ID integer dari tabel users menggunakan UUID
    $stmt_user = $conn->prepare("SELECT id FROM users WHERE uuid = ?");
    $stmt_user->bind_param("s", $user_uuid_from_session); 
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows > 0) {
        $user_id_from_session = $result_user->fetch_assoc()['id'];
    }
    $stmt_user->close();

    if ($user_id_from_session) {
        // B. Ambil provider_id dari tabel providers menggunakan user_id integer
        $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
        $stmt_provider->bind_param("i", $user_id_from_session); 
        $stmt_provider->execute();
        $result_provider = $stmt_provider->get_result();
        
        if ($result_provider->num_rows > 0) {
            $actual_provider_id = $result_provider->fetch_assoc()['id'];
        }
        $stmt_provider->close();
    }

} catch (Exception $e) {
    $_SESSION['dashboard_message'] = "Error Otorisasi Database.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}

if (!$actual_provider_id) {
    $_SESSION['dashboard_message'] = "Error Otorisasi: Akun provider tidak terdaftar.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}
// ------------------------------------------------------------------

$action = $_POST['action'] ?? '';

// ==========================================================
// --- AKSI: CREATE VOUCHER ---
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_voucher') {

    $errors = [];
    $redirect_to_form = "voucher_create";

    // Ambil dan bersihkan input
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = $_POST['type'] ?? '';
    $value = (float)($_POST['value'] ?? 0);
    $max_usage = (int)($_POST['max_usage'] ?? 0);
    $min_purchase = (float)($_POST['min_purchase'] ?? 0);
    $valid_until_raw = trim($_POST['valid_until'] ?? '');
    $valid_until = null; // Inisialisasi ke NULL
    $is_active = (int)($_POST['is_active'] ?? 0);
    $uuid = generate_uuid(); // Asumsi fungsi generate_uuid() tersedia

    // Simpan data POST ke session untuk redisplay jika terjadi error
    $_SESSION['form_data'] = $_POST;

    // --- Validasi Input ---
    if (empty($code) || strlen($code) < 3) {
        $errors[] = "Kode voucher wajib diisi (min. 3 karakter).";
    }
    if ($type !== 'percentage' && $type !== 'fixed') {
        $errors[] = "Tipe diskon tidak valid.";
    }
    if ($value <= 0) {
        $errors[] = "Nilai diskon harus lebih dari 0.";
    } elseif ($type === 'percentage' && $value > 100) {
        $errors[] = "Nilai persentase tidak boleh lebih dari 100%.";
    }
    if ($max_usage < 1) {
        $errors[] = "Batas maksimal penggunaan harus minimal 1.";
    }

    if (empty($valid_until_raw)) {
        $errors[] = "Tanggal dan jam validasi wajib diisi.";
    } else {
        // Coba konversi ke objek DateTime
        try {
            $dt = new DateTime($valid_until_raw);
            $valid_until = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $errors[] = "Format tanggal validasi tidak valid. Error: " . $e->getMessage();
        }
    }

    if (!$valid_until && empty($errors)) {
         $errors[] = "Konversi tanggal gagal total. Mohon cek format input.";
    }

    if (empty($errors)) {
        try {
            $stmt_check = $conn->prepare("SELECT id FROM vouchers WHERE code = ? AND provider_id = ?");
            $stmt_check->bind_param("si", $code, $actual_provider_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $errors[] = "Kode voucher '$code' sudah ada untuk provider Anda. Mohon gunakan kode lain.";
            }
            $stmt_check->close();
        } catch (Exception $e) {
            $errors[] = "Error database saat cek duplikasi: " . $e->getMessage();
        }
    }
    // ----------------------


    // Jika error, redirect kembali ke form
    if (!empty($errors)) {
        $_SESSION['dashboard_message'] = implode("<br>", $errors);
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=" . $redirect_to_form);
        exit();
    }

    // --- Simpan ke Database ---
    try {
        $sql = "INSERT INTO vouchers (
            uuid, provider_id, code, type, value, max_usage, min_purchase, valid_until, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("sissisisi",
            $uuid,               // s
            $actual_provider_id, // i
            $code,               // s
            $type,               // s
            $value,              // s (untuk DECIMAL)
            $max_usage,          // i
            $min_purchase,       // s (untuk DECIMAL)
            $valid_until,        // s
            $is_active           // i
        );

        if ($stmt->execute()) {
            $stmt->close();
            // Sukses
            unset($_SESSION['form_data']); 
            $_SESSION['dashboard_message'] = "Voucher '$code' berhasil dibuat.";
            $_SESSION['dashboard_message_type'] = "success";
            header("Location: /dashboard?p=vouchers");
            exit();
        } else {
            throw new Exception("Gagal menyimpan data voucher: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['dashboard_message'] = "Terjadi kesalahan sistem saat menyimpan: " . $e->getMessage();
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=" . $redirect_to_form);
        exit();
    }
}


// ==========================================================
// --- AKSI: DEACTIVATE VOUCHER ---
// ==========================================================
elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'deactivate_voucher') {
    
    $voucher_id = (int)($_POST['voucher_id'] ?? 0);
    $message = "Aksi gagal.";
    $message_type = "danger";

    if ($voucher_id > 0) {
        try {
            // Nonaktifkan voucher (is_active = 0)
            $stmt = $conn->prepare("UPDATE vouchers SET is_active = 0 WHERE id = ? AND provider_id = ?");
            $stmt->bind_param("ii", $voucher_id, $actual_provider_id); 
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Voucher berhasil dinonaktifkan.";
                $message_type = "success";
            } else {
                $message = "Gagal menonaktifkan voucher. ID tidak ditemukan, sudah nonaktif, atau Anda tidak memiliki izin.";
                $message_type = "danger";
            }
            $stmt->close();

        } catch (Exception $e) {
            $message = "Kesalahan database saat menonaktifkan voucher: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "ID Voucher tidak valid.";
        $message_type = "danger";
    }
    
    // Set pesan di session dan redirect
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $message_type;
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}


$conn->close();
?>