<?php
require_once '../config/database.php';
session_start();

// Si déjà connecté, rediriger
if (isset($_SESSION['user_id'])) {
    switch($_SESSION['role']) {
        case 'super_admin':
            header("Location: ../super_admin/dashboard.php");
            break;
        case 'admin_club':
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
                    $_SESSION['role'] = 'super_admin';
                } elseif ($dbRole === 'organisateur') {
                    $_SESSION['role'] = 'admin_club';
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
                    case 'super_admin':
                        header("Location: ../super_admin/dashboard.php");
                        break;
                    case 'admin_club':
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            padding: 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .error {
            background: #fee;
            border-left: 4px solid #f44;
            color: #c33;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🎉 Event Manager</h1>
            <p>Connectez-vous à votre compte</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="votre@email.com">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required 
                       placeholder="••••••••">
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    Se souvenir de moi
                </label>
                <a href="#" class="forgot-password">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn-primary">Se connecter</button>
        </form>

        <div class="register-link">
            Vous n'avez pas de compte ? <a href="register.php">S'inscrire</a>
        </div>
    </div>
</body>
</html>