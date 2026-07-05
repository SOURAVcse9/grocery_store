<?php
/**
 * ==========================================================================
 * public/payment-gateway.php — Mock Payment Gateway Portal
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$orderId = (int) input('order_id', '0', 'get');

if ($orderId <= 0) {
    echo "Invalid Order ID.";
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo "Order details not found.";
        exit;
    }
} catch (PDOException $e) {
    error_log('[payment-gateway.php] failed: ' . $e->getMessage());
    echo "Database error loading checkout details.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secured Payment Gateway Portal — GroCo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #5c7cfa;
            --color-bg: #f8f9fa;
            --color-text: #212529;
            --color-border: #dee2e6;
            --radius-md: 12px;
            --radius-pill: 9999px;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 480px;
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 30px;
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .price { font-size: 24px; font-weight: 800; color: var(--color-primary); margin: 10px 0; }
        .label { font-size: 12px; color: #868e96; text-transform: uppercase; font-weight: 700; }
        .line { border-top: 1px dashed var(--color-border); margin: 20px 0; }
        .gateway-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .gateway-box {
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
        }
        .gateway-box.active {
            border-color: var(--color-primary);
            background: rgba(92,124,250,0.05);
            color: var(--color-primary);
        }
        .btn {
            display: block;
            width: 100%;
            border: none;
            border-radius: var(--radius-pill);
            padding: 12px;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 10px;
            text-decoration: none;
        }
        .btn-success { background: #0ca678; color: #fff; }
        .btn-danger { background: #f03e3e; color: #fff; }
        .btn-secondary { background: #868e96; color: #fff; }
    </style>
</head>
<body>

    <div class="container text-center">
        <h2 style="font-size: 18px; font-weight: 800; margin: 0 0 10px 0;">SECURED GATEWAY SIMULATOR</h2>
        <span class="label">Total Payable Due Amount</span>
        <div class="price">৳<?= number_format((float)$order['total_amount'], 2) ?></div>
        <p style="font-size: 13px; color: #495057;">Order Reference: <strong>#<?= e($order['order_number']) ?></strong></p>
        
        <div class="line"></div>

        <span class="label" style="display: block; margin-bottom: 12px;">Choose Gateway Provider</span>
        <div class="gateway-selector">
            <div class="gateway-box active" onclick="selectGateway('bkash', this);">bKash</div>
            <div class="gateway-box" onclick="selectGateway('nagad', this);">Nagad</div>
            <div class="gateway-box" onclick="selectGateway('sslcommerz', this);">SSLCommerz</div>
        </div>

        <div class="line"></div>

        <form id="paymentForm" action="payment-callback.php" method="get">
            <input type="hidden" name="order_id" value="<?= $orderId ?>">
            <input type="hidden" id="selectedGateway" name="gateway" value="bkash">
            <input type="hidden" name="status" id="paymentStatus" value="success">
            
            <button type="submit" onclick="setPaymentStatus('success');" class="btn btn-success">Simulate Payment Success</button>
            <button type="submit" onclick="setPaymentStatus('failed');" class="btn btn-danger">Simulate Payment Failure</button>
            <a href="payment-callback.php?status=cancelled&order_id=<?= $orderId ?>" class="btn btn-secondary">Cancel and Return</a>
        </form>
    </div>

    <script>
    function selectGateway(name, el) {
        document.querySelectorAll('.gateway-box').forEach(box => {
            box.classList.remove('active');
        });
        el.classList.add('active');
        document.getElementById('selectedGateway').value = name;
    }
    function setPaymentStatus(status) {
        document.getElementById('paymentStatus').value = status;
    }
    </script>

</body>
</html>
