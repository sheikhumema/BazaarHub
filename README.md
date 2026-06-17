# BazaarHub – Shopping Store Database Project

## Overview
BazaarHub is a full-featured, database-driven web-based shopping store. It allows sellers to list and manage products while customers can browse, purchase, and review items through a structured e-commerce system.

The project focuses on strong database design, SQL transactions, and real-world e-commerce workflows using PHP and MySQL.

---

## Features

### User Management
- Role-based system (Admin, Seller, Customer)
- Secure registration and login system
- User profile management

### Product Management
- Add, update, and delete products (Seller/Admin)
- Product categorization and search functionality
- Detailed product pages

### Shopping Cart & Orders
- Persistent shopping cart system
- Complete order lifecycle (place, track, cancel)
- Real-time inventory updates using SQL transactions

### Inventory Management
- Automatic stock deduction after order confirmation
- Seller-side stock tracking system

### Reviews & Ratings
- One review per product per customer
- Star rating and text reviews
- Average rating calculated using SQL aggregates

### Billing System
- Order-based invoice generation
- Simulated payment records stored in database (PKR currency)

### Admin Dashboard
- Manage users, sellers, products, and orders
- Sales and revenue reporting
- SQL-based analytics and reporting

---

## Tech Stack

- Frontend: HTML5, CSS3, JavaScript
- Backend: PHP
- Database: MySQL

---

## System Roles

### Administrator
- Full system control
- Manage users, products, categories, orders, and reports

### Seller
- Manage product listings and inventory
- View and fulfill customer orders

### Customer
- Browse and search products
- Add items to cart and place orders
- Submit reviews and manage profile

---

## System Scope

### In Scope
- User authentication and role-based access
- Product catalog with search and filtering
- Shopping cart and order processing system
- Inventory management using SQL transactions
- Review and rating system
- Admin dashboard with analytics
- Full SQL implementation (joins, views, triggers, procedures)

### Out of Scope
- Mobile application (Android/iOS)
- Real payment gateway integration
- Multi-currency support
- Courier/shipping API integration
- AI-based recommendations
- Live chat support

---

## Assumptions
- All transactions are in PKR currency
- Payments are simulated and always successful
- Web-based system only
- Each seller is a registered user with role = 'seller'
- Customers can have multiple addresses
- Only one review per product per customer

---

## Database Design Highlights
- Fully normalized relational database
- Foreign key constraints for data integrity
- SQL transactions for order processing
- Aggregation queries for ratings and reports
- Views for admin analytics
- Stored procedures and functions for business logic

---

## Project Goal
The goal of BazaarHub is to simulate a real-world e-commerce platform while focusing on:
- Database design principles
- Transaction management
- Role-based access control
- SQL query optimization
- Practical backend integration with PHP and MySQL


## License
This project is developed for educational purposes only.
