<?php
$page_title = 'Dashboard'; 
require_once 'includes/header.php';
require_once '../models/Customer.php';
require_once 'models/Order.php';
require_once 'models/Invoice.php';
require_once 'models/User.php';
require_once '../models/EmailChangeRequest.php';
require_once 'models/Company.php';
$customerModel = new Customer();
$orderModel = new Order();
$invoiceModel = new Invoice();
$userModel = new User();
$emailChangeRequestModel = new EmailChangeRequest();
$companyModelForProgress = new Company();
$customerStats = $customerModel->getCustomerStats();
$orderStats = $orderModel->getOrderStats();
$invoiceStats = $invoiceModel->getInvoiceStats();
$userStats = $userModel->getUserStats();
$companyId = get_company_id();

$recentOrders = $orderModel->getOrdersWithDetails([], 5);
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
$scheduleItems = [];
$todayOrders = !empty($ordersDueToday) ? $ordersDueToday : (!empty($recentOrders) ? array_slice($recentOrders, 0, 2) : []);
$times = ['10:00 AM', '02:00 PM', '04:30 PM'];
$timeColors = ['#8b5cf6', '#f43f5e', '#10b981'];
$i = 0;
foreach ($todayOrders as $ord) {
    $cName = $ord['customer_name'] ?? (($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
    if (empty($cName) && !empty($ord['measurement_customer_name'])) {
        $cName = $ord['measurement_customer_name'];
    }
    $cName = $cName ?: 'Customer';
    $cCloth = $ord['cloth_type_name'] ?? 'Garment';
    $scheduleItems[] = [
        'time' => $times[$i % count($times)],
        'color' => $timeColors[$i % count($timeColors)],
        'name' => $cName,
        'action' => ($i === 0 ? 'Fitting' : 'Trial') . ' - ' . $cCloth
    ];
    $i++;
}
if (empty($scheduleItems)) {
    $scheduleItems = [
        ['time' => '10:00 AM', 'color' => '#8b5cf6', 'name' => 'Rana had', 'action' => 'Fitting - pent shirt'],
        ['time' => '02:00 PM', 'color' => '#f43f5e', 'name' => 'chetna', 'action' => 'Trial - rond kurti']
    ];
}
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
        $fieldsDone[$key] = !empty(trim((string)($companyForProgress[$key] ?? '')));
    }
    $prog = (int)$completionProgress;
    $r = 34; $circ = round(2 * M_PI * $r, 2);
    $offset = round($circ - ($prog / 100) * $circ, 2);
    ?>
    <!-- Dashboard Welcome Banner -->
    <style>
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

    /* Mobile specific adjustments */
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

        <!-- Background Image (Tailoring workshop with mannequin visible on the right) -->
        <div class="position-absolute top-0 start-0 w-100 h-100 wb-bg-img"
             style="background: url('../assets/images/dashboard/welcome_banner_bg.png') no-repeat right center / cover; z-index: 0;"></div>

        <!-- Royal blue to violet gradient overlay (leaves right side clear for mannequin) -->
        <div class="position-absolute top-0 start-0 w-100 h-100 wb-bg-gradient"
             style="background: linear-gradient(90deg, 
                 rgba(15, 23, 115, 0.96) 0%, 
                 rgba(24, 35, 145, 0.92) 26%, 
                 rgba(35, 48, 175, 0.82) 46%, 
                 rgba(58, 48, 175, 0.60) 65%, 
                 rgba(70, 45, 168, 0.22) 80%, 
                 transparent 92%); z-index: 1;"></div>

        <!-- Content Area -->
        <div class="position-relative text-white p-3 p-md-4 wb-content-pad" style="z-index: 2;">

            <!-- ── DESKTOP LAYOUT (>= 992px) ── -->
            <div class="d-none d-lg-flex flex-column justify-content-between" style="min-height: 168px;">
                <!-- Desktop Top Row -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <div style="font-size: 0.84rem; color: rgba(255,255,255,0.85); margin-bottom: 2px;">Welcome back,</div>
                        <div style="font-size: 1.85rem; font-weight: 800; line-height: 1.15; color: #ffffff; letter-spacing: -0.5px; margin-bottom: 4px;">
                            <?php echo htmlspecialchars($current_user['name'] ?? 'User'); ?> 👋
                        </div>
                        <div style="font-size: 0.78rem; color: rgba(255,255,255,0.82); line-height: 1.45; max-width: 320px;">
                            Complete your shop profile to unlock all features and grow your tailoring business.
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
                                Add your shop details to get more customers and manage everything easily.
                            </div>
                        </div>
                    </div>

                    <div class="pe-5 me-3">
                        <a href="company-settings.php" class="wb-btn-clean">
                            Complete Profile
                            <i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i>
                        </a>
                    </div>
                </div>

                <!-- Desktop Bottom Row -->
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

                    <div class="d-flex align-items-center gap-3 pe-5 me-3" style="flex: 0 0 46%; max-width: 46%;">
                        <div style="flex: 1; height: 8px; background: rgba(255,255,255,0.22); border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $prog; ?>%; background: #00e676; border-radius: 10px; box-shadow: 0 0 10px rgba(0,230,118,0.7); transition: width 1.2s ease;"></div>
                        </div>
                        <span class="fw-bold text-white flex-shrink-0" style="font-size: 0.92rem; min-width: 34px;">
                            <?php echo $prog; ?>%
                        </span>
                    </div>
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

                <!-- 3. White Pill Button: "Complete Profile →" -->
                <div class="mt-1">
                    <a href="company-settings.php" class="wb-pill-btn-mob">
                        Complete Profile <i class="fas fa-arrow-right ms-1" style="font-size: 0.70rem;"></i>
                    </a>
                </div>

                <!-- 4. Checklist Chips in 2 neat rows -->
                <div class="d-flex flex-column gap-1 mt-1 pt-1">
                    <!-- Row 1: Address, City, State -->
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

                    <!-- Row 2: PIN Code, Business Logo -->
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
    <!-- /Dashboard Welcome Banner -->

    <!-- Stat Cards (2x2 Grid with compact height matching reference screenshot) -->
    <div class="row g-2 g-md-3 mb-2 mb-md-3">
        <!-- 1. Monthly Revenue -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #faf5ff; border: 1px solid #f3e8ff !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #ede9fe; color: #7c3aed;">
                        <i class="fas fa-wallet" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 7px; background: #c084fc; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 13px; background: #a855f7; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 10px; background: #9333ea; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 18px; background: #7e22ce; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;">₹<?php echo number_format($currentMonthRevenue); ?></div>
                    <div class="text-success fw-semibold" style="font-size: 0.63rem; line-height: 1.2;">
                        <i class="fas fa-arrow-up me-1" style="font-size: 0.55rem;"></i>+3% this month
                    </div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2;">Monthly Revenue</div>
                </div>
            </div>
        </div>

        <!-- 2. Total Orders -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #f0fdf4; border: 1px solid #dcfce7 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #dcfce7; color: #10b981;">
                        <i class="fas fa-layer-group" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 7px; background: #86efac; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 13px; background: #4ade80; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 10px; background: #22c55e; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 18px; background: #16a34a; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;"><?php echo number_format($orderStats['total'] ?? 0); ?></div>
                    <div class="text-success fw-semibold" style="font-size: 0.63rem; line-height: 1.2;">
                        <i class="fas fa-arrow-up me-1" style="font-size: 0.55rem;"></i>+3% this month
                    </div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2;">Total Orders</div>
                </div>
            </div>
        </div>

        <!-- 3. Total Invoices -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #fffbeb; border: 1px solid #fef3c7 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #fef3c7; color: #f59e0b;">
                        <i class="fas fa-file-invoice" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 6px; background: #fde68a; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 11px; background: #fcd34d; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 15px; background: #fbbf24; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 18px; background: #f59e0b; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;"><?php echo number_format($invoiceStats['total'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2; margin-top: 3px;">Total Invoices</div>
                </div>
            </div>
        </div>

        <!-- 4. Pending Orders -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 h-100" style="background: #fff1f2; border: 1px solid #fee2e2 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width: 30px; height: 30px; background: #fee2e2; color: #ef4444;">
                        <i class="fas fa-bell" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="d-flex align-items-end gap-1 opacity-75">
                        <span style="width: 3px; height: 7px; background: #fca5a5; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 14px; background: #f87171; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 11px; background: #ef4444; border-radius: 2px;"></span>
                        <span style="width: 3px; height: 19px; background: #dc2626; border-radius: 2px;"></span>
                    </div>
                </div>
                <div class="mt-1 pt-1">
                    <div class="fw-bold text-dark mb-0" style="font-size: 1.15rem; line-height: 1.15; letter-spacing: -0.3px;"><?php echo (int)($orderStats['pending'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500; line-height: 1.2; margin-top: 3px;">Pending Orders</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue & Order Status Row -->
    <div class="row g-3 mb-3">
        <!-- Monthly Revenue Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.92rem;">
                        <i class="fas fa-chart-line text-purple me-2" style="color: #8b5cf6;"></i>Monthly Revenue
                    </h6>
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
                <div style="position: relative; height: 190px; width: 100%;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Order Status (5 Circular Donut Rings matching reference screenshot) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.92rem;">
                        <i class="fas fa-layer-group text-primary me-2" style="color: #6366f1;"></i>Order Status
                    </h6>
                    <span class="badge rounded-pill" style="background: #a78bfa; color: #ffffff; font-weight: 600; font-size: 0.72rem; padding: 4px 10px;">
                        <?php echo $totalOrderCount; ?> Total
                    </span>
                </div>

                <?php
                $orderRings = [
                    ['label' => 'Pending', 'count' => $statusCounts['pending'], 'color' => '#f59e0b'],
                    ['label' => 'In Progress', 'count' => $statusCounts['in_progress'], 'color' => '#06b6d4'],
                    ['label' => 'Completed', 'count' => $statusCounts['completed'], 'color' => '#10b981'],
                    ['label' => 'Delivered', 'count' => $statusCounts['delivered'], 'color' => '#8b5cf6'],
                    ['label' => 'Cancelled', 'count' => $statusCounts['cancelled'], 'color' => '#ef4444'],
                ];
                $rRing = 16;
                $circRing = round(2 * M_PI * $rRing, 2);
                ?>
                <div class="d-flex align-items-start justify-content-between text-center pt-2" style="gap: 2px;">
                    <?php foreach ($orderRings as $ring): 
                        $pct = ($totalOrderCount > 0) ? round(($ring['count'] / $totalOrderCount) * 100) : 0;
                        $ringOffset = round($circRing - ($pct / 100) * $circRing, 2);
                    ?>
                    <div class="order-status-ring-col text-center" style="flex: 1; min-width: 0;">
                        <div class="position-relative d-inline-block" style="width: 44px; height: 44px;">
                            <svg width="44" height="44" viewBox="0 0 44 44">
                                <circle cx="22" cy="22" r="<?php echo $rRing; ?>" fill="none" stroke="#f1f5f9" stroke-width="4.5" />
                                <?php if ($pct > 0): ?>
                                <circle cx="22" cy="22" r="<?php echo $rRing; ?>" fill="none"
                                        stroke="<?php echo $ring['color']; ?>" stroke-width="4.5"
                                        stroke-linecap="round"
                                        stroke-dasharray="<?php echo $circRing; ?>"
                                        stroke-dashoffset="<?php echo $ringOffset; ?>"
                                        transform="rotate(-90 22 22)" />
                                <?php endif; ?>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle fw-bold" style="font-size: 0.62rem; color: #1e293b;">
                                <?php echo $pct; ?>%
                            </div>
                        </div>
                        <div class="fw-bold text-dark mt-1" style="font-size: 0.82rem; line-height: 1.1;"><?php echo $ring['count']; ?></div>
                        <div class="text-muted text-truncate px-1" style="font-size: 0.65rem; line-height: 1.2;"><?php echo $ring['label']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop bar view for large screens -->
                <div class="d-none d-xl-flex flex-column gap-2 mt-3 pt-2 border-top">
                    <?php foreach ($orderRings as $st): 
                        $pct = $totalOrderCount > 0 ? round(($st['count'] / $totalOrderCount) * 100) : 0;
                    ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted text-truncate" style="width: 75px; font-size: 0.72rem;"><?php echo $st['label']; ?></span>
                        <div class="flex-grow-1" style="height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $pct; ?>%; background: <?php echo $st['color']; ?>; border-radius: 10px;"></div>
                        </div>
                        <span class="text-dark fw-bold" style="width: 25px; font-size: 0.72rem; text-align: right;"><?php echo $st['count']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
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
                <a href="invoices.php?action=create" class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 text-decoration-none h-100 transition-hover" style="background: #f0fdf4; border: 1px solid #dcfce7 !important;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 36px; height: 36px; background: #16a34a; color: #ffffff;">
                                <i class="fas fa-file-invoice" style="font-size: 0.95rem;"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate" style="font-size: 0.80rem; color: #16a34a;">Create Invoice</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.65rem;">Generate invoice</div>
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
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white h-100">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                        <i class="fas fa-clock text-purple me-2" style="color: #8b5cf6;"></i>Recent Orders
                    </h6>
                    <a href="orders.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 bg-white text-dark shadow-xs" style="border-color: #e2e8f0; font-size: 0.78rem;">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recentOrders)): ?>
                        <div class="table-responsive">
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
                                        <td class="text-muted"><?php echo format_date($order['due_date'], 'Y-m-d'); ?></td>
                                        <td>
                                            <span class="badge rounded-pill" style="<?php echo $statusPillStyle; ?> font-size: 0.72rem; padding: 4px 10px; font-weight: 500;">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light rounded-circle text-primary me-1 p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="View">
                                                <i class="fas fa-eye" style="font-size: 0.72rem;"></i>
                                            </a>
                                            <a href="orders.php?edit=<?php echo $order['id']; ?>" class="btn btn-sm btn-light rounded-circle text-secondary p-0 shadow-xs" style="width: 28px; height: 28px; line-height: 28px;" title="Edit">
                                                <i class="fas fa-pencil-alt" style="font-size: 0.72rem;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3 opacity-50"></i>
                            <div class="fw-bold text-dark fs-6 mb-1">No Orders Yet</div>
                            <p class="text-muted small mb-3">Create your first order to start tracking.</p>
                            <a href="orders.php?action=create" class="btn btn-primary rounded-pill px-4 py-2">
                                <i class="fas fa-plus me-1"></i> New Order
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Today's Schedule & Promo -->
        <div class="col-lg-4 d-flex flex-column gap-3">
            <!-- Today's Schedule Card -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="fw-bold text-dark" style="font-size: 0.92rem;">
                        <i class="fas fa-calendar-alt text-purple me-2" style="color: #8b5cf6;"></i>Today's Schedule
                    </span>
                    <a href="orders.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-0 bg-white" style="font-size: 0.72rem; border-color: #e2e8f0;">
                        View All
                    </a>
                </div>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($scheduleItems as $sc): ?>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge rounded-pill text-white px-3 py-2 flex-shrink-0" style="background: <?php echo $sc['color']; ?>; font-size: 0.72rem; font-weight: 500;">
                            <?php echo $sc['time']; ?>
                        </span>
                        <div class="text-truncate">
                            <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($sc['name']); ?></div>
                            <div class="text-muted small text-truncate" style="font-size: 0.72rem;"><?php echo htmlspecialchars($sc['action']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4 p-3 overflow-hidden" style="background: #fff1f2; border: 1px solid #ffe4e6 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.3;">
                            Grow Your<br>Tailoring Business
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-1" style="font-size: 0.72rem; color: #334155;">
                            <li><i class="fas fa-check-circle text-success me-1"></i> Manage Orders Easily</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Track Measurements</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Generate Invoices</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Keep Customers Happy</li>
                        </ul>
                    </div>
                    <div class="flex-shrink-0 ms-2">
                        <img src="../assets/images/dashboard/grow_business_bg.png" alt="Tailoring Business" style="width: 140px; height: 110px; object-fit: cover; object-position: right center; border-radius: 12px;">
                    </div>
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

/* Welcome Banner responsive */
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