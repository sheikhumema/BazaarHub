<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../login.php");
    exit();
}

$seller_id = (int) $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "
    SELECT p.*, c.name AS category_name, COALESCE(AVG(r.rating), 0) AS avg_rating
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN reviews r ON r.product_id = p.id
    WHERE p.seller_id = ?
    GROUP BY p.id, p.name, p.description, p.image_url, p.price, p.stock, p.category_id, p.seller_id, p.created_at, c.name
    ORDER BY p.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $seller_id);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Products - BazaarHub</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body class="customer-page">
<main class="shop-shell">
    <nav class="shop-nav">
        <a class="shop-brand" href="dashboard.php"><strong>BazaarHub</strong><span>Seller studio</span></a>
        <div class="shop-links">
            <a class="shop-link" href="dashboard.php">Dashboard</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="checkout-layout">
        <section class="checkout-summary" style="grid-column: 1 / -1;">
            <p class="shop-kicker">Seller catalog</p>
            <h1 class="checkout-title">Your products</h1>
            <p class="muted-copy">This page shows only the products created by your seller account.</p>
        </section>
    </section>

    <section class="product-grid">
        <?php if (mysqli_num_rows($products) === 0): ?>
            <div class="empty-state">No products have been added by your seller account yet.</div>
        <?php endif; ?>
        <?php while ($product = mysqli_fetch_assoc($products)): ?>
            <article class="product-card">
                <div class="product-card__image">
                    <img src="../<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <span class="product-chip"><?= htmlspecialchars($product['category_name']) ?></span>
                </div>
                <div class="product-card__body">
                    <div class="product-card__meta"><span><?= (int) $product['stock'] ?> in stock</span><span><?= number_format($product['avg_rating'], 1) ?>/5</span></div>
                    <h2><?= htmlspecialchars($product['name']) ?></h2>
                    <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>
                    <div class="product-card__footer">
                        <div class="product-price"><strong>$<?= number_format($product['price'], 2) ?></strong></div>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </section>
</main>
</body>
</html>
