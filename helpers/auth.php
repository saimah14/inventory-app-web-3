<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Mengecek apakah pengguna sudah login.
 * Jika belum, redirect ke halaman login.
 */
function checkAuth() {
    if (!isset($_SESSION['user'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Mengecek hak akses berdasarkan role pengguna.
 */
function checkRole(array $allowedRoles) {
    checkAuth();
    if (!in_array($_SESSION['user']['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        exit('Akses Ditolak: Anda tidak memiliki izin.');
    }
}