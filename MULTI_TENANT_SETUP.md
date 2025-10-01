# Multi-Tenant Setup Guide
## Convert to SaaS - Multiple Tailor Shops System

This guide will help you convert the system from single-shop to multi-tenant (multiple tailor shops can register and use the system independently).

---

## 🎯 What This Enables

- ✅ **Multiple tailor shops** can register independently
- ✅ Each shop has **isolated data** (customers, orders, etc.)
- ✅ Shop owners can **manage their own team** (tailors, staff)
- ✅ Each shop has **custom branding** (logo, company name)
- ✅ **Subscription plans** support (free, basic, premium)
- ✅ Each shop **manages only their data**

---

## 📋 Setup Steps

### Step 1: Run Database Migration

Open **phpMyAdmin** and run the SQL file:
```
database/add_multi_tenant.sql
```

This will:
1. Create `companies` table
2. Add `company_id` to all relevant tables
3. Update unique constraints for multi-tenancy

### Step 2: Update Existing Data (If You Have Data)

If you already have customers/orders in your database, you need to:

```sql
-- Create a default company for existing data
INSERT INTO companies (company_name, owner_name, business_email, business_phone, status) 
VALUES ('Main Tailor Shop', 'Admin', 'admin@tailoring.com', '1234567890', 'active');

-- Get the company ID (will be 1 if this is first company)
SET @company_id = LAST_INSERT_ID();

-- Update existing users
UPDATE users SET company_id = @company_id WHERE company_id IS NULL;

-- Update existing customers
UPDATE customers SET company_id = @company_id WHERE company_id IS NULL OR company_id = 0;

-- Update existing orders
UPDATE orders SET company_id = @company_id WHERE company_id IS NULL OR company_id = 0;

-- Update other tables similarly
UPDATE measurements SET company_id = @company_id WHERE company_id IS NULL OR company_id = 0;
UPDATE invoices SET company_id = @company_id WHERE company_id IS NULL OR company_id = 0;
UPDATE payments SET company_id = @company_id WHERE company_id IS NULL OR company_id = 0;
UPDATE expenses SET company_id = @company_id WHERE company_id IS NULL OR company_id = 0;
UPDATE cloth_types SET company_id = @company_id WHERE company_id IS NULL;
```

### Step 3: Test Registration

Visit: `http://localhost/tailoring/register.php`

Fill in the registration form with:
- Company/Shop name
- Owner name
- Business email
- Business phone
- Address details
- Logo (optional)
- Username and password

### Step 4: Login

After registration, login with the credentials you created.

---

## 🚀 How It Works

### Registration Flow:
```
1. New tailor visits register.php
2. Fills in business details
3. System creates:
   - Company record
   - Admin user for that company
4. User can login and access their dashboard
5. All data is isolated to their company
```

### Data Isolation:
- Each company sees **only their own**:
  - Customers
  - Orders
  - Measurements
  - Invoices
  - Payments
  - Users/Team members
  - Cloth types

---

## 📂 New Files Created

1. **`register.php`** - Registration page for new tailor shops
2. **`models/Company.php`** - Company model
3. **`company-settings.php`** - Manage company profile
4. **`database/add_multi_tenant.sql`** - Database migration

## 📝 Modified Files

1. **`config/config.php`** - Added company helper functions
2. **`controllers/AuthController.php`** - Added company_id to session
3. **`login.php`** - Added registration link, fixed form resubmission

---

## 🔐 User Roles in Multi-Tenant System

Each company can have:
- **Admin** - Company owner (full access to their shop)
- **Tailor** - Can be assigned orders
- **Staff** - General operations
- **Cashier** - Handle payments

---

## 💡 Next Steps (After Setup)

### 1. Update All Models
Each model (Customer, Order, etc.) needs to filter by `company_id`:

```php
// Example in Customer model
public function getCompanyCustomers($company_id) {
    return $this->findAll(['company_id' => $company_id]);
}
```

### 2. Update All Pages
Add company_id filter to all queries:

```php
$companyId = get_company_id();
$customers = $customerModel->findAll(['company_id' => $companyId]);
```

### 3. Test Thoroughly
- Register new company
- Login
- Add customers, orders
- Logout
- Login as different company
- Verify data isolation

---

## 🛡️ Security Features

- ✅ Each company data is isolated
- ✅ Users can only access their company data
- ✅ Foreign key constraints ensure data integrity
- ✅ CSRF protection on all forms
- ✅ Password hashing
- ✅ Session management

---

## 📊 Subscription Plans

- **Free**: 30 days, 50 customers, 100 orders
- **Basic**: $29.99/month, 200 customers, 500 orders
- **Premium**: $59.99/month, 500 customers, 1000 orders
- **Enterprise**: $99.99/month, unlimited

---

## 🔧 Troubleshooting

**Registration fails?**
- Check database migration ran successfully
- Verify `companies` table exists
- Check file upload permissions

**Can't see customers after migration?**
- Run the "Update Existing Data" SQL above
- Verify company_id is set correctly

**Foreign key errors?**
- Ensure all tables have company_id column
- Check constraints are created properly

---

## 🎨 Customization

Each company can customize:
- Company logo
- Company name
- Contact information
- Currency settings
- Timezone
- Branding (future enhancement)

---

## 📞 Support

For issues, check:
- Database schema: `database/schema.sql`
- Multi-tenant migration: `database/add_multi_tenant.sql`
- Company model: `models/Company.php`

---

**This converts your system into a powerful multi-tenant SaaS platform!** 🚀

