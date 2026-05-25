<?php
session_start();
include "../koneksi.php";
require_once __DIR__ . '/produk_upload.php';
require_once __DIR__ . '/../lib/kategori_produk.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$errMsg = '';

if (isset($_POST['submit'])) {
    $nama = trim($_POST['nama'] ?? '');
    $harga = (int) ($_POST['harga'] ?? 0);
    $stok = (int) ($_POST['stok'] ?? 0);
    $kategori = trim($_POST['kategori'] ?? '');
    if (!kategori_produk_valid($kategori)) {
        $kategori = '';
    }

    if ($nama === '') {
        $errMsg = 'Nama produk wajib diisi.';
    } else {
        $gambar = '';
        if (!empty($_FILES['gambar']['name']) || (isset($_FILES['gambar']['error']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE)) {
            $up = produk_save_upload($_FILES['gambar']);
            if ($up === false) {
                $errMsg = 'Gambar gagal diunggah. Gunakan JPG, PNG, GIF, atau WebP (maks. 5 MB).';
            } else {
                $gambar = $up;
            }
        }

        if ($errMsg === '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO produk (nama_produk, kategori, harga, stok, gambar) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssiis", $nama, $kategori, $harga, $stok, $gambar);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header("Location: dashboard.php");
                    exit;
                }
                mysqli_stmt_close($stmt);
            }
            if ($gambar !== '') {
                produk_delete_image_file($gambar);
            }
            $errMsg = 'Gagal menyimpan data.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="form-container form-container-wide">
    <h2>Tambah Produk</h2>

    <?php if ($errMsg !== '') { ?>
        <p class="form-alert" role="alert"><?= htmlspecialchars($errMsg) ?></p>
    <?php } ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="nama" placeholder="Nama Produk" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>

        <label class="file-label" for="kategori">Kategori toko kebun</label>
        <select name="kategori" id="kategori" class="form-select">
            <option value="">— Pilih kategori —</option>
            <?php foreach (kategori_produk_map() as $slug => $lab) { ?>
                <option value="<?= htmlspecialchars($slug) ?>"<?= (($_POST['kategori'] ?? '') === $slug) ? ' selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php } ?>
        </select>

        <input type="number" name="harga" placeholder="Harga" min="0" value="<?= htmlspecialchars($_POST['harga'] ?? '') ?>" required>
        <input type="number" name="stok" placeholder="Stok" min="0" value="<?= htmlspecialchars($_POST['stok'] ?? '') ?>" required>

        <label class="file-label" for="gambar">Gambar produk <span class="optional">(opsional)</span></label>
        <input type="file" name="gambar" id="gambar" accept="image/jpeg,image/png,image/gif,image/webp">
        <p class="form-hint">Format: JPG, PNG, GIF, WebP — maks. 5 MB.</p>

        <button type="submit" name="submit">Simpan</button>
    </form>
    <a class="form-back" href="dashboard.php">← Kembali ke dashboard</a>
</div>

</body>
</html>
