<?php
/**
 * Contacts Page
 * Tailoring Management System
 */

$page_title = 'Contact Management';
require_once 'includes/header.php';

require_once 'models/Contact.php';

$contactModel = new Contact();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'name' => sanitize_input($_POST['name']),
                    'company' => sanitize_input($_POST['company']),
                    'email' => sanitize_input($_POST['email']),
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'category' => sanitize_input($_POST['category']),
                    'notes' => sanitize_input($_POST['notes'])
                ];
                
                $contactId = $contactModel->create($data);
                if ($contactId) {
                    $message = 'Contact added successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add contact';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $contactId = (int)$_POST['contact_id'];
                $data = [
                    'name' => sanitize_input($_POST['name']),
                    'company' => sanitize_input($_POST['company']),
                    'email' => sanitize_input($_POST['email']),
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'category' => sanitize_input($_POST['category']),
                    'notes' => sanitize_input($_POST['notes'])
                ];
                
                if ($contactModel->update($contactId, $data)) {
                    $message = 'Contact updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update contact';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $contactId = (int)$_POST['contact_id'];
                if ($contactModel->delete($contactId)) {
                    $message = 'Contact deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete contact';
                    $messageType = 'error';
                }
                break;
        }
    } else {
        $message = 'Invalid request';
        $messageType = 'error';
    }
}

// Get contacts
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$conditions = [];
if (!empty($category_filter)) {
    $conditions['category'] = $category_filter;
}

$contacts = $contactModel->findAll($conditions, 'name ASC', $limit);
$totalContacts = $contactModel->count($conditions);
$totalPages = ceil($totalContacts / $limit);

// Get contact for editing
$editContact = null;
if (isset($_GET['edit'])) {
    $editContact = $contactModel->find((int)$_GET['edit']);
}

// Get categories
$categories = ['Supplier', 'Partner', 'Vendor', 'Service Provider', 'Other'];
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Contact Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                <i class="fas fa-plus me-2"></i>Add Contact
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
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           placeholder="Search contacts..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        <a href="contacts.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Contacts Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-address-book me-2"></i>
            Contacts (<?php echo number_format($totalContacts); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($contacts)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Contact Info</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($contact['name']); ?></strong>
                                    <?php if (!empty($contact['notes'])): ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($contact['notes'], 0, 50)); ?><?php echo strlen($contact['notes']) > 50 ? '...' : ''; ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($contact['company'])): ?>
                                    <?php echo htmlspecialchars($contact['company']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <?php if (!empty($contact['phone'])): ?>
                                        <div>
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($contact['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($contact['email'])): ?>
                                        <div>
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($contact['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($contact['category'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($contact['category']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $contact['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($contact['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="editContact(<?php echo htmlspecialchars(json_encode($contact)); ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteContact(<?php echo $contact['id']; ?>, '<?php echo htmlspecialchars($contact['name']); ?>')"
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
                <nav aria-label="Contact pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-address-book fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No contacts found</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        No contacts match your search criteria.
                    <?php else: ?>
                        Get started by adding your first contact.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($category_filter)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                        <i class="fas fa-plus me-2"></i>Add Contact
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="contactForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalTitle">Add Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="contactAction" value="create">
                    <input type="hidden" name="contact_id" id="contactId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="company" class="form-label">Company</label>
                            <input type="text" class="form-control" id="company" name="company">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                        <i class="fas fa-save me-2"></i>Save Contact
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
                <p>Are you sure you want to delete contact <strong id="deleteContactName"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="contact_id" id="deleteContactId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editContact(contact) {
    document.getElementById('contactModalTitle').textContent = 'Edit Contact';
    document.getElementById('contactAction').value = 'update';
    document.getElementById('contactId').value = contact.id;
    
    // Populate form fields
    document.getElementById('name').value = contact.name || '';
    document.getElementById('company').value = contact.company || '';
    document.getElementById('email').value = contact.email || '';
    document.getElementById('phone').value = contact.phone || '';
    document.getElementById('address').value = contact.address || '';
    document.getElementById('category').value = contact.category || '';
    document.getElementById('notes').value = contact.notes || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('contactModal')).show();
}

function deleteContact(contactId, contactName) {
    document.getElementById('deleteContactId').value = contactId;
    document.getElementById('deleteContactName').textContent = contactName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Reset modal when closed
document.getElementById('contactModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('contactModalTitle').textContent = 'Add Contact';
    document.getElementById('contactAction').value = 'create';
    document.getElementById('contactId').value = '';
    document.getElementById('contactForm').reset();
});
</script>

<?php require_once 'includes/footer.php'; ?>

