<?php

/**
 * Konfigurasi Midtrans.
 *
 * Mode dummy (default): tidak memanggil API Midtrans; halaman bayar memakai simulator Snap di browser.
 * Untuk sandbox asli: set 'use_dummy' => false lalu isi Server Key & Client Key dari dashboard Midtrans.
 *
 * @return array{
 *   use_dummy: bool,
 *   client_key: string,
 *   server_key: string,
 *   is_production: bool
 * }
 */
return [
    'use_dummy' => true,
    'client_key' => 'SB-Mid-client-dummy-local',
    'server_key' => 'SB-Mid-server-dummy-local',
    'is_production' => false,
];
