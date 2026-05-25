<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/pembayaran.php';
require_once __DIR__ . '/../lib/checkout_pengiriman.php';

if (!checkout_pengiriman_is_complete()) {
    header('Location: checkout_alamat.php');
    exit;
}

$peng = checkout_pengiriman_get();

$data = mysqli_query($conn, "
    SELECT c.produk_id, SUM(c.jumlah) AS jumlah, p.nama_produk, p.harga
    FROM cart c
    JOIN produk p ON c.produk_id = p.id
    WHERE c.user_id=$userId
    GROUP BY c.produk_id, p.nama_produk, p.harga
");

$rows = [];
$total = 0;
if ($data) {
    while ($r = mysqli_fetch_assoc($data)) {
        $rows[] = $r;
        $total += (int) $r['harga'] * (int) $r['jumlah'];
    }
}

if (count($rows) === 0) {
    checkout_pengiriman_clear();
    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['konfirmasi'])) {
    if ($total < 1) {
        header('Location: cart.php');
        exit;
    }
    if (!checkout_pengiriman_is_complete()) {
        header('Location: checkout_alamat.php');
        exit;
    }
    $p = checkout_pengiriman_get();
    $metode = $_POST['metode'] ?? METODE_SNAP;
    if (!in_array($metode, metode_pembayaran_allowed(), true)) {
        $metode = METODE_SNAP;
    }

    $nama = $p['nama_lengkap'];
    $email = $p['email'];
    $telepon = $p['telepon'];
    $alamat = $p['alamat'];
    $kota = $p['kota'];
    $kode_pos = $p['kode_pos'] ?? '';
    $catatan = $p['catatan'] ?? '';

    $stmt = mysqli_prepare($conn, 'INSERT INTO transaksi (user_id, total_harga, metode_pembayaran, nama_pembeli, email_pembeli, telepon_pembeli, alamat_pengiriman, kota_pengiriman, kode_pos, catatan_pengiriman) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            'iissssssss',
            $userId,
            $total,
            $metode,
            $nama,
            $email,
            $telepon,
            $alamat,
            $kota,
            $kode_pos,
            $catatan
        );
        if (mysqli_stmt_execute($stmt)) {
            $id_transaksi = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $itemStmt = mysqli_prepare(
                $conn,
                'INSERT INTO transaksi_item (transaksi_id, produk_id, nama_produk, harga_satuan, jumlah, subtotal) VALUES (?,?,?,?,?,?)'
            );
            if ($itemStmt) {
                foreach ($rows as $r) {
                    $pid = (int) $r['produk_id'];
                    $nama = (string) $r['nama_produk'];
                    $h = (int) $r['harga'];
                    $j = (int) $r['jumlah'];
                    $sub = $h * $j;
                    mysqli_stmt_bind_param($itemStmt, 'iisiii', $id_transaksi, $pid, $nama, $h, $j, $sub);
                    mysqli_stmt_execute($itemStmt);
                }
                mysqli_stmt_close($itemStmt);
            }

            $_SESSION['last_transaksi_id'] = (int) $id_transaksi;
            mysqli_query($conn, "DELETE FROM cart WHERE user_id=$userId");
            checkout_pengiriman_clear();
            header('Location: bayar.php?id=' . (int) $id_transaksi);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: checkout.php?err=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <h2>📋 Checkout</h2>
    <nav class="navbar-actions">
        <a class="btn btn-nav-home" href="checkout_alamat.php">← Ubah alamat</a>
        <a class="btn btn-nav-logout" href="../logout.php">Keluar</a>
    </nav>
</header>

<main class="wrap stack checkout-stack">
    <h1 class="page-title">Ringkasan pesanan</h1>
    <p class="page-sub">Periksa data pengiriman, lalu pilih metode pembayaran.</p>

    <?php if (isset($_GET['err'])) { ?>
        <div class="alert alert-error">Gagal membuat transaksi. Coba lagi.</div>
    <?php } ?>

    <div class="summary-box">
        <h2>Pengiriman ke</h2>
        <div class="pengiriman-readonly">
            <p><strong><?= htmlspecialchars($peng['nama_lengkap']) ?></strong></p>
            <p><?= htmlspecialchars($peng['email']) ?> · <?= htmlspecialchars($peng['telepon']) ?></p>
            <p><?= nl2br(htmlspecialchars($peng['alamat'])) ?></p>
            <p><?= htmlspecialchars($peng['kota']) ?><?= ($peng['kode_pos'] ?? '') !== '' ? ' ' . htmlspecialchars($peng['kode_pos']) : '' ?></p>
            <?php if (trim((string) ($peng['catatan'] ?? '')) !== '') { ?>
                <p class="bayar-hint"><strong>Catatan:</strong> <?= htmlspecialchars($peng['catatan']) ?></p>
            <?php } ?>
        </div>
        <p style="margin-top:12px;"><a class="btn btn-ghost btn-sm" href="checkout_alamat.php">Ubah data pengiriman</a></p>
    </div>

    <div class="summary-box">
        <h2>Detail barang</h2>
        <?php foreach ($rows as $row) {
            $sub = (int) $row['harga'] * (int) $row['jumlah'];
            ?>
            <div class="summary-row">
                <span><?= htmlspecialchars($row['nama_produk']) ?> × <?= (int) $row['jumlah'] ?></span>
                <span>Rp <?= number_format($sub) ?></span>
            </div>
        <?php } ?>
        <div class="summary-total">
            <span>Total bayar</span>
            <span>Rp <?= number_format($total) ?></span>
        </div>
    </div>

    <div class="summary-box metode-box">
        <h2>Metode pembayaran</h2>
        <p class="metode-intro">Pilih salah satu. Anda dapat menyelesaikan langkah berikutnya di halaman pembayaran.</p>
        <form method="POST" class="pay-actions" id="form-checkout">
            <fieldset class="metode-list">
                <legend class="visually-hidden">Pilih metode</legend>
                <label class="metode-card">
                    <input type="radio" name="metode" value="<?= METODE_TRANSFER ?>">
                    <span class="metode-card-body">
                        <span class="metode-title">Transfer bank</span>
                        <span class="metode-desc">Transfer ke rekening atau virtual account toko (manual).</span>
                    </span>
                </label>
                <label class="metode-card">
                    <input type="radio" name="metode" value="<?= METODE_DUMMY ?>">
                    <span class="metode-card-body">
                        <span class="metode-title">Midtrans</span>
                        <span class="metode-desc">QRIS dan virtual account bank (BCA, BNI, Permata, BRI, Mandiri).</span>
                    </span>
                </label>
            </fieldset>

            <button type="submit" name="konfirmasi" class="btn btn-success btn-block" value="1">Konfirmasi &amp; lanjut bayar</button>
            <a class="btn btn-ghost btn-block" href="cart.php" style="text-align:center;">Kembali ke keranjang</a>
        </form>
    </div>
</main>

</body>
</html>
