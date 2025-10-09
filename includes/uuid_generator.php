<?php
// File: includes/uuid_generator.php

/**
 * Fungsi untuk menghasilkan UUID versi 4 (Universally Unique Identifier).
 * Digunakan untuk kolom 'uuid' di tabel database Anda.
 * * @return string UUID V4 dalam format 8-4-4-4-12
 */
if (!function_exists('generate_uuid')) {
    function generate_uuid() {
        // Algoritma UUID V4 (random)
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // set version to 0100
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits for "clk_seq_hi_res",
            // set variant to 10xx
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
// Tidak perlu tag penutup ?> di file PHP murni (hanya berisi kode PHP)