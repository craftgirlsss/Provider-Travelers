<?php
// File: tracking.php
// Halaman untuk menampilkan lokasi perjalanan secara publik menggunakan data dari URL.

// Pastikan koneksi database tersedia jika Anda ingin mengambil data dari trip_departures
require_once __DIR__ . '/config/db_config.php'; 
global $conn;

$trip_id = $_GET['trip_id'] ?? 0;
$ref = $_GET['ref'] ?? '';
$error = null;
$trip_info = [];

// --- Logika Pengambilan Data Trip dan Posisi Driver ---
// Asumsi: Kita menggunakan $ref untuk mencari jadwal keberangkatan yang valid
if ($trip_id > 0 && !empty($ref)) {
    try {
        // Query untuk mendapatkan detail jadwal, trip_title, dan driver_uuid
        $stmt = $conn->prepare("
            SELECT 
                td.vehicle_type, td.license_plate, 
                t.title AS trip_title,
                td.driver_uuid
            FROM trip_departures td
            JOIN trips t ON td.trip_id = t.id
            WHERE td.trip_id = ? AND td.tracking_link LIKE ? 
            LIMIT 1
        ");
        
        // Match tracking_link menggunakan LIKE karena kita mencari hash unik ($ref)
        $tracking_pattern = "%&ref=" . $ref;
        $stmt->bind_param("is", $trip_id, $tracking_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Jadwal Perjalanan atau Kode Pelacakan tidak valid.";
        } else {
            $trip_info = $result->fetch_assoc();
            
            // --- Placeholder Posisi Driver Real-Time ---
            // DALAM APLIKASI NYATA: Anda akan mengambil latitude dan longitude 
            // driver dari tabel terpisah (misalnya 'driver_locations') 
            // menggunakan $trip_info['driver_uuid'].
            
            // Placeholder Lokasi (Jakarta, Indonesia)
            // Ganti ini dengan data real-time dari database Anda!
            $current_lat = -6.2000; 
            $current_lon = 106.8167;
            
            // Anda dapat menyimpan data ini dalam array untuk digunakan di JavaScript
            $trip_info['current_lat'] = $current_lat;
            $trip_info['current_lon'] = $current_lon;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Gagal mengambil data perjalanan: " . $e->getMessage();
    }
} else {
    $error = "Parameter URL tidak lengkap (Trip ID atau Ref hilang).";
}

// Data akan di-pass ke JavaScript sebagai JSON
$js_data = json_encode($trip_info);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelacakan Perjalanan - ID #<?php echo htmlspecialchars($trip_id); ?></title>
    
    <!-- Tailwind CSS (untuk styling modern) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        /* Mengatur agar peta mengisi area yang tersedia */
        #map {
            height: 100vh;
            width: 100%;
        }
    </style>
</head>
<body class="font-sans antialiased">

<?php if ($error): ?>
    <!-- Tampilan Error -->
    <div class="fixed inset-0 flex items-center justify-center bg-gray-500 bg-opacity-75 z-50">
        <div class="bg-white p-8 rounded-lg shadow-2xl max-w-lg mx-4 text-center">
            <h2 class="text-xl font-bold text-red-600 mb-4">Akses Ditolak</h2>
            <p class="text-gray-700 mb-6"><?php echo htmlspecialchars($error); ?></p>
            <a href="/" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Kembali ke Beranda</a>
        </div>
    </div>
<?php else: ?>

    <!-- Tampilan Utama Tracking -->
    <div class="flex flex-col md:flex-row h-screen">
        
        <!-- Sidebar Informasi (Kiri/Atas) -->
        <div class="md:w-1/4 w-full p-6 bg-white shadow-xl z-10 md:h-full overflow-y-auto">
            <h1 class="text-2xl font-extrabold text-indigo-700 border-b pb-2 mb-4">
                <i class="fas fa-route"></i> Pelacakan Perjalanan
            </h1>
            
            <div class="space-y-4">
                <div class="p-3 bg-indigo-50 rounded-lg">
                    <p class="text-xs font-semibold text-gray-500">Trip</p>
                    <p class="text-lg font-bold text-indigo-900"><?php echo htmlspecialchars($trip_info['trip_title']); ?></p>
                </div>
                <div class="p-3 border-l-4 border-yellow-500 bg-yellow-50 rounded-lg">
                    <p class="text-xs font-semibold text-gray-500">Kendaraan</p>
                    <p class="text-md font-semibold text-gray-800"><?php echo htmlspecialchars($trip_info['vehicle_type']); ?></p>
                </div>
                <div class="p-3 border-l-4 border-yellow-500 bg-yellow-50 rounded-lg">
                    <p class="text-xs font-semibold text-gray-500">Plat Nomor</p>
                    <p class="text-md font-bold text-gray-800"><?php echo htmlspecialchars($trip_info['license_plate']); ?></p>
                </div>

                <div class="pt-4 border-t">
                    <p class="text-sm font-semibold text-gray-600 mb-2">Status Saat Ini:</p>
                    <div id="status-location" class="text-sm text-gray-700 bg-gray-100 p-2 rounded">
                        Mengambil lokasi...
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                 <p class="text-xs text-gray-400">ID Pelacakan: <?php echo htmlspecialchars($ref); ?></p>
            </div>
        </div>

        <!-- Area Peta (Kanan/Bawah) -->
        <div id="map" class="md:w-3/4 w-full h-full"></div>
    </div>
    
    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        const TRIP_DATA = <?php echo $js_data; ?>;
        const INITIAL_LAT = TRIP_DATA.current_lat;
        const INITIAL_LON = TRIP_DATA.current_lon;
        const DRIVER_UUID = TRIP_DATA.driver_uuid;

        // Inisialisasi Peta
        var map = L.map('map').setView([INITIAL_LAT, INITIAL_LON], 13);

        // Tambahkan layer OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Tambahkan Marker untuk Lokasi Driver
        // Menggunakan icon khusus dari Leaflet/FontAwesome jika Anda memilikinya, 
        // namun disini kita pakai marker default dulu.
        var driverIcon = L.marker([INITIAL_LAT, INITIAL_LON]).addTo(map)
            .bindPopup("<b>Posisi Driver</b><br>Kendaraan: " + TRIP_DATA.license_plate)
            .openPopup();
            
        // Update Status Lokasi di Sidebar
        const statusElement = document.getElementById('status-location');
        statusElement.innerHTML = `Latitude: ${INITIAL_LAT.toFixed(4)}, Longitude: ${INITIAL_LON.toFixed(4)}`;

        // =========================================================
        // FUNGSI SIMULASI UPDATE LOKASI REAL-TIME (PENTING)
        // =========================================================

        /**
         * DALAM APLIKASI NYATA, fungsi ini akan memanggil API endpoint 
         * Anda (misalnya /api/get_location.php?uuid=...) untuk mendapatkan
         * lat/lon terbaru dari database.
         * * Karena kita tidak punya backend API, ini hanya simulasi.
         */
        function updateDriverLocation() {
            // --- GANTI DENGAN LOGIKA FETCH DARI BACKEND API ANDA ---
            
            // SIMULASI: Pindah sedikit ke arah utara-timur
            const newLat = INITIAL_LAT + (Math.random() - 0.5) * 0.005;
            const newLon = INITIAL_LON + (Math.random() - 0.5) * 0.005;

            // Perbarui posisi Marker
            driverIcon.setLatLng([newLat, newLon]);
            
            // Perbarui tampilan status
            statusElement.innerHTML = `Latitude: ${newLat.toFixed(4)}, Longitude: ${newLon.toFixed(4)}`;

            // --- JIKA MENGGUNAKAN FETCH NYATA ---
            /*
            fetch(`/api/get_location.php?uuid=${DRIVER_UUID}`)
                .then(response => response.json())
                .then(data => {
                    if (data.lat && data.lon) {
                        const newPos = [data.lat, data.lon];
                        driverIcon.setLatLng(newPos);
                        map.panTo(newPos); // Pindahkan peta ke lokasi baru
                        statusElement.innerHTML = `Latitude: ${data.lat.toFixed(4)}, Longitude: ${data.lon.toFixed(4)}`;
                    }
                })
                .catch(err => console.error("Gagal update lokasi:", err));
            */
            
            // Atur agar fungsi dipanggil lagi setiap 5 detik
            // Jika menggunakan Fetch nyata, Anda bisa memperpanjang interval ini.
            setTimeout(updateDriverLocation, 5000); 
        }

        // Mulai proses pembaruan lokasi setelah 1 detik
        if (DRIVER_UUID) {
            setTimeout(updateDriverLocation, 1000); 
        }
        
    </script>

<?php endif; ?>

</body>
</html>