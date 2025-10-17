<?php
// utilisateur/parametres.php
require_once '../config/database.php';
require_once '../config/session.php';

$currentPage = 'parametres';

requireLogin();
requireRole(['organisateur']);

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
            // Mettre à jour les informations de la session pour un affichage immédiat
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
// Récupérer le club associé à cet administrateur (pour header/sidebar)
$club_query = "SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id LIMIT 1";
$club_stmt = $db->prepare($club_query);
$club_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$club_stmt->execute();
$club = $club_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Event Manager</title>
    <!-- LIENS VERS VOS FICHIERS CSS MODERNES -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">Event Manager</a>
            <div class="header-right">
                <div class="club-info">
                    <span class="club-badge"></span>
                    <span><?php echo htmlspecialchars($club['NomClub'] ?? 'Mon Club'); ?></span>
                </div>
                <div class="user-section">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                        <div class="user-role">Administrateur du club</div>
                    </div>
                    <?php $initials = strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)); ?>
                    <div class="user-avatar-modern"><?php echo $initials; ?></div>
                    <button class="btn btn-ghost btn-sm" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="layout">
            <?php include __DIR__ . '/_sidebar.php'; ?>

            <main class="main-content">
                <div class="page-title">
                    <h1>Paramètres du compte</h1>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-xl">
            
            <!-- Section Informations personnelles -->
            <div class="card">
                <div class="card-header">
                    <h3>Informations personnelles</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($profile_message)): // Afficher le message de mise à jour du profil ?>
                        <div class="alert-modern <?php echo ($profile_message_type === 'error') ? 'alert-error-modern' : 'alert-success-modern'; ?>"><?php echo htmlspecialchars($profile_message); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="parametres.php">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-group">
                            <label for="email" class="form-label">Email (non modifiable)</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" readonly class="form-input" style="background-color: var(--neutral-100);">
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
                    <?php if (!empty($password_message)): // Afficher le message de changement de mot de passe ?>
                        <div class="alert-modern <?php echo ($password_message_type === 'error') ? 'alert-error-modern' : 'alert-success-modern'; ?>"><?php echo htmlspecialchars($password_message); ?></div>
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
                        <button type="submit" class="btn btn-secondary">Changer le mot de passe</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>