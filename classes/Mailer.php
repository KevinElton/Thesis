<?php
/**
 * Mailer Class - Gmail SMTP Email Sender
 * 
 * SECURITY: Credentials are now loaded from config/env.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/../config/env.php';

class Mailer {
    
    /**
     * Send email via Gmail SMTP (SSL - Port 465)
     */
    public static function send($to, $subject, $body) {
        $mail = new PHPMailer(true);
        
        try {
            // Use SSL instead of TLS (more reliable)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            $mail->Port = 465; // SSL port
            
            // Connection settings
            $mail->Timeout = 60;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Sender and recipient
            $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
            $mail->addAddress($to);
            
            // Email content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            // Send
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Email Error to {$to}: " . $mail->ErrorInfo);
            return "Error: " . $mail->ErrorInfo;
        }
    }
    
    /**
     * Send with debug output (for testing only - disable in production)
     */
    public static function sendDebug($to, $subject, $body) {
        // Only allow debug mode if APP_DEBUG is true
        if (!defined('APP_DEBUG') || APP_DEBUG !== true) {
            return self::send($to, $subject, $body);
        }
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->Timeout = 60;
            
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            return "Error: " . $mail->ErrorInfo;
        }
    }
}
