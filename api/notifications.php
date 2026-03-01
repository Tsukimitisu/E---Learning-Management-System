<?php
/**
 * Notifications API — works for ALL roles.
 * Actions: fetch, count, mark_read, mark_all_read
 */
require_once '../config/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'fetch';

switch ($action) {
    case 'fetch':
        fetchNotifications($user_id);
        break;
    case 'count':
        getUnreadCount($user_id);
        break;
    case 'mark_read':
        markAsRead($user_id);
        break;
    case 'mark_all_read':
        markAllAsRead($user_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function fetchNotifications($user_id) {
    global $conn;
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $stmt = $conn->prepare("
        SELECT id, title, message, type, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_read'] = (int)$row['is_read'];
        $row['time_ago'] = timeAgo($row['created_at']);
        $notifications[] = $row;
    }

    // Get unread count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $unread = (int)($count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread
    ]);
}

function getUnreadCount($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    echo json_encode(['success' => true, 'unread_count' => $count]);
}

function markAsRead($user_id) {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        return;
    }

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
}

function markAllAsRead($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;

    echo json_encode(['success' => true, 'marked' => $affected]);
}

function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}
