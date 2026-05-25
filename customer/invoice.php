<?php

declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../lib/invoice_render.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$data = invoice_muat_data($conn, $id);
if ($data === null || (int) ($data['tx']['user_id'] ?? 0) !== $userId) {
    header('Location: pesanan.php');
    exit;
}

invoice_halaman($data['tx'], $data['items'], 'pesanan_detail.php?id=' . $id, 'Kembali ke detail pesanan');
