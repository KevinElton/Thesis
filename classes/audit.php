<?php
require_once __DIR__ . '/../config/database.php';

class Audit {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Log an action to the audit log
     */
    public function logAction($user_id, $user_role, $action, $table_name = null, $record_id = null, $details = null) {
        $conn = $this->db->connect();

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $sql = "INSERT INTO audit_log (user_id, user_role, action, table_name, record_id, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $user_role, $action, $table_name, $record_id, $details, $ip_address]);

        return $conn->lastInsertId();
    }

    /**
     * Get audit logs with optional filters
     */
    public function getAuditLogs($filters = [], $limit = 100, $offset = 0) {
        $conn = $this->db->connect();

        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['user_role'])) {
            $where[] = "user_role = ?";
            $params[] = $filters['user_role'];
        }

        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['table_name'])) {
            $where[] = "table_name = ?";
            $params[] = $filters['table_name'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT * FROM audit_log $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get audit log statistics
     */
    public function getAuditStats($date_from = null, $date_to = null) {
        $conn = $this->db->connect();

        $where = "";
        $params = [];

        if ($date_from && $date_to) {
            $where = "WHERE DATE(created_at) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];
        } elseif ($date_from) {
            $where = "WHERE DATE(created_at) >= ?";
            $params = [$date_from];
        } elseif ($date_to) {
            $where = "WHERE DATE(created_at) <= ?";
            $params = [$date_to];
        }

        $sql = "SELECT
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    action,
                    COUNT(*) as action_count
                FROM audit_log
                $where
                GROUP BY action
                ORDER BY action_count DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clean up old audit logs (keep last N days)
     */
    public function cleanupOldLogs($days_to_keep = 365) {
        $conn = $this->db->connect();

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));

        $sql = "DELETE FROM audit_log WHERE created_at < ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$cutoff_date]);

        return $stmt->rowCount();
    }
}
?>


