# How to Replace Shirt Measurement Chart with Your Image

## What You Need
- Your shirt measurement diagram (like the professional one you have)
- Transparent background version of the image

## Step-by-Step Guide

### 1. Remove Background from Your Image

**Option A: Using Remove.bg (Easiest - Free)**
1. Go to: https://www.remove.bg
2. Click "Upload Image"
3. Select your shirt measurement image
4. Wait for processing (5-10 seconds)
5. Click "Download" to get PNG with transparent background
6. Save as: `shirt-measurement.png`

**Option B: Using Photoshop/GIMP**
- Use magic wand or selection tools to remove white background
- Export as PNG with transparency

**Option C: Using PowerPoint (Quick)**
1. Insert your image in PowerPoint
2. Click on image → "Format" → "Remove Background"
3. Adjust areas to keep/remove
4. Right-click image → "Save as Picture"
5. Save as PNG

### 2. Upload to Your System

**Method 1: Via Cloth Types Page (Recommended)**
1. Login to your tailoring system
2. Go to **Cloth Types** page
3. Find "Shirt" in the list
4. Click the **Edit** button (pencil icon)
5. Scroll to **"Measurement Chart (Image/SVG)"** field
6. Click **"Choose File"**
7. Select your `shirt-measurement.png` file
8. You'll see a preview below
9. Click **"Save Cloth Type"**
10. Done! ✅

**Method 2: Direct File Replacement**
1. Rename your file to `shirt.png`
2. Copy it to: `uploads/measurement-charts/shirt.png`
3. Update database:
   ```sql
   UPDATE cloth_types 
   SET measurement_chart_image = 'uploads/measurement-charts/shirt.png' 
   WHERE LOWER(name) LIKE '%shirt%';
   ```

### 3. Test It

1. Go to **Measurements** page
2. Click **"Add Measurement"**
3. Select any customer
4. Select **"Shirt"** from Cloth Type dropdown
5. 🎉 Your professional measurement image should appear!

## Image Requirements

✅ **Recommended:**
- Format: PNG with transparent background
- Size: 800-1200px width
- File size: Under 500KB
- Quality: High resolution for clarity

✅ **Acceptable:**
- JPG/JPEG (with white background)
- SVG (vector format)
- GIF (with transparency)

## Troubleshooting

**Image not showing?**
- Clear browser cache (Ctrl + F5)
- Check file path is correct
- Verify file permissions (readable)
- Make sure cloth type "Shirt" has chart assigned

**Background not transparent?**
- Re-process image with remove.bg
- Save as PNG, not JPG
- Check transparency in image editor

**Image too large/small?**
- The system automatically scales images
- Recommended width: 600-1000px
- Use image compression tools if file is too large

## Pro Tips

1. **Use Consistent Style**: Use the same style for all cloth types
2. **Label Clearly**: Make sure measurement points are clearly labeled
3. **High Contrast**: Use colors that stand out against backgrounds
4. **Save Originals**: Keep backup of your original images

## Need Help?

- Check the preview in Cloth Types edit modal
- View all charts at: `check_measurement_charts.php`
- Test in Measurements page before production use

---

## Quick Reference: Supported Formats

| Format | Transparency | Best For | File Size |
|--------|-------------|----------|-----------|
| PNG | ✅ Yes | Professional diagrams | Medium |
| SVG | ✅ Yes | Scalable graphics | Small |
| JPG | ❌ No | Photos only | Small |
| GIF | ✅ Yes | Simple graphics | Small |

**Recommendation:** Use PNG with transparent background for best results!

