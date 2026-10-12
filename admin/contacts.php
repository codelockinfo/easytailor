<?php

require_once __DIR__ . '/../config/config.php';
require_login(); 
require_once 'models/Contact.php';

$contactModel = new Contact();
$message = '';
$messageType = '';
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
                    require_once '../helpers/GA4Helper.php';
                    $_SESSION['ga4_event'] = GA4Helper::trackGenerateLead('contact', null);
                    $_SESSION['flash_message'] = 'Contact added successfully';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Failed to add contact';
                    $_SESSION['flash_type'] = 'error';
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
                    $_SESSION['flash_message'] = 'Contact updated successfully';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Failed to update contact';
                    $_SESSION['flash_type'] = 'error';
                }
                break;
                
            case 'delete':
                $contactId = (int)$_POST['contact_id'];
                if ($contactModel->delete($contactId)) {
                    $_SESSION['flash_message'] = 'Contact deleted successfully';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Failed to delete contact';
                    $_SESSION['flash_type'] = 'error';
                }
                break;

            default:
                $_SESSION['flash_message'] = 'Invalid action';
                $_SESSION['flash_type'] = 'error';
                break;
        }
    } else {
        $_SESSION['flash_message'] = 'Invalid request';
        $_SESSION['flash_type'] = 'error';
    }
    $redirect_url = 'contacts.php';
    $qs = [];
    if (!empty($_GET['page']))     $qs[] = 'page='     . (int)$_GET['page'];
    if (!empty($_GET['search']))   $qs[] = 'search='   . urlencode($_GET['search']);
    if (!empty($_GET['category'])) $qs[] = 'category=' . urlencode($_GET['category']);
    if ($qs) $redirect_url .= '?' . implode('&', $qs);
    header('Location: ' . $redirect_url);
    exit;
}
if (!empty($_SESSION['flash_message'])) {
    $message     = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
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
$editContact = null;
if (isset($_GET['edit'])) {
    $editContact = $contactModel->find((int)$_GET['edit']);
}
$categories = ['Supplier', 'Partner', 'Vendor', 'Service Provider', 'Other'];

$contactStats = $contactModel->getContactStats();
$totalContactsCount = $contactStats['total'] ?? $totalContacts;
$activeContactsCount = $contactStats['active'] ?? 0;
$supplierCount = 0;
if (!empty($contactStats['by_category'])) {
    foreach ($contactStats['by_category'] as $catItem) {
        if (stripos($catItem['category'], 'supplier') !== false || stripos($catItem['category'], 'vendor') !== false) {
            $supplierCount += $catItem['count'];
        }
    }
}
$categoriesCount = count($contactStats['by_category'] ?? []);

if (!function_exists('getContactAvatarDesign')) {
    function getContactAvatarDesign($name, $index) {
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

if (!function_exists('getContactCategoryBadge')) {
    function getContactCategoryBadge($category) {
        $cat = strtolower(trim($category ?? ''));
        if (strpos($cat, 'supplier') !== false) {
            return ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe'];
        } elseif (strpos($cat, 'vendor') !== false) {
            return ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fde68a'];
        } elseif (strpos($cat, 'partner') !== false) {
            return ['bg' => '#faf5ff', 'color' => '#7c3aed', 'border' => '#ede9fe'];
        } elseif (strpos($cat, 'service') !== false) {
            return ['bg' => '#f0fdfa', 'color' => '#0f766e', 'border' => '#99f6e4'];
        } else {
            return ['bg' => '#f8fafc', 'color' => '#475569', 'border' => '#e2e8f0'];
        }
    }
}
$page_title = 'Contact Management';
require_once 'includes/header.php';
?>

<style>
.cat-stat-box {
    border-radius: 16px;
    padding: 10px 12px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.cat-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.cat-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.15s ease;
    padding: 0;
    text-decoration: none;
}
.cat-action-edit {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #2563eb;
}
.cat-action-edit:hover {
    background: #dbeafe;
    color: #1d4ed8;
}
.cat-action-del {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
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
#searchInput, #categoryFilter {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
@media (max-width: 768px) {
    .cat-stat-box .fs-4 {
        font-size: 1.15rem !important;
    }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" style="border-radius: 12px;">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php 
$pageHeaderTitle = 'Contacts';
$pageHeaderSubtitle = 'Manage suppliers, vendors, partners, and tailoring business contacts.';
include 'includes/page-header.php'; 
?>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL CONTACTS</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="statTotalContacts"><?php echo number_format($totalContactsCount); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dbeafe; color: #2563eb; font-size: 0.95rem;">
                <i class="fas fa-address-book"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">ACTIVE CONTACTS</span>
                <div class="fs-4 fw-bold text-success mt-1"><?php echo number_format($activeContactsCount); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dcfce7; color: #16a34a; font-size: 0.95rem;">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">SUPPLIERS & VENDORS</span>
                <div class="fs-4 fw-bold text-warning mt-1"><?php echo number_format($supplierCount); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #fef3c7; color: #d97706; font-size: 0.95rem;">
                <i class="fas fa-truck"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">CATEGORIES</span>
                <div class="fs-4 fw-bold text-primary mt-1"><?php echo number_format($categoriesCount); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #ede9fe; color: #9333ea; font-size: 0.95rem;">
                <i class="fas fa-tags"></i>
            </div>
        </div>
    </div>
</div>
<div class="row g-2 align-items-center mb-3">
    
    <div class="col-12 col-md-4 col-lg-5">
        <div class="position-relative">
            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
            <input type="text" 
                   id="searchInput"
                   class="form-control" 
                   placeholder="Search contacts by name, company, email, phone..."
                   value="<?php echo htmlspecialchars($search); ?>"
                   autocomplete="off"
                   style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding-left: 2.4rem; padding-right: 2.4rem;">
            <button type="button" id="clearSearch" class="btn btn-link text-muted position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-decoration-none" style="display: <?php echo !empty($search) ? 'block' : 'none'; ?>; width: 24px; height: 24px;" title="Clear search">
                <i class="fas fa-times" style="font-size: 0.85rem;"></i>
            </button>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-3">
        <select class="form-select" id="categoryFilter" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 2.2rem 0.45rem 0.8rem; line-height: 1.4; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
        <button type="button" id="clearAllFiltersBtn" class="btn btn-outline-danger align-items-center justify-content-center text-nowrap w-100" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 16px; display: inline-flex;" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
    </div>
    <div class="col-12 col-md-3 col-lg-2 d-flex justify-content-md-end">
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap w-100 w-md-auto px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#contactModal">
            <i class="fas fa-plus"></i>
            <span>Add Contact</span>
        </button>
    </div>
</div>

<div id="searchResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="searchCount">0</strong> contacts found</span>
    </div>
</div>
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($contacts)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>CONTACT</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortContactsBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3">COMPANY</th>
                            <th class="py-3">CONTACT INFO</th>
                            <th class="py-3">CATEGORY</th>
                            <th class="py-3">STATUS</th>
                            <th class="pe-3 py-3 text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="contactsTableBody">
                        <?php foreach ($contacts as $index => $contact): 
                            $cleanName = trim($contact['name'] ?? 'Contact');
                            $initial = strtoupper(mb_substr($cleanName, 0, 1) ?: 'C');
                            $avColor = getContactAvatarDesign($cleanName, $index);
                            $catBadge = getContactCategoryBadge($contact['category'] ?? '');
                            $isActive = ($contact['status'] ?? 'active') === 'active';
                        ?>
                        <tr class="contact-row" data-name="<?php echo htmlspecialchars(strtolower($cleanName)); ?>">
                            <td class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo $avColor['bg']; ?>; border: 1px solid <?php echo $avColor['border']; ?>; color: <?php echo $avColor['color']; ?>; font-size: 0.88rem;">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 180px;">
                                            <?php echo display_val($contact['name']); ?>
                                        </div>
                                        <?php if (!empty($contact['notes'])): ?>
                                            <div class="text-muted text-truncate" style="font-size: 0.72rem; max-width: 180px;" title="<?php echo htmlspecialchars($contact['notes']); ?>">
                                                <?php echo display_val(substr($contact['notes'], 0, 45)); ?><?php echo strlen($contact['notes']) > 45 ? '...' : ''; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($contact['company'])): ?>
                                    <div class="text-dark fw-medium" style="font-size: 0.84rem;">
                                        <i class="fas fa-building text-muted me-1" style="font-size: 0.75rem;"></i>
                                        <?php echo display_val($contact['company']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <div>
                                    <?php if (!empty($contact['phone'])): ?>
                                        <div class="text-dark" style="font-size: 0.82rem;">
                                            <i class="fas fa-phone text-muted me-1" style="font-size: 0.72rem;"></i>
                                            <?php echo display_val($contact['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($contact['email'])): ?>
                                        <div class="text-muted" style="font-size: 0.78rem;">
                                            <i class="fas fa-envelope text-muted me-1" style="font-size: 0.72rem;"></i>
                                            <?php echo display_val($contact['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (empty($contact['phone']) && empty($contact['email'])): ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($contact['category'])): ?>
                                    <span class="badge fw-medium" style="background: <?php echo $catBadge['bg']; ?>; color: <?php echo $catBadge['color']; ?>; border: 1px solid <?php echo $catBadge['border']; ?>; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px;">
                                        <?php echo display_val($contact['category']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: <?php echo $isActive ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $isActive ? '#15803d' : '#64748b'; ?>; border: 1px solid <?php echo $isActive ? '#bbf7d0' : '#e2e8f0'; ?>; font-size: 0.72rem;">
                                    <?php echo ucfirst($contact['status'] ?? 'Active'); ?>
                                </span>
                            </td>
                            <td class="pe-3 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" 
                                            class="cat-action-btn cat-action-edit" 
                                            onclick="editContact(<?php echo htmlspecialchars(json_encode($contact)); ?>)"
                                            title="Edit Contact">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-del" 
                                            onclick="deleteContact(<?php echo (int)$contact['id']; ?>, '<?php echo htmlspecialchars(addslashes($contact['name'])); ?>')"
                                            title="Delete Contact">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="p-3">
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Contact pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
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
        <?php else: ?>
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                    <i class="fas fa-address-book fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No contacts found</h5>
                <p class="text-muted small mb-3">
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        No contacts match your search criteria.
                    <?php else: ?>
                        Get started by adding your first contact.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($category_filter)): ?>
                    <button type="button" class="btn text-white fw-semibold px-4 py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#contactModal">
                        <i class="fas fa-plus me-1"></i>Add Contact
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <form method="POST" id="contactForm">
                <div class="modal-header border-bottom py-3" style="background: #fafafa;">
                    <h5 class="modal-title fw-bold text-dark" id="contactModalTitle">Add Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="contactAction" value="create">
                    <input type="hidden" name="contact_id" id="contactId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label fw-semibold small text-muted">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="company" class="form-label fw-semibold small text-muted">Company</label>
                            <input type="text" class="form-control" id="company" name="company" style="border-radius: 10px;">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-semibold small text-muted">Email</label>
                            <input type="email" class="form-control" id="email" name="email" style="border-radius: 10px;">
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
                    
                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold small text-muted">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" style="border-radius: 10px;"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label fw-semibold small text-muted">Category</label>
                            <select class="form-select" id="category" name="category" style="border-radius: 10px;">
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
                        <label for="notes" class="form-label fw-semibold small text-muted">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" style="border-radius: 10px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3" style="background: #fafafa;">
                    <button type="button" class="btn btn-light fw-medium px-4" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.85rem;">Cancel</button>
                    <button type="submit" class="btn text-white fw-semibold px-4" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 10px; font-size: 0.85rem; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);">
                        <i class="fas fa-save me-1"></i>Save Contact
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary me-3" data-bs-dismiss="modal" style="border-radius: 10px;">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="contact_id" id="deleteContactId">
                    <button type="submit" class="btn btn-danger px-4" style="border-radius: 10px;">
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
    document.getElementById('name').value = contact.name || '';
    document.getElementById('company').value = contact.company || '';
    document.getElementById('email').value = contact.email || '';
    let phoneNumber = contact.phone || '';
    if (phoneNumber.startsWith('+91')) {
        phoneNumber = phoneNumber.replace('+91', '').trim();
    }
    phoneNumber = phoneNumber.replace(/[^0-9]/g, '').slice(0, 10);
    document.getElementById('phone').value = phoneNumber;
    document.getElementById('address').value = contact.address || '';
    document.getElementById('category').value = contact.category || '';
    document.getElementById('notes').value = contact.notes || '';
    new bootstrap.Modal(document.getElementById('contactModal')).show();
}

function deleteContact(contactId, contactName) {
    document.getElementById('deleteContactId').value = contactId;
    document.getElementById('deleteContactName').textContent = contactName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
document.getElementById('contactModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('contactModalTitle').textContent = 'Add Contact';
    document.getElementById('contactAction').value = 'create';
    document.getElementById('contactId').value = '';
    document.getElementById('contactForm').reset();
});
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

const avatarPalettes = [
    { bg: '#eff6ff', border: '#dbeafe', color: '#3b82f6' },
    { bg: '#f0fdf4', border: '#dcfce7', color: '#10b981' },
    { bg: '#faf5ff', border: '#ede9fe', color: '#8b5cf6' },
    { bg: '#fffbeb', border: '#fef3c7', color: '#f59e0b' },
    { bg: '#fdf2f8', border: '#fce7f3', color: '#ec4899' },
    { bg: '#f0fdfa', border: '#ccfbf1', color: '#0d9488' }
];

function getJsCategoryBadge(category) {
    const cat = (category || '').toLowerCase().trim();
    if (cat.indexOf('supplier') !== -1) {
        return { bg: '#eff6ff', color: '#2563eb', border: '#bfdbfe' };
    } else if (cat.indexOf('vendor') !== -1) {
        return { bg: '#fffbeb', color: '#b45309', border: '#fde68a' };
    } else if (cat.indexOf('partner') !== -1) {
        return { bg: '#faf5ff', color: '#7c3aed', border: '#ede9fe' };
    } else if (cat.indexOf('service') !== -1) {
        return { bg: '#f0fdfa', color: '#0f766e', border: '#99f6e4' };
    }
    return { bg: '#f8fafc', color: '#475569', border: '#e2e8f0' };
}

let searchTimeout;
document.addEventListener('DOMContentLoaded', function() {
  
    setupPhoneValidation('phone', '+91');
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
                phoneInput.value = phoneValue;
            }
        });
    }
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const clearAllFiltersBtn = document.getElementById('clearAllFiltersBtn');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            if (clearBtn) clearBtn.style.display = searchTerm ? 'block' : 'none';
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterContacts();
            }, 300);
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            this.style.display = 'none';
            const searchResults = document.getElementById('searchResults');
            if (searchResults) searchResults.style.display = 'none';
            filterContacts();
        });
    }
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            filterContacts();
        });
    }
    if (clearAllFiltersBtn) {
        clearAllFiltersBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            if (categoryFilter) categoryFilter.value = '';
            const searchResults = document.getElementById('searchResults');
            if (searchResults) searchResults.style.display = 'none';
            filterContacts();
        });
    }
    const sortBtn = document.getElementById('sortContactsBtn');
    let sortAsc = true;
    if (sortBtn) {
        sortBtn.addEventListener('click', function() {
            const tableBody = document.getElementById('contactsTableBody');
            if (!tableBody) return;
            const rows = Array.from(tableBody.querySelectorAll('tr.contact-row'));
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
    if (searchInput) {
        const searchTerm = searchInput.value.trim();
        if (searchTerm && clearBtn) {
            clearBtn.style.display = 'block';
            const searchResults = document.getElementById('searchResults');
            const searchCount = document.getElementById('searchCount');
            if (searchResults && searchCount) {
                searchCount.textContent = '<?php echo count($contacts); ?>';
                searchResults.style.display = 'block';
            }
        }
    }
    const tableBody = document.getElementById('contactsTableBody');
    if (tableBody) {
        window.originalContactsTableContent = tableBody.innerHTML;
    }
});

function bindDynamicEditButtons() {
    const tableBody = document.getElementById('contactsTableBody');
    if (!tableBody) return;
    tableBody.querySelectorAll('.edit-contact-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
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

function filterContacts() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const searchTerm = searchInput ? searchInput.value.trim() : '';
    const category = categoryFilter ? categoryFilter.value : '';
    const tableBody = document.getElementById('contactsTableBody');
    
    if (!tableBody) {
        console.error('Table body element not found');
        return;
    }
    if (!searchTerm && !category) {
        if (window.originalContactsTableContent) {
            tableBody.innerHTML = window.originalContactsTableContent;
            updateSearchResults(0, '');
        }
        return;
    }
    
    tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2 text-primary"></i>Loading contacts...</td></tr>';
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (category) params.append('category', category);
    fetch(`ajax/filter_contacts.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateContactsTable(data.contacts);
                updateSearchResults(data.pagination.total_contacts, searchTerm, data.pagination.total_pages);
            } else {
                console.error('Filter error:', data.error);
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading contacts</td></tr>';
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading contacts</td></tr>';
        });
}

function updateContactsTable(contacts) {
    const tableBody = document.getElementById('contactsTableBody');
    
    if (!tableBody) {
        console.error('Table body element not found in updateContactsTable');
        return;
    }
    
    if (!contacts || contacts.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                        <i class="fas fa-address-book fa-lg"></i>
                    </div>
                    <div class="fw-semibold text-dark">No contacts found</div>
                    <small class="text-muted">Try adjusting your search or category filter.</small>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    contacts.forEach((contact, index) => {
        const cleanName = (contact.name || 'Contact').trim();
        const initial = cleanName.charAt(0).toUpperCase() || 'C';
        const palette = avatarPalettes[index % avatarPalettes.length];
        const catBadge = getJsCategoryBadge(contact.category);
        const isActive = (contact.status || 'active').toLowerCase() === 'active';
        const contactJson = JSON.stringify(contact)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
            
        const safeNameForDelete = cleanName.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        
        html += `
            <tr class="contact-row" data-name="${escapeHtml(cleanName.toLowerCase())}">
                <td class="ps-3 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: ${palette.bg}; border: 1px solid ${palette.border}; color: ${palette.color}; font-size: 0.88rem;">
                            ${escapeHtml(initial)}
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 180px;">
                                ${escapeHtml(contact.name || '')}
                            </div>
                            ${contact.notes ? `
                                <div class="text-muted text-truncate" style="font-size: 0.72rem; max-width: 180px;" title="${escapeHtml(contact.notes)}">
                                    ${escapeHtml(contact.notes.length > 45 ? contact.notes.substring(0, 45) + '...' : contact.notes)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    ${contact.company ? `
                        <div class="text-dark fw-medium" style="font-size: 0.84rem;">
                            <i class="fas fa-building text-muted me-1" style="font-size: 0.75rem;"></i>
                            ${escapeHtml(contact.company)}
                        </div>
                    ` : '<span class="text-muted small">—</span>'}
                </td>
                <td class="py-3">
                    <div>
                        ${contact.phone ? `
                            <div class="text-dark" style="font-size: 0.82rem;">
                                <i class="fas fa-phone text-muted me-1" style="font-size: 0.72rem;"></i>
                                ${escapeHtml(contact.phone)}
                            </div>
                        ` : ''}
                        ${contact.email ? `
                            <div class="text-muted" style="font-size: 0.78rem;">
                                <i class="fas fa-envelope text-muted me-1" style="font-size: 0.72rem;"></i>
                                ${escapeHtml(contact.email)}
                            </div>
                        ` : ''}
                        ${!contact.phone && !contact.email ? '<span class="text-muted small">—</span>' : ''}
                    </div>
                </td>
                <td class="py-3">
                    ${contact.category ? `
                        <span class="badge fw-medium" style="background: ${catBadge.bg}; color: ${catBadge.color}; border: 1px solid ${catBadge.border}; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px;">
                            ${escapeHtml(contact.category)}
                        </span>
                    ` : '<span class="text-muted small">—</span>'}
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: ${isActive ? '#dcfce7' : '#f1f5f9'}; color: ${isActive ? '#15803d' : '#64748b'}; border: 1px solid ${isActive ? '#bbf7d0' : '#e2e8f0'}; font-size: 0.72rem;">
                        ${isActive ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td class="pe-3 py-3 text-end">
                    <div class="d-inline-flex align-items-center gap-1">
                        <button type="button" 
                                class="cat-action-btn cat-action-edit edit-contact-btn" 
                                data-contact='${contactJson}' 
                                title="Edit Contact">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" 
                                class="cat-action-btn cat-action-del" 
                                onclick="deleteContact(${contact.id}, '${safeNameForDelete}')" 
                                title="Delete Contact">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    bindDynamicEditButtons();
}

function updateSearchResults(totalContacts, searchTerm, totalPages = null) {
    const searchResults = document.getElementById('searchResults');
    const searchCount = document.getElementById('searchCount');
    
    if (!searchResults || !searchCount) return;
    
    if (searchTerm && totalContacts > 0) {
        searchCount.textContent = totalContacts;
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