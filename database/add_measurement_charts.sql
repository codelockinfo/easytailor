-- Add measurement chart image field to cloth_types table
ALTER TABLE `cloth_types` 
ADD COLUMN `measurement_chart_image` VARCHAR(255) DEFAULT NULL 
AFTER `category`;

-- Update existing cloth types with default measurement chart paths
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/pants.svg' WHERE LOWER(`name`) LIKE '%pant%' OR LOWER(`name`) LIKE '%trouser%';
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/shirt.svg' WHERE LOWER(`name`) LIKE '%shirt%';
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/kurta.svg' WHERE LOWER(`name`) LIKE '%kurta%' OR LOWER(`name`) LIKE '%kameez%';
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/lehenga.svg' WHERE LOWER(`name`) LIKE '%lehenga%' OR LOWER(`name`) LIKE '%lehnga%';
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/suit.svg' WHERE LOWER(`name`) LIKE '%suit%' OR LOWER(`name`) LIKE '%blazer%';
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/dress.svg' WHERE LOWER(`name`) LIKE '%dress%' OR LOWER(`name`) LIKE '%gown%';
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/saree.svg' WHERE LOWER(`name`) LIKE '%saree%' OR LOWER(`name`) LIKE '%sari%';
UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/blouse.svg' WHERE LOWER(`name`) LIKE '%blouse%' OR LOWER(`name`) LIKE '%choli%';

