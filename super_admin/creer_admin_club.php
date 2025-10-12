<?php
/**
 * Création d'un administrateur de club - Backend PHP
 */

require_once '../config/database.php';
require_once '../config/session.php';

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
                $success = true;
                
                // Optionnel : Envoyer un email avec les identifiants
                // Ici vous pourriez ajouter l'envoi d'email avec les identifiants
                
                // Réinitialiser le formulaire
                $nom = $prenom = $email = $telephone = '';
            } else {
                $errors['general'] = "Erreur lors de la création de l'administrateur.";
            }
            
        } catch (PDOException $e) {
            $errors['general'] = "Erreur de base de données : " . $e->getMessage();
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
                            Administrateur créé avec succès !<br>
                            <strong>Mot de passe temporaire :</strong> <?php echo htmlspecialchars($mot_de_passe_temporaire ?? ''); ?>
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
                                   value="<?php echo htmlspecialchars($nom); ?>" required>
                            <?php if (isset($errors['nom'])): ?>
                                <div class="form-error-modern"><?php echo htmlspecialchars($errors['nom']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Prénom *</label>
                            <input type="text" name="prenom" class="form-input-modern" 
                                   value="<?php echo htmlspecialchars($prenom); ?>" required>
                            <?php if (isset($errors['prenom'])): ?>
                                <div class="form-error-modern"><?php echo htmlspecialchars($errors['prenom']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Email *</label>
                        <input type="email" name="email" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="form-error-modern"><?php echo htmlspecialchars($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Téléphone</label>
                        <input type="tel" name="telephone" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($telephone); ?>">
                        <?php if (isset($errors['telephone'])): ?>
                            <div class="form-error-modern"><?php echo htmlspecialchars($errors['telephone']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="info-box">
                        <div class="info-icon">ℹ️</div>
                        <div class="info-content">
                            <strong>Informations importantes :</strong>
                            <ul>
                                <li>Un mot de passe temporaire sera généré automatiquement</li>
                                <li>L'administrateur recevra ses identifiants par email</li>
                                <li>Il pourra modifier son mot de passe lors de sa première connexion</li>
                            </ul>
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
</body>
</html>
