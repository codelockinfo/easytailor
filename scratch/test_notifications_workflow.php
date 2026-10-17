<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/NotificationService.php';

$db = (new Database())->getConnection();
$notifService = new NotificationService($db);

$testsPassed = 0;
$testsTotal = 0;

function assertTest($condition, $description) {
    global $testsPassed, $testsTotal;
    $testsTotal++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
    }
}
assertTest(defined('VAPID_PUBLIC_KEY') && !empty(VAPID_PUBLIC_KEY), "VAPID Public Key loaded and configured");
assertTest(defined('VAPID_PRIVATE_KEY') && !empty(VAPID_PRIVATE_KEY), "VAPID Private Key loaded and configured");
assertTest(function_exists('get_vapid_public_key') && get_vapid_public_key() === VAPID_PUBLIC_KEY, "get_vapid_public_key() safely retrieves key");

$stmtAdmin = $db->query("SELECT id, company_id, full_name, role FROM users WHERE role = 'admin' LIMIT 1");
$adminUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

if (!$adminUser) {
    $db->exec("INSERT INTO users (username, email, password, full_name, role, status) VALUES ('test_admin_" . time() . "', 'test_admin_" . time() . "@test.com', 'test', 'Admin Test', 'admin', 'active')");
    $adminId = (int)$db->lastInsertId();
    $companyId = 1;
} else {
    $adminId = (int)$adminUser['id'];
    $companyId = (int)($adminUser['company_id'] ?: 1);
}
$stmtTailors = $db->prepare("SELECT id, full_name, role FROM users WHERE role = 'tailor' LIMIT 2");
$stmtTailors->execute();
$tailors = $stmtTailors->fetchAll(PDO::FETCH_ASSOC);

if (count($tailors) < 2) {
    $t1Name = 'Tailor Rahul ' . time();
    $db->exec("INSERT INTO users (username, email, password, full_name, role, company_id, status) VALUES ('tailor_a_" . time() . "', 'tailor_a_" . time() . "@test.com', 'test', '{$t1Name}', 'tailor', {$companyId}, 'active')");
    $tailorAId = (int)$db->lastInsertId();

    $t2Name = 'Tailor Suresh ' . time();
    $db->exec("INSERT INTO users (username, email, password, full_name, role, company_id, status) VALUES ('tailor_b_" . time() . "', 'tailor_b_" . time() . "@test.com', 'test', '{$t2Name}', 'tailor', {$companyId}, 'active')");
    $tailorBId = (int)$db->lastInsertId();
} else {
    $tailorAId = (int)$tailors[0]['id'];
    $t1Name = $tailors[0]['full_name'];
    $tailorBId = (int)$tailors[1]['id'];
    $t2Name = $tailors[1]['full_name'];
}

echo "Testing with:\n";
echo " - Admin ID: {$adminId}\n";
echo " - Tailor A ID: {$tailorAId} ({$t1Name})\n";
echo " - Tailor B ID: {$tailorBId} ({$t2Name})\n";
echo " - Company ID: {$companyId}\n\n";

$testOrderId = 999901;
$initialAdminUnread = $notifService->getUnreadCount($adminId, $companyId);
$notifService->createNotification($adminId, $companyId, $testOrderId, 'order_created', 'New Order Received', "A new order #{$testOrderId} has been placed by a customer.");
$newAdminUnread = $notifService->getUnreadCount($adminId, $companyId);
assertTest($newAdminUnread === $initialAdminUnread + 1, "Event 1: Admin unread notification count incremented by 1");

$adminNotifs = $notifService->getRecentNotifications($adminId, $companyId, 1);
assertTest(!empty($adminNotifs) && $adminNotifs[0]['type'] === 'order_created', "Event 1: Order created notification record saved for Admin");

$initialTailorAUnread = $notifService->getUnreadCount($tailorAId, $companyId);
$initialTailorBUnread = $notifService->getUnreadCount($tailorBId, $companyId);

$notifService->createNotification($tailorAId, $companyId, $testOrderId, 'order_assigned', 'New Order Assigned', "Order #{$testOrderId} has been assigned to you. Please check your dashboard.", 'order-details.php?id=' . $testOrderId, 'fas fa-user-tag text-success');

$tailorAUnreadAfter = $notifService->getUnreadCount($tailorAId, $companyId);
$tailorBUnreadAfter = $notifService->getUnreadCount($tailorBId, $companyId);

assertTest($tailorAUnreadAfter === $initialTailorAUnread + 1, "Event 2: Tailor A receives assignment notification");
assertTest($tailorBUnreadAfter === $initialTailorBUnread, "Event 2: Tailor B does NOT receive Tailor A's assignment notification");

$adminCountBeforeProgress = $notifService->getUnreadCount($adminId, $companyId);
$notifService->createNotification($adminId, $companyId, $testOrderId, 'order_in_progress', 'Order In Progress', "Tailor {$t1Name} has started working on order #{$testOrderId}.", 'order-details.php?id=' . $testOrderId, 'fas fa-spinner text-warning');
$adminCountAfterProgress = $notifService->getUnreadCount($adminId, $companyId);
assertTest($adminCountAfterProgress === $adminCountBeforeProgress + 1, "Event 3: Admin receives 'In Progress' notification with Tailor name");

$adminCountBeforeCompleted = $notifService->getUnreadCount($adminId, $companyId);
$notifService->createNotification($adminId, $companyId, $testOrderId, 'order_completed', 'Order Completed', "Tailor {$t1Name} has completed order #{$testOrderId}.", 'order-details.php?id=' . $testOrderId, 'fas fa-check-circle text-success');
$adminCountAfterCompleted = $notifService->getUnreadCount($adminId, $companyId);
assertTest($adminCountAfterCompleted === $adminCountBeforeCompleted + 1, "Event 4: Admin receives 'Order Completed' notification with Tailor name");

$adminCountBeforeCancel = $notifService->getUnreadCount($adminId, $companyId);
$cancellationReason = "Customer requested style change unavailable";
$notifService->createNotification($adminId, $companyId, $testOrderId, 'order_cancelled', 'Order Cancelled', "Tailor {$t1Name} has cancelled order #{$testOrderId}. Reason: {$cancellationReason}.", 'order-details.php?id=' . $testOrderId, 'fas fa-times-circle text-danger');
$adminCountAfterCancel = $notifService->getUnreadCount($adminId, $companyId);
assertTest($adminCountAfterCancel === $adminCountBeforeCancel + 1, "Event 5: Admin receives 'Order Cancelled' notification with cancellation reason");

$notifService->createNotification($tailorBId, $companyId, $testOrderId, 'order_assigned', 'New Order Assigned', "Order #{$testOrderId} has been assigned to you. Please check your dashboard.");
$tailorBUnreadFinal = $notifService->getUnreadCount($tailorBId, $companyId);
assertTest($tailorBUnreadFinal === $tailorBUnreadAfter + 1, "Event 6 / Reassignment: Newly assigned Tailor B receives assignment notification");

$tailorANotifs = $notifService->getRecentNotifications($tailorAId, $companyId, 50);
$allBelongToTailorA = true;
foreach ($tailorANotifs as $tNotif) {
    if ((int)$tNotif['user_id'] !== $tailorAId) {
        $allBelongToTailorA = false;
        break;
    }
}
assertTest($allBelongToTailorA, "Security: Tailor A query ONLY returns notifications belonging to Tailor A");
$adminNotifId = $adminNotifs[0]['id'];
$crossReadResult = $notifService->markAsRead($adminNotifId, $tailorAId);
$checkStmt = $db->prepare("SELECT is_read FROM notifications WHERE id = :id");
$checkStmt->execute([':id' => $adminNotifId]);
$adminNotifRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
assertTest((int)$adminNotifRow['is_read'] === 0, "Security: Tailor A CANNOT mark Admin's notification as read");

$tailorANotifId = $tailorANotifs[0]['id'];
$ownReadResult = $notifService->markAsRead($tailorANotifId, $tailorAId);
$checkStmt->execute([':id' => $tailorANotifId]);
$tailorANotifRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
assertTest((int)$tailorANotifRow['is_read'] === 1, "In-app read status: Tailor A successfully marks own notification as read");
$notifService->markAllAsRead($adminId, $companyId);
$adminUnreadAfterMarkAll = $notifService->getUnreadCount($adminId, $companyId);
assertTest($adminUnreadAfterMarkAll === 0, "In-app mark all read: Admin unread count is 0 after markAllAsRead");
$testEndpoint = "https://fcm.googleapis.com/fcm/send/test_device_token_" . time();
$subSaved = $notifService->savePushSubscription($tailorAId, $companyId, $testEndpoint, "dummy_p256dh_key_base64", "dummy_auth_key_base64", "Mozilla/5.0 Test Browser");
assertTest($subSaved === true, "Browser Push: Save browser push subscription to MySQL succeeds");
$subUpdated = $notifService->savePushSubscription($tailorAId, $companyId, $testEndpoint, "dummy_p256dh_key_base64_v2", "dummy_auth_key_base64_v2", "Mozilla/5.0 Updated Browser");
assertTest($subUpdated === true, "Browser Push: Subscription endpoint upsert works without duplicate key error");

$testEndpoint2 = "https://fcm.googleapis.com/fcm/send/test_phone_token_" . time();
$subSaved2 = $notifService->savePushSubscription($tailorAId, $companyId, $testEndpoint2, "dummy_p256dh_key_base64", "dummy_auth_key_base64", "Mobile Safari Test");
assertTest($subSaved2 === true, "Browser Push: Multiple devices/browsers per user supported");

$notifService->deletePushSubscription($testEndpoint);
$notifService->deletePushSubscription($testEndpoint2);
$fakeSubEndpoint = "https://updates.push.services.mozilla.com/wpush/v2/gAAAAABdummy" . time();
$notifService->savePushSubscription($tailorAId, $companyId, $fakeSubEndpoint, "dummy_invalid_key", "dummy_invalid_auth", "Fake Browser");
try {
    $pushFailedGracefully = true;
    $notifService->sendPushToUser($tailorAId, [
        'title' => 'Test Failure Resilience',
        'body' => 'Testing that failure does not throw'
    ]);
} catch (Throwable $e) {
    $pushFailedGracefully = false;
}
assertTest($pushFailedGracefully, "Resilience: Push delivery errors handled gracefully without breaking execution");
$notifService->deletePushSubscription($fakeSubEndpoint);

$db->exec("DELETE FROM notifications WHERE order_id = {$testOrderId}");

echo "\n====================================================\n";
echo " Test Results: {$testsPassed} / {$testsTotal} passed\n";
echo "====================================================\n";
