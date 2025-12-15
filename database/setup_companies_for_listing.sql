-- Setup Companies Table for Public Listing
-- Shows tailor shops/companies on landing page

-- Create companies table (if not exists)
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) NOT NULL COMMENT 'Name of the person giving the review',
  `email` varchar(100) NOT NULL COMMENT 'Email of the reviewer (from form)',
  `company_id` int(11) NOT NULL COMMENT 'Foreign key to companies table (shop name)',
  `owner_name` varchar(100) DEFAULT NULL COMMENT 'Owner name from companies table (denormalized for quick access)',
  `user_id` int(11) DEFAULT NULL COMMENT 'Foreign key to users table (tailor/employee)',
  `star` tinyint(1) NOT NULL DEFAULT 5 COMMENT 'Rating from 1 to 5 stars',
  `comment` text NOT NULL COMMENT 'Review comment/testimonial text',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT 'Review status for moderation',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created date',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `email` (`email`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_star_rating` CHECK (`star` >= 1 AND `star` <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;