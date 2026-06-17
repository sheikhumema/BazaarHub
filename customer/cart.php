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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    if (isset($_POST['update_cart'])) {
        $cart_id = (int) $_POST['cart_id'];
        $quantity = max(1, (int) $_POST['quantity']);
        $stmt = mysqli_prepare($conn, "
            SELECT c.id, c.product_id, p.stock
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.id = ? AND c.user_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
        mysqli_stmt_execute($stmt);
        $cart_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$cart_item) {
            $message = "Cart item not found.";
        } elseif ($quantity > (int) $cart_item['stock']) {
            $message = "Requested quantity is more than available stock.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "iii", $quantity, $cart_id, $user_id);
            mysqli_stmt_execute($stmt);
            $message = "Cart updated.";
        }
    }

    if (isset($_POST['remove_cart'])) {
        $cart_id = (int) $_POST['cart_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
        mysqli_stmt_execute($stmt);
        $message = "Item removed.";
    }

}

$stmt = mysqli_prepare($conn, "
    SELECT c.id, c.quantity, p.name, p.price, p.stock, p.image_url
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = ?
    ORDER BY c.id DESC
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$cart_items = mysqli_stmt_get_result($stmt);
$total = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - BazaarHub</title>
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
            <a class="shop-link" href="my_orders.php">Orders</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="shop-hero">
        <div class="shop-hero__copy">
            <p class="shop-kicker">Checkout bag</p>
            <h1 class="shop-title">Your cart is almost runway-ready.</h1>
            <p class="shop-copy">Review quantities, remove pieces you changed your mind about, and place the order when the outfit feels right.</p>
        </div>
        <div class="shop-hero__media">
            <img src="../assets/images/products/white_dress.jfif" alt="White dress product">
        </div>
    </section>

    <?php if ($message): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <section class="shop-panel">
        <div class="cart-list">
            <?php while ($item = mysqli_fetch_assoc($cart_items)): ?>
                <?php
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                    $image_src = '../' . ltrim($item['image_url'] ?: 'assets/images/products/blue_bow_blouse.jfif', '/');
                ?>
                <article class="cart-row">
                    <img src="<?= htmlspecialchars($image_src) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <div>
                        <h2><?= htmlspecialchars($item['name']) ?></h2>
                        <p>$<?= number_format($item['price'], 2) ?> each</p>
                    </div>
                    <form method="POST" class="cart-row__actions">
                        <?= csrf_input() ?>
                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                        <input class="quantity-input" type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['stock'] ?>">
                        <button class="shop-button" type="submit" name="update_cart">Update</button>
                    </form>
                    <strong>$<?= number_format($subtotal, 2) ?></strong>
                    <form method="POST">
                        <?= csrf_input() ?>
                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                        <button class="shop-button" type="submit" name="remove_cart">Remove</button>
                    </form>
                </article>
            <?php endwhile; ?>

            <?php if ($total <= 0): ?>
                <div class="empty-state">Your cart is empty. <a href="products.php">Browse products</a>.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="cart-summary">
        <div>
            <p class="shop-kicker">Order total</p>
            <h2>$<?= number_format($total, 2) ?></h2>
        </div>
        <?php if ($total > 0): ?>
            <a class="shop-button shop-button--primary" href="checkout.php">Go to Checkout</a>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
