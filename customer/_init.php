<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'customer') {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$namaCustomer = htmlspecialchars($_SESSION['user']['username'] ?? $_SESSION['user']['nama'] ?? $_SESSION['user']['email'] ?? 'Pelanggan');
