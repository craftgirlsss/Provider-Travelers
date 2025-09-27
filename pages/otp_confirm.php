<?php
// File: pages/otp_confirm.php
session_start();

$message = '';
$message_type = '';
$reset_email = $_SESSION['reset_email'] ?? ''; // Ambil email yang sudah disimpan di session

// Jika email belum ada di session, arahkan kembali ke form forgot password
if (empty($reset_email)) {
    $_SESSION['message'] = "Sesi reset password Anda telah berakhir. Silakan masukkan email lagi.";
    $_SESSION['message_type'] = "danger";
    header("Location: /forgot-password");
    exit();
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi OTP - Open Trip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 450px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); border-radius: 10px; background-color: #ffffff; }
        body { background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .otp-input { font-size: 1.5rem; text-align: center; letter-spacing: 0.5rem; }
    </style>
</head>
<body>

    <div class="container">
        <h3 class="text-center mb-4 fw-bold text-success">Konfirmasi Kode OTP</h3>
        <p class="text-center text-muted mb-4">
            Masukkan kode 6 digit OTP yang telah kami kirimkan ke email Anda: 
            <strong class="text-primary"><?php echo htmlspecialchars($reset_email); ?></strong>
        </p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo ($message_type == 'success' ? 'success' : 'danger'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="/process/auth_process" method="POST">
            <input type="hidden" name="action" value="verify_otp"> 
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($reset_email); ?>">

            <div class="mb-4">
                <label for="otp_code" class="form-label">Kode OTP</label>
                <input type="text" class="form-control otp-input" id="otp_code" name="otp_code" 
                       required maxlength="6" pattern="\d{6}" placeholder="------">
            </div>
            
            <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Verifikasi OTP</button>
        </form>

        <p class="text-center mt-3 small">
            Belum menerima kode? <a href="/forgot-password" class="text-decoration-none">Kirim ulang</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr="javascript:void(0)"npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>