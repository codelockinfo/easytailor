<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="favicon(2).png">

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
                
                $contactId = $contactModel->createContact($data);
                if ($contactId) {
                    // Track generate lead event
                    require_once '../helpers/GA4Helper.php';
                    $_SESSION['ga4_event'] = GA4Helper::trackGenerateLead('contact', null);
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
        <div class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           id="searchInput"
                           class="form-control" 
                           placeholder="Search contacts..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="button" id="clearSearch" class="btn btn-outline-secondary" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="searchResults" class="mt-3" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="searchCount">0</span> contacts found
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="categoryFilter" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Contacts Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-address-book me-2"></i>
            Contacts (<?php echo number_format($totalContacts); ?>)
        </h5>
        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#contactModal">
            <i class="fas fa-plus me-1"></i>Add Contact
        </button>
    </div>
    <div class="card-body">
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
                <tbody id="contactsTableBody">
                    <?php if (!empty($contacts)): ?>
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
                                            title="Edit" style="border: 1px solid #667eea;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteContact(<?php echo $contact['id']; ?>, '<?php echo htmlspecialchars($contact['name']); ?>')"
                                            title="Delete" style="border: 1px solid #667eea;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-address-book fa-2x mb-2"></i>
                                <div>
                                    <?php if (!empty($search) || !empty($category_filter)): ?>
                                        No contacts match your search criteria.
                                    <?php else: ?>
                                        No contacts found. Get started by adding your first contact.
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($search) && empty($category_filter)): ?>
                                    <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#contactModal">
                                        <i class="fas fa-plus me-1"></i>Add Contact
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
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
                    <i class="fas fa-user-times fa-4x text-danger mb-3"></i>
                </div>
                <h6 class="mb-3">Are you sure you want to delete this contact?</h6>
                <div class="alert alert-light border">
                    <strong id="deleteContactName" class="text-primary"></strong>
                </div>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    All associated data will be permanently removed from the system.
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
                    <input type="hidden" name="contact_id" id="deleteContactId">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-trash me-2"></i>Delete Contact
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
    // Handle phone number - remove +91 prefix if present, keep only 10 digits
    let phoneNumber = contact.phone || '';
    if (phoneNumber.startsWith('+91')) {
        phoneNumber = phoneNumber.replace('+91', '').trim();
    }
    phoneNumber = phoneNumber.replace(/[^0-9]/g, '').slice(0, 10);
    document.getElementById('phone').value = phoneNumber;
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

// AJAX Filtering functionality
let searchTimeout;

// Initialize filtering when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Setup phone validation
    setupPhoneValidation('phone', '+91');
    
    // Update phone value with prefix before form submission
    const contactForm = document.getElementById('contactForm');
    if (contactForm && document.getElementById('phone')) {
        contactForm.addEventListener('submit', function(e) {
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
    
    // Check if required elements exist
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    
    if (!searchInput || !clearBtn || !categoryFilter) {
        console.error('Required filter elements not found');
        return;
    }
    
    // Search functionality
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        const searchResults = document.getElementById('searchResults');
        
        // Show/hide clear button
        clearBtn.style.display = searchTerm ? 'block' : 'none';
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            filterContacts();
        }, 300);
    });

    // Clear search
    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        this.style.display = 'none';
        document.getElementById('searchResults').style.display = 'none';
        filterContacts();
    });

    // Category filter change
    categoryFilter.addEventListener('change', function() {
        filterContacts();
    });
    
    // Set initial search results if there's a search term
    const searchTerm = searchInput.value.trim();
    if (searchTerm) {
        const searchResults = document.getElementById('searchResults');
        const searchCount = document.getElementById('searchCount');
        if (searchResults && searchCount) {
            searchCount.textContent = '<?php echo count($contacts); ?>';
            searchResults.style.display = 'block';
        }
    }
    
    // Store original table content to prevent duplicates
    const tableBody = document.getElementById('contactsTableBody');
    if (tableBody) {
        window.originalContactsTableContent = tableBody.innerHTML;
        console.log('Original table content stored, rows:', tableBody.querySelectorAll('tr').length);
    }
});

function filterContacts() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    const category = document.getElementById('categoryFilter').value;
    const searchResults = document.getElementById('searchResults');
    const searchCount = document.getElementById('searchCount');
    
    // Show loading state - check if element exists
    const tableBody = document.getElementById('contactsTableBody');
    if (!tableBody) {
        console.error('Table body element not found');
        return;
    }
    
    // If no filters are applied, restore original content
    if (!searchTerm && !category) {
        console.log('No filters applied, restoring original table content');
        if (window.originalContactsTableContent) {
            tableBody.innerHTML = window.originalContactsTableContent;
            updateSearchResults(0, '');
        }
        return;
    }
    
    tableBody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>';
    
    // Build query parameters
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (category) params.append('category', category);
    
    console.log('Filtering contacts with params:', params.toString());
    
    // Make AJAX request
    fetch(`ajax/filter_contacts.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            console.log('Filter response received:', data);
            if (data.success) {
                updateContactsTable(data.contacts);
                updateSearchResults(data.pagination.total_contacts, searchTerm);
            } else {
                console.error('Filter error:', data.error);
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading contacts</td></tr>';
                }
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading contacts</td></tr>';
            }
        });
}

function updateContactsTable(contacts) {
    const tableBody = document.getElementById('contactsTableBody');
    
    if (!tableBody) {
        console.error('Table body element not found in updateContactsTable');
        return;
    }
    
    if (contacts.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No contacts found</td></tr>';
        return;
    }
    
    let html = '';
    contacts.forEach(contact => {
        // Escape contact object for use in HTML onclick attribute
        // Use HTML entity encoding for the JSON string to avoid quote issues
        const contactJson = JSON.stringify(contact)
            .replace(/&/g, '&amp;')   // Escape ampersands first
            .replace(/</g, '&lt;')    // Escape less than
            .replace(/>/g, '&gt;')    // Escape greater than
            .replace(/"/g, '&quot;')  // Escape double quotes
            .replace(/'/g, '&#39;');  // Escape single quotes
        
        html += `
            <tr>
                <td>
                    <div>
                        <strong>${contact.name}</strong>
                        ${contact.notes ? `<br><small class="text-muted">${contact.notes.length > 50 ? contact.notes.substring(0, 50) + '...' : contact.notes}</small>` : ''}
                    </div>
                </td>
                <td>
                    ${contact.company ? contact.company : '<span class="text-muted">-</span>'}
                </td>
                <td>
                    <div>
                        ${contact.email ? `<i class="fas fa-envelope me-1"></i>${contact.email}<br>` : ''}
                        ${contact.phone ? `<i class="fas fa-phone me-1"></i>${contact.phone}` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge bg-info">${contact.category}</span>
                </td>
                <td>
                    
                    <span class="badge bg-${contact.status === 'active' ? 'success' : 'secondary'}">${contact.status ? contact.status.charAt(0).toUpperCase() + contact.status.slice(1).toLowerCase() : 'Active'}</span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary edit-contact-btn" data-contact='${contactJson}' title="Edit" style="border: 1px solid #667eea;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="deleteContact(${contact.id}, '${contact.name.replace(/'/g, "\\'")}')" title="Delete" style="border: 1px solid #667eea;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    
    // Attach event listeners to edit buttons
    tableBody.querySelectorAll('.edit-contact-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                // Decode HTML entities back to JSON string, then parse
                const contactJson = this.getAttribute('data-contact')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'")
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&');
                const contact = JSON.parse(contactJson);
                editContact(contact);
            } catch (e) {
                console.error('Error parsing contact data:', e);
                alert('Error loading contact data');
            }
        });
    });
}

function updateSearchResults(totalContacts, searchTerm) {
    const searchResults = document.getElementById('searchResults');
    const searchCount = document.getElementById('searchCount');
    
    if (!searchResults || !searchCount) {
        console.error('Search results elements not found');
        return;
    }
    
    if (searchTerm && totalContacts > 0) {
        searchCount.textContent = totalContacts;
        searchResults.style.display = 'block';
    } else {
        searchResults.style.display = 'none';
    }
}

</script>

<?php require_once 'includes/footer.php'; ?>

<style>
    @media (max-width: 768px) {
        .card-header {
            display: flex !important;
            align-items: flex-start !important;
            flex-direction: column;
            gap: 15px;
        }
        .card-header .btn-light {
            width: 100% !important;
        }
}
</style>