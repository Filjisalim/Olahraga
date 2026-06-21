<?php
/**
 * auth_check.php
 * Dipanggil di awal file API yang butuh login admin (tambah/edit/hapus modul)
 * Cukup tulis: require_once 'auth_check.php';  di baris atas file tersebut
 */

session_start();

function requireAdmin() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    if (empty($token) || !isset($_SESSION['admin_token']) || $token !== $_SESSION['admin_token']) {
        http_response_code(401);
        echo json_encode(['error' => 'Akses ditolak. Silakan login terlebih dahulu.']);
        exit;
    }
}

requireAdmin();