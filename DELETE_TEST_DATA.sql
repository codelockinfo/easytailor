-- DELETE ALL TEST DATA
-- Run this in phpMyAdmin to remove all existing data
-- Each new user will start with empty/fresh data

-- Delete payments first (has foreign keys)
DELETE FROM payments;

-- Delete invoices
DELETE FROM invoices;

-- Delete orders
DELETE FROM orders;

-- Delete measurements
DELETE FROM measurements;

-- Delete customers (keep the structure)
DELETE FROM customers;

-- Delete expenses
DELETE FROM expenses;

-- Delete contacts
DELETE FROM contacts;

-- Optional: Delete cloth types (or keep the default 8)
-- DELETE FROM cloth_types WHERE id > 8;

-- Reset auto-increment counters (optional)
ALTER TABLE customers AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE invoices AUTO_INCREMENT = 1;
ALTER TABLE measurements AUTO_INCREMENT = 1;
ALTER TABLE expenses AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE contacts AUTO_INCREMENT = 1;

-- Done! Now each user starts fresh
-- Test data is cleared


