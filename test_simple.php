<?php
require 'vendor/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/src/SMTP.php';
require 'vendor/phpmailer/src/Exception.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);
echo "✅ PHPMailer installé avec succès ! Version : " . $mail::VERSION;
?>