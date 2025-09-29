<?php
// File: process/profile_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Cek login & role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: /login");
    exit();
}

$user_id_from_session = $_SESSION['user_id'];
$redirect_page = 'profile'; 
$actual_provider_id = null; // ID Provider yang benar (Primary Key dari tabel 'providers')

// Ambil Provider ID dan data lama untuk cek dokumen
try {
    $stmt_provider = $conn->prepare("SELECT id, ktp_path, business_license_path, company_logo_path /* <-- BARU */
        FROM providers WHERE user_id = ?");
    $stmt_provider->bind_param("i", $user_id_from_session);
    $stmt_provider->execute();
    $result_provider = $stmt_provider->get_result();
    
    if ($result_provider->num_rows > 0) {
        $row = $result_provider->fetch_assoc();
        $actual_provider_id = $row['id']; 
        $old_ktp_path = $row['ktp_path'];
        $old_license_path = $row['business_license_path'];
        $old_logo_path = $row['company_logo_path']; // <-- BARU
    }
    $stmt_provider->close();
} catch (Exception $e) {
    // Handle error...
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

    // ==========================================================
    // --- AKSI UPDATE DATA PROFIL PROVIDER ---
    // ==========================================================
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

        // Validasi Dasar
        if (empty($company_name) || empty($owner_name) || empty($phone_number) || empty($address) || empty($postal_code) || empty($province) || empty($city) || empty($district) || empty($village)) {
            $errors[] = "Semua kolom bertanda (*) wajib diisi.";
        }
        
        // Inisialisasi Path Lama
        $ktp_path = $old_ktp_path; 
        $license_path = $old_license_path; 
        $logo_path = $old_logo_path; // <-- BARU

        // Fungsi Pembantu Upload (Disesuaikan untuk menerima max_size)
        function upload_document($file_key, $allowed_types, $max_size, $prefix, &$errors) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$file_key];
                
                // Cek MIME Type menggunakan tipe yang dikirim browser (Solusi sementara jika Fileinfo tidak aktif)
                $file_mime_uploaded = $file['type'];
                if (!in_array($file_mime_uploaded, $allowed_types)) {
                    $errors[] = "Format file $prefix tidak didukung (Received: {$file_mime_uploaded}).";
                    return null;
                }
                
                // Cek Ukuran
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

        // 1. Upload KTP
        $new_ktp = upload_document('ktp_file', $allowed_img, $max_size_doc, 'ktp', $errors);
        if ($new_ktp) { $ktp_path = $new_ktp; }
        elseif (empty($old_ktp_path) && $_FILES['ktp_file']['error'] !== UPLOAD_ERR_NO_FILE) { $errors[] = "File KTP wajib diupload."; } // Check if upload attempted but failed

        // 2. Upload Surat Izin (Hanya jika entitas = 'company')
        if ($entity_type === 'company') {
            $new_license = upload_document('business_license_file', $allowed_all, $max_size_doc, 'license', $errors);
            if ($new_license) { $license_path = $new_license; }
            elseif (empty($old_license_path) && $_FILES['business_license_file']['error'] !== UPLOAD_ERR_NO_FILE) { $errors[] = "File Surat Izin Berusaha wajib diupload untuk entitas PT/CV."; }
        } elseif ($entity_type === 'umkm') {
            $license_path = null; 
        }
        
        // 3. Upload Logo Perusahaan (BARU)
        $new_logo = upload_document('company_logo_file', $allowed_img, $max_size_logo, 'logo', $errors);
        if ($new_logo) { $logo_path = $new_logo; }
        
        // Catatan: Logo tidak diwajibkan (NULL allowed), jadi tidak perlu pengecekan 'required'

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // A. Update data di tabel users (Nama)
                $stmt_user = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
                $stmt_user->bind_param("si", $user_name, $user_id_from_session);
                $stmt_user->execute();
                $stmt_user->close();

                // B. Update data di tabel providers
                $stmt_provider = $conn->prepare("UPDATE providers SET 
                    entity_type = ?, company_name = ?, owner_name = ?, ktp_path = ?, business_license_path = ?, company_logo_path = ?, /* <-- BARU: company_logo_path */
                    address = ?, rt = ?, rw = ?, phone_number = ?, postal_code = ?, 
                    province = ?, city = ?, district = ?, village = ?, updated_at = NOW(),
                    verification_status = 'unverified' /* Reset status jika data penting diubah */
                    WHERE id = ?");

                $stmt_provider->bind_param("sssssssssssssssi",
                    $entity_type, $company_name, $owner_name, $ktp_path, $license_path, $logo_path, // <-- BARU: $logo_path
                    $address, $rt, $rw, $phone_number, $postal_code,
                    $province, $city, $district, $village, $actual_provider_id
                );
                
                if ($stmt_provider->execute()) {
                    // C. Hapus file dokumen lama jika path di DB berubah
                    $upload_base_dir = __DIR__ . '/../';

                    if ($new_ktp && $old_ktp_path && file_exists($upload_base_dir . $old_ktp_path)) {
                        unlink($upload_base_dir . $old_ktp_path);
                    }
                    if ($new_license && $old_license_path && file_exists($upload_base_dir . $old_license_path)) {
                        unlink($upload_base_dir . $old_license_path);
                    }
                    // Jika UMKM dan license_path lama ada, hapus juga file fisiknya
                    if ($entity_type === 'umkm' && $old_license_path && file_exists($upload_base_dir . $old_license_path)) {
                         unlink($upload_base_dir . $old_license_path);
                    }
                    
                    // Hapus logo lama jika logo baru diupload
                    if ($new_logo && $old_logo_path && file_exists($upload_base_dir . $old_logo_path)) {
                         unlink($upload_base_dir . $old_logo_path);
                    }
                    
                    $conn->commit();
                    $message = "Profil Perusahaan berhasil diperbarui. Status verifikasi direset, silakan ajukan ulang verifikasi.";
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
                if ($new_logo && file_exists(__DIR__ . '/../' . $new_logo)) { unlink(__DIR__ . '/../' . $new_logo); } // <-- BARU
            }

        } else {
            $message = implode("<br>", $errors);
        }
    } 
    
}


// Set pesan di session dan redirect
$_SESSION['dashboard_message'] = $message;
$_SESSION['dashboard_message_type'] = $message_type;
header("Location: /dashboard?p=" . $redirect_page);
exit();
?>