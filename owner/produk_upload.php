<?php

/** Direktori upload relatif dari root proyek (htdocs/ecommerce2/) */
const PRODUK_UPLOAD_WEB = 'uploads/produk';

function produk_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/' . PRODUK_UPLOAD_WEB;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Simpan file upload gambar produk.
 * @return string path web (uploads/produk/...) jika sukses tanpa file, '' jika tidak ada file, false jika gagal
 */
function produk_save_upload(array $file)
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $tmp = $file['tmp_name'];
    $mime = '';

    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($tmp) ?: '';
    }
    if ($mime === '') {
        $info = @getimagesize($tmp);
        if ($info !== false && !empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($map[$mime])) {
        return false;
    }

    $max = 5 * 1024 * 1024;
    if (!empty($file['size']) && (int) $file['size'] > $max) {
        return false;
    }

    $ext = $map[$mime];
    $name = 'p_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = produk_upload_dir() . DIRECTORY_SEPARATOR . $name;

    if (!is_uploaded_file($tmp) || !move_uploaded_file($tmp, $dest)) {
        return false;
    }

    return PRODUK_UPLOAD_WEB . '/' . $name;
}

/** Hapus file gambar di disk jika path valid dan aman */
function produk_delete_image_file(?string $relative): void
{
    if ($relative === null || $relative === '') {
        return;
    }
    if (strpos($relative, '..') !== false) {
        return;
    }
    if (strpos($relative, PRODUK_UPLOAD_WEB . '/') !== 0) {
        return;
    }
    $full = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_file($full)) {
        @unlink($full);
    }
}
