# Setup Companies Listing Feature

## ✅ **What Changed:**

Instead of creating a separate `tailor_profiles` table, we're now using the existing **`companies`** table to show registered tailor shops on the landing page!

This is better because:
- ✅ Uses existing infrastructure
- ✅ Shows real registered companies
- ✅ No duplicate data
- ✅ Integrates with existing multi-tenant system

---

## 📋 **Setup Steps:**

### Step 1: Run SQL Migration

**Open phpMyAdmin** (`http://localhost/phpmyadmin`) and import:

**File:** `database/setup_companies_for_listing.sql`

This will:
- Create/update `companies` table with new fields
- Add: description, specialties, rating, reviews, years_experience
- Add: is_verified, is_featured, show_on_listing flags
- Add: whatsapp field
- Insert 8 sample companies

---

### Step 2: Test Your Setup

**Landing Page:**
```
http://localhost/tailoring/
```
- Scroll to "Tailors Near You" section
- Should show slider with companies
- Each card shows: shop name, owner, rating, location, contact

**Full Listing:**
```
http://localhost/tailoring/tailors.php
```
- Shows all companies/tailor shops
- Search and filter functionality
- Call and WhatsApp buttons

---

## 📊 **Sample Companies Included:**

8 sample tailor shops will be added:

1. **Elite Tailors** (Mumbai, Maharashtra)
   - Rating: 4.8★ | Reviews: 125 | Experience: 15 years
   - Specialties: Wedding Suits, Party Wear, Formal Suits

2. **Fashion Stitch** (Delhi)
   - Rating: 4.6★ | Reviews: 98 | Experience: 10 years
   - Specialties: Ladies Suits, Lehenga, Designer Wear

3. **Stitch Perfect** (Ahmedabad, Gujarat)
   - Rating: 4.7★ | Reviews: 156 | Experience: 12 years
   - Specialties: Men's Shirts, Pants, Kurta Pajama

4. **Royal Tailors** (Jaipur, Rajasthan)
   - Rating: 4.9★ | Reviews: 210 | Experience: 20 years
   - Specialties: Wedding Sherwanis, Traditional Wear

5. **Modern Stitches** (Bangalore, Karnataka)
   - Rating: 4.5★ | Reviews: 82 | Experience: 8 years
   - Specialties: Corporate Wear, Party Dresses

6. **Classic Cuts** (Hyderabad, Telangana)
   - Rating: 4.7★ | Reviews: 143 | Experience: 18 years
   - Specialties: Sherwanis, Pathani Suits, Formal Wear

7. **Trendy Threads** (Kochi, Kerala)
   - Rating: 4.8★ | Reviews: 167 | Experience: 14 years
   - Specialties: Bridal Wear, Designer Sarees

8. **Perfect Fit Tailors** (Pune, Maharashtra)
   - Rating: 4.6★ | Reviews: 119 | Experience: 11 years
   - Specialties: Corporate Shirts, Blazers, Casual Wear

---

## 📂 **Files Updated:**

**Modified:**
- ✅ `models/Company.php` - Added listing methods
- ✅ `ajax/filter_tailors.php` - Now uses companies table
- ✅ `ajax/get_tailor_locations.php` - Now uses companies table
- ✅ `tailors.php` - Now shows companies
- ✅ `index.php` - Loads companies in slider

**Deleted:**
- ❌ `models/TailorProfile.php` (not needed)
- ❌ `database/add_tailor_profiles.sql` (not needed)

**Created:**
- ✅ `database/setup_companies_for_listing.sql` - Main migration

---

## 🎯 **New Company Table Fields:**

The companies table now has these fields for public listing:

| Field | Purpose |
|-------|---------|
| description | Shop description for public viewing |
| specialties | JSON array of what they specialize in |
| working_hours | JSON object with daily hours |
| rating | Average rating (0-5) |
| total_reviews | Number of reviews |
| years_experience | Years in business |
| is_verified | Shows verified badge |
| is_featured | Shows on homepage slider |
| show_on_listing | Show on public listing (yes/no) |
| whatsapp | WhatsApp number for direct contact |

---

## 🔧 **How It Works:**

### Registration:
1. User registers their tailor shop
2. Creates a company record
3. Company appears on public listing (if `show_on_listing = 1`)

### Public Display:
1. Landing page shows featured companies (is_featured = 1)
2. Tailors page shows all companies (show_on_listing = 1)
3. Users can search/filter by location, rating, etc.

---

## ✅ **Quick Setup:**

### Method 1: phpMyAdmin Import
1. Open `http://localhost/phpmyadmin`
2. Select database: `tailoring_management`
3. Click "Import" tab
4. Choose file: `database/setup_companies_for_listing.sql`
5. Click "Go"
6. Done! ✅

### Method 2: Copy-Paste SQL
1. Open `database/setup_companies_for_listing.sql` in Notepad
2. Copy all content (Ctrl+A, Ctrl+C)
3. In phpMyAdmin, click "SQL" tab
4. Paste (Ctrl+V)
5. Click "Go"
6. Done! ✅

---

## 🧪 **After Setup:**

1. Visit: `http://localhost/tailoring/`
2. Scroll to "Tailors Near You" section
3. Should see 8 companies in slider
4. Click "Show All Tailors" → See full listing
5. Test search and filters

---

## 🔄 **For New Registered Users:**

When a user registers:
- Their company data goes into `companies` table
- Set `show_on_listing = 1` to appear on public listing
- Set `is_featured = 1` to appear on homepage slider
- Add description, specialties, working hours
- Users will see their shop on the landing page!

---

## 📞 **Features:**

✅ Responsive slider (1-4 shops per view)
✅ Auto-play every 3 seconds
✅ Search by shop name, city, owner
✅ Filter by location and rating
✅ Sort by rating, reviews, experience, name
✅ Pagination (12 per page)
✅ Call and WhatsApp buttons
✅ Verified and featured badges
✅ Star ratings display

---

## 🚀 **Next Step:**

**Import the SQL file:** `database/setup_companies_for_listing.sql`

Then test: `http://localhost/tailoring/`

---

✅ **Ready to use existing companies infrastructure!**


