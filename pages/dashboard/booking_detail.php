<?php
// File: pages/dashboard/booking_detail.php
// Asumsi: Di-include melalui dashboard.php. $conn dan $actual_provider_id tersedia.

// =========================================================================
// 1. KONFIGURASI AWAL DAN FUNGSI
// =========================================================================

// Base URL untuk akses gambar bukti pembayaran (sudah fix dengan '/public')
// Catatan: Hapus trailing slash di BASE_URL_PROOF
const BASE_URL_PROOF = 'https://api-travelers.karyadeveloperindonesia.com/public';

function generateUniqueInvoiceNumber($conn) {
    // Fungsi untuk membuat nomor invoice unik
    $prefix = "INV/" . date("Y") . "/";
    
    // Perbaikan: Gunakan MAX(id) untuk mendapatkan urutan yang benar
    $result = $conn->query("SELECT MAX(id) AS max_id FROM bookings");
    $row = $result->fetch_assoc();
    $sequence = ($row['max_id'] ?? 0) + 1; // Mulai dari ID terakhir + 1

    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// =========================================================================
// 2. LOGIC POST PROCESS (HARUS DI LAKUKAN SEBELUM OUTPUT APAPUN!)
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $booking_id_to_process = (int)($_POST['booking_id'] ?? 0);
    $redirect_url = "/dashboard?p=booking_detail&id=" . $booking_id_to_process;

    if ($booking_id_to_process > 0) {
        // Mulai transaksi untuk memastikan konsistensi data
        $conn->begin_transaction(); 

        try {
            // Cek kepemilikan dan status, DAN AMBIL DATA HARGA DARI TRIPS UNTUK VERIFIKASI
            $stmt_check = $conn->prepare("
                SELECT 
                    b.status, b.amount_paid, b.num_of_people, b.proof_of_payment_path,
                    t.price AS trip_price, t.discount_price AS trip_discount_price -- <<< TAMBAHAN HARGA TRIP
                FROM bookings b 
                JOIN trips t ON b.trip_id = t.id 
                WHERE b.id = ? AND t.provider_id = ?
            ");
            $stmt_check->bind_param("ii", $booking_id_to_process, $actual_provider_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $current_booking = $result_check->fetch_assoc();
            $stmt_check->close();

            if ($result_check->num_rows === 0) {
                throw new Exception("Akses ditolak atau pemesanan tidak ditemukan.");
            }
            $current_status = $current_booking['status'];
            $has_proof = !empty(trim($current_booking['proof_of_payment_path'] ?? ''));
            
            // ====================================================
            // !!! LOGIKA HARGA PER ORANG YANG BARU (POINT 1 & 2) !!!
            // Dilakukan di sini untuk validasi sebelum APPROVED
            // ====================================================
            $discount_price = (float)($current_booking['trip_discount_price'] ?? 0);
            $regular_price = (float)($current_booking['trip_price'] ?? 0);

            // 1. Hitung Harga Per Orang
            $price_per_person = ($discount_price > 0) ? $discount_price : $regular_price;
            
            // 2. Hitung Total Harga Aktual
            $actual_price_calculated = $price_per_person * $current_booking['num_of_people'];
            $amount_paid = $current_booking['amount_paid']; 
            // ====================================================


            // Verifikasi wajib: Hanya bisa diproses dari status 'waiting_confirmation'
            if ($current_status !== 'waiting_confirmation') {
                 throw new Exception("Status pemesanan bukan waiting_confirmation. Aksi verifikasi ditolak.");
            }

            // Verifikasi wajib: Harus ada bukti transfer
            if (!$has_proof) {
                 throw new Exception("Tidak ada bukti transfer yang diunggah.");
            }
            
            // Logika aksi: CONFIRM atau DECLINE
            if ($action === 'confirm_payment_and_invoice') { // --- ACTION APPROVE ---
                
                // VERIFIKASI Ulang berdasarkan harga yang dihitung dari trips
                if ($amount_paid < $actual_price_calculated) {
                    throw new Exception("Jumlah yang dibayar oleh client (Rp " . number_format($amount_paid, 0, ',', '.') . ") kurang dari total harga aktual (Rp " . number_format($actual_price_calculated, 0, ',', '.') . "). Harap DECLINE dan minta client melunasi.");
                }
                
                $new_invoice_number = generateUniqueInvoiceNumber($conn);
                $paid_at = date('Y-m-d H:i:s');
                
                // Update status booking ke 'paid'
                $stmt_update_booking = $conn->prepare("
                    UPDATE bookings SET 
                        status = 'paid', 
                        invoice_number = ?, 
                        payment_confirmation_at = ?, 
                        admin_verification_note = NULL
                    WHERE id = ?
                ");
                $stmt_update_booking->bind_param("ssi", $new_invoice_number, $paid_at, $booking_id_to_process);
                $stmt_update_booking->execute();
                $stmt_update_booking->close();
                
                // Update/Insert Payment Record
                $stmt_check_payment = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
                $stmt_check_payment->bind_param("i", $booking_id_to_process);
                $stmt_check_payment->execute();
                $result_payment = $stmt_check_payment->get_result();
                $stmt_check_payment->close();

                if ($result_payment->num_rows > 0) {
                    $stmt_update_payment = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = ? WHERE booking_id = ?");
                    $stmt_update_payment->bind_param("si", $paid_at, $booking_id_to_process);
                    $stmt_update_payment->execute();
                    $stmt_update_payment->close();
                } else {
                    $stmt_insert_payment = $conn->prepare("INSERT INTO payments (booking_id, amount, method, status, paid_at, uuid) 
                                                          VALUES (?, ?, 'Transfer Bank (Konfirmasi Admin)', 'paid', ?, UUID())");
                    $stmt_insert_payment->bind_param("ids", $booking_id_to_process, $amount_paid, $paid_at); 
                    $stmt_insert_payment->execute();
                    $stmt_insert_payment->close();
                }

                $conn->commit();
                $_SESSION['dashboard_message'] = "Pembayaran berhasil dikonfirmasi. Status diubah menjadi PAID, dan Invoice **" . htmlspecialchars($new_invoice_number) . "** telah diterbitkan.";
                $_SESSION['dashboard_message_type'] = 'success';
                
            } elseif ($action === 'decline_payment') { // --- ACTION DECLINE ---

                $admin_note = trim($_POST['admin_verification_note'] ?? '');
                if (empty($admin_note)) {
                     throw new Exception("Catatan penolakan wajib diisi untuk memberitahu Client.");
                }

                $paid_at_null = NULL;
                // Update status kembali ke 'unpaid', tambahkan catatan admin, dan HAPUS bukti/notes client
                $stmt_update_booking = $conn->prepare("
                    UPDATE bookings SET 
                        status = 'unpaid', 
                        admin_verification_note = ?, 
                        payment_confirmation_at = ?, 
                        proof_of_payment_path = NULL, 
                        payment_notes = NULL
                    WHERE id = ?
                ");
                $stmt_update_booking->bind_param("ssi", $admin_note, $paid_at_null, $booking_id_to_process);
                $stmt_update_booking->execute();
                $stmt_update_booking->close();
                
                // Opsional: Update status payment record (jika ada) ke 'rejected' atau hapus. Di sini kita biarkan payment record tetap, namun status booking diubah.

                $conn->commit();
                $_SESSION['dashboard_message'] = "Pembayaran Ditolak. Status dikembalikan ke UNPAID. Client harus upload ulang bukti.";
                $_SESSION['dashboard_message_type'] = 'info';

            } else {
                 throw new Exception("Aksi tidak valid.");
            }

        } catch (Exception $e) {
            $conn->rollback(); 
            $_SESSION['dashboard_message'] = "Gagal memproses aksi: " . $e->getMessage();
            $_SESSION['dashboard_message_type'] = 'danger';
        }

        // REDIRECT HARUS SELALU ADA DI AKHIR LOGIC POST
        header("Location: " . $redirect_url);
        exit();
    }
}
// =========================================================================


// =========================================================================
// 3. LOGIC PENGAMBILAN DATA (HANYA DIEKSEKUSI JIKA BUKAN POST)
// =========================================================================
$booking_id = (int)($_GET['id'] ?? 0);
$booking_data = null;
$error = null;

if ($booking_id > 0) {
    try {
        // Ambil semua kolom yang dibutuhkan, termasuk data harga dari trips
        $stmt = $conn->prepare("
            SELECT 
                b.*, 
                t.title AS trip_title, 
                t.price AS trip_price,            -- <<< HARGA NORMAL
                t.discount_price AS trip_discount_price, -- <<< HARGA DISKON
                u.name AS client_name, u.email AS client_email,
                p.paid_at 
            FROM bookings b
            JOIN trips t ON b.trip_id = t.id
            JOIN users u ON b.user_id = u.id
            LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'paid'
            WHERE b.id = ? AND t.provider_id = ?
        ");
        $stmt->bind_param("ii", $booking_id, $actual_provider_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $booking_data = $result->fetch_assoc();
        } else {
            $error = "Pemesanan tidak ditemukan atau bukan milik Anda.";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Gagal memuat detail pemesanan: " . $e->getMessage();
    }
} else {
    $error = "ID Pemesanan tidak valid.";
}

// REDIRECT JIKA TERJADI ERROR PADA SAAT MEMUAT HALAMAN (GET)
if ($error && !isset($_SESSION['dashboard_message'])) { // Jangan timpa jika sudah ada pesan error dari POST
    $_SESSION['dashboard_message'] = $error;
    $_SESSION['dashboard_message_type'] = "danger";
    header("Location: /dashboard?p=orders"); 
    exit(); 
}

// Pastikan data booking ada sebelum melanjutkan ke perhitungan dan tampilan
if (!$booking_data) {
    // Jika redirect GET gagal, set error untuk tampilan (meskipun harusnya sudah redirect)
    $error = "Pemesanan tidak ditemukan atau ID tidak valid.";
    $booking_data = []; // Hindari error array kosong saat render
}

// =========================================================================
// !!! PERHITUNGAN HARGA BARU BERDASARKAN LOGIKA TRIP (POINT 1 & 2) !!!
// =========================================================================
$discount_price = (float)($booking_data['trip_discount_price'] ?? 0);
$regular_price = (float)($booking_data['trip_price'] ?? 0);
$num_of_people = (int)($booking_data['num_of_people'] ?? 0);

// 1. Hitung Harga Per Orang
// Jika discount_price > 0 (bukan 0 atau NULL), gunakan discount_price. Jika tidak, gunakan price normal.
$price_per_person = ($discount_price > 0) ? $discount_price : $regular_price;

// 2. Hitung Total Harga Aktual
$actual_price = $price_per_person * $num_of_people;

$paid_from_client = (float)($booking_data['amount_paid'] ?? 0); 
$payment_difference = $paid_from_client - $actual_price;


// Tampilkan pesan notifikasi dari session
$message = $_SESSION['dashboard_message'] ?? '';
$message_type = $_SESSION['dashboard_message_type'] ?? 'danger';
unset($_SESSION['dashboard_message']);
unset($_SESSION['dashboard_message_type']);

// =========================================================================
// 4. TAMPILAN HTML
// =========================================================================
?>

<h1 class="mb-4">Detail Pemesanan #<?php echo htmlspecialchars($booking_data['id'] ?? ''); ?></h1>
<p class="text-muted">Trip: <b><?php echo htmlspecialchars($booking_data['trip_title'] ?? 'N/A'); ?></b></p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Ringkasan Pemesanan</div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Client</th>
                        <td><?php echo htmlspecialchars($booking_data['client_name'] ?? ''); ?> (<?php echo htmlspecialchars($booking_data['client_email'] ?? ''); ?>)</td>
                    </tr>
                    <tr>
                        <th>Nomor Invoice</th>
                        <td>
                            <?php if (!empty($booking_data['invoice_number'])): ?>
                                <b><?php echo htmlspecialchars($booking_data['invoice_number']); ?></b>
                            <?php else: ?>
                                <span class="text-secondary">Belum Terbit</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Jumlah Peserta</th>
                        <td><?php echo $num_of_people; ?> orang</td>
                    </tr>
                    
                    <!-- !!! TAMPILAN BARU SESUAI LOGIKA BARU !!! -->
                    <tr>
                        <th>Harga Normal Trip</th>
                        <td>Rp <?php echo number_format($regular_price, 0, ',', '.'); ?></td>
                    </tr>
                    <?php if ($discount_price > 0): ?>
                    <tr>
                        <th>Harga Diskon Trip</th>
                        <td class="text-success">Rp <?php echo number_format($discount_price, 0, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Harga Per Orang</th>
                        <td>
                            <b>Rp <?php echo number_format($price_per_person, 0, ',', '.'); ?></b>
                        </td>
                    </tr>
                    <!-- !!! AKHIR TAMPILAN BARU !!! -->

                    <tr>
                        <th>Total Harga Aktual</th>
                        <td>
                            <b>Rp <?php echo number_format($actual_price, 0, ',', '.'); ?></b>
                            <small class="text-muted d-block">(<?php echo $num_of_people; ?> x Rp <?php echo number_format($price_per_person, 0, ',', '.'); ?>)</small>
                        </td>
                    </tr>
                    <tr>
                        <th>Dibayar dari Client</th>
                        <td>
                            <?php 
                            $paid_color = ($payment_difference < 0) ? 'text-danger' : 'text-success';
                            $paid_label = ($payment_difference >= 0) ? 'LUNAS' : 'KURANG';
                            ?>
                            <b class="<?php echo $paid_color; ?>">Rp <?php echo number_format($paid_from_client, 0, ',', '.'); ?></b> 
                            <span class="badge bg-<?php echo ($payment_difference < 0) ? 'danger' : 'success'; ?>"><?php echo $paid_label; ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Status Pembayaran</th>
                        <td>
                            <?php 
                                $status = $booking_data['status'] ?? 'unknown';
                                $badge_class = 'secondary';
                                if ($status === 'paid' || $status === 'completed') $badge_class = 'success';
                                else if ($status === 'unpaid') $badge_class = 'warning text-dark';
                                else if ($status === 'cancelled') $badge_class = 'danger';
                                else if ($status === 'waiting_confirmation') $badge_class = 'info text-dark';
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($status))); ?></span>
                        </td>
                    </tr>
                    
                    <?php if (!empty($booking_data['payment_confirmation_at'])): ?>
                    <tr>
                        <th>Waktu Konfirmasi</th>
                        <td><?php echo date('d M Y H:i', strtotime($booking_data['payment_confirmation_at'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking_data['admin_verification_note'])): ?>
                    <tr>
                        <th>Catatan Admin</th>
                        <td><span class="text-danger"><?php echo nl2br(htmlspecialchars($booking_data['admin_verification_note'])); ?></span></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <?php 
        $status_data = $booking_data['status'] ?? '';
        $has_proof = !empty(trim($booking_data['proof_of_payment_path'] ?? ''));
        $is_waiting_confirmation = $status_data === 'waiting_confirmation';
        
        // --- BLOK TOMBOL AKSI ---
        if ($is_waiting_confirmation && $has_proof): 
            ?>
            <div class="alert alert-warning">
                <b>Pembayaran Menunggu Konfirmasi!</b> Harap verifikasi bukti transfer di sebelah kanan.
            </div>
            
            <div class="d-flex gap-2 mb-4">
                <form method="POST" onsubmit="return confirm('Yakin ingin MENGKONFIRMASI pembayaran ini? Status akan diubah menjadi PAID, Invoice akan diterbitkan.');">
                    <input type="hidden" name="action" value="confirm_payment_and_invoice">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-2"></i> Approve Pembayaran
                    </button>
                </form>
                
                <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#declineModal">
                    <i class="bi bi-x-circle me-2"></i> Decline Pembayaran
                </button>
            </div>
            
        <?php 
        // 2. Kondisi: PAID (SUDAH LUNAS)
        elseif ($status_data === 'paid'): ?>
            <div class="alert alert-success d-flex justify-content-between align-items-center">
                <div>
                    Pembayaran telah <b>LUNAS</b> dikonfirmasi.
                </div>
                <a href="/generate_invoice.php?id=<?php echo $booking_data['id']; ?>" target="_blank" class="btn btn-sm btn-light">
                     <i class="bi bi-file-earmark-pdf me-1"></i> Download Invoice
                </a>
            </div>
            
        <?php 
        // 3. Kondisi: Status lain yang membutuhkan perhatian (e.g., unpaid)
        else: ?>
             <div class="alert alert-info">
                Status saat ini: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($status_data))); ?>. Tidak ada aksi verifikasi yang tersedia.
            </div>
        <?php endif; ?>

    </div>

    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Bukti Transfer Client</div>
            <div class="card-body text-center">
                
                <?php if (!empty($booking_data['payment_notes'])): ?>
                    <div class="alert alert-light text-start p-2 mb-3 border">
                        <small><b>Catatan Client:</b> <?php echo nl2br(htmlspecialchars($booking_data['payment_notes'])); ?></small>
                    </div>
                <?php endif; ?>

                <?php if ($has_proof): 
                    // FIX PATH: Menghilangkan slash di awal path DB dan menggabungkannya dengan BASE_URL
                    $db_path = $booking_data['proof_of_payment_path'] ?? ''; 
                    $clean_path = ltrim($db_path, '/');
                    $proof_url = BASE_URL_PROOF . '/' . htmlspecialchars($clean_path);
                ?>
                    <p class="text-success">Bukti transfer sudah diunggah.</p>
                    <a href="<?php echo $proof_url; ?>" target="_blank" class="btn btn-sm btn-info mb-3">
                        <i class="bi bi-eye"></i> Lihat Bukti Transfer
                    </a>
                    <div class="border rounded p-1">
                         <img src="<?php echo $proof_url; ?>" alt="Bukti Transfer" class="img-fluid" style="max-height: 300px; object-fit: contain;">
                    </div>
                <?php else: ?>
                    <p class="text-muted">Bukti transfer belum diunggah oleh Client.</p>
                    <i class="bi bi-file-earmark-image" style="font-size: 5rem; color: #ccc;"></i>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="declineModal" tabindex="-1" aria-labelledby="declineModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="declineModalLabel">Tolak Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Anda akan <b>MENOLAK</b> bukti pembayaran ini. Status booking akan dikembalikan menjadi <b>UNPAID</b>, dan Client harus mengunggah bukti baru.</p>
                    <div class="mb-3">
                        <label for="admin_verification_note" class="form-label">Catatan Penolakan Wajib (Akan dilihat Client)</label>
                        <textarea class="form-control" id="admin_verification_note" name="admin_verification_note" rows="4" required placeholder="Contoh: Bukti transfer tidak valid/jelas, atau jumlah transfer tidak sesuai."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="decline_payment">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_data['id'] ?? ''; ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak & Kembalikan ke UNPAID</button>
                </div>
            </form>
        </div>
    </div>
</div>