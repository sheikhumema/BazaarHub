<?php
session_start();
require_once '../db.php';
require_once '../security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$message = "";

$stmt = mysqli_prepare($conn, "
    UPDATE orders
    SET status = 'delivered'
    WHERE user_id = ?
      AND status IN ('pending','completed')
      AND created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

if (isset($_POST['submit_review'])) {
    csrf_validate_or_fail();
    $product_id = (int) $_POST['product_id'];
    $rating = max(1, min(5, (int) $_POST['rating']));
    $comment = trim($_POST['comment'] ?? '');

    $stmt = mysqli_prepare($conn, "
        SELECT p.id
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ? AND p.id = ? AND o.status = 'delivered'
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
    mysqli_stmt_execute($stmt);
    $delivered_product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $stmt = mysqli_prepare($conn, "SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
    mysqli_stmt_execute($stmt);
    $existing_review = mysqli_stmt_get_result($stmt);

    if (!$delivered_product) {
        $message = "You can review this product after the order is delivered.";
    } elseif (mysqli_num_rows($existing_review) > 0) {
        $message = "You already reviewed this product.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiis", $user_id, $product_id, $rating, $comment);
        mysqli_stmt_execute($stmt);
        $message = "Review submitted.";
    }
}

$stmt = mysqli_prepare($conn, "
    SELECT DISTINCT p.id, p.name
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN reviews r ON r.user_id = o.user_id AND r.product_id = p.id
    WHERE o.user_id = ? AND o.status = 'delivered' AND r.id IS NULL
    ORDER BY p.name
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT p.name, r.rating, r.comment, r.created_at
    FROM reviews r
    JOIN products p ON p.id = r.product_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$completed_reviews = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - BazaarHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body class="customer-page">
<main class="shop-shell">
    <nav class="shop-nav" aria-label="Customer navigation">
        <a class="shop-brand" href="dashboard.php"><strong>BazaarHub</strong><span>Product reviews</span></a>
        <div class="shop-links">
            <a class="shop-link" href="products.php">Shop</a>
            <a class="shop-link" href="my_orders.php">Orders</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="shop-panel checkout-form">
        <p class="shop-kicker">Delivered items only</p>
        <h1 class="checkout-title">Rate your purchases</h1>
        <?php if ($message): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <?php if (mysqli_num_rows($products) === 0): ?>
            <div class="empty-state">No delivered products are waiting for a review. This means your orders are not delivered yet, or you already reviewed every delivered product.</div>
        <?php else: ?>
            <form class="checkout-form" method="POST">
                <?= csrf_input() ?>
                <label>Product
                    <select class="shop-select" name="product_id" required>
                        <option value="">Select a delivered product</option>
                        <?php while ($p = mysqli_fetch_assoc($products)): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </label>
                <label>Rating
                    <select class="shop-select" name="rating">
                        <option value="5">5 stars</option>
                        <option value="4">4 stars</option>
                        <option value="3">3 stars</option>
                        <option value="2">2 stars</option>
                        <option value="1">1 star</option>
                    </select>
                </label>
                <label>Review
                    <textarea class="shop-textarea" name="comment" placeholder="How did the product feel, fit, and look?"></textarea>
                </label>
                <button class="shop-button shop-button--primary" type="submit" name="submit_review">Submit Review</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="admin-section">
        <h2>Your Submitted Reviews</h2>
        <div class="admin-table">
            <?php if (mysqli_num_rows($completed_reviews) === 0): ?>
                <div class="empty-state">You have not submitted any reviews yet.</div>
            <?php endif; ?>
            <?php while ($review = mysqli_fetch_assoc($completed_reviews)): ?>
                <div class="admin-row">
                    <span><?= htmlspecialchars($review['name']) ?><small><?= htmlspecialchars($review['comment']) ?></small></span>
                    <strong><?= (int) $review['rating'] ?>/5</strong>
                    <span><?= htmlspecialchars($review['created_at']) ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
</main>
</body>
</html>
