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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    csrf_validate_or_fail();
    $product_id = (int) $_POST['product_id'];
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

    $stmt = mysqli_prepare($conn, "SELECT stock FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$product) {
        $message = "Product not found.";
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT c.id, c.quantity AS existing_quantity, p.stock
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.user_id = ? AND c.product_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $existing_quantity = (int) ($existing['existing_quantity'] ?? 0);
        $available_stock = (int) $product['stock'];
        $requested_total = $existing_quantity + $quantity;

        if ($requested_total > $available_stock) {
            $message = "Requested quantity is more than available stock.";
        } elseif ($existing) {
            $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "iii", $requested_total, $existing['id'], $user_id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $product_id, $quantity);
        }

        if (!isset($message) || $message === "") {
            mysqli_stmt_execute($stmt);
            $message = "Product added to cart.";
        }
    }
}

$search = trim($_GET['search'] ?? '');
$category_id = (int) ($_GET['category_id'] ?? 0);
$categories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$hero_image = "../assets/images/products/blue_bow_blouse.jfif";

$sql = "
    SELECT p.id, p.name, p.description, p.price, p.stock, p.image_url,
           c.name AS category_name, u.name AS seller_name,
           COALESCE(AVG(r.rating), 0) AS avg_rating
    FROM products p
    JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = p.seller_id
    LEFT JOIN reviews r ON r.product_id = p.id
    WHERE (? = '' OR p.name LIKE CONCAT('%', ?, '%'))
      AND (? = 0 OR p.category_id = ?)
    GROUP BY p.id, p.name, p.description, p.price, p.stock, p.image_url, c.name, u.name
    ORDER BY p.created_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssii", $search, $search, $category_id, $category_id);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clothing Collection - BazaarHub</title>
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
            <a class="shop-link" href="cart.php">Cart</a>
            <a class="shop-link" href="my_orders.php">Orders</a>
            <a class="shop-link" href="reviews.php">Reviews</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="shop-hero">
        <div class="shop-hero__copy">
            <p class="shop-kicker">New season edit</p>
            <h1 class="shop-title">Scroll the closet. Pick your fit.</h1>
            <p class="shop-copy">
                Browse handpicked everyday pieces from soft blouses and denim staples to jackets,
                dresses, skirts, and weekend shorts. Add your size-ready picks to cart in one tap.
            </p>
        </div>
        <div class="shop-hero__media">
            <img src="<?= htmlspecialchars($hero_image) ?>" alt="BazaarHub clothing collection">
        </div>
    </section>

    <?php if ($message): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <section class="shop-panel" aria-label="Product filters">
        <form class="shop-toolbar" method="GET">
            <input class="shop-input" type="text" name="search" placeholder="Search blouses, jeans, jackets..." value="<?= htmlspecialchars($search) ?>">
            <select class="shop-select" name="category_id">
                <option value="0">All categories</option>
                <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                    <option value="<?= $category['id'] ?>" <?= $category_id === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button class="shop-button shop-button--primary" type="submit">Search</button>
        </form>
    </section>

    <section class="product-grid" aria-label="Products">
        <?php if (mysqli_num_rows($products) === 0): ?>
            <div class="empty-state">No products matched your search.</div>
        <?php endif; ?>

        <?php while ($product = mysqli_fetch_assoc($products)): ?>
            <?php
                $image_url = $product['image_url'] ?: 'assets/images/products/blue_bow_blouse.jfif';
                $image_src = '../' . ltrim($image_url, '/');
            ?>
            <article class="product-card">
                <div class="product-card__image">
                    <img src="<?= htmlspecialchars($image_src) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <span class="product-chip"><?= htmlspecialchars($product['category_name']) ?></span>
                </div>
                <div class="product-card__body">
                    <div class="product-card__meta">
                        <span><?= htmlspecialchars($product['seller_name']) ?></span>
                        <span><?= number_format($product['avg_rating'], 1) ?>/5</span>
                    </div>
                    <h2><?= htmlspecialchars($product['name']) ?></h2>
                    <p class="product-desc"><?= htmlspecialchars($product['description'] ?? '') ?></p>
                    <div class="product-card__footer">
                        <div class="product-price">
                            <strong>$<?= number_format($product['price'], 2) ?></strong>
                            <span class="product-stock"><?= (int) $product['stock'] ?> in stock</span>
                        </div>
                        <?php if ((int) $product['stock'] > 0): ?>
                            <form class="cart-form" method="POST">
                                <?= csrf_input() ?>
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <input class="quantity-input" type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>" aria-label="Quantity for <?= htmlspecialchars($product['name']) ?>">
                                <button class="shop-button shop-button--primary" type="submit">Add to Cart</button>
                            </form>
                        <?php else: ?>
                            <div class="empty-state">Out of stock</div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </section>
</main>
</body>
</html>
