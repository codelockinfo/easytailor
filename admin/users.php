<?php
/**
 * User Management Page
 * Tailoring Management System
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

// Only admins can access this page
require_role('admin');

require_once 'models/User.php';
require_once '../helpers/SubscriptionHelper.php';

$userModel = new User();
$companyId = get_company_id();
$message = '';
$messageType = '';

if (!$companyId) {
    $_SESSION['message'] = 'Your company information is missing. Please contact support.';
    $_SESSION['messageType'] = 'error';
    header('Location: dashboard.php');
    exit;
}

// Handle form submissions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                // Check subscription limit for users
                $limitCheck = SubscriptionHelper::canAddUser($companyId);
                if (!$limitCheck['allowed']) {
                    $_SESSION['message'] = $limitCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('users', SubscriptionHelper::getCurrentPlan($companyId));
                    $_SESSION['messageType'] = 'error';
                    header('Location: users.php');
                    exit;
                }
                
                // Validate email uniqueness (globally unique)
                if (!empty($_POST['email']) && $userModel->emailExists($_POST['email'])) {
                    $_SESSION['message'] = 'Email already exists';
                    $_SESSION['messageType'] = 'error';
                    header('Location: users.php');
                    exit;
                }
                
                // Validate username uniqueness (globally unique)
                if ($userModel->usernameExists($_POST['username'])) {
                    $_SESSION['message'] = 'Username already exists';
                    $_SESSION['messageType'] = 'error';
                    header('Location: users.php');
                    exit;
                }
                
                $data = [
                    'username' => sanitize_input($_POST['username']),
                    'email' => sanitize_input($_POST['email']),
                    'password' => $_POST['password'],
                    'full_name' => sanitize_input($_POST['full_name']),
                    'role' => $_POST['role'],
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'status' => $_POST['status'],
                    'company_id' => $companyId
                ];
                
                $userId = $userModel->createUser($data);
                if ($userId) {
                    $_SESSION['message'] = 'User created successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to create user';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: users.php');
                exit;
                break;
                
            case 'update':
                $userId = (int)$_POST['user_id'];
                $existingUser = $userModel->findByIdAndCompany($userId, $companyId);

                if (!$existingUser) {
                    $_SESSION['message'] = 'User not found or access denied';
                    $_SESSION['messageType'] = 'error';
                    header('Location: users.php');
                    exit;
                }

                // Validate email uniqueness (globally unique, exclude current user)
                if (!empty($_POST['email']) && $userModel->emailExists($_POST['email'], $userId)) {
                    $_SESSION['message'] = 'Email already exists';
                    $_SESSION['messageType'] = 'error';
                    header('Location: users.php');
                    exit;
                }
                
                // Validate username uniqueness (globally unique, exclude current user)
                if ($userModel->usernameExists($_POST['username'], $userId)) {
                    $_SESSION['message'] = 'Username already exists';
                    $_SESSION['messageType'] = 'error';
                    header('Location: users.php');
                    exit;
                }

                $data = [
                    'username' => sanitize_input($_POST['username']),
                    'email' => sanitize_input($_POST['email']),
                    'full_name' => sanitize_input($_POST['full_name']),
                    'role' => $_POST['role'],
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'status' => $_POST['status']
                ];
                
                // Update password only if provided
                if (!empty($_POST['password'])) {
                    $userModel->updatePassword($userId, $_POST['password']);
                }
                
                if ($userModel->update($userId, $data)) {
                    $_SESSION['message'] = 'User updated successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to update user';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: users.php');
                exit;
                break;
                
            case 'delete':
                $userId = (int)$_POST['user_id'];
                $existingUser = $userModel->findByIdAndCompany($userId, $companyId);

                if (!$existingUser) {
                    $_SESSION['message'] = 'User not found or access denied';
                    $_SESSION['messageType'] = 'error';
                    header('Location: users.php');
                    exit;
                }

                // Don't allow deleting yourself
                if ($userId == get_user_id()) {
                    $_SESSION['message'] = 'You cannot delete your own account';
                    $_SESSION['messageType'] = 'error';
                } elseif ($userModel->delete($userId)) {
                    $_SESSION['message'] = 'User deleted successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to delete user';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: users.php');
                exit;
                break;
        }
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        header('Location: users.php');
        exit;
    }
}

// Get messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Check subscription limits
$currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
$userLimitCheck = SubscriptionHelper::canAddUser($companyId);

// Get users
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$conditions = [];
if (!empty($role_filter)) {
    $conditions['role'] = $role_filter;
}
$conditions['status'] = 'active';

$users = $userModel->findAll($conditions, 'full_name ASC');
$totalUsers = count($users);
$users = array_slice($users, $offset, $limit);
$totalPages = ceil($totalUsers / $limit);

// Get user for editing
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = $userModel->findByIdAndCompany((int)$_GET['edit'], $companyId);
}

// Get user statistics
$stats = [
    'total' => $userModel->countByCompany($companyId),
    'active' => $userModel->countByCompany($companyId, ['status' => 'active']),
    'admins' => $userModel->countByCompany($companyId, ['role' => 'admin']),
    'tailors' => $userModel->countByCompany($companyId, ['role' => 'tailor']),
    'staff' => $userModel->countByCompany($companyId, ['role' => 'staff']),
    'cashiers' => $userModel->countByCompany($companyId, ['role' => 'cashier'])
];

if (!function_exists('getUserAvatarDesign')) {
    function getUserAvatarDesign($name, $index) {
        $palettes = [
            ['bg' => '#eff6ff', 'border' => '#dbeafe', 'color' => '#3b82f6'],
            ['bg' => '#f0fdf4', 'border' => '#dcfce7', 'color' => '#10b981'],
            ['bg' => '#faf5ff', 'border' => '#ede9fe', 'color' => '#8b5cf6'],
            ['bg' => '#fffbeb', 'border' => '#fef3c7', 'color' => '#f59e0b'],
            ['bg' => '#fdf2f8', 'border' => '#fce7f3', 'color' => '#ec4899'],
            ['bg' => '#f0fdfa', 'border' => '#ccfbf1', 'color' => '#0d9488']
        ];
        return $palettes[$index % count($palettes)];
    }
}

if (!function_exists('getUserRoleBadge')) {
    function getUserRoleBadge($role) {
        $r = strtolower(trim($role ?? ''));
        switch ($r) {
            case 'admin':
                return ['bg' => '#fee2e2', 'color' => '#b91c1c', 'border' => '#fecaca', 'icon' => 'user-shield'];
            case 'tailor':
                return ['bg' => '#ede9fe', 'color' => '#7c3aed', 'border' => '#ddd6fe', 'icon' => 'cut'];
            case 'staff':
                return ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fde68a', 'icon' => 'user-tie'];
            case 'cashier':
                return ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe', 'icon' => 'cash-register'];
            default:
                return ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0', 'icon' => 'user'];
        }
    }
}

// NOW include header (after all redirects and logic are complete)
$page_title = 'User Management';
require_once 'includes/header.php';
?>

<style>
.cat-stat-box {
    border-radius: 14px;
    padding: 16px 18px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid transparent;
}
.cat-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}
.cat-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    border: 1px solid transparent;
    transition: all 0.15s ease;
    background: #ffffff;
    cursor: pointer;
    text-decoration: none;
}
.cat-action-view {
    background: #f0fdf4;
    color: #16a34a;
    border-color: #bbf7d0;
}
.cat-action-view:hover {
    background: #dcfce7;
    color: #15803d;
}
.cat-action-edit {
    background: #eff6ff;
    color: #2563eb;
    border-color: #dbeafe;
}
.cat-action-edit:hover {
    background: #dbeafe;
    color: #1d4ed8;
}
.cat-action-del {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fee2e2;
}
.cat-action-del:hover {
    background: #fee2e2;
    color: #b91c1c;
}
.cat-table-head th {
    background-color: #f8fafc !important;
    color: #334155 !important;
    font-size: 0.78rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0 !important;
    padding: 12px 14px !important;
    text-transform: uppercase;
}
.category-table tbody tr {
    transition: background-color 0.12s ease;
}
.category-table tbody tr:hover {
    background-color: #f8fafc;
}
.users-main-card {
    border-radius: 14px;
    border: 1px solid #f1f5f9 !important;
    background-color: #ffffff;
}
@media (max-width: 767.98px) {
    .users-main-card {
        background: transparent !important;
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        margin-bottom: 0 !important;
    }
    .users-main-card > .card-body {
        background: transparent !important;
        background-color: transparent !important;
        padding: 0 !important;
    }
    #userMobileCardsList {
        padding: 0 !important;
    }
    #paginationContainer {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
}
.user-mobile-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}
.tailor-profile-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f7ff 100%) !important;
    border: 1px solid #e2e8f0 !important;
    border-left: 4px solid #6366f1 !important;
    border-radius: 16px !important;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04) !important;
    transition: none !important;
}
.tailor-profile-card:hover {
    transform: none !important;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04) !important;
}
.tailor-profile-avatar {
    width: 54px;
    height: 54px;
    border-radius: 15px;
    font-size: 1.35rem;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
    box-shadow: 0 6px 14px rgba(79, 70, 229, 0.24);
    letter-spacing: -0.5px;
}
.tailor-meta-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: stretch;
}
.tailor-meta-chip {
    display: flex;
    align-items: center;
    gap: 9px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 7px 12px;
    min-width: 0;
}
.tailor-meta-icon {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.78rem;
}
.tailor-meta-label {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.45px;
    text-transform: uppercase;
    color: #94a3b8;
    line-height: 1.1;
}
.tailor-meta-value {
    font-size: 0.80rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.25;
    word-break: break-word;
}
@media (max-width: 767.98px) {
    .tailor-profile-avatar {
        width: 46px;
        height: 46px;
        border-radius: 13px;
        font-size: 1.15rem;
    }
    .tailor-meta-grid {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr;
        gap: 6px;
    }
    .tailor-meta-chip {
        padding: 7px 10px;
    }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($userLimitCheck['remaining']) && $userLimitCheck['remaining'] <= 1 && $userLimitCheck['remaining'] > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>User Limit Warning:</strong> You have <?php echo $userLimitCheck['remaining']; ?> user slot(s) remaining in your <?php echo ucfirst($currentPlan); ?> plan. 
        <a href="subscriptions.php" class="alert-link fw-semibold">Upgrade your plan</a> to add more users.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (!$userLimitCheck['allowed']): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-ban me-2"></i>
        <strong>User Limit Reached:</strong> <?php echo $userLimitCheck['message']; ?> 
        <a href="subscriptions.php" class="alert-link fw-semibold">Upgrade your plan</a> to add more users.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- 1. Shared Modern Header Banner -->
<?php
$pageHeaderTitle = 'User Management';
$pageHeaderSubtitle = 'Manage team members, assign tailoring shop roles, and manage permissions.';
include 'includes/page-header.php';
?>

<!-- 2. Pastel Stat Cards -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL USERS</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="statTotalUsers"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dbeafe; color: #2563eb; font-size: 0.95rem;">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">ACTIVE USERS</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="statActiveUsers"><?php echo number_format($stats['active']); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dcfce7; color: #16a34a; font-size: 0.95rem;">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TAILORS & CUTTERS</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="statTailors"><?php echo number_format($stats['tailors']); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #ede9fe; color: #9333ea; font-size: 0.95rem;">
                <i class="fas fa-cut"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">ADMINS</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="statAdminsStaff"><?php echo number_format($stats['admins']); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #fef3c7; color: #d97706; font-size: 0.95rem;">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>
    </div>
</div>

<!-- 3. Clean Single-Row Search, Filter & Action Bar -->
<div class="row g-2 align-items-center mb-3">
    <!-- 1. Search Input -->
    <div class="col-12 col-md-4 col-lg-5">
        <div class="position-relative">
            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
            <input type="text" 
                   id="searchInput"
                   class="form-control" 
                   placeholder="Search users by name, username, email, phone..."
                   value="<?php echo htmlspecialchars($search); ?>"
                   autocomplete="off"
                   style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding-left: 2.4rem; padding-right: 2.4rem;">
            <button type="button" id="clearSearch" class="btn btn-link text-muted position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-decoration-none" style="display: <?php echo !empty($search) ? 'block' : 'none'; ?>; width: 24px; height: 24px;" title="Clear search">
                <i class="fas fa-times" style="font-size: 0.85rem;"></i>
            </button>
        </div>
    </div>
    
    <!-- 2. Role Filter -->
    <div class="col-6 col-md-3 col-lg-3">
        <select class="form-select" id="roleFilter" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 2.2rem 0.45rem 0.8rem; line-height: 1.4; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Roles</option>
            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="tailor" <?php echo $role_filter === 'tailor' ? 'selected' : ''; ?>>Tailor</option>
        </select>
    </div>
    
    <!-- 3. Clear button -->
    <div class="col-6 col-md-2 col-lg-2">
        <button type="button" id="clearAllFiltersBtn" class="btn btn-outline-danger align-items-center justify-content-center text-nowrap w-100" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 16px; display: inline-flex;" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
    </div>
    
    <!-- 4. Add User button -->
    <div class="col-12 col-md-3 col-lg-2 d-flex justify-content-md-end">
        <?php if (!$userLimitCheck['allowed']): ?>
<button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap w-100 w-md-auto px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; opacity: 0.65;" data-bs-toggle="modal" data-bs-target="#userLimitUpgradeModal" title="User limit reached. Click to upgrade your plan.">
    <i class="fas fa-plus"></i>
    <span>Add User</span>
</button>
<?php else: ?>
<button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap w-100 w-md-auto px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#userModal">
    <i class="fas fa-plus"></i>
    <span>Add User</span>
</button>
<?php endif; ?>
    </div>
</div>

<div id="searchResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="searchCount">0</strong> users found</span>
    </div>
</div>

<!-- 4. Users Table -->
<div class="card shadow-sm border-0 mb-3 users-main-card" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($users)): ?>
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>USER</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortUsersBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3">EMAIL</th>
                            <th class="py-3">PHONE</th>
                            <th class="py-3">ROLE</th>
                            <th class="py-3">STATUS</th>
                            <th class="py-3">JOINED</th>
                            <th class="pe-3 py-3 text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($users as $index => $user): 
                            $cleanName = trim($user['full_name'] ?? 'User');
                            $initial = strtoupper(mb_substr($cleanName, 0, 1) ?: 'U');
                            $avColor = getUserAvatarDesign($cleanName, $index);
                            $roleBadge = getUserRoleBadge($user['role'] ?? '');
                            $isActive = ($user['status'] ?? 'active') === 'active';
                        ?>
                        <tr class="user-row" data-name="<?php echo htmlspecialchars(strtolower($cleanName)); ?>">
                            <td class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo $avColor['bg']; ?>; border: 1px solid <?php echo $avColor['border']; ?>; color: <?php echo $avColor['color']; ?>; font-size: 0.88rem;">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 180px;">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </div>
                                        <div class="text-muted text-truncate" style="font-size: 0.72rem; max-width: 180px;">
                                            @<?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($user['email'])): ?>
                                    <div class="text-dark" style="font-size: 0.82rem;">
                                        <i class="fas fa-envelope text-muted me-1" style="font-size: 0.72rem;"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($user['phone'])): ?>
                                    <div class="text-dark" style="font-size: 0.82rem;">
                                        <i class="fas fa-phone text-muted me-1" style="font-size: 0.72rem;"></i>
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if ($user['role'] === 'tailor'): ?>
                                    <span class="badge fw-medium d-inline-flex align-items-center gap-1" 
                                          style="background: <?php echo $roleBadge['bg']; ?>; color: <?php echo $roleBadge['color']; ?>; border: 1px solid <?php echo $roleBadge['border']; ?>; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; cursor: pointer; transition: transform 0.15s ease;" 
                                          onclick="viewTailorAssignments(<?php echo (int)$user['id']; ?>)" 
                                          title="Click to view assigned orders & garments">
                                        <i class="fas fa-<?php echo $roleBadge['icon']; ?>"></i>
                                        <span><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
                                        <i class="fas fa-external-link-alt ms-1" style="font-size: 0.6rem; opacity: 0.75;"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="badge fw-medium d-inline-flex align-items-center gap-1" style="background: <?php echo $roleBadge['bg']; ?>; color: <?php echo $roleBadge['color']; ?>; border: 1px solid <?php echo $roleBadge['border']; ?>; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px;">
                                        <i class="fas fa-<?php echo $roleBadge['icon']; ?>"></i>
                                        <span><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: <?php echo $isActive ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $isActive ? '#15803d' : '#64748b'; ?>; border: 1px solid <?php echo $isActive ? '#bbf7d0' : '#e2e8f0'; ?>; font-size: 0.72rem;">
                                    <?php echo ucfirst($user['status'] ?? 'Active'); ?>
                                </span>
                            </td>
                            <td class="py-3 text-muted" style="font-size: 0.82rem;">
                                <?php echo format_date($user['created_at']); ?>
                            </td>
                            <td class="pe-3 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <?php if ($user['role'] === 'tailor'): ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-view" 
                                            onclick="viewTailorAssignments(<?php echo (int)$user['id']; ?>)"
                                            title="View Tailor Orders & Garments">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-edit" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                            title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != get_user_id()): ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-del" 
                                            onclick="deleteUser(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')"
                                            title="Delete User">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards View -->
            <div class="d-md-none d-flex flex-column gap-3 mb-3" id="userMobileCardsList">
                <?php foreach ($users as $index => $user): 
                    $cleanName = trim($user['full_name'] ?? 'User');
                    $initial = strtoupper(mb_substr($cleanName, 0, 1) ?: 'U');
                    $avColor = getUserAvatarDesign($cleanName, $index);
                    $roleBadge = getUserRoleBadge($user['role'] ?? '');
                    $isActive = ($user['status'] ?? 'active') === 'active';
                ?>
                <div class="user-mobile-card user-card-row" data-name="<?php echo htmlspecialchars(strtolower($cleanName)); ?>">
                    <!-- Top bar: Avatar + Name + Role + Status -->
                    <div class="d-flex align-items-start justify-content-between gap-2 pb-2 mb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 38px; height: 38px; border-radius: 12px; background: <?php echo $avColor['bg']; ?>; border: 1px solid <?php echo $avColor['border']; ?>; color: <?php echo $avColor['color']; ?>; font-size: 0.92rem;">
                                <?php echo $initial; ?>
                            </div>
                            <div>
                                <strong class="text-dark d-block" style="font-size: 0.92rem; line-height: 1.2;"><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                <span class="text-muted small" style="font-size: 0.75rem;">@<?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <?php if ($user['role'] === 'tailor'): ?>
                                <span class="badge fw-medium d-inline-flex align-items-center gap-1" 
                                      style="background: <?php echo $roleBadge['bg']; ?>; color: <?php echo $roleBadge['color']; ?>; border: 1px solid <?php echo $roleBadge['border']; ?>; font-size: 0.72rem; padding: 3px 6px; border-radius: 6px; cursor: pointer;" 
                                      onclick="viewTailorAssignments(<?php echo (int)$user['id']; ?>)" 
                                      title="Click to view assigned orders">
                                    <i class="fas fa-<?php echo $roleBadge['icon']; ?>"></i>
                                    <span><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
                                    <i class="fas fa-external-link-alt ms-1" style="font-size: 0.55rem; opacity: 0.75;"></i>
                                </span>
                            <?php else: ?>
                                <span class="badge fw-medium d-inline-flex align-items-center gap-1" style="background: <?php echo $roleBadge['bg']; ?>; color: <?php echo $roleBadge['color']; ?>; border: 1px solid <?php echo $roleBadge['border']; ?>; font-size: 0.72rem; padding: 3px 6px; border-radius: 6px;">
                                    <i class="fas fa-<?php echo $roleBadge['icon']; ?>"></i>
                                    <span><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
                                </span>
                            <?php endif; ?>
                            <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: <?php echo $isActive ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $isActive ? '#15803d' : '#64748b'; ?>; border: 1px solid <?php echo $isActive ? '#bbf7d0' : '#e2e8f0'; ?>; font-size: 0.7rem;">
                                <?php echo ucfirst($user['status'] ?? 'Active'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Details Body -->
                    <div class="p-2 mb-2 rounded-3" style="background: #f8fafc; font-size: 0.82rem;">
                        <?php if (!empty($user['email'])): ?>
                            <div class="text-dark mb-1 text-truncate">
                                <i class="fas fa-envelope me-2 text-muted" style="width: 14px;"></i>
                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-dark text-decoration-none">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($user['phone'])): ?>
                            <div class="text-dark mb-1">
                                <i class="fas fa-phone me-2 text-muted" style="width: 14px;"></i>
                                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" class="text-dark text-decoration-none fw-medium">
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size: 0.78rem;">
                            <i class="far fa-calendar-alt me-2 text-muted" style="width: 14px;"></i>Joined: <?php echo format_date($user['created_at']); ?>
                        </div>
                    </div>

                    <!-- Footer: Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-1 pt-1">
                        <?php if ($user['role'] === 'tailor'): ?>
                        <button type="button" 
                                class="cat-action-btn cat-action-view" 
                                onclick="viewTailorAssignments(<?php echo (int)$user['id']; ?>)"
                                title="View Tailor Orders & Garments">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php endif; ?>
                        <button type="button" 
                                class="cat-action-btn cat-action-edit" 
                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($user['id'] != get_user_id()): ?>
                        <button type="button" 
                                class="cat-action-btn cat-action-del" 
                                onclick="deleteUser(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')"
                                title="Delete User">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="paginationContainer" class="p-3">
            <?php if ($totalPages > 1): ?>
                <nav aria-label="User pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                    <i class="fas fa-users fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No users found</h5>
                <p class="text-muted small mb-3">
                    <?php if (!empty($search) || !empty($role_filter)): ?>
                        No users match your search criteria.
                    <?php else: ?>
                        Get started by adding your first team member.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($role_filter)): ?>
                    <button type="button" class="btn text-white fw-semibold px-4 py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#userModal" <?php echo !$userLimitCheck['allowed'] ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus me-1"></i>Add User
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userLimitUpgradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #fee2e2; color: #ef4444;">
                    <i class="fas fa-crown fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">User Limit Reached</h5>
                <p class="text-muted small mb-4">
                    <?php echo htmlspecialchars($userLimitCheck['message'] ?? 'You have reached the maximum limit of users for your plan. Please upgrade your subscription to add more users.'); ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light px-4 fw-medium" data-bs-dismiss="modal" style="border-radius: 12px; font-size: 0.85rem;">Cancel</button>
                    <a href="subscriptions.php" class="btn text-white px-4 fw-semibold" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;">
                        <i class="fas fa-arrow-up me-1"></i> Upgrade Plan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <form method="POST" id="userForm">
                <div class="modal-header border-bottom py-3" style="background: #fafafa;">
                    <h5 class="modal-title fw-bold text-dark" id="userModalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="userAction" value="create">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label fw-semibold small text-muted">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label fw-semibold small text-muted">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required style="border-radius: 10px;">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-semibold small text-muted">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label fw-semibold small text-muted">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-radius: 10px 0 0 10px;">+91</span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone"
                                       placeholder="10-digit mobile number"
                                       pattern="[0-9]{10}"
                                       maxlength="10"
                                       style="border-radius: 0 10px 10px 0;">
                            </div>
                            <small class="text-muted">Enter 10-digit mobile number (digits only)</small>
                            <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label fw-semibold small text-muted">Role *</label>
                            <select class="form-select" id="role" name="role" required style="border-radius: 10px;">
                                <option value="">Select Role</option>
                                <option value="admin">Admin - Full access</option>
                                <option value="tailor">Tailor - Can be assigned to orders</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label fw-semibold small text-muted">Status *</label>
                            <select class="form-select" id="status" name="status" required style="border-radius: 10px;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold small text-muted">Password <span id="passwordRequired">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" style="border-top-left-radius: 10px; border-bottom-left-radius: 10px;" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" onclick="togglePasswordVisibility('password', this)" style="border-top-right-radius: 10px; border-bottom-right-radius: 10px; border-color: #dee2e6; " title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted" id="passwordHelp">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold small text-muted">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" style="border-radius: 10px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3" style="background: #fafafa;">
                    <button type="button" class="btn btn-light fw-medium px-4" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.85rem;">Cancel</button>
                    <button type="submit" class="btn text-white fw-semibold px-4" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 10px; font-size: 0.85rem; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);">
                        <i class="fas fa-save me-1"></i>Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white border-0">
                <div class="d-flex align-items-center w-100">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 text-center">
                        <h5 class="modal-title mb-0" id="deleteModalLabel">
                            <strong>Confirm Deletion</strong>
                        </h5>
                        <small class="opacity-75">This action cannot be undone</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-4">
                    <i class="fas fa-user-slash fa-4x text-danger mb-3"></i>
                </div>
                <h6 class="mb-3">Are you sure you want to delete this user?</h6>
                <div class="alert alert-light border">
                    <strong id="deleteUserName" class="text-primary"></strong>
                </div>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    All user data and access permissions will be permanently removed.
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary me-3" data-bs-dismiss="modal" style="border-radius: 10px;">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger px-4" style="border-radius: 10px;">
                        <i class="fas fa-trash me-2"></i>Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tailor Assigned Orders & Garments Modal -->
<div class="modal fade" id="tailorViewModal" tabindex="-1" aria-labelledby="tailorViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <!-- Modal Header -->
            <div class="modal-header border-bottom py-3 px-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; border-radius: 12px; background: #ede9fe; color: #7c3aed; font-size: 1.1rem; border: 1px solid #ddd6fe;">
                        <i class="fas fa-cut"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="tailorViewModalLabel">Tailor Details & Assigned Orders</h5>
                        <small class="text-muted" style="font-size: 0.78rem;">View tailor profile, assigned garments, and order progress</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-3 p-md-4" style="background-color: #fafbfc;">
                <!-- Loader -->
                <div id="tailorModalLoader" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 2.8rem; height: 2.8rem;"></div>
                    <div class="fw-semibold text-dark mb-1">Loading Tailor Information...</div>
                    <small class="text-muted">Fetching assigned orders and garments</small>
                </div>

                <!-- Error Box -->
                <div id="tailorModalError" class="alert alert-danger py-3 px-4 mb-3" style="display: none; border-radius: 12px;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="tailorModalErrorMsg">Unable to load tailor details.</span>
                </div>

                <!-- Content Container -->
                <div id="tailorModalContent" style="display: none;">
                    <!-- 1. Tailor Profile Summary Card -->
                    <div class="card border-0 mb-3 tailor-profile-card">
                        <div class="card-body p-3">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div id="tailorViewAvatar" class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold tailor-profile-avatar">
                                        T
                                    </div>
                                    <div class="min-w-0">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h5 class="fw-bold text-dark mb-0 text-capitalize" id="tailorViewName" style="font-size: 1.08rem; letter-spacing: -0.2px;">Tailor Name</h5>
                                            <span class="badge fw-semibold d-inline-flex align-items-center gap-1" id="tailorViewRoleBadge" style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; font-size: 0.70rem; padding: 4px 9px; border-radius: 7px;">
                                                <i class="fas fa-cut"></i> Tailor
                                            </span>
                                            <span class="badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 px-2 py-1" id="tailorViewStatusBadge" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 0.70rem;">
                                                Active
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="tailor-meta-grid">
                                    <div class="tailor-meta-chip" id="tailorViewEmailWrap">
                                        <span class="tailor-meta-icon" style="background: #eef2ff; color: #4f46e5;">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="tailor-meta-label">Email</div>
                                            <div class="tailor-meta-value" id="tailorViewEmail">—</div>
                                        </div>
                                    </div>
                                    <div class="tailor-meta-chip" id="tailorViewPhoneWrap">
                                        <span class="tailor-meta-icon" style="background: #e0f2fe; color: #0284c7;">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="tailor-meta-label">Phone</div>
                                            <div class="tailor-meta-value" id="tailorViewPhone">Not provided</div>
                                        </div>
                                    </div>
                                    <div class="tailor-meta-chip">
                                        <span class="tailor-meta-icon" style="background: #f3e8ff; color: #7c3aed;">
                                            <i class="far fa-calendar-alt"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="tailor-meta-label">Joined</div>
                                            <div class="tailor-meta-value" id="tailorViewJoined">—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Pastel Stat Boxes -->
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="p-3 text-center rounded-3 h-100" style="background: #eff6ff; border: 1px solid #dbeafe;">
                                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px;">TOTAL ASSIGNED</span>
                                <div class="fs-4 fw-bold text-dark mt-1" id="tailorStatTotal">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 text-center rounded-3 h-100" style="background: #fffbeb; border: 1px solid #fef3c7;">
                                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px;">PENDING</span>
                                <div class="fs-4 fw-bold text-dark mt-1" id="tailorStatPending">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 text-center rounded-3 h-100" style="background: #f0fdf4; border: 1px solid #dcfce7;">
                                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px;">IN PROGRESS</span>
                                <div class="fs-4 fw-bold text-dark mt-1" id="tailorStatInProgress">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 text-center rounded-3 h-100" style="background: #faf5ff; border: 1px solid #ede9fe;">
                                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px;">COMPLETED / DONE</span>
                                <div class="fs-4 fw-bold text-dark mt-1" id="tailorStatCompleted">0</div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Filter Row inside Modal -->
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark" style="font-size: 0.92rem;">Assigned Garments & Orders</span>
                            <span class="badge rounded-pill bg-light text-dark border px-2 py-1" id="tailorItemsCountBadge" style="font-size: 0.72rem;">0 Items</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0 justify-content-end">
                            <select id="tailorModalStatusFilter" class="form-select form-select-sm" style="width: auto; min-width: 135px; height: 36px; padding: 0 2.2rem 0 0.75rem; border-radius: 10px; font-size: 0.8rem; line-height: 1.4;">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="delivered">Delivered</option>
                            </select>
                            <div class="position-relative" style="min-width: 160px;">
                                <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted" style="font-size: 0.75rem;"></i>
                                <input type="text" id="tailorModalSearchInput" class="form-control form-control-sm ps-4" placeholder="Filter cloth/customer..." style="border-radius: 10px; font-size: 0.8rem; height: 36px;">
                            </div>
                        </div>
                    </div>

                    <!-- 4. Table (Desktop) -->
                    <div class="card border-0 shadow-sm" style="border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0 !important;">
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-hover mb-0 align-middle">
                                <thead>
                                    <tr style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                        <th class="ps-3 py-2 text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color:white !important;">#</th>
                                        <th class="py-2 text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color:white !important">ORDER #</th>
                                        <th class="py-2 text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color:white !important">CUSTOMER</th>
                                        <th class="py-2 text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color:white !important">GARMENT / CLOTH TYPE</th>
                                        <th class="py-2 text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color:white !important">DUE DATE</th>
                                        <th class="py-2 text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color:white !important">STATUS</th>
                                        <th class="pe-3 py-2 text-end text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700;color:white !important">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody id="tailorModalTableBody">
                                    <!-- Rendered dynamically -->
                                </tbody>
                            </table>
                        </div>

                        <!-- 5. Cards (Mobile) -->
                        <div class="d-md-none p-2 d-flex flex-column gap-2" id="tailorModalCardsBody">
                            <!-- Rendered dynamically -->
                        </div>

                        <!-- Empty State -->
                        <div id="tailorModalEmptyState" class="text-center py-5" style="display: none;">
                            <div class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                                <i class="fas fa-clipboard-list fa-lg"></i>
                            </div>
                            <div class="fw-semibold text-dark">No assigned garments found</div>
                            <small class="text-muted">No orders match the selected filter criteria.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-top py-2 px-4" style="background: #fafafa;">
                <button type="button" class="btn btn-secondary px-4 fw-medium" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.85rem;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const currentLoggedInUserId = <?php echo (int)get_user_id(); ?>;
let currentTailorData = null;

function viewTailorAssignments(userId) {
    const modalEl = document.getElementById('tailorViewModal');
    if (!modalEl) return;
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();

    const loader = document.getElementById('tailorModalLoader');
    const content = document.getElementById('tailorModalContent');
    const errorBox = document.getElementById('tailorModalError');
    const errorMsg = document.getElementById('tailorModalErrorMsg');

    loader.style.display = 'block';
    content.style.display = 'none';
    errorBox.style.display = 'none';

    // Reset filters
    const statusFilter = document.getElementById('tailorModalStatusFilter');
    const searchFilter = document.getElementById('tailorModalSearchInput');
    if (statusFilter) statusFilter.value = '';
    if (searchFilter) searchFilter.value = '';

    fetch(`ajax/get_tailor_assignments.php?user_id=${encodeURIComponent(userId)}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Failed to fetch tailor assignments');
            }
            currentTailorData = data;
            renderTailorModal(data);
            loader.style.display = 'none';
            content.style.display = 'block';
        })
        .catch(err => {
            console.error('Error fetching tailor assignments:', err);
            loader.style.display = 'none';
            errorBox.style.display = 'block';
            errorMsg.textContent = err.message || 'Error loading tailor details';
        });
}

function renderTailorModal(data) {
    const user = data.user || {};
    const stats = data.stats || {};
    const items = data.items || [];

    // Profile info
    const cleanName = user.full_name || user.username || 'Tailor';
    const initial = cleanName.charAt(0).toUpperCase() || 'T';
    document.getElementById('tailorViewName').textContent = cleanName;
    document.getElementById('tailorViewAvatar').textContent = initial;
    const emailEl = document.getElementById('tailorViewEmail');
    const phoneEl = document.getElementById('tailorViewPhone');
    emailEl.textContent = user.email || 'Not provided';
    emailEl.classList.toggle('text-muted', !user.email);
    phoneEl.textContent = user.phone || 'Not provided';
    phoneEl.classList.toggle('text-muted', !user.phone);

    const joinedDate = (() => {
        if (!user.created_at) return '—';
        try {
            const d = new Date(user.created_at);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        } catch (e) {
            return user.created_at;
        }
    })();
    document.getElementById('tailorViewJoined').textContent = joinedDate;

    // Status badge
    const isActive = (user.status || 'active').toLowerCase() === 'active';
    const statusBadge = document.getElementById('tailorViewStatusBadge');
    statusBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background: ${isActive ? '#16a34a' : '#94a3b8'}; display: inline-block;"></span>${isActive ? 'Active' : 'Inactive'}`;
    statusBadge.style.background = isActive ? '#dcfce7' : '#f1f5f9';
    statusBadge.style.color = isActive ? '#15803d' : '#64748b';
    statusBadge.style.borderColor = isActive ? '#bbf7d0' : '#e2e8f0';

    // Stats
    document.getElementById('tailorStatTotal').textContent = stats.total || 0;
    document.getElementById('tailorStatPending').textContent = stats.pending || 0;
    document.getElementById('tailorStatInProgress').textContent = stats.in_progress || 0;
    document.getElementById('tailorStatCompleted').textContent = (stats.completed || 0) + (stats.delivered || 0);

    renderTailorItemsList(items);
}

function renderTailorItemsList(items) {
    const tableBody = document.getElementById('tailorModalTableBody');
    const cardsBody = document.getElementById('tailorModalCardsBody');
    const emptyState = document.getElementById('tailorModalEmptyState');
    const countBadge = document.getElementById('tailorItemsCountBadge');

    const statusFilterVal = (document.getElementById('tailorModalStatusFilter')?.value || '').toLowerCase();
    const searchVal = (document.getElementById('tailorModalSearchInput')?.value || '').toLowerCase().trim();

    const filtered = items.filter(it => {
        const st = (it.garment_status || 'pending').toLowerCase();
        if (statusFilterVal && st !== statusFilterVal) return false;
        if (searchVal) {
            const matchesOrder = (it.order_number || '').toLowerCase().includes(searchVal);
            const matchesCust = (it.customer_name || '').toLowerCase().includes(searchVal);
            const matchesCloth = (it.cloth_type_name || '').toLowerCase().includes(searchVal);
            const matchesPhone = (it.customer_phone || '').toLowerCase().includes(searchVal);
            if (!matchesOrder && !matchesCust && !matchesCloth && !matchesPhone) return false;
        }
        return true;
    });

    if (countBadge) {
        countBadge.textContent = `${filtered.length} ${filtered.length === 1 ? 'Item' : 'Items'}`;
    }

    if (filtered.length === 0) {
        if (tableBody) tableBody.innerHTML = '';
        if (cardsBody) cardsBody.innerHTML = '';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    const statusBadgeMap = {
        'pending': { bg: '#fffbeb', color: '#b45309', border: '#fef3c7', label: 'Pending' },
        'in_progress': { bg: '#eff6ff', color: '#1d4ed8', border: '#bfdbfe', label: 'In Progress' },
        'completed': { bg: '#f0fdf4', color: '#15803d', border: '#bbf7d0', label: 'Completed' },
        'delivered': { bg: '#faf5ff', color: '#7c3aed', border: '#ede9fe', label: 'Delivered' },
        'cancelled': { bg: '#fef2f2', color: '#b91c1c', border: '#fee2e2', label: 'Cancelled' }
    };

    let tableHtml = '';
    let cardsHtml = '';

    filtered.forEach((it, idx) => {
        const stKey = (it.garment_status || 'pending').toLowerCase();
        const bStyle = statusBadgeMap[stKey] || statusBadgeMap['pending'];
        const dueFormatted = it.due_date ? it.due_date : '—';
        const isOverdue = it.due_date && new Date(it.due_date) < new Date() && !['completed', 'delivered', 'cancelled'].includes(stKey);

        // Desktop row
        tableHtml += `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td class="ps-3 py-3 text-muted" style="font-size: 0.78rem;">${idx + 1}</td>
                <td class="py-3">
                    <a href="order-details.php?id=${encodeURIComponent(it.order_id)}" class="badge fw-semibold text-decoration-none" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.8rem; padding: 4px 8px; border-radius: 6px;">
                        #${escapeHtml(it.order_number)} <i class="fas fa-external-link-alt ms-1" style="font-size: 0.6rem;"></i>
                    </a>
                </td>
                <td class="py-3">
                    <div class="fw-bold text-dark" style="font-size: 0.85rem;">${escapeHtml(it.customer_name)}</div>
                    ${it.customer_phone ? `<small class="text-muted" style="font-size: 0.72rem;">${escapeHtml(it.customer_phone)}</small>` : ''}
                </td>
                <td class="py-3">
                    <span class="badge fw-medium" style="background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; font-size: 0.8rem; padding: 5px 9px; border-radius: 8px;">
                        <i class="fas fa-tshirt me-1 text-primary" style="font-size: 0.75rem;"></i>${escapeHtml(it.cloth_type_name)}
                    </span>
                    ${it.quantity && it.quantity > 1 ? `<span class="badge bg-light text-muted border ms-1" style="font-size: 0.68rem;">Qty: ${it.quantity}</span>` : ''}
                </td>
                <td class="py-3" style="font-size: 0.82rem;">
                    <span class="${isOverdue ? 'text-danger fw-bold' : 'text-dark'}">
                        ${isOverdue ? '<i class="fas fa-exclamation-circle text-danger me-1"></i>' : ''}${escapeHtml(dueFormatted)}
                    </span>
                </td>
                <td class="py-3">
                    <span class="badge fw-semibold px-2 py-1" style="background: ${bStyle.bg}; color: ${bStyle.color}; border: 1px solid ${bStyle.border}; font-size: 0.72rem; border-radius: 6px;">
                        ${escapeHtml(bStyle.label)}
                    </span>
                </td>
                <td class="pe-3 py-3 text-end">
                    <a href="order-details.php?id=${encodeURIComponent(it.order_id)}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" style="border-radius: 8px; font-size: 0.75rem; padding: 3px 8px;">
                        <i class="fas fa-eye"></i> View Order
                    </a>
                </td>
            </tr>
        `;

        // Mobile card
        cardsHtml += `
            <div class="p-3 bg-white rounded-3 border shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <a href="order-details.php?id=${encodeURIComponent(it.order_id)}" class="badge fw-semibold text-decoration-none" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.78rem;">
                        #${escapeHtml(it.order_number)}
                    </a>
                    <span class="badge fw-semibold px-2 py-1" style="background: ${bStyle.bg}; color: ${bStyle.color}; border: 1px solid ${bStyle.border}; font-size: 0.7rem; border-radius: 6px;">
                        ${escapeHtml(bStyle.label)}
                    </span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.88rem;">${escapeHtml(it.customer_name)}</div>
                        ${it.customer_phone ? `<small class="text-muted" style="font-size: 0.72rem;">${escapeHtml(it.customer_phone)}</small>` : ''}
                    </div>
                    <span class="badge fw-medium" style="background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; font-size: 0.78rem;">
                        ${escapeHtml(it.cloth_type_name)}
                    </span>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="font-size: 0.78rem;">
                    <span class="${isOverdue ? 'text-danger fw-bold' : 'text-muted'}">
                        Due: ${escapeHtml(dueFormatted)}
                    </span>
                    <a href="order-details.php?id=${encodeURIComponent(it.order_id)}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;">
                        <i class="fas fa-eye me-1"></i>View
                    </a>
                </div>
            </div>
        `;
    });

    if (tableBody) tableBody.innerHTML = tableHtml;
    if (cardsBody) cardsBody.innerHTML = cardsHtml;
}

document.addEventListener('DOMContentLoaded', function() {
    const statusSel = document.getElementById('tailorModalStatusFilter');
    const searchInp = document.getElementById('tailorModalSearchInput');
    if (statusSel) {
        statusSel.addEventListener('change', function() {
            if (currentTailorData && currentTailorData.items) {
                renderTailorItemsList(currentTailorData.items);
            }
        });
    }
    if (searchInp) {
        searchInp.addEventListener('input', function() {
            if (currentTailorData && currentTailorData.items) {
                renderTailorItemsList(currentTailorData.items);
            }
        });
    }
});

function editUser(user) {
    document.getElementById('userModalTitle').textContent = 'Edit User';
    document.getElementById('userAction').value = 'update';
    document.getElementById('userId').value = user.id;
    
    // Populate form fields
    document.getElementById('full_name').value = user.full_name || '';
    document.getElementById('username').value = user.username || '';
    document.getElementById('email').value = user.email || '';
    
    // Handle phone number - remove +91 prefix if present, keep only 10 digits
    let phoneNumber = user.phone || '';
    if (phoneNumber.startsWith('+91')) {
        phoneNumber = phoneNumber.replace('+91', '').trim();
    }
    phoneNumber = phoneNumber.replace(/[^0-9]/g, '').slice(0, 10);
    document.getElementById('phone').value = phoneNumber;
    document.getElementById('role').value = user.role || '';
    document.getElementById('status').value = user.status || 'active';
    document.getElementById('address').value = user.address || '';
    
    // Make password optional for editing
    document.getElementById('password').required = false;
    document.getElementById('passwordRequired').style.display = 'none';
    document.getElementById('passwordHelp').textContent = 'Leave blank to keep current password';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function deleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function togglePasswordVisibility(fieldId, btn) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    const icon = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        field.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

// Reset modal when closed
document.getElementById('userModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('userAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHelp').textContent = 'Minimum 6 characters';
    
    const pwdField = document.getElementById('password');
    if (pwdField) pwdField.type = 'password';
    const pwdIcon = document.querySelector('#togglePasswordBtn i');
    if (pwdIcon) {
        pwdIcon.classList.remove('fa-eye-slash');
        pwdIcon.classList.add('fa-eye');
    }
    
    document.getElementById('userForm').reset();
});

// Helper for HTML escaping
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

const userAvatarPalettes = [
    { bg: '#eff6ff', border: '#dbeafe', color: '#3b82f6' },
    { bg: '#f0fdf4', border: '#dcfce7', color: '#10b981' },
    { bg: '#faf5ff', border: '#ede9fe', color: '#8b5cf6' },
    { bg: '#fffbeb', border: '#fef3c7', color: '#f59e0b' },
    { bg: '#fdf2f8', border: '#fce7f3', color: '#ec4899' },
    { bg: '#f0fdfa', border: '#ccfbf1', color: '#0d9488' }
];

function getJsUserRoleBadge(role) {
    const r = (role || '').toLowerCase().trim();
    switch (r) {
        case 'admin':
            return { bg: '#fee2e2', color: '#b91c1c', border: '#fecaca', icon: 'user-shield' };
        case 'tailor':
            return { bg: '#ede9fe', color: '#7c3aed', border: '#ddd6fe', icon: 'cut' };
        case 'staff':
            return { bg: '#fffbeb', color: '#b45309', border: '#fde68a', icon: 'user-tie' };
        case 'cashier':
            return { bg: '#eff6ff', color: '#2563eb', border: '#bfdbfe', icon: 'cash-register' };
        default:
            return { bg: '#f1f5f9', color: '#475569', border: '#e2e8f0', icon: 'user' };
    }
}

// AJAX Filtering Functionality
let searchTimeout;

document.addEventListener('DOMContentLoaded', function() {
    // Setup phone validation
    setupPhoneValidation('phone', '+91');
    
    // Update phone value with prefix before form submission
    const userForm = document.getElementById('userForm');
    if (userForm && document.getElementById('phone')) {
        userForm.addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('phone');
            if (phoneInput.value.trim()) {
                const phoneValue = getPhoneWithPrefix('phone', '+91');
                if (!validatePhoneNumber('phone', '+91')) {
                    e.preventDefault();
                    phoneInput.focus();
                    alert('Please enter a valid 10-digit phone number.');
                    return false;
                }
                phoneInput.value = phoneValue;
            }
        });
    }
    
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearch');
    const roleFilter = document.getElementById('roleFilter');
    const clearAllFiltersBtn = document.getElementById('clearAllFiltersBtn');
    const usersTableBody = document.getElementById('usersTableBody');

    // Store original table content
    if (usersTableBody) {
        window.originalUsersTableContent = usersTableBody.innerHTML;
    }

    // Search input event with debouncing
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            if (clearBtn) clearBtn.style.display = searchTerm ? 'block' : 'none';
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                performFilter();
            }, 300);
        });
    }

    // Clear search button in input
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            this.style.display = 'none';
            const searchResults = document.getElementById('searchResults');
            if (searchResults) searchResults.style.display = 'none';
            performFilter();
        });
    }

    // Role filter change event
    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            performFilter();
        });
    }

    // Clear All Filters button
    if (clearAllFiltersBtn) {
        clearAllFiltersBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            if (roleFilter) roleFilter.value = '';
            const searchResults = document.getElementById('searchResults');
            if (searchResults) searchResults.style.display = 'none';
            performFilter();
        });
    }

    // Interactive Sorting A-Z
    const sortBtn = document.getElementById('sortUsersBtn');
    let sortAsc = true;
    if (sortBtn) {
        sortBtn.addEventListener('click', function() {
            const tableBody = document.getElementById('usersTableBody');
            if (tableBody) {
                const rows = Array.from(tableBody.querySelectorAll('tr.user-row'));
                if (rows.length > 0) {
                    sortAsc = !sortAsc;
                    const sortIcon = document.getElementById('sortIcon');
                    if (sortIcon) {
                        sortIcon.className = sortAsc ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
                    }
                    
                    rows.sort((a, b) => {
                        const nameA = (a.getAttribute('data-name') || '').toLowerCase();
                        const nameB = (b.getAttribute('data-name') || '').toLowerCase();
                        return sortAsc ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
                    });
                    
                    rows.forEach(row => tableBody.appendChild(row));
                }
            }
            const cardsContainer = document.getElementById('userMobileCardsList');
            if (cardsContainer) {
                const cards = Array.from(cardsContainer.querySelectorAll('.user-card-row'));
                if (cards.length > 0) {
                    cards.sort((a, b) => {
                        const nameA = (a.getAttribute('data-name') || '').toLowerCase();
                        const nameB = (b.getAttribute('data-name') || '').toLowerCase();
                        return sortAsc ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
                    });
                    cards.forEach(card => cardsContainer.appendChild(card));
                }
            }
        });
    }

    const tableBody = document.getElementById('usersTableBody');
    if (tableBody) {
        window.originalUsersTableContent = tableBody.innerHTML;
    }
    const cardsContainer = document.getElementById('userMobileCardsList');
    if (cardsContainer) {
        window.originalUsersCardsContent = cardsContainer.innerHTML;
    }
});

function bindDynamicEditButtons() {
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const userJson = this.getAttribute('data-user')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'")
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&');
                const user = JSON.parse(userJson);
                editUser(user);
            } catch (e) {
                console.error('Error parsing user data:', e);
                alert('Error loading user data');
            }
        });
    });
}

function performFilter() {
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const search = searchInput ? searchInput.value.trim() : '';
    const role = roleFilter ? roleFilter.value : '';
    const usersTableBody = document.getElementById('usersTableBody');
    const userMobileCards = document.getElementById('userMobileCardsList');

    if (!usersTableBody && !userMobileCards) return;

    // If no filters are applied, show original content
    if (!search && !role) {
        if (window.originalUsersTableContent && usersTableBody) {
            usersTableBody.innerHTML = window.originalUsersTableContent;
        }
        if (window.originalUsersCardsContent && userMobileCards) {
            userMobileCards.innerHTML = window.originalUsersCardsContent;
        }
        updateSearchResults(0, '');
        bindDynamicEditButtons();
        return;
    }

    // Show loading state
    if (usersTableBody) {
        usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2 text-primary"></i>Loading users...</td></tr>';
    }
    if (userMobileCards) {
        userMobileCards.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2 text-primary"></i>Loading users...</div>';
    }

    // Build query parameters
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (role) params.append('role', role);
    params.append('page', '1');
    params.append('limit', '20');

    // Make AJAX request
    fetch(`ajax/filter_users.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateUsersTable(data.users);
                updateSearchResults(data.pagination.total_users, search, data.pagination.total_pages);
            } else {
                console.error('Filter error:', data.error);
                if (usersTableBody) usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading users</td></tr>';
                if (userMobileCards) userMobileCards.innerHTML = '<div class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading users</div>';
            }
        })
        .catch(error => {
            console.error('Filter error:', error);
            if (usersTableBody) usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading users</td></tr>';
            if (userMobileCards) userMobileCards.innerHTML = '<div class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading users</div>';
        });
}

function updateUsersTable(users) {
    const usersTableBody = document.getElementById('usersTableBody');
    const userMobileCards = document.getElementById('userMobileCardsList');
    if (!usersTableBody && !userMobileCards) return;

    if (!users || users.length === 0) {
        if (usersTableBody) {
            usersTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0;">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1" style="font-size: 1rem;">No Data Found</h5>
                        <p class="text-muted small mb-0">No users match your search or role filter.</p>
                    </td>
                </tr>
            `;
        }
        if (userMobileCards) {
            userMobileCards.innerHTML = `
                <div class="text-center py-5" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px;">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1" style="font-size: 1rem;">No Data Found</h5>
                    <p class="text-muted small mb-0">No users match your search or role filter.</p>
                </div>
            `;
        }
        return;
    }

    let tableHTML = '';
    let cardsHTML = '';
    users.forEach((user, index) => {
        const cleanName = (user.full_name || user.username || 'User').trim();
        const initial = cleanName.charAt(0).toUpperCase() || 'U';
        const palette = userAvatarPalettes[index % userAvatarPalettes.length];
        const roleBadge = getJsUserRoleBadge(user.role);
        const isActive = (user.status || 'active').toLowerCase() === 'active';
        
        // Escape JSON for data-user attribute
        const userJson = JSON.stringify(user)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
            
        const safeNameForDelete = cleanName.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        
        const dateFormatted = (() => {
            if (!user.created_at) return '—';
            try {
                const date = new Date(user.created_at);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            } catch (e) {
                return user.created_at;
            }
        })();

        tableHTML += `
            <tr class="user-row" data-name="${escapeHtml(cleanName.toLowerCase())}">
                <td class="ps-3 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: ${palette.bg}; border: 1px solid ${palette.border}; color: ${palette.color}; font-size: 0.88rem;">
                            ${escapeHtml(initial)}
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 180px;">
                                ${escapeHtml(user.full_name || '')}
                            </div>
                            <div class="text-muted text-truncate" style="font-size: 0.72rem; max-width: 180px;">
                                @${escapeHtml(user.username || '')}
                            </div>
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    ${user.email ? `
                        <div class="text-dark" style="font-size: 0.82rem;">
                            <i class="fas fa-envelope text-muted me-1" style="font-size: 0.72rem;"></i>
                            ${escapeHtml(user.email)}
                        </div>
                    ` : '<span class="text-muted small">—</span>'}
                </td>
                <td class="py-3">
                    ${user.phone ? `
                        <div class="text-dark" style="font-size: 0.82rem;">
                            <i class="fas fa-phone text-muted me-1" style="font-size: 0.72rem;"></i>
                            ${escapeHtml(user.phone)}
                        </div>
                    ` : '<span class="text-muted small">—</span>'}
                </td>
                <td class="py-3">
                    <span class="badge fw-medium d-inline-flex align-items-center gap-1" style="background: ${roleBadge.bg}; color: ${roleBadge.color}; border: 1px solid ${roleBadge.border}; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; ${user.role === 'tailor' ? 'cursor: pointer;' : ''}" ${user.role === 'tailor' ? `onclick="viewTailorAssignments(${user.id})" title="Click to view assigned orders"` : ''}>
                        <i class="fas fa-${roleBadge.icon}"></i>
                        <span>${escapeHtml(user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User')}</span>
                        ${user.role === 'tailor' ? '<i class="fas fa-external-link-alt ms-1" style="font-size: 0.6rem; opacity: 0.75;"></i>' : ''}
                    </span>
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: ${isActive ? '#dcfce7' : '#f1f5f9'}; color: ${isActive ? '#15803d' : '#64748b'}; border: 1px solid ${isActive ? '#bbf7d0' : '#e2e8f0'}; font-size: 0.72rem;">
                        ${isActive ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td class="py-3 text-muted" style="font-size: 0.82rem;">
                    ${escapeHtml(dateFormatted)}
                </td>
                <td class="pe-3 py-3 text-end">
                    <div class="d-inline-flex align-items-center gap-1">
                        ${user.role === 'tailor' ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-view" 
                                onclick="viewTailorAssignments(${user.id})" 
                                title="View Tailor Orders & Garments">
                            <i class="fas fa-eye"></i>
                        </button>
                        ` : ''}
                        <button type="button" 
                                class="cat-action-btn cat-action-edit edit-user-btn" 
                                data-user='${userJson}' 
                                title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${user.id != currentLoggedInUserId ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-del" 
                                onclick="deleteUser(${user.id}, '${safeNameForDelete}')" 
                                title="Delete User">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;

        cardsHTML += `
            <div class="user-mobile-card user-card-row" data-name="${escapeHtml(cleanName.toLowerCase())}">
                <div class="d-flex align-items-start justify-content-between gap-2 pb-2 mb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 38px; height: 38px; border-radius: 12px; background: ${palette.bg}; border: 1px solid ${palette.border}; color: ${palette.color}; font-size: 0.92rem;">
                            ${escapeHtml(initial)}
                        </div>
                        <div>
                            <strong class="text-dark d-block" style="font-size: 0.92rem; line-height: 1.2;">${escapeHtml(user.full_name || '')}</strong>
                            <span class="text-muted small" style="font-size: 0.75rem;">@${escapeHtml(user.username || '')}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <span class="badge fw-medium d-inline-flex align-items-center gap-1" style="background: ${roleBadge.bg}; color: ${roleBadge.color}; border: 1px solid ${roleBadge.border}; font-size: 0.72rem; padding: 3px 6px; border-radius: 6px; ${user.role === 'tailor' ? 'cursor: pointer;' : ''}" ${user.role === 'tailor' ? `onclick="viewTailorAssignments(${user.id})" title="Click to view assigned orders"` : ''}>
                            <i class="fas fa-${roleBadge.icon}"></i>
                            <span>${escapeHtml(user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User')}</span>
                            ${user.role === 'tailor' ? '<i class="fas fa-external-link-alt ms-1" style="font-size: 0.55rem; opacity: 0.75;"></i>' : ''}
                        </span>
                        <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: ${isActive ? '#dcfce7' : '#f1f5f9'}; color: ${isActive ? '#15803d' : '#64748b'}; border: 1px solid ${isActive ? '#bbf7d0' : '#e2e8f0'}; font-size: 0.7rem;">
                            ${isActive ? 'Active' : 'Inactive'}
                        </span>
                    </div>
                </div>

                <div class="p-2 mb-2 rounded-3" style="background: #f8fafc; font-size: 0.82rem;">
                    ${user.email ? `
                        <div class="text-dark mb-1 text-truncate">
                            <i class="fas fa-envelope me-2 text-muted" style="width: 14px;"></i>
                            <a href="mailto:${escapeHtml(user.email)}" class="text-dark text-decoration-none">
                                ${escapeHtml(user.email)}
                            </a>
                        </div>
                    ` : ''}
                    ${user.phone ? `
                        <div class="text-dark mb-1">
                            <i class="fas fa-phone me-2 text-muted" style="width: 14px;"></i>
                            <a href="tel:${escapeHtml(user.phone)}" class="text-dark text-decoration-none fw-medium">
                                ${escapeHtml(user.phone)}
                            </a>
                        </div>
                    ` : ''}
                    <div class="text-muted" style="font-size: 0.78rem;">
                        <i class="far fa-calendar-alt me-2 text-muted" style="width: 14px;"></i>Joined: ${escapeHtml(dateFormatted)}
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-1 pt-1">
                    ${user.role === 'tailor' ? `
                    <button type="button" 
                            class="cat-action-btn cat-action-view" 
                            onclick="viewTailorAssignments(${user.id})" 
                            title="View Tailor Orders & Garments">
                        <i class="fas fa-eye"></i>
                    </button>
                    ` : ''}
                    <button type="button" 
                            class="cat-action-btn cat-action-edit edit-user-btn" 
                            data-user='${userJson}' 
                            title="Edit User">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${user.id != currentLoggedInUserId ? `
                    <button type="button" 
                            class="cat-action-btn cat-action-del" 
                            onclick="deleteUser(${user.id}, '${safeNameForDelete}')" 
                            title="Delete User">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
    });

    if (usersTableBody) usersTableBody.innerHTML = tableHTML;
    if (userMobileCards) userMobileCards.innerHTML = cardsHTML;
    bindDynamicEditButtons();
}

function updateSearchResults(totalUsers, searchTerm, totalPages = null) {
    const searchResults = document.getElementById('searchResults');
    const searchCount = document.getElementById('searchCount');
    
    if (!searchResults || !searchCount) return;
    
    if (searchTerm && totalUsers > 0) {
        searchCount.textContent = totalUsers;
        searchResults.style.display = 'block';
    } else {
        searchResults.style.display = 'none';
    }
    
    const paginationContainer = document.getElementById('paginationContainer');
    if (paginationContainer) {
        if (totalPages !== null && totalPages <= 1) {
            paginationContainer.style.display = 'none';
        } else if (totalPages !== null) {
            paginationContainer.style.display = 'block';
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>