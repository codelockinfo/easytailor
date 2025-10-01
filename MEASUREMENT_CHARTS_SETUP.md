# Measurement Charts Feature Setup

## Overview
This feature allows you to assign measurement guide diagrams/charts for each cloth type. When users add measurements for a customer, they will see the appropriate measurement chart based on the selected cloth type.

## Setup Instructions

### 1. Update Database Schema
Run the SQL migration to add the measurement chart field to cloth_types table:

```sql
-- Run this in phpMyAdmin or MySQL command line:
source database/add_measurement_charts.sql
```

Or manually run:
```sql
ALTER TABLE `cloth_types` 
ADD COLUMN `measurement_chart_image` VARCHAR(255) DEFAULT NULL 
AFTER `category`;
```

### 2. Default Measurement Charts
Pre-built SVG measurement charts are already included in: `uploads/measurement-charts/`

Available charts:
- **pants.svg** - For pants/trousers (Waist, Hip, Length, Inseam)
- **shirt.svg** - For shirts (Chest, Waist, Length, Shoulder, Sleeve)
- **kurta.svg** - For kurta/kameez (Chest, Waist, Length, Shoulder, Sleeve, Neck)
- **lehenga.svg** - For lehenga (Waist, Hip, Length, Bottom Flair)
- **suit.svg** - For suits/blazers (Chest, Waist, Jacket Length, Shoulder, Sleeve, Lapel)

### 3. Assign Charts to Cloth Types

#### Option A: Automatic Assignment (Recommended)
Run the UPDATE statements from `database/add_measurement_charts.sql` to automatically assign charts based on cloth type names.

#### Option B: Manual Assignment
1. Go to **Cloth Types** page
2. Click **Edit** on any cloth type
3. Upload or select a measurement chart image/SVG
4. Save

### 4. How It Works

When adding/editing measurements:
1. Select a customer
2. Select a cloth type
3. **The measurement guide will automatically appear below**
4. Add measurement values according to the guide
5. Save

## Creating Custom Measurement Charts

You can create your own measurement charts:

### Using SVG (Recommended)
- SVG files are scalable and look crisp at any size
- Use tools like Inkscape, Adobe Illustrator, or Figma
- Include labeled measurement points (A, B, C, etc.)
- Add descriptions for each measurement point
- Save as `.svg` file

### Using Images
- Use PNG or JPG format
- Recommended size: 600x800 pixels or larger
- Ensure good contrast and readability
- Clearly label measurement points

### Upload Process
1. Go to **Cloth Type Management**
2. Edit the cloth type or create new one
3. Click "Choose File" under **Measurement Chart**
4. Select your custom chart image/SVG
5. Save

## Features

- ✅ Automatic chart display based on cloth type selection
- ✅ Support for SVG and image formats (PNG, JPG, GIF)
- ✅ Pre-built professional measurement charts
- ✅ Responsive design - works on all devices
- ✅ Easy to update and customize
- ✅ Visual guide helps reduce measurement errors

## File Locations

- **Cloth Types Management**: `cloth-types.php`
- **Measurements Management**: `measurements.php`
- **Charts Directory**: `uploads/measurement-charts/`
- **Database Migration**: `database/add_measurement_charts.sql`

## Troubleshooting

**Chart not showing?**
- Verify the cloth type has a measurement chart assigned
- Check file path in database
- Ensure file exists in `uploads/measurement-charts/` directory
- Check file permissions (should be readable)

**Upload fails?**
- Check `uploads/measurement-charts/` directory permissions
- Verify file size is under upload limit
- Ensure file type is allowed (image/* or .svg)

## Support

For issues or questions, refer to the main documentation or check:
- Database schema: `database/schema.sql`
- Config file: `config/config.php`

