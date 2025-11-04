# Setup Tailors Feature - Quick Guide

## ✅ What's Done:
1. ✅ "Tailors Near You" section added to main landing page (index.php)
2. ✅ Section appears BEFORE "Why Choose Our Platform?"
3. ✅ Separate `tailors.php` page with search/filter functionality
4. ✅ No admin changes (admin folder not modified)

## 🔧 Setup Database (Required):

### Step 1: Open phpMyAdmin
Visit: `http://localhost/phpmyadmin`

### Step 2: Select Database
Click on `tailoring_management` in the left sidebar

### Step 3: Import SQL File

**Method A - Import Tab:**
1. Click **"Import"** tab at the top
2. Click **"Choose File"** button
3. Navigate to: `C:\wamp64\www\tailoring\database\add_tailor_profiles.sql`
4. Click **"Go"** button at the bottom
5. Done! ✅

**Method B - SQL Tab:**
1. Click **"SQL"** tab at the top
2. Open file: `database/add_tailor_profiles.sql` in Notepad
3. Copy ALL content (Ctrl+A, Ctrl+C)
4. Paste in the SQL window (Ctrl+V)
5. Click **"Go"** button
6. Done! ✅

---

## 🧪 Test After Setup:

### 1. Main Landing Page:
```
http://localhost/tailoring/
```
- Scroll past "Features" section
- You'll see "Tailors Near You" with slider
- Appears BEFORE "Why Choose Our Platform?"

### 2. Full Tailor Listing:
```
http://localhost/tailoring/tailors.php
```
- Shows all 8 sample tailors
- Test search and filters

---

## 📊 Sample Data:
8 tailors will be added:
- Elite Tailors (Mumbai) - 4.8★
- Fashion Stitch (Delhi) - 4.6★
- Stitch Perfect (Ahmedabad) - 4.7★
- Royal Tailors (Jaipur) - 4.9★
- Modern Stitches (Bangalore) - 4.5★
- Classic Cuts (Hyderabad) - 4.7★
- Trendy Threads (Kochi) - 4.8★
- Perfect Fit Tailors (Pune) - 4.6★

---

## 🎯 Section Order on Landing Page:
1. Hero
2. About
3. Features (with slick carousel)
4. **🆕 Tailors Near You** ← NEW!
5. Why Choose Our Platform? (Benefits)
6. How It Works
7. Screenshots
8. Testimonials
9. Pricing
10. Final CTA
11. Footer

---

## 📁 Key Files:
- ✅ `index.php` - Main landing page (UPDATED)
- ✅ `tailors.php` - Full listing page (NEW)
- ✅ `ajax/filter_tailors.php` - Filter endpoint (NEW)
- ✅ `ajax/get_tailor_locations.php` - Locations endpoint (NEW)
- ✅ `models/TailorProfile.php` - Database model (NEW)
- ✅ `database/add_tailor_profiles.sql` - SQL migration (NEW)
- ✅ `assets/css/style.css` - Styles added (UPDATED)
- ❌ Admin folder - NOT modified (as requested)

---

## ❌ Error: Table doesn't exist?

**Solution:** You need to run the SQL file first (Step 1-3 above)

---

## 📞 Need Help?

1. Make sure WAMP is running (green icon)
2. Run the SQL file in phpMyAdmin
3. Clear browser cache (Ctrl+F5)
4. Check browser console for errors (F12)

---

**Ready?** Go to phpMyAdmin and import the SQL file! 🚀

