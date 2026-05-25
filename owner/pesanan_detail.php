<?php
session_start();
include '../koneksi.php';
require_once __DIR__ . '/../customer/pembayaran.php';
require_once __DIR__ . '/../lib/pesanan_transaksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header('Location: ../index.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: pesanan.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT t.*, u.email AS customer_email
     FROM transaksi t
     LEFT JOIN users u ON t.user_id = u.id
     WHERE t.id = ?'
);
if (!$stmt) {
    header('Location: pesanan.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $id);
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
$tgl = $tx['created_at'] ?? '';
$cust = trim((string) ($tx['customer_email'] ?? ''));
if ($cust === '') {
    $cust = '—';
}

$totalQty = 0;
foreach ($items as $it) {
    $totalQty += (int) ($it['jumlah'] ?? 0);
}
$jenisBarang = count($items);

$rek = metode_transfer_info();
$va = trim((string) ($rek['virtual_account'] ?? ''));
$noRek = trim((string) ($rek['nomor'] ?? ''));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan #<?= (int) $id ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <span aria-hidden="true">🌿</span>
            <h2>Panel Owner</h2>
        </div>
        <div class="nav-label">Menu</div>
        <ul>
            <li><a href="dashboard.php"><span class="nav-icon">▣</span> Dashboard</a></li>
            <li><a href="pesanan.php" class="nav-active" aria-current="page"><span class="nav-icon">📦</span> Pesanan</a></li>
            <li><a href="tambah.php" class="nav-tambah"><span class="nav-icon">＋</span> Tambah Produk</a></li>
            <li><a href="../logout.php" class="nav-logout"><span class="nav-icon">⎋</span> Logout</a></li>
        </ul>
    </aside>

    <main class="content">
        <header class="page-header">
            <h1>Pesanan #<?= (int) $id ?></h1>
            <p class="page-header-actions">
                <a href="pesanan.php" class="link-back">← Kembali ke daftar pesanan</a>
                <a class="btn btn-primary btn-sm" href="invoice.php?id=<?= (int) $id ?>" target="_blank" rel="noopener">Cetak invoice</a>
            </p>
        </header>

        <section class="panel order-detail-panel">
            <div class="order-detail-grid">
                <div>
                    <h2 class="subhead">Status dan pembayaran</h2>
                    <p><span class="badge-status <?= htmlspecialchars(transaksi_status_class($st)) ?>"><?= htmlspecialchars(transaksi_status_label($st)) ?></span></p>
                    <p class="detail-line"><strong>Metode:</strong> <?= htmlspecialchars(metode_pembayaran_label($met)) ?></p>
                    <p class="detail-line"><strong>Total:</strong> Rp <?= number_format((int) $tx['total_harga']) ?></p>
                    <p class="detail-line"><strong>Waktu:</strong> <?= htmlspecialchars($tgl !== '' ? date('d F Y, H:i', strtotime($tgl)) : '—') ?></p>
                    <p class="detail-line"><strong>Referensi:</strong> <span class="mono">TRX-<?= (int) $id ?></span></p>
                    <?php if ($jenisBarang > 0) { ?>
                        <p class="detail-line"><strong>Ringkasan barang:</strong> <?= (int) $jenisBarang ?> jenis · <?= (int) $totalQty ?> item</p>
                    <?php } ?>
                </div>
                <div>
                    <h2 class="subhead">Akun pemesan</h2>
                    <p class="detail-line"><?= htmlspecialchars($cust) ?></p>
                </div>
            </div>

            <h2 class="subhead">Pengiriman</h2>
            <div class="pengiriman-box">
                <p><strong><?= htmlspecialchars((string) ($tx['nama_pembeli'] ?? '')) ?></strong></p>
                <p><?= htmlspecialchars((string) ($tx['email_pembeli'] ?? '')) ?> · <?= htmlspecialchars((string) ($tx['telepon_pembeli'] ?? '')) ?></p>
                <p><?= nl2br(htmlspecialchars((string) ($tx['alamat_pengiriman'] ?? ''))) ?></p>
                <p><?= htmlspecialchars((string) ($tx['kota_pengiriman'] ?? '')) ?><?= trim((string) ($tx['kode_pos'] ?? '')) !== '' ? ' ' . htmlspecialchars((string) $tx['kode_pos']) : '' ?></p>
                <?php if (trim((string) ($tx['catatan_pengiriman'] ?? '')) !== '') { ?>
                    <p><strong>Catatan:</strong> <?= htmlspecialchars((string) $tx['catatan_pengiriman']) ?></p>
                <?php } ?>
            </div>

            <?php if ($met === METODE_TRANSFER) { ?>
                <h2 class="subhead">Rekening tujuan transfer</h2>
                <p class="detail-line">Pelanggan harus mentransfer <strong>Rp <?= number_format((int) $tx['total_harga']) ?></strong> dengan referensi <span class="mono">TRX-<?= (int) $id ?></span>.</p>
                <?php if ($va !== '') { ?>
                    <div class="pengiriman-box" style="margin-bottom:12px;">
                        <p><strong>Virtual account</strong></p>
                        <?php if (!empty($rek['bank_va'])) { ?>
                            <p class="detail-line">Bank: <?= htmlspecialchars((string) $rek['bank_va']) ?></p>
                        <?php } ?>
                        <p class="detail-line">No. VA: <span class="mono"><?= htmlspecialchars($va) ?></span></p>
                    </div>
                <?php } ?>
                <?php if ($noRek !== '') { ?>
                    <div class="pengiriman-box">
                        <p><strong>Rekening bank</strong></p>
                        <p class="detail-line"><?= htmlspecialchars((string) ($rek['bank'] ?? '')) ?> — <span class="mono"><?= htmlspecialchars($noRek) ?></span> a.n. <?= htmlspecialchars((string) ($rek['atas_nama'] ?? '')) ?></p>
                    </div>
                <?php } ?>
            <?php } ?>

            <h2 class="subhead">Item pesanan</h2>
            <?php if (count($items) === 0) { ?>
                <p class="muted">Tidak ada baris detail (pesanan dibuat sebelum pencatatan item).</p>
            <?php } else { ?>
                <div class="table-scroll">
                    <table class="order-owner-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="num">ID produk</th>
                                <th class="num">Harga</th>
                                <th class="num">Qty</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it) { ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $it['nama_produk']) ?></td>
                                    <td class="num mono"><?= (int) ($it['produk_id'] ?? 0) ?></td>
                                    <td class="num">Rp <?= number_format((int) $it['harga_satuan']) ?></td>
                                    <td class="num"><?= (int) $it['jumlah'] ?></td>
                                    <td class="num">Rp <?= number_format((int) $it['subtotal']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="num">Total</th>
                                <th class="num">Rp <?= number_format((int) $tx['total_harga']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php } ?>
        </section>
    </main>

</div>

</body>
</html>
