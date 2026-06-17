USE bazaarhub;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS account_status ENUM('pending','active','suspended') DEFAULT 'active' AFTER role;

ALTER TABLE orders
    MODIFY status ENUM('pending','completed','delivered','cancelled') DEFAULT 'pending',
    DROP COLUMN IF EXISTS subtotal,
    DROP COLUMN IF EXISTS delivery_fee,
    DROP COLUMN IF EXISTS tax_amount,
    DROP COLUMN IF EXISTS total_amount,
    DROP COLUMN IF EXISTS payment_method,
    DROP COLUMN IF EXISTS card_last4;

ALTER TABLE payments
    DROP COLUMN IF EXISTS amount,
    ADD COLUMN IF NOT EXISTS payment_method ENUM('card','cash') DEFAULT 'cash' AFTER order_id,
    ADD COLUMN IF NOT EXISTS card_last4 VARCHAR(4) NULL AFTER payment_method;

CREATE TABLE IF NOT EXISTS user_addresses (
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

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNIQUE NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
