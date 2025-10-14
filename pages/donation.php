<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h1 class="h3 mb-0"><i class="bi bi-heart-fill me-2"></i> Dukung Kami</h1>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <p class="lead text-center mb-4">
                        Platform ini didirikan untuk membantu menjembatani pemilik usaha dengan calon pelanggan. Dukungan Anda sangat berarti untuk biaya operasional dan pengembangan fitur baru.
                    </p>
                    
                    <h4 class="text-center mb-4 text-secondary">Pilih Metode Donasi Anda:</h4>

                    <div class="donation-method p-3 p-md-4 mb-4 border rounded-3 shadow-sm bg-white">
                        <div class="row align-items-center">
                            <div class="col-sm-4 text-center mb-3 mb-sm-0">
                                <img src="/assets/logo-shopeepay.png" alt="Logo ShopeePay" class="logo-img" style="max-height: 40px;">
                                <h6 class="mt-2 text-muted">ShopeePay</h6>
                            </div>
                            <div class="col-sm-8 text-center text-sm-start">
                                <p class="mb-1 fw-bold">Nomor ShopeePay:</p>
                                <p class="h5 text-primary">0881036480285</p>
                                <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyToClipboard('0881036480285')">
                                    <i class="bi bi-clipboard"></i> Salin Nomor
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="donation-method p-3 p-md-4 border rounded-3 shadow-sm bg-white">
                        <div class="row align-items-center">
                            <div class="col-sm-4 text-center mb-3 mb-sm-0">
                                <img src="/assets/logo-bca.png" alt="Logo Bank BCA" class="logo-img" style="max-height: 40px;">
                                <h6 class="mt-2 text-muted">Bank Central Asia (BCA)</h6>
                            </div>
                            <div class="col-sm-8 text-center text-sm-start">
                                <p class="mb-1 fw-bold">Nomor Rekening:</p>
                                <p class="h5 text-primary">8725164421</p>
                                <p class="text-muted small mb-1">A.N. Saputra Budianto</p>
                                <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyToClipboard('8725164421')">
                                    <i class="bi bi-clipboard"></i> Salin Nomor
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-center mt-5 mb-0 text-success fw-bold">
                        Terima kasih atas kemurahan hati Anda!
                    </p>

                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Nomor ' + text + ' berhasil disalin ke clipboard!');
    }, function(err) {
        console.error('Gagal menyalin: ', err);
        alert('Gagal menyalin. Silakan salin secara manual.');
    });
}
</script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">