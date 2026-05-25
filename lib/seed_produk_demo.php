<?php

/**
 * Isi katalog demo — dipanggil hanya saat tabel produk masih kosong.
 */
function seed_produk_demo(mysqli $conn): void
{
    $items = [
        ['Monstera Adansonii (Janda Bolong)', 135000, 12, 'hias-daun'],
        ['Philodendron Brasil', 95000, 20, 'hias-daun'],
        ['Calathea Orbifolia', 165000, 8, 'hias-daun'],
        ['Sirih Gading Variegata', 45000, 30, 'hias-daun'],
        ['Anthurium Andraeanum Merah', 110000, 14, 'bunga'],
        ['Mawar Floribunda Pink', 85000, 10, 'bunga'],
        ['Bunga Krisan Pot', 55000, 25, 'bunga'],
        ['Lavender English (Herba)', 75000, 15, 'bunga'],
        ['Jeruk Kalamansi', 120000, 9, 'buah-kebun'],
        ['Alpukat Hass Bibit', 185000, 6, 'buah-kebun'],
        ['Stroberi Day Neutral', 65000, 18, 'buah-kebun'],
        ['Tomat Cherry Hidroponik', 35000, 40, 'sayur-herba'],
        ['Selada Romaine', 28000, 35, 'sayur-herba'],
        ['Basil Genovese', 32000, 22, 'sayur-herba'],
        ['Kemangi Limau', 25000, 28, 'sayur-herba'],
        ['Lidah Buaya (Aloe Vera)', 48000, 16, 'kaktus-sukulen'],
        ['Echeveria Elegans', 35000, 24, 'kaktus-sukulen'],
        ['Kaktus Mini Gymnocalycium', 42000, 20, 'kaktus-sukulen'],
        ['Bibit Trembesi', 25000, 50, 'pohon-pelindung'],
        ['Bibit Tabebuya Kuning', 35000, 30, 'pohon-pelindung'],
        ['Bambu Jepang (Pagar)', 45000, 14, 'pohon-pelindung'],
        ['Tanah Organik Premium 5 kg', 55000, 40, 'media-perlengkapan'],
        ['Cocopeat Brick 650g', 18000, 60, 'media-perlengkapan'],
        ['Pot Terracotta 20 cm', 32000, 45, 'media-perlengkapan'],
        ['Pupuk NPK Growmore 1 kg', 42000, 32, 'pupuk-nutrisi'],
        ['Pupuk Organik Cair POC', 68000, 20, 'pupuk-nutrisi'],
        ['Nutrisi AB Mix Hidroponik 250 ml', 55000, 25, 'pupuk-nutrisi'],
        ['Sekop Kebun Stainless', 75000, 12, 'alat-kebun'],
        ['Semprotan Tanaman 2L', 48000, 18, 'alat-kebun'],
    ];

    $stmt = mysqli_prepare($conn, 'INSERT INTO produk (nama_produk, kategori, harga, stok, gambar) VALUES (?, ?, ?, ?, NULL)');
    if (!$stmt) {
        return;
    }
    foreach ($items as $it) {
        [$nama, $harga, $stok, $kat] = $it;
        mysqli_stmt_bind_param($stmt, 'ssii', $nama, $kat, $harga, $stok);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}
