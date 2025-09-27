<?php
// File: pages/forgot_password.php
session_start();

$message = '';
$message_type = '';

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
    <title>Lupa Password - Open Trip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 450px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); border-radius: 10px; background-color: #ffffff; }
        body { background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
    </style>
</head>
<body>

    <div class="container">
        <h3 class="text-center mb-4 fw-bold text-warning">Lupa Password?</h3>
        <p class="text-center text-muted mb-4">Masukkan alamat email Anda yang terdaftar. Kami akan mengirimkan Kode OTP untuk mengatur ulang kata sandi Anda.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo ($message_type == 'success' ? 'success' : 'danger'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="/process/auth_process" method="POST">
            <input type="hidden" name="action" value="forgot_password_request"> 

            <div class="mb-4">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="nama@domain.com">
            </div>
            
            <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-white">Kirim Kode OTP</button>
        </form>

        <p class="text-center mt-3 small">
            <a href="/login" class="text-decoration-none">Kembali ke Login</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>