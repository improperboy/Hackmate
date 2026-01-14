<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// Enable CORS for API access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'subscribe':
            handleSubscription();
            break;
            
        case 'unsubscribe':
            handleUnsubscription();
            break;
            
        case 'send':
            handleSendNotification();
            break;
            
        case 'get_announcements':
            getRecentAnnouncements();
            break;
            
        case 'get_support_messages':
            getSupportMessages();
            break;
            
        case 'mark_read':
            markNotificationRead();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleSubscription() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $subscription = $input['subscription'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (!$subscription) {
        throw new Exception('No subscription data provided');
    }
    
    // Store subscription in database
    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            p256dh_key = VALUES(p256dh_key),
            auth_key = VALUES(auth_key),
            user_agent = VALUES(user_agent),
            updated_at = NOW()
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $subscription['endpoint'],
        $subscription['keys']['p256dh'] ?? '',
        $subscription['keys']['auth'] ?? '',
        $userAgent
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Subscription saved']);
}

function handleUnsubscription() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $endpoint = $input['endpoint'] ?? null;
    
    if (!$endpoint) {
        throw new Exception('No endpoint provided');
    }
    
    $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
    $stmt->execute([$_SESSION['user_id'], $endpoint]);
    
    echo json_encode(['success' => true, 'message' => 'Subscription removed']);
}

function handleSendNotification() {
    global $pdo;
    
    checkAuth(['admin']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $title = $input['title'] ?? '';
    $body = $input['body'] ?? '';
    $type = $input['type'] ?? 'general';
    $targetRoles = $input['target_roles'] ?? ['participant', 'mentor', 'volunteer'];
    $url = $input['url'] ?? '/';
    
    if (!$title || !$body) {
        throw new Exception('Title and body are required');
    }
    
    // Get all active subscriptions for target roles
    $placeholders = str_repeat('?,', count($targetRoles) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT ps.*, u.role 
        FROM push_subscriptions ps
        JOIN users u ON ps.user_id = u.id
        WHERE u.role IN ($placeholders) AND ps.is_active = 1
    ");
    $stmt->execute($targetRoles);
    $subscriptions = $stmt->fetchAll();
    
    $sentCount = 0;
    $failedCount = 0;
    
    foreach ($subscriptions as $subscription) {
        try {
            $result = sendPushNotification($subscription, $title, $body, $url, $type);
            if ($result) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        } catch (Exception $e) {
            $failedCount++;
            error_log("Failed to send notification: " . $e->getMessage());
        }
    }
    
    // Log notification in database
    $stmt = $pdo->prepare("
        INSERT INTO notification_logs (type, title, body, target_roles, sent_count, failed_count, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$type, $title, $body, json_encode($targetRoles), $sentCount, $failedCount]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Notification sent to $sentCount users, $failedCount failed",
        'sent_count' => $sentCount,
        'failed_count' => $failedCount
    ]);
}

function getRecentAnnouncements() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $lastCheck = $_GET['since'] ?? '1970-01-01 00:00:00';
    
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.content, p.created_at, u.name as author_name
        FROM posts p
        JOIN users u ON p.author_id = u.id
        WHERE p.created_at > ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$lastCheck]);
    $announcements = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'announcements' => $announcements]);
}

function getSupportMessages() {
    global $pdo;
    
    checkAuth(['admin']);
    
    $lastCheck = $_GET['since'] ?? '1970-01-01 00:00:00';
    
    $stmt = $pdo->prepare("
        SELECT sm.id, sm.message, sm.from_role, sm.created_at, 
               u.name as from_name, u.email as from_email,
               f.floor_number, r.room_number
        FROM support_messages sm
        JOIN users u ON sm.from_id = u.id
        LEFT JOIN floors f ON sm.floor_id = f.id
        LEFT JOIN rooms r ON sm.room_id = r.id
        WHERE sm.to_role = 'admin' AND sm.status = 'open' AND sm.created_at > ?
        ORDER BY sm.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$lastCheck]);
    $messages = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function markNotificationRead() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['notification_id'] ?? null;
    
    if (!$notificationId) {
        throw new Exception('Notification ID required');
    }
    
    // Mark as read in user's notification history
    $stmt = $pdo->prepare("
        INSERT INTO user_notifications (user_id, notification_id, read_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE read_at = NOW()
    ");
    $stmt->execute([$_SESSION['user_id'], $notificationId]);
    
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
}

function sendPushNotification($subscription, $title, $body, $url = '/', $type = 'general') {
    // Simple push notification - in production, use a library like web-push
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => '/assets/icons/icon-192x192.png',
        'badge' => '/assets/icons/icon-72x72.png',
        'url' => $url,
        'type' => $type,
        'timestamp' => time(),
        'requireInteraction' => $type === 'urgent',
        'actions' => [
            [
                'action' => 'open',
                'title' => 'Open',
                'icon' => '/assets/icons/icon-96x96.png'
            ],
            [
                'action' => 'dismiss',
                'title' => 'Dismiss'
            ]
        ]
    ]);
    
    // For demo purposes, we'll simulate sending
    // In production, implement actual push notification sending
    return true;
}
?>
