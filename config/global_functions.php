<?php
/**
 * Fungsi Global untuk Merekam Aktivitas Provider (Audit Logging)
 * * Harus dipanggil setelah koneksi database ($conn) dibuat.
 * * @param mysqli $conn - Objek koneksi database.
 * @param int $providerId - ID provider yang melakukan aksi (akan di-cast sebagai string di DB karena BIGINT).
 * @param string $actionType - Jenis aksi (CREATE, UPDATE, DELETE, LOGIN, LOGOUT).
 * @param string $tableName - Tabel yang terpengaruh (misal: trips, vouchers).
 * @param int|null $recordId - ID record yang terpengaruh (boleh NULL, akan di-cast sebagai string/null).
 * @param string $description - Deskripsi rinci mengenai aksi yang dilakukan.
 * @return bool
 */
function log_provider_activity(
    mysqli $conn, 
    int $providerId, // PHP menanganinya sebagai int/string
    string $actionType, 
    string $tableName, 
    ?int $recordId, // PHP menanganinya sebagai int/string
    string $description
): bool {
    // 1. Ambil data kontekstual
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
    
    // Pastikan deskripsi tidak terlalu panjang
    $description = substr($description, 0, 1000); 

    // Konversi ID ke String untuk binding agar kompatibel dengan BIGINT
    $providerIdStr = (string) $providerId;
    $recordIdStr = $recordId !== null ? (string) $recordId : null;

    try {
        $stmt = $conn->prepare("
            INSERT INTO provider_logs 
                (provider_id, action_type, table_name, record_id, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        // Binding semua nilai ID sebagai string ('s') untuk kompatibilitas BIGINT dan NULL
        $stmt->bind_param(
            "sssssss", // Semua parameter dibinding sebagai string
            $providerIdStr, 
            $actionType, 
            $tableName, 
            $recordIdStr, // Jika null, MySQLi akan menangani null string dengan benar pada kolom yang nullable
            $description, 
            $ipAddress, 
            $userAgent
        );

        $success = $stmt->execute();
        $stmt->close();
        
        return $success;

    } catch (Exception $e) {
        error_log("Gagal log aktivitas provider: " . $e->getMessage());
        return false; 
    }
}
?>
