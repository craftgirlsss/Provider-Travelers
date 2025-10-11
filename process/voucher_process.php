<?php
// File: process/voucher_process.php
session_start();
// Harap pastikan path ini benar
require_once __DIR__ . '/../config/db_config.php'; 
require_once __DIR__ . '/../includes/uuid_generator.php'; 

// --- DEKLARASI VARIABEL OTORISASI ---
$user_uuid_from_session = $_SESSION['user_uuid'] ?? null;
$user_role_from_session = $_SESSION['user_role'] ?? null;
$user_id_from_session = null; // ID integer dari tabel users
$actual_provider_id = null; // ID integer dari tabel providers
$redirect_page = 'vouchers'; 

// Cek login & role (Provider)
if (!$user_uuid_from_session || $user_role_from_session !== 'provider') {
    $_SESSION['message'] = "Akses ditolak. Silakan login sebagai Provider.";
    $_SESSION['message_type'] = "danger";
    header("Location: /login");
    exit();
}

// --- 1. MENDAPATKAN ID PROVIDER ---
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


// =========================================================================
// --- FUNGSI PEMBANTU UNTUK IMAGE UPLOAD ---
// =========================================================================
function handle_image_upload(array $fileData, int $voucherId, string $currentPath = null, bool $deleteFlag = false): array 
{
    $maxFileSize = 2 * 1024 * 1024; // 2 MB
    $uploadDir = __DIR__ . '/../uploads/vouchers/'; // Path absolut dari folder process

    // Pastikan direktori ada
    if (!is_dir($uploadDir)) {
        // Coba buat folder
        if (!mkdir($uploadDir, 0777, true)) {
            return ['success' => false, 'message' => 'Gagal membuat folder upload.', 'new_path' => null];
        }
    }
    
    // Path Relatif untuk disimpan di DB
    $db_path_prefix = 'uploads/vouchers/';
    
    // 1. Cek Permintaan Hapus (Khusus Update)
    if ($deleteFlag) {
        // Hapus file fisik lama
        if ($currentPath && file_exists(__DIR__ . '/../' . $currentPath)) {
            unlink(__DIR__ . '/../' . $currentPath);
        }
        // Kembalikan path null untuk dihapus dari DB
        return ['success' => true, 'message' => 'Gambar berhasil dihapus.', 'new_path' => null];
    }

    // 2. Cek apakah ada file baru yang diupload
    if (!isset($fileData['error']) || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
        // Tidak ada file baru diupload. Pertahankan path lama.
        return ['success' => true, 'message' => 'Tidak ada file baru diupload.', 'new_path' => $currentPath];
    }
    
    // 3. Cek Error Umum Upload
    if ($fileData['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Terjadi error saat upload file.', 'new_path' => $currentPath];
    }

    // 4. Validasi Ukuran File (Maksimal 2 MB)
    if ($fileData['size'] > $maxFileSize) {
        return ['success' => false, 'message' => 'Ukuran file melebihi batas 2MB.', 'new_path' => $currentPath];
    }

    // 5. Validasi Tipe File
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    // --- BLOK KODE YANG DIMODIFIKASI ---
    $is_valid_type = false;

    if (function_exists('finfo_open')) {
        // Pengecekan aman (ASLI)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);
        $is_valid_type = in_array($mimeType, $allowedTypes);
    } else {
        // Pengecekan darurat (KURANG AMAN)
        $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        $is_valid_type = in_array($ext, $allowedExts);
    }

    if (!$is_valid_type) {
        return ['success' => false, 'message' => 'Tipe file tidak didukung. Hanya JPG, PNG, GIF.', 'new_path' => $currentPath];
    }
    // 6. Tentukan Path dan Nama File Unik
    $ext = pathinfo($fileData['name'], PATHINFO_EXTENSION);
    $fileName = 'voucher_' . $voucherId . '_' . time() . '.' . $ext;
    $targetAbsPath = $uploadDir . $fileName; // Path absolut untuk move_uploaded_file
    $targetDbPath = $db_path_prefix . $fileName; // Path relatif untuk DB

    // 7. Hapus file lama jika ada
    if ($currentPath && file_exists(__DIR__ . '/../' . $currentPath)) {
        unlink(__DIR__ . '/../' . $currentPath);
    }

    // 8. Pindahkan File
    if (move_uploaded_file($fileData['tmp_name'], $targetAbsPath)) {
        return ['success' => true, 'message' => 'Gambar berhasil diupload.', 'new_path' => $targetDbPath];
    } else {
        return ['success' => false, 'message' => 'Gagal memindahkan file yang diupload.', 'new_path' => $currentPath];
    }
}
// -------------------------------------------------------------------------


$action = $_POST['action'] ?? '';
$post_data = $_POST;


// ==========================================================
// --- AKSI: CREATE VOUCHER (MODIFIKASI: IMAGE UPLOAD & TRANSAKSI) ---
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'create_voucher') {

    $errors = [];
    $redirect_to_form = "voucher_create";
    $_SESSION['form_data'] = $post_data; // Simpan data POST di awal

    // Ambil dan bersihkan input (sama seperti kode Anda)
    $code = strtoupper(trim($post_data['code'] ?? ''));
    $type = $post_data['type'] ?? '';
    $value = (float)($post_data['value'] ?? 0);
    $max_usage = (int)($post_data['max_usage'] ?? 0);
    $min_purchase = (float)($post_data['min_purchase'] ?? 0);
    $valid_until_raw = trim($post_data['valid_until'] ?? '');
    $valid_until = null;
    $is_active = (int)($post_data['is_active'] ?? 0);
    $uuid = generate_uuid();
    $new_voucher_id = 0;
    
    // --- Validasi Input (sama seperti kode Anda) ---
    // ... (Validasi di sini, omitted for brevity but should be included) ...
    if (empty($code) || strlen($code) < 3) { $errors[] = "Kode voucher wajib diisi (min. 3 karakter)."; }
    if ($type !== 'percentage' && $type !== 'fixed') { $errors[] = "Tipe diskon tidak valid."; }
    if ($value <= 0) { $errors[] = "Nilai diskon harus lebih dari 0."; } elseif ($type === 'percentage' && $value > 100) { $errors[] = "Nilai persentase tidak boleh lebih dari 100%."; }
    if ($max_usage < 1) { $errors[] = "Batas maksimal penggunaan harus minimal 1."; }
    if (empty($valid_until_raw)) { $errors[] = "Tanggal dan jam validasi wajib diisi."; } else { try { $dt = new DateTime($valid_until_raw); $valid_until = $dt->format('Y-m-d H:i:s'); } catch (Exception $e) { $errors[] = "Format tanggal validasi tidak valid."; } }
    if (!$valid_until && empty($errors)) { $errors[] = "Konversi tanggal gagal total."; }

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
    // ------------------------------------
    
    if (!empty($errors)) {
        $_SESSION['dashboard_message'] = implode("<br>", $errors);
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=" . $redirect_to_form);
        exit();
    }

    // --- Mulai Transaksi ---
    $conn->begin_transaction();
    $success = false;
    $db_message = "";
    $image_path_to_db = null; // Default null

    try {
        // 1. Insert Voucher (tanpa image_path dulu)
        $sql = "INSERT INTO vouchers (
            uuid, provider_id, code, type, value, max_usage, min_purchase, valid_until, is_active, image_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Kita gunakan 'NULL' untuk image_path di insert awal
        $stmt = $conn->prepare($sql);
        
        // sissisiss -> string(uuid), int(id), string(code), string(type), string(value), int(max), string(min), string(date), int(active), string(image_path)
        // Kita pakai NULL, lalu update setelah upload
        $sql = "INSERT INTO vouchers (
            uuid, provider_id, code, type, value, max_usage, min_purchase, valid_until, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("sissisisi",
            $uuid, $actual_provider_id, $code, $type, $value, $max_usage, $min_purchase, $valid_until, $is_active
        );

        if (!$stmt->execute()) {
             throw new Exception("Gagal menyimpan data voucher: " . $stmt->error);
        }

        $new_voucher_id = $conn->insert_id;
        $stmt->close();
        
        // 2. Handle Image Upload (setelah voucherId didapat)
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handle_image_upload($_FILES['image'], $new_voucher_id);
            
            if (!$upload_result['success']) {
                // Upload gagal, batalkan insert voucher
                throw new Exception("Voucher Gagal Dibuat. Kesalahan Upload Gambar: " . $upload_result['message']);
            }
            
            $image_path_to_db = $upload_result['new_path'];
            
            // 3. Update Voucher dengan Image Path
            if ($image_path_to_db) {
                $sql_update_img = "UPDATE vouchers SET image_path = ? WHERE id = ?";
                $stmt_img = $conn->prepare($sql_update_img);
                $stmt_img->bind_param("si", $image_path_to_db, $new_voucher_id);
                $stmt_img->execute();
                $stmt_img->close();
            }
        }

        // Commit transaksi jika semua berhasil
        $conn->commit();
        $success = true;
        $db_message = "Voucher '$code' berhasil dibuat.";

    } catch (Exception $e) {
        $conn->rollback();
        $db_message = "Terjadi kesalahan sistem saat menyimpan: " . $e->getMessage();
        $_SESSION['dashboard_message_type'] = "danger";
    }
    
    $_SESSION['dashboard_message'] = $db_message;
    $_SESSION['dashboard_message_type'] = $success ? 'success' : 'danger';
    
    // Redirect ke daftar voucher
    if ($success) {
        unset($_SESSION['form_data']); 
        header("Location: /dashboard?p=vouchers");
    } else {
        // Kembali ke form create
        header("Location: /dashboard?p=" . $redirect_to_form);
    }
    exit();
}


// ==========================================================
// --- AKSI: UPDATE VOUCHER (BARU: IMAGE UPLOAD & TRANSAKSI) ---
// ==========================================================
elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'update_voucher') {

    $voucher_id = (int)($post_data['voucher_id'] ?? 0);
    $redirect_to_form = "voucher_edit&id=" . $voucher_id;
    $_SESSION['form_data'] = $post_data; 
    
    // 1. Validasi Input (Mirip Create, tapi Kode & Tipe tidak diubah)
    $errors = [];
    $value = (float)($post_data['value'] ?? 0);
    $max_usage = (int)($post_data['max_usage'] ?? 0);
    $min_purchase = (float)($post_data['min_purchase'] ?? 0);
    $valid_until_raw = trim($post_data['valid_until'] ?? '');
    $valid_until = null;
    $is_active = (int)($post_data['is_active'] ?? 0);
    $delete_image = isset($post_data['delete_current_image']) && $post_data['delete_current_image'] == '1';
    
    if ($voucher_id <= 0) { $errors[] = "ID Voucher tidak valid."; }
    if ($value <= 0) { $errors[] = "Nilai diskon harus lebih dari 0."; }
    if ($max_usage < 1) { $errors[] = "Batas maksimal penggunaan harus minimal 1."; }
    if (empty($valid_until_raw)) { $errors[] = "Tanggal dan jam validasi wajib diisi."; } else { try { $dt = new DateTime($valid_until_raw); $valid_until = $dt->format('Y-m-d H:i:s'); } catch (Exception $e) { $errors[] = "Format tanggal validasi tidak valid."; } }
    if (!$valid_until && empty($errors)) { $errors[] = "Konversi tanggal gagal total."; }
    
    if (!empty($errors)) {
        $_SESSION['dashboard_message'] = implode("<br>", $errors);
        $_SESSION['dashboard_message_type'] = "danger";
        header("Location: /dashboard?p=" . $redirect_to_form);
        exit();
    }

    // --- Mulai Transaksi ---
    $conn->begin_transaction();
    $success = false;
    $db_message = "";
    $new_image_path = null;

    try {
        // A. Ambil path gambar lama
        $sql_fetch_path = "SELECT image_path FROM vouchers WHERE id = ? AND provider_id = ?";
        $stmt_fetch = $conn->prepare($sql_fetch_path);
        $stmt_fetch->bind_param("ii", $voucher_id, $actual_provider_id);
        $stmt_fetch->execute();
        $old_path = $stmt_fetch->get_result()->fetch_assoc()['image_path'] ?? null;
        $stmt_fetch->close();
        
        // B. Handle Image Upload/Hapus
        $upload_result = handle_image_upload(
            $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], 
            $voucher_id, 
            $old_path,
            $delete_image
        );
        
        if (!$upload_result['success']) {
            throw new Exception("Kesalahan Upload Gambar: " . $upload_result['message']);
        }
        
        $new_image_path = $upload_result['new_path']; // Bisa berupa path baru, path lama, atau NULL
        
        // C. Update Data Voucher di Database
        $sql_update = "UPDATE vouchers SET 
                        value = ?, 
                        max_usage = ?, 
                        min_purchase = ?, 
                        valid_until = ?, 
                        is_active = ?,
                        image_path = ? /* BARU */
                       WHERE id = ? AND provider_id = ?";
        
        $stmt = $conn->prepare($sql_update);
        // Bind parameter: s (value), i (max_usage), s (min_purchase), s (valid_until), i (is_active), s (image_path), i (voucher_id), i (provider_id)
        $stmt->bind_param("sisssisi", 
            $value, $max_usage, $min_purchase, $valid_until, $is_active, 
            $new_image_path, // Path baru atau NULL
            $voucher_id, $actual_provider_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal memperbarui voucher ke database: " . $stmt->error);
        }
        
        $stmt->close();
        
        $conn->commit();
        $success = true;
        $db_message = "Voucher berhasil diperbarui, gambar telah di-handle.";

    } catch (Exception $e) {
        $conn->rollback();
        $db_message = "Kesalahan saat memperbarui: " . $e->getMessage();
        $_SESSION['form_data'] = $post_data;
        $_SESSION['dashboard_message_type'] = "danger";
    }
    
    $_SESSION['dashboard_message'] = $db_message;
    $_SESSION['dashboard_message_type'] = $success ? 'success' : 'danger';
    
    // Redirect ke halaman edit atau daftar jika sukses
    if ($success) {
        unset($_SESSION['form_data']); 
        header("Location: /dashboard?p=vouchers");
    } else {
        header("Location: /dashboard?p=" . $redirect_to_form);
    }
    exit();
}


// ==========================================================
// --- AKSI: DEACTIVATE VOUCHER (TETAP SAMA) ---
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


// ==========================================================
// --- AKSI LAIN (DEFAULT) ---
// ==========================================================
else {
    $_SESSION['dashboard_message'] = "Aksi tidak dikenal.";
    $_SESSION['dashboard_message_type'] = "warning";
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}

$conn->close();
?>
