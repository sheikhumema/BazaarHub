<?php
session_start();
require_once '../db.php';
require_once '../security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    try {
        if (isset($_POST['update_user'])) {
            $user_id = (int) $_POST['user_id'];
            $status = in_array($_POST['account_status'], ['pending', 'active', 'suspended'], true) ? $_POST['account_status'] : 'active';
            $role = in_array($_POST['role'], ['admin', 'seller', 'customer'], true) ? $_POST['role'] : 'customer';
            $stmt = mysqli_prepare($conn, "UPDATE users SET role = ?, account_status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $role, $status, $user_id);
            mysqli_stmt_execute($stmt);
            $message = "User updated.";
        }

        if (isset($_POST['add_category'])) {
            $name = trim($_POST['category_name'] ?? '');
            if ($name !== '') {
                $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO categories (name) VALUES (?)");
                mysqli_stmt_bind_param($stmt, "s", $name);
                mysqli_stmt_execute($stmt);
                $message = "Category saved.";
            }
        }

        if (isset($_POST['delete_category'])) {
            $category_id = (int) $_POST['category_id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $category_id);
            mysqli_stmt_execute($stmt);
            $message = "Category deleted if no products depended on it.";
        }

        if (isset($_POST['delete_product'])) {
            $product_id = (int) $_POST['product_id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $message = "Product deleted.";
        }

        if (isset($_POST['update_order'])) {
            $order_id = (int) $_POST['order_id'];
            $status = in_array($_POST['status'], ['pending', 'completed', 'delivered', 'cancelled'], true) ? $_POST['status'] : 'pending';
            $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $status, $order_id);
            mysqli_stmt_execute($stmt);
            $message = "Order updated.";
        }

        if (isset($_POST['delete_review'])) {
            $review_id = (int) $_POST['review_id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM reviews WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $review_id);
            mysqli_stmt_execute($stmt);
            $message = "Review deleted.";
        }
    } catch (Throwable $e) {
        $message = "Unable to process that action right now.";
    }
}

mysqli_query($conn, "UPDATE orders SET status = 'delivered' WHERE status IN ('pending','completed') AND created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");

$users = mysqli_query($conn, "SELECT id, name, email, role, account_status, created_at FROM users ORDER BY created_at DESC");
$categories = mysqli_query($conn, "SELECT c.id, c.name, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name ORDER BY c.name");
$products = mysqli_query($conn, "SELECT p.id, p.name, p.price, p.stock, p.image_url, c.name AS category_name, u.name AS seller_name FROM products p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.seller_id ORDER BY p.created_at DESC");
$orders = mysqli_query($conn, "
    SELECT o.id, o.status, o.created_at, u.name AS customer_name,
           COALESCE(ot.total_amount, 0) AS total_amount
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN order_totals_view ot ON ot.order_id = o.id
    ORDER BY o.created_at DESC
");
$reviews = mysqli_query($conn, "SELECT r.id, r.rating, r.comment, r.created_at, u.name AS customer_name, p.name AS product_name FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id ORDER BY r.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BazaarHub</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body class="customer-page">
<main class="shop-shell">
    <nav class="shop-nav">
        <a class="shop-brand" href="dashboard.php"><strong>BazaarHub</strong><span>Admin control room</span></a>
        <div class="shop-links">
            <a class="shop-link" href="reports.php">Reports</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="shop-hero">
        <div class="shop-hero__copy">
            <p class="shop-kicker">Administrator</p>
            <h1 class="shop-title">Manage the whole store.</h1>
            <p class="shop-copy">Review users, approve or suspend sellers, manage products, categories, orders, reviews, and reports.</p>
        </div>
        <div class="shop-hero__media"><img src="../assets/images/products/brown_patterned_skirt.jfif" alt="Admin dashboard feature"></div>
    </section>

    <?php if ($message): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <section class="admin-section">
        <h2>Users</h2>
        <div class="admin-table">
            <?php while ($user = mysqli_fetch_assoc($users)): ?>
                <form class="admin-row" method="POST">
                    <?= csrf_input() ?>
                    <span><?= htmlspecialchars($user['name']) ?><small><?= htmlspecialchars($user['email']) ?></small></span>
                    <select class="shop-select" name="role">
                        <?php foreach (['admin', 'seller', 'customer'] as $role): ?>
                            <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="shop-select" name="account_status">
                        <?php foreach (['pending', 'active', 'suspended'] as $status): ?>
                            <option value="<?= $status ?>" <?= ($user['account_status'] ?? 'active') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <button class="shop-button" name="update_user">Save</button>
                </form>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="admin-section">
        <h2>Categories</h2>
        <form class="shop-toolbar" method="POST">
            <?= csrf_input() ?>
            <input class="shop-input" name="category_name" placeholder="New category name">
            <button class="shop-button shop-button--primary" name="add_category">Add Category</button>
        </form>
        <div class="admin-table">
            <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                <form class="admin-row" method="POST">
                    <?= csrf_input() ?>
                    <span><?= htmlspecialchars($category['name']) ?><small><?= (int) $category['product_count'] ?> products</small></span>
                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                    <button class="shop-button" name="delete_category">Delete</button>
                </form>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="admin-section">
        <h2>Products</h2>
        <div class="admin-table">
            <?php while ($product = mysqli_fetch_assoc($products)): ?>
                <form class="admin-row" method="POST">
                    <?= csrf_input() ?>
                    <span><?= htmlspecialchars($product['name']) ?><small><?= htmlspecialchars($product['seller_name']) ?> / <?= htmlspecialchars($product['category_name']) ?></small></span>
                    <strong>$<?= number_format($product['price'], 2) ?></strong>
                    <span><?= (int) $product['stock'] ?> stock</span>
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <button class="shop-button" name="delete_product">Delete</button>
                </form>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="admin-section">
        <h2>Orders</h2>
        <div class="admin-table">
            <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                <form class="admin-row" method="POST">
                    <?= csrf_input() ?>
                    <span>Order #<?= (int) $order['id'] ?><small><?= htmlspecialchars($order['customer_name']) ?> / $<?= number_format($order['total_amount'], 2) ?></small></span>
                    <select class="shop-select" name="status">
                        <?php foreach (['pending', 'completed', 'delivered', 'cancelled'] as $status): ?>
                            <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button class="shop-button" name="update_order">Save</button>
                </form>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="admin-section">
        <h2>Reviews</h2>
        <div class="admin-table">
            <?php while ($review = mysqli_fetch_assoc($reviews)): ?>
                <form class="admin-row" method="POST">
                    <?= csrf_input() ?>
                    <span><?= htmlspecialchars($review['product_name']) ?><small><?= (int) $review['rating'] ?>/5 by <?= htmlspecialchars($review['customer_name']) ?> - <?= htmlspecialchars($review['comment']) ?></small></span>
                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                    <button class="shop-button" name="delete_review">Delete</button>
                </form>
            <?php endwhile; ?>
        </div>
    </section>
</main>
</body>
</html>
