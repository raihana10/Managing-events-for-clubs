<?php
require_once '../config/database.php';
define('RECAPTCHA_SECRET_KEY','6LcxwuUrAAAAAH0hZtRGuIe8yOibf5dTGtSS38OW');
session_start();

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
        $recaptcha_response = $_POST['g-recaptcha-response'];
        
        // Préparer la requête à l'API de Google
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $recaptcha_response
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $json_result = json_decode($result, true);

        // Si Google dit que la validation a échoué
         if (!$json_result['success']) {
            $errors[] = "La vérification reCAPTCHA a échoué. Veuillez réessayer.";
        }
    } else {
        // Si la case n'a même pas été cochée
        $errors[] = "Veuillez cocher la case 'Je ne suis pas un robot'.";
    }

    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $errors[] = "Tous les champs obligatoires doivent être remplis.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }

    // Captcha retiré (vous pourrez réimplémenter votre propre mécanisme)

    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT IdUtilisateur FROM Utilisateur WHERE Email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $errors[] = "Cet email est déjà utilisé.";
        }
    }

    // Insertion dans la base
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        $query = "INSERT INTO Utilisateur (Nom, Prenom, Email, MotDePasse, Telephone, Role, TokenVerification) 
                  VALUES (:nom, :prenom, :email, :password, :telephone, 'participant', :token)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':token', $token);

        if ($stmt->execute()) {
            // TODO: Envoyer email de vérification
            $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
        } else {
            $errors[] = "Erreur lors de l'inscription.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="auth-page">
    <div class="register-container">
        <div class="logo-section">
            <div class="logo-modern">Event Manager</div>
            <p class="logo-subtitle">Créez votre compte</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-modern">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-modern"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <div class="form-row-modern">
                <div class="form-group-modern">
                    <label for="nom" class="form-label-modern">Nom *</label>
                    <input type="text" id="nom" name="nom" class="form-input-modern" required 
                           value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>"
                           placeholder="Votre nom">
                </div>

                <div class="form-group-modern">
                    <label for="prenom" class="form-label-modern">Prénom *</label>
                    <input type="text" id="prenom" name="prenom" class="form-input-modern" required 
                           value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>"
                           placeholder="Votre prénom">
                </div>
            </div>

            <div class="form-group-modern">
                <label for="email" class="form-label-modern">Adresse email *</label>
                <input type="email" id="email" name="email" class="form-input-modern" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="votre@email.com">
            </div>

            <div class="form-group-modern">
                <label for="telephone" class="form-label-modern">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" class="form-input-modern"
                       value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>"
                       placeholder="Votre numéro de téléphone">
            </div>

            <div class="form-group-modern">
                <label for="password" class="form-label-modern">Mot de passe *</label>
                <input type="password" id="password" name="password" class="form-input-modern" required 
                       minlength="6" placeholder="Minimum 6 caractères">
            </div>

            <div class="form-group-modern">
                <label for="confirm_password" class="form-label-modern">Confirmer le mot de passe *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input-modern" required
                       placeholder="Confirmez votre mot de passe">
            </div>

            <div class="captcha-section-modern">
                <div class="g-recaptcha" data-sitekey="6LcxwuUrAAAAADuwTqnQq54AwIgsWsAWQxPVzoj4"></div>
            </div>

            <button type="submit" class="btn-register-modern">Créer mon compte</button>
        </form>

        <div class="divider-modern">
            <span>ou</span>
        </div>

        <div class="login-link-modern">
            Vous avez déjà un compte ? <a href="login.php">Se connecter</a>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>