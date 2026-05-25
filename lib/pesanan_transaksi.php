<?php

/**
 * Label status pembayaran (kolom transaksi.status_pembayaran).
 */
function transaksi_status_label(string $kode): string
{
    $map = [
        'menunggu' => 'Menunggu pembayaran',
        'lunas' => 'Lunas',
        'menunggu_konfirmasi' => 'Menunggu konfirmasi transfer',
    ];

    return $map[$kode] ?? $kode;
}

function transaksi_status_class(string $kode): string
{
    if ($kode === 'lunas') {
        return 'status-lunas';
    }
    if ($kode === 'menunggu_konfirmasi') {
        return 'status-pending';
    }

    return 'status-wait';
}
