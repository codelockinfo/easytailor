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
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">ADMINS & STAFF</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="statAdminsStaff"><?php echo number_format($stats['admins'] + $stats['staff'] + $stats['cashiers']); ?></div>
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
            <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
            <option value="cashier" <?php echo $role_filter === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
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
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap w-100 w-md-auto px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#userModal" <?php echo !$userLimitCheck['allowed'] ? 'disabled title="User limit reached"' : ''; ?>>
            <i class="fas fa-plus"></i>
            <span>Add User</span>
        </button>
    </div>
</div>

<div id="searchResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="searchCount">0</strong> users found</span>
    </div>
</div>

<!-- 4. Users Table -->
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
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
                                <span class="badge fw-medium d-inline-flex align-items-center gap-1" style="background: <?php echo $roleBadge['bg']; ?>; color: <?php echo $roleBadge['color']; ?>; border: 1px solid <?php echo $roleBadge['border']; ?>; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px;">
                                    <i class="fas fa-<?php echo $roleBadge['icon']; ?>"></i>
                                    <span><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
                                </span>
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
                                <option value="staff">Staff - General operations</option>
                                <option value="cashier">Cashier - Payments & invoices</option>
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
                        <input type="password" class="form-control" id="password" name="password" style="border-radius: 10px;">
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

<script>
const currentLoggedInUserId = <?php echo (int)get_user_id(); ?>;

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

// Reset modal when closed
document.getElementById('userModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('userAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHelp').textContent = 'Minimum 6 characters';
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
            if (!tableBody) return;
            const rows = Array.from(tableBody.querySelectorAll('tr.user-row'));
            if (rows.length === 0) return;
            
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
        });
    }
});

function bindDynamicEditButtons() {
    const tableBody = document.getElementById('usersTableBody');
    if (!tableBody) return;
    tableBody.querySelectorAll('.edit-user-btn').forEach(btn => {
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

    if (!usersTableBody) return;

    // If no filters are applied, show original content
    if (!search && !role) {
        if (window.originalUsersTableContent) {
            usersTableBody.innerHTML = window.originalUsersTableContent;
            updateSearchResults(0, '');
        }
        return;
    }

    // Show loading state
    usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2 text-primary"></i>Loading users...</td></tr>';

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
                usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading users</td></tr>';
            }
        })
        .catch(error => {
            console.error('Filter error:', error);
            usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading users</td></tr>';
        });
}

function updateUsersTable(users) {
    const usersTableBody = document.getElementById('usersTableBody');
    if (!usersTableBody) return;

    if (!users || users.length === 0) {
        usersTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                        <i class="fas fa-users fa-lg"></i>
                    </div>
                    <div class="fw-semibold text-dark">No users found</div>
                    <small class="text-muted">Try adjusting your search or role filter.</small>
                </td>
            </tr>
        `;
        return;
    }

    let tableHTML = '';
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
                    <span class="badge fw-medium d-inline-flex align-items-center gap-1" style="background: ${roleBadge.bg}; color: ${roleBadge.color}; border: 1px solid ${roleBadge.border}; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px;">
                        <i class="fas fa-${roleBadge.icon}"></i>
                        <span>${escapeHtml(user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User')}</span>
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
    });

    usersTableBody.innerHTML = tableHTML;
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