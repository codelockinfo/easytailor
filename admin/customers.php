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
        <div class="row g-3 align-items-end">
            <div class="col-md-9">
                <label for="searchInput" class="form-label">Search Customers</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           id="searchInput"
                           class="form-control" 
                           placeholder="Search customers by name, code, email, or phone..."
                           autocomplete="off">
                    <button type="button" id="clearSearch" class="btn btn-outline-secondary" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="searchResults" class="mt-3" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="searchCount">0</span> customers found
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#customerModal">
                    <i class="fas fa-plus me-2"></i>Add Customer
                </button>
            </div>
        </div>
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
            <button type="button" class="btn btn-sm btn-light" onclick="exportCustomers()">
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-danger text-white border-0">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="deleteModalLabel">
                            <strong>Confirm Deletion</strong>
                        </h5>
                        <small class="opacity-75">This action cannot be undone</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-user-times text-danger" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                    <h6 class="text-dark mb-3">Are you sure you want to delete this customer?</h6>
                </div>
                
                <div class="alert alert-warning border-0 shadow-sm">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle text-warning me-3 mt-1"></i>
                        <div>
                            <strong class="text-dark">Customer Details:</strong>
                            <p class="mb-0 mt-2" id="deleteCustomerName"></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-danger border-0 shadow-sm">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-circle text-danger me-3 mt-1"></i>
                        <div>
                            <strong>Warning:</strong>
                            <p class="mb-0 mt-1">This action will permanently delete the customer record and all associated data cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer border-0 p-4 bg-light">
                <div class="w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="customer_id" id="deleteCustomerId">
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-trash me-2"></i>Yes, Delete
                        </button>
                    </form>
                </div>
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
    // Show initial progress message
    showToast('🔄 Preparing your customer export... Please wait.', 'info');
    
    // Disable the export button temporarily
    const exportBtn = document.querySelector('button[onclick="exportCustomers()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
    
    // Create FormData for AJAX request
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    // Show progress message
    setTimeout(() => {
        showToast('📊 Generating Excel file with all customer details...', 'info');
    }, 500);
    
    // Make AJAX request
    fetch('ajax/export_customers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Export failed');
        }
        return response.blob();
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = 'customers_export_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.xlsx';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        // Show success message
        showToast('✅ Export completed! Your Excel file has been downloaded.', 'success');
        
        // Re-enable the export button
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
    })
    .catch(error => {
        console.error('Export error:', error);
        showToast('❌ Export failed. Please try again.', 'error');
        
        // Re-enable the export button
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
    });
}


// Reset modal when closed
document.getElementById('customerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('customerModalTitle').textContent = 'Add Customer';
    document.getElementById('customerAction').value = 'create';
    document.getElementById('customerId').value = '';
    document.getElementById('customerForm').reset();
});

// AJAX Customer Search
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');
const searchCount = document.getElementById('searchCount');
const clearSearch = document.getElementById('clearSearch');
const customersTable = document.querySelector('.table tbody');

// Store original table content
const originalTableContent = customersTable.innerHTML;

searchInput.addEventListener('input', function() {
    const searchTerm = this.value.trim();
    
    // Clear previous timeout
    clearTimeout(searchTimeout);
    
    if (searchTerm.length < 2) {
        // Show original table
        customersTable.innerHTML = originalTableContent;
        searchResults.style.display = 'none';
        clearSearch.style.display = 'none';
        return;
    }
    
    // Show loading state
    searchResults.style.display = 'block';
    searchCount.textContent = 'Searching...';
    clearSearch.style.display = 'inline-block';
    
    // Debounce search
    searchTimeout = setTimeout(() => {
        performSearch(searchTerm);
    }, 300);
});

clearSearch.addEventListener('click', function() {
    searchInput.value = '';
    customersTable.innerHTML = originalTableContent;
    searchResults.style.display = 'none';
    clearSearch.style.display = 'none';
});

function performSearch(searchTerm) {
    fetch(`ajax/search_customers.php?search=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.customers);
                searchCount.textContent = data.count;
            } else {
                console.error('Search error:', data.error);
                searchCount.textContent = 'Search failed';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            searchCount.textContent = 'Search failed';
        });
}

function displaySearchResults(customers) {
    if (customers.length === 0) {
        customersTable.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No customers found</h5>
                    <p class="text-muted">Try adjusting your search terms</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let tableHTML = '';
    customers.forEach(customer => {
        // Format date of birth if available
        const dobDisplay = customer.date_of_birth ? 
            `<br><small class="text-muted"><i class="fas fa-birthday-cake me-1"></i>${customer.date_of_birth}</small>` : '';
        
        // Format contact info
        const phoneDisplay = customer.phone ? 
            `<div><i class="fas fa-phone me-1"></i>${customer.phone}</div>` : '';
        const emailDisplay = customer.email ? 
            `<div><i class="fas fa-envelope me-1"></i>${customer.email}</div>` : '';
        
        // Format location
        const locationDisplay = customer.city || customer.state ? 
            `${customer.city || ''}${customer.city && customer.state ? ', ' : ''}${customer.state || ''}` : '';
        
        tableHTML += `
            <tr>
                <td>
                    <span class="badge bg-primary">${customer.customer_code}</span>
                </td>
                <td>
                    <div>
                        <strong>${customer.name}</strong>
                        ${dobDisplay}
                    </div>
                </td>
                <td>
                    <div>
                        ${phoneDisplay}
                        ${emailDisplay}
                    </div>
                </td>
                <td>
                    ${locationDisplay ? `<div>${locationDisplay}</div>` : ''}
                </td>
                <td>
                    <span class="badge bg-info">${customer.total_orders || 0}</span>
                </td>
                <td>
                    <span class="badge bg-success">${customer.status || 'active'}</span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" 
                                class="btn btn-outline-primary" 
                                onclick="editCustomer(${JSON.stringify(customer).replace(/"/g, '&quot;')})"
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="customer-details.php?id=${customer.id}" 
                           class="btn btn-outline-info" 
                           title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button type="button" 
                                class="btn btn-outline-danger" 
                                onclick="deleteCustomer(${customer.id}, '${customer.name.replace(/'/g, "\\'")}')"
                                title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    customersTable.innerHTML = tableHTML;
}
</script>

<?php require_once 'includes/footer.php'; ?>

