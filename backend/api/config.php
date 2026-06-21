<?php
/**
 * config.php
 * File koneksi database — dipakai oleh SEMUA file api/*.php
 *
 * PENTING: Ganti 4 nilai di bawah ini sesuai data dari hosting kamu
 * (kamu akan dapat info ini saat membuat database lewat cPanel)
 */

define('DB_HOST', 'localhost');           // biasanya tetap 'localhost' di shared hosting
define('DB_NAME', 'rythme_db');           // nama database kamu (cek di cPanel > MySQL Databases)
define('DB_USER', 'root');     // username database (BUKAN username cPanel)
define('DB_PASS', '');     // password database

// Koneksi ke database menggunakan PDO (lebih aman dari injection)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
}

// Izinkan akses dari frontend (CORS) — aman karena hanya GET/POST data publik & terproteksi token
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Browser kadang kirim request OPTIONS dulu sebelum POST/PUT — langsung jawab OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Helper: ekstrak video ID dari berbagai format link YouTube
 * Mendukung: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID
 */
function extractYoutubeId($url) {
    if (empty($url)) return null;
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }
    }
    return null;
}

/**
 * Helper: kirim response JSON lalu hentikan eksekusi
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}