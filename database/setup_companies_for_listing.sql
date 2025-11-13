-- Setup Companies Table for Public Listing
-- Shows tailor shops/companies on landing page

-- Create companies table (if not exists)
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'Owner user ID',
  `company_name` varchar(200) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `business_email` varchar(100) NOT NULL,
  `business_phone` varchar(20) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `postal_code` varchar(20) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'INR',
  `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
  `description` text DEFAULT NULL COMMENT 'Shop description for public listing',
  `specialties` json DEFAULT NULL COMMENT 'Array of specialties',
  `working_hours` json DEFAULT NULL COMMENT 'Working hours',
  `rating` decimal(2,1) DEFAULT 0.0 COMMENT 'Average rating',
  `total_reviews` int(11) DEFAULT 0 COMMENT 'Total reviews',
  `years_experience` int(11) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'Verified shop',
  `is_featured` tinyint(1) DEFAULT 0 COMMENT 'Featured on homepage',
  `show_on_listing` tinyint(1) DEFAULT 1 COMMENT 'Show on public listing',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `subscription_plan` enum('free','basic','premium','enterprise') DEFAULT 'free',
  `subscription_expiry` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_email` (`business_email`),
  KEY `user_id` (`user_id`),
  KEY `city` (`city`),
  KEY `state` (`state`),
  KEY `is_featured` (`is_featured`),
  KEY `show_on_listing` (`show_on_listing`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample companies/tailor shops
INSERT INTO `companies` (`company_name`, `owner_name`, `business_email`, `business_phone`, `whatsapp`, `business_address`, `city`, `state`, `postal_code`, `description`, `specialties`, `working_hours`, `rating`, `total_reviews`, `years_experience`, `is_verified`, `is_featured`, `show_on_listing`, `status`) VALUES

('Elite Tailors', 'Rajesh Kumar', 'elite.tailors@gmail.com', '+91 9876543210', '+91 9876543210', '123 MG Road, Near City Mall', 'Mumbai', 'Maharashtra', '400001', 'Premium tailoring service with over 15 years of experience. Specializing in wedding suits, party wear, and formal attire.', '["Wedding Suits", "Party Wear", "Formal Suits", "Custom Designs"]', '{"monday": "9:00 AM - 8:00 PM", "tuesday": "9:00 AM - 8:00 PM", "wednesday": "9:00 AM - 8:00 PM", "thursday": "9:00 AM - 8:00 PM", "friday": "9:00 AM - 8:00 PM", "saturday": "9:00 AM - 8:00 PM", "sunday": "10:00 AM - 6:00 PM"}', 4.8, 125, 15, 1, 1, 1, 'active'),

('Fashion Stitch', 'Priya Sharma', 'fashion.stitch@gmail.com', '+91 9876543211', '+91 9876543211', '456 Fashion Street, Commercial Complex', 'Delhi', 'Delhi', '110001', 'Modern tailoring boutique offering contemporary designs and traditional wear with perfect finishing.', '["Ladies Suits", "Lehenga", "Saree Blouse", "Designer Wear"]', '{"monday": "10:00 AM - 7:00 PM", "tuesday": "10:00 AM - 7:00 PM", "wednesday": "10:00 AM - 7:00 PM", "thursday": "10:00 AM - 7:00 PM", "friday": "10:00 AM - 7:00 PM", "saturday": "10:00 AM - 7:00 PM", "sunday": "Closed"}', 4.6, 98, 10, 1, 1, 1, 'active'),

('Stitch Perfect', 'Amit Patel', 'stitch.perfect@gmail.com', '+91 9876543212', '+91 9876543212', '789 Textile Market, Shop No. 45', 'Ahmedabad', 'Gujarat', '380001', 'Expert in men\'s formal wear and casual stitching. Quick delivery and affordable prices.', '["Men\'s Shirts", "Pants", "Kurta Pajama", "Alterations"]', '{"monday": "9:00 AM - 9:00 PM", "tuesday": "9:00 AM - 9:00 PM", "wednesday": "9:00 AM - 9:00 PM", "thursday": "9:00 AM - 9:00 PM", "friday": "9:00 AM - 9:00 PM", "saturday": "9:00 AM - 9:00 PM", "sunday": "10:00 AM - 5:00 PM"}', 4.7, 156, 12, 1, 1, 1, 'active'),

('Royal Tailors', 'Vikram Singh', 'royal.tailors@gmail.com', '+91 9876543213', '+91 9876543213', '321 Palace Road, Heritage Building', 'Jaipur', 'Rajasthan', '302001', 'Specializing in traditional Rajasthani attire and royal wedding outfits. Premium quality fabrics.', '["Wedding Sherwanis", "Traditional Wear", "Royal Outfits", "Indo-Western"]', '{"monday": "10:00 AM - 8:00 PM", "tuesday": "10:00 AM - 8:00 PM", "wednesday": "10:00 AM - 8:00 PM", "thursday": "10:00 AM - 8:00 PM", "friday": "10:00 AM - 8:00 PM", "saturday": "10:00 AM - 8:00 PM", "sunday": "11:00 AM - 6:00 PM"}', 4.9, 210, 20, 1, 1, 1, 'active'),

('Modern Stitches', 'Sneha Reddy', 'modern.stitches@gmail.com', '+91 9876543214', '+91 9876543214', '654 Tech Park Road, Building B', 'Bangalore', 'Karnataka', '560001', 'Contemporary tailoring with a modern touch. Fast service and trendy designs for all occasions.', '["Corporate Wear", "Party Dresses", "Fusion Wear", "Quick Alterations"]', '{"monday": "9:30 AM - 7:30 PM", "tuesday": "9:30 AM - 7:30 PM", "wednesday": "9:30 AM - 7:30 PM", "thursday": "9:30 AM - 7:30 PM", "friday": "9:30 AM - 7:30 PM", "saturday": "10:00 AM - 6:00 PM", "sunday": "Closed"}', 4.5, 82, 8, 1, 1, 1, 'active'),

('Classic Cuts', 'Mohammad Ali', 'classic.cuts@gmail.com', '+91 9876543215', '+91 9876543215', '987 Market Street, Near Station', 'Hyderabad', 'Telangana', '500001', 'Traditional tailoring with classic styles. Expert in sherwanis, suits, and ethnic wear.', '["Sherwanis", "Pathani Suits", "Formal Wear", "Ethnic Wear"]', '{"monday": "9:00 AM - 8:30 PM", "tuesday": "9:00 AM - 8:30 PM", "wednesday": "9:00 AM - 8:30 PM", "thursday": "9:00 AM - 8:30 PM", "friday": "9:00 AM - 8:30 PM", "saturday": "9:00 AM - 8:30 PM", "sunday": "10:00 AM - 5:00 PM"}', 4.7, 143, 18, 1, 0, 1, 'active'),

('Trendy Threads', 'Kavya Nair', 'trendy.threads@gmail.com', '+91 9876543216', '+91 9876543216', '135 Beach Road, Coastal Plaza', 'Kochi', 'Kerala', '682001', 'Boutique tailoring with international designs and local craftsmanship. Wedding specialists.', '["Bridal Wear", "Designer Sarees", "Custom Gowns", "Traditional Kerala Wear"]', '{"monday": "10:00 AM - 7:00 PM", "tuesday": "10:00 AM - 7:00 PM", "wednesday": "10:00 AM - 7:00 PM", "thursday": "10:00 AM - 7:00 PM", "friday": "10:00 AM - 7:00 PM", "saturday": "10:00 AM - 8:00 PM", "sunday": "11:00 AM - 5:00 PM"}', 4.8, 167, 14, 1, 0, 1, 'active'),

('Perfect Fit Tailors', 'Suresh Gupta', 'perfectfit@gmail.com', '+91 9876543217', '+91 9876543217', '246 Shopping Complex, 2nd Floor', 'Pune', 'Maharashtra', '411001', 'Known for perfect measurements and on-time delivery. Corporate and casual wear experts.', '["Corporate Shirts", "Blazers", "Casual Wear", "Uniform Stitching"]', '{"monday": "9:00 AM - 8:00 PM", "tuesday": "9:00 AM - 8:00 PM", "wednesday": "9:00 AM - 8:00 PM", "thursday": "9:00 AM - 8:00 PM", "friday": "9:00 AM - 8:00 PM", "saturday": "9:00 AM - 8:00 PM", "sunday": "Closed"}', 4.6, 119, 11, 1, 0, 1, 'active');

COMMIT;




