<?php
require_once '../config/database.php';
session_start();

// Éviter les boucles de redirection
if (isset($_GET['redirect_loop'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Si déjà connecté, rediriger
if (isset($_SESSION['user_id'])) {
    switch($_SESSION['role']) {
        case 'administrateur':
            header("Location: ../super_admin/dashboard.php");
            break;
        case 'organisateur':
            header("Location: ../admin_club/dashboard.php");
            break;
        case 'participant':
            header("Location: ../utilisateur/dashboard.php");
            break;
    }
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT IdUtilisateur, Nom, Prenom, Email, MotDePasse, Role 
                  FROM Utilisateur WHERE Email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();

            if (password_verify($password, $user['MotDePasse'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['IdUtilisateur'];
                $_SESSION['nom'] = $user['Nom'];
                $_SESSION['prenom'] = $user['Prenom'];
                $_SESSION['email'] = $user['Email'];
                // Normaliser le rôle selon le schéma SQL fourni
                $dbRole = trim(strtolower($user['Role']));
                if ($dbRole === 'administrateur') {
                    $_SESSION['role'] = 'administrateur';
                } elseif ($dbRole === 'organisateur') {
                    $_SESSION['role'] = 'organisateur';
                } elseif ($dbRole === 'participant') {
                    $_SESSION['role'] = 'participant';
                } else {
                    // par défaut, considérer comme participant
                    $_SESSION['role'] = 'participant';
                }

                // Remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), "/");
                    // TODO: Stocker le token en base
                }

                // Redirection selon le rôle
                switch($_SESSION['role']) {
                    case 'administrateur':
                        header("Location: ../super_admin/dashboard.php");
                        break;
                    case 'organisateur':
                        header("Location: ../admin_club/dashboard.php");
                        break;
                    case 'participant':
                        header("Location: ../utilisateur/dashboard.php");
                        break;
                }
                exit();
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-modern">Event Manager</div>
            <p class="logo-subtitle">Connectez-vous à votre compte</p>
        </div>

        <?php if ($error): ?>
            <div class="error-modern"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group-modern">
                <label for="email" class="form-label-modern">Adresse email</label>
                <input type="email" id="email" name="email" class="form-input-modern" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="votre@email.com">
            </div>

            <div class="form-group-modern">
                <label for="password" class="form-label-modern">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-input-modern" required 
                       placeholder="••••••••">
            </div>

            <div class="form-options-modern">
                <label class="remember-me-modern">
                    <input type="checkbox" name="remember">
                    Se souvenir de moi
                </label>
                <a href="reset_password.php" class="forgot-password-modern">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn-login-modern">Se connecter</button>
        </form>

        <div class="divider-modern">
            <span>ou</span>
        </div>

        <div class="register-link-modern">
            Vous n'avez pas de compte ? <a href="register.php">Créer un compte</a>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>