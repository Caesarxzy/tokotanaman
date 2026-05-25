<?php
$conn = mysqli_connect("localhost", "root", "", "toko_tanaman");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$tProduk = @mysqli_query($conn, "SHOW TABLES LIKE 'produk'");
if ($tProduk && mysqli_num_rows($tProduk) > 0) {
    $cGambar = @mysqli_query($conn, "SHOW COLUMNS FROM `produk` LIKE 'gambar'");
    if ($cGambar && mysqli_num_rows($cGambar) === 0) {
        @mysqli_query($conn, "ALTER TABLE `produk` ADD `gambar` VARCHAR(255) NULL DEFAULT NULL");
    }
    $cKat = @mysqli_query($conn, "SHOW COLUMNS FROM `produk` LIKE 'kategori'");
    if ($cKat && mysqli_num_rows($cKat) === 0) {
        @mysqli_query($conn, "ALTER TABLE `produk` ADD `kategori` VARCHAR(64) NOT NULL DEFAULT ''");
    }

    $tProdukCount = @mysqli_query($conn, "SELECT COUNT(*) AS n FROM produk");
    if ($tProdukCount && ($cn = mysqli_fetch_assoc($tProdukCount)) && (int) $cn['n'] === 0) {
        require_once __DIR__ . '/lib/seed_produk_demo.php';
        seed_produk_demo($conn);
    }
}

$tTransaksi = @mysqli_query($conn, "SHOW TABLES LIKE 'transaksi'");
if ($tTransaksi && mysqli_num_rows($tTransaksi) > 0) {
    $cMetode = @mysqli_query($conn, "SHOW COLUMNS FROM `transaksi` LIKE 'metode_pembayaran'");
    if ($cMetode && mysqli_num_rows($cMetode) === 0) {
        @mysqli_query($conn, "ALTER TABLE `transaksi` ADD `metode_pembayaran` VARCHAR(32) NOT NULL DEFAULT 'snap'");
    }
    $txCols = [
        'nama_pembeli' => 'VARCHAR(128) NOT NULL DEFAULT \'\'',
        'email_pembeli' => 'VARCHAR(128) NOT NULL DEFAULT \'\'',
        'telepon_pembeli' => 'VARCHAR(32) NOT NULL DEFAULT \'\'',
        'alamat_pengiriman' => 'VARCHAR(512) NOT NULL DEFAULT \'\'',
        'kota_pengiriman' => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
        'kode_pos' => 'VARCHAR(16) NOT NULL DEFAULT \'\'',
        'catatan_pengiriman' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
    ];
    foreach ($txCols as $col => $def) {
        $esc = mysqli_real_escape_string($conn, $col);
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `transaksi` WHERE Field = '$esc'");
        if ($chk && mysqli_num_rows($chk) === 0) {
            @mysqli_query($conn, "ALTER TABLE `transaksi` ADD `$esc` $def");
        }
    }
    $cCreatedAt = @mysqli_query($conn, "SHOW COLUMNS FROM `transaksi` LIKE 'created_at'");
    if ($cCreatedAt && mysqli_num_rows($cCreatedAt) === 0) {
        @mysqli_query($conn, "ALTER TABLE `transaksi` ADD `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    $cStatusBayar = @mysqli_query($conn, "SHOW COLUMNS FROM `transaksi` LIKE 'status_pembayaran'");
    if ($cStatusBayar && mysqli_num_rows($cStatusBayar) === 0) {
        @mysqli_query($conn, "ALTER TABLE `transaksi` ADD `status_pembayaran` VARCHAR(32) NOT NULL DEFAULT 'menunggu'");
    }
    $cWaktuBayar = @mysqli_query($conn, "SHOW COLUMNS FROM `transaksi` LIKE 'waktu_pembayaran'");
    if ($cWaktuBayar && mysqli_num_rows($cWaktuBayar) === 0) {
        @mysqli_query($conn, "ALTER TABLE `transaksi` ADD `waktu_pembayaran` DATETIME NULL DEFAULT NULL");
    }
}

$tItem = @mysqli_query($conn, "SHOW TABLES LIKE 'transaksi_item'");
if (!$tItem || mysqli_num_rows($tItem) === 0) {
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `transaksi_item` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `transaksi_id` INT UNSIGNED NOT NULL,
        `produk_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `nama_produk` VARCHAR(255) NOT NULL DEFAULT '',
        `harga_satuan` INT NOT NULL DEFAULT 0,
        `jumlah` INT NOT NULL DEFAULT 1,
        `subtotal` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_transaksi` (`transaksi_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
?>