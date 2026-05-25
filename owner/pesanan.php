<?php
session_start();
include '../koneksi.php';
require_once __DIR__ . '/../customer/pembayaran.php';
require_once __DIR__ . '/../lib/pesanan_transaksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header('Location: ../index.php');
    exit;
}

$u = $_SESSION['user'];
$namaUser = htmlspecialchars($u['username'] ?? $u['nama'] ?? $u['email'] ?? 'Owner');

$orders = [];
$orderStat = ['jumlah' => 0, 'nilai' => 0];
$tTx = @mysqli_query($conn, "SHOW TABLES LIKE 'transaksi'");
if ($tTx && mysqli_num_rows($tTx) > 0) {
    $os = mysqli_query($conn, 'SELECT COUNT(*) AS jumlah, COALESCE(SUM(total_harga), 0) AS nilai FROM transaksi');
    if ($os && ($or = mysqli_fetch_assoc($os))) {
        $orderStat['jumlah'] = (int) $or['jumlah'];
        $orderStat['nilai'] = (int) $or['nilai'];
    }
    $q = mysqli_query(
        $conn,
        'SELECT t.*, u.email AS customer_email
         FROM transaksi t
         LEFT JOIN users u ON t.user_id = u.id
         ORDER BY t.id DESC'
    );
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $orders[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan masuk</title>
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
            <li>
                <a href="dashboard.php">
                    <span class="nav-icon">▣</span> Dashboard
                </a>
            </li>
            <li>
                <a href="pesanan.php" class="nav-active" aria-current="page">
                    <span class="nav-icon">📦</span> Pesanan
                </a>
            </li>
            <li>
                <a href="tambah.php" class="nav-tambah">
                    <span class="nav-icon">＋</span> Tambah Produk
                </a>
            </li>
            <li>
                <a href="../logout.php" class="nav-logout">
                    <span class="nav-icon">⎋</span> Logout
                </a>
            </li>
        </ul>
    </aside>

    <main class="content">
        <header class="page-header">
            <h1>Pesanan Masuk</h1>
            <p>Selamat datang, <strong><?= $namaUser ?></strong> — daftar pesanan dari pelanggan.</p>
        </header>

        <section class="panel" aria-labelledby="daftar-pesanan">
            <div class="panel-head">
                <h2 id="daftar-pesanan">Semua pesanan</h2>
                <a href="dashboard.php" class="btn btn-ghost">← Dashboard produk</a>
            </div>

            <?php if ($orderStat['jumlah'] > 0) { ?>
                <div class="order-summary-bar" role="status">
                    <span><strong><?= number_format($orderStat['jumlah']) ?></strong> pesanan</span>
                    <span>Total nilai: <strong>Rp <?= number_format($orderStat['nilai']) ?></strong></span>
                </div>
            <?php } ?>

            <?php if (count($orders) === 0) { ?>
                <div class="empty-state">
                    <p>Belum ada pesanan dari pelanggan.</p>
                </div>
            <?php } else { ?>
                <div class="table-scroll">
                    <table class="order-owner-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Penerima</th>
                                <th>Total</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Aksi</th>
                                <th>Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $row) {
                                $oid = (int) $row['id'];
                                $st = (string) ($row['status_pembayaran'] ?? 'menunggu');
                                $met = (string) ($row['metode_pembayaran'] ?? '');
                                $tgl = $row['created_at'] ?? '';
                                $cust = trim((string) ($row['customer_email'] ?? ''));
                                if ($cust === '') {
                                    $cust = '—';
                                }
                                ?>
                                <tr>
                                    <td class="num"><?= $oid ?></td>
                                    <td><?= htmlspecialchars($tgl !== '' ? date('d/m/Y H:i', strtotime($tgl)) : '—') ?></td>
                                    <td><?= htmlspecialchars($cust) ?></td>
                                    <td><?= htmlspecialchars((string) ($row['nama_pembeli'] ?? '')) ?></td>
                                    <td class="price">Rp <?= number_format((int) $row['total_harga']) ?></td>
                                    <td><?= htmlspecialchars(metode_pembayaran_label($met)) ?></td>
                                    <td><span class="badge-status <?= htmlspecialchars(transaksi_status_class($st)) ?>"><?= htmlspecialchars(transaksi_status_label($st)) ?></span></td>
                                    <td>
                                        <a class="btn btn-edit btn-sm" href="pesanan_detail.php?id=<?= $oid ?>">Detail</a>
                                        <?php if ($met === 'transfer' && $st === 'menunggu') { ?>
                                            <a class="btn btn-success btn-sm" href="konfirmasi_pesanan.php?id=<?= $oid ?>&return=pesanan" onclick="return confirm('Konfirmasi pembayaran untuk pesanan #<?= $oid ?>?')">Konfirmasi</a>
                                        <?php } ?>
                                        <a class="btn btn-danger btn-sm" href="hapus_pesanan.php?id=<?= $oid ?>" onclick="return confirm('Hapus pesanan #<?= $oid ?>?')">Hapus</a>
                                    </td>
                                    <td><a class="btn btn-ghost btn-sm" href="invoice.php?id=<?= $oid ?>" target="_blank" rel="noopener">Cetak</a></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>
    </main>

</div>

</body>
</html>
