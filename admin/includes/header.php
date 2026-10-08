<?php

require_once __DIR__ . '/../../config/config.php';
if (!is_logged_in()) {
    smart_redirect('login.php');
}
$current_user = [
    'id' => get_user_id(),
    'name' => get_user_name() ?? 'User',
    'role' => get_user_role(),
    'username' => $_SESSION['username'] ?? ''
];
require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT profile_image FROM users WHERE id = :id");
$userId = $current_user['id'];
$stmt->bindParam(':id', $userId);
$stmt->execute();
$headerUserData = $stmt->fetch(PDO::FETCH_ASSOC);
$current_user['profile_image'] = $headerUserData['profile_image'] ?? null;
$notifCount = 0;
$notifItems = [];
if (!empty($current_user['id']) && function_exists('get_company_id')) {
    $headerCompanyId = get_company_id();
    if ($headerCompanyId) {
        try {
            $stmtOverdue = $db->prepare("SELECT COUNT(*) as cnt FROM orders WHERE company_id = :cid AND due_date < CURDATE() AND status NOT IN ('completed', 'delivered', 'cancelled')");
            $stmtOverdue->execute([':cid' => $headerCompanyId]);
            $overdueCnt = (int)($stmtOverdue->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            if ($overdueCnt > 0) {
                $notifCount += $overdueCnt;
                $notifItems[] = [
                    'icon' => 'fas fa-exclamation-circle text-danger',
                    'title' => "$overdueCnt Overdue Order" . ($overdueCnt > 1 ? 's' : ''),
                    'desc' => 'Need immediate attention',
                    'link' => 'orders.php'
                ];
            }
            $stmtDue = $db->prepare("SELECT COUNT(*) as cnt FROM orders WHERE company_id = :cid AND due_date = CURDATE() AND status NOT IN ('completed', 'delivered', 'cancelled')");
            $stmtDue->execute([':cid' => $headerCompanyId]);
            $dueCnt = (int)($stmtDue->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            if ($dueCnt > 0) {
                $notifCount += $dueCnt;
                $notifItems[] = [
                    'icon' => 'fas fa-clock text-warning',
                    'title' => "$dueCnt Order" . ($dueCnt > 1 ? 's' : '') . " Due Today",
                    'desc' => 'Scheduled for delivery today',
                    'link' => 'orders.php'
                ];
            }
            $stmtInv = $db->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE company_id = :cid AND status IN ('due', 'partial')");
            $stmtInv->execute([':cid' => $headerCompanyId]);
            $invDueCnt = (int)($stmtInv->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            if ($invDueCnt > 0) {
                $notifItems[] = [
                    'icon' => 'fas fa-file-invoice text-info',
                    'title' => "$invDueCnt Unpaid Invoice" . ($invDueCnt > 1 ? 's' : ''),
                    'desc' => 'Pending payment collections',
                    'link' => 'invoices.php'
                ];
            }
        } catch (Exception $e) {
            
        }
    }
}
?>
<?php
require_once __DIR__ . '/../../helpers/SEOHelper.php';

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$currentPage = basename($_SERVER['PHP_SELF']);
$canonicalUrl = $baseUrl . '/admin/' . $currentPage;
$pageTitle = __t(strtolower(str_replace(' ', '_', $page_title ?? 'dashboard')));

$seoOptions = [
    'title' => ucfirst($pageTitle) . ' - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => 'Manage your tailor shop with ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System') . ' - ' . ucfirst($pageTitle) . ' dashboard',
    'keywords' => 'tailor shop management, tailoring software, ' . strtolower($pageTitle),
    'canonical' => $canonicalUrl,
    'noindex' => true 
];
?>
<!DOCTYPE html>
<html lang="<?php echo getHtmlLang(); ?>" dir="<?php echo getTextDirection(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo SEOHelper::generateMetaTags($seoOptions); ?>
    <link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
   
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">
    <?php
    if (!defined('GA4_MEASUREMENT_ID')) {
        define('GA4_MEASUREMENT_ID', 'G-27LLB9QMEV');
    }
    require_once __DIR__ . '/../../helpers/GA4Helper.php';
    $ga4Code = GA4Helper::generateBaseCode();
    if (!empty($ga4Code)) {
        echo $ga4Code;
    } else {
        $ga4Id = defined('GA4_MEASUREMENT_ID') ? GA4_MEASUREMENT_ID : 'G-27LLB9QMEV';
        ?>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        window.gtag = gtag;
        gtag('js', new Date());
        gtag('config', '<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>');
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php
    }
    ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand {
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 700;
        }
        #sidebarClose {
            display: none;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 0.25rem 1rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            padding: 0.72rem 1.1rem;
            border-radius: 12px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.94rem;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link:focus {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.12) !important;
            transform: translateX(4px);
        }

        .sidebar .nav-link.active {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.22) !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }
        
        .nav-link i {
            width: 22px;
            margin-right: 12px;
            font-size: 1.05rem;
            text-align: center;
        }
        
        .nav-dropdown {
            margin-left: 1rem;
        }
        
        .nav-dropdown .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .top-header {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .content-area {
            padding: 1rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 1.5rem;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px;
            padding: 17px;
            margin-bottom: 1rem;
        }
        
        .stat-card .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background-color: #fff;
            color: #000000 !important;
        }
        
        .form-control:hover,
        input:hover,
        textarea:hover {
            color: #000000 !important;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background-color: #fff;
            color: #000000 !important;
        }
        .dropdown-menu {
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        }
        .dropdown-menu .dropdown-item,
        .dropdown-item {
            color: #334155;
            font-size: 0.88rem;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: 8px;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .dropdown-menu .dropdown-item:hover,
        .dropdown-menu .dropdown-item:focus,
        .dropdown-item:hover,
        .dropdown-item:focus,
        .autocomplete-item:hover,
        .searchable-option:hover,
        .dropdown-menu a:hover,
        .dropdown-menu a:focus,
        input + .dropdown-menu .dropdown-item:hover,
        input + ul li:hover,
        input + .list-group .list-group-item:hover {
            background: #f1f5f9 !important;
            color: #4f46e5 !important;
        }
        .dropdown-menu .dropdown-item:hover i,
        .dropdown-menu .dropdown-item:focus i,
        .dropdown-item:hover i,
        .dropdown-item:focus i {
            color: #4f46e5 !important;
        }
        .dropdown-menu .dropdown-item.text-danger,
        .dropdown-item.text-danger {
            color: #dc2626 !important;
        }
        .dropdown-menu .dropdown-item.text-danger:hover,
        .dropdown-menu .dropdown-item.text-danger:focus,
        .dropdown-item.text-danger:hover,
        .dropdown-item.text-danger:focus {
            background-color: #fef2f2 !important;
            color: #dc2626 !important;
        }
        .dropdown-menu .dropdown-item.text-danger:hover i,
        .dropdown-menu .dropdown-item.text-danger:focus i,
        .dropdown-item.text-danger:hover i,
        .dropdown-item.text-danger:focus i {
            color: #dc2626 !important;
        }
        .dropdown-menu li:has(.dropdown-header):hover,
        .dropdown-menu li:has(.dropdown-divider):hover,
        .dropdown-menu li:hover .dropdown-header,
        .dropdown-menu li:hover .dropdown-divider {
            background: transparent !important;
        }
        .dropdown-menu .dropdown-header {
            color: #64748b !important;
            font-weight: 700 !important;
            font-size: 0.85rem !important;
            padding: 0.5rem 0.875rem !important;
            background: transparent !important;
        }
        .form-control::placeholder,
        .form-select::placeholder,
        input::placeholder,
        textarea::placeholder,
        select::placeholder {
            color: #000000 !important;
            opacity: 1;
        }
        .form-control,
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="tel"],
        input[type="url"],
        input[type="search"],
        textarea {
            color: #000000 !important;
        }
        .form-select,
        select {
            color: #000000 !important;
        }
        
        .form-select option,
        select option {
            color: #000000 !important;
            background-color: #ffffff;
        }
        .form-select {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            line-height: 1.5;
            
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.875rem 1.25rem;
            padding-right: 3rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%234a5568'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.875rem center;
            background-size: 18px 18px;
            cursor: pointer;
            position: relative;
            transition: border-color 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
            background-attachment: local;
        }
        
        .form-select:hover {
            border-color: #cbd5e0;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%234a5568'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e"), #ffffff;
            background-position: right 0.875rem center, center !important;
            background-size: 18px 18px, 100% 100% !important;
            background-repeat: no-repeat, no-repeat !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.1);
            transform: none !important;
        }
        
        .form-select:focus {
            border-color: #667eea;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%234a5568'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e"), #ffffff;
            background-position: right 0.875rem center, center !important;
            background-size: 18px 18px, 100% 100% !important;
            background-repeat: no-repeat, no-repeat !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
            outline: none;
        }
        
        .form-select:active {
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-select option {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            line-height: 1.6;
            
            padding: 0.875rem 1.25rem;
            margin: 0.125rem 0.25rem;
            background-color: #ffffff;
            color: #000000 !important;
            border-radius: 8px;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .form-select option:hover,
        select option:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #ffffff !important;
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
            font-weight: 600;
        }
        
        .form-select option:checked,
        .form-select option:focus,
        .form-select option[selected] {
            background-color: #667eea !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #f70707ff !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
        }
        .dropdown-container {
            position: relative;
        }
        .dropdown-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(145deg, transparent, rgba(102, 126, 234, 0.02));
            border-radius: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            z-index: 0;
        }
        .dropdown-container:hover::before {
            opacity: 1;
        }
        
        .dropdown-container .form-select {
            position: relative;
            z-index: 1;
        }
        .form-select.loading {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2'%3e%3cpath d='M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83'/%3e%3c/svg%3e");
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .form-select {
            color: #000000 !important;
        }
        
        .form-select::placeholder {
            color: #000000 !important;
            font-weight: 400;
            opacity: 1;
        }
        .form-select:disabled {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23000000'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e"), linear-gradient(145deg, #f7fafc, #edf2f7);
            background-position: right 0.875rem center, center;
            background-size: 18px 18px, 100% 100%;
            border-color: #e2e8f0;
            color: #000000 !important;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .form-select:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        @media (prefers-color-scheme: dark) {
            .form-select {
                background: linear-gradient(145deg, #2d3748, #4a5568);
             
                color: #e2e8f0;
                background: #ffffff;
            }
            
            .form-select:hover {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23a0aec0'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e"), #ffffff;
                background-position: right 0.875rem center, center;
                background-size: 18px 18px, 100% 100%;
                border-color: #667eea;
            }
            
            .form-select option {
                background-color: #ffffff;
                color: #000000 !important;
            }
            
            .form-select option:hover,
            select option:hover {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                color: #ffffff !important;
            }
            
            .form-select option:checked,
            .form-select option:focus,
            .form-select option[selected] {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff !important;
            }
        }
        .form-select::-webkit-scrollbar {
            width: 6px;
        }
        
        .form-select::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .form-select::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
        }
        
        .form-select::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a67d8, #6b46c1);
        }
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
            text-transform: capitalize;
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(3px);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        @media (max-width: 991.98px) {
            :root {
                --header-height: 60px;
            }
            .sidebar {
                transform: translateX(-100%) !important;
                width: 280px !important;
                max-width: 85vw !important;
                z-index: 1050 !important;
                box-shadow: none;
            }
            .sidebar.show {
                transform: translateX(0) !important;
                box-shadow: 6px 0 25px rgba(0,0,0,0.3) !important;
            }
            #sidebarClose {
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: #ffffff;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
            }
            #sidebarClose:hover {
                background: rgba(255, 255, 255, 0.35);
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100vw !important;
                overflow-x: hidden;
            }
            .top-header {
                padding: 0 12px !important;
            }
            .page-main-title {
                display: inline-block !important;
                font-size: 1.15rem !important;
                font-weight: 700 !important;
                color: #1e293b !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                max-width: 180px !important;
            }
            .mobile-center-brand {
                display: none !important;
            }
            .content-area {
                padding: 12px 12px 30px !important;
            }
        }

        @media (max-width: 767.98px) {
          
            .row:has(> [class*="col"] > .stat-card) {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                margin-bottom: 14px !important;
            }
            .row:has(> [class*="col"] > .stat-card) > [class*="col"] {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                flex: none !important;
            }
            .stat-card {
                padding: 12px 14px !important;
                border-radius: 12px !important;
                margin-bottom: 0 !important;
                min-height: 82px !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                box-shadow: 0 3px 10px rgba(0,0,0,0.06) !important;
            }
            .stat-card .stat-number {
                font-size: 1.35rem !important;
                font-weight: 700 !important;
                line-height: 1.2 !important;
            }
            .stat-card .stat-label {
                font-size: 0.76rem !important;
                font-weight: 500 !important;
                opacity: 0.9 !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .stat-card .stat-icon {
                font-size: 1.4rem !important;
                opacity: 0.8 !important;
            }
            .stat-card small {
                font-size: 0.68rem !important;
            }

            .card-body > .row.align-items-end > .col,
            .card-body > .row.g-2 > .col,
            .card-body > .row.g-3 > .col-md-6,
            .card-body > .row.g-3 > .col-md-4,
            .card-body > .row.g-3 > .col-md-2 {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                width: 100% !important;
                margin-bottom: 8px !important;
            }
            .card-body > .row.align-items-end > .col-auto,
            .card-body > .row.g-2 > .col-auto {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .card-body > .row.align-items-end > .col-auto .btn,
            .card-body > .row.g-2 > .col-auto .btn {
                width: 100% !important;
            }
            .card {
                border-radius: 12px !important;
                margin-bottom: 14px !important;
            }
            .card-header {
                padding: 10px 14px !important;
                border-radius: 12px 12px 0 0 !important;
            }
            .card-header.d-flex, .card-header .d-flex {
                flex-wrap: wrap !important;
                gap: 8px !important;
            }
            .card-header h5, .card-header .card-title {
                font-size: 0.95rem !important;
            }
            .card-header .btn {
                font-size: 0.78rem !important;
                padding: 4px 10px !important;
                border-radius: 8px !important;
            }
            .card-body {
                padding: 12px !important;
            }
            .table-responsive {
                border-radius: 10px !important;
                -webkit-overflow-scrolling: touch !important;
                margin-bottom: 0 !important;
            }
            .table th {
                font-size: 0.78rem !important;
                padding: 8px 10px !important;
                white-space: nowrap;
            }
            .table td {
                font-size: 0.82rem !important;
                padding: 8px 10px !important;
            }
            .table .btn-group, .table .btn-group-sm {
                display: inline-flex !important;
                flex-wrap: nowrap !important;
                gap: 2px !important;
            }
            .table .btn-sm, .table .btn-group-sm > .btn {
                padding: 4px 7px !important;
                font-size: 0.75rem !important;
                border-radius: 6px !important;
            }
            .nav-tabs, .nav-pills {
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding-bottom: 4px !important;
                gap: 4px !important;
            }
            .nav-tabs .nav-link, .nav-pills .nav-link {
                white-space: nowrap !important;
                font-size: 0.82rem !important;
                padding: 6px 12px !important;
                border-radius: 8px !important;
            }
            .modal-dialog {
                margin: 10px !important;
                max-width: calc(100% - 20px) !important;
            }
            .modal-content {
                border-radius: 14px !important;
            }
            .modal-header, .modal-footer {
                padding: 12px 14px !important;
            }
            .modal-body {
                padding: 14px !important;
                max-height: calc(85vh - 120px) !important;
                overflow-y: auto !important;
            }
            .pagination {
                flex-wrap: wrap !important;
                justify-content: center !important;
                gap: 4px !important;
            }
            .pagination .page-link {
                padding: 4px 8px !important;
                font-size: 0.78rem !important;
            }
        }
        .notif-bell-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .notif-bell-btn:hover {
            background: #f1f5f9;
            color: #4f46e5;
        }
        .notif-dot {
            position: absolute;
            top: 7px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border: 2px solid #ffffff;
            border-radius: 50%;
        }

        .user-pill-btn {
            background: transparent;
            border: none;
            padding: 2px 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            border-radius: 30px;
            transition: background 0.2s ease;
        }
        .user-pill-btn:hover, .user-pill-btn:focus {
            background: #f8fafc;
        }
        .user-avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
            flex-shrink: 0;
        }
        .user-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-pill-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
        }
        .user-pill-chevron {
            font-size: 0.68rem;
            color: #3b82f6;
        }
        .dropdown-toggle::after {
            display: none !important;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner-border:not(.spinner-border-sm) {
            width: 3rem;
            height: 3rem;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        .pagination .page-item .page-link {
            color: #667eea;
            border-color: #dee2e6;
            box-shadow: none;
        }
        
        .pagination .page-item .page-link:hover {
            color: #ffffff;
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .pagination .page-item.active .page-link {
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-color: #667eea;
        }
        
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #dee2e6;
        }
        
        .pagination .page-link:focus {
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="sidebar-brand d-flex align-items-center">
                <?php
                $brandLogo = get_logo_path('footer-logo.png');
                if ($brandLogo):
                ?>
                    <img src="<?php echo $brandLogo; ?>" alt="<?php echo APP_NAME; ?>" style="height: 44px; width: auto; max-width: 170px; display: block;">
                <?php else: ?>
                    <i class="fas fa-cut me-2"></i>
                    <?php echo APP_NAME; ?>
                <?php endif; ?>
            </a>
            <button type="button" class="btn text-white p-1" id="sidebarClose" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a href="categories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i>
                        Category
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="cloth-types.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cloth-types.php' && !isset($_GET['view']) ? 'active' : ''; ?>">
                        <i class="fas fa-tshirt"></i>
                        Cloth type
                    </a>
                </li>
                <li class="nav-item">
                    <a href="measurements.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'measurements.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ruler"></i>
                        Measurement
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customers.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['customers.php', 'customer-details.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        Orders
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="invoices.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i>
                        Invoices
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="expenses.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>">
                        <i class="fas fa-receipt"></i>
                        Expenses
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        Reports
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="contacts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i>
                        Contacts
                    </a>
                </li>
                
                <?php if (has_role('admin')): ?>
                <li class="nav-item">
                    <a href="company-settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'company-settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i>
                        Company Settings
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i>
                        User Management
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="subscriptions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'subscriptions.php' ? 'active' : ''; ?>">
                        <i class="fas fa-crown"></i>
                        Subscriptions
                    </a>
                </li>
                
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <div class="main-content">
        <header class="top-header">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-link p-1 text-primary d-lg-none" id="sidebarToggle" aria-label="Toggle Navigation" style="font-size: 1.25rem; text-decoration: none; color: #4f46e5 !important; line-height: 1;">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0 page-main-title fw-bold text-dark" style="text-transform: capitalize; font-size: 1.25rem; letter-spacing: -0.3px;">
                    <?php 
                        $displayPageTitle = str_ireplace([' Management', ' management'], '', $page_title ?? 'Dashboard');
                        echo htmlspecialchars(ucwords(__t(strtolower(str_replace('_', ' ', $displayPageTitle))))); 
                    ?>
                </h4>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                
                <div class="d-none d-md-block">
                    <?php include __DIR__ . '/../../lang/language_switcher.php'; ?>
                </div>
                <div class="dropdown">
                    <button class="notif-bell-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($notifCount > 0): ?>
                            <span class="notif-dot"></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 12px; min-width: 280px; max-width: 320px; z-index: 1060;">
                        <li class="d-flex justify-content-between align-items-center px-2 py-1 mb-1">
                            <h6 class="mb-0 fw-bold" style="font-size: 0.9rem; color: #1e293b;">Notifications</h6>
                            <?php if ($notifCount > 0): ?>
                                <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem;"><?php echo $notifCount; ?> new</span>
                            <?php endif; ?>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <?php if (!empty($notifItems)): ?>
                            <?php foreach ($notifItems as $nItem): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-start gap-2 p-2 rounded" href="<?php echo $nItem['link']; ?>" style="white-space: normal;">
                                    <i class="<?php echo $nItem['icon']; ?> mt-1"></i>
                                    <div>
                                        <div class="fw-semibold text-dark" style="font-size: 0.82rem;"><?php echo htmlspecialchars($nItem['title']); ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($nItem['desc']); ?></div>
                                    </div>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-center py-3 text-muted" style="font-size: 0.8rem;">
                                <i class="fas fa-check-circle text-success mb-1 d-block" style="font-size: 1.2rem;"></i>
                                All caught up! No urgent alerts.
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="dropdown">
                    <?php
                    $name = $current_user['name'];
                    $nameParts = explode(' ', trim($name));
                    $initials = '';
                    if (count($nameParts) >= 2) {
                        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($name, 0, 2));
                    }
                    $firstName = $nameParts[0] ?? $name;
                    $hasHeaderImage = !empty($current_user['profile_image']) && file_exists(__DIR__ . '/../../' . $current_user['profile_image']);
                    ?>
                    <button class="user-pill-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar-circle">
                            <?php if ($hasHeaderImage): ?>
                                <img src="../<?php echo htmlspecialchars($current_user['profile_image']); ?>" alt="<?php echo htmlspecialchars($initials); ?>">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="user-pill-name d-none d-sm-inline"><?php echo htmlspecialchars($firstName); ?></span>
                        <i class="fas fa-chevron-down user-pill-chevron ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 12px; min-width: 200px; z-index: 1060;">
                        <li class="px-3 py-2">
                            <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($current_user['name']); ?></div>
                            <small class="text-muted text-capitalize" style="font-size: 0.75rem;"><?php echo htmlspecialchars($current_user['role']); ?></small>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                        <li><a class="dropdown-item py-2" href="change-password.php"><i class="fas fa-key me-2 text-primary"></i> Change Password</a></li>
                        <?php if (has_role('admin')): ?>
                            <li><a class="dropdown-item py-2" href="company-settings.php"><i class="fas fa-cog me-2 text-primary"></i> Settings</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="content-area">
        <script>
        document.addEventListener('DOMContentLoaded', function() {
       
            const fontLink = document.createElement('link');
            fontLink.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';
            fontLink.rel = 'stylesheet';
            document.head.appendChild(fontLink);
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggle');
            const sidebarCloseBtn = document.getElementById('sidebarClose');

            function openSidebar() {
                sidebar?.classList.add('show');
                document.body.classList.add('sidebar-open');
            }

            function closeSidebar() {
                sidebar?.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }

            sidebarToggleBtn?.addEventListener('click', openSidebar);
            sidebarCloseBtn?.addEventListener('click', closeSidebar);
            const selects = document.querySelectorAll('.form-select');
            
            selects.forEach(select => {
                select.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.1)';
                });
                
                select.addEventListener('mouseleave', function() {
                    if (document.activeElement !== this) {
                        this.style.transform = '';
                        this.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
                    }
                });
                select.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08)';
                    this.parentElement.classList.add('focused');
                });
                
                select.addEventListener('blur', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
                    this.parentElement.classList.remove('focused');
                });
                select.addEventListener('mousedown', function() {
                    this.style.transform = 'translateY(-1px)';
                    this.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
                });
                
                select.addEventListener('mouseup', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08)';
                });
                select.addEventListener('change', function() {
                 
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = 'translateY(-2px)';
                    }, 100);
                    const ripple = document.createElement('div');
                    ripple.style.cssText = `
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        width: 20px;
                        height: 20px;
                        background: rgba(102, 126, 234, 0.3);
                        border-radius: 50%;
                        transform: translate(-50%, -50%) scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                        z-index: 10;
                    `;
                    
                    this.style.position = 'relative';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    0% {
                        transform: translate(-50%, -50%) scale(0);
                        opacity: 1;
                    }
                    100% {
                        transform: translate(-50%, -50%) scale(4);
                        opacity: 0;
                    }
                }
                .form-select option {
                    font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif !important;
                    font-weight: 500 !important;
                    font-size: 0.95rem !important;
                    letter-spacing: 0.01em !important;
                    line-height: 1.6 !important;
                    padding: 0.875rem 1.25rem !important;
                    margin: 0.125rem 0.25rem !important;
                    border-radius: 8px !important;
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                }
                
                .form-select option:hover,
                select option:hover {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                    color: #ffffff !important;
                    transform: translateX(4px) !important;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25) !important;
                    font-weight: 600 !important;
                }
                
                .form-select option {
                    color: #000000;
                    background-color: #ffffff;
                }
                
                .form-select option:checked,
                .form-select option:focus,
                .form-select option[selected] {
                    background-color: #667eea !important;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                    color: #000000ff !important;
                    font-weight: 600 !important;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
                }
                
                /* Enhanced dropdown container */
                .dropdown-container {
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                }
                
                .dropdown-container:hover {
                    transform: translateY(-1px);
                }
                .dropdown-container.focused {
                    transform: translateY(-1px);
                }
                
                .dropdown-container.focused .form-select {
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08) !important;
                }
            `;
            document.head.appendChild(style);
        });
        </script>