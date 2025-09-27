<?php
session_start();

// Inisialisasi variabel untuk pesan status
$message = '';
$message_type = '';

// Cek dan ambil pesan dari session (setelah redirect dari register_process.php)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Hapus session setelah ditampilkan
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Provider - Open Trip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .register-container {
            max-width: 500px;
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
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-color: #0d6efd;
        }
    </style>
</head>
<body>

    <div class="register-container">
        <h3 class="text-center mb-4 fw-bold text-primary">Daftar Akun Provider</h3>
        <p class="text-center text-muted mb-4">Buat akun Anda untuk mulai memposting layanan *open trip*.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo ($message_type == 'success' ? 'success' : 'danger'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="/process/register_process" method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Nama Anda/Perusahaan</label>
                <input type="text" class="form-control" id="name" name="name" required placeholder="Contoh: PT. Wisata Jaya">
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="nama@domain.com">
                <div id="emailHelp" class="form-text">Gunakan email yang aktif.</div>
            </div>
            
            <div class="mb-3">
                <label for="phone" class="form-label">Nomor Telepon</label>
                <input type="tel" class="form-control" id="phone" name="phone" required placeholder="Contoh: 08123456789">
                <div id="phoneHelp" class="form-text">Pastikan nomor telepon valid untuk verifikasi.</div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="Minimal 8 karakter">
            </div>
            
            <div class="mb-4">
                <label for="password_confirm" class="form-label">Konfirmasi Password</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Daftar Sekarang</button>
        </form>

        <p class="text-center mt-4">
            Sudah punya akun? <a href="login.php" class="text-decoration-none">Login di sini</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>