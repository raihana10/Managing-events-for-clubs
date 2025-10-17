<?php
require 'vendor/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/src/SMTP.php';
require 'vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Configuration SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mohito.raihana@gmail.com';
    $mail->Password = 'pqie uzik iuym wsgl';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // ✅ CONFIGURATION SSL MANUELLE
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Désactiver la vérification de certificat
    $mail->SMTPAutoTLS = false;
    
    // Timeout plus long
    $mail->Timeout = 30;
    
    // Debug (optionnel - à désactiver après)
    $mail->SMTPDebug = 2; // Affiche les logs de connexion
    $mail->Debugoutput = function($str, $level) {
        echo "Debug: $str<br>";
    };

    // Encodage
    $mail->CharSet = 'UTF-8';

    // Destinataires
    $mail->setFrom('mohito.raihana@gmail.com', 'Gestionnaire Événements');
    $mail->addAddress('oumaima.ameziane@etu.uae.ac.ma', 'Oumaima Ameziane');
    
    // Contenu
    $mail->isHTML(true);
    $mail->Subject = 'Test PHPMailer RÉUSSI - Gestion d\'événements';
    $mail->Body = '
        <h1 style="color: #27ae60;">🎉 Félicitations ! PHPMailer fonctionne !</h1>
        <p>Bonjour Oumaima,</p>
        <p>Notre système d\'envoi d\'emails pour la <strong>gestion d\'événements de clubs</strong> est maintenant opérationnel !</p>
        
        <div style="background-color: #e8f6f3; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <h3 style="color: #16a085;">📧 Détails de l\'envoi :</h3>
            <ul>
                <li><strong>Serveur :</strong> smtp.gmail.com</li>
                <li><strong>Port :</strong> 587</li>
                <li><strong>Date :</strong> ' . date('d/m/Y à H:i:s') . '</li>
                <li><strong>Statut :</strong> ✅ Envoyé avec succès</li>
            </ul>
        </div>
        
        <p>Cette configuration permettra d\'envoyer des notifications pour :</p>
        <ul>
            <li>📅 Les inscriptions aux événements</li>
            <li>🔔 Les rappels de réunions</li>
            <li>✅ Les confirmations de participation</li>
        </ul>
        
        <p>Cordialement,<br>
        <strong>Équipe de développement</strong></p>
    ';
    
    $mail->AltBody = 'Test PHPMailer RÉUSSI - Notre système d\'envoi d\'emails pour la gestion d\'événements de clubs est maintenant opérationnel. Date: ' . date('d/m/Y H:i:s');

    // Envoi
    $mail->send();
    
    // Message de succès
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>✅ Email Envoyé !</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 40px; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            .success-card { 
                background: white; 
                padding: 30px; 
                border-radius: 15px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                color: #333;
                text-align: center;
            }
            .success-icon { 
                font-size: 60px; 
                color: #27ae60; 
                margin-bottom: 20px;
            }
            .details {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                text-align: left;
            }
        </style>
    </head>
    <body>
        <div class="success-card">
            <div class="success-icon">✅</div>
            <h1 style="color: #27ae60; margin-bottom: 10px;">EMAIL ENVOYÉ AVEC SUCCÈS !</h1>
            <p style="font-size: 18px; color: #666;">L\'email a été envoyé à Oumaima Ameziane</p>
            
            <div class="details">
                <h3 style="color: #2c3e50; margin-top: 0;">📨 Détails de l\'envoi :</h3>
                <p><strong>De :</strong> mohito.raihana@gmail.com</p>
                <p><strong>À :</strong> oumaima.ameziane@etu.uae.ac.ma</p>
                <p><strong>Sujet :</strong> Test PHPMailer RÉUSSI - Gestion d\'événements</p>
                <p><strong>Date :</strong> ' . date('d/m/Y à H:i:s') . '</p>
                <p><strong>Statut :</strong> <span style="color: #27ae60; font-weight: bold;">✅ Livré</span></p>
            </div>
            
            <p style="color: #7f8c8d;">Vérifiez la boîte de réception de Oumaima pour confirmer la réception.</p>
        </div>
    </body>
    </html>';
    
} catch (Exception $e) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
            .error { background: white; padding: 20px; border-radius: 10px; border-left: 5px solid #e74c3c; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2 style="color: #e74c3c;">❌ Dernière erreur</h2>
            <p><strong>Erreur :</strong> ' . $mail->ErrorInfo . '</p>
            <p><strong>Solution :</strong> Le problème persiste malgré la configuration réseau.</p>
            <p>Essayez de redémarrer XAMPP complètement ou utilisez un autre réseau.</p>
        </div>
    </body>
    </html>';
}

// Désactiver le debug après test
$mail->SMTPDebug = 0;
?>