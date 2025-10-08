<?php
require 'vendor/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/src/SMTP.php';
require 'vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);

try {
    // Configuration du serveur
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'votre_email@gmail.com';  // À changer
    $mail->Password   = 'votre_mot_de_passe';     // À changer
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Destinataires
    $mail->setFrom('from@example.com', 'Mailer');
    $mail->addAddress('to@example.com', 'Joe User');

    // Contenu
    $mail->isHTML(true);
    $mail->Subject = 'Test PHPMailer';
    $mail->Body    = 'Ceci est le corps du message en <b>HTML</b>';
    $mail->AltBody = 'Ceci est le corps du message en texte brut';

    $mail->send();
    echo 'Message envoyé avec succès';
} catch (Exception $e) {
    echo "Le message n'a pas pu être envoyé. Erreur : {$mail->ErrorInfo}";
}
?>