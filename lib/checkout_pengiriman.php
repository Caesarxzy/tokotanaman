<?php

const SESSION_CHECKOUT_PENGIRIMAN = 'checkout_pengiriman';

function checkout_pengiriman_get(): ?array
{
    $p = $_SESSION[SESSION_CHECKOUT_PENGIRIMAN] ?? null;
    return is_array($p) ? $p : null;
}

function checkout_pengiriman_clear(): void
{
    unset($_SESSION[SESSION_CHECKOUT_PENGIRIMAN]);
}

function checkout_pengiriman_is_complete(): bool
{
    $p = checkout_pengiriman_get();
    if ($p === null) {
        return false;
    }
    foreach (['nama_lengkap', 'email', 'telepon', 'alamat', 'kota'] as $k) {
        if (trim((string) ($p[$k] ?? '')) === '') {
            return false;
        }
    }
    return (bool) filter_var($p['email'], FILTER_VALIDATE_EMAIL);
}

/**
 * Validasi POST; mengembalikan array data bersih atau null jika gagal.
 * @param list<string> $errors diisi pesan jika gagal
 */
function checkout_pengiriman_validate_post(array &$errors): ?array
{
    $errors = [];
    $nama = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telepon = preg_replace('/\D+/', '', (string) ($_POST['telepon'] ?? ''));
    $alamat = trim((string) ($_POST['alamat'] ?? ''));
    $kota = trim((string) ($_POST['kota'] ?? ''));
    $kode_pos = trim((string) ($_POST['kode_pos'] ?? ''));
    $catatan = trim((string) ($_POST['catatan'] ?? ''));

    $len = static function (string $s): int {
        return function_exists('mb_strlen') ? (int) mb_strlen($s) : strlen($s);
    };
    if ($len($nama) < 3) {
        $errors[] = 'Nama lengkap minimal 3 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }
    if (strlen($telepon) < 10) {
        $errors[] = 'Nomor telepon/WA minimal 10 digit.';
    }
    if ($len($alamat) < 10) {
        $errors[] = 'Alamat lengkap minimal 10 karakter.';
    }
    if ($len($kota) < 2) {
        $errors[] = 'Kota / kabupaten wajib diisi.';
    }

    if ($errors !== []) {
        return null;
    }

    return [
        'nama_lengkap' => $nama,
        'email' => $email,
        'telepon' => $telepon,
        'alamat' => $alamat,
        'kota' => $kota,
        'kode_pos' => $kode_pos,
        'catatan' => $catatan,
    ];
}
