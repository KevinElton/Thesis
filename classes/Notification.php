<?php
// File: /THESIS/classes/Notification.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/Mailer.php';

class Notification {
    private $conn;
    
    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }
    
    /**
     * Create a notification for a user
     */
    public function create($userId, $userType, $title, $message, $type = 'general', $relatedId = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (user_id, user_type, title, message, type, related_id, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            return $stmt->execute([$userId, $userType, $title, $message, $type, $relatedId]);
        } catch (PDOException $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify admin when panelist updates availability (In-System + Email)
     */
    public function notifyAdminAvailabilityUpdate($panelistId, $panelistName, $panelistEmail, $day, $timeRange) {
        // Get all admins
        $stmt = $this->conn->query("SELECT id, email FROM admin");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $title = "Panelist Availability Updated";
        $message = "{$panelistName} has updated their availability for {$day} ({$timeRange}). You can now assign them to thesis groups.";
        
        // Create in-system notifications for each admin
        foreach ($admins as $admin) {
            $this->create($admin['id'], 'admin', $title, $message, 'availability_update', $panelistId);
            
            // Send email notification
            $emailBody = $this->getAvailabilityUpdateEmailTemplate($panelistName, $panelistEmail, $day, $timeRange);
            $this->sendEmail($admin['email'], "Panelist Availability Updated - {$panelistName}", $emailBody);
        }
        
        return true;
    }
    
    /**
     * Notify panelist when assigned to thesis group (In-System + Email)
     */
    public function notifyPanelistAssignment($panelistId, $panelistEmail, $groupName, $thesisTitle, $scheduleDate, $scheduleTime, $scheduleEndTime, $room, $role, $groupId) {
        $title = "New Panel Assignment";
        $message = "You have been assigned as {$role} for {$groupName}'s thesis defense. ";
        $message .= "Thesis: \"{$thesisTitle}\". ";
        $message .= "Schedule: " . date('F j, Y', strtotime($scheduleDate)) . " at " . date('g:i A', strtotime($scheduleTime)) . ".";
        
        // Create in-system notification
        $this->create($panelistId, 'panelist', $title, $message, 'assignment', $groupId);
        
        // Send email notification
        $emailBody = $this->getPanelistAssignmentEmailTemplate(
            $groupName, 
            $thesisTitle, 
            $scheduleDate, 
            $scheduleTime, 
            $scheduleEndTime, 
            $room, 
            $role
        );
        $this->sendEmail($panelistEmail, "Panel Assignment - {$groupName}'s Thesis Defense", $emailBody);
        
        return true;
    }
    
    /**
     * Get unread notifications for a user
     */
    public function getUnread($userId, $userType) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications
            WHERE user_id = ? AND user_type = ? AND is_read = 0
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all notifications for a user
     */
    public function getAll($userId, $userType, $limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications
            WHERE user_id = ? AND user_type = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $userType, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($userId, $userType) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM notifications
            WHERE user_id = ? AND user_type = ? AND is_read = 0
        ");
        $stmt->execute([$userId, $userType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1 WHERE notification_id = ?
        ");
        return $stmt->execute([$notificationId]);
    }
    
    /**
     * Mark all as read for a user
     */
    public function markAllAsRead($userId, $userType) {
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1 
            WHERE user_id = ? AND user_type = ? AND is_read = 0
        ");
        return $stmt->execute([$userId, $userType]);
    }
    
    /**
     * Delete notification
     */
    public function delete($notificationId) {
        $stmt = $this->conn->prepare("DELETE FROM notifications WHERE notification_id = ?");
        return $stmt->execute([$notificationId]);
    }
    
    /**
     * Send email using your Mailer class
     */
    private function sendEmail($to, $subject, $body) {
        try {
            $result = Mailer::send($to, $subject, $body);
            
            // Log email
            $status = ($result === true) ? 'sent' : 'failed';
            $error = ($result === true) ? null : $result;
            $this->logEmail($to, $subject, $body, $status, $error);
            
            return $result === true;
        } catch (Exception $e) {
            $this->logEmail($to, $subject, $body, 'failed', $e->getMessage());
            error_log("Email Error: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Log email sending attempts
     */
    private function logEmail($email, $subject, $message, $status, $error = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO email_logs (recipient_email, subject, message, status, error_message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$email, $subject, $message, $status, $error]);
        } catch (Exception $e) {
            error_log("Email logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Email template for availability update
     */
    private function getAvailabilityUpdateEmailTemplate($panelistName, $panelistEmail, $day, $timeRange) {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>📅 Availability Update</h1>
            </div>
            
            <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <h2 style='color: #333; margin-top: 0;'>Panelist Updated Their Schedule</h2>
                
                <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Panelist:</strong> {$panelistName}</p>
                    <p style='margin: 5px 0;'><strong>Email:</strong> {$panelistEmail}</p>
                    <p style='margin: 5px 0;'><strong>Day:</strong> {$day}</p>
                    <p style='margin: 5px 0;'><strong>Time:</strong> {$timeRange}</p>
                </div>
                
                <p style='color: #666; line-height: 1.6;'>
                    A panelist has updated their availability schedule. You can now assign them to thesis groups 
                    that match their available time slots.
                </p>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='http://localhost/THESIS/app/assignPanel.php' 
                       style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                              color: white; 
                              padding: 12px 30px; 
                              text-decoration: none; 
                              border-radius: 5px; 
                              display: inline-block;
                              font-weight: bold;'>
                        View Assignment Dashboard
                    </a>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 12px; text-align: center;'>
                    <p>This is an automated message from the Thesis Defense Scheduling System</p>
                </div>
            </div>
        </div>
        ";
    }
    
    /**
     * Email template for panelist assignment
     */
    private function getPanelistAssignmentEmailTemplate($groupName, $thesisTitle, $scheduleDate, $scheduleTime, $scheduleEndTime, $room, $role) {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
            <div style='background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>🎓 Panel Assignment</h1>
            </div>
            
            <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <h2 style='color: #333; margin-top: 0;'>You've Been Assigned!</h2>
                
                <p style='color: #666; line-height: 1.6;'>
                    You have been assigned as a <strong style='color: #43e97b;'>{$role}</strong> for an upcoming thesis defense.
                </p>
                
                <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #43e97b;'>
                    <h3 style='margin-top: 0; color: #166534;'>Thesis Group Details</h3>
                    <p style='margin: 5px 0;'><strong>Group Leader:</strong> {$groupName}</p>
                    <p style='margin: 5px 0;'><strong>Thesis Title:</strong> <em>{$thesisTitle}</em></p>
                </div>
                
                <div style='background: #eff6ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3b82f6;'>
                    <h3 style='margin-top: 0; color: #1e40af;'>Schedule Details</h3>
                    <p style='margin: 5px 0;'><strong>📅 Date:</strong> " . date('F j, Y', strtotime($scheduleDate)) . "</p>
                    <p style='margin: 5px 0;'><strong>🕐 Time:</strong> " . date('g:i A', strtotime($scheduleTime)) . " - " . date('g:i A', strtotime($scheduleEndTime)) . "</p>
                    <p style='margin: 5px 0;'><strong>📍 Venue:</strong> {$room}</p>
                    <p style='margin: 5px 0;'><strong>👤 Your Role:</strong> <span style='background: #43e97b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;'>{$role}</span></p>
                </div>
                
                <div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #92400e; font-size: 14px;'>
                        ⚠️ <strong>Important:</strong> Please mark your calendar and ensure you're available at the scheduled time.
                    </p>
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='http://localhost/THESIS/panelist/viewAssignment.php' 
                       style='background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); 
                              color: white; 
                              padding: 12px 30px; 
                              text-decoration: none; 
                              border-radius: 5px; 
                              display: inline-block;
                              font-weight: bold;'>
                        View Assignment Details
                    </a>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 12px; text-align: center;'>
                    <p>This is an automated message from the Thesis Defense Scheduling System</p>
                    <p>If you have any questions, please contact the admin.</p>
                </div>
            </div>
        </div>
        ";
    }
}