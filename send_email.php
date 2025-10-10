<?php
require 'vendor/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/src/SMTP.php';
require 'vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Configuration Gmail
$smtp_host = 'smtp.gmail.com';
$smtp_username = 'mohito.raihana@gmail.com';  // REMPLACEZ par votre Gmail
$smtp_password = 'pqie uzik iuym wsgl';  // REMPLACEZ par le mot de passe d'application
$smtp_port = 587;

$mail = new PHPMailer(true);

try {
    // Configuration du serveur SMTP
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = $smtp_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtp_port;
    
    // Encodage
    $mail->CharSet = 'UTF-8';

    // Destinataires
    $mail->setFrom($smtp_username, 'Gestionnaire Événements');
    $mail->addAddress('oumaima.ameziane@etu.uae.ac.ma', 'Oumaima Ameziane');
    
    // Contenu de l'email
    $mail->isHTML(true);
    $mail->Subject = 'Test PHPMailer - Gestion d\'événements pour clubs';
    $mail->Body    = '
        <h1 style="color: #2c3e50;">✅ Test PHPMailer Réussi !</h1>
        <p>Bonjour Oumaima,</p>
        <p>Ceci est un test d\'envoi d\'email depuis notre application de <strong>gestion d\'événements pour clubs</strong>.</p>
        
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <h3 style="color: #e74c3c;">📋 Détails techniques :</h3>
            <ul>
                <li><strong>Version PHPMailer :</strong> ' . $mail::VERSION . '</li>
                <li><strong>Serveur SMTP :</strong> ' . $smtp_host . '</li>
                <li><strong>Date d\'envoi :</strong> ' . date('d/m/Y à H:i:s') . '</li>
                <li><strong>Projet :</strong> Managing Events for Clubs</li>
            </ul>
        </div>
        
        <p>Si vous recevez cet email, cela signifie que la configuration PHPMailer fonctionne parfaitement ! 🎉</p>
        
        <p>Cordialement,<br>
        <strong>Équipe de développement</strong></p>
    ';
    
    $mail->AltBody = 'Test PHPMailer Réussi ! Bonjour Oumaima, ceci est un test d\'envoi d\'email depuis notre application de gestion d\'événements pour clubs. Version PHPMailer : ' . $mail::VERSION . ' - Date : ' . date('d/m/Y H:i:s');

    $mail->send();
    
    // Message de confirmation
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Email envoyé</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
            .success { background: white; padding: 20px; border-radius: 10px; border-left: 5px solid #27ae60; }
            .info { background: #e8f4fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="success">
            <h2 style="color: #27ae60;">✅ Email envoyé avec succès !</h2>
            <p><strong>À :</strong> oumaima.ameziane@etu.uae.ac.ma</p>
            <p><strong>Sujet :</strong> Test PHPMailer - Gestion d\'événements pour clubs</p>
            <p><strong>Date :</strong> ' . date('d/m/Y à H:i:s') . '</p>
            
            <div class="info">
                <h3>📨 Vérifiez :</h3>
                <p>L\'email a été envoyé à Oumaima Ameziane. Vérifiez sa boîte de réception.</p>
            </div>
        </div>
    </body>
    </html>';
    
} catch (Exception $e) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur d\'envoi</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
            .error { background: white; padding: 20px; border-radius: 10px; border-left: 5px solid #e74c3c; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2 style="color: #e74c3c;">❌ Erreur d\'envoi</h2>
            <p><strong>Erreur :</strong> ' . $mail->ErrorInfo . '</p>
            <h3>🔧 Vérifiez :</h3>
            <ul>
                <li>Vos identifiants Gmail sont corrects</li>
                <li>Vous avez généré un <strong>mot de passe d\'application</strong></li>
                <li>L\'authentification à 2 facteurs est activée</li>
                <li>Votre connexion Internet fonctionne</li>
            </ul>
        </div>
    </body>
    </html>';
}
?>