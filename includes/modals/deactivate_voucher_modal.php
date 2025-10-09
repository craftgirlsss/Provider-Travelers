<div class="modal fade" id="deactivateVoucherModal" tabindex="-1" aria-labelledby="deactivateVoucherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deactivateVoucherModalLabel">Konfirmasi Nonaktifkan Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin **menonaktifkan** voucher "<span id="voucherCodePlaceholder" class="fw-bold"></span>"?<br>
                Aksi ini akan segera menghentikan penggunaan voucher tersebut oleh pelanggan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <form id="deactivateVoucherForm" action="/process/voucher_process.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="deactivate_voucher">
                    <input type="hidden" name="voucher_id" id="modalVoucherId">
                    <button type="submit" class="btn btn-danger">Ya, Nonaktifkan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var deactivateVoucherModal = document.getElementById('deactivateVoucherModal');
    
    if (deactivateVoucherModal) {
        deactivateVoucherModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; 
            var voucherId = button.getAttribute('data-voucher-id');
            var voucherCode = button.getAttribute('data-voucher-code');

            // Isi nilai ke field tersembunyi
            document.getElementById('modalVoucherId').value = voucherId;
            // Isi kode voucher ke placeholder
            document.getElementById('voucherCodePlaceholder').textContent = voucherCode;
        });
    }
});
</script>