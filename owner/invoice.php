<?php

declare(strict_types=1);

session_start();
include '../koneksi.php';
require_once __DIR__ . '/../lib/invoice_render.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    header('Location: ../index.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$data = invoice_muat_data($conn, $id);
if ($data === null) {
    header('Location: pesanan.php');
    exit;
}

invoice_halaman($data['tx'], $data['items'], 'pesanan_detail.php?id=' . $id, 'Kembali ke detail');
