<?php
session_start();
include 'config.php';
include 'notifications-helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'get-notifications') {
    // Get recent notifications
    $notifications = getRecentNotifications($conn, $user_id, 10);
    
    foreach ($notifications as &$notif) {
        $notif['time_ago'] = formatNotificationTime($notif['created_at']);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => getUnreadCount($conn, $user_id)
    ]);

} elseif ($action === 'mark-all-read') {
    // Mark all notifications as read
    markAllAsRead($conn, $user_id);
    echo json_encode(['success' => true]);

} elseif ($action === 'mark-read') {
    // Mark single notification as read
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    if ($notification_id > 0) {
        markAsRead($conn, $notification_id, $user_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    }

} elseif ($action === 'unread-count') {
    // Just get unread count
    echo json_encode([
        'success' => true,
        'unread_count' => getUnreadCount($conn, $user_id)
    ]);

} else {
    echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
?>
