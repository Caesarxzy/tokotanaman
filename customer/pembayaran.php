<?php

/** Kode disimpan di kolom transaksi.metode_pembayaran */
const METODE_SNAP = 'snap';
const METODE_TRANSFER = 'transfer';
const METODE_DUMMY = 'dummy';

function metode_pembayaran_allowed(): array
{
    return [METODE_SNAP, METODE_TRANSFER, METODE_DUMMY];
}

function metode_pembayaran_label(string $kode): string
{
    $map = [
        METODE_SNAP => 'Midtrans (QRIS & virtual account)',
        METODE_TRANSFER => 'Transfer bank',
        METODE_DUMMY => 'Dummy Payment Gateway',
    ];
    return $map[$kode] ?? $kode;
}

/**
 * Instruksi transfer manual.
 * - nomor: nomor rekening bank (isi jika dipakai)
 * - virtual_account: nomor VA manual toko (isi jika dipakai; bisa hanya salah satu)
 * Sesuaikan di bawah untuk toko Anda.
 */
function metode_transfer_info(): array
{
    return [
        'bank' => 'BCA',
        'nomor' => '7831231178',
        /** Kosongkan '' jika tidak memakai VA manual; isi nomor VA statis toko jika ada */
        'virtual_account' => '',
        /** Opsional, jika VA dari bank lain: */
        'bank_va' => '',
        'atas_nama_va' => '',
        'atas_nama' => 'Toko Tanaman',
        'catatan' => 'Cantumkan nomor pesanan pada berita transfer.',
    ];
}

/**
 * Jenis pembayaran Snap Midtrans: hanya QRIS + VA bank umum.
 * Sesuaikan dengan yang diaktifkan di dashboard Midtrans Anda.
 *
 * @return list<string>
 */
function midtrans_enabled_payments(): array
{
    return [
        'qris',
        'bca_va',
        'bni_va',
        'permata_va',
        'bri_va',
        'echannel',
    ];
}
