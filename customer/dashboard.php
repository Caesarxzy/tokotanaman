<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../lib/kategori_produk.php';
require_once __DIR__ . '/../lib/pesanan_transaksi.php';

if (isset($_GET['paid'], $_SESSION['last_transaksi_id'])) {
    $tid = (int) $_SESSION['last_transaksi_id'];
    if ($tid > 0) {
        $paidFlag = (string) $_GET['paid'];
        if (in_array($paidFlag, ['snap', 'dummy_snap', 'demo', '1'], true)) {
            $st = 'lunas';
            $up = mysqli_prepare($conn, 'UPDATE transaksi SET status_pembayaran = ? WHERE id = ? AND user_id = ?');
            if ($up) {
                mysqli_stmt_bind_param($up, 'sii', $st, $tid, $userId);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);
            }
        } elseif ($paidFlag === 'transfer') {
            $st = 'menunggu_konfirmasi';
            $up = mysqli_prepare($conn, 'UPDATE transaksi SET status_pembayaran = ? WHERE id = ? AND user_id = ?');
            if ($up) {
                mysqli_stmt_bind_param($up, 'sii', $st, $tid, $userId);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);
            }
        }
    }
    unset($_SESSION['last_transaksi_id']);
}

$q = trim($_GET['q'] ?? '');
$kat = trim($_GET['kat'] ?? '');

$sql = 'SELECT * FROM produk WHERE 1=1';
if ($q !== '') {
    $qEsc = mysqli_real_escape_string($conn, $q);
    $sql .= " AND (nama_produk LIKE '%{$qEsc}%' OR kategori LIKE '%{$qEsc}%')";
}
if ($kat === kategori_filter_tanpa_kategori()) {
    $sql .= " AND TRIM(IFNULL(kategori,'')) = ''";
} elseif ($kat !== '' && kategori_produk_valid($kat)) {
    $katEsc = mysqli_real_escape_string($conn, $kat);
    $sql .= " AND kategori = '{$katEsc}'";
}
$sql .= ' ORDER BY kategori ASC, nama_produk ASC';

$data = mysqli_query($conn, $sql);

$cartCount = 0;
$qc = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) AS j FROM cart WHERE user_id=$userId");
if ($qc && ($rc = mysqli_fetch_assoc($qc))) {
    $cartCount = (int) $rc['j'];
}

$kategoriChips = kategori_produk_map();
$queryBase = [];
if ($q !== '') {
    $queryBase['q'] = $q;
}

$uid = (int) $userId;
$pesananTerbaru = [];
$customerTotalPesanan = 0;
$customerTotalNilai = 0;
$tPesanan = @mysqli_query($conn, "SHOW TABLES LIKE 'transaksi'");
if ($tPesanan && mysqli_num_rows($tPesanan) > 0) {
    $totQ = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS n, COALESCE(SUM(total_harga), 0) AS rp FROM transaksi WHERE user_id={$uid}"
    );
    if ($totQ && ($totR = mysqli_fetch_assoc($totQ))) {
        $customerTotalPesanan = (int) $totR['n'];
        $customerTotalNilai = (int) $totR['rp'];
    }
    $rq = mysqli_query(
        $conn,
        "SELECT id, total_harga, status_pembayaran, metode_pembayaran, created_at 
         FROM transaksi WHERE user_id={$uid} ORDER BY id DESC LIMIT 4"
    );
    if ($rq) {
        while ($rp = mysqli_fetch_assoc($rq)) {
            $pesananTerbaru[] = $rp;
        }
    }
}

$slideItems = [];
$slideQuery = mysqli_query(
    $conn,
    "SELECT p.id, p.nama_produk, p.gambar
     FROM transaksi_item ti
     JOIN produk p ON p.id = ti.produk_id
     GROUP BY p.id
     ORDER BY SUM(ti.jumlah) DESC, p.id ASC
     LIMIT 3"
);
if ($slideQuery) {
    while ($slideRow = mysqli_fetch_assoc($slideQuery)) {
        $slideItems[] = $slideRow;
    }
}

if (count($slideItems) === 0) {
    $slideItems = [
        ['nama_produk' => 'Koleksi Terbaru', 'gambar' => ''],
        ['nama_produk' => 'Promo Musim Ini', 'gambar' => ''],
        ['nama_produk' => 'Tanaman Favorit', 'gambar' => ''],
    ];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belanja — Buana Gardenia</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <h2>Buana Gardenia</h2>
    <nav class="navbar-actions">
        <a class="btn btn-nav-home" href="pesanan.php">📦 Riwayat</a>
        <a class="btn btn-nav-cart" href="cart.php">🛒 Keranjang<?= $cartCount > 0 ? ' <strong>(' . $cartCount . ')</strong>' : '' ?></a>
        <a class="btn btn-nav-logout" href="../logout.php">Logout</a>
    </nav>
</header>

<main class="wrap">
    <h1 class="page-title">Katalog Toko</h1>
    <p class="page-sub">Halo <strong><?= $namaCustomer ?></strong> — cari tanaman, media tanam, atau perlengkapan kebun Anda.</p>

    <section class="hero-slider" aria-label="Banner promosi">
        <div class="slide-window">
            <?php foreach ($slideItems as $index => $slide) { ?>
                <article class="slide<?= $index === 0 ? ' is-active' : '' ?>">
                    <?php if ($slide['gambar'] !== '') { ?>
                        <img src="<?= htmlspecialchars('../' . $slide['gambar']) ?>" alt="<?= htmlspecialchars($slide['nama_produk']) ?>">
                    <?php } else { ?>
                        <div class="slide-placeholder">
                            <div>
                                <p class="slide-placeholder-label">Promo Buana Gardenia</p>
                                <h2><?= htmlspecialchars($slide['nama_produk']) ?></h2>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="slide-caption">
                        <span>Highlight</span>
                        <h2><?= htmlspecialchars($slide['nama_produk']) ?></h2>
                    </div>
                </article>
            <?php } ?>
        </div>
        <div class="slide-search">
            <form class="catalog-search banner-search" method="get" action="dashboard.php" role="search">
                <label class="visually-hidden" for="q">Cari produk</label>
                <input type="search" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama tanaman, pupuk, pot…" autocomplete="off">
                <button type="submit" class="btn btn-primary btn-search">Cari</button>
            </form>
        </div>
        <div class="slide-indicators" aria-label="Kontrol slideshow">
            <?php foreach ($slideItems as $index => $slide) { ?>
                <button type="button" class="slide-indicator<?= $index === 0 ? ' is-active' : '' ?>" data-index="<?= $index ?>" aria-label="Slide <?= $index + 1 ?>"></button>
            <?php } ?>
        </div>
    </section>

    <?php if (!$data || mysqli_num_rows($data) === 0) { ?>
        <div class="panel">
            <div class="empty-state">
                <p>Tidak ada produk yang cocok dengan filter Anda.</p>
                <a class="btn btn-ghost" href="dashboard.php">Reset filter</a>
            </div>
        </div>
    <?php } else { ?>
        <p class="catalog-result"><?= mysqli_num_rows($data) ?> produk ditemukan.</p>
        <div class="grid">
            <?php while ($row = mysqli_fetch_assoc($data)) {
                $stok = (int) $row['stok'];
                $gambar = $row['gambar'] ?? '';
                $katSlug = trim((string) ($row['kategori'] ?? ''));
                ?>
                <article class="card">
                    <?php if ($gambar !== '') { ?>
                        <img class="card-img" src="<?= htmlspecialchars('../' . $gambar) ?>" alt="" loading="lazy">
                    <?php } else { ?>
                        <div class="card-img placeholder" aria-hidden="true">🌱</div>
                    <?php } ?>
                    <div class="card-body">
                        <span class="card-badge"><?= htmlspecialchars(kategori_produk_label($katSlug)) ?></span>
                        <h3><?= htmlspecialchars($row['nama_produk']) ?></h3>
                        <p class="card-price">Rp <?= number_format((int) $row['harga']) ?></p>
                        <p class="card-stok<?= $stok < 5 ? ' low' : '' ?>">Stok: <?= $stok ?></p>

                        <?php if ($stok < 1) { ?>
                            <p class="card-stok low">Habis — tidak bisa dipesan</p>
                        <?php } else { ?>
                            <form class="card-form" method="POST" action="cart.php">
                                <input type="hidden" name="produk_id" value="<?= (int) $row['id'] ?>">
                                <div class="qty-row">
                                    <label for="jml-<?= (int) $row['id'] ?>">Jumlah</label>
                                    <input id="jml-<?= (int) $row['id'] ?>" type="number" name="jumlah" value="1" min="1" max="<?= $stok ?>" required>
                                </div>
                                <button class="btn btn-success btn-block" type="submit" name="tambah">Tambah ke keranjang</button>
                            </form>
                        <?php } ?>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } ?>
</main>

<script>
(function () {
    const slides = Array.from(document.querySelectorAll('.hero-slider .slide'));
    const prevButton = document.querySelector('.hero-slider .slide-prev');
    const nextButton = document.querySelector('.hero-slider .slide-next');
    const indicators = Array.from(document.querySelectorAll('.slide-indicator'));
    let currentIndex = 0;
    let timer = null;

    function showSlide(index) {
        slides.forEach((slide, idx) => {
            slide.classList.toggle('is-active', idx === index);
        });
        indicators.forEach((dot, idx) => {
            dot.classList.toggle('is-active', idx === index);
        });
        currentIndex = index;
    }

    function nextSlide() {
        showSlide((currentIndex + 1) % slides.length);
    }

    function startAutoSlide() {
        if (timer) clearInterval(timer);
        timer = setInterval(nextSlide, 3000);
    }

    if (slides.length) {
        if (prevButton && nextButton) {
            prevButton.addEventListener('click', function () {
                showSlide((currentIndex - 1 + slides.length) % slides.length);
                startAutoSlide();
            });
            nextButton.addEventListener('click', function () {
                nextSlide();
                startAutoSlide();
            });
        }

        if (indicators.length) {
            indicators.forEach((dot) => {
                dot.addEventListener('click', function () {
                    const idx = Number(this.dataset.index);
                    if (!Number.isNaN(idx)) {
                        showSlide(idx);
                        startAutoSlide();
                    }
                });
            });
        }

        startAutoSlide();
    }
})();
</script>

</body>
</html>
