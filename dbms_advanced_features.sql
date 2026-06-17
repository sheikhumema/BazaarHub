USE bazaarhub;

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,
    action_type VARCHAR(20) NOT NULL,
    record_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS product_rating_summary (
    product_id INT PRIMARY KEY,
    review_count INT NOT NULL DEFAULT 0,
    average_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

ALTER TABLE cart
    ADD UNIQUE KEY IF NOT EXISTS uq_cart_user_product (user_id, product_id);

CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_seller ON products(seller_id);
CREATE INDEX IF NOT EXISTS idx_orders_user_status ON orders(user_id, status);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_reviews_product ON reviews(product_id);

INSERT INTO product_rating_summary (product_id, review_count, average_rating)
SELECT p.id, COUNT(r.id), COALESCE(AVG(r.rating), 0)
FROM products p
LEFT JOIN reviews r ON r.product_id = p.id
GROUP BY p.id
ON DUPLICATE KEY UPDATE
    review_count = VALUES(review_count),
    average_rating = VALUES(average_rating);

DROP VIEW IF EXISTS product_catalog_full_view;
CREATE VIEW product_catalog_full_view AS
SELECT p.id,
       p.name,
       p.description,
       p.image_url,
       p.price,
       p.stock,
       c.name AS category_name,
       u.name AS seller_name,
       COALESCE(prs.average_rating, 0) AS average_rating,
       COALESCE(prs.review_count, 0) AS review_count
FROM products p
JOIN categories c ON c.id = p.category_id
JOIN users u ON u.id = p.seller_id
LEFT JOIN product_rating_summary prs ON prs.product_id = p.id;

DROP VIEW IF EXISTS admin_sales_report_view;
CREATE VIEW admin_sales_report_view AS
SELECT DATE(o.created_at) AS order_date,
       COUNT(*) AS total_orders,
       SUM(ot.subtotal) AS subtotal,
       SUM(ot.delivery_fee) AS delivery_fees,
       SUM(ot.tax_amount) AS taxes,
       SUM(ot.total_amount) AS revenue
FROM orders o
JOIN order_totals_view ot ON ot.order_id = o.id
WHERE o.status <> 'cancelled'
GROUP BY DATE(o.created_at);

DROP VIEW IF EXISTS seller_revenue_view;
CREATE VIEW seller_revenue_view AS
SELECT u.id AS seller_id,
       u.name AS seller_name,
       COUNT(DISTINCT o.id) AS orders_count,
       COALESCE(SUM(oi.quantity), 0) AS units_sold,
       COALESCE(SUM(oi.quantity * oi.price), 0) AS seller_revenue
FROM users u
LEFT JOIN products p ON p.seller_id = u.id
LEFT JOIN order_items oi ON oi.product_id = p.id
LEFT JOIN orders o ON o.id = oi.order_id AND o.status <> 'cancelled'
WHERE u.role = 'seller'
GROUP BY u.id, u.name;

DELIMITER $$

DROP TRIGGER IF EXISTS trg_cart_before_insert$$
CREATE TRIGGER trg_cart_before_insert
BEFORE INSERT ON cart
FOR EACH ROW
BEGIN
    DECLARE available_stock INT DEFAULT 0;

    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cart quantity must be positive';
    END IF;

    SELECT stock INTO available_stock
    FROM products
    WHERE id = NEW.product_id;

    IF available_stock IS NULL OR NEW.quantity > available_stock THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cart quantity exceeds available stock';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_cart_before_update$$
CREATE TRIGGER trg_cart_before_update
BEFORE UPDATE ON cart
FOR EACH ROW
BEGIN
    DECLARE available_stock INT DEFAULT 0;

    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cart quantity must be positive';
    END IF;

    SELECT stock INTO available_stock
    FROM products
    WHERE id = NEW.product_id;

    IF available_stock IS NULL OR NEW.quantity > available_stock THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cart quantity exceeds available stock';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_reviews_after_insert$$
CREATE TRIGGER trg_reviews_after_insert
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    INSERT INTO product_rating_summary (product_id, review_count, average_rating)
    SELECT NEW.product_id, COUNT(*), AVG(rating)
    FROM reviews
    WHERE product_id = NEW.product_id
    ON DUPLICATE KEY UPDATE
        review_count = VALUES(review_count),
        average_rating = VALUES(average_rating);

    INSERT INTO audit_log(table_name, action_type, record_id, details)
    VALUES ('reviews', 'INSERT', NEW.id, CONCAT('Product ', NEW.product_id, ' rated ', NEW.rating, '/5'));
END$$

DROP TRIGGER IF EXISTS trg_reviews_after_delete$$
CREATE TRIGGER trg_reviews_after_delete
AFTER DELETE ON reviews
FOR EACH ROW
BEGIN
    INSERT INTO product_rating_summary (product_id, review_count, average_rating)
    SELECT OLD.product_id, COUNT(*), COALESCE(AVG(rating), 0)
    FROM reviews
    WHERE product_id = OLD.product_id
    ON DUPLICATE KEY UPDATE
        review_count = VALUES(review_count),
        average_rating = VALUES(average_rating);

    INSERT INTO audit_log(table_name, action_type, record_id, details)
    VALUES ('reviews', 'DELETE', OLD.id, CONCAT('Deleted review for product ', OLD.product_id));
END$$

DROP TRIGGER IF EXISTS trg_products_after_insert$$
CREATE TRIGGER trg_products_after_insert
AFTER INSERT ON products
FOR EACH ROW
BEGIN
    INSERT INTO product_rating_summary(product_id, review_count, average_rating)
    VALUES (NEW.id, 0, 0);

    INSERT INTO audit_log(table_name, action_type, record_id, details)
    VALUES ('products', 'INSERT', NEW.id, CONCAT('Product added: ', NEW.name));
END$$

DROP TRIGGER IF EXISTS trg_orders_after_update$$
CREATE TRIGGER trg_orders_after_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO audit_log(table_name, action_type, record_id, details)
        VALUES ('orders', 'STATUS_CHANGE', NEW.id, CONCAT(OLD.status, ' -> ', NEW.status));
    END IF;
END$$

DROP PROCEDURE IF EXISTS place_order_from_cart_v2$$
CREATE PROCEDURE place_order_from_cart_v2(
    IN customerId INT,
    IN addressId INT,
    IN payMethod VARCHAR(10),
    IN cardLast4Value VARCHAR(4)
)
BEGIN
    DECLARE orderSubtotal DECIMAL(10,2);
    DECLARE deliveryFee DECIMAL(10,2) DEFAULT 10.00;
    DECLARE taxAmount DECIMAL(10,2);
    DECLARE grandTotal DECIMAL(10,2);
    DECLARE newOrderId INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COALESCE(SUM(c.quantity * p.price), 0)
    INTO orderSubtotal
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = customerId
    FOR UPDATE;

    IF orderSubtotal <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cart is empty';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.user_id = customerId AND c.quantity > p.stock
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;

    SET taxAmount = ROUND(orderSubtotal * 0.05, 2);
    SET grandTotal = orderSubtotal + deliveryFee + taxAmount;

    INSERT INTO orders (user_id, delivery_address_id, status)
    VALUES (customerId, addressId, 'pending');

    SET newOrderId = LAST_INSERT_ID();

    INSERT INTO order_items (order_id, product_id, quantity, price)
    SELECT newOrderId, c.product_id, c.quantity, p.price
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = customerId;

    UPDATE products p
    JOIN cart c ON c.product_id = p.id
    SET p.stock = p.stock - c.quantity
    WHERE c.user_id = customerId;

    INSERT INTO payments (order_id, payment_method, card_last4, payment_status)
    VALUES (newOrderId, payMethod, cardLast4Value, IF(payMethod = 'card', 'paid', 'pending'));

    INSERT INTO invoices (order_id, invoice_number)
    VALUES (newOrderId, CONCAT('INV-', LPAD(newOrderId, 4, '0')));

    DELETE FROM cart WHERE user_id = customerId;

    COMMIT;
END$$

DROP EVENT IF EXISTS ev_mark_orders_delivered$$
CREATE EVENT ev_mark_orders_delivered
ON SCHEDULE EVERY 1 MINUTE
DO
BEGIN
    UPDATE orders
    SET status = 'delivered'
    WHERE status IN ('pending','completed')
      AND created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE);
END$$

DELIMITER ;

SET GLOBAL event_scheduler = ON;
