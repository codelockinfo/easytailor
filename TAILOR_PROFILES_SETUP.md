# Tailor Profiles Feature - Setup Guide

## Overview

This feature adds a "Tailors Near You" section to the landing page, allowing visitors to discover registered tailors with their profiles, ratings, and contact information. It includes a responsive slider on the homepage and a dedicated search/filter page.

## 📋 Features

### Landing Page Section
- ✅ Responsive slider showing featured tailors
- ✅ Auto-play carousel with manual navigation
- ✅ "Show All" button linking to full listing page
- ✅ Mobile and desktop optimized

### Full Tailor Listing Page
- ✅ Search by keyword (shop name, city, specialty)
- ✅ Filter by city, state, and minimum rating
- ✅ Sort by rating, reviews, experience, or name
- ✅ Pagination with customizable results per page
- ✅ Responsive card layout
- ✅ Direct call and WhatsApp contact buttons

### Admin Management
- ✅ Complete CRUD operations for tailor profiles
- ✅ Statistics dashboard
- ✅ Featured and verified badges
- ✅ Status management (active/pending/inactive)

## 🚀 Installation Steps

### Step 1: Run Database Migration

Execute the SQL migration to create the tailor_profiles table:

```sql
-- Navigate to your database (phpMyAdmin, MySQL Workbench, or command line)
-- Execute the file:
SOURCE database/add_tailor_profiles.sql;

-- OR copy and paste the content from:
-- database/add_tailor_profiles.sql
```

**Local Environment:**
```bash
# Using MySQL command line
mysql -u root tailoring_management < database/add_tailor_profiles.sql
```

**Live Environment:**
- Log into your cPanel
- Navigate to phpMyAdmin
- Select your database (u402017191_tailorpro)
- Click on "Import" tab
- Choose `add_tailor_profiles.sql` file
- Click "Go"

### Step 2: Create Upload Directory

Create directory for tailor profile images:

```bash
# Create directory
mkdir uploads/tailor-profiles

# Set permissions (if on Linux/Unix)
chmod 755 uploads/tailor-profiles
```

### Step 3: Add Default Images

Place default images in the uploads folder:
- `uploads/logos/default-shop.png` - Default shop image
- `uploads/logos/default-logo.png` - Default logo image

### Step 4: Verify File Structure

Ensure all files are in place:

```
tailoring/
├── ajax/
│   ├── filter_tailors.php              ✅ (Created)
│   └── get_tailor_locations.php        ✅ (Created)
├── admin/
│   ├── ajax/
│   │   └── manage_tailor_profile.php   ✅ (Created)
│   └── tailor-profiles.php             ✅ (Created)
├── database/
│   └── add_tailor_profiles.sql         ✅ (Created)
├── landing/
│   ├── assets/
│   │   └── css/
│   │       └── style.css               ✅ (Updated)
│   └── index.html                      ✅ (Updated)
├── models/
│   └── TailorProfile.php               ✅ (Created)
├── uploads/
│   └── tailor-profiles/                ✅ (Create this)
└── tailors.php                         ✅ (Created)
```

### Step 5: Test the Setup

1. **Database Check:**
   - Verify table creation: `SHOW TABLES LIKE 'tailor_profiles';`
   - Check sample data: `SELECT COUNT(*) FROM tailor_profiles;`
   - Should show 8 sample tailor profiles

2. **Landing Page Test:**
   - Visit: `http://localhost/tailoring/landing/index.html`
   - Scroll to "Tailors Near You" section
   - Verify slider is working
   - Check if sample tailors are displayed

3. **Full Listing Page Test:**
   - Click "Show All Tailors" button
   - Verify all tailors are displayed
   - Test search functionality
   - Test filters (city, state, rating)
   - Test pagination

4. **Admin Panel Test:**
   - Login to admin: `http://localhost/tailoring/admin/login.php`
   - Navigate to: `http://localhost/tailoring/admin/tailor-profiles.php`
   - Verify statistics are displayed
   - Test adding new tailor profile
   - Test editing existing profile
   - Test delete functionality

## 📁 Database Schema

### tailor_profiles Table

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| user_id | INT | Link to users table (optional) |
| shop_name | VARCHAR(200) | Name of the tailor shop |
| owner_name | VARCHAR(100) | Owner's name |
| email | VARCHAR(100) | Email address |
| phone | VARCHAR(20) | Phone number |
| whatsapp | VARCHAR(20) | WhatsApp number |
| address | TEXT | Full address |
| city | VARCHAR(100) | City name |
| state | VARCHAR(100) | State name |
| postal_code | VARCHAR(20) | Postal/ZIP code |
| latitude | DECIMAL(10,8) | GPS latitude |
| longitude | DECIMAL(11,8) | GPS longitude |
| shop_image | VARCHAR(255) | Shop image path |
| logo_image | VARCHAR(255) | Logo image path |
| description | TEXT | Shop description |
| specialties | JSON | Array of specialties |
| working_hours | JSON | Working hours object |
| rating | DECIMAL(2,1) | Average rating (0-5) |
| total_reviews | INT | Number of reviews |
| years_experience | INT | Years in business |
| is_verified | TINYINT | Verified badge |
| is_featured | TINYINT | Featured on homepage |
| status | ENUM | active/inactive/pending |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

## 🎨 Customization

### Change Number of Tailors in Slider

Edit `landing/index.html` line 870:

```javascript
// Change limit=8 to desired number
fetch('../ajax/filter_tailors.php?limit=8&sort=rating')
```

### Change Slider Autoplay Speed

Edit `landing/index.html` line 951-953:

```javascript
autoplay: {
    delay: 3000,  // Change to desired milliseconds
    disableOnInteraction: false,
},
```

### Modify Slider Breakpoints

Edit `landing/index.html` line 963-976:

```javascript
breakpoints: {
    640: { slidesPerView: 2 },   // Tablets
    768: { slidesPerView: 3 },   // Small laptops
    1024: { slidesPerView: 4 },  // Desktop
}
```

### Change Results Per Page

Edit `tailors.php` line 303:

```javascript
const perPage = 12;  // Change to desired number
```

## 🔧 Troubleshooting

### Issue: Slider Not Showing

**Solution:**
1. Check browser console for errors
2. Verify Swiper.js is loading: View page source, search for "swiper"
3. Check AJAX endpoint: `../ajax/filter_tailors.php`
4. Verify database has sample data

### Issue: Images Not Displaying

**Solution:**
1. Check image paths in database
2. Verify `uploads/tailor-profiles/` directory exists
3. Check file permissions (755 or 777)
4. Add default images to `uploads/logos/`

### Issue: Filter Not Working

**Solution:**
1. Check browser console for JavaScript errors
2. Verify AJAX endpoint: `ajax/filter_tailors.php`
3. Test endpoint directly in browser
4. Check database connection

### Issue: Admin Page Not Loading

**Solution:**
1. Verify you're logged in as admin
2. Check session is active
3. Verify file path: `admin/tailor-profiles.php`
4. Check database connection

## 🌐 API Endpoints

### Get Filtered Tailors
```
GET /ajax/filter_tailors.php

Parameters:
- keyword: Search term (optional)
- city: Filter by city (optional)
- state: Filter by state (optional)
- min_rating: Minimum rating (optional)
- specialty: Filter by specialty (optional)
- sort: Sort field (rating/reviews/experience/name)
- order: Sort order (ASC/DESC)
- limit: Results per page (default: 12)
- offset: Pagination offset (default: 0)

Response:
{
  "success": true,
  "data": [...],
  "pagination": {
    "total": 8,
    "current_page": 1,
    "total_pages": 1,
    "per_page": 12,
    "offset": 0
  }
}
```

### Get Locations
```
GET /ajax/get_tailor_locations.php

Response:
{
  "success": true,
  "data": {
    "cities": ["Mumbai", "Delhi", ...],
    "states": ["Maharashtra", "Delhi", ...]
  }
}
```

### Manage Tailor Profile (Admin)
```
POST /admin/ajax/manage_tailor_profile.php

Parameters:
- action: add/edit/delete
- id: Tailor profile ID (for edit/delete)
- [other fields]: As needed

Response:
{
  "success": true,
  "message": "Operation successful"
}
```

## 📱 Responsive Design

The feature is fully responsive and optimized for:

- ✅ Mobile phones (320px - 767px)
- ✅ Tablets (768px - 1023px)
- ✅ Laptops (1024px - 1439px)
- ✅ Desktops (1440px+)

## 🔐 Security Considerations

1. **Admin Panel**: Only accessible by admin users
2. **AJAX Endpoints**: Validation on all inputs
3. **SQL Injection**: Using prepared statements
4. **XSS Protection**: HTML escaping on output
5. **CSRF Protection**: Session-based authentication

## 📊 Sample Data

The migration includes 8 sample tailor profiles:
1. Elite Tailors - Mumbai
2. Fashion Stitch - Delhi
3. Stitch Perfect - Ahmedabad
4. Royal Tailors - Jaipur
5. Modern Stitches - Bangalore
6. Classic Cuts - Hyderabad
7. Trendy Threads - Kochi
8. Perfect Fit Tailors - Pune

## 🎯 Future Enhancements

Potential improvements:
- [ ] Google Maps integration with location markers
- [ ] Customer reviews and ratings system
- [ ] Image upload functionality for shop/logo
- [ ] Booking/appointment system
- [ ] Distance calculation from user location
- [ ] Social media links
- [ ] Working hours display
- [ ] Gallery of completed work
- [ ] Push notifications for new tailors

## 📞 Support

For issues or questions:
- Email: codelockinfo@gmail.com
- Phone: +91 7600464414

---

**Last Updated**: November 3, 2025
**Version**: 1.0
**Compatible With**: Tailoring Management System v2.0+






