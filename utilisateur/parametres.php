<?php
// utilisateur/parametres.php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
requireRole(['participant']);

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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - GestionEvents</title>
    <!-- Votre CSS sera inclus ici -->
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
        <h1>Paramètres du compte</h1>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
            
            <!-- Section Informations personnelles -->
            <div style="background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h2>Informations personnelles</h2>
                <hr style="margin: 15px 0;">

                <?php if ($profile_message): ?>
                    <div style="padding: 10px; margin-bottom: 15px; border-radius: 5px; <?php echo $profile_message_type === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>"><?php echo $profile_message; ?></div>
                <?php endif; ?>

                <form method="POST" action="parametres.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div style="margin-bottom: 15px;">
                        <label for="email">Email (non modifiable)</label><br>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" readonly style="width: 100%; padding: 8px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="prenom">Prénom</label><br>
                        <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user['Prenom']); ?>" required style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="nom">Nom</label><br>
                        <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user['Nom']); ?>" required style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label for="telephone">Téléphone</label><br>
                        <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user['Telephone']); ?>" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <button type="submit" style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer;">Mettre à jour le profil</button>
                </form>
            </div>

            <!-- Section Changer le mot de passe -->
            <div style="background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h2>Changer le mot de passe</h2>
                <hr style="margin: 15px 0;">

                <?php if ($password_message): ?>
                    <div style="padding: 10px; margin-bottom: 15px; border-radius: 5px; <?php echo $password_message_type === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>"><?php echo $password_message; ?></div>
                <?php endif; ?>

                <form method="POST" action="parametres.php">
                    <input type="hidden" name="action" value="change_password">
                    <div style="margin-bottom: 15px;">
                        <label for="current_password">Mot de passe actuel</label><br>
                        <input type="password" id="current_password" name="current_password" required style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="new_password">Nouveau mot de passe</label><br>
                        <input type="password" id="new_password" name="new_password" required minlength="6" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label for="confirm_password">Confirmer le nouveau mot de passe</label><br>
                        <input type="password" id="confirm_password" name="confirm_password" required style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <button type="submit" style="background-color: #28a745; color: white; padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer;">Changer le mot de passe</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>