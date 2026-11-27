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
if (has_role('tailor')) {
    $tailorAllowedPages = ['orders.php', 'order-details.php', 'profile.php', 'change-password.php', 'logout.php'];
    $currentScript = basename($_SERVER['PHP_SELF']);
    if (!in_array($currentScript, $tailorAllowedPages)) {
        smart_redirect('orders.php');
        exit;
    }
}

require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT profile_image, role, full_name, username, company_id FROM users WHERE id = :id");
$userId = $current_user['id'];
$stmt->bindParam(':id', $userId);
$stmt->execute();
$headerUserData = $stmt->fetch(PDO::FETCH_ASSOC);
if (empty($headerUserData)) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if (!headers_sent() && ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    smart_redirect('login.php?error=account_deleted');
    exit;
}
$current_user['profile_image'] = $headerUserData['profile_image'] ?? null;
if (!empty($headerUserData)) {
    if (empty($current_user['role']) && !empty($headerUserData['role'])) {
        $current_user['role'] = $headerUserData['role'];
        $_SESSION['user_role'] = $headerUserData['role'];
    }
    if ((empty($current_user['name']) || $current_user['name'] === 'User') && !empty($headerUserData['full_name'])) {
        $current_user['name'] = $headerUserData['full_name'];
        $_SESSION['user_name'] = $headerUserData['full_name'];
    }
    if (empty($current_user['username']) && !empty($headerUserData['username'])) {
        $current_user['username'] = $headerUserData['username'];
        $_SESSION['username'] = $headerUserData['username'];
    }
    if (empty($_SESSION['company_id']) && !empty($headerUserData['company_id'])) {
        $_SESSION['company_id'] = $headerUserData['company_id'];
    }
}
require_once __DIR__ . '/../../helpers/NotificationService.php';
$notifService = new NotificationService($db);
$unreadNotifCount = 0;
$recentNotifs = [];
if (!empty($current_user['id'])) {
    $headerCompanyId = function_exists('get_company_id') ? get_company_id() : null;
    $unreadNotifCount = $notifService->getUnreadCount($current_user['id'], $headerCompanyId);
    $recentNotifs = $notifService->getRecentNotifications($current_user['id'], $headerCompanyId, 12);
}
$notifCount = $unreadNotifCount;
if (!function_exists('render_page_header')) {
    function render_page_header($title = null, $subtitle = null) {
        $pageHeaderTitle = $title;
        $pageHeaderSubtitle = $subtitle;
        include __DIR__ . '/page-header.php';
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
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --sidebar-width: 236px;
            --header-height: 62px;
        }
        html, body {
            max-width: 100%;
            overflow-x: hidden;
            overflow-x: clip;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .min-w-0 {
            min-width: 0 !important;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            height: 100dvh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #4f46e5 0%, #4338ca 100%);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }
        .sidebar-header {
            padding: 1rem 1.1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            min-height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .sidebar-brand {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-align: center;
        }
        .sidebar-logo-img {
            height: 52px;
            width: auto;
            max-width: 185px;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.25));
            transition: transform 0.2s ease;
        }
        .sidebar-logo-img:hover {
            transform: scale(1.03);
        }
        #sidebarClose {
            display: none;
        }
        
        .sidebar-nav {
            padding: 0.75rem 0;
        }
        
        .nav-item {
            margin: 0.15rem 0.65rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            padding: 0.52rem 0.85rem;
            border-radius: 10px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.86rem;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link:focus {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.12) !important;
            transform: translateX(3px);
        }

        .sidebar .nav-link.active {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.18) !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 998;
            transition: left 0.3s ease;
        }

        /* Global Header Search */
        .global-search-container {
            width: 350px;
        }
        .global-search-container input:focus,
        #mobileGlobalSearchInput:focus {
            background-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15), 0 2px 8px rgba(0,0,0,0.06) !important;
            border: 1px solid #c7d2fe !important;
        }
        .search-result-item {
            transition: all 0.15s ease-in-out;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .search-result-item:hover, .search-result-item.active {
            background-color: #f8fafc !important;
            border-color: #e2e8f0;
            transform: translateX(2px);
        }
        .global-search-results::-webkit-scrollbar {
            width: 5px;
        }
        .global-search-results::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .global-search-results::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .global-search-results::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .content-area {
            padding: 1rem;
            padding-top: calc(var(--header-height) + 1rem);
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
        .dropdown-menu .dropdown-item.active,
        .dropdown-menu .dropdown-item:active,
        .dropdown-menu .dropdown-item.active:hover,
        .dropdown-menu .dropdown-item.active:focus,
        .dropdown-item.active,
        .dropdown-item:active,
        .dropdown-item.active:hover,
        .dropdown-item.active:focus {
            background: #4f46e5 !important;
            background-color: #4f46e5 !important;
            color: #ffffff !important;
        }
        .dropdown-menu .dropdown-item.active i,
        .dropdown-menu .dropdown-item:active i,
        .dropdown-item.active i,
        .dropdown-item:active i {
            color: #ffffff !important;
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
            color: #9ca3af !important;
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
            color: #9ca3af !important;
            font-weight: 400;
            opacity: 1;
        }
        .form-select:disabled,
        .form-select[disabled] {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2394a3b8'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e"), linear-gradient(145deg, #f8fafc, #f1f5f9) !important;
            background-position: right 0.875rem center, center !important;
            background-size: 18px 18px, 100% 100% !important;
            background-repeat: no-repeat, no-repeat !important;
            border-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            cursor: not-allowed !important;
            transform: none !important;
            box-shadow: none !important;
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
            z-index: 1045;
            opacity: 0;
            visibility: hidden;
            touch-action: none;
            overscroll-behavior: contain;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        html.sidebar-open,
        body.sidebar-open,
        html:has(body.sidebar-open) {
            overflow: hidden !important;
            overscroll-behavior: none !important;
        }
        body.sidebar-open .main-content,
        body.sidebar-open .content-area {
            overflow: hidden !important;
            touch-action: none !important;
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
                bottom: 0;
                height: auto;
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
                max-width: 100% !important;
                overflow-x: hidden;
                overflow-x: clip;
            }
            .top-header {
                left: 0 !important;
                padding: 0 12px !important;
                position: fixed;
                top: 0;
                right: 0 !important;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                height: 58px;
                background: #ffffff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            }
            .top-header > div {
                min-width: 0;
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
            .mobile-header-logo {
                display: inline-flex;
                align-items: center;
                text-decoration: none;
            }
            .mobile-header-logo img {
                height: 34px;
                width: auto;
                max-width: 140px;
                object-fit: contain;
                display: block;
            }
            .content-area {
                padding: 12px 12px 68px !important;
                padding-top: calc(var(--header-height) + 12px) !important;
                max-width: 100%;
                overflow-x: hidden;
                overflow-x: clip;
            }
        }

        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            background: #ef4444;
            color: #ffffff;
            font-size: 0.62rem;
            font-weight: 700;
            line-height: 16px;
            text-align: center;
            border-radius: 999px;
            border: 1.5px solid #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            height: 60px;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
            box-shadow: 0 -3px 12px rgba(0, 0, 0, 0.05);
            z-index: 1040;
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 4px 6px;
        }
        .mobile-bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            text-decoration: none;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 500;
            transition: color 0.15s ease;
            padding: 4px 0;
            border: none;
            background: transparent;
        }
        .mobile-bottom-nav-item i {
            font-size: 1.15rem;
            margin-bottom: 3px;
        }
        .mobile-bottom-nav-item.active {
            color: #7c3aed !important;
            font-weight: 700;
            background: #f5f3ff;
            border-radius: 14px;
        }
        .mobile-bottom-nav-item:hover, .mobile-bottom-nav-item:focus {
            color: #7c3aed;
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
            .row:has(> [class*="col"] > .stat-card) > [class*="col"]:last-child:nth-child(odd) {
                grid-column: 1 / -1 !important;
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
        /* Global Page Hero Banner Card (Standardized from Category page) */
        .cat-hero-card {
            border-radius: 18px;
            background: #faf5ff;
            border: 1px solid #ede9fe;
            min-height: 180px;
            position: relative;
            overflow: hidden;
        }
        @media (max-width: 767.98px) {
            .cat-hero-card {
                min-height: 120px;
            }
        }
        .notif-bell-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            flex-shrink: 0;
            padding: 0;
            font-size: 0.95rem;
        }
        .notif-bell-btn:hover {
            background: #f1f5f9;
            color: #4f46e5;
            transform: translateY(-1px);
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

        .notif-dropdown-menu {
            border-radius: 14px !important;
            width: 360px !important;
            max-width: calc(100vw - 24px) !important;
            min-width: 340px !important;
            z-index: 1060;
            padding: 8px 10px !important;
            box-shadow: 0 12px 30px -4px rgba(16, 24, 40, 0.12), 0 4px 10px -2px rgba(16, 24, 40, 0.05) !important;
        }
        .notif-header-row {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 4px 6px 8px 6px !important;
            margin-bottom: 6px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            gap: 10px !important;
            flex-wrap: nowrap !important;
        }
        .notif-header-left {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            flex-shrink: 0 !important;
            white-space: nowrap !important;
        }
        .notif-header-right {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            flex-shrink: 0 !important;
            white-space: nowrap !important;
            margin-left: auto !important;
        }
        .notif-header-right a {
            white-space: nowrap !important;
            text-decoration: none !important;
            font-size: 0.72rem !important;
            font-weight: 500 !important;
        }
        #notifHeaderCountBadge {
            font-size: 0.68rem !important;
            font-weight: 600 !important;
            padding: 2px 7px !important;
            white-space: nowrap !important;
            line-height: 1.3 !important;
        }
        @media (max-width: 420px) {
            .notif-dropdown-menu {
                width: calc(100vw - 20px) !important;
                min-width: min(290px, calc(100vw - 20px)) !important;
            }
            .notif-header-left h6 {
                font-size: 0.82rem !important;
            }
            .notif-header-right {
                gap: 5px !important;
            }
        }

        #enablePushBtn {
            width: 38px !important;
            height: 38px !important;
            min-width: 38px !important;
            max-width: 38px !important;
            border-radius: 50% !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 0.92rem !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
            white-space: nowrap !important;
            cursor: pointer !important;
            flex-shrink: 0 !important;
            border: 1px solid #c7d2fe !important;
            box-sizing: border-box !important;
        }
        #enablePushBtn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        #enablePushBtn.btn-primary {
            background-color: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: #ffffff !important;
        }
        #enablePushBtn.btn-outline-primary {
            background-color: #eef2ff !important;
            border-color: #c7d2fe !important;
            color: #4338ca !important;
        }
        #enablePushBtn.btn-outline-danger {
            background-color: #fef2f2 !important;
            border-color: #fecaca !important;
            color: #dc2626 !important;
        }
        #enablePushBtn.btn-outline-secondary {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            color: #475569 !important;
        }
        #enablePushBtn.btn-outline-success {
            background-color: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
            color: #059669 !important;
        }

        #mobileSearchToggle {
            width: 38px !important;
            height: 38px !important;
            min-width: 38px !important;
            max-width: 38px !important;
            border-radius: 50% !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 0.92rem !important;
            color: #4f46e5 !important;
            background: #eef2ff !important;
            border: 1px solid #c7d2fe !important;
            flex-shrink: 0 !important;
            transition: all 0.2s ease !important;
            box-sizing: border-box !important;
        }
        @media (min-width: 992px) {
            #mobileSearchToggle {
                display: none !important;
            }
        }
        #mobileSearchToggle:hover {
            background: #e0e7ff !important;
            transform: translateY(-1px);
        }

        .user-pill-btn {
            background: transparent;
            border: none;
            padding: 2px 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            border-radius: 38px;
            transition: background 0.2s ease;
            flex-shrink: 0;
            height: 38px;
        }
        .user-pill-btn:hover, .user-pill-btn:focus {
            background: #f8fafc;
        }
        .user-avatar-circle {
            width: 38px;
            height: 38px;
            min-width: 38px;
            max-width: 38px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
            flex-shrink: 0;
            box-sizing: border-box;
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
        <div class="sidebar-header position-relative d-flex align-items-center justify-content-center py-2">
            <a href="<?php echo has_role('tailor') ? 'orders.php' : 'dashboard.php'; ?>" class="sidebar-brand d-flex align-items-center justify-content-center text-decoration-none w-100">
                <img src="../assets/images/tailor_logo.png" alt="Tailor Pro" class="sidebar-logo-img">
            </a>
            <button type="button" class="btn text-white p-1 position-absolute end-0 me-3" id="sidebarClose" aria-label="Close Sidebar" style="top: 50%; transform: translateY(-50%);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <?php if (!has_role('tailor')): ?>
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
                <?php endif; ?>

                <li class="nav-item">
                    <a href="orders.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        Orders
                    </a>
                </li>
                
                <?php if (!has_role('tailor')): ?>
                
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
                
                <li class="px-3 mt-3 mb-3">
                    <div class="position-relative overflow-hidden text-white d-flex flex-column justify-content-between"
                         style="background: linear-gradient(135deg, rgba(42, 28, 110, 0.95) 0%, rgba(52, 32, 125, 0.88) 38%, rgba(62, 32, 135, 0.35) 72%, transparent 100%),
                                url('../assets/images/dashboard/tailor_pro_bg.jpg') no-repeat 78% center / cover;
                                border-radius: 18px;
                                min-height: 185px;
                                padding: 18px 16px 16px 16px;
                                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);">

                        <div>
                            <div class="d-flex align-items-center justify-content-center"
                                 style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); box-shadow: 0 4px 12px rgba(124, 58, 237, 0.4);">
                                <i class="fas fa-crown text-white" style="font-size: 1.05rem;"></i>
                            </div>
                            <div class="fw-bold text-white mt-2 mb-1" style="font-size: 1.25rem; letter-spacing: -0.2px;">
                                Tailor Pro
                            </div>
                            <p class="mb-0" style="font-size: 0.94rem; color: rgba(255, 255, 255, 0.9); line-height: 1.35; max-width: 155px;">
                                Manage your tailoring<br>business with ease!
                            </p>
                        </div>
                        <div class="mt-3">
                            <a href="subscriptions.php"
                               class="d-flex align-items-center justify-content-center gap-2 w-100 text-white text-decoration-none fw-semibold"
                               style="background: #1c1a44;
                                      border: 1px solid rgba(255, 255, 255, 0.08);
                                      border-radius: 12px;
                                      padding: 10px 14px;
                                      font-size: 1.02rem;
                                      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
                                      transition: all 0.2s ease;"
                               onmouseover="this.style.background='#252358'; this.style.transform='translateY(-1px)';"
                               onmouseout="this.style.background='#1c1a44'; this.style.transform='translateY(0)';">
                                <span>Upgrade Plan</span>
                                <i class="fas fa-arrow-right" style="font-size: 0.85rem;"></i>
                            </a>
                        </div>
                    </div>
                </li>
                
                <?php endif; ?>
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
                <?php 
                $currScript = basename($_SERVER['PHP_SELF']);
                if (in_array($currScript, ['dashboard.php', 'dashboard'])): 
                    $headerSearchVal = htmlspecialchars($_GET['search'] ?? '');
                ?>
                    <a href="dashboard" class="mobile-header-logo d-inline-flex align-items-center d-lg-none" aria-label="Dashboard">
                        <img src="../assets/images/tailor_logo_color.png?v=<?php echo time(); ?>" alt="Tailor Pro">
                    </a>
                    <div class="position-relative d-none d-lg-block global-search-container" style="width: 340px;">

                        <form id="globalSearchForm" action="dashboard" method="GET" class="m-0" onsubmit="return handleGlobalSearchSubmit(event, 'desktop');">
                            <div class="position-relative">
                                <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.8rem; pointer-events: none;"></i>
                                <input type="text" 
                                       id="globalSearchInput" 
                                       name="search" 
                                       value="<?php echo $headerSearchVal; ?>"
                                       class="form-control rounded-pill ps-5 pe-5 py-2 border-0" 
                                       style="font-size: 0.82rem; background-color: #f1f5f9 !important; color: #334155; transition: all 0.2s;" 
                                       placeholder="Search orders, customers, categories..." 
                                       autocomplete="off"
                                       aria-label="Search orders, customers, categories">
                                <span id="globalSearchSpinner" class="spinner-border spinner-border-sm text-primary position-absolute top-50 end-0 translate-middle-y me-3 d-none" style="width: 0.82rem; height: 0.82rem; pointer-events: none;" role="status"></span>
                                <button type="button" id="globalSearchClear" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted p-0 me-3 border-0 <?php echo empty($headerSearchVal) ? 'd-none' : ''; ?>" style="line-height: 1; font-size: 0.85rem; text-decoration: none;" title="Clear search">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                        </form>
                        <div id="globalSearchResults" class="dropdown-menu shadow-lg border-0 p-0 global-search-results" style="display: none; position: absolute; top: calc(100% + 8px); left: 0; width: 440px; border-radius: 14px; z-index: 1060; max-height: 480px; overflow-y: auto; background: #ffffff; border: 1px solid #edf2f7 !important;"></div>
                    </div>
                <?php elseif (in_array($currScript, ['categories.php', 'cloth-types.php', 'measurements.php', 'customers.php', 'orders.php', 'invoices.php', 'expenses.php', 'reports.php', 'contacts.php', 'company-settings.php', 'users.php'])): ?>
                    <a href="dashboard.php" class="mobile-header-logo d-inline-flex align-items-center d-lg-none" aria-label="Dashboard">
                        <img src="../assets/images/tailor_logo_color.png?v=<?php echo time(); ?>" alt="Tailor Pro">
                    </a>
                    <div class="d-none d-lg-block">
                        <h4 class="mb-0 page-main-title fw-bold text-dark" style="text-transform: capitalize; font-size: 1.25rem; letter-spacing: -0.3px;">
                            <?php 
                                $displayPageTitle = str_ireplace([' Management', ' management'], '', $page_title ?? 'Cloth Type');
                                echo htmlspecialchars(ucwords(__t(strtolower(str_replace('_', ' ', $displayPageTitle))))); 
                            ?>
                        </h4>
                    </div>
                <?php else: ?>
                    <h4 class="mb-0 page-main-title fw-bold text-dark" style="text-transform: capitalize; font-size: 1.25rem; letter-spacing: -0.3px;">
                        <?php 
                            $displayPageTitle = str_ireplace([' Management', ' management'], '', $page_title ?? 'Dashboard');
                            echo htmlspecialchars(ucwords(__t(strtolower(str_replace('_', ' ', $displayPageTitle))))); 
                        ?>
                    </h4>
                <?php endif; ?>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <?php if (in_array($currScript, ['dashboard.php', 'dashboard'])): ?>
                    <button type="button" id="mobileSearchToggle" class="btn btn-sm btn-light rounded-circle d-lg-none" title="Search" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                <?php endif; ?>

                <div class="d-none d-md-block">
                    <?php include __DIR__ . '/../../lang/language_switcher.php'; ?>
                </div>
                <div class="dropdown">
                    <button class="notif-bell-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" style="<?php echo ($unreadNotifCount > 0) ? '' : 'display: none;'; ?>"><?php echo $unreadNotifCount; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 notif-dropdown-menu">
                        <li class="notif-header-row">
                            <div class="notif-header-left">
                                <h6 class="mb-0 fw-bold" style="font-size: 0.9rem; color: #1e293b; white-space: nowrap;">Notifications</h6>
                                <span id="notifHeaderCountBadge" class="badge bg-danger rounded-pill" style="<?php echo ($unreadNotifCount > 0) ? 'display: inline-block;' : 'display: none;'; ?>"><?php echo $unreadNotifCount; ?> new</span>
                            </div>
                            <div class="notif-header-right">


                                <a href="#" onclick="window.markAllNotificationsRead(event)" class="text-primary" style="white-space: nowrap;">Mark all read</a>
                            </div>
                        </li>
                        <div id="notifDropdownItems" style="max-height: 360px; overflow-y: auto;">
                            <?php if (!empty($recentNotifs)): ?>
                                <?php foreach ($recentNotifs as $nItem): 
                                    $isUnread = ((int)$nItem['is_read'] === 0);
                                    $bgClass = $isUnread ? 'bg-light-subtle fw-medium' : '';
                                    $unreadDot = $isUnread ? '<span class="rounded-circle bg-primary d-inline-block ms-auto" style="width: 7px; height: 7px;"></span>' : '';
                                    $link = !empty($nItem['link']) ? $nItem['link'] : 'orders.php';
                                ?>
                                <li class="notif-item-row" data-id="<?php echo (int)$nItem['id']; ?>">
                                    <a class="dropdown-item d-flex align-items-start gap-2 p-2 rounded <?php echo $bgClass; ?>" href="<?php echo htmlspecialchars((string)$link); ?>" style="white-space: normal;" onclick="window.markNotifRead(<?php echo (int)$nItem['id']; ?>, event, '<?php echo htmlspecialchars((string)$link); ?>')">
                                        <i class="<?php echo htmlspecialchars((string)($nItem['icon'] ?? 'fas fa-bell')); ?> mt-1" style="font-size: 1rem;"></i>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;"><?php echo htmlspecialchars((string)($nItem['title'] ?? 'Notification')); ?></div>
                                                <?php echo $unreadDot; ?>
                                            </div>
                                            <div class="text-muted text-break" style="font-size: 0.74rem; line-height: 1.35;"><?php echo htmlspecialchars((string)($nItem['message'] ?? '')); ?></div>
                                            <div class="text-secondary mt-1" style="font-size: 0.68rem;"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars((string)($nItem['time_ago'] ?? 'Recent')); ?></div>
                                        </div>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="text-center py-4 text-muted" style="font-size: 0.85rem;">
                                    <i class="fas fa-check-circle text-success mb-2 d-block" style="font-size: 1.5rem;"></i>
                                    All caught up! No notifications.
                                </li>
                            <?php endif; ?>
                        </div>
                    </ul>
                </div>
                <div class="dropdown">
                    <?php
                    $name = (string)($current_user['name'] ?? $current_user['username'] ?? 'User');
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
                    <button class="user-pill-btn dropdown-toggle p-0 p-sm-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar-circle">
                            <?php if ($hasHeaderImage): ?>
                                <img src="../<?php echo htmlspecialchars((string)$current_user['profile_image']); ?>" alt="<?php echo htmlspecialchars((string)$initials); ?>">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars((string)$initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="user-pill-name d-none d-sm-inline"><?php echo htmlspecialchars((string)$firstName); ?></span>
                        <i class="fas fa-chevron-down user-pill-chevron ms-1 d-none d-sm-inline"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 12px; min-width: 200px; z-index: 1060;">
                        <li class="px-3 py-2">
                            <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars((string)($current_user['name'] ?? 'User')); ?></div>
                            <small class="text-muted text-capitalize" style="font-size: 0.75rem;"><?php echo htmlspecialchars((string)($current_user['role'] ?? '')); ?></small>
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
        <?php if (in_array($currScript, ['dashboard.php', 'dashboard'])): 
            $mobileSearchVal = htmlspecialchars($_GET['search'] ?? '');
        ?>
            <div id="mobileSearchDrawer" class="d-lg-none bg-white border-bottom px-3 py-2 position-fixed start-0 end-0 shadow-sm" style="display: none; top: 58px; z-index: 1050;">
                <form id="mobileGlobalSearchForm" action="dashboard" method="GET" class="m-0 position-relative" onsubmit="return handleGlobalSearchSubmit(event, 'mobile');">
                    <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.8rem; pointer-events: none;"></i>
                    <input type="text" 
                           id="mobileGlobalSearchInput" 
                           name="search" 
                           value="<?php echo $mobileSearchVal; ?>"
                           class="form-control rounded-pill ps-5 pe-5 py-2 border-0" 
                           style="font-size: 0.85rem; background-color: #f1f5f9 !important; color: #334155;" 
                           placeholder="Search orders, customers, categories..." 
                           autocomplete="off">
                    <span id="mobileGlobalSearchSpinner" class="spinner-border spinner-border-sm text-primary position-absolute top-50 end-0 translate-middle-y me-3 d-none" style="width: 0.82rem; height: 0.82rem; pointer-events: none;" role="status"></span>
                    <button type="button" id="mobileGlobalSearchClear" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted p-0 me-3 border-0 <?php echo empty($mobileSearchVal) ? 'd-none' : ''; ?>" style="line-height: 1; font-size: 0.85rem;" title="Clear">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </form>
                <div id="mobileGlobalSearchResults" class="dropdown-menu shadow-lg border-0 p-0 w-100 position-relative mt-2" style="display: none; border-radius: 14px; max-height: 380px; overflow-y: auto; background: #ffffff; border: 1px solid #edf2f7 !important;"></div>
            </div>
        <?php endif; ?>
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
                document.getElementById('sidebarOverlay')?.classList.add('show');
                document.documentElement.classList.add('sidebar-open');
                document.body.classList.add('sidebar-open');
            }

            function closeSidebar() {
                sidebar?.classList.remove('show');
                document.getElementById('sidebarOverlay')?.classList.remove('show');
                document.documentElement.classList.remove('sidebar-open');
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

            // Global Header Search
            window.handleGlobalSearchSubmit = function(e, mode) {
                const resultsId = mode === 'mobile' ? 'mobileGlobalSearchResults' : 'globalSearchResults';
                const inputId = mode === 'mobile' ? 'mobileGlobalSearchInput' : 'globalSearchInput';
                const results = document.getElementById(resultsId);
                const input = document.getElementById(inputId);
                if (!input) return true;
                const query = input.value.trim();
                if (!query) {
                    e.preventDefault();
                    return false;
                }
                const activeItem = results ? results.querySelector('.search-result-item.active') : null;
                if (activeItem && activeItem.getAttribute('href')) {
                    e.preventDefault();
                    window.location.href = activeItem.getAttribute('href');
                    return false;
                }
                return true;
            };

            function initGlobalSearch(inputId, resultsId, clearId, spinnerId) {
                const input = document.getElementById(inputId);
                const results = document.getElementById(resultsId);
                const clearBtn = document.getElementById(clearId);
                const spinner = document.getElementById(spinnerId);
                if (!input || !results) return;

                let debounceTimer = null;
                let activeIndex = -1;
                let lastQuery = '';

                function getStatusBadge(status) {
                    const s = (status || '').toLowerCase();
                    switch (s) {
                        case 'completed': return { badge: 'bg-success text-white', label: 'Completed' };
                        case 'in_progress': return { badge: 'bg-info text-dark', label: 'In Progress' };
                        case 'delivered': return { badge: 'bg-primary text-white', label: 'Delivered' };
                        case 'cancelled': return { badge: 'bg-danger text-white', label: 'Cancelled' };
                        case 'pending': default: return { badge: 'bg-warning text-dark', label: 'Pending' };
                    }
                }

                function escapeHtml(str) {
                    if (!str) return '';
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function renderResults(data, query) {
                    activeIndex = -1;
                    const orders = data.results?.orders || [];
                    const customers = data.results?.customers || [];
                    const categories = data.results?.categories || [];
                    const clothTypes = data.results?.cloth_types || [];
                    const total = (data.total_matches !== undefined) ? data.total_matches : (orders.length + customers.length + categories.length + clothTypes.length);

                    if (total === 0) {
                        results.innerHTML = `
                            <div class="p-4 text-center">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 44px; height: 44px; background: #f8fafc; color: #94a3b8; font-size: 1.1rem; border: 1px solid #e2e8f0;">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="fw-semibold text-dark" style="font-size: 0.85rem;">No results found</div>
                                <div class="text-muted mt-1" style="font-size: 0.74rem;">No matches found for "<span class="text-dark fw-medium">${escapeHtml(query)}</span>"</div>
                                <div class="mt-3 pt-2 border-top d-flex justify-content-center gap-2">
                                    <a href="dashboard?search=${encodeURIComponent(query)}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size: 0.72rem;">Search on Dashboard</a>
                                    <a href="orders?search=${encodeURIComponent(query)}" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="font-size: 0.72rem;">Search Orders</a>
                                </div>
                            </div>
                        `;
                        results.style.display = 'block';
                        return;
                    }

                    let html = '<div class="p-2">';

                    // Orders
                    if (orders.length > 0) {
                        html += `
                            <div class="px-2 pt-2 pb-1 text-uppercase text-muted fw-bold d-flex align-items-center justify-content-between" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                <span><i class="fas fa-shopping-bag me-1 text-primary"></i> Orders (${orders.length})</span>
                                <a href="orders.php?search=${encodeURIComponent(query)}" class="text-primary text-decoration-none" style="font-size: 0.68rem;">View all</a>
                            </div>
                            <div class="mb-2">
                        `;
                        orders.forEach(ord => {
                            const st = getStatusBadge(ord.status);
                            html += `
                                <a href="${escapeHtml(ord.url)}" class="dropdown-item search-result-item d-flex align-items-center justify-content-between p-2 rounded-3 text-decoration-none">
                                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #eff6ff; color: #3b82f6; font-size: 0.8rem; flex-shrink: 0;">
                                            <i class="fas fa-receipt"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;">${escapeHtml(ord.order_number)} <span class="fw-normal text-muted" style="font-size: 0.75rem;">· ${escapeHtml(ord.customer_name)}</span></div>
                                            <div class="text-muted text-truncate" style="font-size: 0.72rem;">${escapeHtml(ord.cloth_type_name ? ord.cloth_type_name + ' · ' : '')}${escapeHtml(ord.order_date || '')}</div>
                                        </div>
                                    </div>
                                    <div class="text-end ps-2 flex-shrink-0">
                                        <span class="badge ${st.badge}" style="font-size: 0.65rem; font-weight: 500;">${st.label}</span>
                                        <div class="fw-bold text-dark mt-1" style="font-size: 0.75rem;">${escapeHtml(ord.formatted_amount)}</div>
                                    </div>
                                </a>
                            `;
                        });
                        html += '</div>';
                    }

                    // Customers
                    if (customers.length > 0) {
                        html += `
                            <div class="px-2 pt-2 pb-1 text-uppercase text-muted fw-bold d-flex align-items-center justify-content-between" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                <span><i class="fas fa-users me-1 text-success"></i> Customers (${customers.length})</span>
                                <a href="customers.php?search=${encodeURIComponent(query)}" class="text-success text-decoration-none" style="font-size: 0.68rem;">View all</a>
                            </div>
                            <div class="mb-2">
                        `;
                        customers.forEach(cust => {
                            html += `
                                <a href="${escapeHtml(cust.url)}" class="dropdown-item search-result-item d-flex align-items-center justify-content-between p-2 rounded-3 text-decoration-none">
                                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #f0fdf4; color: #10b981; font-size: 0.8rem; flex-shrink: 0;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;">${escapeHtml(cust.name)}</div>
                                        </div>
                                    </div>
                                    <div class="text-end ps-2 flex-shrink-0">
                                        <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">${escapeHtml(cust.customer_code)}</span>
                                        <div class="text-muted mt-1" style="font-size: 0.7rem;">${cust.order_count} ${cust.order_count === 1 ? 'order' : 'orders'}</div>
                                    </div>
                                </a>
                            `;
                        });
                        html += '</div>';
                    }

                    // Categories
                    if (categories.length > 0) {
                        html += `
                            <div class="px-2 pt-2 pb-1 text-uppercase text-muted fw-bold d-flex align-items-center justify-content-between" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                <span><i class="fas fa-folder me-1 text-warning"></i> Categories (${categories.length})</span>
                                <a href="categories.php?search=${encodeURIComponent(query)}" class="text-warning text-decoration-none" style="font-size: 0.68rem;">View all</a>
                            </div>
                            <div class="mb-2">
                        `;
                        categories.forEach(cat => {
                            html += `
                                <a href="${escapeHtml(cat.url)}" class="dropdown-item search-result-item d-flex align-items-center justify-content-between p-2 rounded-3 text-decoration-none">
                                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #fffbeb; color: #f59e0b; font-size: 0.8rem; flex-shrink: 0;">
                                            <i class="fas fa-folder-open"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;">${escapeHtml(cat.name)}</div>
                                            <div class="text-muted text-truncate" style="font-size: 0.72rem;">${escapeHtml(cat.description || 'Category')}</div>
                                        </div>
                                    </div>
                                    <div class="text-end ps-2 flex-shrink-0">
                                        <span class="badge bg-light text-muted" style="font-size: 0.65rem;">${cat.cloth_count} items</span>
                                    </div>
                                </a>
                            `;
                        });
                        html += '</div>';
                    }

                    // Cloth Types
                    if (clothTypes.length > 0) {
                        html += `
                            <div class="px-2 pt-2 pb-1 text-uppercase text-muted fw-bold d-flex align-items-center justify-content-between" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                <span><i class="fas fa-tshirt me-1 text-info"></i> Cloth Types (${clothTypes.length})</span>
                                <a href="cloth-types.php?search=${encodeURIComponent(query)}" class="text-info text-decoration-none" style="font-size: 0.68rem;">View all</a>
                            </div>
                            <div class="mb-2">
                        `;
                        clothTypes.forEach(ct => {
                            html += `
                                <a href="${escapeHtml(ct.url)}" class="dropdown-item search-result-item d-flex align-items-center justify-content-between p-2 rounded-3 text-decoration-none">
                                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #e0f2fe; color: #0284c7; font-size: 0.8rem; flex-shrink: 0;">
                                            <i class="fas fa-tshirt"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;">${escapeHtml(ct.name)}</div>
                                            <div class="text-muted text-truncate" style="font-size: 0.72rem;">${escapeHtml(ct.category || 'Garment')}</div>
                                        </div>
                                    </div>
                                    ${ct.rate ? `<div class="fw-bold text-dark ps-2 flex-shrink-0" style="font-size: 0.75rem;">${escapeHtml(ct.rate)}</div>` : ''}
                                </a>
                            `;
                        });
                        html += '</div>';
                    }

                    html += '</div>';

                    // Quick bottom footer
                    html += `
                        <div class="p-2 border-top bg-light-subtle rounded-bottom-4 d-flex align-items-center justify-content-between" style="font-size: 0.72rem;">
                            <span class="text-muted"><i class="fas fa-info-circle me-1"></i>Press <kbd style="font-size: 0.65rem; background: #e2e8f0; color: #334155; padding: 1px 4px; border-radius: 4px;">Enter</kbd> to search</span>
                            <div class="d-flex gap-2">
                                <a href="dashboard?search=${encodeURIComponent(query)}" class="text-primary text-decoration-none fw-semibold">Dashboard &rarr;</a>
                                <a href="orders?search=${encodeURIComponent(query)}" class="text-secondary text-decoration-none fw-semibold">Orders &rarr;</a>
                            </div>
                        </div>
                    `;

                    results.innerHTML = html;
                    results.style.display = 'block';
                }

                function doSearch(val) {
                    const query = val.trim();
                    if (query.length === 0) {
                        results.style.display = 'none';
                        results.innerHTML = '';
                        if (clearBtn) clearBtn.classList.add('d-none');
                        if (spinner) spinner.classList.add('d-none');
                        return;
                    }

                    if (clearBtn) clearBtn.classList.remove('d-none');
                    if (spinner) spinner.classList.remove('d-none');

                    lastQuery = query;
                    fetch('ajax/global_search.php?q=' + encodeURIComponent(query))
                        .then(res => res.json())
                        .then(data => {
                            if (spinner) spinner.classList.add('d-none');
                            if (input.value.trim() === lastQuery) {
                                renderResults(data, query);
                            }
                        })
                        .catch(err => {
                            if (spinner) spinner.classList.add('d-none');
                            console.error('Global search error:', err);
                        });
                }

                input.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    const val = this.value;
                    debounceTimer = setTimeout(() => doSearch(val), 250);
                });

                input.addEventListener('focus', function() {
                    if (this.value.trim().length > 0 && results.innerHTML.trim().length > 0) {
                        results.style.display = 'block';
                    }
                });

                if (clearBtn) {
                    clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const hadSearchInUrl = new URLSearchParams(window.location.search).has('search');
                        input.value = '';
                        results.style.display = 'none';
                        results.innerHTML = '';
                        clearBtn.classList.add('d-none');
                        if (spinner) spinner.classList.add('d-none');
                        if (hadSearchInUrl) {
                            window.location.href = 'dashboard';
                        } else {
                            input.focus();
                        }
                    });
                }

                input.addEventListener('keydown', function(e) {
                    const items = results.querySelectorAll('.search-result-item');
                    if (items.length === 0 || results.style.display === 'none') {
                        return;
                    }

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIndex++;
                        if (activeIndex >= items.length) activeIndex = 0;
                        updateActiveItem(items);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIndex--;
                        if (activeIndex < 0) activeIndex = items.length - 1;
                        updateActiveItem(items);
                    } else if (e.key === 'Enter') {
                        if (activeIndex >= 0 && items[activeIndex]) {
                            e.preventDefault();
                            window.location.href = items[activeIndex].getAttribute('href');
                        }
                    } else if (e.key === 'Escape') {
                        results.style.display = 'none';
                    }
                });

                function updateActiveItem(items) {
                    items.forEach((item, idx) => {
                        if (idx === activeIndex) {
                            item.classList.add('active');
                            item.scrollIntoView({ block: 'nearest' });
                        } else {
                            item.classList.remove('active');
                        }
                    });
                }

                document.addEventListener('click', function(e) {
                    if (!input.contains(e.target) && !results.contains(e.target) && (!clearBtn || !clearBtn.contains(e.target))) {
                        results.style.display = 'none';
                    }
                });
            }

            // Init Desktop & Mobile Search
            initGlobalSearch('globalSearchInput', 'globalSearchResults', 'globalSearchClear', 'globalSearchSpinner');
            initGlobalSearch('mobileGlobalSearchInput', 'mobileGlobalSearchResults', 'mobileGlobalSearchClear', 'mobileGlobalSearchSpinner');

            const mobileToggle = document.getElementById('mobileSearchToggle');
            const mobileDrawer = document.getElementById('mobileSearchDrawer');
            const desktopResults = document.getElementById('globalSearchResults');
            const mobileResults = document.getElementById('mobileGlobalSearchResults');

            function closeMobileSearchDrawer() {
                if (mobileDrawer) {
                    mobileDrawer.style.display = 'none';
                }
                if (mobileResults) {
                    mobileResults.style.display = 'none';
                }
            }

            if (mobileToggle && mobileDrawer) {
                mobileToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const isOpen = mobileDrawer.style.display !== 'none';
                    mobileDrawer.style.display = isOpen ? 'none' : 'block';
                    if (!isOpen) {
                        // Close any open header dropdowns (notifications/profile/language) before opening search
                        document.querySelectorAll('.top-header [data-bs-toggle="dropdown"]').forEach(btn => {
                            if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                                bootstrap.Dropdown.getInstance(btn)?.hide();
                            }
                        });
                        const mobInput = document.getElementById('mobileGlobalSearchInput');
                        if (mobInput) mobInput.focus();
                    }
                });

                document.addEventListener('click', function(e) {
                    if (mobileDrawer.style.display !== 'none' &&
                        !mobileDrawer.contains(e.target) &&
                        !mobileToggle.contains(e.target)) {
                        closeMobileSearchDrawer();
                    }
                });
            }

            document.addEventListener('show.bs.dropdown', function(e) {
                if (e.target && e.target.closest('.top-header')) {
                    closeMobileSearchDrawer();
                    if (desktopResults) {
                        desktopResults.style.display = 'none';
                    }
                }
            });
        });
        </script>