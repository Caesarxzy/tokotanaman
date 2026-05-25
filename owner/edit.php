<?php
session_start();
include "../koneksi.php";
require_once __DIR__ . '/produk_upload.php';
require_once __DIR__ . '/../lib/kategori_produk.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header("Location: dashboard.php");
    exit;
}
$data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM produk WHERE id=$id"));
if (!$data) {
    header("Location: dashboard.php");
    exit;
}

$errMsg = '';
$gambarSaatIni = $data['gambar'] ?? '';

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
        $gantiGambar = false;
        $gambarPath = $gambarSaatIni;

        if (!empty($_FILES['gambar']['name']) || (isset($_FILES['gambar']['error']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE)) {
            $up = produk_save_upload($_FILES['gambar']);
            if ($up === false) {
                $errMsg = 'Gambar gagal diunggah. Gunakan JPG, PNG, GIF, atau WebP (maks. 5 MB).';
            } elseif ($up !== '') {
                $gantiGambar = true;
                $gambarPath = $up;
            }
        }

        if ($errMsg === '') {
            if ($gantiGambar) {
                $stmt = mysqli_prepare($conn, "UPDATE produk SET nama_produk=?, kategori=?, harga=?, stok=?, gambar=? WHERE id=?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssiisi", $nama, $kategori, $harga, $stok, $gambarPath, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        produk_delete_image_file($gambarSaatIni);
                        mysqli_stmt_close($stmt);
                        header("Location: dashboard.php");
                        exit;
                    }
                    mysqli_stmt_close($stmt);
                }
                produk_delete_image_file($gambarPath);
                $errMsg = 'Gagal memperbarui data.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE produk SET nama_produk=?, kategori=?, harga=?, stok=? WHERE id=?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssiii", $nama, $kategori, $harga, $stok, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header("Location: dashboard.php");
                        exit;
                    }
                    mysqli_stmt_close($stmt);
                }
                $errMsg = 'Gagal memperbarui data.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="form-container form-container-wide">
    <h2>Edit Produk</h2>

    <?php if ($errMsg !== '') { ?>
        <p class="form-alert" role="alert"><?= htmlspecialchars($errMsg) ?></p>
    <?php } ?>

    <?php if ($gambarSaatIni !== '') { ?>
        <div class="current-image">
            <span class="form-hint">Gambar saat ini</span>
            <img src="<?= htmlspecialchars('../' . $gambarSaatIni) ?>" alt="Preview">
        </div>
    <?php } ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="nama" value="<?= htmlspecialchars($data['nama_produk']) ?>" required>

        <label class="file-label" for="kategori">Kategori toko kebun</label>
        <select name="kategori" id="kategori" class="form-select">
            <option value="">— Pilih kategori —</option>
            <?php
            $curKat = $data['kategori'] ?? '';
            foreach (kategori_produk_map() as $slug => $lab) {
                ?>
                <option value="<?= htmlspecialchars($slug) ?>"<?= $curKat === $slug ? ' selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php } ?>
        </select>

        <input type="number" name="harga" value="<?= (int) $data['harga'] ?>" min="0" required>
        <input type="number" name="stok" value="<?= (int) $data['stok'] ?>" min="0" required>

        <label class="file-label" for="gambar">Ganti gambar <span class="optional">(opsional)</span></label>
        <input type="file" name="gambar" id="gambar" accept="image/jpeg,image/png,image/gif,image/webp">
        <p class="form-hint">Kosongkan jika tidak ingin mengganti gambar.</p>

        <button type="submit" name="submit">Update</button>
    </form>
    <a class="form-back" href="dashboard.php">← Kembali ke dashboard</a>
</div>

</body>
</html>
