<?php
$page_title = 'Dashboard'; 
require_once 'includes/header.php';
require_once '../models/Customer.php';
require_once 'models/Order.php';

require_once 'models/User.php';
require_once '../models/EmailChangeRequest.php';
require_once 'models/Company.php';
require_once 'models/ClothType.php';
require_once 'models/Category.php';
$customerModel = new Customer();
$orderModel = new Order();

$userModel = new User();
$emailChangeRequestModel = new EmailChangeRequest();
$companyModelForProgress = new Company();
$customerStats = $customerModel->getCustomerStats();
$orderStats = $orderModel->getOrderStats();

$userStats = $userModel->getUserStats();
$companyId = get_company_id();

$search = trim($_GET['search'] ?? '');
$searchResults = [
    'orders' => [],
    'customers' => [],
    'categories' => [],
    'cloth_types' => [],
    'total' => 0
];

if (!empty($search)) {
    $searchLower = mb_strtolower($search);
    
    // Filter Orders
    $allOrdersForSearch = $orderModel->getOrdersWithDetails([], null, 0);
    $searchResults['orders'] = array_values(array_filter($allOrdersForSearch, function($order) use ($searchLower) {
        $cName = !empty($order['measurement_customer_name']) 
            ? $order['measurement_customer_name'] 
            : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
        $clothName = $order['cloth_type_name'] ?? '';
        $cPhone = $order['customer_phone'] ?? '';
        $ordNum = $order['order_number'] ?? '';
        
        return mb_stripos($ordNum, $searchLower) !== false ||
               mb_stripos($cName, $searchLower) !== false ||
               mb_stripos($cPhone, $searchLower) !== false ||
               mb_stripos($clothName, $searchLower) !== false;
    }));
    
    // Filter Customers
    $matchingCustomers = $customerModel->searchCustomers($search, 10);
    $searchResults['customers'] = $matchingCustomers;
    
    // Filter Categories
    $categoryModelForSearch = new Category();
    $allCats = $categoryModelForSearch->getCategoriesWithStats();
    $searchResults['categories'] = array_values(array_filter($allCats, function($cat) use ($searchLower) {
        return mb_stripos($cat['name'], $searchLower) !== false ||
               (!empty($cat['description']) && mb_stripos($cat['description'], $searchLower) !== false);
    }));
    
    // Filter Cloth Types
    $clothTypeModelForSearch = new ClothType();
    $allCloth = $clothTypeModelForSearch->getActiveClothTypes();
    $searchResults['cloth_types'] = array_values(array_filter($allCloth, function($ct) use ($searchLower) {
        return mb_stripos($ct['name'], $searchLower) !== false ||
               (!empty($ct['category']) && mb_stripos($ct['category'], $searchLower) !== false);
    }));
    
    $searchResults['total'] = count($searchResults['orders']) + count($searchResults['customers']) + count($searchResults['categories']) + count($searchResults['cloth_types']);
}

$recentOrders = !empty($search) ? $searchResults['orders'] : $orderModel->getOrdersWithDetails([], 5);
$totalOrderCount = ($orderStats['pending'] ?? 0) + ($orderStats['in_progress'] ?? 0) + ($orderStats['completed'] ?? 0) + ($orderStats['delivered'] ?? 0) + ($orderStats['cancelled'] ?? 0);
$overdueOrders = $orderModel->getOverdueOrders();
$ordersDueToday = $orderModel->getOrdersDueToday();
$monthlyRevenue = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    $revenue = (float)$orderModel->getMonthlyRevenue($year, $month);
    $monthlyRevenue[] = [
        'month' => date('M', strtotime($date)),
        'full_month' => date('M Y', strtotime($date)),
        'revenue' => $revenue
    ];
}
$currentMonthRevenue = end($monthlyRevenue)['revenue'] ?? 0;

$companyForProgress = $companyModelForProgress->find($companyId);
$completionProgress = Company::calculateProfileCompletion($companyForProgress);
$statusCounts = [
    'pending' => (int)($orderStats['pending'] ?? 0),
    'in_progress' => (int)($orderStats['in_progress'] ?? 0),
    'completed' => (int)($orderStats['completed'] ?? 0),
    'delivered' => (int)($orderStats['delivered'] ?? 0),
    'cancelled' => (int)($orderStats['cancelled'] ?? 0),
];
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Morning priority deliveries:
// Orders requiring delivery attention, ordered by urgency: Overdue first, then Due Today, then Tomorrow, then Soonest
$priorityQuery = "SELECT o.*, 
                         c.first_name, c.last_name, c.phone as customer_phone,
                         ct.name as cloth_type_name
                  FROM orders o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  WHERE o.company_id = :company_id 
                  AND o.status IN ('pending', 'in_progress', 'completed')";
if (has_role('tailor')) {
    $priorityQuery .= " AND o.assigned_tailor_id = " . (int)get_user_id();
}
$priorityQuery .= " ORDER BY 
                      CASE 
                          WHEN o.due_date < :today1 THEN 1
                          WHEN o.due_date = :today2 THEN 2
                          WHEN o.due_date = :tomorrow THEN 3
                          ELSE 4
                      END ASC,
                      o.due_date ASC
                    LIMIT 8";

$stmtP = $db->prepare($priorityQuery);
$stmtP->bindParam(':company_id', $companyId, PDO::PARAM_INT);
$stmtP->bindParam(':today1', $today);
$stmtP->bindParam(':today2', $today);
$stmtP->bindParam(':tomorrow', $tomorrow);
$stmtP->execute();
$priorityDeliveries = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// Morning delivery pulse counts
$morningCounts = [
    'overdue' => 0,
    'today' => 0,
    'tomorrow' => 0,
    'ready' => 0
];
$countQuery = "SELECT 
    SUM(CASE WHEN due_date < :today1 AND status IN ('pending', 'in_progress', 'completed') THEN 1 ELSE 0 END) as overdue_count,
    SUM(CASE WHEN due_date = :today2 AND status IN ('pending', 'in_progress', 'completed') THEN 1 ELSE 0 END) as today_count,
    SUM(CASE WHEN due_date = :tomorrow AND status IN ('pending', 'in_progress', 'completed') THEN 1 ELSE 0 END) as tomorrow_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as ready_count
FROM orders 
WHERE company_id = :company_id";
if (has_role('tailor')) {
    $countQuery .= " AND assigned_tailor_id = " . (int)get_user_id();
}
$stmtC = $db->prepare($countQuery);
$stmtC->bindParam(':company_id', $companyId, PDO::PARAM_INT);
$stmtC->bindParam(':today1', $today);
$stmtC->bindParam(':today2', $today);
$stmtC->bindParam(':tomorrow', $tomorrow);
$stmtC->execute();
$countsRow = $stmtC->fetch(PDO::FETCH_ASSOC);

$morningCounts['overdue'] = (int)($countsRow['overdue_count'] ?? 0);
$morningCounts['today'] = (int)($countsRow['today_count'] ?? 0);
$morningCounts['tomorrow'] = (int)($countsRow['tomorrow_count'] ?? 0);
$morningCounts['ready'] = (int)($countsRow['ready_count'] ?? 0);
?>

<div class="container-fluid px-0">
    <?php
    $profileFields = [
        'business_address' => 'Address',
        'city'             => 'City',
        'state'            => 'State',
        'postal_code'      => 'PIN Code',
        'logo'             => 'Business Logo',
    ];
    $fieldsDone = [];
    foreach ($profileFields as $key => $label) {
        if ($key === 'logo') {
            $fieldsDone[$key] = Company::hasCustomLogo($companyForProgress);
        } else {
            $fieldsDone[$key] = !empty(trim((string)($companyForProgress[$key] ?? '')));
        }
    }
    $prog = (int)$completionProgress;
    $r = 34; $circ = round(2 * M_PI * $r, 2);
    $offset = round($circ - ($prog / 100) * $circ, 2);
    ?>
    <style>
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .welcome-banner {
        position: relative;
        min-height: 168px;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(26, 35, 126, 0.35);
        margin-bottom: 1.25rem;
    }
    .wb-btn-clean {
        background: #ffffff;
        color: #2563eb;
        font-weight: 700;
        font-size: 0.88rem;
        padding: 9px 20px;
        border-radius: 12px;
        text-decoration: none;
        white-space: nowrap;
        box-shadow: 0 4px 14px rgba(0,0,0,0.18);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .wb-btn-clean:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        color: #1d4ed8;
    }
    .wb-pill-btn-mob {
        background: #ffffff;
        color: #2563eb;
        font-weight: 700;
        font-size: 0.78rem;
        padding: 6px 18px;
        border-radius: 999px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        box-shadow: 0 3px 10px rgba(0,0,0,0.18);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .wb-pill-btn-mob:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.25);
        color: #1d4ed8;
    }
    @media (max-width: 991.98px) {
        .welcome-banner {
            border-radius: 16px !important;
            min-height: auto !important;
            margin-bottom: 1rem !important;
        }
        .wb-bg-gradient {
            background: linear-gradient(90deg, 
                rgba(15, 23, 115, 0.98) 0%, 
                rgba(20, 30, 135, 0.92) 48%, 
                rgba(30, 40, 150, 0.60) 68%, 
                rgba(40, 50, 160, 0.22) 84%, 
                transparent 100%) !important;
        }
        .wb-content-pad {
            padding: 16px 14px 14px !important;
        }
        .order-status-ring-col {
            padding: 0 2px !important;
        }
    }
    </style>

    <div class="welcome-banner overflow-hidden position-relative">
        <div class="position-absolute top-0 start-0 w-100 h-100 wb-bg-img"
             style="background: url('../assets/images/dashboard/welcome_banner_bg.png') no-repeat right center / cover; z-index: 0;"></div>

        <div class="position-absolute top-0 start-0 w-100 h-100 wb-bg-gradient"
             style="background: linear-gradient(90deg, 
                 rgba(15, 23, 115, 0.96) 0%, 
                 rgba(24, 35, 145, 0.92) 26%, 
                 rgba(35, 48, 175, 0.82) 46%, 
                 rgba(58, 48, 175, 0.60) 65%, 
                 rgba(70, 45, 168, 0.22) 80%, 
                 transparent 92%); z-index: 1;"></div>
        <div class="position-relative text-white p-3 p-md-4 wb-content-pad" style="z-index: 2;">

            <div class="d-none d-lg-flex flex-column justify-content-between" style="min-height: 168px;">
                
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <div style="font-size: 0.84rem; color: rgba(255,255,255,0.85); margin-bottom: 2px;">Welcome back,</div>
                        <div style="font-size: 1.85rem; font-weight: 800; line-height: 1.15; color: #ffffff; letter-spacing: -0.5px; margin-bottom: 4px;">
                            <?php echo htmlspecialchars($current_user['name'] ?? 'User'); ?> 👋
                        </div>
                        <div style="font-size: 0.78rem; color: rgba(255,255,255,0.82); line-height: 1.45; max-width: 320px;">
                            <?php if ($prog >= 100): ?>
                                Your shop profile is fully completed and active!
                            <?php else: ?>
                                Complete your shop profile to unlock all features and grow your tailoring business.
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <?php
                        $rDesk = 32;
                        $circDesk = round(2 * M_PI * $rDesk, 2);
                        $offsetDesk = round($circDesk - ($prog / 100) * $circDesk, 2);
                        ?>
                        <div class="position-relative flex-shrink-0" style="width: 72px; height: 72px;">
                            <svg width="72" height="72" viewBox="0 0 72 72">
                                <circle cx="36" cy="36" r="34" fill="rgba(15, 23, 42, 0.45)" />
                                <circle cx="36" cy="36" r="<?php echo $rDesk; ?>" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="6.5" />
                                <circle cx="36" cy="36" r="<?php echo $rDesk; ?>" fill="none"
                                        stroke="#00e676" stroke-width="6.5" stroke-linecap="round"
                                        stroke-dasharray="<?php echo $circDesk; ?>"
                                        stroke-dashoffset="<?php echo $offsetDesk; ?>"
                                        transform="rotate(-90 36 36)" />
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle fw-bold text-white" style="font-size: 1.05rem;">
                                <?php echo $prog; ?>%
                            </div>
                        </div>
                        <div>
                            <div class="fw-bold text-white mb-1" style="font-size: 1.05rem; line-height: 1.2;">Profile Completed</div>
                            <div style="font-size: 0.74rem; color: rgba(255,255,255,0.82); line-height: 1.4; max-width: 240px;">
                                <?php if ($prog >= 100): ?>
                                    All your shop details have been added successfully.
                                <?php else: ?>
                                    Add your shop details to get more customers and manage everything easily.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($prog < 100): ?>
                    <div class="pe-5 me-3">
                        <a href="company-settings.php" class="wb-btn-clean">
                            Complete Profile
                            <i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center justify-content-between gap-4 mt-3 pt-2">
                    <div class="d-flex align-items-center flex-wrap" style="gap: 16px;">
                        <?php foreach ($profileFields as $key => $label): ?>
                            <?php if ($fieldsDone[$key]): ?>
                                <span class="d-inline-flex align-items-center gap-2" style="font-size: 0.76rem; color: #ffffff; font-weight: 500;">
                                    <span style="width: 16px; height: 16px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 9px; flex-shrink: 0;">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <?php echo $label; ?>
                                </span>
                            <?php else: ?>
                                <span class="d-inline-flex align-items-center gap-2" style="font-size: 0.76rem; color: rgba(255,255,255,0.85); font-weight: 500;">
                                    <span style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.55); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                                    <?php echo $label; ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($prog < 100): ?>
                    <div class="d-flex align-items-center gap-3 pe-5 me-3" style="flex: 0 0 46%; max-width: 46%;">
                        <div style="flex: 1; height: 8px; background: rgba(255,255,255,0.22); border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $prog; ?>%; background: #00e676; border-radius: 10px; box-shadow: 0 0 10px rgba(0,230,118,0.7); transition: width 1.2s ease;"></div>
                        </div>
                        <span class="fw-bold text-white flex-shrink-0" style="font-size: 0.92rem; min-width: 34px;">
                            <?php echo $prog; ?>%
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex d-lg-none flex-column gap-2">
                <div>
                    <div style="font-size: 0.76rem; color: rgba(255,255,255,0.85); margin-bottom: 2px;">Welcome back,</div>
                    <div style="font-size: 1.45rem; font-weight: 800; line-height: 1.15; color: #ffffff; letter-spacing: -0.3px; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($current_user['name'] ?? 'User'); ?> 👋
                    </div>
                    
                </div>
                <?php
                $rMob = 19;
                $circMob = round(2 * M_PI * $rMob, 2);
                $offsetMob = round($circMob - ($prog / 100) * $circMob, 2);
                ?>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <div class="position-relative flex-shrink-0" style="width: 46px; height: 46px;">
                        <svg width="46" height="46" viewBox="0 0 46 46">
                            <circle cx="23" cy="23" r="21" fill="rgba(15, 23, 42, 0.45)" />
                            <circle cx="23" cy="23" r="<?php echo $rMob; ?>" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="4.5" />
                            <circle cx="23" cy="23" r="<?php echo $rMob; ?>" fill="none"
                                    stroke="#00e676" stroke-width="4.5" stroke-linecap="round"
                                    stroke-dasharray="<?php echo $circMob; ?>"
                                    stroke-dashoffset="<?php echo $offsetMob; ?>"
                                    transform="rotate(-90 23 23)" />
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle fw-bold text-white" style="font-size: 0.76rem;">
                            <?php echo $prog; ?>%
                        </div>
                    </div>
                    <div>
                        <div class="fw-bold text-white" style="font-size: 0.90rem; line-height: 1.2;">Profile Completed</div>
                        <div style="font-size: 0.67rem; color: rgba(255,255,255,0.82); line-height: 1.3; max-width: 62%;">
                            Add your shop details to get more customers and manage everything easily.
                        </div>
                    </div>
                </div>
                <?php if ($prog < 100): ?>
                <div class="mt-1">
                    <a href="company-settings.php" class="wb-pill-btn-mob">
                        Complete Profile <i class="fas fa-arrow-right ms-1" style="font-size: 0.70rem;"></i>
                    </a>
                </div>
                <?php endif; ?>
                <div class="d-flex flex-column gap-1 mt-1 pt-1">
                    <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                        <?php 
                        $row1Fields = ['business_address' => 'Address', 'city' => 'City', 'state' => 'State'];
                        foreach ($row1Fields as $key => $label): 
                        ?>
                            <?php if ($fieldsDone[$key]): ?>
                                <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: #ffffff; font-weight: 500;">
                                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 8px; flex-shrink: 0;">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <?php echo $label; ?>
                                </span>
                            <?php else: ?>
                                <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: rgba(255,255,255,0.85); font-weight: 500;">
                                    <span style="width: 13px; height: 13px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.6); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                                    <?php echo $label; ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                        <?php 
                        $row2Fields = ['postal_code' => 'PIN Code', 'logo' => 'Business Logo'];
                        foreach ($row2Fields as $key => $label): 
                        ?>
                            <?php if ($fieldsDone[$key]): ?>
                                <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: #ffffff; font-weight: 500;">
                                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 8px; flex-shrink: 0;">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <?php echo $label; ?>
                                </span>
                            <?php else: ?>
                                <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: rgba(255,255,255,0.85); font-weight: 500;">
                                    <span style="width: 13px; height: 13px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.6); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                                    <?php echo $label; ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php if (!empty($search)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3 mb-md-4 overflow-hidden" id="dashboardSearchResultsContainer" style="border: 1px solid #c7d2fe !important; background: #ffffff;">
        <div class="px-3 px-md-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%); border-bottom: 1px solid #e0e7ff;">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background: #4f46e5; color: #ffffff;">
                    <i class="fas fa-search" style="font-size: 0.95rem;"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">
                        Search Results for "<span class="text-primary"><?php echo htmlspecialchars($search); ?></span>"
                    </h5>
                    <div class="text-muted" style="font-size: 0.76rem;">
                        Found <strong class="text-dark"><?php echo $searchResults['total']; ?></strong> total match<?php echo $searchResults['total'] === 1 ? '' : 'es'; ?> on dashboard
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="dashboard" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 shadow-xs" style="font-size: 0.8rem;">
                    <i class="fas fa-times"></i>
                    <span>Clear Search</span>
                </a>
                <a href="orders?search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 shadow-xs" style="font-size: 0.8rem; background: #4f46e5; border-color: #4f46e5;">
                    <i class="fas fa-external-link-alt" style="font-size: 0.72rem;"></i>
                    <span>Orders Page</span>
                </a>
            </div>
        </div>
        
        <div class="card-body p-3 p-md-4">
            <!-- Matching Orders Section -->
            <?php if (!empty($searchResults['orders'])): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                        <span class="fw-bold text-dark text-uppercase" style="font-size: 0.82rem; letter-spacing: 0.5px;">
                            <i class="fas fa-shopping-bag me-1 text-primary"></i> Matching Orders (<?php echo count($searchResults['orders']); ?>)
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light text-muted" style="font-size: 0.74rem; text-transform: uppercase;">
                                <tr>
                                    <th class="ps-3">Order #</th>
                                    <th>Customer</th>
                                    <th>Cloth Type</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th class="text-center pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults['orders'] as $ord): 
                                    $cName = $ord['customer_name'] ?? (($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
                                    if (empty($cName) && !empty($ord['measurement_customer_name'])) {
                                        $cName = $ord['measurement_customer_name'];
                                    }
                                    $cName = $cName ?: 'Customer';
                                    $statusPillStyle = match($ord['status']) {
                                        'completed' => 'background-color: #10b981; color: white;',
                                        'pending' => 'background-color: #f59e0b; color: white;',
                                        'in_progress' => 'background-color: #06b6d4; color: white;',
                                        'delivered' => 'background-color: #3b82f6; color: white;',
                                        default => 'background-color: #ef4444; color: white;'
                                    };
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-2 d-inline-flex align-items-center justify-content-center p-1" style="width: 28px; height: 28px; background: #eff6ff; color: #3b82f6;">
                                                <i class="fas fa-tshirt" style="font-size: 0.75rem;"></i>
                                            </span>
                                            <a href="order-details.php?id=<?php echo $ord['id']; ?>" class="fw-bold text-primary text-decoration-none">
                                                #<?php echo htmlspecialchars($ord['order_number']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($cName); ?></div>
                                        <small class="text-muted"><i class="fas fa-phone-alt me-1" style="font-size: 0.65rem;"></i><?php echo htmlspecialchars($ord['customer_phone'] ?? ''); ?></small>
                                    </td>
                                    <td class="text-muted"><?php echo htmlspecialchars($ord['cloth_type_name'] ?? 'Custom'); ?></td>
                                    <td class="fw-bold text-dark"><?php echo format_currency($ord['total_amount']); ?></td>
                                    <td class="text-muted"><?php echo format_date($ord['due_date'], 'Y-m-d'); ?></td>
                                    <td>
                                        <span class="badge rounded-pill" style="<?php echo $statusPillStyle; ?> font-size: 0.72rem; padding: 4px 10px; font-weight: 500;">
                                            <?php echo ucfirst(str_replace('_', ' ', $ord['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center pe-3">
                                        <a href="order-details.php?id=<?php echo $ord['id']; ?>" class="btn btn-sm btn-light rounded-circle text-primary me-1 p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="View Details">
                                            <i class="fas fa-eye" style="font-size: 0.72rem;"></i>
                                        </a>
                                        <a href="orders.php?edit=<?php echo $ord['id']; ?>" class="btn btn-sm btn-light rounded-circle text-secondary p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="Edit Order">
                                            <i class="fas fa-pencil-alt" style="font-size: 0.72rem;"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Matching Customers Section -->
            <?php if (!empty($searchResults['customers'])): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                        <span class="fw-bold text-dark text-uppercase" style="font-size: 0.82rem; letter-spacing: 0.5px;">
                            <i class="fas fa-users me-1 text-success"></i> Matching Customers (<?php echo count($searchResults['customers']); ?>)
                        </span>
                        <a href="customers?search=<?php echo urlencode($search); ?>" class="text-success text-decoration-none small fw-semibold">Customers Page &rarr;</a>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($searchResults['customers'] as $cust): ?>
                        <div class="col-md-6 col-lg-4">
                            <a href="customer-details.php?id=<?php echo $cust['id']; ?>" class="card p-3 border shadow-xs rounded-3 text-decoration-none h-100 transition-hover" style="border-color: #e2e8f0 !important; background: #f8fafc;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="fw-bold text-dark" style="font-size: 0.88rem;"><?php echo htmlspecialchars($cust['name'] ?? ($cust['first_name'] . ' ' . $cust['last_name'])); ?></div>
                                    <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;"><?php echo htmlspecialchars($cust['customer_code'] ?? ''); ?></span>
                                </div>
                                <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                    <i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($cust['phone'] ?? $cust['phone_number'] ?? 'No phone'); ?>
                                </div>
                                <div class="text-secondary small mt-1" style="font-size: 0.72rem;">
                                    <i class="fas fa-shopping-bag me-1 text-muted"></i><?php echo (int)($cust['order_count'] ?? 0); ?> orders
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Matching Categories Section -->
            <?php if (!empty($searchResults['categories'])): ?>
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                        <span class="fw-bold text-dark text-uppercase" style="font-size: 0.82rem; letter-spacing: 0.5px;">
                            <i class="fas fa-folder me-1 text-warning"></i> Matching Categories (<?php echo count($searchResults['categories']); ?>)
                        </span>
                        <a href="categories?search=<?php echo urlencode($search); ?>" class="text-warning text-decoration-none small fw-semibold">Categories Page &rarr;</a>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($searchResults['categories'] as $cat): ?>
                        <a href="categories?search=<?php echo urlencode($cat['name']); ?>" class="badge bg-light text-dark border p-2 text-decoration-none d-inline-flex align-items-center gap-1" style="font-size: 0.82rem; border-radius: 8px;">
                            <i class="fas fa-tag text-warning"></i>
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- If 0 total matches -->
            <?php if ($searchResults['total'] === 0): ?>
                <div class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">No orders or customers found for "<?php echo htmlspecialchars($search); ?>"</h6>
                    <p class="text-muted small mb-3">Try searching with another customer name, phone number, order number, or category.</p>
                    <a href="dashboard" class="btn btn-sm btn-outline-secondary rounded-pill px-4">
                        Back to Full Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-2 g-md-3 mb-2 mb-md-3">
        <!-- 1. Advance Amount -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #faf5ff; border: 1px solid #f3e8ff !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #ede9fe; color: #7c3aed;">
                        <i class="fas fa-hand-holding-usd" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 7px; background: #c084fc; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 13px; background: #a855f7; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 10px; background: #9333ea; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 18px; background: #7e22ce; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;"><?php echo format_currency($orderStats['total_advance'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2; margin-top: 3px;">Advance Amount</div>
                </div>
            </div>
        </div>
        <!-- 2. Pending Amount -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #fff1f2; border: 1px solid #fee2e2 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #fee2e2; color: #ef4444;">
                        <i class="fas fa-clock" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 7px; background: #fca5a5; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 14px; background: #f87171; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 11px; background: #ef4444; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 19px; background: #dc2626; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;"><?php echo format_currency($orderStats['total_pending'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2; margin-top: 3px;">Pending Amount</div>
                </div>
            </div>
        </div>
        <!-- 3. Total Amount -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #f0fdf4; border: 1px solid #dcfce7 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #dcfce7; color: #10b981;">
                        <i class="fas fa-money-bill-wave" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 7px; background: #86efac; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 13px; background: #4ade80; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 10px; background: #22c55e; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 18px; background: #16a34a; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;"><?php echo format_currency($orderStats['total_amount'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2; margin-top: 3px;">Total Amount</div>
                </div>
            </div>
        </div>
        <!-- 4. Total Orders -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #fffbeb; border: 1px solid #fef3c7 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #fef3c7; color: #f59e0b;">
                        <i class="fas fa-layer-group" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 6px; background: #fde68a; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 11px; background: #fcd34d; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 15px; background: #fbbf24; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 18px; background: #f59e0b; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;"><?php echo number_format($orderStats['total'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2; margin-top: 3px;">Total Orders</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. MAIN MORNING OPERATIONS ROW: Today's Delivery Schedule & Priorities (col-lg-7) + Order Status (col-lg-5) -->
    <div class="row g-3 mb-3">
        <!-- Today's Schedule & Delivery Priorities (col-lg-7) -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                            <i class="fas fa-shipping-fast me-2 text-primary" style="color: #6366f1;"></i>Today's Delivery Schedule & Priorities
                        </h6>
                        <small class="text-muted" style="font-size: 0.72rem;">Orders requiring immediate attention, delivery or trial</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($morningCounts['overdue'] > 0): ?>
                            <span class="badge rounded-pill text-white px-2 py-1 shadow-xs" style="background: #ef4444; font-size: 0.70rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i><?php echo $morningCounts['overdue']; ?> Overdue
                            </span>
                        <?php elseif ($morningCounts['today'] > 0): ?>
                            <span class="badge rounded-pill text-dark px-2 py-1 shadow-xs" style="background: #fde047; font-size: 0.70rem;">
                                <i class="fas fa-bolt me-1 text-warning-emphasis"></i><?php echo $morningCounts['today']; ?> Due Today
                            </span>
                        <?php elseif ($morningCounts['tomorrow'] > 0): ?>
                            <span class="badge rounded-pill text-white px-2 py-1 shadow-xs" style="background: #f59e0b; font-size: 0.70rem;">
                                <i class="fas fa-clock me-1"></i><?php echo $morningCounts['tomorrow']; ?> Due Tomorrow
                            </span>
                        <?php else: ?>
                            <span class="badge rounded-pill text-success border border-success-subtle bg-success-subtle px-2 py-1" style="font-size: 0.70rem;">
                                <i class="fas fa-check-circle me-1"></i>On Track
                            </span>
                        <?php endif; ?>
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 bg-white text-dark shadow-xs" style="font-size: 0.75rem; border-color: #e2e8f0;">
                            View All
                        </a>
                    </div>
                </div>

                <!-- Morning Quick Stats Strip -->
                <div class="row g-2 mb-3">
                    <div class="col-3">
                        <div class="p-2 rounded-3 text-center" style="background: #fef2f2; border: 1px solid #fee2e2;">
                            <div class="fw-bold text-danger" style="font-size: 1.15rem; line-height: 1.1;"><?php echo $morningCounts['overdue']; ?></div>
                            <div class="text-muted text-uppercase" style="font-size: 0.63rem; font-weight: 600; letter-spacing: 0.3px;">Overdue</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded-3 text-center" style="background: #fefce8; border: 1px solid #fef08a;">
                            <div class="fw-bold" style="font-size: 1.15rem; line-height: 1.1; color: #ca8a04;"><?php echo $morningCounts['today']; ?></div>
                            <div class="text-muted text-uppercase" style="font-size: 0.63rem; font-weight: 600; letter-spacing: 0.3px;">Due Today</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded-3 text-center" style="background: #fff7ed; border: 1px solid #ffedd5;">
                            <div class="fw-bold" style="font-size: 1.15rem; line-height: 1.1; color: #ea580c;"><?php echo $morningCounts['tomorrow']; ?></div>
                            <div class="text-muted text-uppercase" style="font-size: 0.63rem; font-weight: 600; letter-spacing: 0.3px;">Tomorrow</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded-3 text-center" style="background: #ecfdf5; border: 1px solid #d1fae5;">
                            <div class="fw-bold text-success" style="font-size: 1.15rem; line-height: 1.1;"><?php echo $morningCounts['ready']; ?></div>
                            <div class="text-muted text-uppercase" style="font-size: 0.63rem; font-weight: 600; letter-spacing: 0.3px;">Ready Pickup</div>
                        </div>
                    </div>
                </div>

                <!-- Priority Deliveries List -->
                <?php if (!empty($priorityDeliveries)): ?>
                    <div class="d-flex flex-column gap-2 custom-scrollbar" style="max-height: 220px; overflow-y: auto; padding-right: 4px;">
                        <?php foreach ($priorityDeliveries as $pd): 
                            $cName = $pd['customer_name'] ?? trim(($pd['first_name'] ?? '') . ' ' . ($pd['last_name'] ?? ''));
                            if (empty($cName) && !empty($pd['measurement_customer_name'])) {
                                $cName = $pd['measurement_customer_name'];
                            }
                            $cName = $cName ?: 'Customer';
                            $dueDate = $pd['due_date'];
                            $isOverdue = ($dueDate < $today);
                            $isDueToday = ($dueDate === $today);
                            $isDueTomorrow = ($dueDate === $tomorrow);
                            
                            $rowBg = '#ffffff';
                            $rowBorder = '#e2e8f0';
                            if ($isOverdue) {
                                $rowBg = '#fff5f5';
                                $rowBorder = '#fecaca';
                            } elseif ($isDueToday) {
                                $rowBg = '#fefce8';
                                $rowBorder = '#fef08a';
                            } elseif ($pd['status'] === 'completed') {
                                $rowBg = '#f0fdf4';
                                $rowBorder = '#bbf7d0';
                            }
                        ?>
                        <div class="p-2 p-sm-3 rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-2 transition-hover" style="background: <?php echo $rowBg; ?>; border: 1px solid <?php echo $rowBorder; ?>;">
                            <div class="d-flex align-items-center gap-2 min-w-0" style="flex: 1 1 230px;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: <?php echo $isOverdue ? '#fee2e2' : ($isDueToday ? '#fef08a' : ($pd['status'] === 'completed' ? '#dcfce7' : '#eff6ff')); ?>; color: <?php echo $isOverdue ? '#ef4444' : ($isDueToday ? '#ca8a04' : ($pd['status'] === 'completed' ? '#16a34a' : '#3b82f6')); ?>;">
                                    <i class="fas fa-<?php echo $isOverdue ? 'exclamation' : ($pd['status'] === 'completed' ? 'check' : 'tshirt'); ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <a href="order-details.php?id=<?php echo $pd['id']; ?>" class="fw-bold text-dark text-decoration-none" style="font-size: 0.92rem;">
                                            <?php echo htmlspecialchars($cName); ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small text-truncate" style="font-size: 0.74rem;">
                                        <span><?php echo htmlspecialchars($pd['cloth_type_name'] ?? 'Garment'); ?></span>
                                        <?php if (!empty($pd['customer_phone'])): ?>
                                            <span class="ms-1 text-secondary"><i class="fas fa-phone-alt me-1 opacity-75" style="font-size: 0.65rem;"></i><?php echo htmlspecialchars($pd['customer_phone']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <?php if ($isOverdue): 
                                    $daysLate = (int)round((strtotime($today) - strtotime($dueDate)) / 86400);
                                ?>
                                    <span class="badge rounded-pill text-white px-2 py-1" style="background: #ef4444; font-size: 0.70rem; font-weight: 600;">
                                        <i class="fas fa-exclamation-circle me-1"></i>Overdue (<?php echo $daysLate; ?>d)
                                    </span>
                                <?php elseif ($isDueToday): ?>
                                    <span class="badge rounded-pill text-dark px-2 py-1" style="background: #fde047; font-size: 0.70rem; font-weight: 600;">
                                        <i class="fas fa-bolt me-1 text-warning-emphasis"></i>Due Today
                                    </span>
                                <?php elseif ($isDueTomorrow): ?>
                                    <span class="badge rounded-pill text-white px-2 py-1" style="background: #f59e0b; font-size: 0.70rem; font-weight: 600;">
                                        <i class="fas fa-clock me-1"></i>Due Tomorrow
                                    </span>
                                <?php else: 
                                    $daysUntil = (int)round((strtotime($dueDate) - strtotime($today)) / 86400);
                                ?>
                                    <span class="badge rounded-pill text-secondary px-2 py-1" style="background: #f1f5f9; font-size: 0.70rem; font-weight: 500;">
                                        Due in <?php echo $daysUntil; ?>d (<?php echo date('d M', strtotime($dueDate)); ?>)
                                    </span>
                                <?php endif; ?>

                                <?php if ($pd['status'] === 'completed'): ?>
                                    <span class="badge rounded-pill text-white px-2 py-1" style="background: #10b981; font-size: 0.70rem; font-weight: 500;">
                                        Ready Pickup
                                    </span>
                                <?php elseif ($pd['status'] === 'in_progress'): ?>
                                    <span class="badge rounded-pill text-white px-2 py-1" style="background: #06b6d4; font-size: 0.70rem; font-weight: 500;">
                                        In Progress
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-dark px-2 py-1" style="background: #fde68a; font-size: 0.70rem; font-weight: 500;">
                                        Pending
                                    </span>
                                <?php endif; ?>

                                <div class="d-flex align-items-center gap-1 ms-1">
                                    <?php if (!empty($pd['customer_phone'])): 
                                        $cleanP = preg_replace('/\D/', '', $pd['customer_phone']);
                                        if (strlen($cleanP) === 10) $cleanP = '91' . $cleanP;
                                    ?>
                                        <a href="https://wa.me/<?php echo $cleanP; ?>" target="_blank" class="btn btn-sm btn-light rounded-circle text-success p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="WhatsApp Customer">
                                            <i class="fab fa-whatsapp" style="font-size: 0.75rem;"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="order-details.php?id=<?php echo $pd['id']; ?>" class="btn btn-sm btn-light rounded-circle text-primary p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="View Order">
                                        <i class="fas fa-eye" style="font-size: 0.72rem;"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 my-2">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 52px; height: 52px; background: #ecfdf5; color: #10b981;">
                            <i class="fas fa-check-double fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">All Caught Up!</h6>
                        <p class="text-muted small mb-3" style="max-width: 320px; margin: 0 auto; font-size: 0.78rem;">
                            No pending deliveries or overdue orders right now. Your tailoring workshop is completely on schedule!
                        </p>
                        <a href="orders.php?action=create" class="btn btn-sm btn-primary rounded-pill px-3 py-1 shadow-xs" style="background: #4f46e5; border-color: #4f46e5; font-size: 0.8rem;">
                            <i class="fas fa-plus me-1"></i>Create New Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Status Overview (col-lg-5) -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                <i class="fas fa-chart-pie me-2 text-primary" style="color: #6366f1;"></i>Order Status Breakdown
                            </h6>
                            <small class="text-muted" style="font-size: 0.72rem;">Distribution of all workshop orders</small>
                        </div>
                        <span class="badge rounded-pill" style="background: #a78bfa; color: #ffffff; font-weight: 600; font-size: 0.72rem; padding: 4px 10px;">
                            <?php echo $totalOrderCount; ?> Total
                        </span>
                    </div>

                    <?php if ($totalOrderCount > 0): ?>
                        <div class="row align-items-center g-3 my-1 py-1">
                            <!-- Doughnut with Center Metric -->
                            <div class="col-sm-5 d-flex justify-content-center">
                                <div style="position: relative; width: 145px; height: 145px;">
                                    <canvas id="orderStatusPieChart"></canvas>
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                                        <div class="fw-bold text-dark lh-1" style="font-size: 1.45rem;"><?php echo $totalOrderCount; ?></div>
                                        <div class="text-muted fw-semibold" style="font-size: 0.62rem; letter-spacing: 0.5px; text-transform: uppercase;">Orders</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Breakdown Progress Rows -->
                            <div class="col-sm-7">
                                <div class="d-flex flex-column gap-2">
                                    <a href="orders.php?status=in_progress" class="text-decoration-none p-1 px-2 rounded-2 transition-hover text-dark d-block" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.74rem;">
                                            <span class="d-flex align-items-center fw-semibold">
                                                <span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: #06b6d4;"></span>
                                                In Progress
                                            </span>
                                            <span class="fw-bold text-dark">
                                                <?php echo $statusCounts['in_progress']; ?> 
                                                <small class="text-muted fw-normal" style="font-size: 0.68rem;">(<?php echo $totalOrderCount > 0 ? round(($statusCounts['in_progress'] / $totalOrderCount) * 100) : 0; ?>%)</small>
                                            </span>
                                        </div>
                                        <div class="progress rounded-pill" style="height: 4px; background: #e2e8f0;">
                                            <div class="progress-bar rounded-pill" style="width: <?php echo $totalOrderCount > 0 ? round(($statusCounts['in_progress'] / $totalOrderCount) * 100) : 0; ?>%; background: #06b6d4;"></div>
                                        </div>
                                    </a>

                                    <a href="orders.php?status=pending" class="text-decoration-none p-1 px-2 rounded-2 transition-hover text-dark d-block" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.74rem;">
                                            <span class="d-flex align-items-center fw-semibold">
                                                <span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: #f59e0b;"></span>
                                                Pending
                                            </span>
                                            <span class="fw-bold text-dark">
                                                <?php echo $statusCounts['pending']; ?> 
                                                <small class="text-muted fw-normal" style="font-size: 0.68rem;">(<?php echo $totalOrderCount > 0 ? round(($statusCounts['pending'] / $totalOrderCount) * 100) : 0; ?>%)</small>
                                            </span>
                                        </div>
                                        <div class="progress rounded-pill" style="height: 4px; background: #e2e8f0;">
                                            <div class="progress-bar rounded-pill" style="width: <?php echo $totalOrderCount > 0 ? round(($statusCounts['pending'] / $totalOrderCount) * 100) : 0; ?>%; background: #f59e0b;"></div>
                                        </div>
                                    </a>

                                    <a href="orders.php?status=completed" class="text-decoration-none p-1 px-2 rounded-2 transition-hover text-dark d-block" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.74rem;">
                                            <span class="d-flex align-items-center fw-semibold">
                                                <span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: #10b981;"></span>
                                                Ready / Completed
                                            </span>
                                            <span class="fw-bold text-dark">
                                                <?php echo $statusCounts['completed']; ?> 
                                                <small class="text-muted fw-normal" style="font-size: 0.68rem;">(<?php echo $totalOrderCount > 0 ? round(($statusCounts['completed'] / $totalOrderCount) * 100) : 0; ?>%)</small>
                                            </span>
                                        </div>
                                        <div class="progress rounded-pill" style="height: 4px; background: #e2e8f0;">
                                            <div class="progress-bar rounded-pill" style="width: <?php echo $totalOrderCount > 0 ? round(($statusCounts['completed'] / $totalOrderCount) * 100) : 0; ?>%; background: #10b981;"></div>
                                        </div>
                                    </a>

                                    <a href="orders.php?status=delivered" class="text-decoration-none p-1 px-2 rounded-2 transition-hover text-dark d-block" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.74rem;">
                                            <span class="d-flex align-items-center fw-semibold">
                                                <span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: #8b5cf6;"></span>
                                                Delivered
                                            </span>
                                            <span class="fw-bold text-dark">
                                                <?php echo $statusCounts['delivered']; ?> 
                                                <small class="text-muted fw-normal" style="font-size: 0.68rem;">(<?php echo $totalOrderCount > 0 ? round(($statusCounts['delivered'] / $totalOrderCount) * 100) : 0; ?>%)</small>
                                            </span>
                                        </div>
                                        <div class="progress rounded-pill" style="height: 4px; background: #e2e8f0;">
                                            <div class="progress-bar rounded-pill" style="width: <?php echo $totalOrderCount > 0 ? round(($statusCounts['delivered'] / $totalOrderCount) * 100) : 0; ?>%; background: #8b5cf6;"></div>
                                        </div>
                                    </a>

                                    <?php if ($statusCounts['cancelled'] > 0): ?>
                                    <a href="orders.php?status=cancelled" class="text-decoration-none p-1 px-2 rounded-2 transition-hover text-dark d-block" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.74rem;">
                                            <span class="d-flex align-items-center fw-semibold text-danger">
                                                <span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: #ef4444;"></span>
                                                Cancelled
                                            </span>
                                            <span class="fw-bold text-dark">
                                                <?php echo $statusCounts['cancelled']; ?> 
                                                <small class="text-muted fw-normal" style="font-size: 0.68rem;">(<?php echo round(($statusCounts['cancelled'] / $totalOrderCount) * 100); ?>%)</small>
                                            </span>
                                        </div>
                                        <div class="progress rounded-pill" style="height: 4px; background: #e2e8f0;">
                                            <div class="progress-bar rounded-pill" style="width: <?php echo round(($statusCounts['cancelled'] / $totalOrderCount) * 100); ?>%; background: #ef4444;"></div>
                                        </div>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 52px; height: 52px; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0;">
                                <i class="fas fa-chart-pie fa-2x"></i>
                            </div>
                            <div class="fw-bold text-dark small mb-1">No Orders Yet</div>
                            <p class="text-muted small mb-0" style="font-size: 0.75rem;">Create orders to see status distribution.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer Summary Strip -->
                <div class="pt-2 border-top d-flex justify-content-between align-items-center mt-2">
                    <span class="text-muted small" style="font-size: 0.74rem;">
                        <i class="fas fa-tshirt me-1 text-primary"></i><strong><?php echo ($statusCounts['pending'] + $statusCounts['in_progress'] + $statusCounts['completed']); ?></strong> in production
                    </span>
                    <a href="orders.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs" style="font-size: 0.74rem;">
                        All Orders &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. QUICK ACTIONS BAR -->
    <div class="mb-3">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-bolt me-2" style="color: #6366f1; font-size: 0.88rem;"></i>
            <h6 class="fw-bold mb-0" style="color: #4f46e5; font-size: 0.92rem;">Quick Actions</h6>
        </div>
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <a href="orders.php?action=create" class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 text-decoration-none h-100 transition-hover" style="background: #faf5ff; border: 1px solid #ede9fe !important;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 36px; height: 36px; background: #8b5cf6; color: #ffffff;">
                                <i class="fas fa-clipboard-list" style="font-size: 0.95rem;"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate" style="font-size: 0.80rem;">New Order</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.65rem;">Create a new order</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right flex-shrink-0 ms-1" style="color: #8b5cf6; font-size: 0.72rem;"></i>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="measurements.php?action=create" class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 text-decoration-none h-100 transition-hover" style="background: #f0f9ff; border: 1px solid #e0f2fe !important;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 36px; height: 36px; background: #0284c7; color: #ffffff;">
                                <i class="fas fa-ruler-combined" style="font-size: 0.95rem;"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate" style="font-size: 0.80rem; color: #0284c7;">Add Measurement</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.65rem;">Record measurements</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right flex-shrink-0 ms-1" style="color: #0284c7; font-size: 0.72rem;"></i>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="customers.php" class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 text-decoration-none h-100 transition-hover" style="background: #f0fdf4; border: 1px solid #dcfce7 !important;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 36px; height: 36px; background: #16a34a; color: #ffffff;">
                                <i class="fas fa-users" style="font-size: 0.95rem;"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate" style="font-size: 0.80rem; color: #16a34a;">Customers</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.65rem;">View customers</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right flex-shrink-0 ms-1" style="color: #16a34a; font-size: 0.72rem;"></i>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="expenses.php?action=create" class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 text-decoration-none h-100 transition-hover" style="background: #fff7ed; border: 1px solid #ffedd5 !important;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 36px; height: 36px; background: #ea580c; color: #ffffff;">
                                <i class="fas fa-wallet" style="font-size: 0.95rem;"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate" style="font-size: 0.80rem; color: #ea580c;">Record Expense</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.65rem;">Add business expense</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right flex-shrink-0 ms-1" style="color: #ea580c; font-size: 0.72rem;"></i>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- 3. RECENT ORDERS (Spacious Full-Width Table) -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom flex-wrap gap-2">
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                        <i class="fas fa-clock text-purple me-2" style="color: #8b5cf6;"></i><?php echo !empty($search) ? 'Orders Matching "' . htmlspecialchars($search) . '"' : 'Recent Orders'; ?>
                    </h6>
                    <a href="<?php echo !empty($search) ? 'orders?search=' . urlencode($search) : 'orders.php'; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 bg-white text-dark shadow-xs" style="border-color: #e2e8f0; font-size: 0.78rem;">
                        View All Orders
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recentOrders)): ?>
                        <!-- 1. Desktop & Tablet View: Full Table (d-none d-md-block) -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <tr>
                                        <th class="ps-4">Order #</th>
                                        <th>Customer</th>
                                        <th>Cloth Type</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th class="text-center pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): 
                                        $cName = $order['customer_name'] ?? (($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                                        if (empty($cName) && !empty($order['measurement_customer_name'])) {
                                            $cName = $order['measurement_customer_name'];
                                        }
                                        $cName = $cName ?: 'Customer';
                                        $statusPillStyle = match($order['status']) {
                                            'completed' => 'background-color: #10b981; color: white;',
                                            'pending' => 'background-color: #f59e0b; color: white;',
                                            'in_progress' => 'background-color: #06b6d4; color: white;',
                                            'delivered' => 'background-color: #3b82f6; color: white;',
                                            default => 'background-color: #ef4444; color: white;'
                                        };
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="rounded-2 d-inline-flex align-items-center justify-content-center p-1" style="width: 28px; height: 28px; background: #eff6ff; color: #3b82f6;">
                                                    <i class="fas fa-tshirt" style="font-size: 0.75rem;"></i>
                                                </span>
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="fw-bold text-primary text-decoration-none">
                                                    #<?php echo htmlspecialchars($order['order_number']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($cName); ?></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($order['cloth_type_name'] ?? 'Custom'); ?></td>
                                        <td class="fw-bold text-dark"><?php echo format_currency($order['total_amount']); ?></td>
                                        <td class="text-muted"><?php echo format_date($order['due_date'], 'd M Y'); ?></td>
                                        <td>
                                            <span class="badge rounded-pill" style="<?php echo $statusPillStyle; ?> font-size: 0.72rem; padding: 4px 10px; font-weight: 500;">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light rounded-circle text-primary me-1 p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="View Details">
                                                <i class="fas fa-eye" style="font-size: 0.72rem;"></i>
                                            </a>
                                            <a href="orders.php?edit=<?php echo $order['id']; ?>" class="btn btn-sm btn-light rounded-circle text-secondary p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="Edit Order">
                                                <i class="fas fa-pencil-alt" style="font-size: 0.72rem;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 2. Mobile Card View: Beautiful Stacked Cards (d-block d-md-none) -->
                        <div class="d-md-none p-3 d-flex flex-column gap-2" style="background: #f8fafc;">
                            <?php foreach ($recentOrders as $order): 
                                $cName = $order['customer_name'] ?? (($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                                if (empty($cName) && !empty($order['measurement_customer_name'])) {
                                    $cName = $order['measurement_customer_name'];
                                }
                                $cName = $cName ?: 'Customer';
                                $statusPillStyle = match($order['status']) {
                                    'completed' => 'background-color: #10b981; color: white;',
                                    'pending' => 'background-color: #f59e0b; color: white;',
                                    'in_progress' => 'background-color: #06b6d4; color: white;',
                                    'delivered' => 'background-color: #3b82f6; color: white;',
                                    default => 'background-color: #ef4444; color: white;'
                                };
                            ?>
                            <div class="card border rounded-3 p-3 shadow-xs bg-white" style="border-color: #e2e8f0 !important;">
                                <!-- Top Row: Order # and Status -->
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-2 d-inline-flex align-items-center justify-content-center" style="width: 26px; height: 26px; background: #eff6ff; color: #3b82f6;">
                                            <i class="fas fa-tshirt" style="font-size: 0.72rem;"></i>
                                        </span>
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="fw-bold text-primary text-decoration-none" style="font-size: 0.88rem;">
                                            #<?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </div>
                                    <span class="badge rounded-pill" style="<?php echo $statusPillStyle; ?> font-size: 0.70rem; padding: 4px 9px; font-weight: 500;">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </div>

                                <!-- Middle Row: Customer, Garment, Amount, Due Date -->
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.90rem;">
                                            <?php echo htmlspecialchars($cName); ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.76rem;">
                                            <i class="fas fa-cut me-1 opacity-75"></i><?php echo htmlspecialchars($order['cloth_type_name'] ?? 'Custom'); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                                            <?php echo format_currency($order['total_amount']); ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            Due: <?php echo format_date($order['due_date'], 'd M Y'); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom Row: Quick Action Links -->
                                <div class="pt-2 border-top d-flex justify-content-end align-items-center gap-2">
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-primary fw-medium" style="font-size: 0.74rem;">
                                        <i class="fas fa-eye me-1"></i> Details
                                    </a>
                                    <a href="orders.php?edit=<?php echo $order['id']; ?>" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-secondary fw-medium" style="font-size: 0.74rem;">
                                        <i class="fas fa-pencil-alt me-1"></i> Edit
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-2x text-muted mb-2 opacity-50"></i>
                            <div class="fw-bold text-dark small mb-1"><?php echo !empty($search) ? 'No Matching Orders' : 'No Orders Yet'; ?></div>
                            <p class="text-muted small mb-2" style="font-size: 0.72rem;"><?php echo !empty($search) ? 'No orders matched.' : 'Create your first order to start tracking.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. AT LAST: MONTHLY REVENUE GRAPH (col-lg-8) + GROW BUSINESS BANNER (col-lg-4) -->
    <div class="row g-3 mb-4">
        <!-- Monthly Revenue (col-lg-8) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                            <i class="fas fa-chart-line text-purple me-2" style="color: #8b5cf6;"></i>Monthly Revenue
                        </h6>
                        <small class="text-muted" style="font-size: 0.72rem;">Annual earnings and revenue growth trends</small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm rounded-pill px-3 py-1 bg-white text-dark shadow-xs" style="border: 1px solid #e2e8f0; font-size: 0.75rem;" data-bs-toggle="dropdown">
                            <i class="fas fa-calendar-alt text-muted me-1" style="font-size: 0.70rem;"></i> This Year <i class="fas fa-chevron-down ms-1" style="font-size: 0.62rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 10px; font-size: 0.8rem;">
                            <li><a class="dropdown-item active" href="#">This Year</a></li>
                            <li><a class="dropdown-item" href="#">Last Year</a></li>
                        </ul>
                    </div>
                </div>
                <div style="position: relative; height: 215px; width: 100%;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Grow Your Tailoring Business Card (col-lg-4) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 overflow-hidden h-100 d-flex flex-column justify-content-between" style="background: #fff1f2; border: 1px solid #ffe4e6 !important;">
                <!-- Header -->
                <div class="mb-2">
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.3;">
                        Grow Your Tailoring Business
                    </h5>
                </div>

                <!-- Main Content: Features & Large Image -->
                <div class="d-flex align-items-center justify-content-between gap-3 my-auto py-2">
                    <ul class="list-unstyled mb-0 d-flex flex-column justify-content-between gap-3" style="font-size: 0.90rem; color: #334155;">
                        <li class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2" style="font-size: 1rem;"></i>
                            <span class="fw-semibold">Manage Orders Easily</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2" style="font-size: 1rem;"></i>
                            <span class="fw-semibold">Track Measurements</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2" style="font-size: 1rem;"></i>
                            <span class="fw-semibold">Generate Invoices</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2" style="font-size: 1rem;"></i>
                            <span class="fw-semibold">Keep Customers Happy</span>
                        </li>
                    </ul>
                    <div class="flex-shrink-0">
                        <img src="../assets/images/dashboard/grow_business_bg.png" alt="Tailoring Business" style="width: 175px; height: 165px; object-fit: cover; object-position: center; border-radius: 16px; box-shadow: 0 4px 15px rgba(225, 29, 72, 0.12); border: 2px solid #ffffff;">
                    </div>
                </div>

                <!-- Bottom Footer -->
                <div class="pt-2 border-top d-flex justify-content-between align-items-center" style="border-color: rgba(255,228,230,0.9) !important;">
                    <span class="text-muted fw-semibold" style="font-size: 0.76rem;">TailorPro Workshop</span>
                    <a href="company-settings.php" class="btn btn-sm btn-danger rounded-pill px-3 py-1 shadow-xs fw-semibold" style="background: #e11d48; border-color: #e11d48; font-size: 0.78rem;">
                        Shop Settings &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.transition-hover {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.transition-hover:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08) !important;
}
@media (max-width: 767.98px) {
    .welcome-banner .d-flex.align-items-stretch {
        flex-direction: column !important;
    }
    .welcome-banner .d-flex.align-items-stretch > div[style*="flex: 0 0"] {
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }
    .welcome-banner [style*="flex: 0 0 40%"],
    .welcome-banner [style*="flex: 0 0 35%"],
    .welcome-banner [style*="flex: 0 0 25%"] {
        max-width: 100% !important;
        flex: unset !important;
        width: 100% !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
    if (revenueCtx) {
        const gradient = revenueCtx.createLinearGradient(0, 0, 0, 230);
        gradient.addColorStop(0, 'rgba(139, 92, 246, 0.28)');
        gradient.addColorStop(1, 'rgba(139, 92, 246, 0.00)');

    const orderStatusCanvas = document.getElementById('orderStatusPieChart');
    const orderStatusCtx = orderStatusCanvas?.getContext('2d');
    if (orderStatusCtx) {
        // Custom External HTML Tooltip so text NEVER gets clipped by canvas bounds
        const getOrCreateTooltip = () => {
            let tooltipEl = document.getElementById('chartjs-order-status-tooltip');
            if (!tooltipEl) {
                tooltipEl = document.createElement('div');
                tooltipEl.id = 'chartjs-order-status-tooltip';
                tooltipEl.style.background = 'rgba(15, 23, 42, 0.94)';
                tooltipEl.style.borderRadius = '8px';
                tooltipEl.style.color = '#ffffff';
                tooltipEl.style.opacity = '0';
                tooltipEl.style.pointerEvents = 'none';
                tooltipEl.style.position = 'absolute';
                tooltipEl.style.transform = 'translate(-50%, -125%)';
                tooltipEl.style.transition = 'opacity .12s ease';
                tooltipEl.style.padding = '6px 12px';
                tooltipEl.style.fontSize = '12px';
                tooltipEl.style.fontWeight = '500';
                tooltipEl.style.fontFamily = 'system-ui, -apple-system, sans-serif';
                tooltipEl.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.35)';
                tooltipEl.style.zIndex = '99999';
                tooltipEl.style.whiteSpace = 'nowrap';
                document.body.appendChild(tooltipEl);
            }
            return tooltipEl;
        };

        const externalTooltipHandler = (context) => {
            const {chart, tooltip} = context;
            const tooltipEl = getOrCreateTooltip();

            if (tooltip.opacity === 0) {
                tooltipEl.style.opacity = '0';
                return;
            }

            if (tooltip.dataPoints && tooltip.dataPoints.length > 0) {
                const dp = tooltip.dataPoints[0];
                const label = dp.label || '';
                const val = dp.raw || 0;
                const colors = ['#06b6d4', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444'];
                const color = colors[dp.dataIndex] || '#6366f1';
                const total = dp.dataset.data.reduce((a, b) => a + b, 0);
                const pct = total > 0 ? Math.round((val / total) * 100) : 0;

                tooltipEl.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: ${color}; flex-shrink: 0;"></span>
                        <span style="font-weight: 600;">${label}:</span>
                        <span style="color: #f1f5f9;">${val} ${val === 1 ? 'order' : 'orders'} (${pct}%)</span>
                    </div>
                `;
            }

            const canvasRect = chart.canvas.getBoundingClientRect();
            tooltipEl.style.opacity = '1';
            tooltipEl.style.left = canvasRect.left + window.scrollX + tooltip.caretX + 'px';
            tooltipEl.style.top = canvasRect.top + window.scrollY + tooltip.caretY + 'px';
        };

        orderStatusCanvas.addEventListener('mouseleave', () => {
            const tooltipEl = document.getElementById('chartjs-order-status-tooltip');
            if (tooltipEl) tooltipEl.style.opacity = '0';
        });

        new Chart(orderStatusCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    'In Progress',
                    'Pending',
                    'Completed',
                    'Delivered'<?php echo $statusCounts['cancelled'] > 0 ? ", 'Cancelled'" : ""; ?>
                ],
                datasets: [{
                    data: [
                        <?php echo $statusCounts['in_progress']; ?>,
                        <?php echo $statusCounts['pending']; ?>,
                        <?php echo $statusCounts['completed']; ?>,
                        <?php echo $statusCounts['delivered']; ?>
                        <?php echo $statusCounts['cancelled'] > 0 ? ", " . $statusCounts['cancelled'] : ""; ?>
                    ],
                    backgroundColor: [
                        '#06b6d4',
                        '#f59e0b',
                        '#10b981',
                        '#8b5cf6'<?php echo $statusCounts['cancelled'] > 0 ? ", '#ef4444'" : ""; ?>
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4,
                    cutout: '72%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false,
                        external: externalTooltipHandler
                    }
                }
            }
        });
    }

    new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                datasets: [{
                    label: 'Revenue',
                    data: [15, 20, 25, 30, 40, 60, 100, 220, 450, 700],
                    borderColor: '#8b5cf6',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ₹' + Number(context.raw || 0).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 700,
                        ticks: {
                            stepSize: 100,
                            font: { size: 9 },
                            color: '#94a3b8',
                            callback: function(value) {
                                if ([0, 100, 300, 500, 700].indexOf(value) !== -1) {
                                    return '₹' + value;
                                }
                                return null;
                            }
                        },
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 10 },
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>