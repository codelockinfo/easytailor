<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

<?php
require_once '../config/config.php';

require_login();

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

// Get logged-in user details for auto-filling subscription upgrade
$loggedInUserId = get_user_id();
$currentUserData = [];
if (isset($db) && $loggedInUserId) {
    $userStmt = $db->prepare("SELECT full_name, email, phone, username FROM users WHERE id = :id LIMIT 1");
    $userStmt->bindParam(':id', $loggedInUserId, PDO::PARAM_INT);
    $userStmt->execute();
    $currentUserData = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$customerName = !empty($currentUserData['full_name']) ? $currentUserData['full_name'] : (!empty($company['owner_name']) ? $company['owner_name'] : ($company['company_name'] ?? 'User'));
$customerEmail = !empty($currentUserData['email']) ? $currentUserData['email'] : (!empty($company['business_email']) ? $company['business_email'] : ($company['email'] ?? ''));

$rawPhone = !empty($currentUserData['phone']) ? $currentUserData['phone'] : (!empty($company['business_phone']) ? $company['business_phone'] : ($company['phone'] ?? ''));
$customerPhoneClean = preg_replace('/[^0-9]/', '', (string)$rawPhone);
if (strlen($customerPhoneClean) > 10 && substr($customerPhoneClean, 0, 2) === '91') {
    $customerPhoneClean = substr($customerPhoneClean, 2);
}
if (strlen($customerPhoneClean) < 10) {
    $customerPhoneClean = '9999999999';
}
$customerPhonePrefixed = '+91' . $customerPhoneClean;

// Get company statistics
$companyStats = $companyModel->getCompanyStats($companyId);

// Load subscription plans from JSON file
$pricingData = json_decode(file_get_contents('../data/pricing.json'), true);
$plans = $pricingData['plans'] ?? [];

$currentPlan = $company['subscription_plan'];
$currentPlanData = $plans[$currentPlan] ?? $plans['free'];

// Calculate days remaining and determine duration
$daysRemaining = 0;
$currentDuration = 'monthly';
if ($company['subscription_expiry'] && $currentPlan !== 'free') {
    $expiry = strtotime($company['subscription_expiry']);
    $today = strtotime(date('Y-m-d'));
    $daysRemaining = max(0, floor(($expiry - $today) / (60 * 60 * 24)));
    if ($daysRemaining > 35) {
        $currentDuration = 'annual';
    }
}
?>


<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<div class="card border-0 mb-3 mb-md-4 sub-current-plan-card">
    <div class="card-header border-0 text-white p-3 p-md-4" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%);">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; border-radius: 12px; background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.25);">
                    <i class="fas fa-crown" style="font-size: 1.05rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.6px; color: rgba(255, 255, 255, 0.78);">Your Current Plan</div>
                    <h5 class="mb-0 fw-bold text-white" style="font-size: 1.08rem; line-height: 1.2;"><?php echo $currentPlanData['name']; ?></h5>
                </div>
            </div>
            <?php if ($currentPlan !== 'free'): ?>
                <?php if ($daysRemaining > 0): ?>
                    <span class="badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 px-3 py-2" style="background: rgba(255, 255, 255, 0.95); color: #312e81; font-size: 0.75rem; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);">
                        <i class="fas fa-clock text-primary"></i><?php echo $daysRemaining; ?> Days Remaining
                    </span>
                <?php elseif ($daysRemaining == 0): ?>
                    <span class="badge rounded-pill bg-danger fw-semibold d-inline-flex align-items-center gap-1 px-3 py-2" style="font-size: 0.75rem;">
                        <i class="fas fa-exclamation-triangle"></i>Expires today!
                    </span>
                <?php else: ?>
                    <span class="badge rounded-pill bg-danger fw-semibold d-inline-flex align-items-center gap-1 px-3 py-2" style="font-size: 0.75rem;">
                        <i class="fas fa-times-circle"></i>Expired
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-2 p-sm-3 bg-white">
        <div class="row g-2 g-md-3">
            <div class="col-6 col-md-3">
                <div class="sub-stat-tile h-100 d-flex flex-column justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="sub-stat-label">Customers</span>
                        <span class="sub-stat-icon" style="background: #dbeafe; color: #2563eb;">
                            <i class="fas fa-users"></i>
                        </span>
                    </div>
                    <div>
                        <div class="sub-stat-val text-dark"><?php echo $companyStats['total_customers']; ?></div>
                        <?php if ($currentPlanData['limits']['customers'] > 0): ?>
                            <div class="sub-stat-sub">of <?php echo number_format($currentPlanData['limits']['customers']); ?> limit</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sub-stat-tile h-100 d-flex flex-column justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="sub-stat-label">Orders</span>
                        <span class="sub-stat-icon" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-clipboard-list"></i>
                        </span>
                    </div>
                    <div>
                        <div class="sub-stat-val text-dark"><?php echo $companyStats['total_orders']; ?></div>
                        <?php if ($currentPlanData['limits']['orders'] > 0): ?>
                            <div class="sub-stat-sub">of <?php echo number_format($currentPlanData['limits']['orders']); ?> limit</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sub-stat-tile h-100 d-flex flex-column justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="sub-stat-label">Team Members</span>
                        <span class="sub-stat-icon" style="background: #ede9fe; color: #7c3aed;">
                            <i class="fas fa-user-friends"></i>
                        </span>
                    </div>
                    <div>
                        <div class="sub-stat-val text-dark"><?php echo $companyStats['total_users']; ?></div>
                        <?php if ($currentPlanData['limits']['users'] > 0): ?>
                            <div class="sub-stat-sub">of <?php echo number_format($currentPlanData['limits']['users']); ?> limit</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sub-stat-tile h-100 d-flex flex-column justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="sub-stat-label">Expiry Date</span>
                        <span class="sub-stat-icon" style="background: #dcfce7; color: #16a34a;">
                            <i class="fas fa-calendar-check"></i>
                        </span>
                    </div>
                    <div>
                        <?php if ($currentPlan === 'free'): ?>
                            <div class="sub-stat-val text-success" style="font-size: 1.05rem;">Lifetime</div>
                            <div class="sub-stat-sub">No Expiration</div>
                        <?php elseif ($company['subscription_expiry']): ?>
                            <div class="sub-stat-val text-dark" style="font-size: 1.02rem;"><?php echo format_date($company['subscription_expiry'], 'M j, Y'); ?></div>
                            <div class="sub-stat-sub">Active until date</div>
                        <?php else: ?>
                            <div class="sub-stat-val text-dark" style="font-size: 1.05rem;">N/A</div>
                            <div class="sub-stat-sub">No Expiry</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row mb-4">
    <?php foreach ($plans as $planKey => $plan): ?>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100 <?php echo (isset($plan['popular']) && $plan['popular']) ? 'popular-plan-card ' : ''; ?><?php echo $planKey === $currentPlan ? 'border-' . $plan['color'] : ''; ?>"
                style="<?php echo $planKey === $currentPlan ? 'border-width: 3px;' : ''; ?>">
                <?php if (isset($plan['popular']) && $plan['popular']): ?>
                    <div class="position-absolute top-0 start-50 translate-middle">
                        <span class="badge bg-success popular-plan-badge">
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
                                    <input type="radio" class="btn-check" name="pricing-<?php echo $planKey; ?>"
                                        id="monthly-<?php echo $planKey; ?>" <?php echo ($planKey === $currentPlan && $currentDuration === 'annual') ? '' : 'checked'; ?>>
                                    <label
                                        class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center"
                                        for="monthly-<?php echo $planKey; ?>">Monthly</label>

                                    <input type="radio" class="btn-check" name="pricing-<?php echo $planKey; ?>"
                                        id="annual-<?php echo $planKey; ?>" <?php echo ($planKey === $currentPlan && $currentDuration === 'annual') ? 'checked' : ''; ?>>
                                    <label
                                        class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center"
                                        for="annual-<?php echo $planKey; ?>">Annual <span class="badge bg-success ms-1">10%
                                            OFF</span></label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <h2 class="mb-0 monthly-price"
                            style="<?php echo ($planKey === $currentPlan && $currentDuration === 'annual') ? 'display: none;' : ''; ?>">
                            <?php if ($plan['price'] > 0): ?>
                                ₹<?php echo number_format($plan['price'], 0); ?>
                            <?php else: ?>
                                FREE
                            <?php endif; ?>
                        </h2>
                        <?php if ($planKey !== 'free'): ?>
                            <h2 class="mb-0 annual-price"
                                style="<?php echo ($planKey === $currentPlan && $currentDuration === 'annual') ? '' : 'display: none;'; ?>">
                                <?php if ($plan['price_annual'] > 0): ?>
                                    ₹<?php echo number_format($plan['price_annual'], 0); ?>
                                <?php else: ?>
                                    FREE
                                <?php endif; ?>
                            </h2>
                        <?php endif; ?>

                        <?php if ($planKey !== 'free'): ?>
                            <small class="text-muted annual-duration"
                                style="<?php echo ($planKey === $currentPlan && $currentDuration === 'annual') ? '' : 'display: none;'; ?>">per
                                year</small>
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
                        <button class="btn btn-<?php echo $plan['color']; ?> plan-btn" disabled>
                            <i class="fas fa-check-circle me-2"></i>Current Plan
                        </button>
                    <?php elseif ($planKey === 'free'): ?>
                        <button class="btn btn-outline-<?php echo $plan['color']; ?> plan-btn" disabled>
                            Free Plan
                        </button>
                    <?php else: ?>
                        <button class="btn btn-<?php echo $plan['color']; ?> plan-btn"
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

<div class="card sub-comparison-card mb-2 mb-md-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-table me-2"></i>Detailed Plan Comparison
        </h5>
    </div>
    <div class="card-body p-2 p-md-3">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 sub-comp-table">
                <thead class="table-light">
                    <tr>
                        <th>Feature</th>
                        <th class="text-center">Free</th>
                        <th class="text-center">Basic</th>
                        <th class="text-center">Premium</th>
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
                        <td class="text-center">₹1,069/yr <small class="text-success">(10% OFF)</small></td>
                        <td class="text-center bg-light">₹2,149/yr <small class="text-success">(10% OFF)</small></td>
                        <td class="text-center">₹10,789/yr <small class="text-success">(10% OFF)</small></td>
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
                        <td class="text-center">3</td>
                        <td class="text-center">10</td>
                        <td class="text-center bg-light">20</td>
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
                                <label
                                    class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center"
                                    for="modal-monthly">Monthly</label>

                                <input type="radio" class="btn-check" name="modal-pricing" id="modal-annual">
                                <label
                                    class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center"
                                    for="modal-annual">Annual <span class="badge bg-success ms-1">10% OFF</span></label>
                            </div>
                        </div>

                        <h2 class="text-primary mb-0">₹<span id="selectedPlanPrice">199</span><span
                                id="selectedPlanDuration">/month</span></h2>
                    </div>

                    <hr class="my-3">

                    <div class="bg-light rounded p-3 mb-3 border text-start">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small"><i class="fas fa-store me-1 text-primary"></i> Shop</span>
                            <span class="fw-semibold small"><?php echo htmlspecialchars($company['company_name'] ?? 'Your Shop'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small"><i class="fas fa-user me-1 text-primary"></i> Subscriber</span>
                            <span class="fw-semibold small"><?php echo htmlspecialchars($customerName); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><i class="fas fa-envelope me-1 text-primary"></i> Email</span>
                            <span class="fw-semibold small"><?php echo htmlspecialchars($customerEmail); ?></span>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0 py-2 text-start">
                        <i class="fas fa-shield-alt text-primary me-2"></i>
                        <small>Secured by Razorpay. Instant plan activation upon payment.</small>
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
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .border-primary {
        border-color: #0d6efd !important;
    }

    .border-info {
        border-color: #0dcaf0 !important;
    }

    .border-warning {
        border-color: #ffc107 !important;
    }

    .border-secondary {
        border-color: #6c757d !important;
    }
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

    const loggedInCustomer = {
        name: <?php echo json_encode($customerName); ?>,
        email: <?php echo json_encode($customerEmail); ?>,
        phone: <?php echo json_encode($customerPhonePrefixed); ?>
    };

    function selectPlan(planKey, planName, planPrice) {
        selectedPlan = planKey;
        selectedPlanData = planData[planKey];

        document.getElementById('selectedPlanName').textContent = planName;

        // Sync duration from the plan card toggle
        const cardAnnual = document.getElementById('annual-' + planKey);
        if (cardAnnual && cardAnnual.checked) {
            document.getElementById('modal-annual').checked = true;
        } else {
            document.getElementById('modal-monthly').checked = true;
        }

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
        const customerName = loggedInCustomer.name || 'User';
        const customerEmail = loggedInCustomer.email || 'customer@example.com';
        const customerPhone = loggedInCustomer.phone || '+919999999999';

        // Get pricing details
        const annualRadio = document.getElementById('modal-annual');
        const isAnnual = annualRadio ? annualRadio.checked : false;
        const price = isAnnual ? selectedPlanData.price_annual : selectedPlanData.price;
        const duration = isAnnual ? 'yearly' : 'monthly';

        // Disable button to prevent double submission
        const payBtn = document.getElementById('payNowBtn');
        if (payBtn) {
            payBtn.disabled = true;
            payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        }

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
                        handler: function (response) {
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
                            ondismiss: function () {
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
                        (function () {
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

                    // Close modal and reload page (success message is set in session and will show on reload)
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
    document.addEventListener('DOMContentLoaded', function () {
        // Setup phone validation if element exists
        if (document.getElementById('customerPhone')) {
            setupPhoneValidation('customerPhone', '+91');
        }

        const monthlyRadio = document.getElementById('modal-monthly');
        const annualRadio = document.getElementById('modal-annual');

        if (monthlyRadio && annualRadio) {
            monthlyRadio.addEventListener('change', updateModalPricing);
            annualRadio.addEventListener('change', updateModalPricing);
        }

        // Reset form when modal is closed
        const upgradeModal = document.getElementById('upgradeModal');
        if (upgradeModal) {
            upgradeModal.addEventListener('hidden.bs.modal', function () {
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
            radio.addEventListener('change', function () {
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

                // Handle current plan switch to annual/monthly
                const isCurrentPlan = (planKey === '<?php echo $currentPlan; ?>');
                const currentDuration = '<?php echo $currentDuration; ?>';
                const btn = card.querySelector('.plan-btn');

                if (isCurrentPlan && btn) {
                    if (this.id.includes('annual')) {
                        if (currentDuration === 'annual') {
                            btn.disabled = true;
                            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Current Plan';
                            btn.onclick = null;
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-arrow-up me-2"></i>Upgrade to Annual';
                            btn.onclick = function () { selectPlan(planKey, planData[planKey].name, planData[planKey].price_annual); document.getElementById('modal-annual').checked = true; updateModalPricing(); };
                        }
                    } else {
                        if (currentDuration === 'monthly') {
                            btn.disabled = true;
                            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Current Plan';
                            btn.onclick = null;
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-arrow-down me-2"></i>Switch to Monthly';
                            btn.onclick = function () { selectPlan(planKey, planData[planKey].name, planData[planKey].price); document.getElementById('modal-monthly').checked = true; updateModalPricing(); };
                        }
                    }
                } else if (btn && !isCurrentPlan && planKey !== 'free') {
                    if (this.id.includes('annual')) {
                        btn.onclick = function () { selectPlan(planKey, planData[planKey].name, planData[planKey].price_annual); document.getElementById('modal-annual').checked = true; updateModalPricing(); };
                    } else {
                        btn.onclick = function () { selectPlan(planKey, planData[planKey].name, planData[planKey].price); document.getElementById('modal-monthly').checked = true; updateModalPricing(); };
                    }
                }
            });
        });
    });
</script>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<?php require_once 'includes/footer.php'; ?>

<style>
    .sub-current-plan-card {
        border-radius: 16px !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05) !important;
        overflow: hidden;
    }
    .sub-current-plan-card:hover {
        transform: none !important;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05) !important;
    }
    .sub-stat-tile {
        border-radius: 12px;
        padding: 12px 14px;
    }
    .sub-stat-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .sub-stat-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        flex-shrink: 0;
    }
    .sub-stat-val {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.15;
    }
    .sub-stat-sub {
        font-size: 0.70rem;
        color: #64748b;
        margin-top: 2px;
    }
    @media (max-width: 768px) {
        .border-end {
            border-right: none !important;
        }
        .sub-stat-tile {
            padding: 10px 11px;
        }
        .sub-stat-label {
            font-size: 0.64rem;
        }
        .sub-stat-icon {
            width: 24px;
            height: 24px;
            font-size: 0.70rem;
        }
        .sub-stat-val {
            font-size: 1.15rem;
        }
        .popular-plan-badge {
            font-size: 0.64rem !important;
            padding: 3px 9px !important;
            line-height: 1.2 !important;
        }
        .popular-plan-card {
            margin-top: 8px;
        }
        .popular-plan-card > .card-header {
            padding-top: 16px !important;
        }
    }
    .sub-comparison-card:hover {
        transform: none !important;
    }
    @media (max-width: 767.98px) {
        .sub-comparison-card > .card-body {
            padding: 8px 6px !important;
        }
        .sub-comp-table {
            width: 100% !important;
            table-layout: fixed !important;
            margin-bottom: 0 !important;
        }
        .sub-comp-table th:first-child,
        .sub-comp-table td:first-child {
            width: 24% !important;
            text-align: left !important;
        }
        .sub-comp-table th:not(:first-child),
        .sub-comp-table td:not(:first-child) {
            width: 19% !important;
        }
        .sub-comp-table th,
        .sub-comp-table td {
            padding: 6px 3px !important;
            font-size: 0.64rem !important;
            line-height: 1.25 !important;
            white-space: normal !important;
            vertical-align: middle !important;
            overflow-wrap: break-word;
            word-break: normal;
        }
        .sub-comp-table th {
            font-size: 0.60rem !important;
            padding: 7px 2px !important;
        }
        .sub-comp-table td small {
            display: block;
            font-size: 0.54rem !important;
            line-height: 1.15;
            margin-top: 2px;
        }
    }
    @media (max-width: 360px) {
        .sub-comp-table th,
        .sub-comp-table td {
            font-size: 0.56rem !important;
            padding: 5px 2px !important;
        }
        .sub-comp-table th {
            font-size: 0.52rem !important;
        }
        .sub-comp-table td small {
            font-size: 0.48rem !important;
        }
    }
</style>