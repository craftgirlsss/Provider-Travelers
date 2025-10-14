<?php
// File: process/login_process.php
session_start();
require_once __DIR__ . '/../config/db_config.php';
// [1] INCLUDE GLOBAL FUNCTIONS UNTUK LOGGING AUDIT
require_once __DIR__ . '/../config/global_functions.php'; 

// Aturan Keamanan
const MAX_ATTEMPTS = 5;
const SUSPEND_EMAIL_CONTACT = "info@karyadeveloperindonesia.com";

// --- Fungsi Pembantu untuk Update attempts ---
function updateLoginAttempts($conn, $uuid, $attempts, $is_suspended = 0) {
    // Gunakan prepared statement untuk keamanan
    $sql = "UPDATE users SET login_attempts = ?, is_suspended = ? WHERE uuid = ?";
    $stmt = $conn->prepare($sql);
    // Tipe data: i (int), i (int), s (string)
    $stmt->bind_param("iis", $attempts, $is_suspended, $uuid);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /login");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$redirect_to = '/login';
$message_type = "danger";

if (empty($email) || empty($password)) {
    $_SESSION['message'] = "Email dan password wajib diisi.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . $redirect_to);
    exit();
}

try {
    // 1. Ambil data user dari DB (Mengambil ID integer, UUID, dan detail lainnya)
    $stmt = $conn->prepare("
        SELECT id, uuid, name, password, role, status, login_attempts, is_suspended, suspended_until 
        FROM users 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Kredensial tidak valid.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $redirect_to);
        exit();
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['id']; // ID Integer dari tabel users
    $user_uuid = $user['uuid']; 
    $user_role = $user['role'];
    $current_attempts = (int)$user['login_attempts'];
    $stmt->close();

    // 2. CEK SUSPENSI PERMANEN
    if ($user['is_suspended'] == 1) {
        $message = "Akun Anda telah dinonaktifkan (suspended) karena terlalu banyak percobaan login yang gagal. Silakan kirim email permohonan pengaktifan akun ke **" . SUSPEND_EMAIL_CONTACT . "**.";
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = "danger";
        header("Location: " . $redirect_to);
        exit();
    }

    // 3. VERIFIKASI PASSWORD
    if (password_verify($password, $user['password'])) {
        
        // --- LOGIN BERHASIL ---
        
        // Reset percobaan gagal
        if ($current_attempts > 0) {
            updateLoginAttempts($conn, $user_uuid, 0, 0); 
        }
        
        // Set session umum
        $_SESSION['user_id'] = $user_id; // ID Integer untuk FK
        $_SESSION['user_uuid'] = $user_uuid; 
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user_role;
        $_SESSION['user_email'] = $email;

        $provider_id = null;
        
        // 4. LOGIKA TAMBAHAN UNTUK PROVIDER (Ambil actual_provider_id & Logging)
        if ($user_role === 'provider') {
            // [2] Ambil ID Provider dari tabel providers
            $stmt_provider = $conn->prepare("SELECT id FROM providers WHERE user_id = ?");
            $stmt_provider->bind_param("i", $user_id);
            $stmt_provider->execute();
            $result_provider = $stmt_provider->get_result();
            
            if ($result_provider->num_rows > 0) {
                $provider_id = $result_provider->fetch_assoc()['id'];
                $_SESSION['actual_provider_id'] = $provider_id;
                
                // [3] LOGGING AKTIVITAS (Hanya jika provider_id ditemukan)
                log_provider_activity(
                    $conn,
                    $provider_id,
                    'LOGIN',
                    'users', 
                    $user_id, 
                    "Login berhasil ke Dashboard Provider."
                );
                
                $redirect_to = '/dashboard';
            } else {
                // Provider role, tapi data di tabel providers tidak ada (error sistem)
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['message'] = "Login gagal. Data Provider Anda tidak terdaftar dengan benar.";
                $_SESSION['message_type'] = "danger";
                header("Location: /login");
                exit();
            }
            $stmt_provider->close();

        } elseif ($user_role === 'admin') {
            $redirect_to = '/admin/dashboard'; 
        } else {
            $redirect_to = '/'; 
        }
        
        header("Location: " . $redirect_to);
        exit();

    } else {
        
        // --- LOGIN GAGAL: PASSWORD SALAH ---
        
        // 5. Tingkatkan hitungan percobaan gagal
        $new_attempts = $current_attempts + 1;
        $message = "Kredensial tidak valid.";
        
        if ($new_attempts >= MAX_ATTEMPTS) {
            
            // Suspensi Permanen
            updateLoginAttempts($conn, $user_uuid, $new_attempts, 1);
            
            $message = "Login gagal. Akun Anda telah dinonaktifkan (suspended) karena mencapai batas percobaan gagal (" . MAX_ATTEMPTS . " kali). Silakan kirim email permohonan pengaktifan akun ke **" . SUSPEND_EMAIL_CONTACT . "**.";
            $_SESSION['message_type'] = "danger";

        } else {
            // Update hitungan percobaan gagal
            updateLoginAttempts($conn, $user_uuid, $new_attempts, 0);
            
            $remaining = MAX_ATTEMPTS - $new_attempts;
            $message .= " Sisa percobaan: $remaining kali.";
            $_SESSION['message_type'] = "warning";
        }
        
        $_SESSION['message'] = $message;
        header("Location: " . $redirect_to);
        exit();
    }
    
} catch (Exception $e) {
    // Log kesalahan database yang lebih umum
    error_log("Database Error during login: " . $e->getMessage());
    $_SESSION['message'] = "Terjadi kesalahan sistem. Silakan coba lagi.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . $redirect_to);
    exit();
}
?>