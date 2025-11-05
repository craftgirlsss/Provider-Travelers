<?php
// File: process/profile_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';
$redirect_page = 'profile'; 
if (!isset($_SESSION['user_uuid']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}
$user_uuid_from_session = $_SESSION['user_uuid'];
$user_id_from_session = null; 
$actual_provider_id = null; 
$message = "Gagal memproses data.";
$message_type = "danger";
try {
    $stmt_user = $conn->prepare("SELECT id FROM users WHERE uuid = ?");
    $stmt_user->bind_param("s", $user_uuid_from_session); 
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows > 0) {
        $user_id_from_session = $result_user->fetch_assoc()['id'];
    }
    $stmt_user->close();
} catch (Exception $e) {}
if (!$user_id_from_session) {
    $_SESSION['dashboard_message'] = "Error Otorisasi: ID pengguna tidak ditemukan.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}
try {
    $stmt_provider = $conn->prepare("SELECT 
        id, ktp_path, business_license_path, company_logo_path, 
        bank_name, bank_account_number, bank_account_name 
        FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("i", $user_id_from_session);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    if ($result_provider->num_rows > 0) {
        $row = $result_provider->fetch_assoc();
        $actual_provider_id = $row['id']; 
        $old_ktp_path = $row['ktp_path'];
        $old_license_path = $row['business_license_path'];
        $old_logo_path = $row['company_logo_path']; 
    }
    $stmt_provider->close();
} catch (Exception $e) {
    $message = "Terjadi kesalahan sistem saat otorisasi data provider.";
}
if (!$actual_provider_id) {
    $_SESSION['dashboard_message'] = "Error Otorisasi: Akun provider tidak terdaftar dengan benar.";
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=" . $redirect_page);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $errors = [];
    $message = "Gagal memproses data.";
    $message_type = "danger";
    if ($action === 'update_charter_status') {
        if (!isset($actual_provider_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID Provider tidak ditemukan (Otorisasi Gagal).']);
            exit(); 
        }
        $is_charter_available = (int)($_POST['is_charter_available'] ?? 0);
        header('Content-Type: application/json');
        try {
            $stmt = $conn->prepare("UPDATE providers SET is_charter_available = ? WHERE id = ?");
            $stmt->bind_param("ii", $is_charter_available, $actual_provider_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Status layanan charter berhasil diperbarui.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui database.']);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server saat update.']);
        }
        exit(); // PENTING: Hentikan script setelah merespon AJAX. Ini solusi untuk "Kesalahan Jaringan".
    }
    if ($action === 'update_profile_data') {
        $entity_type = $_POST['entity_type'] ?? '';
        $company_name = trim($_POST['company_name'] ?? '');
        $owner_name = trim($_POST['owner_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $rt = trim($_POST['rt'] ?? '');
        $rw = trim($_POST['rw'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $village = trim($_POST['village'] ?? '');
        $user_name = trim($_POST['name'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_account_number = trim($_POST['bank_account_number'] ?? '');
        $bank_account_name = trim($_POST['bank_account_name'] ?? '');
        if (empty($company_name) || empty($owner_name) || empty($phone_number) || empty($address) || empty($postal_code) || empty($province) || empty($city) || empty($district) || empty($village)) {
            $errors[] = "Semua kolom bertanda (*) wajib diisi.";
        }
        $bank_fields = [$bank_name, $bank_account_number, $bank_account_name];
        $filled_count = count(array_filter($bank_fields));
        if ($filled_count > 0 && $filled_count < 3) {
            $errors[] = "Jika Anda mengisi informasi Bank, semua field (Nama Bank, Nomor Rekening, dan Nama Pemilik) wajib diisi lengkap.";
        }
        if (!empty($bank_account_number) && (!is_numeric($bank_account_number) || strlen($bank_account_number) < 5)) {
            $errors[] = "Nomor Rekening tidak valid (hanya boleh angka, min 5 digit).";
        }
        $ktp_path = $old_ktp_path; 
        $license_path = $old_license_path; 
        $logo_path = $old_logo_path; 
        function upload_document($file_key, $allowed_types, $max_size, $prefix, &$errors) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$file_key];
                
                $file_mime_uploaded = $file['type'];
                if (!in_array($file_mime_uploaded, $allowed_types)) {
                    $errors[] = "Format file $prefix tidak didukung (Received: {$file_mime_uploaded}).";
                    return null;
                }
                if ($file['size'] > $max_size) {
                    $errors[] = "Ukuran file $prefix melebihi batas " . ($max_size / 1024 / 1024) . "MB.";
                    return null;
                }
                $upload_dir = __DIR__ . '/../uploads/documents/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid($prefix . '_') . '.' . $file_extension;
                $destination_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file['tmp_name'], $destination_path)) {
                    return 'uploads/documents/' . $new_file_name;
                } else {
                    $errors[] = "Gagal memindahkan file $prefix ke folder upload.";
                }
            }
            return null;
        }
        $allowed_img = ['image/jpeg', 'image/png', 'image/jpg'];
        $allowed_all = array_merge($allowed_img, ['application/pdf']);
        $max_size_doc = 2 * 1024 * 1024; // 2MB untuk KTP/Izin
        $max_size_logo = 500 * 1024; // 500KB untuk Logo
        $new_ktp = upload_document('ktp_file', $allowed_img, $max_size_doc, 'ktp', $errors);
        if ($new_ktp) { $ktp_path = $new_ktp; }
        elseif (empty($old_ktp_path) && $_FILES['ktp_file']['error'] === UPLOAD_ERR_NO_FILE) { $errors[] = "File KTP wajib diupload."; }
        if ($entity_type === 'company') {
            $new_license = upload_document('business_license_file', $allowed_all, $max_size_doc, 'license', $errors);
            if ($new_license) { $license_path = $new_license; }
            elseif (empty($old_license_path) && $_FILES['business_license_file']['error'] === UPLOAD_ERR_NO_FILE) { $errors[] = "File Surat Izin Berusaha wajib diupload untuk entitas PT/CV."; }
        } elseif ($entity_type === 'umkm') {
            $license_path = null; 
        }
        $new_logo = upload_document('company_logo_file', $allowed_img, $max_size_logo, 'logo', $errors);
        if ($new_logo) { $logo_path = $new_logo; }
        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // A. Update data di tabel users (Nama) - Menggunakan ID integer
                $stmt_user = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
                $stmt_user->bind_param("si", $user_name, $user_id_from_session);
                $stmt_user->execute();
                $stmt_user->close();
                $stmt_provider = $conn->prepare("UPDATE providers SET 
                    entity_type = ?, company_name = ?, owner_name = ?, ktp_path = ?, business_license_path = ?, company_logo_path = ?, 
                    address = ?, rt = ?, rw = ?, phone_number = ?, postal_code = ?, 
                    province = ?, city = ?, district = ?, village = ?, 
                    bank_name = ?, bank_account_number = ?, bank_account_name = ?, 
                    updated_at = NOW(),
                    verification_status = 'unverified'
                    WHERE id = ?");
                $stmt_provider->bind_param("ssssssssssssssssssi",
                    $entity_type, $company_name, $owner_name, $ktp_path, $license_path, $logo_path, 
                    $address, $rt, $rw, $phone_number, $postal_code,
                    $province, $city, $district, $village,
                    $bank_name, $bank_account_number, $bank_account_name,
                    $actual_provider_id
                );

                if ($stmt_provider->execute()) {
                    $upload_base_dir = __DIR__ . '/../';
                    if ($new_ktp && $old_ktp_path && file_exists($upload_base_dir . $old_ktp_path)) {
                        unlink($upload_base_dir . $old_ktp_path);
                    }
                    if ($new_license && $old_license_path && file_exists($upload_base_dir . $old_license_path)) {
                        unlink($upload_base_dir . $old_license_path);
                    }
                    if ($entity_type === 'umkm' && $old_license_path && file_exists($upload_base_dir . $old_license_path)) {
                         unlink($upload_base_dir . $old_license_path);
                    }
                    if ($new_logo && $old_logo_path && file_exists($upload_base_dir . $old_logo_path)) {
                         unlink($upload_base_dir . $old_logo_path);
                    }
                    $conn->commit();
                    $message = "Profil Perusahaan berhasil diperbarui. Verifikasi memakan waktu 1-2 Hari Kerja.";
                    $message_type = "success";
                } else {
                    throw new Exception("Gagal menyimpan data provider: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Terjadi kesalahan sistem saat menyimpan data: " . $e->getMessage();
                // Hapus file baru yang mungkin sudah terupload jika transaksi gagal
                if ($new_ktp && file_exists(__DIR__ . '/../' . $new_ktp)) { unlink(__DIR__ . '/../' . $new_ktp); }
                if ($new_license && file_exists(__DIR__ . '/../' . $new_license)) { unlink(__DIR__ . '/../' . $new_license); }
                if ($new_logo && file_exists(__DIR__ . '/../' . $new_logo)) { unlink(__DIR__ . '/../' . $new_logo); }
            }
            finally {
                if (isset($stmt_provider)) $stmt_provider->close();
            }
        } else {
            $message = implode("<br>", $errors);
        }
    } 
}
$_SESSION['dashboard_message'] = $message;
$_SESSION['dashboard_message_type'] = $message_type;
header("Location: /dashboard?p=" . $redirect_page);
exit();
?>