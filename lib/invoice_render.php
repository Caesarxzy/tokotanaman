<?php

declare(strict_types=1);

const INVOICE_NAMA_TOKO = 'Buana Gardenia';

/**
 * @return array{tx: array<string, mixed>, items: list<array<string, mixed>>}|null
 */
function invoice_muat_data(mysqli $conn, int $transaksiId): ?array
{
    if ($transaksiId < 1) {
        return null;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT t.*, u.email AS customer_email
         FROM transaksi t
         LEFT JOIN users u ON t.user_id = u.id
         WHERE t.id = ?'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $transaksiId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tx = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$tx) {
        return null;
    }

    $items = [];
    $qi = mysqli_prepare($conn, 'SELECT * FROM transaksi_item WHERE transaksi_id = ? ORDER BY id ASC');
    if ($qi) {
        mysqli_stmt_bind_param($qi, 'i', $transaksiId);
        mysqli_stmt_execute($qi);
        $r2 = mysqli_stmt_get_result($qi);
        while ($it = mysqli_fetch_assoc($r2)) {
            $items[] = $it;
        }
        mysqli_stmt_close($qi);
    }

    return ['tx' => $tx, 'items' => $items];
}

/**
 * Output dokumen HTML invoice (layar + cetak).
 *
 * @param list<array<string, mixed>> $items
 */
function invoice_halaman(array $tx, array $items, string $backUrl, string $backLabel = 'Kembali'): void
{
    if (!function_exists('metode_pembayaran_label')) {
        require_once __DIR__ . '/../customer/pembayaran.php';
    }
    if (!function_exists('transaksi_status_label')) {
        require_once __DIR__ . '/pesanan_transaksi.php';
    }

    $id = (int) ($tx['id'] ?? 0);
    $total = (int) ($tx['total_harga'] ?? 0);
    $met = (string) ($tx['metode_pembayaran'] ?? '');
    $st = (string) ($tx['status_pembayaran'] ?? 'menunggu');
    $tgl = (string) ($tx['created_at'] ?? '');
    $tglStr = $tgl !== '' ? date('d/m/Y H:i', strtotime($tgl)) : '—';
    $akun = trim((string) ($tx['customer_email'] ?? ''));
    if ($akun === '') {
        $akun = '—';
    }

    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $id ?> — <?= htmlspecialchars(INVOICE_NAMA_TOKO) ?></title>
    <style>
        :root { --ink: #0d3d26; --accent: #009b4d; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; color: var(--ink); background: #f4faf6; }
        .no-print { padding: 16px 20px; background: var(--accent); color: #fff; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .no-print a, .no-print button { font-weight: 600; text-decoration: none; border-radius: 10px; padding: 10px 18px; cursor: pointer; font-size: 0.9rem; border: 2px solid #fff; background: #fff; color: var(--accent); }
        .no-print button { background: transparent; color: #fff; border-color: #fff; }
        .no-print a:hover { filter: brightness(0.95); }
        .no-print button:hover { background: rgba(255,255,255,0.15); }
        .sheet { max-width: 720px; margin: 0 auto; padding: 28px 24px 40px; background: #fff; min-height: 60vh; }
        .inv-head { border-bottom: 3px solid var(--accent); padding-bottom: 16px; margin-bottom: 20px; }
        .inv-head h1 { margin: 0 0 4px; font-size: 1.5rem; color: var(--accent); letter-spacing: -0.02em; }
        .inv-head .tag { font-size: 0.85rem; color: #555; }
        .inv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; font-size: 0.9rem; margin-bottom: 22px; }
        .inv-grid dt { margin: 0 0 2px; font-weight: 700; color: var(--accent); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .inv-grid dd { margin: 0 0 12px; }
        .inv-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; margin-bottom: 20px; }
        .inv-table th, .inv-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #ddd; }
        .inv-table th { background: rgba(0,155,77,0.1); color: var(--accent); font-weight: 700; }
        .inv-table .num { text-align: right; white-space: nowrap; }
        .inv-total { display: flex; justify-content: flex-end; margin-top: 8px; }
        .inv-total-inner { min-width: 260px; border: 2px solid var(--accent); border-radius: 10px; padding: 14px 16px; }
        .inv-total-row { display: flex; justify-content: space-between; gap: 16px; font-size: 1rem; }
        .inv-total-row strong { font-size: 1.15rem; color: var(--accent); }
        .inv-foot { margin-top: 28px; padding-top: 16px; border-top: 1px solid #ddd; font-size: 0.82rem; color: #666; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { padding: 0; max-width: none; min-height: auto; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <span>Invoice pesanan #<?= $id ?></span>
        <span>
            <a href="<?= htmlspecialchars($backUrl) ?>"><?= htmlspecialchars($backLabel) ?></a>
            <button type="button" onclick="window.print()">Cetak / PDF</button>
        </span>
    </div>
    <div class="sheet">
        <header class="inv-head">
            <h1><?= htmlspecialchars(INVOICE_NAMA_TOKO) ?></h1>
            <p class="tag">Invoice &middot; Nomor pesanan <strong>#<?= $id ?></strong> &middot; <?= htmlspecialchars($tglStr) ?></p>
        </header>

        <dl class="inv-grid">
            <div>
                <dt>Status pembayaran</dt>
                <dd><?= htmlspecialchars(transaksi_status_label($st)) ?></dd>
                <dt>Metode</dt>
                <dd><?= htmlspecialchars(metode_pembayaran_label($met)) ?></dd>
                <dt>Akun pemesan</dt>
                <dd><?= htmlspecialchars($akun) ?></dd>
            </div>
            <div>
                <dt>Penerima &amp; pengiriman</dt>
                <dd>
                    <strong><?= htmlspecialchars((string) ($tx['nama_pembeli'] ?? '')) ?></strong><br>
                    <?= htmlspecialchars((string) ($tx['email_pembeli'] ?? '')) ?> · <?= htmlspecialchars((string) ($tx['telepon_pembeli'] ?? '')) ?><br>
                    <?= nl2br(htmlspecialchars((string) ($tx['alamat_pengiriman'] ?? ''))) ?><br>
                    <?= htmlspecialchars((string) ($tx['kota_pengiriman'] ?? '')) ?><?= trim((string) ($tx['kode_pos'] ?? '')) !== '' ? ' ' . htmlspecialchars((string) $tx['kode_pos']) : '' ?>
                </dd>
            </div>
        </dl>

        <?php if (trim((string) ($tx['catatan_pengiriman'] ?? '')) !== '') { ?>
            <p style="font-size:0.88rem;margin:0 0 16px;"><strong>Catatan:</strong> <?= htmlspecialchars((string) $tx['catatan_pengiriman']) ?></p>
        <?php } ?>

        <?php if (count($items) === 0) { ?>
            <p style="font-size:0.9rem;color:#666;">Rincian item tidak tersedia untuk pesanan ini.</p>
            <div class="inv-total">
                <div class="inv-total-inner">
                    <div class="inv-total-row"><span>Total</span><strong>Rp <?= number_format($total) ?></strong></div>
                </div>
            </div>
        <?php } else { ?>
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="num">Harga satuan</th>
                        <th class="num">Qty</th>
                        <th class="num">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it) { ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($it['nama_produk'] ?? '')) ?></td>
                            <td class="num">Rp <?= number_format((int) ($it['harga_satuan'] ?? 0)) ?></td>
                            <td class="num"><?= (int) ($it['jumlah'] ?? 0) ?></td>
                            <td class="num">Rp <?= number_format((int) ($it['subtotal'] ?? 0)) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <div class="inv-total">
                <div class="inv-total-inner">
                    <div class="inv-total-row"><span>Total pembayaran</span><strong>Rp <?= number_format($total) ?></strong></div>
                </div>
            </div>
        <?php } ?>

        <footer class="inv-foot">
            Dokumen ini dibuat secara elektronik. Terima kasih atas pembelian Anda di <?= htmlspecialchars(INVOICE_NAMA_TOKO) ?>.
        </footer>
    </div>
</body>
</html>
    <?php
}
