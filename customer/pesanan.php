<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/pembayaran.php';
require_once __DIR__ . '/../lib/pesanan_transaksi.php';

$uid = (int) $userId;
$orders = [];
$q = mysqli_query(
    $conn,
    "SELECT id, user_id, total_harga, metode_pembayaran, status_pembayaran, nama_pembeli, created_at 
     FROM transaksi WHERE user_id={$uid} ORDER BY id DESC"
);
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $orders[] = $row;
    }
}

$itemsByTx = [];
if (count($orders) > 0) {
    $ids = array_map(static function ($o) {
        return (int) $o['id'];
    }, $orders);
    $in = implode(',', $ids);
    $qi = mysqli_query($conn, "SELECT * FROM transaksi_item WHERE transaksi_id IN ({$in}) ORDER BY transaksi_id DESC, id ASC");
    if ($qi) {
        while ($it = mysqli_fetch_assoc($qi)) {
            $tid = (int) $it['transaksi_id'];
            $itemsByTx[$tid][] = $it;
        }
    }
}

$cartCount = 0;
$qc = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) AS j FROM cart WHERE user_id={$uid}");
if ($qc && ($rc = mysqli_fetch_assoc($qc))) {
    $cartCount = (int) $rc['j'];
}

$totalJumlahPesanan = count($orders);
$totalNilaiPesanan = 0;
foreach ($orders as $o) {
    $totalNilaiPesanan += (int) ($o['total_harga'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat pesanan</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <h2>Riwayat pesanan</h2>
    <nav class="navbar-actions">
        <a class="btn btn-nav-home" href="dashboard.php">← Katalog</a>
        <a class="btn btn-nav-cart" href="cart.php">🛒 Keranjang<?= $cartCount > 0 ? ' <strong>(' . $cartCount . ')</strong>' : '' ?></a>
        <a class="btn btn-nav-logout" href="../logout.php">Logout</a>
    </nav>
</header>

<main class="wrap stack checkout-stack">
    <h1 class="page-title">Riwayat pemesanan</h1>
    <p class="page-sub">Semua pesanan yang pernah Anda buat di toko ini.</p>

    <?php if ($totalJumlahPesanan > 0) { ?>
        <div class="panel customer-order-totals" role="status">
            <p class="customer-order-totals-line"><strong><?= number_format($totalJumlahPesanan) ?></strong> pesanan &middot; Total belanja <strong>Rp <?= number_format($totalNilaiPesanan) ?></strong></p>
        </div>
    <?php } ?>

    <?php if (count($orders) === 0) { ?>
        <div class="panel">
            <div class="empty-state">
                <p>Belum ada pesanan. Mulai belanja dari katalog.</p>
                <a class="btn btn-primary" href="dashboard.php">Ke katalog</a>
            </div>
        </div>
    <?php } else { ?>
        <?php
function cekExpired($created_at, $status) {
    if ($status !== 'menunggu') return $status;

    $waktuOrder = strtotime($created_at);
    $sekarang = time();

    // 10 menit = 600 detik
    if (($sekarang - $waktuOrder) > 600) {
        return 'expired';
    }

    return 'menunggu';
}
?>
        <?php foreach ($orders as $o) {
            $oid = (int) $o['id'];
            $st = cekExpired($o['created_at'], $o['status_pembayaran']);
            if ($st === 'expired' && $o['status_pembayaran'] === 'menunggu') {
                mysqli_query($conn, "UPDATE transaksi 
                SET status_pembayaran='expired' 
                WHERE id={$oid}");
            }
            $met = (string) ($o['metode_pembayaran'] ?? '');
            $items = $itemsByTx[$oid] ?? [];
            $tgl = $o['created_at'] ?? '';
            ?>
            <?php
if ($st === 'menunggu') {
    $expiredTime = strtotime($o['created_at']) + 600;
?>
<p class="countdown" data-time="<?= $expiredTime ?>"></p>
<?php } 
?>
            <article class="summary-box order-card" id="order-<?= $oid ?>">
                <div class="order-card-head">
                    <div>
                        <h2 class="order-card-title">Pesanan #<?= $oid ?></h2>
                        <p class="order-card-date"><?= htmlspecialchars($tgl !== '' ? date('d F Y, H:i', strtotime($tgl)) : 'Tanggal tidak tercatat') ?></p>
                    </div>
                    <div class="order-card-tags">
                        <span class="badge-status <?= htmlspecialchars(transaksi_status_class($st)) ?>"><?= htmlspecialchars(transaksi_status_label($st)) ?></span>
                    </div>
                </div>
                <p class="order-card-row"><span>Total</span><strong>Rp <?= number_format((int) $o['total_harga']) ?></strong></p>
                <p class="order-card-row"><span>Metode</span><span><?= htmlspecialchars(metode_pembayaran_label($met)) ?></span></p>
                <p class="order-card-row"><span>Penerima</span><span><?= htmlspecialchars((string) ($o['nama_pembeli'] ?? '')) ?></span></p>

                <h3 class="order-items-title">Barang dipesan</h3>
                <?php if (count($items) === 0) { ?>
                    <p class="order-items-empty">Detail barang tidak tersimpan (pesanan lama).</p>
                <?php } else { ?>
                    <div class="table-wrap">
                        <table class="order-items-table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="num">Harga</th>
                                    <th class="num">Qty</th>
                                    <th class="num">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $it) { ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $it['nama_produk']) ?></td>
                                        <td class="num">Rp <?= number_format((int) $it['harga_satuan']) ?></td>
                                        <td class="num"><?= (int) $it['jumlah'] ?></td>
                                        <td class="num">Rp <?= number_format((int) $it['subtotal']) ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
                <p class="order-card-foot order-card-actions">
                    <a class="btn btn-primary btn-sm" href="pesanan_detail.php?id=<?= $oid ?>">Detail pesanan</a>
                    <a class="btn btn-ghost btn-sm" href="bayar.php?id=<?= $oid ?>">Pembayaran</a>
                    <a class="btn btn-ghost btn-sm" href="invoice.php?id=<?= $oid ?>" target="_blank" rel="noopener">Invoice</a>
                </p>
            </article>
        <?php } ?>
    <?php } ?>
</main>
<script>
document.querySelectorAll('.countdown').forEach(el => {
    let end = parseInt(el.dataset.time) * 1000;

    function update() {
        let now = new Date().getTime();
        let diff = end - now;

        if (diff <= 0) {
            el.innerHTML = "⛔ Waktu habis";
            location.reload();
            return;
        }

        let m = Math.floor(diff / 600000);
        let s = Math.floor((diff % 600000) / 1000);

        el.innerHTML = "Bayar dalam: " + m + "m " + s + "s";
    }

    setInterval(update, 1000);
    update();
});
</script>

</body>
</html>
