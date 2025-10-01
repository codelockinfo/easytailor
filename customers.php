<?php
/**
 * Customers Page
 * Tailoring Management System
 */

$page_title = 'Customer Management';
require_once 'includes/header.php';

require_once 'models/Customer.php';

$customerModel = new Customer();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'first_name' => sanitize_input($_POST['first_name']),
                    'last_name' => sanitize_input($_POST['last_name']),
                    'email' => sanitize_input($_POST['email']),
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'city' => sanitize_input($_POST['city']),
                    'state' => sanitize_input($_POST['state']),
                    'postal_code' => sanitize_input($_POST['postal_code']),
                    'date_of_birth' => $_POST['date_of_birth'] ?: null,
                    'notes' => sanitize_input($_POST['notes'])
                ];
                
                // Validate email uniqueness
                if (!empty($data['email']) && $customerModel->emailExists($data['email'])) {
                    $message = 'Email already exists';
                    $messageType = 'error';
                } else {
                    $customerId = $customerModel->createCustomer($data);
                    if ($customerId) {
                        $message = 'Customer created successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to create customer';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update':
                $customerId = (int)$_POST['customer_id'];
                $data = [
                    'first_name' => sanitize_input($_POST['first_name']),
                    'last_name' => sanitize_input($_POST['last_name']),
                    'email' => sanitize_input($_POST['email']),
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'city' => sanitize_input($_POST['city']),
                    'state' => sanitize_input($_POST['state']),
                    'postal_code' => sanitize_input($_POST['postal_code']),
                    'date_of_birth' => $_POST['date_of_birth'] ?: null,
                    'notes' => sanitize_input($_POST['notes'])
                ];
                
                // Validate email uniqueness
                if (!empty($data['email']) && $customerModel->emailExists($data['email'], $customerId)) {
                    $message = 'Email already exists';
                    $messageType = 'error';
                } else {
                    if ($customerModel->update($customerId, $data)) {
                        $message = 'Customer updated successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update customer';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete':
                $customerId = (int)$_POST['customer_id'];
                if ($customerModel->delete($customerId)) {
                    $message = 'Customer deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete customer';
                    $messageType = 'error';
                }
                break;
        }
    } else {
        $message = 'Invalid request';
        $messageType = 'error';
    }
}

// Get customers
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

if (!empty($search)) {
    $customers = $customerModel->searchCustomers($search, $limit);
    $totalCustomers = count($customers);
} else {
    $customers = $customerModel->getCustomersWithOrderCount();
    $totalCustomers = $customerModel->count(['status' => 'active']);
    $customers = array_slice($customers, $offset, $limit);
}

$totalPages = ceil($totalCustomers / $limit);

// Get customer for editing
$editCustomer = null;
if (isset($_GET['edit'])) {
    $editCustomer = $customerModel->find((int)$_GET['edit']);
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Customer Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
                <i class="fas fa-plus me-2"></i>Add Customer
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           placeholder="Search customers by name, code, email, or phone..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="customers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-users me-2"></i>
            Customers (<?php echo number_format($totalCustomers); ?>)
        </h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportCustomers()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($customers)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Orders</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($customer['customer_code']); ?></span>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                    <?php if (!empty($customer['date_of_birth'])): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-birthday-cake me-1"></i>
                                            <?php echo format_date($customer['date_of_birth']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <?php if (!empty($customer['phone'])): ?>
                                        <div>
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($customer['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($customer['email'])): ?>
                                        <div>
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($customer['city']) || !empty($customer['state'])): ?>
                                    <div>
                                        <?php if (!empty($customer['city'])): ?>
                                            <?php echo htmlspecialchars($customer['city']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($customer['state'])): ?>
                                            <?php if (!empty($customer['city'])): ?>, <?php endif; ?>
                                            <?php echo htmlspecialchars($customer['state']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $customer['order_count'] ?? 0; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($customer['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="customer-details.php?id=<?php echo $customer['id']; ?>" 
                                       class="btn btn-outline-info" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>')"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Customer pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No customers found</h5>
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        No customers match your search criteria.
                    <?php else: ?>
                        Get started by adding your first customer.
                    <?php endif; ?>
                </p>
                <?php if (empty($search)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
                        <i class="fas fa-plus me-2"></i>Add Customer
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="customerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="customerAction" value="create">
                    <input type="hidden" name="customer_id" id="customerId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" placeholder="e.g., Mumbai">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="state" class="form-label">State</label>
                            <input type="text" class="form-control" id="state" name="state" placeholder="e.g., Maharashtra">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="postal_code" class="form-label">PIN Code</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" placeholder="e.g., 400001" maxlength="6" pattern="[0-9]{6}">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete customer <strong id="deleteCustomerName"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="customer_id" id="deleteCustomerId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editCustomer(customer) {
    document.getElementById('customerModalTitle').textContent = 'Edit Customer';
    document.getElementById('customerAction').value = 'update';
    document.getElementById('customerId').value = customer.id;
    
    // Populate form fields
    document.getElementById('first_name').value = customer.first_name || '';
    document.getElementById('last_name').value = customer.last_name || '';
    document.getElementById('email').value = customer.email || '';
    document.getElementById('phone').value = customer.phone || '';
    document.getElementById('address').value = customer.address || '';
    document.getElementById('city').value = customer.city || '';
    document.getElementById('state').value = customer.state || '';
    document.getElementById('postal_code').value = customer.postal_code || '';
    document.getElementById('date_of_birth').value = customer.date_of_birth || '';
    document.getElementById('notes').value = customer.notes || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function deleteCustomer(customerId, customerName) {
    document.getElementById('deleteCustomerId').value = customerId;
    document.getElementById('deleteCustomerName').textContent = customerName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function exportCustomers() {
    // Implement export functionality
    showToast('Export functionality will be implemented', 'info');
}

// Reset modal when closed
document.getElementById('customerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('customerModalTitle').textContent = 'Add Customer';
    document.getElementById('customerAction').value = 'create';
    document.getElementById('customerId').value = '';
    document.getElementById('customerForm').reset();
});
</script>

<?php require_once 'includes/footer.php'; ?>

