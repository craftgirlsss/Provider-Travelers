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
        /* Menggunakan flexbox untuk centring yang konsisten */
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px; /* Tambahkan padding agar tidak menempel di tepi layar kecil */
        }
        .register-container {
            width: 100%; /* Agar bisa mengambil lebar penuh di mobile */
            max-width: 500px; /* Batasan lebar di desktop */
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            background-color: #ffffff;
            /* Tambahkan margin vertical otomatis agar centring flexbox bekerja */
            margin-top: auto; 
            margin-bottom: auto;
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
        <p class="text-center text-muted mb-4">Buat akun Anda untuk mulai memposting layanan Trip.</p>

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
            
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" value="1" id="terms_agree" name="terms_agree" required>
                <label class="form-check-label" for="terms_agree">
                    Saya menyetujui <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#termsModal">Syarat dan Ketentuan</a> yang berlaku dari PT. Karya Developer Indonesia.
                </label>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Daftar Sekarang</button>
        </form>

        <p class="text-center mt-4">
            Sudah punya akun? <a href="login.php" class="text-decoration-none">Login di sini</a>
        </p>
    </div>

    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="termsModalLabel">Syarat dan Ketentuan Provider PT. Karya Developer Indonesia</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <h6>1. Ketentuan Umum</h6>
            <p>1.1. Dengan mendaftar sebagai Provider, Anda mengakui bahwa Anda adalah entitas bisnis yang sah (Perusahaan/Perorangan) dan berhak secara hukum untuk menjual layanan perjalanan (trip) di platform ini.</p>
            <p>1.2. PT. Karya Developer Indonesia bertindak sebagai penyedia platform dan tidak bertanggung jawab atas kualitas layanan yang diberikan oleh Provider.</p>

            <h6>2. Komisi dan Pembayaran</h6>
            <p>2.1. Provider setuju untuk membayar komisi sebesar X% dari total harga pemesanan yang berhasil dibayar melalui platform.</p>
            <p>2.2. Pembayaran ke Provider akan diproses dalam waktu T+X hari kerja setelah tanggal keberangkatan trip berhasil dilaksanakan.</p>
            
            <h6>3. Kewajiban Provider</h6>
            <p>3.1. Provider wajib memberikan informasi trip yang akurat, jujur, dan lengkap, termasuk harga, tanggal, fasilitas, dan detail lainnya.</p>
            <p>3.2. Provider bertanggung jawab penuh atas pelaksanaan, keselamatan, dan kualitas layanan selama trip berlangsung.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>