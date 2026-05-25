<?php

/**
 * Kategori katalog toko kebun — slug disimpan di kolom produk.kategori
 */
function kategori_produk_map(): array
{
    return [
        'hias-daun' => 'Tanaman hias daun',
        'bunga' => 'Tanaman berbunga',
        'buah-kebun' => 'Buah pot & kebun buah',
        'sayur-herba' => 'Sayuran & herba',
        'kaktus-sukulen' => 'Kaktus & sukulen',
        'pohon-pelindung' => 'Pohon pelindung & pagar hidup',
        'media-perlengkapan' => 'Media tanam & pot',
        'pupuk-nutrisi' => 'Pupuk & nutrisi',
        'alat-kebun' => 'Alat & perlengkapan kebun',
        'lainnya' => 'Lainnya',
    ];
}

function kategori_produk_slugs(): array
{
    return array_keys(kategori_produk_map());
}

function kategori_produk_label(string $slug): string
{
    $slug = trim($slug);
    if ($slug === '') {
        return 'Belum dikategorikan';
    }
    $m = kategori_produk_map();
    return $m[$slug] ?? $slug;
}

function kategori_produk_valid(string $slug): bool
{
    return $slug !== '' && isset(kategori_produk_map()[$slug]);
}

/** Filter katalog: produk tanpa kategori di database */
function kategori_filter_tanpa_kategori(): string
{
    return 'nonkat';
}
