<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="../favicon(2).png">

<?php
/**
 * Subscriptions Page
 * Tailoring Management System - Multi-Tenant
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

// Only admins can manage subscriptions
require_role('admin');

require_once 'models/Company.php';

$companyModel = new Company();
$message = '';
$messageType = '';

// Get company ID from session
$companyId = get_company_id();

if (!$companyId) {
    die('No company associated with your account. Please contact support.');
}

// Get messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// NOW include header (after all redirects are done)
$page_title = 'Subscription Plans';
require_once 'includes/header.php';

// Get company details
$company = $companyModel->find($companyId);

if (!$company) {
    echo '<div class="alert alert-danger">Company not found</div>';
    exit;
}

// Get company statistics
$companyStats = $companyModel->getCompanyStats($companyId);

// Load subscription plans from JSON file
$pricingData = json_decode(file_get_contents('../data/pricing.json'), true);
$plans = $pricingData['plans'] ?? [];

$currentPlan = $company['subscription_plan'];
$currentPlanData = $plans[$currentPlan] ?? $plans['free'];

// Calculate days remaining
$daysRemaining = 0;
if ($company['subscription_expiry']) {
    $expiry = strtotime($company['subscription_expiry']);
    $today = strtotime(date('Y-m-d'));
    $daysRemaining = max(0, floor(($expiry - $today) / (60 * 60 * 24)));
}
?>


<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Current Subscription Card -->
<div class="card mb-4">
    <div class="card-header bg-<?php echo $currentPlanData['color']; ?> text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">
                    <i class="fas fa-crown me-2"></i>Your Current Plan: <?php echo $currentPlanData['name']; ?>
                </h5>
            </div>
            <?php if ($daysRemaining > 0): ?>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-clock me-1"></i><?php echo $daysRemaining; ?> days remaining
                </span>
            <?php elseif ($daysRemaining == 0): ?>
                <span class="badge bg-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>Expires today!
                </span>
            <?php else: ?>
                <span class="badge bg-danger">
                    <i class="fas fa-times-circle me-1"></i>Expired
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="text-center p-3 border-end">
                    <h3 class="text-<?php echo $currentPlanData['color']; ?> mb-1">
                        <?php echo $companyStats['total_customers']; ?>
                    </h3>
                    <small class="text-muted">
                        Customers
                        <?php if ($currentPlanData['limits']['customers'] > 0): ?>
                            <br>of <?php echo number_format($currentPlanData['limits']['customers']); ?> limit
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 border-end">
                    <h3 class="text-<?php echo $currentPlanData['color']; ?> mb-1">
                        <?php echo $companyStats['total_orders']; ?>
                    </h3>
                    <small class="text-muted">
                        Orders
                        <?php if ($currentPlanData['limits']['orders'] > 0): ?>
                            <br>of <?php echo number_format($currentPlanData['limits']['orders']); ?> limit
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 border-end">
                    <h3 class="text-<?php echo $currentPlanData['color']; ?> mb-1">
                        <?php echo $companyStats['total_users']; ?>
                    </h3>
                    <small class="text-muted">
                        Team Members
                        <?php if ($currentPlanData['limits']['users'] > 0): ?>
                            <br>of <?php echo $currentPlanData['limits']['users']; ?> limit
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3">
                    <?php if ($company['subscription_expiry']): ?>
                        <h5 class="mb-1"><?php echo format_date($company['subscription_expiry'], 'M j, Y'); ?></h5>
                        <small class="text-muted">Expiry Date</small>
                    <?php else: ?>
                        <h5 class="mb-1">N/A</h5>
                        <small class="text-muted">No Expiry</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subscription Plans -->
<div class="row mb-4">
    <?php foreach ($plans as $planKey => $plan): ?>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100 <?php echo $planKey === $currentPlan ? 'border-' . $plan['color'] : ''; ?>" style="<?php echo $planKey === $currentPlan ? 'border-width: 3px;' : ''; ?>">
            <?php if (isset($plan['popular']) && $plan['popular']): ?>
                <div class="position-absolute top-0 start-50 translate-middle">
                    <span class="badge bg-success">
                        <i class="fas fa-star me-1"></i>Most Popular
                    </span>
                </div>
            <?php endif; ?>
            
            <div class="card-header bg-<?php echo $plan['color']; ?> text-white text-center">
                <h4 class="mb-0"><?php echo $plan['name']; ?></h4>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="text-center mb-4">
                    <?php if ($planKey !== 'free'): ?>
                    <div class="pricing-toggle mb-3">
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="pricing-<?php echo $planKey; ?>" id="monthly-<?php echo $planKey; ?>" checked>
                            <label class="btn btn-outline-primary btn-sm" for="monthly-<?php echo $planKey; ?>">Monthly</label>
                            
                            <input type="radio" class="btn-check" name="pricing-<?php echo $planKey; ?>" id="annual-<?php echo $planKey; ?>">
                            <label class="btn btn-outline-primary btn-sm" for="annual-<?php echo $planKey; ?>">Annual <span class="badge bg-success ms-1">10% OFF</span></label>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <h2 class="mb-0 monthly-price">
                        <?php if ($plan['price'] > 0): ?>
                            ₹<?php echo number_format($plan['price'], 0); ?>
                        <?php else: ?>
                            FREE
                        <?php endif; ?>
                    </h2>
                    <?php if ($planKey !== 'free'): ?>
                    <h2 class="mb-0 annual-price" style="display: none;">
                        <?php if ($plan['price_annual'] > 0): ?>
                            ₹<?php echo number_format($plan['price_annual'], 0); ?>
                        <?php else: ?>
                            FREE
                        <?php endif; ?>
                    </h2>
                    <?php endif; ?>
                    <small class="text-muted monthly-duration"><?php echo $plan['duration']; ?></small>
                    <?php if ($planKey !== 'free'): ?>
                    <small class="text-muted annual-duration" style="display: none;">per year</small>
                    <?php endif; ?>
                </div>
                
                <ul class="list-unstyled mb-4 flex-grow-1">
                    <?php foreach ($plan['features'] as $feature): ?>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <?php echo $feature; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($planKey === $currentPlan): ?>
                    <button class="btn btn-<?php echo $plan['color']; ?>" disabled>
                        <i class="fas fa-check-circle me-2"></i>Current Plan
                    </button>
                <?php elseif ($planKey === 'free'): ?>
                    <button class="btn btn-outline-<?php echo $plan['color']; ?>" disabled>
                        Trial Plan
                    </button>
                <?php else: ?>
                    <button class="btn btn-<?php echo $plan['color']; ?>" 
                            onclick="selectPlan('<?php echo $planKey; ?>', '<?php echo $plan['name']; ?>', <?php echo $plan['price']; ?>)">
                        <i class="fas fa-arrow-up me-2"></i>
                        <?php echo $planKey > $currentPlan ? 'Upgrade' : 'Switch'; ?> to <?php echo $plan['name']; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Plan Comparison Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-table me-2"></i>Detailed Plan Comparison
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Feature</th>
                        <th class="text-center">Free</th>
                        <th class="text-center">Basic</th>
                        <th class="text-center bg-primary text-white">Premium</th>
                        <th class="text-center">Enterprise</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Price (Monthly)</strong></td>
                        <td class="text-center">Free</td>
                        <td class="text-center">₹99/mo</td>
                        <td class="text-center bg-light">₹199/mo</td>
                        <td class="text-center">₹999/mo</td>
                    </tr>
                    <tr>
                        <td><strong>Price (Annual)</strong></td>
                        <td class="text-center">Free</td>
                        <td class="text-center">₹89/yr <small class="text-success">(10% OFF)</small></td>
                        <td class="text-center bg-light">₹179/yr <small class="text-success">(10% OFF)</small></td>
                        <td class="text-center">₹899/yr <small class="text-success">(10% OFF)</small></td>
                    </tr>
                    <tr>
                        <td>Customers</td>
                        <td class="text-center">30</td>
                        <td class="text-center">100</td>
                        <td class="text-center bg-light">500</td>
                        <td class="text-center">Unlimited</td>
                    </tr>
                    <tr>
                        <td>Orders</td>
                        <td class="text-center">50</td>
                        <td class="text-center">150</td>
                        <td class="text-center bg-light">1,000</td>
                        <td class="text-center">Unlimited</td>
                    </tr>
                    <tr>
                        <td>Team Members</td>
                        <td class="text-center">1</td>
                        <td class="text-center">3</td>
                        <td class="text-center bg-light">10</td>
                        <td class="text-center">Unlimited</td>
                    </tr>
                    <tr>
                        <td>Support</td>
                        <td class="text-center">Email</td>
                        <td class="text-center">Priority Email</td>
                        <td class="text-center bg-light">24/7 Support</td>
                        <td class="text-center">Dedicated Manager</td>
                    </tr>
                    <tr>
                        <td>SMS Notifications</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>Export Data</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>Custom Integrations</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>Training & Onboarding</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upgrade Modal -->
<div class="modal fade" id="upgradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-arrow-up me-2"></i>Upgrade Subscription
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="subscriptionForm">
                    <div class="text-center mb-4">
                        <i class="fas fa-crown fa-3x text-warning mb-3"></i>
                        <h4 id="selectedPlanName">Premium Plan</h4>
                        
                        <div class="pricing-toggle mb-3">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="modal-pricing" id="modal-monthly" checked>
                                <label class="btn btn-outline-primary btn-sm" for="modal-monthly">Monthly</label>
                                
                                <input type="radio" class="btn-check" name="modal-pricing" id="modal-annual">
                                <label class="btn btn-outline-primary btn-sm" for="modal-annual">Annual <span class="badge bg-success ms-1">10% OFF</span></label>
                            </div>
                        </div>
                        
                        <h2 class="text-primary mb-0">₹<span id="selectedPlanPrice">199</span><span id="selectedPlanDuration">/month</span></h2>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3"><i class="fas fa-user me-2"></i>Customer Details</h6>
                    
                    <div class="mb-3">
                        <label for="customerName" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="customerName" name="customerName" required placeholder="Enter your full name">
                        <div class="invalid-feedback">Please provide your full name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customerEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="customerEmail" name="customerEmail" required placeholder="Enter your email address">
                        <div class="invalid-feedback">Please provide a valid email address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customerPhone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">+91</span>
                            <input type="tel" 
                                   class="form-control" 
                                   id="customerPhone" 
                                   name="customerPhone" 
                                   required 
                                   placeholder="10-digit mobile number" 
                                   pattern="[0-9]{10}" 
                                   maxlength="10">
                        </div>
                        <small class="text-muted">Enter 10-digit mobile number (digits only)</small>
                        <div class="invalid-feedback">Please provide a valid 10-digit phone number.</div>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>You will be redirected to Razorpay secure payment gateway to complete the transaction.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="payNowBtn" onclick="proceedToPayment()">
                    <i class="fas fa-credit-card me-2"></i>Pay Now
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.border-primary { border-color: #0d6efd !important; }
.border-info { border-color: #0dcaf0 !important; }
.border-warning { border-color: #ffc107 !important; }
.border-secondary { border-color: #6c757d !important; }
</style>

<script>
let selectedPlan = '';
let selectedPlanData = {};

// Plan data for JavaScript
const planData = {
    <?php foreach ($plans as $key => $plan): ?>
    '<?php echo $key; ?>': {
        name: '<?php echo $plan['name']; ?>',
        price: <?php echo $plan['price']; ?>,
        price_annual: <?php echo $plan['price_annual']; ?>
    },
    <?php endforeach; ?>
};

function selectPlan(planKey, planName, planPrice) {
    selectedPlan = planKey;
    selectedPlanData = planData[planKey];
    
    document.getElementById('selectedPlanName').textContent = planName;
    updateModalPricing();
    
    const modal = new bootstrap.Modal(document.getElementById('upgradeModal'));
    modal.show();
}

function updateModalPricing() {
    const monthlyRadio = document.getElementById('modal-monthly');
    const annualRadio = document.getElementById('modal-annual');
    const priceElement = document.getElementById('selectedPlanPrice');
    const durationElement = document.getElementById('selectedPlanDuration');
    
    if (monthlyRadio.checked) {
        priceElement.textContent = selectedPlanData.price.toLocaleString('en-IN');
        durationElement.textContent = '/month';
    } else {
        priceElement.textContent = selectedPlanData.price_annual.toLocaleString('en-IN');
        durationElement.textContent = '/year';
    }
}

function proceedToPayment() {
    // Validate form
    const form = document.getElementById('subscriptionForm');
    const customerName = document.getElementById('customerName').value.trim();
    const customerEmail = document.getElementById('customerEmail').value.trim();
    const customerPhone = document.getElementById('customerPhone').value.trim();
    
    // Reset validation
    form.classList.remove('was-validated');
    document.getElementById('customerName').classList.remove('is-invalid');
    document.getElementById('customerEmail').classList.remove('is-invalid');
    document.getElementById('customerPhone').classList.remove('is-invalid');
    
    // Validate fields
    let isValid = true;
    
    if (!customerName) {
        document.getElementById('customerName').classList.add('is-invalid');
        isValid = false;
    }
    
    if (!customerEmail || !isValidEmail(customerEmail)) {
        document.getElementById('customerEmail').classList.add('is-invalid');
        isValid = false;
    }
    
    // Clean phone number and validate
    customerPhone = customerPhone.replace(/[^0-9]/g, '');
    if (!customerPhone || customerPhone.length !== 10) {
        document.getElementById('customerPhone').classList.add('is-invalid');
        isValid = false;
    } else {
        // Add +91 prefix for submission
        customerPhone = '+91' + customerPhone;
    }
    
    if (!isValid) {
        form.classList.add('was-validated');
        return;
    }
    
    // Get pricing details
    const annualRadio = document.getElementById('modal-annual');
    const isAnnual = annualRadio.checked;
    const price = isAnnual ? selectedPlanData.price_annual : selectedPlanData.price;
    const duration = isAnnual ? 'yearly' : 'monthly';
    
    // Disable button to prevent double submission
    const payBtn = document.getElementById('payNowBtn');
    payBtn.disabled = true;
    payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    
    // Create Razorpay order
    fetch('ajax/create_razorpay_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            plan_key: selectedPlan,
            plan_name: selectedPlanData.name,
            amount: price,
            duration: duration,
            customer_name: customerName,
            customer_email: customerEmail,
            customer_phone: customerPhone
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Initialize Razorpay checkout
            const options = {
                key: data.razorpay_key,
                amount: data.amount,
                currency: 'INR',
                name: 'Tailoring Management System',
                description: `${selectedPlanData.name} - ${duration}`,
                image: '', // Add your logo URL here if needed
                order_id: data.order_id,
                handler: function(response) {
                    // Payment successful
                    handlePaymentSuccess(response, {
                        plan_key: selectedPlan,
                        plan_name: selectedPlanData.name,
                        amount: price,
                        duration: duration,
                        customer_name: customerName,
                        customer_email: customerEmail,
                        customer_phone: customerPhone
                    });
                },
                prefill: {
                    name: customerName,
                    email: customerEmail,
                    contact: customerPhone
                },
                notes: {
                    plan_key: selectedPlan,
                    duration: duration
                },
                theme: {
                    color: '#0d6efd'
                },
                modal: {
                    ondismiss: function() {
                        // Re-enable button if user closes the modal
                        payBtn.disabled = false;
                        payBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Pay Now';
                    }
                }
            };
            
            const razorpay = new Razorpay(options);
            razorpay.open();
        } else {
            alert('Error: ' + (data.message || 'Failed to create payment order. Please try again.'));
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Pay Now';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Pay Now';
    });
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function handlePaymentSuccess(response, paymentData) {
    // Verify payment on server
    fetch('ajax/verify_razorpay_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_signature: response.razorpay_signature,
            ...paymentData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Track purchase event if provided in response (wait for gtag)
            if (data.ga4_event) {
                (function() {
                    var attempts = 0;
                    var maxAttempts = 50; // 5 seconds max wait time
                    var eventCode = data.ga4_event;
                    
                    function firePurchaseEvent() {
                        if (typeof gtag !== 'undefined' && typeof window.dataLayer !== 'undefined') {
                            try {
                                eval(eventCode);
                                console.log('GA4 purchase event fired successfully');
                            } catch (e) {
                                console.error('GA4 purchase event tracking error:', e);
                            }
                        } else {
                            attempts++;
                            if (attempts < maxAttempts) {
                                setTimeout(firePurchaseEvent, 100);
                            } else {
                                console.warn('GA4 not loaded after 5 seconds, purchase event may be lost');
                            }
                        }
                    }
                    
                    firePurchaseEvent();
                })();
            }
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('upgradeModal'));
            modal.hide();
            
            // Show success message and reload page
            alert('Payment successful! Your subscription has been upgraded.');
            window.location.reload();
        } else {
            alert('Payment verification failed: ' + (data.message || 'Please contact support.'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Payment verification error. Please contact support with your payment ID: ' + response.razorpay_payment_id);
    });
}

// Handle pricing toggle in modal
document.addEventListener('DOMContentLoaded', function() {
    // Setup phone validation
    setupPhoneValidation('customerPhone', '+91');
    
    const monthlyRadio = document.getElementById('modal-monthly');
    const annualRadio = document.getElementById('modal-annual');
    
    if (monthlyRadio && annualRadio) {
        monthlyRadio.addEventListener('change', updateModalPricing);
        annualRadio.addEventListener('change', updateModalPricing);
    }
    
    // Reset form when modal is closed
    const upgradeModal = document.getElementById('upgradeModal');
    if (upgradeModal) {
        upgradeModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('subscriptionForm');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
                document.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
            }
            const payBtn = document.getElementById('payNowBtn');
            if (payBtn) {
                payBtn.disabled = false;
                payBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Pay Now';
            }
        });
    }
    
    // Handle pricing toggles in plan cards (skip free plan)
    document.querySelectorAll('input[name^="pricing-"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const planKey = this.name.replace('pricing-', '');
            const card = this.closest('.card');
            const monthlyPrice = card.querySelector('.monthly-price');
            const annualPrice = card.querySelector('.annual-price');
            const monthlyDuration = card.querySelector('.monthly-duration');
            const annualDuration = card.querySelector('.annual-duration');
            
            if (this.id.includes('monthly')) {
                monthlyPrice.style.display = 'block';
                if (annualPrice) annualPrice.style.display = 'none';
                monthlyDuration.style.display = 'inline';
                if (annualDuration) annualDuration.style.display = 'none';
            } else {
                monthlyPrice.style.display = 'none';
                if (annualPrice) annualPrice.style.display = 'block';
                monthlyDuration.style.display = 'none';
                if (annualDuration) annualDuration.style.display = 'inline';
            }
        });
    });
});
</script>

<!-- Razorpay Checkout Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<?php require_once 'includes/footer.php'; ?>

<style>
    @media (max-width: 768px) {
        .border-end {
            border-right: none !important;
        }
    }
</style>