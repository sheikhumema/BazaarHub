USE bazaarhub;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) AFTER description;

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

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS delivery_address_id INT NULL AFTER user_id;

INSERT INTO categories (name) VALUES
('Blouses'), ('Denim'), ('Jackets'), ('Dresses'), ('Skirts'), ('Shorts'), ('Shirts')
ON DUPLICATE KEY UPDATE name = VALUES(name);

DELETE FROM reviews;
DELETE FROM cart;
DELETE FROM invoices;
DELETE FROM payments;
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM products;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Blue Bow Blouse',
       'Soft blue blouse with a delicate bow detail for polished everyday outfits.',
       'assets/images/products/blue_bow_blouse.jfif',
       34.99,
       18,
       c.id,
       3
FROM categories c
WHERE c.name = 'Blouses'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Blue Denim Mom Jeans',
       'Relaxed high-rise mom jeans in classic blue denim with a vintage-inspired fit.',
       'assets/images/products/blue_denim_mom_jeans.jpg',
       49.99,
       14,
       c.id,
       4
FROM categories c
WHERE c.name = 'Denim'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Brown Blouse',
       'Warm brown blouse with an easy drape, made for office days and dinner plans.',
       'assets/images/products/brown_blouse.jfif',
       32.99,
       20,
       c.id,
       3
FROM categories c
WHERE c.name = 'Blouses'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Brown Leather Jacket',
       'Structured brown leather-look jacket that adds instant edge to casual looks.',
       'assets/images/products/brown_leather_jacket.jfif',
       89.99,
       9,
       c.id,
       4
FROM categories c
WHERE c.name = 'Jackets'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Brown Patterned Skirt',
       'Printed brown skirt with graceful movement and a versatile mid-length shape.',
       'assets/images/products/brown_patterned_skirt.jfif',
       39.99,
       16,
       c.id,
       3
FROM categories c
WHERE c.name = 'Skirts'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Dark Straight-Leg Denim Jeans',
       'Dark-wash straight-leg denim with a clean silhouette for repeat styling.',
       'assets/images/products/dark_straighleg_denim_jeans.jfif',
       54.99,
       12,
       c.id,
       4
FROM categories c
WHERE c.name = 'Denim'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Faded Bermuda Shorts',
       'Faded denim Bermuda shorts with a relaxed weekend fit and easy summer feel.',
       'assets/images/products/faded_bermuda_shorts.jfif',
       29.99,
       22,
       c.id,
       3
FROM categories c
WHERE c.name = 'Shorts'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Pink Button-Down Shirt',
       'Crisp pink button-down shirt that works tucked, layered, or worn open.',
       'assets/images/products/pink_buttondown_shirt.jfif',
       36.99,
       17,
       c.id,
       4
FROM categories c
WHERE c.name = 'Shirts'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'Purple Checkered Shirt',
       'Purple checkered shirt with a casual pattern and comfortable daily wear fit.',
       'assets/images/products/purple_checkered.jpg',
       31.99,
       19,
       c.id,
       3
FROM categories c
WHERE c.name = 'Shirts'
LIMIT 1;

INSERT INTO products (name, description, image_url, price, stock, category_id, seller_id)
SELECT 'White Dress',
       'Fresh white dress with a clean feminine shape for day events and brunch plans.',
       'assets/images/products/white_dress.jfif',
       59.99,
       11,
       c.id,
       4
FROM categories c
WHERE c.name = 'Dresses'
LIMIT 1;
