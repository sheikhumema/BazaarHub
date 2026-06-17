# BazaarHub Normalization Breakdown

This document describes the normalized core schema used by BazaarHub after the database cleanup.

## Design Goal

The base schema is organized so that each table stores one subject, repeating groups are removed, and non-key attributes depend only on the key, the whole key, and nothing but the key.

Derived totals are produced through views such as `order_totals_view` instead of being duplicated in multiple tables.

## Table-by-Table Review

| Table | 1NF | 2NF | 3NF | BCNF | Notes |
|---|---:|---:|---:|---:|---|
| `users` | Yes | Yes | Yes | Yes | Atomic user attributes only. `email` is unique, so account data is not repeated elsewhere. |
| `user_addresses` | Yes | Yes | Yes | Yes | Stores one address record per row. Phone, city, and address depend on the address record, not on unrelated data. |
| `categories` | Yes | Yes | Yes | Yes | Single lookup table for category names. |
| `products` | Yes | Yes | Yes | Yes | Product identity, category, seller, price, stock, and image live in one place. Category and seller are foreign keys instead of duplicated text. |
| `cart` | Yes | Yes | Yes | Yes | One row per user-product pair. The `UNIQUE(user_id, product_id)` rule prevents duplicate cart rows for the same item. |
| `orders` | Yes | Yes | Yes | Yes | Now stores only the order header: customer, delivery address, status, and timestamps. Financial values were moved out. |
| `order_items` | Yes | Yes | Yes | Yes | Each row stores one product line in an order. The unit price is kept as a historical snapshot for that line item. |
| `reviews` | Yes | Yes | Yes | Yes | One review per customer-product pair. The unique constraint prevents duplicate reviews for the same purchase target. |
| `payments` | Yes | Yes | Yes | Yes | Stores payment method, status, and card tail for a single order. One payment row per order is enforced. |
| `invoices` | Yes | Yes | Yes | Yes | Stores invoice identity only. Totals are derived from order items through views rather than duplicated in the table. |
| `password_reset_tokens` | Yes | Yes | Yes | Yes | One token row represents one reset request. The token hash and expiry belong to the token record only. |

## Why the Schema Is Normalized

- No table stores repeated groups in a single row.
- Many-to-many relationships are split into junction tables such as `order_items`, `cart`, and `reviews`.
- Product category names and seller names are not duplicated inside `products`.
- Customer address data is separated from the user account record.
- Order financial totals are not copied across multiple tables; they are derived from `order_items` and exposed through `order_totals_view`.

## Historical Snapshot Rules

Some values are intentionally stored as snapshots for business history:

- `order_items.price` preserves the product price at the time of purchase.
- `payments.card_last4` preserves only the card tail for reference.

These do not break normalization because they describe the transaction at the time it occurred.

## BCNF Summary

The core base tables satisfy BCNF because every determinant is a candidate key or the primary key of the table.

The only derived objects are reporting views, which are not base storage and are therefore outside the normalization discussion.
