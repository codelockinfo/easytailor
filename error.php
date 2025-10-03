<?php
/**
 * General Error Handler
 * Tailoring Management System
 */

// Get error details
$error_code = $_GET['error'] ?? '500';
$error_messages = [
    '400' => 'Bad Request',
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Page Not Found',
    '500' => 'Internal Server Error',
    '502' => 'Bad Gateway',
    '503' => 'Service Unavailable'
];

$error_title = $error_messages[$error_code] ?? 'Error';
$page_title = $error_title;
require_once 'includes/header.php';

// Get the requested URL for display
$requested_url = $_SERVER['REQUEST_URI'] ?? 'Unknown';
?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-lg-6 col-md-8 col-sm-10">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center p-5">
                    <!-- Error Icon -->
                    <div class="mb-4">
                        <?php if ($error_code === '404'): ?>
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                        <?php elseif ($error_code === '403'): ?>
                            <i class="fas fa-lock text-danger" style="font-size: 4rem;"></i>
                        <?php elseif ($error_code === '500'): ?>
                            <i class="fas fa-exclamation-circle text-danger" style="font-size: 4rem;"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-danger" style="font-size: 4rem;"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Error Code and Title -->
                    <h1 class="display-4 fw-bold text-primary mb-3"><?php echo htmlspecialchars($error_code); ?></h1>
                    <h2 class="h4 mb-3"><?php echo htmlspecialchars($error_title); ?></h2>
                    
                    <!-- Error Message -->
                    <div class="mb-4">
                        <?php if ($error_code === '404'): ?>
                            <p class="text-muted">Sorry, the page you are looking for doesn't exist or has been moved.</p>
                        <?php elseif ($error_code === '403'): ?>
                            <p class="text-muted">You don't have permission to access this resource.</p>
                        <?php elseif ($error_code === '500'): ?>
                            <p class="text-muted">Something went wrong on our end. Please try again later.</p>
                        <?php else: ?>
                            <p class="text-muted">An error occurred while processing your request.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Requested URL (if available and not 404) -->
                    <?php if ($requested_url !== 'Unknown' && $error_code !== '404'): ?>
                    <div class="alert alert-info mb-4">
                        <small class="text-muted">
                            <strong>Requested URL:</strong> <?php echo htmlspecialchars($requested_url); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <a href="dashboard.php" class="btn btn-primary w-100">
                                <i class="fas fa-home me-2"></i>Go to Dashboard
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button onclick="history.back()" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-2"></i>Go Back
                            </button>
                        </div>
                    </div>
                    
                    <!-- Contact Support (for server errors) -->
                    <?php if (in_array($error_code, ['500', '502', '503'])): ?>
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        If this problem persists, please contact the system administrator.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Links -->
                    <div class="border-top pt-4">
                        <h6 class="mb-3">Quick Links:</h6>
                        <div class="row g-2">
                            <div class="col-6 col-md-4">
                                <a href="customers.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-users me-1"></i>Customers
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="orders.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-clipboard-list me-1"></i>Orders
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="measurements.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-ruler me-1"></i>Measurements
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="invoices.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-file-invoice me-1"></i>Invoices
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="contacts.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-address-book me-1"></i>Contacts
                                </a>
                            </div>
                            <div class="col-6 col-md-4">
                                <a href="company-settings.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-cog me-1"></i>Settings
                                </a>
                            </div>
                        </div>
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
