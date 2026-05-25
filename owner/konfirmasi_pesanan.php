<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    // Update status to paid and order status for transfer payments
    mysqli_query(
        $conn,
        "UPDATE transaksi
         SET status_pembayaran='lunas', status='dibayar', waktu_pembayaran=NOW()
         WHERE id=$id AND metode_pembayaran='transfer' AND status_pembayaran='menunggu_konfirmasi'"
    );
}

$returnPage = 'pesanan.php';
if (isset($_GET['return']) && $_GET['return'] === 'dashboard') {
    $returnPage = 'dashboard.php';
}

header("Location: {$returnPage}");
exit;