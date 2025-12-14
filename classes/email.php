<?php
/**
 * Email Class
 * Handles all email notifications for the Thesis Defense Scheduling System
 */
class Email {
    private $from_email = "noreply@thesisdefense.com";
    private $from_name = "Thesis Defense System";
    
    /**
     * Send email using PHP mail() function
     * In production, replace this with a proper mail service (PHPMailer, SendGrid, etc.)
     */
    private function sendEmail($to, $subject, $message) {
        // Set email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>" . "\r\n";
        $headers .= "Reply-To: {$this->from_email}" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // For development: Just return true (skip actual email sending)
        // This prevents errors during development
        return true;
        
        // Uncomment below to enable actual email sending in production
        // return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send panel assignment notification to panelists
     * 
     * @param int $group_id - The thesis group ID
     * @param array $panelistEmails - Array of panelist email addresses
     * @param array $scheduleInfo - Schedule details (date, time, room, mode, group_name)
     */
    public function sendPanelAssignmentNotification($group_id, $panelistEmails, $scheduleInfo) {
        if (empty($panelistEmails)) {
            return false;
        }
        
        $subject = "Panel Assignment - Thesis Defense";
        
        $message = $this->getEmailTemplate([
            'title' => 'Panel Assignment Notification',
            'body' => "
                <p>Dear Panelist,</p>
                <p>You have been assigned as a panel member for the following thesis defense:</p>
                
                <div style='background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 15px 0; color: #1f2937;'>Defense Details</h3>
                    <table style='width: 100%;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'><strong>Group:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['group_name']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'><strong>Date:</strong></td>
                            <td style='padding: 8px 0;'>" . date('F d, Y', strtotime($scheduleInfo['date'])) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'><strong>Time:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['time']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'><strong>Room:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['room']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'><strong>Mode:</strong></td>
                            <td style='padding: 8px 0;'>" . ucfirst(htmlspecialchars($scheduleInfo['mode'])) . "</td>
                        </tr>
                    </table>
                </div>
                
                <p>Please ensure your availability for this schedule. If you have any conflicts, please contact the administrator immediately.</p>
                
                <p style='margin-top: 30px;'>
                    <a href='http://localhost/Thesis/auth/login.php' 
                       style='background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                        View in Dashboard
                    </a>
                </p>
            "
        ]);
        
        $success = true;
        foreach ($panelistEmails as $email) {
            if (!empty($email)) {
                $result = $this->sendEmail($email, $subject, $message);
                if (!$result) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Send schedule change notification
     * 
     * @param int $group_id - The thesis group ID
     * @param array $panelistEmails - Array of panelist email addresses
     * @param array $scheduleInfo - Updated schedule details
     */
    public function sendScheduleChangeNotification($group_id, $panelistEmails, $scheduleInfo) {
        if (empty($panelistEmails)) {
            return false;
        }
        
        $isCancelled = ($scheduleInfo['date'] === 'CANCELLED');
        
        $subject = $isCancelled ? "Defense Schedule Cancelled" : "Defense Schedule Updated";
        
        if ($isCancelled) {
            $bodyContent = "
                <p>Dear Panelist,</p>
                <p>The thesis defense for <strong>" . htmlspecialchars($scheduleInfo['group_name']) . "'s group</strong> has been <span style='color: #dc2626; font-weight: bold;'>CANCELLED</span>.</p>
                
                <div style='background: #fee2e2; border-left: 4px solid #dc2626; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #991b1b;'><strong>⚠️ This schedule has been cancelled by the administrator.</strong></p>
                </div>
                
                <p>If you have any questions, please contact the administrator.</p>
            ";
        } else {
            $bodyContent = "
                <p>Dear Panelist,</p>
                <p>The thesis defense schedule you are assigned to has been updated:</p>
                
                <div style='background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 15px 0; color: #92400e;'>Updated Schedule</h3>
                    <table style='width: 100%;'>
                        <tr>
                            <td style='padding: 8px 0; color: #78350f;'><strong>Group:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['group_name']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #78350f;'><strong>New Date:</strong></td>
                            <td style='padding: 8px 0;'>" . date('F d, Y', strtotime($scheduleInfo['date'])) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #78350f;'><strong>New Time:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['time']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #78350f;'><strong>Room:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['room']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #78350f;'><strong>Mode:</strong></td>
                            <td style='padding: 8px 0;'>" . ucfirst(htmlspecialchars($scheduleInfo['mode'])) . "</td>
                        </tr>
                    </table>
                </div>
                
                <p>Please update your calendar accordingly. If you have any conflicts with the new schedule, please contact the administrator immediately.</p>
            ";
        }
        
        $message = $this->getEmailTemplate([
            'title' => $subject,
            'body' => $bodyContent . "
                <p style='margin-top: 30px;'>
                    <a href='http://localhost/Thesis/auth/login.php' 
                       style='background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                        View in Dashboard
                    </a>
                </p>
            "
        ]);
        
        $success = true;
        foreach ($panelistEmails as $email) {
            if (!empty($email)) {
                $result = $this->sendEmail($email, $subject, $message);
                if (!$result) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Send reminder notification for upcoming defense
     * 
     * @param array $panelistEmails - Array of panelist email addresses
     * @param array $scheduleInfo - Schedule details
     */
    public function sendDefenseReminder($panelistEmails, $scheduleInfo) {
        if (empty($panelistEmails)) {
            return false;
        }
        
        $subject = "Reminder: Upcoming Thesis Defense";
        
        $message = $this->getEmailTemplate([
            'title' => 'Defense Reminder',
            'body' => "
                <p>Dear Panelist,</p>
                <p>This is a reminder for the upcoming thesis defense:</p>
                
                <div style='background: #dbeafe; border-left: 4px solid #3b82f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 15px 0; color: #1e40af;'>Defense Details</h3>
                    <table style='width: 100%;'>
                        <tr>
                            <td style='padding: 8px 0; color: #1e3a8a;'><strong>Group:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['group_name']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #1e3a8a;'><strong>Date:</strong></td>
                            <td style='padding: 8px 0;'>" . date('F d, Y', strtotime($scheduleInfo['date'])) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #1e3a8a;'><strong>Time:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['time']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #1e3a8a;'><strong>Room:</strong></td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scheduleInfo['room']) . "</td>
                        </tr>
                    </table>
                </div>
                
                <p>Please be on time and prepared for the defense session.</p>
                
                <p style='margin-top: 30px;'>
                    <a href='http://localhost/Thesis/auth/login.php' 
                       style='background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                        View in Dashboard
                    </a>
                </p>
            "
        ]);
        
        $success = true;
        foreach ($panelistEmails as $email) {
            if (!empty($email)) {
                $result = $this->sendEmail($email, $subject, $message);
                if (!$result) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Get email template wrapper
     * 
     * @param array $data - Template data (title, body)
     * @return string - HTML email template
     */
    private function getEmailTemplate($data) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 24px;'>" . htmlspecialchars($data['title']) . "</h1>
                </div>
                <div class='content'>
                    " . $data['body'] . "
                </div>
                <div class='footer'>
                    <p style='margin: 0;'>This is an automated message from the Thesis Defense Scheduling System</p>
                    <p style='margin: 5px 0 0 0;'>Please do not reply to this email</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>

