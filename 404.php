<?php
/**
 * 404 Error Page
 * Tailoring Management System
 */

$page_title = 'Page Not Found';
require_once 'includes/header.php';

// Get the requested URL for display
$requested_url = $_SERVER['REQUEST_URI'] ?? 'Unknown';
?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-lg-6 col-md-8 col-sm-10">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center p-5">
                    <!-- 404 Icon -->
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                    </div>
                    
                    <!-- Error Title -->
                    <h1 class="display-4 fw-bold text-primary mb-3">404</h1>
                    <h2 class="h4 mb-3">Page Not Found</h2>
                    
                    <!-- Error Message -->
                    <p class="text-muted mb-4">
                        Sorry, the page you are looking for doesn't exist or has been moved.
                    </p>
                    
                    <!-- Requested URL (if available) -->
                    <?php if ($requested_url !== 'Unknown'): ?>
                    <div class="alert alert-info mb-4">
                        <small class="text-muted">
                            <strong>Requested URL:</strong> <?php echo htmlspecialchars($requested_url); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <a href="admin/dashboard.php" class="btn btn-primary w-100">
                                <i class="fas fa-home me-2"></i>Go to Dashboard
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button onclick="history.back()" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-2"></i>Go Back
                            </button>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="border-top pt-4">
                        <h6 class="mb-3">Quick Links:</h6>
                        <div class="row g-2">
                            <div class="col-6 col-md-4">
                                <a href="admin/customers.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-users me-1"></i>Customers
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="admin/orders.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-clipboard-list me-1"></i>Orders
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="admin/measurements.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-ruler me-1"></i>Measurements
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="admin/invoices.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-file-invoice me-1"></i>Invoices
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="admin/contacts.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-address-book me-1"></i>Contacts
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="admin/company-settings.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-cog me-1"></i>Settings
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Form -->
                    <div class="border-top pt-4 mt-4">
                        <h6 class="mb-3">Search for what you need:</h6>
                        <form action="admin/dashboard.php" method="GET" class="d-flex gap-2">
                            <input type="text" class="form-control" name="search" placeholder="Search customers, orders, etc...">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.min-vh-100 {
    min-height: 100vh;
}
.card {
    border-radius: 15px;
}
.display-4 {
    font-size: 5rem;
    line-height: 1;
}
@media (max-width: 768px) {
    .display-4 {
        font-size: 3rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
