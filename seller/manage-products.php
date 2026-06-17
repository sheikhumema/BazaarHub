<?php
session_start();
require_once '../db.php';
require_once '../security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../login.php");
    exit();
}

$seller_id = (int) $_SESSION['user_id'];
$message = '';
$editing_product = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();

    try {
        if (isset($_POST['add_product'])) {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $image_url = trim($_POST['image_url'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $stock = (int) ($_POST['stock'] ?? 0);
            $category_id = (int) ($_POST['category_id'] ?? 0);

            if ($name === '' || $description === '' || $image_url === '' || $price <= 0 || $stock < 0 || $category_id <= 0) {
                throw new RuntimeException('Please fill in all product fields correctly.');
            }

            $stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $category_id);
            mysqli_stmt_execute($stmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                throw new RuntimeException('Selected category does not exist.');
            }

            $stmt = mysqli_prepare($conn, "INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssdiii", $name, $description, $image_url, $price, $stock, $category_id, $seller_id);
            mysqli_stmt_execute($stmt);
            $message = 'Product added successfully.';
        }

        if (isset($_POST['update_product'])) {
            $product_id = (int) $_POST['product_id'];
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $image_url = trim($_POST['image_url'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $stock = (int) ($_POST['stock'] ?? 0);
            $category_id = (int) ($_POST['category_id'] ?? 0);

            $stmt = mysqli_prepare($conn, "SELECT id FROM products WHERE id = ? AND seller_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $product_id, $seller_id);
            mysqli_stmt_execute($stmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                throw new RuntimeException('Product not found.');
            }

            if ($name === '' || $description === '' || $image_url === '' || $price <= 0 || $stock < 0 || $category_id <= 0) {
                throw new RuntimeException('Please fill in all product fields correctly.');
            }

            $stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $category_id);
            mysqli_stmt_execute($stmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                throw new RuntimeException('Selected category does not exist.');
            }

            $stmt = mysqli_prepare($conn, "UPDATE products SET name = ?, description = ?, image_url = ?, price = ?, stock = ?, category_id = ? WHERE id = ? AND seller_id = ?");
            mysqli_stmt_bind_param($stmt, "sssdiiii", $name, $description, $image_url, $price, $stock, $category_id, $product_id, $seller_id);
            mysqli_stmt_execute($stmt);
            $message = 'Product updated successfully.';
        }

        if (isset($_POST['delete_product'])) {
            $product_id = (int) $_POST['product_id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ? AND seller_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $product_id, $seller_id);
            mysqli_stmt_execute($stmt);
            $message = 'Product deleted successfully.';
        }
    } catch (Throwable $e) {
        $message = $e->getMessage() ?: 'Unable to process that action right now.';
    }
}

if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ? AND seller_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $edit_id, $seller_id);
    mysqli_stmt_execute($stmt);
    $editing_product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

$categories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$products_stmt = mysqli_prepare($conn, "
    SELECT p.id, p.name, p.description, p.image_url, p.price, p.stock, p.category_id, c.name AS category_name,
           COALESCE(AVG(r.rating), 0) AS avg_rating
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN reviews r ON r.product_id = p.id
    WHERE p.seller_id = ?
    GROUP BY p.id, p.name, p.description, p.image_url, p.price, p.stock, p.category_id, c.name
    ORDER BY p.created_at DESC
");
mysqli_stmt_bind_param($products_stmt, "i", $seller_id);
mysqli_stmt_execute($products_stmt);
$products = mysqli_stmt_get_result($products_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - BazaarHub</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body class="customer-page">
<main class="shop-shell">
    <nav class="shop-nav" aria-label="Seller navigation">
        <a class="shop-brand" href="dashboard.php">
            <strong>BazaarHub</strong>
            <span>Seller studio</span>
        </a>
        <div class="shop-links">
            <a class="shop-link" href="dashboard.php">Dashboard</a>
            <a class="shop-link" href="products.php">Store Catalog</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="shop-hero">
        <div class="shop-hero__copy">
            <p class="shop-kicker">Product management</p>
            <h1 class="shop-title">Manage your own products.</h1>
            <p class="shop-copy">Add new listings, edit existing items, or remove products from your seller account. The store catalog remains read-only.</p>
        </div>
        <div class="shop-hero__media">
            <img src="../assets/images/products/brown_leather_jacket.jfif" alt="Seller product management">
        </div>
    </section>

    <?php if ($message): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <section class="admin-section">
        <h2><?= $editing_product ? 'Edit Product' : 'Add Product' ?></h2>
        <form class="shop-toolbar" method="POST">
            <?= csrf_input() ?>
            <?php if ($editing_product): ?>
                <input type="hidden" name="product_id" value="<?= (int) $editing_product['id'] ?>">
            <?php endif; ?>
            <input class="shop-input" name="name" placeholder="Product name" value="<?= htmlspecialchars($editing_product['name'] ?? '') ?>" required>
            <input class="shop-input" name="image_url" placeholder="Image URL" value="<?= htmlspecialchars($editing_product['image_url'] ?? '') ?>" required>
            <select class="shop-select" name="category_id" required>
                <option value="">Select category</option>
                <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= isset($editing_product['category_id']) && (int) $editing_product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <input class="shop-input" name="price" type="number" step="0.01" min="0.01" placeholder="Price" value="<?= htmlspecialchars($editing_product['price'] ?? '') ?>" required>
            <input class="shop-input" name="stock" type="number" step="1" min="0" placeholder="Stock" value="<?= htmlspecialchars($editing_product['stock'] ?? '') ?>" required>
            <textarea class="shop-input" name="description" placeholder="Product description" style="min-height: 8rem; padding-top: 1rem;" required><?= htmlspecialchars($editing_product['description'] ?? '') ?></textarea>
            <button class="shop-button shop-button--primary" name="<?= $editing_product ? 'update_product' : 'add_product' ?>">
                <?= $editing_product ? 'Update Product' : 'Add Product' ?>
            </button>
            <?php if ($editing_product): ?>
                <a class="shop-button" href="manage-products.php">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="admin-section">
        <h2>Your Products</h2>
        <div class="admin-table">
            <?php if (mysqli_num_rows($products) === 0): ?>
                <div class="empty-state">No products have been added by your seller account yet.</div>
            <?php endif; ?>
            <?php while ($product = mysqli_fetch_assoc($products)): ?>
                <form class="admin-row" method="POST">
                    <?= csrf_input() ?>
                    <span>
                        <?= htmlspecialchars($product['name']) ?>
                        <small><?= htmlspecialchars($product['category_name']) ?> / <?= (int) $product['stock'] ?> in stock / <?= number_format($product['avg_rating'], 1) ?>/5</small>
                    </span>
                    <strong>$<?= number_format($product['price'], 2) ?></strong>
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <a class="shop-button" href="manage-products.php?edit=<?= (int) $product['id'] ?>">Edit</a>
                    <button class="shop-button" name="delete_product" onclick="return confirm('Delete this product?')">Delete</button>
                </form>
            <?php endwhile; ?>
        </div>
    </section>
</main>
</body>
</html>
