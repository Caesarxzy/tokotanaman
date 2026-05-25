<?php
session_start();
include "../koneksi.php";
require_once __DIR__ . '/produk_upload.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $q = mysqli_query($conn, "SELECT gambar FROM produk WHERE id=$id");
    if ($q && ($row = mysqli_fetch_assoc($q))) {
        produk_delete_image_file($row['gambar'] ?? '');
    }
    mysqli_query($conn, "DELETE FROM produk WHERE id=$id");
}

header("Location: dashboard.php");
exit;