<?php
// class/Mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/Exception.php';

class Mailer {
    public static function send($to, $subject, $body) {
        $mail = new PHPMailer(true);
        try {
            // SMTP settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->AuthType = 'LOGIN';
            $mail->Username = 'standinkevin30@gmail.com';   // Replace with your Gmail
            $mail->Password = 'gyunkbhxcqlwpuzn';      // Replace with your Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('standinkevin30@gmail.com', 'Thesis');
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            return $mail->ErrorInfo;
        }
    }
}
