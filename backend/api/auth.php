<?php
/**
 * auth.php
 * Login admin — menghasilkan token sederhana yang disimpan di browser admin
 *
 * Cara pakai dari JS:
 * POST ke auth.php?action=login  dengan body { "username": "...", "password": "..." }
 * POST ke auth.php?action=verify dengan header Authorization: Bearer <token>
 */

require_once 'config.php';
session_start();

$action = $_GET['action'] ?? '';

if ($action === 'login') {

    $body = json_decode(file_get_contents('php://input'), true);
    $username = $body['username'] ?? '';
    $password = $body['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Username dan password wajib diisi'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        jsonResponse(['error' => 'Username atau password salah'], 401);
    }

    // Buat token sederhana (random string) dan simpan di session server
    $token = bin2hex(random_bytes(32));
    $_SESSION['admin_token'] = $token;
    $_SESSION['admin_id']    = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];

    jsonResponse([
        'success'  => true,
        'token'    => $token,
        'username' => $admin['username'],
    ]);

} elseif ($action === 'verify') {

    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    if (empty($token) || !isset($_SESSION['admin_token']) || $token !== $_SESSION['admin_token']) {
        jsonResponse(['valid' => false], 401);
    }

    jsonResponse(['valid' => true, 'username' => $_SESSION['admin_username']]);

} elseif ($action === 'logout') {

    session_destroy();
    jsonResponse(['success' => true]);

} else {
    jsonResponse(['error' => 'Action tidak dikenali'], 400);
}