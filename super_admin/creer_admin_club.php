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
    <title>Créer un administrateur de club</title>
    <link rel="stylesheet" href="../frontend/css.css">
    <style>
        body { margin: 0; background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-size: 1.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 2em;
            color: #333;
            margin-bottom: 10px;
        }
        .page-header p {
            color: #666;
        }
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .form-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #555;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .error {
            color: #f44336;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-box strong {
            color: #1565c0;
        }
        .credentials-box {
            background: #fff3e0;
            border: 2px solid #ff9800;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .credentials-box h4 {
            color: #e65100;
            margin-bottom: 10px;
        }
        .credentials-box p {
            margin: 5px 0;
            font-family: monospace;
            background: #f5f5f5;
            padding: 8px;
            border-radius: 5px;
        }
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🎓 GestionEvents</div>
        <a href="dashboard.php" class="btn btn-secondary">Retour au dashboard</a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>👤 Créer un administrateur de club</h1>
            <p>Créez un nouveau compte administrateur pour gérer un club</p>
        </div>

        <?php if ($success): ?>
            <div class="success-message">
                ✅ <strong>Administrateur créé avec succès !</strong><br>
                L'administrateur a été créé et peut maintenant se connecter.
            </div>
            
            <div class="credentials-box">
                <h4>🔑 Identifiants de connexion</h4>
                <p><strong>Email :</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Mot de passe temporaire :</strong> <?php echo htmlspecialchars($mot_de_passe_temporaire); ?></p>
                <p style="color: #e65100; font-size: 0.9em; margin-top: 10px;">
                    ⚠️ <strong>Important :</strong> Transmettez ces identifiants à l'administrateur. 
                    Il devra changer son mot de passe lors de sa première connexion.
                </p>
            </div>
            
            <div class="form-actions">
                <a href="liste_admins.php" class="btn btn-primary">Voir tous les administrateurs</a>
                <a href="creer_admin_club.php" class="btn btn-secondary">Créer un autre administrateur</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors['general'])): ?>
                <div class="error" style="background: #ffebee; border: 1px solid #f44336; color: #c62828; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="adminForm">
                <div class="form-section">
                    <h3>📝 Informations personnelles</h3>
                    
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" 
                               id="nom" 
                               name="nom" 
                               placeholder="Ex: Dupont" 
                               required
                               maxlength="50"
                               value="<?php echo htmlspecialchars($nom); ?>">
                        <?php if (isset($errors['nom'])): ?>
                            <span class="error"><?php echo htmlspecialchars($errors['nom']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" 
                               id="prenom" 
                               name="prenom" 
                               placeholder="Ex: Jean" 
                               required
                               maxlength="50"
                               value="<?php echo htmlspecialchars($prenom); ?>">
                        <?php if (isset($errors['prenom'])): ?>
                            <span class="error"><?php echo htmlspecialchars($errors['prenom']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>📧 Informations de connexion</h3>
                    
                    <div class="info-box">
                        <strong>ℹ️ Information :</strong> Un mot de passe temporaire sera généré automatiquement. 
                        L'administrateur devra le changer lors de sa première connexion.
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               placeholder="Ex: jean.dupont@email.com" 
                               required
                               maxlength="100"
                               value="<?php echo htmlspecialchars($email); ?>">
                        <?php if (isset($errors['email'])): ?>
                            <span class="error"><?php echo htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="telephone">Téléphone (optionnel)</label>
                        <input type="tel" 
                               id="telephone" 
                               name="telephone" 
                               placeholder="Ex: 0123456789" 
                               maxlength="20"
                               value="<?php echo htmlspecialchars($telephone); ?>">
                        <?php if (isset($errors['telephone'])): ?>
                            <span class="error"><?php echo htmlspecialchars($errors['telephone']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="liste_admins.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        ✓ Créer l'administrateur
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Confirmation avant de quitter si formulaire modifié
        let formModified = false;
        document.getElementById('adminForm').addEventListener('input', function() {
            formModified = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (formModified) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.getElementById('adminForm').addEventListener('submit', function() {
            formModified = false;
        });
    </script>
</body>
</html>
