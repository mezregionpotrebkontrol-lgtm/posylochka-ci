-- Migration 012: recipient name and phone for orders.
-- Import via ispmanager -> Databases -> phpMyAdmin -> SQL.

SET NAMES utf8mb4;

ALTER TABLE orders
    ADD COLUMN recipient_name VARCHAR(255) DEFAULT NULL AFTER to_address,
    ADD COLUMN recipient_phone VARCHAR(32) DEFAULT NULL AFTER recipient_name;
