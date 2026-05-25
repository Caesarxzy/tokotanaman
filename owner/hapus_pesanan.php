<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    // Delete items first
    mysqli_query($conn, "DELETE FROM transaksi_item WHERE transaksi_id=$id");
    // Then delete transaction
    mysqli_query($conn, "DELETE FROM transaksi WHERE id=$id");
}

header("Location: pesanan.php");
exit;