<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="favicon(2).png">

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

// NOW include header (after all redirects are done)
$page_title = 'User Management';
require_once 'includes/header.php';

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
// Only show active users
$conditions['status'] = 'active';

// Note: findAll automatically filters by company_id for non-admin users
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
?>


<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($userLimitCheck['remaining']) && $userLimitCheck['remaining'] <= 1 && $userLimitCheck['remaining'] > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>User Limit Warning:</strong> You have <?php echo $userLimitCheck['remaining']; ?> user slot(s) remaining in your <?php echo ucfirst($currentPlan); ?> plan. 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more users.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (!$userLimitCheck['allowed']): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-ban me-2"></i>
        <strong>User Limit Reached:</strong> <?php echo $userLimitCheck['message']; ?> 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more users.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- User Statistics -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['admins']); ?></div>
                    <div class="stat-label">Admins</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['tailors']); ?></div>
                    <div class="stat-label">Tailors</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-cut"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['staff']); ?></div>
                    <div class="stat-label">Staff</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg, #6610f2 0%, #520dc2 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['cashiers']); ?></div>
                    <div class="stat-label">Cashiers</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="searchInput"
                           placeholder="Search by name, username, or email...">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="roleFilter">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="tailor">Tailor</option>
                    <option value="staff">Staff</option>
                    <option value="cashier">Cashier</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="d-grid gap-2 d-md-flex">
                    <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-users me-2"></i>
            Users (<?php echo number_format($totalUsers); ?>)
        </h5>
        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="fas fa-plus me-1"></i>Add User
        </button>
    </div>
    <div class="card-body">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $roleColors = [
                                    'admin' => 'danger',
                                    'tailor' => 'info',
                                    'staff' => 'warning',
                                    'cashier' => 'primary'
                                ];
                                $roleIcons = [
                                    'admin' => 'user-shield',
                                    'tailor' => 'cut',
                                    'staff' => 'user-tie',
                                    'cashier' => 'cash-register'
                                ];
                                $color = $roleColors[$user['role']] ?? 'secondary';
                                $icon = $roleIcons[$user['role']] ?? 'user';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($user['created_at']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                            title="Edit" style="border: 1px solid #667eea;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != get_user_id()): ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')"
                                            title="Delete" style="border: 1px solid #667eea;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="User pagination">
                    <ul class="pagination justify-content-center">
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
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No users found</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($role_filter)): ?>
                        No users match your search criteria.
                    <?php else: ?>
                        Get started by adding your first user.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($role_filter)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                        <i class="fas fa-plus me-2"></i>Add User
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="userAction" value="create">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text">+91</span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone"
                                       placeholder="10-digit mobile number"
                                       pattern="[0-9]{10}"
                                       maxlength="10">
                            </div>
                            <small class="text-muted">Enter 10-digit mobile number (digits only)</small>
                            <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin - Full access</option>
                                <option value="tailor">Tailor - Can be assigned to orders</option>
                                <option value="staff">Staff - General operations</option>
                                <option value="cashier">Cashier - Payments & invoices</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span id="passwordRequired">*</span></label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="text-muted" id="passwordHelp">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
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
            
            <!-- Modal Body -->
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
            
            <!-- Modal Footer -->
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary me-3" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-trash me-2"></i>Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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

// AJAX Filtering Functions
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
                // Set the phone value with prefix for submission
                phoneInput.value = phoneValue;
            }
        });
    }
    
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const usersTableBody = document.getElementById('usersTableBody');

    if (!searchInput || !roleFilter || !usersTableBody) {
        console.error('Required DOM elements not found for user filtering');
        return;
    }

    // Store original table content
    const originalTableContent = usersTableBody.innerHTML;

    // Search input event with debouncing
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performFilter();
        }, 300);
    });

    // Role filter change event
    roleFilter.addEventListener('change', function() {
        performFilter();
    });

    function performFilter() {
        const search = searchInput.value.trim();
        const role = roleFilter.value;

        // If no filters are applied, show original content
        if (!search && !role) {
            usersTableBody.innerHTML = originalTableContent;
            return;
        }

        // Show loading state
        usersTableBody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>';

        // Build query parameters
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (role) params.append('role', role);
        params.append('page', '1');
        params.append('limit', '20');

        // Make AJAX request
        fetch(`ajax/filter_users.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUsersTable(data.users);
                } else {
                    console.error('Filter error:', data.error);
                    showToast('Error filtering users: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Filter error:', error);
                showToast('Error filtering users', 'error');
            });
    }

    function updateUsersTable(users) {
        if (users.length === 0) {
            usersTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No users found</h5>
                        <p class="text-muted">Try adjusting your search criteria.</p>
                    </td>
                </tr>
            `;
            return;
        }

        let tableHTML = '';
        users.forEach(user => {
            const roleColors = {
                'admin': 'danger',
                'tailor': 'info',
                'staff': 'warning',
                'cashier': 'primary'
            };
            const roleIcons = {
                'admin': 'user-shield',
                'tailor': 'cut',
                'staff': 'user-tie',
                'cashier': 'cash-register'
            };
            const color = roleColors[user.role] || 'secondary';
            const icon = roleIcons[user.role] || 'user';

            tableHTML += `
                <tr>
                    <td><strong>${user.full_name}</strong></td>
                    <td>${user.username}</td>
                    <td>${user.email}</td>
                    <td>${user.phone || '-'}</td>
                    <td>
                        <span class="badge bg-${color}">
                            <i class="fas fa-${icon} me-1"></i>
                            ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">
                            ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                        </span>
                    </td>
                    <td>${new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUser(${JSON.stringify(user).replace(/"/g, '&quot;')})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id}, '${user.full_name.replace(/'/g, "\\'")}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        usersTableBody.innerHTML = tableHTML;
    }

    function clearFilters() {
        searchInput.value = '';
        roleFilter.value = '';
        usersTableBody.innerHTML = originalTableContent;
    }

    // Make clearFilters globally available
    window.clearFilters = clearFilters;
});

function showToast(message, type = 'info') {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
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
</script>

<?php require_once 'includes/footer.php'; ?>

