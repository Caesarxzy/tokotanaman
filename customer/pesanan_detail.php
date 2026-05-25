<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/pembayaran.php';
require_once __DIR__ . '/../lib/pesanan_transaksi.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: pesanan.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM transaksi WHERE id = ? AND user_id = ?');
if (!$stmt) {
    header('Location: pesanan.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'ii', $id, $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$tx = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$tx) {
    header('Location: pesanan.php');
    exit;
}

$items = [];
$qi = mysqli_prepare($conn, 'SELECT * FROM transaksi_item WHERE transaksi_id = ? ORDER BY id ASC');
if ($qi) {
    mysqli_stmt_bind_param($qi, 'i', $id);
    mysqli_stmt_execute($qi);
    $r2 = mysqli_stmt_get_result($qi);
    while ($it = mysqli_fetch_assoc($r2)) {
        $items[] = $it;
    }
    mysqli_stmt_close($qi);
}

$st = (string) ($tx['status_pembayaran'] ?? 'menunggu');
$met = (string) ($tx['metode_pembayaran'] ?? '');
$tgl = (string) ($tx['created_at'] ?? '');
$totalQty = 0;
foreach ($items as $it) {
    $totalQty += (int) ($it['jumlah'] ?? 0);
}
$jenisBarang = count($items);

$cartCount = 0;
$uid = (int) $userId;
$qc = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) AS j FROM cart WHERE user_id={$uid}");
if ($qc && ($rc = mysqli_fetch_assoc($qc))) {
    $cartCount = (int) $rc['j'];
}

$rek = metode_transfer_info();
$va = trim((string) ($rek['virtual_account'] ?? ''));
$noRek = trim((string) ($rek['nomor'] ?? ''));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail pesanan #<?= (int) $id ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <h2>Detail pesanan</h2>
    <nav class="navbar-actions">
        <a class="btn btn-nav-home" href="pesanan.php">← Riwayat Pesanan</a>
        <a class="btn btn-nav-home" href="dashboard.php">Katalog</a>
        <a class="btn btn-nav-cart" href="cart.php">🛒 Keranjang<?= $cartCount > 0 ? ' <strong>(' . $cartCount . ')</strong>' : '' ?></a>
        <a class="btn btn-nav-logout" href="../logout.php">Logout</a>
    </nav>
</header>

<main class="wrap stack checkout-stack order-detail-customer">
    <header class="order-detail-page-head">
        <h1 class="page-title">Pesanan #<?= (int) $id ?></h1>
        <p class="page-sub"><?= htmlspecialchars($tgl !== '' ? date('d F Y, H:i', strtotime($tgl)) : 'Waktu tidak tercatat') ?></p>
        <div class="order-detail-actions">
            <a class="btn btn-ghost btn-sm" href="pesanan.php">← Semua pesanan</a>
            <a class="btn btn-primary btn-sm" href="invoice.php?id=<?= (int) $id ?>" target="_blank" rel="noopener">Cetak invoice</a>
            <a class="btn btn-success btn-sm" href="bayar.php?id=<?= (int) $id ?>">Pembayaran</a>
        </div>
    </header>

    <div class="summary-box order-detail-section">
        <h2 class="order-detail-h2">Status dan ringkasan</h2>
        <p class="order-detail-badge-wrap"><span class="badge-status <?= htmlspecialchars(transaksi_status_class($st)) ?>"><?= htmlspecialchars(transaksi_status_label($st)) ?></span></p>
        <dl class="order-detail-dl">
            <div><dt>Metode pembayaran</dt><dd><?= htmlspecialchars(metode_pembayaran_label($met)) ?></dd></div>
            <div><dt>Total tagihan</dt><dd><strong>Rp <?= number_format((int) ($tx['total_harga'] ?? 0)) ?></strong></dd></div>
            <div><dt>Referensi</dt><dd class="mono">TRX-<?= (int) $id ?></dd></div>
            <?php if ($jenisBarang > 0) { ?>
                <div><dt>Barang</dt><dd><?= (int) $jenisBarang ?> jenis · <?= (int) $totalQty ?> item</dd></div>
            <?php } ?>
        </dl>
    </div>

    <div class="summary-box order-detail-section">
        <h2 class="order-detail-h2">Pengiriman</h2>
        <div class="pengiriman-readonly">
            <p><strong><?= htmlspecialchars((string) ($tx['nama_pembeli'] ?? '')) ?></strong></p>
            <p><?= htmlspecialchars((string) ($tx['email_pembeli'] ?? '')) ?> · <?= htmlspecialchars((string) ($tx['telepon_pembeli'] ?? '')) ?></p>
            <p><?= nl2br(htmlspecialchars((string) ($tx['alamat_pengiriman'] ?? ''))) ?></p>
            <p><?= htmlspecialchars((string) ($tx['kota_pengiriman'] ?? '')) ?><?= trim((string) ($tx['kode_pos'] ?? '')) !== '' ? ' ' . htmlspecialchars((string) $tx['kode_pos']) : '' ?></p>
            <?php if (trim((string) ($tx['catatan_pengiriman'] ?? '')) !== '') { ?>
                <p class="bayar-hint"><strong>Catatan:</strong> <?= htmlspecialchars((string) $tx['catatan_pengiriman']) ?></p>
            <?php } ?>
        </div>
    </div>

    <?php if ($met === METODE_TRANSFER) { ?>
        <div class="summary-box order-detail-section">
            <h2 class="order-detail-h2">Instruksi transfer</h2>
            <p class="bayar-lead">Transfer tepat <strong>Rp <?= number_format((int) ($tx['total_harga'] ?? 0)) ?></strong> ke salah satu rekening di bawah. Cantumkan <strong>TRX-<?= (int) $id ?></strong> di berita transfer.</p>
            <?php if ($va !== '') { ?>
                <h3 class="order-detail-h3">Virtual account</h3>
                <div class="rekening-box">
                    <?php if (!empty($rek['bank_va'])) { ?>
                        <div class="rekening-row"><span>Bank</span><strong><?= htmlspecialchars((string) $rek['bank_va']) ?></strong></div>
                    <?php } ?>
                    <div class="rekening-row"><span>No. VA</span><strong class="mono"><?= htmlspecialchars($va) ?></strong></div>
                    <?php if (!empty($rek['atas_nama_va'])) { ?>
                        <div class="rekening-row"><span>Atas nama</span><strong><?= htmlspecialchars((string) $rek['atas_nama_va']) ?></strong></div>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($noRek !== '') { ?>
                <h3 class="order-detail-h3"><?= $va !== '' ? 'Atau rekening' : 'Rekening bank' ?></h3>
                <div class="rekening-box">
                    <div class="rekening-row"><span>Bank</span><strong><?= htmlspecialchars((string) ($rek['bank'] ?? '')) ?></strong></div>
                    <div class="rekening-row"><span>No. rekening</span><strong class="mono"><?= htmlspecialchars($noRek) ?></strong></div>
                    <div class="rekening-row"><span>Atas nama</span><strong><?= htmlspecialchars((string) ($rek['atas_nama'] ?? '')) ?></strong></div>
                </div>
            <?php } ?>
            <?php if ($va === '' && $noRek === '') { ?>
                <p class="alert alert-error">Nomor rekening belum diatur. Hubungi toko.</p>
            <?php } else { ?>
                <p class="bayar-hint"><?= htmlspecialchars((string) ($rek['catatan'] ?? '')) ?></p>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="summary-box order-detail-section">
        <h2 class="order-detail-h2">Rincian barang</h2>
        <?php if (count($items) === 0) { ?>
            <p class="order-items-empty">Detail per baris tidak tersimpan untuk pesanan ini (data lama).</p>
        <?php } else { ?>
            <div class="table-wrap">
                <table class="order-items-table order-items-table-detail">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="num">ID</th>
                            <th class="num">Harga</th>
                            <th class="num">Qty</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it) { ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($it['nama_produk'] ?? '')) ?></td>
                                <td class="num mono"><?= (int) ($it['produk_id'] ?? 0) ?></td>
                                <td class="num">Rp <?= number_format((int) ($it['harga_satuan'] ?? 0)) ?></td>
                                <td class="num"><?= (int) ($it['jumlah'] ?? 0) ?></td>
                                <td class="num">Rp <?= number_format((int) ($it['subtotal'] ?? 0)) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="num">Total</th>
                            <th class="num">Rp <?= number_format((int) ($tx['total_harga'] ?? 0)) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php } ?>
    </div>
</main>

</body>
</html>
