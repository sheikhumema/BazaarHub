<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "
    UPDATE orders
    SET status = 'delivered'
    WHERE user_id = ?
      AND status IN ('pending','completed')
      AND created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT o.id,
           COALESCE(ot.subtotal, 0) AS subtotal,
           COALESCE(ot.delivery_fee, 10.00) AS delivery_fee,
           COALESCE(ot.tax_amount, 0) AS tax_amount,
           COALESCE(ot.total_amount, 0) AS total_amount,
           o.status,
           p.payment_method,
           p.card_last4,
           o.created_at,
           p.payment_status,
           i.invoice_number
    FROM orders o
    LEFT JOIN order_totals_view ot ON ot.order_id = o.id
    LEFT JOIN payments p ON p.order_id = o.id
    LEFT JOIN invoices i ON i.order_id = o.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - BazaarHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body class="customer-page">
<main class="shop-shell">
    <nav class="shop-nav" aria-label="Customer navigation">
        <a class="shop-brand" href="dashboard.php">
            <strong>BazaarHub</strong>
            <span>Clothing marketplace</span>
        </a>
        <div class="shop-links">
            <a class="shop-link" href="dashboard.php">Dashboard</a>
            <a class="shop-link" href="products.php">Shop</a>
            <a class="shop-link" href="cart.php">Cart</a>
            <a class="shop-link" href="reviews.php">Reviews</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="shop-hero">
        <div class="shop-hero__copy">
            <p class="shop-kicker">Order history</p>
            <h1 class="shop-title">Track every BazaarHub fit.</h1>
            <p class="shop-copy">See order status, payment records, invoice numbers, and the clothing pieces included in each checkout.</p>
        </div>
        <div class="shop-hero__media">
            <img src="../assets/images/products/pink_buttondown_shirt.jfif" alt="Pink button-down shirt">
        </div>
    </section>

    <?php if (isset($_GET['placed'])): ?>
        <div class="notice">Order placed successfully. Payment and invoice were created.</div>
    <?php endif; ?>

    <section class="orders-stack">
<?php if (mysqli_num_rows($orders) === 0): ?>
    <div class="empty-state">No orders yet. <a href="products.php">Start shopping</a>.</div>
<?php endif; ?>

<?php while ($order = mysqli_fetch_assoc($orders)): ?>
    <article class="order-card">
        <div class="order-card__head">
            <div>
                <p class="shop-kicker">Order #<?= (int) $order['id'] ?></p>
                <h2><?= $order['invoice_number'] ? 'Invoice ' . htmlspecialchars($order['invoice_number']) : 'Invoice pending' ?></h2>
            </div>
            <strong>$<?= number_format($order['total_amount'], 2) ?></strong>
        </div>
        <p class="muted-copy">
            Status: <?= htmlspecialchars($order['status']) ?> |
            Payment: <?= htmlspecialchars($order['payment_method']) ?> / <?= htmlspecialchars($order['payment_status'] ?? 'pending') ?> |
            Date: <?= htmlspecialchars($order['created_at']) ?>
        </p>
        <p class="muted-copy">
            Subtotal $<?= number_format($order['subtotal'], 2) ?> |
            Delivery $<?= number_format($order['delivery_fee'], 2) ?> |
            Tax $<?= number_format($order['tax_amount'], 2) ?>
        </p>

    <?php
    $order_id = (int) $order['id'];
    $items = mysqli_query($conn, "
        SELECT p.name, oi.quantity, oi.price
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = $order_id
    ");
    ?>
    <ul class="order-items">
        <?php while ($item = mysqli_fetch_assoc($items)): ?>
            <li>
                <?= htmlspecialchars($item['name']) ?> -
                Qty <?= (int) $item['quantity'] ?> -
                $<?= number_format($item['price'], 2) ?>
            </li>
        <?php endwhile; ?>
    </ul>
    </article>
<?php endwhile; ?>
    </section>
</main>
</body>
</html>
