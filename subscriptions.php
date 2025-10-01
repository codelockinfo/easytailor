<?php
/**
 * Subscriptions Page
 * Tailoring Management System - Multi-Tenant
 */

// Include config first (before any output)
require_once 'config/config.php';

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

// Define subscription plans
$plans = [
    'free' => [
        'name' => 'Free Trial',
        'price' => 0,
        'duration' => '30 days',
        'features' => [
            'Up to 50 customers',
            'Up to 100 orders',
            'Basic reporting',
            'Email support',
            '1 user account'
        ],
        'limits' => [
            'customers' => 50,
            'orders' => 100,
            'users' => 1
        ],
        'color' => 'secondary'
    ],
    'basic' => [
        'name' => 'Basic Plan',
        'price' => 2499,
        'duration' => 'per month',
        'features' => [
            'Up to 200 customers',
            'Up to 500 orders',
            'Advanced reporting',
            'Priority email support',
            'Up to 3 users',
            'Custom cloth types',
            'Invoice generation'
        ],
        'limits' => [
            'customers' => 200,
            'orders' => 500,
            'users' => 3
        ],
        'color' => 'info',
        'popular' => false
    ],
    'premium' => [
        'name' => 'Premium Plan',
        'price' => 4999,
        'duration' => 'per month',
        'features' => [
            'Up to 1,000 customers',
            'Up to 2,000 orders',
            'Advanced reporting & analytics',
            'Priority support (24/7)',
            'Up to 10 users',
            'Custom branding',
            'SMS notifications',
            'Export data',
            'API access'
        ],
        'limits' => [
            'customers' => 1000,
            'orders' => 2000,
            'users' => 10
        ],
        'color' => 'primary',
        'popular' => true
    ],
    'enterprise' => [
        'name' => 'Enterprise Plan',
        'price' => 8299,
        'duration' => 'per month',
        'features' => [
            'Unlimited customers',
            'Unlimited orders',
            'Premium analytics & insights',
            'Dedicated account manager',
            'Unlimited users',
            'White-label solution',
            'Custom integrations',
            'Training & onboarding',
            'SLA guarantee',
            'Data migration support'
        ],
        'limits' => [
            'customers' => -1,
            'orders' => -1,
            'users' => -1
        ],
        'color' => 'warning',
        'popular' => false
    ]
];

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

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">Subscription Plans</h1>
        <p class="text-muted">Choose the plan that best fits your business needs</p>
    </div>
</div>

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
                    <h2 class="mb-0">
                        <?php if ($plan['price'] > 0): ?>
                            ₹<?php echo number_format($plan['price'], 0); ?>
                        <?php else: ?>
                            FREE
                        <?php endif; ?>
                    </h2>
                    <small class="text-muted"><?php echo $plan['duration']; ?></small>
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
                        <td><strong>Price</strong></td>
                        <td class="text-center">Free</td>
                        <td class="text-center">₹2,499/mo</td>
                        <td class="text-center bg-light">₹4,999/mo</td>
                        <td class="text-center">₹8,299/mo</td>
                    </tr>
                    <tr>
                        <td>Customers</td>
                        <td class="text-center">50</td>
                        <td class="text-center">200</td>
                        <td class="text-center bg-light">1,000</td>
                        <td class="text-center">Unlimited</td>
                    </tr>
                    <tr>
                        <td>Orders</td>
                        <td class="text-center">100</td>
                        <td class="text-center">500</td>
                        <td class="text-center bg-light">2,000</td>
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
                        <td>Custom Branding</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>SMS Notifications</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>API Access</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-check text-success"></i></td>
                        <td class="text-center"><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>White-label</td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        <td class="text-center bg-light"><i class="fas fa-times text-danger"></i></td>
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
                <div class="text-center mb-4">
                    <i class="fas fa-crown fa-3x text-warning mb-3"></i>
                    <h4 id="selectedPlanName">Premium Plan</h4>
                    <h2 class="text-primary mb-0">₹<span id="selectedPlanPrice">4999</span>/month</h2>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Demo Mode:</strong> This is a demonstration. In production, you would integrate with a payment gateway (Stripe, PayPal, etc.) to process payments.
                </div>
                
                <div class="alert alert-warning mb-0">
                    <h6>To enable real payments:</h6>
                    <ol class="mb-0">
                        <li>Integrate Stripe or PayPal</li>
                        <li>Configure payment gateway in settings</li>
                        <li>Add webhook handlers</li>
                        <li>Enable subscription billing</li>
                    </ol>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="simulateUpgrade()">
                    <i class="fas fa-credit-card me-2"></i>Proceed to Payment (Demo)
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

function selectPlan(planKey, planName, planPrice) {
    selectedPlan = planKey;
    document.getElementById('selectedPlanName').textContent = planName;
    document.getElementById('selectedPlanPrice').textContent = planPrice.toLocaleString('en-IN');
    
    const modal = new bootstrap.Modal(document.getElementById('upgradeModal'));
    modal.show();
}

function simulateUpgrade() {
    alert('Payment Gateway Integration Required\n\nIn production, this would:\n1. Redirect to payment gateway (Stripe/PayPal)\n2. Process payment\n3. Update subscription\n4. Send confirmation email\n\nFor demo purposes, please contact administrator to manually upgrade your plan.');
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('upgradeModal'));
    modal.hide();
}
</script>

<?php require_once 'includes/footer.php'; ?>

