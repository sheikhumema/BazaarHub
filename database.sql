/* =========================================================
   BAZAARHUB DATABASE SCHEMA (FINAL MERGED)
   ========================================================= */

CREATE DATABASE IF NOT EXISTS bazaarhub;
USE bazaarhub;

/* =========================================================
   1. USERS TABLE
   ========================================================= */
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL CHECK (CHAR_LENGTH(email) BETWEEN 5 AND 100),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','seller','customer') DEFAULT 'customer',
    account_status ENUM('pending','active','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* =========================================================
   2. USER ADDRESSES
   ========================================================= */
CREATE TABLE user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(30),
    city VARCHAR(100),
    address TEXT NOT NULL,
    is_default BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

/* =========================================================
   3. CATEGORIES
   ========================================================= */
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL
);

/* =========================================================
   4. PRODUCTS
   ========================================================= */
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    category_id INT NOT NULL,
    seller_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

/* =========================================================
   5. CART
   ========================================================= */
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    UNIQUE(user_id, product_id),
    CHECK (quantity > 0),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

/* =========================================================
   6. ORDERS
   ========================================================= */
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    delivery_address_id INT,
    status ENUM('pending','completed','delivered','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_address_id) REFERENCES user_addresses(id)
    ON DELETE SET NULL
);

/* =========================================================
   7. ORDER ITEMS
   ========================================================= */
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    UNIQUE(order_id, product_id),
    CHECK (quantity > 0),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

/* =========================================================
   8. REVIEWS
   ========================================================= */
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

/* =========================================================
   9. PAYMENTS
   ========================================================= */
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('card','cash') DEFAULT 'cash',
    card_last4 VARCHAR(4) CHECK (card_last4 IS NULL OR CHAR_LENGTH(card_last4) = 4),
    payment_status ENUM('pending','paid') DEFAULT 'paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

/* =========================================================
   10. INVOICES
   ========================================================= */
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNIQUE NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

/* =========================================================
   10B. ORDER TOTALS VIEW
   ========================================================= */
CREATE VIEW order_totals_view AS
SELECT o.id AS order_id,
       COALESCE(SUM(oi.quantity * oi.price), 0) AS subtotal,
       10.00 AS delivery_fee,
       ROUND(COALESCE(SUM(oi.quantity * oi.price), 0) * 0.05, 2) AS tax_amount,
       ROUND(COALESCE(SUM(oi.quantity * oi.price), 0) + 10.00 + ROUND(COALESCE(SUM(oi.quantity * oi.price), 0) * 0.05, 2), 2) AS total_amount
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
GROUP BY o.id;

/* =========================================================
   11. PASSWORD RESET TOKENS
   ========================================================= */
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_hash (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* =========================================================
   SEED DATA
   ========================================================= */

/* USERS */
INSERT INTO users (name, email, password, role) VALUES
('Ali Raza', 'ali.raza@gmail.com', 'Ali@123', 'admin'),
('Sara Khan', 'sara.khan@gmail.com', 'Sara@123', 'admin'),
('Ahmed Hassan', 'ahmed.seller@gmail.com', 'Ahmed@123', 'seller'),
('Hina Malik', 'hina.seller@gmail.com', 'Hina@123', 'seller'),
('Usman Tariq', 'usman@gmail.com', 'Usman@123', 'customer');

/* CATEGORIES */
INSERT INTO categories (name) VALUES
('Blouses'),('Denim'),('Jackets'),('Dresses'),('Skirts'),('Shorts'),('Shirts');

/* PRODUCTS */
INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id) VALUES
('Blue Bow Blouse', 'Soft blue blouse with a delicate bow detail for polished everyday outfits.', 'assets/images/products/blue_bow_blouse.jfif', 34.99, 18, 1, 3),
('Blue Denim Mom Jeans', 'Relaxed high-rise mom jeans in classic blue denim with a vintage-inspired fit.', 'assets/images/products/blue_denim_mom_jeans.jpg', 49.99, 14, 2, 4),
('Brown Blouse', 'Warm brown blouse with an easy drape, made for office days and dinner plans.', 'assets/images/products/brown_blouse.jfif', 32.99, 20, 1, 3),
('Brown Leather Jacket', 'Structured brown leather-look jacket that adds instant edge to casual looks.', 'assets/images/products/brown_leather_jacket.jfif', 89.99, 9, 3, 4),
('Brown Patterned Skirt', 'Printed brown skirt with graceful movement and a versatile mid-length shape.', 'assets/images/products/brown_patterned_skirt.jfif', 39.99, 16, 5, 3),
('Dark Straight-Leg Denim Jeans', 'Dark-wash straight-leg denim with a clean silhouette for repeat styling.', 'assets/images/products/dark_straighleg_denim_jeans.jfif', 54.99, 12, 2, 4),
('Faded Bermuda Shorts', 'Faded denim Bermuda shorts with a relaxed weekend fit and easy summer feel.', 'assets/images/products/faded_bermuda_shorts.jfif', 29.99, 22, 6, 3),
('Pink Button-Down Shirt', 'Crisp pink button-down shirt that works tucked, layered, or worn open.', 'assets/images/products/pink_buttondown_shirt.jfif', 36.99, 17, 7, 4),
('Purple Checkered Shirt', 'Purple checkered shirt with a casual pattern and comfortable daily wear fit.', 'assets/images/products/purple_checkered.jpg', 31.99, 19, 7, 3),
('White Dress', 'Fresh white dress with a clean feminine shape for day events and brunch plans.', 'assets/images/products/white_dress.jfif', 59.99, 11, 4, 4);

/* =========================================================
   VIEWS
   ========================================================= */

CREATE VIEW product_catalog_view AS
SELECT p.id, p.name, p.description, p.image_url, p.price, p.stock, c.name AS category_name
FROM products p
JOIN categories c ON c.id = p.category_id;

CREATE VIEW seller_sales_view AS
SELECT u.id, u.name, COUNT(o.id) AS total_orders
FROM users u
LEFT JOIN products p ON p.seller_id = u.id
LEFT JOIN order_items oi ON oi.product_id = p.id
LEFT JOIN orders o ON o.id = oi.order_id
WHERE u.role = 'seller'
GROUP BY u.id;

/* =========================================================
   FUNCTION
   ========================================================= */

DELIMITER $$

CREATE FUNCTION get_product_average_rating(productId INT)
RETURNS DECIMAL(3,2)
DETERMINISTIC
BEGIN
    DECLARE avgRating DECIMAL(3,2);

    SELECT COALESCE(AVG(rating),0)
    INTO avgRating
    FROM reviews
    WHERE product_id = productId;

    RETURN avgRating;
END$$

/* =========================================================
   PROCEDURE
   ========================================================= */

CREATE PROCEDURE place_order_from_cart(IN customerId INT, IN addressId INT)
BEGIN
    DECLARE orderTotal DECIMAL(10,2);
    DECLARE newOrderId INT;

    START TRANSACTION;

    SELECT SUM(c.quantity * p.price)
    INTO orderTotal
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = customerId;

    INSERT INTO orders (user_id, delivery_address_id, total_amount, status)
    VALUES (customerId, addressId, orderTotal, 'completed');

    SET newOrderId = LAST_INSERT_ID();

    INSERT INTO order_items (order_id, product_id, quantity, price)
    SELECT newOrderId, c.product_id, c.quantity, p.price
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = customerId;

    DELETE FROM cart WHERE user_id = customerId;

    COMMIT;
END$$

DELIMITER ;
