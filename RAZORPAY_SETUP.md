# Razorpay Payment Gateway Setup Guide

This guide explains how to set up Razorpay payment gateway for subscription plan purchases in the admin panel.

## Features

- Customer details form (Name, Email, Phone Number) in the upgrade modal
- Razorpay test mode integration
- Secure payment processing
- Automatic subscription upgrade after successful payment
- Payment verification and database logging

## Setup Instructions

### 1. Get Razorpay Test Keys

1. Go to [Razorpay Dashboard](https://dashboard.razorpay.com/)
2. Sign up or log in to your account
3. Navigate to **Settings** → **API Keys**
4. Generate test keys (keys starting with `rzp_test_`)
5. Copy your **Key ID** and **Key Secret**

### 2. Configure Razorpay Keys

Open `config/config.php` and update the Razorpay configuration:

```php
define('RAZORPAY_KEY_ID', 'rzp_test_YOUR_ACTUAL_KEY_ID');
define('RAZORPAY_KEY_SECRET', 'YOUR_ACTUAL_KEY_SECRET');
```

**OR** set environment variables:

```bash
RAZORPAY_KEY_ID=rzp_test_YOUR_KEY_ID
RAZORPAY_KEY_SECRET=YOUR_KEY_SECRET
```

### 3. Database Tables

The system will automatically create the following tables when first used:
- `razorpay_orders` - Stores order details
- `razorpay_payments` - Stores payment records

### 4. Testing

1. Log in to the admin panel
2. Navigate to **Subscriptions** page
3. Click on any plan's "Upgrade" or "Switch" button
4. Fill in the customer details form:
   - Full Name
   - Email Address
   - Phone Number (10 digits)
5. Click **Pay Now**
6. The Razorpay payment modal will open
7. Use Razorpay test cards for testing:
   - **Card Number**: 4111 1111 1111 1111
   - **CVV**: Any 3 digits (e.g., 123)
   - **Expiry**: Any future date (e.g., 12/25)
   - **Name**: Any name

### 5. Test Cards

Razorpay provides various test cards for different scenarios:

| Card Number | Scenario |
|------------|----------|
| 4111 1111 1111 1111 | Successful payment |
| 5104 0600 0000 0008 | Successful payment (Mastercard) |
| 4012 0010 3714 1112 | 3D Secure authentication |
| 5104 0600 0000 0008 | 3D Secure authentication |

For more test cards, visit: https://razorpay.com/docs/payments/test-cards/

## How It Works

1. **User selects a plan** → Modal opens with customer details form
2. **User fills form and clicks Pay Now** → Form is validated
3. **Order is created** → Backend creates Razorpay order via API
4. **Razorpay modal opens** → User completes payment
5. **Payment is verified** → Backend verifies payment signature
6. **Subscription is updated** → Company subscription is upgraded
7. **Success message** → User sees confirmation and page reloads

## Files Modified/Created

- `admin/subscriptions.php` - Added form and Razorpay integration
- `admin/ajax/create_razorpay_order.php` - Creates Razorpay orders
- `admin/ajax/verify_razorpay_payment.php` - Verifies payments and updates subscription
- `config/config.php` - Added Razorpay configuration

## Production Setup

For production:

1. Switch to live mode in `config/config.php`:
   ```php
   define('RAZORPAY_MODE', 'live');
   ```

2. Use live keys (starting with `rzp_live_`):
   ```php
   define('RAZORPAY_KEY_ID', 'rzp_live_YOUR_LIVE_KEY_ID');
   define('RAZORPAY_KEY_SECRET', 'YOUR_LIVE_KEY_SECRET');
   ```

3. Set up webhooks (optional but recommended):
   - Go to Razorpay Dashboard → Settings → Webhooks
   - Add webhook URL: `https://yourdomain.com/admin/ajax/razorpay_webhook.php`
   - Select events: `payment.captured`, `payment.failed`

## Troubleshooting

### Payment modal not opening
- Check browser console for errors
- Verify Razorpay script is loaded: `https://checkout.razorpay.com/v1/checkout.js`
- Check that Razorpay keys are correctly configured

### Order creation fails
- Verify Razorpay keys are correct
- Check server can make HTTPS requests to Razorpay API
- Check error logs for detailed error messages

### Payment verification fails
- Ensure signature verification is working
- Check that payment amount matches order amount
- Verify payment status is 'captured' or 'authorized'

## Support

For Razorpay API documentation, visit: https://razorpay.com/docs/

For issues with this integration, check the error logs or contact support.

