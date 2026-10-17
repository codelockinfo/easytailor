<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class NotificationService {
    private $db;

    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        } else {
            $database = new Database();
            $this->db = $database->getConnection();
        }
    }
    public function getAdminUserIds($companyId) {
        if (!$companyId) {
            return [];
        }
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE company_id = :cid AND role = 'admin' AND status = 'active'");
            $stmt->execute([':cid' => $companyId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("NotificationService::getAdminUserIds error: " . $e->getMessage());
            return [];
        }
    }
    public function getUser($userId) {
        if (!$userId) return null;
        try {
            $stmt = $this->db->prepare("SELECT id, full_name, username, email, role, company_id FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    public function getOrderDetails($orderId) {
        if (!$orderId) return null;
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, 
                       c.first_name, c.last_name, c.email as customer_email, c.phone as customer_phone,
                       ct.name as cloth_type_name,
                       u.full_name as tailor_name,
                       COALESCE(NULLIF(TRIM(o.customer_name), ''), NULLIF(TRIM(ms.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), '')) as resolved_customer_name
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                LEFT JOIN users u ON o.assigned_tailor_id = u.id
                LEFT JOIN measurements ms ON o.measurement_id = ms.id
                WHERE o.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $orderId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    public function getOrderDescriptor($order) {
        if (!$order) return 'order';

        $customerName = trim($order['resolved_customer_name'] ?? '');
        if (empty($customerName)) {
            $customerName = !empty($order['customer_name']) 
                ? trim($order['customer_name']) 
                : (!empty($order['first_name']) ? trim($order['first_name'] . ' ' . ($order['last_name'] ?? '')) : '');
        }
        if (empty($customerName)) {
            $customerName = 'Customer';
        }

        $clothType = !empty($order['cloth_type_name']) ? trim($order['cloth_type_name']) : '';

        if (!empty($clothType)) {
            return "{$customerName} ({$clothType})";
        }
        return $customerName;
    }
    public function createNotification($userId, $companyId, $orderId, $type, $title, $message, $link = null, $icon = 'fas fa-bell text-primary') {
        if (!$userId) {
            return false;
        }
        if (!$link && $orderId) {
            $link = 'order-details.php?id=' . $orderId;
        }

        try {
            $checkStmt = $this->db->prepare("
                SELECT id FROM notifications 
                WHERE user_id = :uid AND order_id = :oid AND type = :type AND created_at >= (NOW() - INTERVAL 1 MINUTE)
                LIMIT 1
            ");
            $checkStmt->execute([
                ':uid' => $userId,
                ':oid' => $orderId,
                ':type' => $type
            ]);
            if ($checkStmt->fetch()) {
                return true;
            }
            $stmt = $this->db->prepare("
                INSERT INTO notifications (company_id, user_id, order_id, type, title, message, link, icon, is_read, created_at)
                VALUES (:cid, :uid, :oid, :type, :title, :message, :link, :icon, 0, NOW())
            ");
            $stmt->execute([
                ':cid' => $companyId,
                ':uid' => $userId,
                ':oid' => $orderId,
                ':type' => $type,
                ':title' => $title,
                ':message' => $message,
                ':link' => $link,
                ':icon' => $icon
            ]);
            $notificationId = $this->db->lastInsertId();
            $this->sendPushToUser($userId, [
                'title' => $title,
                'body' => $message,
                'icon' => '../assets/images/tailor_logo_color.png',
                'badge' => '../favicon.ico',
                'data' => [
                    'url' => $link,
                    'order_id' => $orderId,
                    'notification_id' => $notificationId
                ]
            ]);

            return $notificationId;
        } catch (Exception $e) {
            error_log("NotificationService::createNotification error: " . $e->getMessage());
            return false;
        }
    }
    public function sendPushToUser($userId, array $payload) {
        if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY') || empty(VAPID_PUBLIC_KEY) || empty(VAPID_PRIVATE_KEY)) {
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT endpoint, endpoint_hash, p256dh_key, auth_token FROM push_subscriptions WHERE user_id = :uid");
            $stmt->execute([':uid' => $userId]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subscriptions)) {
                return;
            }

            $auth = [
                'VAPID' => [
                    'subject' => defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:support@tailorpro.local',
                    'publicKey' => VAPID_PUBLIC_KEY,
                    'privateKey' => VAPID_PRIVATE_KEY
                ]
            ];

            $webPush = new WebPush($auth);
            $webPush->setDefaultOptions(['TTL' => 86400]); 

            $payloadJson = json_encode($payload);

            foreach ($subscriptions as $subRow) {
                if (empty($subRow['endpoint']) || empty($subRow['p256dh_key']) || empty($subRow['auth_token'])) {
                    continue;
                }

                $sub = Subscription::create([
                    'endpoint' => $subRow['endpoint'],
                    'publicKey' => $subRow['p256dh_key'],
                    'authToken' => $subRow['auth_token'],
                ]);

                $webPush->queueNotification($sub, $payloadJson);
            }
            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if (!$report->isSuccess()) {
                    if ($report->isSubscriptionExpired()) {
                        $hash = hash('sha256', $endpoint);
                        $delStmt = $this->db->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = :hash");
                        $delStmt->execute([':hash' => $hash]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("NotificationService::sendPushToUser error: " . $e->getMessage());
        }
    }
    public function onOrderCreated($orderId, $orderData = []) {
        try {
            $order = $this->getOrderDetails($orderId);
            if (!$order) return false;

            $companyId = $order['company_id'] ?? get_company_id();
            $descriptor = $this->getOrderDescriptor($order);
            if (!empty($order['assigned_tailor_id'])) {
                $this->onOrderAssigned($orderId, $order['assigned_tailor_id'], null);
            }
            $isAdminAction = (function_exists('has_role') && has_role('admin')) || 
                             (function_exists('is_logged_in') && is_logged_in() && ($_SESSION['role'] ?? '') === 'admin');
            if (!$isAdminAction) {
                $title = "New Order Received";
                $message = "A new order has been received for {$descriptor}.";
                $icon = "fas fa-shopping-bag text-primary";
                $link = "order-details.php?id=" . $orderId;
                $adminIds = $this->getAdminUserIds($companyId);
                foreach ($adminIds as $adminId) {
                    $this->createNotification($adminId, $companyId, $orderId, 'order_created', $title, $message, $link, $icon);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("onOrderCreated error: " . $e->getMessage());
            return false;
        }
    }
    public function onOrderAssigned($orderId, $assignedTailorId, $previousTailorId = null) {
        if (!$assignedTailorId) return false;
        if ($previousTailorId && (int)$previousTailorId === (int)$assignedTailorId) {
            return false;
        }

        try {
            $order = $this->getOrderDetails($orderId);
            if (!$order) return false;

            $companyId = $order['company_id'] ?? get_company_id();
            $descriptor = $this->getOrderDescriptor($order);

            $title = "New Order Assigned";
            $message = "Order for {$descriptor} has been assigned to you. Please check your dashboard.";
            $icon = "fas fa-user-tag text-success";
            $link = "order-details.php?id=" . $orderId;
            $this->createNotification($assignedTailorId, $companyId, $orderId, 'order_assigned', $title, $message, $link, $icon);

            return true;
        } catch (Exception $e) {
            error_log("onOrderAssigned error: " . $e->getMessage());
            return false;
        }
    }
    public function onTailorStartedWork($orderId, $tailorId, $tailorName = null) {
        try {
            $order = $this->getOrderDetails($orderId);
            if (!$order) return false;

            $companyId = $order['company_id'] ?? get_company_id();
            $descriptor = $this->getOrderDescriptor($order);
            
            if (!$tailorName) {
                $tailor = $this->getUser($tailorId);
                $tailorName = $tailor['full_name'] ?? 'Tailor';
            }

            $title = "Order In Progress";
            $message = "Tailor {$tailorName} has started working on order for {$descriptor}.";
            $icon = "fas fa-spinner text-warning";
            $link = "order-details.php?id=" . $orderId;
            $adminIds = $this->getAdminUserIds($companyId);
            foreach ($adminIds as $adminId) {
                $this->createNotification($adminId, $companyId, $orderId, 'order_in_progress', $title, $message, $link, $icon);
            }

            return true;
        } catch (Exception $e) {
            error_log("onTailorStartedWork error: " . $e->getMessage());
            return false;
        }
    }
    public function onTailorCompletedOrder($orderId, $tailorId, $tailorName = null) {
        try {
            $order = $this->getOrderDetails($orderId);
            if (!$order) return false;

            $companyId = $order['company_id'] ?? get_company_id();
            $descriptor = $this->getOrderDescriptor($order);

            if (!$tailorName) {
                $tailor = $this->getUser($tailorId);
                $tailorName = $tailor['full_name'] ?? 'Tailor';
            }

            $title = "Order Completed";
            $message = "Tailor {$tailorName} has completed order for {$descriptor}.";
            $icon = "fas fa-check-circle text-success";
            $link = "order-details.php?id=" . $orderId;
            $adminIds = $this->getAdminUserIds($companyId);
            foreach ($adminIds as $adminId) {
                $this->createNotification($adminId, $companyId, $orderId, 'order_completed', $title, $message, $link, $icon);
            }
            if (!empty($order['customer_email']) && file_exists(__DIR__ . '/MailService.php')) {
                try {
                    require_once __DIR__ . '/MailService.php';
                    $mailService = new MailService();
                    if (method_exists($mailService, 'sendOrderCompletedNotification')) {
                        $mailService->sendOrderCompletedNotification($order['customer_email'], $order);
                    }
                } catch (Throwable $mailEx) {
                    error_log("Customer completion email notice failed: " . $mailEx->getMessage());
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("onTailorCompletedOrder error: " . $e->getMessage());
            return false;
        }
    }
    public function onTailorCancelledOrder($orderId, $tailorId, $tailorName = null, $reason = '') {
        try {
            $order = $this->getOrderDetails($orderId);
            if (!$order) return false;

            $companyId = $order['company_id'] ?? get_company_id();
            $descriptor = $this->getOrderDescriptor($order);

            if (!$tailorName) {
                $tailor = $this->getUser($tailorId);
                $tailorName = $tailor['full_name'] ?? 'Tailor';
            }

            $title = "Order Cancelled";
            $reasonText = !empty(trim($reason)) ? " Reason: " . trim($reason) . "." : " Please review the order.";
            $message = "Tailor {$tailorName} has cancelled order for {$descriptor}.{$reasonText}";
            $icon = "fas fa-times-circle text-danger";
            $link = "order-details.php?id=" . $orderId;
            $adminIds = $this->getAdminUserIds($companyId);
            foreach ($adminIds as $adminId) {
                $this->createNotification($adminId, $companyId, $orderId, 'order_cancelled', $title, $message, $link, $icon);
            }

            return true;
        } catch (Exception $e) {
            error_log("onTailorCancelledOrder error: " . $e->getMessage());
            return false;
        }
    }
    public function onAdminUpdatedOrderStatus($orderId, $newStatus, $adminId, $reason = '') {
        try {
            $order = $this->getOrderDetails($orderId);
            if (!$order) return false;

            $companyId = $order['company_id'] ?? get_company_id();
            $assignedTailorId = $order['assigned_tailor_id'] ?? null;
            $descriptor = $this->getOrderDescriptor($order);
            $statusLabel = ucfirst(str_replace('_', ' ', $newStatus));
            if ($assignedTailorId) {
                if ($newStatus === 'cancelled') {
                    $title = "Order Cancelled";
                    $reasonText = !empty(trim($reason)) ? " Reason: " . trim($reason) . "." : "";
                    $message = "Admin has cancelled order for {$descriptor}.{$reasonText}";
                    $icon = "fas fa-times-circle text-danger";
                } else {
                    $title = "Order Status Updated";
                    $message = "Order for {$descriptor} status has been updated to {$statusLabel}.";
                    $icon = "fas fa-info-circle text-info";
                }

                $link = "order-details.php?id=" . $orderId;
                $this->createNotification($assignedTailorId, $companyId, $orderId, 'order_status_updated', $title, $message, $link, $icon);
            }
            if (!empty($order['customer_email']) && in_array($newStatus, ['completed', 'delivered', 'cancelled']) && file_exists(__DIR__ . '/MailService.php')) {
                try {
                    require_once __DIR__ . '/MailService.php';
                    $mailService = new MailService();
                    if (method_exists($mailService, 'sendOrderStatusUpdateNotification')) {
                        $mailService->sendOrderStatusUpdateNotification($order['customer_email'], $order, $newStatus);
                    }
                } catch (Throwable $mailEx) {
                    error_log("Customer status email notice failed: " . $mailEx->getMessage());
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("onAdminUpdatedOrderStatus error: " . $e->getMessage());
            return false;
        }
    }
    public function savePushSubscription($userId, $companyId, $endpoint, $p256dhKey, $authToken, $userAgent = null) {
        if (!$userId || !$endpoint || !$p256dhKey || !$authToken) {
            return false;
        }

        try {
            $endpointHash = hash('sha256', $endpoint);
            $stmt = $this->db->prepare("
                INSERT INTO push_subscriptions (user_id, company_id, endpoint, endpoint_hash, p256dh_key, auth_token, user_agent, created_at, updated_at)
                VALUES (:uid, :cid, :endpoint, :hash, :p256dh, :auth, :ua, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    user_id = VALUES(user_id),
                    company_id = VALUES(company_id),
                    p256dh_key = VALUES(p256dh_key),
                    auth_token = VALUES(auth_token),
                    user_agent = VALUES(user_agent),
                    updated_at = NOW()
            ");

            return $stmt->execute([
                ':uid' => $userId,
                ':cid' => $companyId,
                ':endpoint' => $endpoint,
                ':hash' => $endpointHash,
                ':p256dh' => $p256dhKey,
                ':auth' => $authToken,
                ':ua' => substr((string)$userAgent, 0, 255)
            ]);
        } catch (Exception $e) {
            error_log("NotificationService::savePushSubscription error: " . $e->getMessage());
            return false;
        }
    }
    public function deletePushSubscription($endpoint) {
        if (!$endpoint) return false;
        try {
            $hash = hash('sha256', $endpoint);
            $stmt = $this->db->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = :hash");
            return $stmt->execute([':hash' => $hash]);
        } catch (Exception $e) {
            return false;
        }
    }
    public function getUnreadCount($userId, $companyId = null) {
        if (!$userId) return 0;
        try {
            $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0";
            $params = [':uid' => $userId];
            if ($companyId) {
                $sql .= " AND (company_id = :cid OR company_id IS NULL)";
                $params[':cid'] = $companyId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("NotificationService::getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }
    public function getRecentNotifications($userId, $companyId = null, $limit = 15) {
        if (!$userId) return [];
        try {
            $sql = "
                SELECT id, company_id, user_id, order_id, type, title, message, link, icon, is_read, created_at,
                       TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago,
                       TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_ago,
                       TIMESTAMPDIFF(DAY, created_at, NOW()) as days_ago
                FROM notifications 
                WHERE user_id = :uid
            ";
            $params = [':uid' => $userId];
            if ($companyId) {
                $sql .= " AND (company_id = :cid OR company_id IS NULL)";
                $params[':cid'] = $companyId;
            }
            $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$item) {
                $item['time_ago'] = self::formatTimeAgo($item['created_at']);
            }

            return $results;
        } catch (Exception $e) {
            error_log("NotificationService::getRecentNotifications error: " . $e->getMessage());
            return [];
        }
    }
    public function markAsRead($notificationId, $userId) {
        if (!$notificationId || !$userId) return false;
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
            return $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    public function markOrderNotificationsAsRead($orderId, $userId) {
        if (!$orderId || !$userId) return false;
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE order_id = :oid AND user_id = :uid");
            return $stmt->execute([':oid' => $orderId, ':uid' => $userId]);
        } catch (Exception $e) {
            error_log("NotificationService::markOrderNotificationsAsRead error: " . $e->getMessage());
            return false;
        }
    }
    public function markAllAsRead($userId, $companyId = null) {
        if (!$userId) return false;
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :uid";
            $params = [':uid' => $userId];
            if ($companyId) {
                $sql .= " AND (company_id = :cid OR company_id IS NULL)";
                $params[':cid'] = $companyId;
            }
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            return false;
        }
    }
    public static function formatTimeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;

        if ($difference < 60) {
            return 'Just now';
        } elseif ($difference < 3600) {
            $mins = floor($difference / 60);
            return $mins . 'm ago';
        } elseif ($difference < 86400) {
            $hours = floor($difference / 3600);
            return $hours . 'h ago';
        } elseif ($difference < 604800) {
            $days = floor($difference / 86400);
            return $days . 'd ago';
        } else {
            return date('d M', $timestamp);
        }
    }
}
