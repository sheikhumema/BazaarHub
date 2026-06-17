<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'bazaarhub';

function column_exists(mysqli $conn, string $table, string $column): bool
{
    global $dbName;

    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    mysqli_stmt_bind_param($stmt, "sss", $dbName, $table, $column);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return (int) ($row['total'] ?? 0) > 0;
}

function index_exists(mysqli $conn, string $table, string $indexName): bool
{
    global $dbName;

    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    mysqli_stmt_bind_param($stmt, "sss", $dbName, $table, $indexName);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return (int) ($row['total'] ?? 0) > 0;
}

try {
    // Connect to the MySQL server first so we can create/select the database safely.
    $conn = mysqli_connect($dbHost, $dbUser, $dbPass);
    mysqli_set_charset($conn, 'utf8mb4');

    mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    mysqli_select_db($conn, $dbName);

    // If the schema has not been imported yet, stop with a clear setup message instead of a fatal error.
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (mysqli_num_rows($tableCheck) === 0) {
        die("BazaarHub database is present, but the schema is not initialized yet. Import database.sql into the bazaarhub database, then reload the app.");
    }

    if (column_exists($conn, 'orders', 'subtotal') || column_exists($conn, 'orders', 'delivery_fee') || column_exists($conn, 'orders', 'tax_amount') || column_exists($conn, 'orders', 'total_amount') || column_exists($conn, 'orders', 'payment_method') || column_exists($conn, 'orders', 'card_last4')) {
        mysqli_query($conn, "ALTER TABLE orders DROP COLUMN IF EXISTS subtotal, DROP COLUMN IF EXISTS delivery_fee, DROP COLUMN IF EXISTS tax_amount, DROP COLUMN IF EXISTS total_amount, DROP COLUMN IF EXISTS payment_method, DROP COLUMN IF EXISTS card_last4");
    }

    if (column_exists($conn, 'payments', 'amount')) {
        mysqli_query($conn, "ALTER TABLE payments DROP COLUMN IF EXISTS amount");
    }

    if (column_exists($conn, 'invoices', 'subtotal') || column_exists($conn, 'invoices', 'tax_amount') || column_exists($conn, 'invoices', 'total_amount')) {
        mysqli_query($conn, "ALTER TABLE invoices DROP COLUMN IF EXISTS subtotal, DROP COLUMN IF EXISTS tax_amount, DROP COLUMN IF EXISTS total_amount");
    }

    if (!index_exists($conn, 'cart', 'uq_cart_user_product')) {
        mysqli_query($conn, "ALTER TABLE cart ADD UNIQUE KEY uq_cart_user_product (user_id, product_id)");
    }

    if (!index_exists($conn, 'order_items', 'uq_order_items_order_product')) {
        mysqli_query($conn, "ALTER TABLE order_items ADD UNIQUE KEY uq_order_items_order_product (order_id, product_id)");
    }

    if (!index_exists($conn, 'payments', 'uq_payments_order')) {
        mysqli_query($conn, "ALTER TABLE payments ADD UNIQUE KEY uq_payments_order (order_id)");
    }

    mysqli_query($conn, "
        CREATE OR REPLACE VIEW order_totals_view AS
        SELECT o.id AS order_id,
               COALESCE(SUM(oi.quantity * oi.price), 0) AS subtotal,
               10.00 AS delivery_fee,
               ROUND(COALESCE(SUM(oi.quantity * oi.price), 0) * 0.05, 2) AS tax_amount,
               ROUND(COALESCE(SUM(oi.quantity * oi.price), 0) + 10.00 + ROUND(COALESCE(SUM(oi.quantity * oi.price), 0) * 0.05, 2), 2) AS total_amount
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_reset_user (user_id),
            INDEX idx_password_reset_hash (token_hash),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
