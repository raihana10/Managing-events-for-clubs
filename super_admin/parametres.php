<?php
// utilisateur/parametres.php
require_once '../config/database.php';
require_once '../config/session.php';

$currentPage = 'parametres';

requireLogin();
requireRole(['administrateur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Initialisation des messages pour les deux formulaires
$profile_message = '';
$profile_message_type = '';
$password_message = '';
$password_message_type = '';

// Traitement du formulaire de mise à jour du profil
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $telephone = trim($_POST['telephone']);

    if (empty($nom) || empty($prenom)) {
        $profile_message = "Le nom et le prénom ne peuvent pas être vides.";
        $profile_message_type = "error";
    } else {
        $query_update = "UPDATE Utilisateur SET Nom = :nom, Prenom = :prenom, Telephone = :telephone WHERE IdUtilisateur = :id_utilisateur";
        $stmt_update = $db->prepare($query_update);
        $stmt_update->bindParam(':nom', $nom);
        $stmt_update->bindParam(':prenom', $prenom);
        $stmt_update->bindParam(':telephone', $telephone);
        $stmt_update->bindParam(':id_utilisateur', $user_id);

        if ($stmt_update->execute()) {
            $_SESSION['nom'] = $nom;
            $_SESSION['prenom'] = $prenom;
            $profile_message = "Votre profil a été mis à jour avec succès.";
            $profile_message_type = "success";
        } else {
            $profile_message = "Erreur lors de la mise à jour du profil.";
            $profile_message_type = "error";
        }
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Récupérer le mot de passe actuel de l'utilisateur
    $query_pass = "SELECT MotDePasse FROM Utilisateur WHERE IdUtilisateur = :id_utilisateur";
    $stmt_pass = $db->prepare($query_pass);
    $stmt_pass->bindParam(':id_utilisateur', $user_id);
    $stmt_pass->execute();
    $user_data = $stmt_pass->fetch(PDO::FETCH_ASSOC);

    if ($user_data && password_verify($current_password, $user_data['MotDePasse'])) {
        if (strlen($new_password) < 6) {
            $password_message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
            $password_message_type = "error";
        } elseif ($new_password !== $confirm_password) {
            $password_message = "Les nouveaux mots de passe ne correspondent pas.";
            $password_message_type = "error";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query_update_pass = "UPDATE Utilisateur SET MotDePasse = :password WHERE IdUtilisateur = :id_utilisateur";
            $stmt_update_pass = $db->prepare($query_update_pass);
            $stmt_update_pass->bindParam(':password', $hashed_password);
            $stmt_update_pass->bindParam(':id_utilisateur', $user_id);

            if ($stmt_update_pass->execute()) {
                $password_message = "Votre mot de passe a été changé avec succès.";
                $password_message_type = "success";
            } else {
                $password_message = "Erreur lors du changement de mot de passe.";
                $password_message_type = "error";
            }
        }
    } else {
        $password_message = "Le mot de passe actuel est incorrect.";
        $password_message_type = "error";
    }
}

// Récupérer les informations actuelles de l'utilisateur pour pré-remplir le formulaire
$query_user = "SELECT Nom, Prenom, Email, Telephone FROM Utilisateur WHERE IdUtilisateur = :id_utilisateur";
$stmt_user = $db->prepare($query_user);
$stmt_user->bindParam(':id_utilisateur', $user_id);
$stmt_user->execute();
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
        }

        .header-modern {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid #f0f0f0;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            height: 70px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .logo-modern {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            letter-spacing: -0.5px;
        }

        .nav-main {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link-modern {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link-modern:hover {
            color: white;
        }

        .nav-link-modern.active {
            color: white;
        }

        .nav-link-modern.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            right: 0;
            height: 2px;
            background: white;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-left: auto;
        }

        .user-info {
            text-align: right;
            color: white;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .user-avatar-modern {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-ghost {
            background: transparent;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.4);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            color: white;
        }

        .main-content {
            margin-top: 70px;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            margin-bottom: 2rem;
        }

        .page-title h1 {
            font-size: 2rem;
            color: #333;
            margin: 0;
        }

        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-cols-1 {
            grid-template-columns: 1fr;
        }

        @media (min-width: 1024px) {
            .lg\:grid-cols-2 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            background: #fafafa;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #333;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #333;
            font-size: 0.95rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .alert-modern {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success-modern {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error-modern {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }

            .nav-main {
                display: none;
            }

            .user-section {
                gap: 1rem;
            }

            .user-info {
                display: none;
            }

            .main-content {
                padding: 1rem;
            }

            .page-title h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER FIXE -->
    <header class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">Event Manager</a>
            <nav class="nav-main"></nav>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                    <div class="user-role">Administrateur</div>
                </div>
                <?php $initials = strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)); ?>
                <div class="user-avatar-modern"><?php echo $initials; ?></div>
                <a href="../auth/logout.php" class="btn btn-ghost btn-sm">Déconnexion</a>
            </div>
        </div>
    </header>

    <!-- CONTENU PRINCIPAL -->
    <div class="main-content">
        <div class="container">
            <div class="page-title">
                <h1>Paramètres du compte</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2">
                
                <!-- Section Informations personnelles -->
                <div class="card">
                    <div class="card-header">
                        <h3>Informations personnelles</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($profile_message)): ?>
                            <div class="alert-modern <?php echo $profile_message_type === 'success' ? 'alert-success-modern' : 'alert-error-modern'; ?>">
                                <?php echo htmlspecialchars($profile_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="parametres.php">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-group">
                                <label for="email" class="form-label">Email (non modifiable)</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" disabled class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="prenom" class="form-label">Prénom</label>
                                <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user['Prenom']); ?>" required class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user['Nom']); ?>" required class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user['Telephone']); ?>" class="form-input">
                            </div>
                            <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        </form>
                    </div>
                </div>

                <!-- Section Changer le mot de passe -->
                <div class="card">
                    <div class="card-header">
                        <h3>Changer le mot de passe</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($password_message)): ?>
                            <div class="alert-modern <?php echo $password_message_type === 'success' ? 'alert-success-modern' : 'alert-error-modern'; ?>">
                                <?php echo htmlspecialchars($password_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="parametres.php">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                <input type="password" id="current_password" name="current_password" required class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="form-input">
                            </div>
                            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>