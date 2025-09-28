<?php
// File: includes/modals/restore_trip_modal.php
// Pastikan file ini berada di path yang benar: includes/modals/
?>

<!-- Modal Konfirmasi Restore Trip -->
<div class="modal fade" id="restoreTripModal" tabindex="-1" aria-labelledby="restoreTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="restoreTripModalLabel"><i class="fas fa-undo"></i> Konfirmasi Restore Trip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin mengembalikan trip <strong id="tripTitleToRestore"></strong> ke daftar trip aktif?</p>
                <p class="text-danger small">Trip akan kembali terlihat oleh pelanggan jika statusnya *published*.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <!-- Form untuk mengirim permintaan Restore ke trip_process.php -->
                <form id="restoreTripForm" action="/process/trip_process.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="restore_trip">
                    <input type="hidden" name="trip_id" id="tripIdToRestore">
                    <button type="submit" class="btn btn-success">Ya, Restore Trip</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Script untuk mengisi data ID dan Judul ke dalam modal sebelum ditampilkan
    document.addEventListener('DOMContentLoaded', function () {
        const restoreTripModal = document.getElementById('restoreTripModal');
        restoreTripModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const tripId = button.getAttribute('data-trip-id');
            const tripTitle = button.getAttribute('data-trip-title');
            
            const modalTitleElement = restoreTripModal.querySelector('#tripTitleToRestore');
            const modalIdInput = restoreTripModal.querySelector('#tripIdToRestore');
            
            if (modalTitleElement) {
                modalTitleElement.textContent = tripTitle;
            }
            if (modalIdInput) {
                modalIdInput.value = tripId;
            }
        });
    });
</script>
