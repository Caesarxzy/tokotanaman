<?php

namespace Midtrans;

/**
 * Lapisan tipis untuk Snap: mode dummy (tanpa jaringan) atau permintaan ke API Midtrans.
 */
class Config
{
    public static $serverKey = '';

    public static $clientKey = '';

    public static $isProduction = false;

    /** @var bool */
    public static $useDummy = false;
}

class Snap
{
    /**
     * @param array<string, mixed> $params Payload Snap (transaction_details, customer_details, dll.)
     */
    public static function getSnapToken(array $params): string
    {
        if (Config::$useDummy) {
            $oid = (string) ($params['transaction_details']['order_id'] ?? 'ORDER');
            $amt = (int) ($params['transaction_details']['gross_amount'] ?? 0);
            $bin = $oid . '|' . $amt . '|' . microtime(true);
            $b64 = rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

            return 'DUMMY_' . $b64;
        }

        if (Config::$serverKey === '') {
            throw new \RuntimeException('Server Key Midtrans kosong.');
        }

        $base = Config::$isProduction
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
        $url = $base . '/snap/v1/transactions';

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (\defined('JSON_THROW_ON_ERROR')) {
            $flags |= JSON_THROW_ON_ERROR;
        }
        $payload = json_encode($params, $flags);
        if ($payload === false) {
            throw new \RuntimeException('Gagal meng-encode JSON untuk Snap.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Gagal inisialisasi cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode(Config::$serverKey . ':'),
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Koneksi ke Midtrans gagal: ' . $cerr);
        }

        $json = json_decode($body, true);
        if (!\is_array($json)) {
            throw new \RuntimeException('Respons Midtrans tidak valid (HTTP ' . $code . ').');
        }

        if (!empty($json['error_messages']) && \is_array($json['error_messages'])) {
            throw new \RuntimeException(implode('; ', $json['error_messages']));
        }

        if ($code >= 400) {
            $msg = isset($json['status_message']) ? (string) $json['status_message'] : $body;

            throw new \RuntimeException('Midtrans HTTP ' . $code . ': ' . $msg);
        }

        if (empty($json['token'])) {
            throw new \RuntimeException('Token Snap tidak ada dalam respons.');
        }

        return (string) $json['token'];
    }
}
