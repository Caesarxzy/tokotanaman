<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/pembayaran.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: dashboard.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, user_id, total_harga, metode_pembayaran, nama_pembeli, email_pembeli, telepon_pembeli, alamat_pengiriman, kota_pengiriman, kode_pos, catatan_pengiriman FROM transaksi WHERE id = ?');
if (!$stmt) {
    header('Location: dashboard.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$data || (int) $data['user_id'] !== $userId) {
    header('Location: dashboard.php');
    exit;
}

$total = (int) $data['total_harga'];
$metode = $data['metode_pembayaran'] ?? METODE_SNAP;
if ($metode === 'cod') {
    $metode = METODE_TRANSFER;
}
if (!in_array($metode, metode_pembayaran_allowed(), true)) {
    $metode = METODE_SNAP;
}

$midtransLib = __DIR__ . '/../midtrans/Midtrans.php';
$configPath = __DIR__ . '/../midtrans/config.php';
$midConfig = is_file($configPath) ? require $configPath : [];
if (!\is_array($midConfig)) {
    $midConfig = [];
}
$midConfig = array_merge([
    'use_dummy' => true,
    'client_key' => '',
    'server_key' => '',
    'is_production' => false,
], $midConfig);

$useDummy = !empty($midConfig['use_dummy']);
$serverKey = (string) ($midConfig['server_key'] ?? '');
$clientKey = (string) ($midConfig['client_key'] ?? '');
$isProductionMidtrans = !empty($midConfig['is_production']);

$looksLikePlaceholder = stripos($serverKey, 'dummy') !== false
    || stripos($clientKey, 'dummy') !== false;
$realKeysOk = !$useDummy
    && !$looksLikePlaceholder
    && $serverKey !== ''
    && $clientKey !== ''
    && (
        strpos($serverKey, 'SB-Mid-server-') === 0
        || strpos($serverKey, 'Mid-server-') === 0
    );

$midtransReady = is_file($midtransLib) && ($useDummy || $realKeysOk);

$snapToken = '';
$midtransError = '';

if ($metode === METODE_SNAP && $midtransReady) {
    require_once $midtransLib;
    try {
        \Midtrans\Config::$serverKey = $serverKey;
        \Midtrans\Config::$clientKey = $clientKey;
        \Midtrans\Config::$isProduction = $isProductionMidtrans;
        \Midtrans\Config::$useDummy = $useDummy;
        $namaSnap = trim((string) ($data['nama_pembeli'] ?? ''));
        if ($namaSnap === '') {
            $namaSnap = $_SESSION['user']['nama'] ?? $_SESSION['user']['username'] ?? $_SESSION['user']['email'] ?? 'Pelanggan';
        }
        $nama = substr($namaSnap, 0, 50);
        $emailSnap = trim((string) ($data['email_pembeli'] ?? ''));
        if ($emailSnap === '' || !filter_var($emailSnap, FILTER_VALIDATE_EMAIL)) {
            $emailSnap = $_SESSION['user']['email'] ?? 'customer@email.test';
        }
        $params = [
            'transaction_details' => [
                'order_id' => 'TRX-' . $id . '-' . bin2hex(random_bytes(3)),
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'first_name' => $nama,
                'email' => $emailSnap,
                'phone' => preg_replace('/\D+/', '', (string) ($data['telepon_pembeli'] ?? '')) ?: '081234567890',
            ],
            'enabled_payments' => midtrans_enabled_payments(),
        ];
        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
        } catch (Throwable $eFirst) {
            unset($params['enabled_payments']);
            $snapToken = \Midtrans\Snap::getSnapToken($params);
        }
    } catch (Throwable $e) {
        $midtransError = $e->getMessage();
        $midtransReady = false;
    }
}

$rek = metode_transfer_info();
$va = trim((string) ($rek['virtual_account'] ?? ''));
$noRek = trim((string) ($rek['nomor'] ?? ''));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran</title>
    <link rel="stylesheet" href="style.css">
    <?php if ($metode === METODE_SNAP && $midtransReady && $snapToken !== '' && !$useDummy) { ?>
        <script src="<?= $isProductionMidtrans ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com' ?>/snap/snap.js" data-client-key="<?= htmlspecialchars($clientKey) ?>"></script>
    <?php } ?>
</head>
<body>

<header class="navbar">
    <h2>💳 Pembayaran</h2>
    <nav class="navbar-actions">
        <a class="btn btn-nav-home" href="dashboard.php">← Beranda</a>
    </nav>
</header>

<main class="wrap stack checkout-stack">
    <h1 class="page-title">Total: Rp <?= number_format($total) ?></h1>
    <p class="page-sub">Transaksi #<?= (int) $id ?> · <?= htmlspecialchars(metode_pembayaran_label($metode)) ?> · <a href="pesanan_detail.php?id=<?= (int) $id ?>">Detail pesanan lengkap</a></p>

    <div class="summary-box">
        <h2>Pengiriman</h2>
        <div class="pengiriman-readonly">
            <p><strong><?= htmlspecialchars((string) ($data['nama_pembeli'] ?? '')) ?></strong></p>
            <p><?= htmlspecialchars((string) ($data['email_pembeli'] ?? '')) ?> · <?= htmlspecialchars((string) ($data['telepon_pembeli'] ?? '')) ?></p>
            <p><?= nl2br(htmlspecialchars((string) ($data['alamat_pengiriman'] ?? ''))) ?></p>
            <p><?= htmlspecialchars((string) ($data['kota_pengiriman'] ?? '')) ?><?= trim((string) ($data['kode_pos'] ?? '')) !== '' ? ' ' . htmlspecialchars((string) $data['kode_pos']) : '' ?></p>
            <?php if (trim((string) ($data['catatan_pengiriman'] ?? '')) !== '') { ?>
                <p class="bayar-hint"><strong>Catatan:</strong> <?= htmlspecialchars((string) $data['catatan_pengiriman']) ?></p>
            <?php } ?>
        </div>
    </div>

    <div class="summary-box">
        <?php if ($metode === METODE_SNAP) { ?>
            <?php if ($midtransReady && $snapToken !== '') { ?>
                <?php if ($useDummy) { ?>
                    <div class="alert alert-warn" role="status">Mode <strong>simulator Midtrans</strong> aktif — tidak ada pembayaran sungguhan. Klik tombol di bawah lalu pilih hasil di jendela simulasi.</div>
                    <p class="bayar-lead">Ini menggantikan jendela Snap asli (QRIS / VA) untuk pengembangan lokal.</p>
                <?php } else { ?>
                    <p class="bayar-lead">Pilih <strong>QRIS</strong> atau <strong>virtual account</strong> bank di jendela pembayaran Midtrans.</p>
                    <ul class="bayar-list">
                        <li>QRIS — scan kode QR dengan aplikasi e-wallet atau m-banking.</li>
                        <li>Virtual account — BCA, BNI, Permata, BRI, atau Mandiri (sesuai yang aktif di akun Midtrans Anda).</li>
                    </ul>
                <?php } ?>
                <button type="button" id="pay-button" class="btn btn-primary btn-block"><?= $useDummy ? 'Buka simulator pembayaran' : 'Bayar sekarang (Midtrans)' ?></button>
            <?php } else { ?>
                <p class="bayar-lead">Midtrans belum dikonfigurasi atau token gagal dibuat. Pastikan channel QRIS/VA sudah diaktifkan di dashboard Midtrans. Untuk uji coba lokal, aktifkan mode dummy di <code>midtrans/config.php</code> atau selesaikan simulasi di bawah.</p>
                <?php if ($midtransError !== '') { ?>
                    <p class="alert alert-error bayar-alert"><?= htmlspecialchars($midtransError) ?></p>
                <?php } ?>
                <a class="btn btn-success btn-block" href="dashboard.php?paid=demo">Selesai (simulasi berhasil)</a>
                <a class="btn btn-ghost btn-block bayar-gap" href="dashboard.php">Kembali ke katalog</a>
            <?php } ?>

        <?php } elseif ($metode === METODE_DUMMY) { ?>
            <p class="bayar-lead">Ini adalah gateway pembayaran dummy untuk testing. Klik tombol di bawah untuk mensimulasikan pembayaran.</p>
            <a class="btn btn-primary btn-block" href="dummy_gateway.php?id=<?= (int) $id ?>">Simulasi Pembayaran</a>

        <?php } else { /* transfer */ ?>
            <p class="bayar-lead">Silakan transfer tepat <strong>Rp <?= number_format($total) ?></strong> menggunakan salah satu tujuan di bawah.</p>

            <?php if ($va !== '') { ?>
                <h3 class="bayar-subheading">Virtual account</h3>
                <div class="rekening-box">
                    <?php if (!empty($rek['bank_va'])) { ?>
                        <div class="rekening-row"><span>Bank</span><strong><?= htmlspecialchars((string) $rek['bank_va']) ?></strong></div>
                    <?php } ?>
                    <div class="rekening-row"><span>No. virtual account</span><strong class="mono"><?= htmlspecialchars($va) ?></strong></div>
                    <?php if (!empty($rek['atas_nama_va'])) { ?>
                        <div class="rekening-row"><span>Atas nama</span><strong><?= htmlspecialchars((string) $rek['atas_nama_va']) ?></strong></div>
                    <?php } ?>
                    <div class="rekening-row"><span>Referensi</span><strong class="mono">TRX-<?= (int) $id ?></strong></div>
                </div>
            <?php } ?>

            <?php if ($noRek !== '') { ?>
                <h3 class="bayar-subheading"><?= $va !== '' ? 'Atau rekening bank' : 'Rekening bank' ?></h3>
                <div class="rekening-box">
                    <div class="rekening-row"><span>Bank</span><strong><?= htmlspecialchars((string) ($rek['bank'] ?? '')) ?></strong></div>
                    <div class="rekening-row"><span>No. rekening</span><strong class="mono"><?= htmlspecialchars($noRek) ?></strong></div>
                    <div class="rekening-row"><span>Atas nama</span><strong><?= htmlspecialchars((string) ($rek['atas_nama'] ?? '')) ?></strong></div>
                    <div class="rekening-row"><span>Referensi</span><strong class="mono">TRX-<?= (int) $id ?></strong></div>
                </div>
            <?php } ?>

            <?php if ($va === '' && $noRek === '') { ?>
                <p class="alert alert-error">Belum ada nomor rekening atau virtual account. Hubungi admin atau ubah pengaturan di <code>customer/pembayaran.php</code> (fungsi <code>metode_transfer_info</code>).</p>
            <?php } else { ?>
                <p class="bayar-hint"><?= htmlspecialchars((string) ($rek['catatan'] ?? '')) ?></p>
                <p class="bayar-hint">Setelah transfer, tim kami akan memverifikasi pembayaran.</p>
                <a class="btn btn-success btn-block" href="dashboard.php?paid=transfer">Saya sudah transfer</a>
            <?php } ?>
            <a class="btn btn-ghost btn-block bayar-gap" href="dashboard.php">Kembali ke katalog</a>
        <?php } ?>
    </div>
</main>

<?php if ($metode === METODE_SNAP && $midtransReady && $snapToken !== '' && $useDummy) { ?>
<div id="snap-dummy-overlay" class="snap-dummy-overlay" hidden>
    <div class="snap-dummy-dialog" role="dialog" aria-labelledby="snap-dummy-title" aria-modal="true">
        <h3 id="snap-dummy-title" class="snap-dummy-title">Simulator Midtrans Snap</h3>
        <p class="snap-dummy-token-wrap">Token: <code id="snap-dummy-token" class="mono snap-dummy-token"></code></p>
        <p class="snap-dummy-hint">Pilih alur yang ingin Anda uji:</p>
        <div class="snap-dummy-actions">
            <button type="button" class="btn btn-success btn-block" id="snap-dummy-success">Berhasil (settlement)</button>
            <button type="button" class="btn btn-primary btn-block" id="snap-dummy-pending">Pending</button>
            <button type="button" class="btn btn-danger btn-block" id="snap-dummy-error">Gagal / tutup</button>
        </div>
    </div>
</div>
<script>
(function () {
    var payBtn = document.getElementById('pay-button');
    var overlay = document.getElementById('snap-dummy-overlay');
    var tokenOut = document.getElementById('snap-dummy-token');
    if (!payBtn || !overlay || !tokenOut) return;

    var callbacks = { onSuccess: function () {}, onPending: function () {}, onError: function () {} };

    window.snap = {
        pay: function (token, opts) {
            if (String(token).indexOf('DUMMY_') !== 0) {
                alert('Token tidak dikenali sebagai simulator.');
                return;
            }
            callbacks = opts || {};
            tokenOut.textContent = token;
            overlay.hidden = false;
        }
    };

    function closeOverlay() {
        overlay.hidden = true;
    }

    document.getElementById('snap-dummy-success').onclick = function () {
        closeOverlay();
        if (callbacks.onSuccess) callbacks.onSuccess();
    };
    document.getElementById('snap-dummy-pending').onclick = function () {
        closeOverlay();
        if (callbacks.onPending) callbacks.onPending();
    };
    document.getElementById('snap-dummy-error').onclick = function () {
        closeOverlay();
        if (callbacks.onError) callbacks.onError();
    };

    payBtn.onclick = function () {
        window.snap.pay('<?= htmlspecialchars($snapToken, ENT_QUOTES) ?>', {
            onSuccess: function () {
                alert('Pembayaran berhasil (simulator).');
                window.location = 'dashboard.php?paid=dummy_snap';
            },
            onPending: function () {
                alert('Status pending (simulator).');
            },
            onError: function () {
                alert('Pembayaran gagal atau dibatalkan (simulator).');
            }
        });
    };
})();
</script>
<?php } elseif ($metode === METODE_SNAP && $midtransReady && $snapToken !== '' && !$useDummy) { ?>
<script>
document.getElementById('pay-button').onclick = function () {
    snap.pay('<?= htmlspecialchars($snapToken, ENT_QUOTES) ?>', {
        onSuccess: function () {
            alert('Pembayaran berhasil!');
            window.location = 'dashboard.php?paid=snap';
        },
        onPending: function () {
            alert('Menunggu pembayaran.');
        },
        onError: function () {
            alert('Pembayaran gagal.');
        }
    });
};
</script>
<?php } ?>

</body>
</html>
