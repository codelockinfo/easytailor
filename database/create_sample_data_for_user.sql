-- Sample Data Template for New Users
-- This data will be copied for each new user when they register
-- Replace :user_id with actual user ID

-- Sample Customers for User
INSERT INTO `customers` (`user_id`, `customer_code`, `first_name`, `last_name`, `email`, `phone`, `address`, `city`, `state`, `postal_code`, `status`) VALUES
(:user_id, CONCAT('CUST', LPAD(:user_id * 1000 + 1, 6, '0')), 'John', 'Doe', 'john.doe@example.com', '9876543210', '123 Main Street', 'Mumbai', 'Maharashtra', '400001', 'active'),
(:user_id, CONCAT('CUST', LPAD(:user_id * 1000 + 2, 6, '0')), 'Jane', 'Smith', 'jane.smith@example.com', '9876543211', '456 Park Avenue', 'Delhi', 'Delhi', '110001', 'active'),
(:user_id, CONCAT('CUST', LPAD(:user_id * 1000 + 3, 6, '0')), 'Raj', 'Kumar', 'raj.kumar@example.com', '9876543212', '789 Lake Road', 'Bangalore', 'Karnataka', '560001', 'active');

-- Sample Cloth Types for User (if needed per user)
-- Note: Cloth types might be shared across all users, or per-user
-- INSERT INTO `cloth_types` (`user_id`, `name`, `description`, `standard_rate`, `category`, `status`) VALUES
-- (:user_id, 'Sample Shirt', 'Men formal shirt', 500.00, 'Men\'s Wear', 'active');

-- Sample Orders (using the sample customers created above)
-- This requires customer IDs from above inserts

-- Sample Expenses
INSERT INTO `expenses` (`created_by`, `category`, `description`, `amount`, `expense_date`, `payment_method`, `status`) VALUES
(:user_id, 'Rent', 'Shop rent for the month', 10000.00, CURDATE(), 'bank_transfer', 'active'),
(:user_id, 'Utilities', 'Electricity bill', 2000.00, CURDATE(), 'cash', 'active'),
(:user_id, 'Supplies', 'Thread and buttons purchase', 1500.00, CURDATE(), 'cash', 'active');

COMMIT;





