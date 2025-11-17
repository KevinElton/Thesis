<?php
require_once __DIR__ . '/database.php';

class Notification {
    /**
     * Creates a new notification in the database.
     *
     * @param int $user_id The ID of the user to notify (admin_id or panelist_id).
     * @param string $user_type 'admin' or 'panelist'.
     * @param string $title The title of the notification.
     * @param string $message The notification message body.
     * @param string $type The notification type (e.g., 'assignment', 'availability').
     * @param string|null $link An optional URL for the notification to link to.
     * @return bool True on success, false on failure.
     */
    public static function create($user_id, $user_type, $title, $message, $type, $link = null) {
        try {
            $db = new Database();
            $conn = $db->connect();

            $stmt = $conn->prepare(
                "INSERT INTO notifications (user_id, user_type, title, message, type, link) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            return $stmt->execute([$user_id, $user_type, $title, $message, $type, $link]);

        } catch (PDOException $e) {
            // Optional: Log error $e->getMessage()
            return false;
        }
    }
}
?>