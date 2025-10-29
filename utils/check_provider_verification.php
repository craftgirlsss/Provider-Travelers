<?php
// Pastikan file ini di-include setelah koneksi $conn dan $actual_provider_id didefinisikan

/**
 * Memeriksa status verifikasi Provider.
 * Jika belum terverifikasi ('unverified' atau 'pending'), fungsi ini akan menampilkan 
 * pesan peringatan dan MENGHENTIKAN eksekusi script.
 * * @param mysqli $conn Koneksi database.
 * @param int $provider_id ID provider yang sedang login.
 * @param string $page_title Judul halaman yang sedang diakses.
 * @return bool Mengembalikan TRUE jika provider sudah 'verified'.
 */
function check_provider_verification($conn, $provider_id, $page_title) {
    if (!$provider_id) {
        // Jika ID Provider tidak ada, anggap tidak valid.
        display_verification_required("Akses Ditolak", "ID Provider tidak ditemukan. Mohon login ulang.", $page_title);
        exit();
    }
    
    try {
        // Query untuk mengambil status verifikasi (verification_status)
        $stmt = $conn->prepare("SELECT verification_status, company_name FROM providers WHERE id = ?");
        $stmt->bind_param("i", $provider_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            display_verification_required("Provider Tidak Terdaftar", "Data Provider tidak ditemukan di sistem.", $page_title);
            exit();
        }
        
        $provider_data = $result->fetch_assoc();
        $status = $provider_data['verification_status'];
        $company_name = htmlspecialchars($provider_data['company_name']);
        $stmt->close();
        
        // Cek status
        if ($status === 'verified') {
            return true; // Provider sudah diverifikasi, lanjutkan akses halaman
            
        } elseif ($status === 'pending') {
            display_verification_required(
                "Menunggu Verifikasi Admin", 
                "Terima kasih, data perusahaan Anda ({$company_name}) sudah lengkap. Saat ini kami sedang menunggu persetujuan dari Super Admin. Anda akan menerima notifikasi jika proses verifikasi selesai.",
                $page_title,
                "warning"
            );
            exit();
            
        } else { // Meliputi 'unverified', 'rejected', atau status default lainnya
            display_verification_required(
                "Akses Dibatasi: Lengkapi Data Profil", 
                "Untuk dapat mengakses halaman '{$page_title}', Anda harus melengkapi data profil dan dokumen perusahaan Anda agar dapat diverifikasi oleh Super Admin.",
                $page_title,
                "danger"
            );
            exit();
        }
        
    } catch (Exception $e) {
        // Handle error database
        display_verification_required("Error Sistem", "Terjadi kesalahan saat memeriksa status verifikasi: " . $e->getMessage(), $page_title);
        exit();
    }
}


/**
 * Fungsi untuk menampilkan tampilan pesan peringatan
 */
function display_verification_required($heading, $message, $page_title, $type = "danger") {
    // Styling dan HTML untuk menampilkan pesan peringatan yang menarik
    echo "<div class='container mt-5'>";
    echo "  <div class='row justify-content-center'>";
    echo "    <div class='col-md-8'>";
    echo "      <div class='card shadow-lg border-0'>";
    echo "        <div class='card-header bg-{$type} text-white'>";
    echo "          <h4 class='mb-0'><i class='bi bi-shield-fill-exclamation me-2'></i> {$heading}</h4>";
    echo "        </div>";
    echo "        <div class='card-body text-center py-5'>";
    echo "          <h1 class='display-4 text-{$type} mb-4'>⚠️</h1>";
    echo "          <h5 class='card-title mb-4'>Halaman '{$page_title}' Tidak Dapat Diakses</h5>";
    echo "          <p class='card-text lead'>{$message}</p>";
    
    // Tampilkan tombol untuk melengkapi profil hanya jika statusnya belum 'pending'
    if ($type === "danger") {
        echo "          <a href='/dashboard?p=profile' class='btn btn-lg btn-{$type} mt-4'><i class='bi bi-person-lines-fill me-2'></i> Lengkapi Profil Sekarang</a>";
    }
    
    echo "        </div>";
    echo "      </div>";
    echo "    </div>";
    echo "  </div>";
    echo "</div>";
    
    // Asumsi: Anda juga perlu meng-include CSS (Bootstrap dan Bootstrap Icons) 
    // di file induk agar tampilan ini terlihat bagus.
}
?>