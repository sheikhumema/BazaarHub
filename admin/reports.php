<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

mysqli_query($conn, "UPDATE orders SET status = 'delivered' WHERE status IN ('pending','completed') AND created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");

$summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total_orders,
           COALESCE(SUM(ot.total_amount), 0) AS revenue,
           COALESCE(AVG(ot.total_amount), 0) AS avg_order
    FROM orders o
    JOIN order_totals_view ot ON ot.order_id = o.id
    WHERE o.status <> 'cancelled'
"));

$top_products = mysqli_query($conn, "
    SELECT p.name, SUM(oi.quantity) AS units_sold, SUM(oi.quantity * oi.price) AS revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status <> 'cancelled'
    GROUP BY p.id, p.name
    ORDER BY units_sold DESC
    LIMIT 10
");

$seller_sales = mysqli_query($conn, "
    SELECT u.name, COUNT(DISTINCT o.id) AS orders_count, COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue
    FROM users u
    LEFT JOIN products p ON p.seller_id = u.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id AND o.status <> 'cancelled'
    WHERE u.role = 'seller'
    GROUP BY u.id, u.name
    ORDER BY revenue DESC
");

$category_sales = mysqli_query($conn, "
    SELECT c.name, COUNT(p.id) AS product_count, COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id AND o.status <> 'cancelled'
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - BazaarHub</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body class="customer-page">
<main class="shop-shell">
    <nav class="shop-nav">
        <a class="shop-brand" href="dashboard.php"><strong>BazaarHub</strong><span>Sales reports</span></a>
        <div class="shop-links">
            <a class="shop-link" href="dashboard.php">Admin</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="dashboard-grid">
        <div class="dashboard-card"><span>Orders</span><h2><?= (int) $summary['total_orders'] ?></h2><p>Total non-cancelled orders.</p></div>
        <div class="dashboard-card"><span>Revenue</span><h2>$<?= number_format($summary['revenue'], 2) ?></h2><p>Gross order revenue.</p></div>
        <div class="dashboard-card"><span>Average</span><h2>$<?= number_format($summary['avg_order'], 2) ?></h2><p>Average order value.</p></div>
    </section>

    <section class="admin-section">
        <h2>Top Products</h2>
        <div class="admin-table">
            <?php while ($row = mysqli_fetch_assoc($top_products)): ?>
                <div class="admin-row"><span><?= htmlspecialchars($row['name']) ?><small><?= (int) $row['units_sold'] ?> units sold</small></span><strong>$<?= number_format($row['revenue'], 2) ?></strong></div>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="admin-section">
        <h2>Seller Revenue</h2>
        <div class="admin-table">
            <?php while ($row = mysqli_fetch_assoc($seller_sales)): ?>
                <div class="admin-row"><span><?= htmlspecialchars($row['name']) ?><small><?= (int) $row['orders_count'] ?> orders</small></span><strong>$<?= number_format($row['revenue'], 2) ?></strong></div>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="admin-section">
        <h2>Category Revenue</h2>
        <div class="admin-table">
            <?php while ($row = mysqli_fetch_assoc($category_sales)): ?>
                <div class="admin-row"><span><?= htmlspecialchars($row['name']) ?><small><?= (int) $row['product_count'] ?> products</small></span><strong>$<?= number_format($row['revenue'], 2) ?></strong></div>
            <?php endwhile; ?>
        </div>
    </section>
</main>
</body>
</html>
