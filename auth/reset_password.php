<?php
require_once '../config/database.php';

require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/src/Exception.php';// Pour PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

$errors = [];
$success = "";
$step = isset($_GET['step']) ? $_GET['step'] : 1;

// Fonction pour envoyer un email avec le code via PHPMailer
function sendVerificationCode($email, $code) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP sans SSL
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Changez selon votre serveur SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mohito.raihana@gmail.com'; // Votre email
        $mail->Password   = 'pqie uzik iuym wsgl'; // Mot de passe d'application
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS au lieu de SSL
        $mail->Port       = 587; // Port TLS
        
        // Si vous voulez désactiver complètement SSL/TLS (non recommandé en production)
        // $mail->SMTPSecure = false;
        // $mail->SMTPAutoTLS = false;
        // $mail->Port = 25; // ou 587
        
        // Options supplémentaires pour désactiver la vérification SSL
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom('noreply@eventmanager.com', 'Event Manager');
        $mail->addAddress($email);
        
        // Contenu
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Code de réinitialisation - Event Manager';
        $mail->Body    = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #6366f1; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; }
                .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 8px; color: #6366f1; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0;'>Réinitialisation de mot de passe</h1>
                </div>
                <div class='content'>
                    <p>Vous avez demandé à réinitialiser votre mot de passe.</p>
                    <p>Voici votre code de vérification :</p>
                    <div class='code-box'>$code</div>
                    <p><strong>Ce code est valable pendant 15 minutes.</strong></p>
                    <p style='color: #666; font-size: 14px;'>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email et votre mot de passe restera inchangé.</p>
                </div>
                <div class='footer'>
                    <p>Event Manager - Système de gestion d'événements</p>
                    <p>&copy; " . date('Y') . " Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email: {$mail->ErrorInfo}");
        return false;
    }
}

// ÉTAPE 1 : Demande d'email
if ($step == 1 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $errors[] = "Veuillez entrer votre adresse email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT IdUtilisateur FROM Utilisateur WHERE Email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Générer un code à 6 chiffres
                $code = sprintf("%06d", mt_rand(0, 999999));
                $expiration = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Stocker le code en session
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_code'] = $code;
                $_SESSION['reset_expiration'] = $expiration;
                $_SESSION['reset_attempts'] = 0;
                
                // Envoyer l'email
                if (sendVerificationCode($email, $code)) {
                    header("Location: reset_password.php?step=2");
                    exit();
                } else {
                    $errors[] = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
                }
            } else {
                $errors[] = "Aucun compte associé à cette adresse email.";
            }
        } catch (Exception $e) {
            $errors[] = "Erreur de connexion à la base de données.";
            error_log("Database error: " . $e->getMessage());
        }
    }
}

// ÉTAPE 2 : Vérification du code
if ($step == 2) {
    if (!isset($_SESSION['reset_email'])) {
        header("Location: reset_password.php?step=1");
        exit();
    }
    
    // Vérifier l'expiration
    if (strtotime($_SESSION['reset_expiration']) < time()) {
        $errors[] = "Le code a expiré. Veuillez recommencer.";
        session_unset();
        $step = 1;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $entered_code = trim($_POST['code']);
        
        if (empty($entered_code)) {
            $errors[] = "Veuillez entrer le code reçu.";
        } else {
            $_SESSION['reset_attempts']++;
            
            if ($entered_code == $_SESSION['reset_code']) {
                // Code correct
                header("Location: reset_password.php?step=3");
                exit();
            } else {
                if ($_SESSION['reset_attempts'] >= 3) {
                    // Générer un nouveau code après 3 tentatives
                    $new_code = sprintf("%06d", mt_rand(0, 999999));
                    $new_expiration = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    $_SESSION['reset_code'] = $new_code;
                    $_SESSION['reset_expiration'] = $new_expiration;
                    $_SESSION['reset_attempts'] = 0;
                    
                    sendVerificationCode($_SESSION['reset_email'], $new_code);
                    $errors[] = "Code incorrect. Un nouveau code a été envoyé à votre adresse email.";
                } else {
                    $remaining = 3 - $_SESSION['reset_attempts'];
                    $errors[] = "Code incorrect. Il vous reste $remaining tentative(s).";
                }
            }
        }
    }
}

// ÉTAPE 3 : Nouveau mot de passe
if ($step == 3) {
    if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_code'])) {
        header("Location: reset_password.php?step=1");
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $errors[] = "Veuillez remplir tous les champs.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $query = "UPDATE Utilisateur SET MotDePasse = :password WHERE Email = :email";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':email', $_SESSION['reset_email']);
                
                if ($stmt->execute()) {
                    $success = "Votre mot de passe a été réinitialisé avec succès !";
                    session_unset();
                    header("Refresh: 2; url=login.php");
                } else {
                    $errors[] = "Erreur lors de la réinitialisation du mot de passe.";
                }
            } catch (Exception $e) {
                $errors[] = "Erreur lors de la réinitialisation du mot de passe.";
                error_log("Update error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-modern">Event Manager</div>
            <p class="logo-subtitle">
                <?php 
                if ($step == 1) echo "Réinitialisation du mot de passe";
                elseif ($step == 2) echo "Vérification du code";
                else echo "Nouveau mot de passe";
                ?>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-modern">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-modern">
                <?php echo htmlspecialchars($success); ?>
                <br><small>Redirection vers la page de connexion...</small>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <form method="POST" action="">
                <div class="form-group-modern">
                    <label for="email" class="form-label-modern">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-input-modern" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="votre@email.com">
                    <small style="display: block; margin-top: 8px; color: #666;">
                        Entrez l'adresse email associée à votre compte.
                    </small>
                </div>

                <button type="submit" class="btn-login-modern">Envoyer le code</button>
            </form>

        <?php elseif ($step == 2): ?>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="margin: 0; color: #666; font-size: 14px;">
                    Un code de vérification a été envoyé à :<br>
                    <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
                </p>
            </div>

            <form method="POST" action="">
                <div class="form-group-modern">
                    <label for="code" class="form-label-modern">Code de vérification</label>
                    <input type="text" id="code" name="code" class="form-input-modern" required 
                           maxlength="6" pattern="[0-9]{6}"
                           placeholder="000000"
                           style="font-size: 24px; text-align: center; letter-spacing: 8px;">
                    <small style="display: block; margin-top: 8px; color: #666;">
                        Entrez le code à 6 chiffres reçu par email.
                    </small>
                </div>

                <button type="submit" class="btn-login-modern">Vérifier le code</button>
            </form>

            <div style="text-align: center; margin-top: 20px;">
                <a href="reset_password.php?step=1" style="color: #6366f1; text-decoration: none; font-size: 14px;">
                    ← Utiliser une autre adresse email
                </a>
            </div>

        <?php elseif ($step == 3): ?>
            <form method="POST" action="">
                <div class="form-group-modern">
                    <label for="new_password" class="form-label-modern">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password" class="form-input-modern" required 
                           minlength="6" placeholder="Minimum 6 caractères">
                </div>

                <div class="form-group-modern">
                    <label for="confirm_password" class="form-label-modern">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input-modern" required
                           placeholder="Confirmez votre mot de passe">
                </div>

                <button type="submit" class="btn-login-modern">Réinitialiser le mot de passe</button>
            </form>
        <?php endif; ?>

        <div class="divider-modern">
            <span>ou</span>
        </div>

        <div class="register-link-modern">
            <a href="login.php">← Retour à la connexion</a>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Auto-focus sur le champ de code et formatage
        <?php if ($step == 2): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('code');
            if (codeInput) {
                codeInput.focus();
                codeInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>