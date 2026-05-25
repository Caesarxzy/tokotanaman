<?php
require_once __DIR__ . '/_init.php';

if (isset($_POST['tambah'])) {
    $produk_id = (int) ($_POST['produk_id'] ?? 0);
    $jumlah = max(1, (int) ($_POST['jumlah'] ?? 1));

    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, stok FROM produk WHERE id=$produk_id"));
    if ($p) {
        $stok = (int) $p['stok'];
        $sumQ = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) AS s FROM cart WHERE user_id=$userId AND produk_id=$produk_id");
        $inCart = (int) (mysqli_fetch_assoc($sumQ)['s'] ?? 0);
        if ($inCart + $jumlah <= $stok) {
            mysqli_query($conn, "DELETE FROM cart WHERE user_id=$userId AND produk_id=$produk_id");
            $newJumlah = $inCart + $jumlah;
            mysqli_query($conn, "INSERT INTO cart (user_id, produk_id, jumlah) VALUES ($userId, $produk_id, $newJumlah)");
            header('Location: dashboard.php?msg=ditambah');
            exit;
        }
        header('Location: dashboard.php?err=stok');
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['hapus'])) {
    $pid = (int) ($_POST['produk_id'] ?? 0);
    if ($pid > 0) {
        mysqli_query($conn, "DELETE FROM cart WHERE user_id=$userId AND produk_id=$pid");
    }
    header('Location: cart.php');
    exit;
}

if (isset($_POST['update_jumlah'])) {
    $pid = (int) ($_POST['produk_id'] ?? 0);
    $jumlah = max(1, (int) ($_POST['jumlah'] ?? 1));
    if ($pid > 0) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stok FROM produk WHERE id=$pid"));
        if ($p) {
            $stok = (int) $p['stok'];
            $jumlah = min($jumlah, $stok);
            mysqli_query($conn, "DELETE FROM cart WHERE user_id=$userId AND produk_id=$pid");
            mysqli_query($conn, "INSERT INTO cart (user_id, produk_id, jumlah) VALUES ($userId, $pid, $jumlah)");
        }
    }
    header('Location: cart.php');
    exit;
}

$data = mysqli_query($conn, "
    SELECT c.produk_id, SUM(c.jumlah) AS jumlah, p.nama_produk, p.harga, p.stok
    FROM cart c
    JOIN produk p ON c.produk_id = p.id
    WHERE c.user_id=$userId
    GROUP BY c.produk_id, p.nama_produk, p.harga, p.stok
");

$total = 0;
$rows = [];
if ($data) {
    while ($r = mysqli_fetch_assoc($data)) {
        $rows[] = $r;
        $total += (int) $r['harga'] * (int) $r['jumlah'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <h2>🛒 Keranjang</h2>
    <nav class="navbar-actions">
        <a class="btn btn-nav-home" href="dashboard.php">← Katalog</a>
        <a class="btn btn-nav-home" href="pesanan.php">📦 Riwayat</a>
        <a class="btn btn-nav-logout" href="../logout.php">Keluar</a>
    </nav>
</header>

<main class="wrap">
    <h1 class="page-title">Isi keranjang</h1>
    <p class="page-sub">Periksa jumlah lalu lanjut ke checkout.</p>

    <?php if (count($rows) === 0) { ?>
        <div class="panel">
            <div class="empty-state">
                <p>Keranjang masih kosong.</p>
                <a class="btn btn-primary" href="dashboard.php">Mulai belanja</a>
            </div>
        </div>
    <?php } else { ?>
        <div class="panel">
            <div class="panel-head">
                <h2>Item</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga satuan</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) {
                        $sub = (int) $row['harga'] * (int) $row['jumlah'];
                        $pid = (int) $row['produk_id'];
                        $maxStok = (int) $row['stok'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                            <td class="price">Rp <?= number_format((int) $row['harga']) ?></td>
                            <td>
                                <form class="inline-form" method="POST">
                                    <input type="hidden" name="produk_id" value="<?= $pid ?>">
                                    <input type="number" name="jumlah" value="<?= (int) $row['jumlah'] ?>" min="1" max="<?= $maxStok ?>" required>
                                    <button type="submit" name="update_jumlah" class="btn btn-ghost btn-sm">Ubah</button>
                                </form>
                            </td>
                            <td class="price">Rp <?= number_format($sub) ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus produk ini dari keranjang?');">
                                    <input type="hidden" name="produk_id" value="<?= $pid ?>">
                                    <button type="submit" name="hapus" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <div class="cart-footer">
                <div>
                    <div class="total-label">Total</div>
                    <div class="total-value">Rp <?= number_format($total) ?></div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <a class="btn btn-ghost" href="dashboard.php">Lanjut belanja</a>
                    <a class="btn btn-primary" href="checkout_alamat.php">Checkout</a>
                </div>
            </div>
        </div>
    <?php } ?>
</main>

</body>
</html>
