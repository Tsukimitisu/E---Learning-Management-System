<?php
/**
 * Notification Helper — create and broadcast notifications for any role.
 *
 * Usage:
 *   require_once __DIR__ . '/notification_helper.php';
 *   create_notification($user_id, 'Title', 'Message body', 'enrollment', '/path');
 *   create_bulk_notifications([1,2,3], 'Title', 'Message', 'announcement', '/path');
 */

require_once __DIR__ . '/realtime_helper.php';

/**
 * Create a notification for a single user, persist to DB, and push via realtime.
 *
 * @param int    $user_id    Recipient user ID
 * @param string $title      Short title
 * @param string $message    Full message text
 * @param string $type       Notification type (info|enrollment|grade|material|announcement|payment|system)
 * @param string|null $link  Optional link to navigate to
 * @param int|null $created_by  ID of user who triggered it
 * @return int|false  The notification ID or false on failure
 */
function create_notification($user_id, $title, $message, $type = 'info', $link = null, $created_by = null) {
    global $conn;
    if (!$user_id) return false;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $user_id, $title, $message, $type, $link, $created_by);
    $stmt->execute();
    $notif_id = $conn->insert_id;

    if ($notif_id) {
        // Push realtime notification
        try {
            send_realtime_update('notification', [
                'id'      => $notif_id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'link'    => $link
            ], null, [$user_id]);
        } catch (Exception $e) {
            // Realtime push is best-effort; don't break flow
        }
    }

    return $notif_id ?: false;
}

/**
 * Create notifications for multiple users at once.
 *
 * @param array  $user_ids    Array of recipient user IDs
 * @param string $title       Short title
 * @param string $message     Full message text
 * @param string $type        Notification type
 * @param string|null $link   Optional link
 * @param int|null $created_by  ID of user who triggered it
 * @return int  Number of notifications created
 */
function create_bulk_notifications($user_ids, $title, $message, $type = 'info', $link = null, $created_by = null) {
    global $conn;
    if (empty($user_ids)) return 0;

    $user_ids = array_unique(array_filter(array_map('intval', $user_ids)));
    if (empty($user_ids)) return 0;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $count = 0;

    foreach ($user_ids as $uid) {
        $stmt->bind_param("issssi", $uid, $title, $message, $type, $link, $created_by);
        $stmt->execute();
        if ($conn->insert_id) $count++;
    }

    // Push realtime to all recipients at once
    if ($count > 0) {
        try {
            send_realtime_update('notification', [
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'link'    => $link
            ], null, $user_ids);
        } catch (Exception $e) {
            // Best-effort
        }
    }

    return $count;
}
