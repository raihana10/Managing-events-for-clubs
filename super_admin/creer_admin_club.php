<?php
/**
 * Création d'un administrateur de club - Backend PHP
 */

require_once '../config/database.php';
require_once '../config/session.php';
require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fonction pour envoyer un email via PHPMailer
function envoyerEmailNouvelAdmin($destinataire_email, $destinataire_nom, $mot_de_passe) {
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
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->SMTPAutoTLS = false;
        $mail->Timeout = 30;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';

        // Expéditeur et destinataire
        $mail->setFrom('mohito.raihana@gmail.com', 'Event Manager - Administration');
        $mail->addAddress($destinataire_email, $destinataire_nom);
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = "Bienvenue sur Event Manager - Votre compte organisateur";
        
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                    <h1 style="color: white; margin: 0;">🎓 Event Manager</h1>
                    <p style="color: #f0f0f0; margin: 5px 0 0 0;">Gestion des événements de clubs</p>
                </div>
                
                <div style="background: white; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;">
                    <h2 style="color: #333; margin-top: 0;">Bienvenue ' . htmlspecialchars($destinataire_nom) . ' ! 🎉</h2>
                    
                    <p style="font-size: 16px; line-height: 1.6; color: #555;">
                        Votre compte <strong>organisateur de club</strong> a été créé avec succès sur Event Manager !
                    </p>
                    
                    <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
                        <h3 style="color: #856404; margin-top: 0;">🔐 Vos identifiants de connexion</h3>
                        <p style="margin: 10px 0;"><strong>Email :</strong> ' . htmlspecialchars($destinataire_email) . '</p>
                        <p style="margin: 10px 0;"><strong>Mot de passe temporaire :</strong></p>
                        <div style="background: #fff; padding: 15px; border-radius: 5px; text-align: center; margin: 10px 0;">
                            <code style="font-size: 20px; font-weight: bold; color: #d9534f; letter-spacing: 2px;">' . htmlspecialchars($mot_de_passe) . '</code>
                        </div>
                        <p style="color: #856404; margin: 10px 0;">⚠️ <strong>Important :</strong> Veuillez changer votre mot de passe lors de votre première connexion pour des raisons de sécurité.</p>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="color: #2c3e50; margin-top: 0;">📋 Vos privilèges en tant qu\'organisateur :</h3>
                        <ul style="color: #555; line-height: 1.8;">
                            <li>✅ Créer et gérer vos événements de club</li>
                            <li>📅 Planifier des réunions et activités</li>
                            <li>👥 Gérer les inscriptions des participants</li>
                            <li>📊 Consulter les statistiques de vos événements</li>
                            <li>📧 Communiquer avec vos membres</li>
                        </ul>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="https://votre-site.com/auth/login.php" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; font-size: 16px;">
                            🚀 Se connecter maintenant
                        </a>
                    </div>
                    
                    <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3;">
                        <p style="margin: 0; color: #0d47a1;">
                            <strong>💡 Conseil :</strong> Pensez à enregistrer ces identifiants en lieu sûr et à modifier votre mot de passe dès votre première connexion.
                        </p>
                    </div>
                    
                    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
                    
                    <p style="color: #666; font-size: 14px; margin: 0;">
                        Si vous avez des questions, n\'hésitez pas à contacter l\'équipe administrative.<br><br>
                        Cordialement,<br>
                        <strong>L\'équipe Event Manager</strong>
                    </p>
                    
                    <p style="color: #999; font-size: 12px; margin-top: 20px;">
                        📅 Email envoyé le ' . date('d/m/Y à H:i') . '
                    </p>
                </div>
            </div>
        ';
        
        $mail->AltBody = "Bienvenue sur Event Manager !\n\n" .
                        "Votre compte organisateur a été créé.\n\n" .
                        "Identifiants de connexion:\n" .
                        "Email: $destinataire_email\n" .
                        "Mot de passe temporaire: $mot_de_passe\n\n" .
                        "Veuillez changer votre mot de passe lors de votre première connexion.";

        $mail->send();
        return ['success' => true, 'message' => 'Email envoyé avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $mail->ErrorInfo];
    }
}

// Vérifier que c'est bien un super admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header('Location: ../auth/login.php');
    exit;
}

requireRole(['administrateur']);

// Initialiser la connexion à la base de données
$database = new Database();
$conn = $database->getConnection();

// Variables pour stocker les données et les erreurs
$nom = $prenom = $email = $telephone = '';
$errors = [];
$success = false;
$mot_de_passe_temporaire = '';
$email_envoye = false;

// Traitement du formulaire lorsqu'il est soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    
    // Validation du nom
    if (empty($nom)) {
        $errors['nom'] = "Le nom est obligatoire.";
    } elseif (strlen($nom) > 50) {
        $errors['nom'] = "Le nom ne peut pas dépasser 50 caractères.";
    }
    
    // Validation du prénom
    if (empty($prenom)) {
        $errors['prenom'] = "Le prénom est obligatoire.";
    } elseif (strlen($prenom) > 50) {
        $errors['prenom'] = "Le prénom ne peut pas dépasser 50 caractères.";
    }
    
    // Validation de l'email
    if (empty($email)) {
        $errors['email'] = "L'email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'email n'est pas valide.";
    } elseif (strlen($email) > 100) {
        $errors['email'] = "L'email ne peut pas dépasser 100 caractères.";
    } else {
        // Vérifier si l'email existe déjà
        try {
            $sql_check_email = "SELECT IdUtilisateur FROM Utilisateur WHERE Email = :email";
            $stmt_check_email = $conn->prepare($sql_check_email);
            $stmt_check_email->bindParam(':email', $email);
            $stmt_check_email->execute();
            
            if ($stmt_check_email->rowCount() > 0) {
                $errors['email'] = "Un utilisateur avec cet email existe déjà.";
            }
        } catch (PDOException $e) {
            $errors['general'] = "Erreur lors de la vérification de l'email.";
        }
    }
    
    // Validation du téléphone (optionnel)
    if (!empty($telephone) && strlen($telephone) > 20) {
        $errors['telephone'] = "Le numéro de téléphone ne peut pas dépasser 20 caractères.";
    }
    
    // Si aucune erreur, insérer dans la base de données
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Générer un mot de passe temporaire
            $mot_de_passe_temporaire = bin2hex(random_bytes(8));
            $mot_de_passe_hash = password_hash($mot_de_passe_temporaire, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO Utilisateur (Nom, Prenom, Email, Telephone, MotDePasse, Role, DateInscription) 
                    VALUES (:nom, :prenom, :email, :telephone, :mot_de_passe, 'organisateur', CURDATE())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':prenom', $prenom);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':telephone', $telephone);
            $stmt->bindParam(':mot_de_passe', $mot_de_passe_hash);
            
            if ($stmt->execute()) {
                $admin_id = $conn->lastInsertId();
                
                // 🔥 ENVOI RÉEL DE L'EMAIL avec les identifiants
                $nom_complet = $prenom . ' ' . $nom;
                $resultat = envoyerEmailNouvelAdmin($email, $nom_complet, $mot_de_passe_temporaire);
                
                if ($resultat['success']) {
                    // Enregistrer dans la BDD seulement si envoyé
                    $objet = "Bienvenue sur Event Manager - Votre compte organisateur";
                    $contenu = "Bonjour $nom_complet,\n\n";
                    $contenu .= "Votre compte organisateur a été créé avec succès.\n\n";
                    $contenu .= "Identifiants:\nEmail: $email\nMot de passe: $mot_de_passe_temporaire\n\n";
                    $contenu .= "Veuillez changer votre mot de passe lors de votre première connexion.";
                    
                    $sql_email = "INSERT INTO EmailAdmin (IdAdmin, DestinataireEmail, DestinataireNom, Objet, Contenu, TypeEmail, DateEnvoi) 
                                VALUES (:id_admin, :destinataire_email, :destinataire_nom, :objet, :contenu, 'creation_organisateur', NOW())";

                    $stmt_email = $conn->prepare($sql_email);
                    $id_admin_session = $_SESSION['user_id'];
                    $stmt_email->bindParam(':id_admin', $id_admin_session);
                    $stmt_email->bindParam(':destinataire_email', $email);
                    $stmt_email->bindParam(':destinataire_nom', $nom_complet);
                    $stmt_email->bindParam(':objet', $objet);
                    $stmt_email->bindParam(':contenu', $contenu);
                    $stmt_email->execute();
                    
                    $email_envoye = true;
                }
                
                $conn->commit();
                $success = true;
                
                // Réinitialiser le formulaire uniquement si tout est OK
                if ($email_envoye) {
                    // On garde $mot_de_passe_temporaire pour l'afficher
                    $nom = $prenom = $email = $telephone = '';
                }
            } else {
                throw new Exception("Erreur lors de la création de l'administrateur.");
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors['general'] = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un administrateur - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">🎓 Event Manager</a>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                    <div class="user-role">Super Administrateur</div>
                </div>
                <div class="user-avatar-modern"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1)); ?></div>
                <a href="../auth/logout.php" class="btn btn-ghost btn-sm">Déconnexion</a>
            </div>
        </div>
    </nav>

    <aside class="sidebar-modern">
        <nav class="sidebar-nav-modern">
            <div class="sidebar-section-modern">
                <div class="sidebar-title-modern">Administration</div>
                <ul class="sidebar-nav-modern">
                    <li class="sidebar-nav-item-modern">
                        <a href="dashboard.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">📊</div>
                            Tableau de bord
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="gerer_clubs.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">🏛️</div>
                            Gérer les clubs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="liste_admins.php" class="sidebar-nav-link-modern active">
                            <div class="sidebar-nav-icon-modern">👥</div>
                            Admins des clubs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="evenements.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">📅</div>
                            Les événements
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="utilisateurs.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">👤</div>
                            Les utilisateurs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="emails.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">📧</div>
                            Envoyer un email
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="validations.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">✅</div>
                            Validations
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>

    <div class="layout">
        <main class="main-content">
            <div class="page-title">
                <div>
                    <h1>Créer un administrateur</h1>
                    <p>Ajoutez un nouvel organisateur de club</p>
                </div>
                <a href="liste_admins.php" class="btn btn-ghost">
                    ← Retour à la liste
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert-modern alert-success-modern">
                    <div class="alert-icon-modern">✅</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Succès</div>
                        <div class="alert-message-modern">
                            <strong>Administrateur créé avec succès !</strong><br><br>
                            <?php if ($email_envoye): ?>
                                ✉️ Un email contenant les identifiants a été envoyé à l'organisateur.<br><br>
                            <?php endif; ?>
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 10px; border-left: 4px solid #ffc107;">
                                <strong>🔐 Mot de passe temporaire :</strong><br>
                                <code style="font-size: 18px; font-weight: bold; color: #d9534f; background: white; padding: 8px 15px; border-radius: 5px; display: inline-block; margin-top: 8px;">
                                    <?php echo htmlspecialchars($mot_de_passe_temporaire); ?>
                                </code>
                                <p style="margin: 10px 0 0 0; font-size: 14px; color: #856404;">
                                    ⚠️ Notez ce mot de passe, il ne sera plus affiché après actualisation de la page.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert-modern alert-error-modern">
                    <div class="alert-icon-modern">❌</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Erreur</div>
                        <div class="alert-message-modern"><?php echo htmlspecialchars($errors['general']); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section-modern">
                <h3>Informations de l'administrateur</h3>
                <form method="POST" class="form-modern">
                    <div class="form-row">
                        <div class="form-group-modern">
                            <label class="form-label-modern">Nom *</label>
                            <input type="text" name="nom" class="form-input-modern" 
                                   value="<?php echo htmlspecialchars($nom); ?>" 
                                   placeholder="Nom de famille"
                                   required>
                            <?php if (isset($errors['nom'])): ?>
                                <div class="form-error-modern"><?php echo htmlspecialchars($errors['nom']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Prénom *</label>
                            <input type="text" name="prenom" class="form-input-modern" 
                                   value="<?php echo htmlspecialchars($prenom); ?>" 
                                   placeholder="Prénom"
                                   required>
                            <?php if (isset($errors['prenom'])): ?>
                                <div class="form-error-modern"><?php echo htmlspecialchars($errors['prenom']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Email *</label>
                        <input type="email" name="email" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               placeholder="email@exemple.com"
                               required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="form-error-modern"><?php echo htmlspecialchars($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Téléphone</label>
                        <input type="tel" name="telephone" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($telephone); ?>"
                               placeholder="06 12 34 56 78">
                        <?php if (isset($errors['telephone'])): ?>
                            <div class="form-error-modern"><?php echo htmlspecialchars($errors['telephone']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="info-box" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <div style="display: flex; align-items: start; gap: 10px;">
                            <div style="font-size: 24px;">ℹ️</div>
                            <div>
                                <strong style="color: #0d47a1;">Informations importantes :</strong>
                                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #555;">
                                    <li>Un mot de passe temporaire sera généré automatiquement</li>
                                    <li>📧 L'organisateur recevra ses identifiants par email</li>
                                    <li>🔒 Il devra modifier son mot de passe lors de sa première connexion</li>
                                    <li>Le rôle "organisateur" lui permettra de gérer les événements de ses clubs</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-modern">
                        <a href="liste_admins.php" class="btn btn-ghost">
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-icon">➕</span>
                            Créer l'administrateur
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Auto-hide success message after 30 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success-modern');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.transition = 'opacity 0.5s ease';
                    successAlert.style.opacity = '0';
                    setTimeout(() => successAlert.remove(), 500);
                }, 30000);
            }
        });
    </script>
</body>
</html>