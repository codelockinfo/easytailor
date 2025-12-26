<?php
/**
 * Unauthorized Access Page
 * Tailoring Management System
 */

require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?php echo APP_NAME; ?></title>
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .error-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card error-card border-0">
                    <div class="card-body text-center p-5">
                        <div class="error-icon mb-4">
                            <i class="fas fa-ban"></i>
                        </div>
                        <h2 class="h4 mb-3">Access Denied</h2>
                        <p class="text-muted mb-4">
                            You don't have permission to access this page. Please contact your administrator if you believe this is an error.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="javascript:history.back()" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Go Back
                            </a>
                            <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

