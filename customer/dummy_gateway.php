<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/pembayaran.php';

// ========================================
// Validasi ID Transaksi
// ========================================
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: dashboard.php');
    exit;
}

// ========================================
// Ambil Data Transaksi
// ========================================
$query = 'SELECT id, user_id, total_harga, metode_pembayaran, 
                 nama_pembeli, email_pembeli, telepon_pembeli, 
                 alamat_pengiriman, kota_pengiriman, kode_pos, 
                 catatan_pengiriman FROM transaksi WHERE id = ?';

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    header('Location: dashboard.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// ========================================
// Validasi Data & Otorisasi
// ========================================
if (!$data || (int) $data['user_id'] !== $userId) {
    header('Location: dashboard.php');
    exit;
}

$total = (int) $data['total_harga'];
$metode = $data['metode_pembayaran'] ?? METODE_DUMMY;

// Jika metode bukan dummy, arahkan ke halaman pembayaran normal
if ($metode !== METODE_DUMMY) {
    header('Location: bayar.php?id=' . $id);
    exit;
}

// ========================================
// Proses Form Simulasi Pembayaran
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? 'success';
    
    if ($status === 'success') {
        // Simulasi pembayaran berhasil
        $updateQuery = 'UPDATE transaksi SET status_pembayaran = "lunas", 
                        waktu_pembayaran = NOW() WHERE id = ?';
        $stmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header('Location: invoice.php?id=' . $id);
        exit;
    } else {
        // Simulasi pembayaran gagal
        header('Location: bayar.php?id=' . $id . '&error=dummy_failed');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulasi Payment - Toko Tanaman</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .payment-simulator {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

        .payment-simulator h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        .order-info {
            background-color: #fff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }

        .order-info p {
            margin: 8px 0;
            color: #555;
        }

        .order-info strong {
            color: #333;
        }

        .payment-options {
            margin: 25px 0;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
        }

        .payment-options p {
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
            font-style: italic;
        }

        .radio-group {
            margin: 15px 0;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            padding: 10px;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .radio-group label:hover {
            background-color: #f0f0f0;
        }

        .radio-group input[type="radio"] {
            margin-right: 10px;
            cursor: pointer;
        }

        .button-container {
            text-align: center;
            margin-top: 25px;
        }

        button[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        button[type="submit"]:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="payment-simulator">
        <h1>🔐 Simulasi Payment</h1>
        
        <div class="order-info">
            <p><strong>Order ID:</strong> #<?php echo htmlspecialchars($data['id']); ?></p>
            <p><strong>Total Pembayaran:</strong> Rp <?php echo number_format($total, 0, ',', '.'); ?></p>
        </div>

        <form method="post">
            <div class="payment-options">
                <p>⚠️ Ini adalah simulasi payment gateway untuk keperluan testing.</p>
                
                <div class="radio-group">
                    <label>
                        <input type="radio" name="status" value="success" checked>
                        <span>✓ Pembayaran Berhasil</span>
                    </label>
                </div>

                <div class="radio-group">
                    <label>
                        <input type="radio" name="status" value="failed">
                        <span>✗ Pembayaran Gagal</span>
                    </label>
                </div>
            </div>

            <div class="button-container">
                <button type="submit">Proses</button>
            </div>
        </form>
    </div>
</body>
</html>