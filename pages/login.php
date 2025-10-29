<?php
// File: pages/login.php
session_start();

// Inisialisasi variabel untuk pesan status
$message = '';
$message_type = '';

// Cek dan ambil pesan dari session (setelah redirect dari register_process atau login_process)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Hapus session setelah ditampilkan
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Jika user sudah login, arahkan ke dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'provider') {
    header("Location: /dashboard");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Provider - Open Trip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-container {
            /* Mengatur ulang style card */
            width: 100%; 
            max-width: 450px; /* Lebar yang sedikit lebih besar untuk tampilan yang proporsional */
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            background-color: #ffffff;
        }
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        /* Style untuk logo */
        .logo-login {
            max-width: 250px; /* Mungkin sedikit diperbesar agar lebih terlihat */
            height: auto;
            display: block; 
            margin: 0 auto 10px auto; 
        }
    </style>
</head>
<body>

    <div class="login-container">
        
        <img src="/assets/vly.png" alt="Logo Login Provider" class="logo-login">
        
        <p class="text-center text-muted mb-4">Masuk ke akun Anda untuk mengelola layanan trip Anda.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo ($message_type == 'success' ? 'success' : 'danger'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="/process/login_process" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="nama@domain.com">
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password Anda">
            </div>
            
            <button type="submit" class="btn btn-dark w-100 py-2 fw-bold">Login</button>
        </form>

        <p class="text-end mt-3">
            <a href="/forgot-password" class="text-decoration-none small fw-bold text-dark">Lupa Password?</a>
        </p>
        <p class="text-center mt-3 small">
            Belum punya akun? <a href="/register" class="text-decoration-none fw-bold text-dark">Daftar di sini</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>