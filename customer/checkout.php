<?php
session_start();
require_once '../db.php';
require_once '../security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$delivery_fee = 10.00;
$tax_rate = 0.05;
$message = "";

$stmt = mysqli_prepare($conn, "
    SELECT c.product_id, c.quantity, p.name, p.price, p.stock, p.image_url
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = ?
    ORDER BY c.id DESC
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$cart_result = mysqli_stmt_get_result($stmt);

$items = [];
$subtotal = 0;
while ($item = mysqli_fetch_assoc($cart_result)) {
    $item['line_total'] = (float) $item['price'] * (int) $item['quantity'];
    $subtotal += $item['line_total'];
    $items[] = $item;
}

if (!$items) {
    header("Location: cart.php");
    exit();
}

$tax_amount = round($subtotal * $tax_rate, 2);
$total = $subtotal + $delivery_fee + $tax_amount;

$address_stmt = mysqli_prepare($conn, "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC LIMIT 1");
mysqli_stmt_bind_param($address_stmt, "i", $user_id);
mysqli_stmt_execute($address_stmt);
$saved_address = mysqli_fetch_assoc(mysqli_stmt_get_result($address_stmt));

$customer_name = $_SESSION['name'];
$phone = $saved_address['phone'] ?? '';
$city = $saved_address['city'] ?? '';
$address = $saved_address['address'] ?? '';
$payment_method = 'cash';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = ($_POST['payment_method'] ?? 'cash') === 'card' ? 'card' : 'cash';
    $card_number = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
    $card_last4 = $payment_method === 'card' ? substr($card_number, -4) : null;

    if ($phone === '' || $city === '' || $address === '') {
        $message = "Please enter your complete delivery address.";
    } elseif (!validate_phone_number($phone)) {
        $message = "Please enter a valid phone number.";
    } elseif ($payment_method === 'card' && !validate_card_number($card_number)) {
        $message = "Please enter a valid card number or choose cash.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $lock_stmt = mysqli_prepare($conn, "
                SELECT c.product_id, c.quantity, p.price, p.stock
                FROM cart c
                JOIN products p ON p.id = c.product_id
                WHERE c.user_id = ?
                FOR UPDATE
            ");
            mysqli_stmt_bind_param($lock_stmt, "i", $user_id);
            mysqli_stmt_execute($lock_stmt);
            $locked = mysqli_stmt_get_result($lock_stmt);

            $locked_items = [];
            $locked_subtotal = 0;
            while ($item = mysqli_fetch_assoc($locked)) {
                if ((int) $item['quantity'] > (int) $item['stock']) {
                    throw new Exception("One or more items no longer have enough stock.");
                }
                $locked_items[] = $item;
                $locked_subtotal += (int) $item['quantity'] * (float) $item['price'];
            }

            if (!$locked_items) {
                throw new Exception("Your cart is empty.");
            }

            $locked_tax = round($locked_subtotal * $tax_rate, 2);
            $locked_total = $locked_subtotal + $delivery_fee + $locked_tax;

            if ($saved_address) {
                $address_id = (int) $saved_address['id'];
                $stmt = mysqli_prepare($conn, "UPDATE user_addresses SET phone = ?, city = ?, address = ?, is_default = TRUE WHERE id = ? AND user_id = ?");
                $normalized_phone = normalize_phone_number($phone);
                mysqli_stmt_bind_param($stmt, "sssii", $normalized_phone, $city, $address, $address_id, $user_id);
                mysqli_stmt_execute($stmt);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO user_addresses (user_id, phone, city, address, is_default) VALUES (?, ?, ?, ?, TRUE)");
                $normalized_phone = normalize_phone_number($phone);
                mysqli_stmt_bind_param($stmt, "isss", $user_id, $normalized_phone, $city, $address);
                mysqli_stmt_execute($stmt);
                $address_id = mysqli_insert_id($conn);
            }

            $stmt = mysqli_prepare($conn, "
                INSERT INTO orders (user_id, delivery_address_id, status)
                VALUES (?, ?, 'pending')
            ");
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $address_id);
            mysqli_stmt_execute($stmt);
            $order_id = mysqli_insert_id($conn);

            foreach ($locked_items as $item) {
                $stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                mysqli_stmt_execute($stmt);

                $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $item['quantity'], $item['product_id']);
                mysqli_stmt_execute($stmt);
            }

            $payment_status = $payment_method === 'card' ? 'paid' : 'pending';
            $stmt = mysqli_prepare($conn, "INSERT INTO payments (order_id, payment_method, card_last4, payment_status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $order_id, $payment_method, $card_last4, $payment_status);
            mysqli_stmt_execute($stmt);

            $invoice_number = 'INV-' . str_pad((string) $order_id, 4, '0', STR_PAD_LEFT);
            $stmt = mysqli_prepare($conn, "INSERT INTO invoices (order_id, invoice_number) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "is", $order_id, $invoice_number);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);
            header("Location: my_orders.php?placed=1");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BazaarHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body class="customer-page">
<main class="shop-shell">
    <nav class="shop-nav" aria-label="Customer navigation">
        <a class="shop-brand" href="dashboard.php"><strong>BazaarHub</strong><span>Secure checkout</span></a>
        <div class="shop-links">
            <a class="shop-link" href="products.php">Shop</a>
            <a class="shop-link" href="cart.php">Cart</a>
            <a class="shop-link" href="../logout.php">Logout</a>
        </div>
    </nav>

    <section class="checkout-layout">
        <form class="checkout-form shop-panel" method="POST">
            <?= csrf_input() ?>
            <p class="shop-kicker">Delivery details</p>
            <h1 class="checkout-title">Confirm your order</h1>
            <?php if ($message): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>

            <label>Customer name
                <input class="shop-input" value="<?= htmlspecialchars($customer_name) ?>" readonly>
            </label>
            <label>Phone
                <input class="shop-input" name="phone" value="<?= htmlspecialchars($phone) ?>" inputmode="tel" maxlength="15" pattern="[0-9+\-\s()]{10,20}" required>
            </label>
            <label>City
                <input class="shop-input" name="city" value="<?= htmlspecialchars($city) ?>" required>
            </label>
            <label>Complete delivery address
                <textarea class="shop-textarea" name="address" required><?= htmlspecialchars($address) ?></textarea>
            </label>

            <p class="shop-kicker">Payment</p>
            <div class="payment-options">
                <label><input type="radio" name="payment_method" value="cash" <?= $payment_method === 'cash' ? 'checked' : '' ?>> Cash on delivery</label>
                <label><input type="radio" name="payment_method" value="card" <?= $payment_method === 'card' ? 'checked' : '' ?>> Card payment</label>
            </div>
            <label>Card number
                <input class="shop-input" name="card_number" inputmode="numeric" maxlength="19" pattern="[0-9\s\-]{13,23}" placeholder="Only needed for card payment">
            </label>
            <button class="shop-button shop-button--primary" type="submit">Confirm Order</button>
        </form>

        <aside class="checkout-summary">
            <p class="shop-kicker">Order summary</p>
            <?php foreach ($items as $item): ?>
                <div class="summary-line">
                    <span><?= htmlspecialchars($item['name']) ?> x <?= (int) $item['quantity'] ?></span>
                    <strong>$<?= number_format($item['line_total'], 2) ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="summary-line"><span>Subtotal</span><strong>$<?= number_format($subtotal, 2) ?></strong></div>
            <div class="summary-line"><span>Delivery fee</span><strong>$<?= number_format($delivery_fee, 2) ?></strong></div>
            <div class="summary-line"><span>Tax 5%</span><strong>$<?= number_format($tax_amount, 2) ?></strong></div>
            <div class="summary-total"><span>Total</span><strong>$<?= number_format($total, 2) ?></strong></div>
        </aside>
    </section>
</main>
</body>
</html>
