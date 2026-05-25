<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$u = $_SESSION['user'];
$namaUser = htmlspecialchars($u['username'] ?? $u['nama'] ?? $u['email'] ?? 'Owner');

$data = mysqli_query($conn, "SELECT * FROM produk");
$orderStat = ['jumlah' => 0];

$txData = mysqli_query($conn, "SELECT COUNT(*) AS jumlah FROM transaksi");
if ($txData && ($txRow = mysqli_fetch_assoc($txData))) {
    $orderStat['jumlah'] = (int) $txRow['jumlah'];
}

$pendingTransfers = [];
$pendingTransferQ = mysqli_query(
    $conn,
    "SELECT t.id, t.total_harga, t.created_at, u.email AS customer_email, t.nama_pembeli
     FROM transaksi t
     LEFT JOIN users u ON t.user_id = u.id
     WHERE t.metode_pembayaran='transfer' AND t.status_pembayaran='menunggu_konfirmasi'
     ORDER BY t.created_at DESC LIMIT 6"
);
if ($pendingTransferQ) {
    while ($row = mysqli_fetch_assoc($pendingTransferQ)) {
        $pendingTransfers[] = $row;
    }
}

// Statistik
$produkStat = ['jumlah' => 0, 'nilai' => 0];
if ($data) {
    mysqli_data_seek($data, 0); // Reset pointer
    while ($row = mysqli_fetch_assoc($data)) {
        $produkStat['jumlah']++;
        $produkStat['nilai'] += (int) $row['harga'] * (int) $row['stok'];
    }
    mysqli_data_seek($data, 0); // Reset again for loop
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Owner</title>
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
                <a href="dashboard.php" class="nav-active" aria-current="page">
                    <span class="nav-icon">▣</span> Dashboard
                </a>
            </li>
            <li>
                <a href="pesanan.php">
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
            <h1>Dashboard Produk</h1>
            <p>Selamat datang, <strong><?= $namaUser ?></strong> — kelola inventori toko Anda.</p>
        </header>

        <section class="panel" aria-labelledby="statistik-produk">
            <div class="panel-head">
                <h2 id="statistik-produk">Statistik Produk</h2>
            </div>
            <div class="stats">
                <div class="stat-card">
                    <h3>Total Produk</h3>
                    <div class="value"><?= number_format($produkStat['jumlah']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Pesanan</h3>
                    <div class="value"><?= number_format($orderStat['jumlah']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Nilai Inventori</h3>
                    <div class="value value-compact">Rp <?= number_format($produkStat['nilai']) ?></div>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="konfirmasi-transfer">
            <div class="panel-head">
                <h2 id="konfirmasi-transfer">Konfirmasi Transfer</h2>
                <?php if (count($pendingTransfers) === 0) { ?>
                    <span class="badge-status status-wait">Tidak ada</span>
                <?php } ?>
            </div>
            <?php if (count($pendingTransfers) === 0) { ?>
                <div class="empty-state">
                    <p>Tidak ada pembayaran transfer yang menunggu verifikasi.</p>
                </div>
            <?php } else { ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>Nama penerima</th>
                                <th>Total</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingTransfers as $pt) {
                                $pid = (int) $pt['id'];
                                $cust = trim((string) ($pt['customer_email'] ?? '')) ?: '—';
                                $namaPenerima = htmlspecialchars((string) ($pt['nama_pembeli'] ?? ''));
                                $tgl = $pt['created_at'] ?? '';
                                ?>
                                <tr>
                                    <td class="num"><?= $pid ?></td>
                                    <td><?= htmlspecialchars($cust) ?></td>
                                    <td><?= $namaPenerima ?></td>
                                    <td class="price">Rp <?= number_format((int) $pt['total_harga']) ?></td>
                                    <td><?= htmlspecialchars($tgl !== '' ? date('d/m/Y H:i', strtotime($tgl)) : '—') ?></td>
                                    <td>
                                        <a class="btn btn-success btn-sm" href="konfirmasi_pesanan.php?id=<?= $pid ?>&return=dashboard" onclick="return confirm('Konfirmasi pembayaran transfer untuk pesanan #<?= $pid ?>?')">Konfirmasi</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <section class="panel" aria-labelledby="daftar-produk">
            <div class="panel-head">
                <h2 id="daftar-produk">Daftar Produk</h2>
                <a href="tambah.php" class="btn btn-primary">＋ Tambah Produk Baru</a>
            </div>

            <?php if ($produkStat['jumlah'] === 0) { ?>
                <div class="empty-state">
                    <p>Belum ada produk di katalog.</p>
                    <a href="tambah.php" class="btn btn-primary">Tambah Produk Pertama</a>
                </div>
            <?php } else { ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = mysqli_fetch_assoc($data)) { ?>
                                <tr>
                                    <td class="num"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                                    <td class="price">Rp <?= number_format((int) $row['harga']) ?></td>
                                    <td class="num"><?= (int) $row['stok'] ?></td>
                                    <td>
                                        <a class="btn btn-edit btn-sm" href="edit.php?id=<?= $row['id'] ?>">Edit</a>
                                        <a class="btn btn-danger btn-sm" href="hapus.php?id=<?= $row['id'] ?>" onclick="return confirm('Hapus produk ini?')">Hapus</a>
                                    </td>
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