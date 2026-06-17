<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - BazaarHub</title>
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
            <a class="shop-link" href="products.php">Shop</a>
            <a class="shop-link" href="cart.php">Cart</a>
            <a class="shop-link" href="my_orders.php">Orders</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="shop-hero">
        <div class="shop-hero__copy">
            <p class="shop-kicker">Welcome back</p>
            <h1 class="shop-title">Hi, <?= htmlspecialchars($_SESSION['name']); ?>.</h1>
            <p class="shop-copy">
                Your BazaarHub closet is ready. Browse the latest clothing picks, build your cart,
                check out, and track every order from one place.
            </p>
        </div>
        <div class="shop-hero__media">
            <img src="../assets/images/products/brown_leather_jacket.jfif" alt="Featured BazaarHub jacket">
        </div>
    </section>

    <section class="dashboard-grid" aria-label="Customer actions">
        <a class="dashboard-card" href="products.php">
            <span>01</span>
            <h2>Browse Products</h2>
            <p>Scroll the full clothing catalog with photos, prices, stock, and ratings.</p>
        </a>
        <a class="dashboard-card" href="cart.php">
            <span>02</span>
            <h2>View Cart</h2>
            <p>Update quantities, remove items, and place your order securely.</p>
        </a>
        <a class="dashboard-card" href="my_orders.php">
            <span>03</span>
            <h2>Track Orders</h2>
            <p>Review order status, payment records, invoice numbers, and item details.</p>
        </a>
        <a class="dashboard-card" href="reviews.php">
            <span>04</span>
            <h2>Write Reviews</h2>
            <p>Rate purchased products and help future customers pick the right fit.</p>
        </a>
    </section>
</main>
</body>
</html>
