<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../lib/checkout_pengiriman.php';

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
    header('Location: cart.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_alamat'])) {
    $ok = checkout_pengiriman_validate_post($errors);
    if ($ok !== null) {
        $_SESSION[SESSION_CHECKOUT_PENGIRIMAN] = $ok;
        header('Location: checkout.php');
        exit;
    }
}

$u = $_SESSION['user'];
$saved = checkout_pengiriman_get();
$val = function ($key, $fallback = '') use ($saved, $u) {
    if ($saved !== null && array_key_exists($key, $saved)) {
        return $saved[$key];
    }
    if ($key === 'email') {
        return $u['email'] ?? $fallback;
    }
    if ($key === 'nama_lengkap') {
        return trim((string) ($u['nama'] ?? $u['username'] ?? $fallback));
    }
    return $fallback;
};
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data pembeli &amp; alamat</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <h2>📦 Pengiriman</h2>
    <nav class="navbar-actions">
        <a class="btn btn-nav-home" href="cart.php">← Keranjang</a>
        <a class="btn btn-nav-logout" href="../logout.php">Keluar</a>
    </nav>
</header>

<main class="wrap stack checkout-stack">
    <h1 class="page-title">Data diri &amp; alamat pengiriman</h1>
    <p class="page-sub">Lengkapi form berikut sebelum memilih metode pembayaran. Total belanja: <strong>Rp <?= number_format($total) ?></strong></p>

    <?php if ($errors !== []) { ?>
        <div class="alert alert-error" role="alert">
            <ul class="error-list">
                <?php foreach ($errors as $er) { ?>
                    <li><?= htmlspecialchars($er) ?></li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>

    <div class="summary-box">
        <h2>Ringkas item</h2>
        <?php foreach ($rows as $row) {
            $sub = (int) $row['harga'] * (int) $row['jumlah'];
            ?>
            <div class="summary-row">
                <span><?= htmlspecialchars($row['nama_produk']) ?> × <?= (int) $row['jumlah'] ?></span>
                <span>Rp <?= number_format($sub) ?></span>
            </div>
        <?php } ?>
    </div>

    <form method="POST" class="checkout-form summary-box">
        <h2>Data pembeli</h2>

        <label class="form-field-label" for="nama_lengkap">Nama lengkap</label>
        <input class="form-field-input" type="text" id="nama_lengkap" name="nama_lengkap" required maxlength="128"
               value="<?= htmlspecialchars($val('nama_lengkap')) ?>" autocomplete="name">

        <label class="form-field-label" for="email">Email</label>
        <input class="form-field-input" type="email" id="email" name="email" required maxlength="128"
               value="<?= htmlspecialchars($val('email')) ?>" autocomplete="email">

        <label class="form-field-label" for="telepon">Nomor HP / WhatsApp</label>
        <input class="form-field-input" type="tel" id="telepon" name="telepon" required inputmode="numeric" maxlength="20"
               value="<?= htmlspecialchars($val('telepon')) ?>" autocomplete="tel" placeholder="08xxxxxxxxxx">

        <h2 class="form-section-title">Alamat pengiriman</h2>

        <label class="form-field-label" for="alamat">Alamat lengkap</label>
        <textarea class="form-field-textarea" id="alamat" name="alamat" required maxlength="500" rows="4" placeholder="Jalan, nomor rumah, RT/RW, patokan"><?= htmlspecialchars($val('alamat')) ?></textarea>

        <label class="form-field-label" for="kota">Kota / kabupaten</label>
        <input class="form-field-input" type="text" id="kota" name="kota" required maxlength="100"
               value="<?= htmlspecialchars($val('kota')) ?>" autocomplete="address-level2">

        <label class="form-field-label" for="kode_pos">Kode pos <span class="optional-label">(opsional)</span></label>
        <input class="form-field-input" type="text" id="kode_pos" name="kode_pos" maxlength="16"
               value="<?= htmlspecialchars($val('kode_pos')) ?>" autocomplete="postal-code">

        <label class="form-field-label" for="catatan">Catatan untuk kurir <span class="optional-label">(opsional)</span></label>
        <textarea class="form-field-textarea" id="catatan" name="catatan" maxlength="255" rows="2" placeholder="Contoh: rumah pagar hijau"><?= htmlspecialchars($val('catatan')) ?></textarea>

        <button type="submit" name="simpan_alamat" class="btn btn-success btn-block" value="1">Lanjut ke pembayaran</button>
        <a class="btn btn-ghost btn-block" href="cart.php">Kembali ke keranjang</a>
    </form>
</main>

</body>
</html>
