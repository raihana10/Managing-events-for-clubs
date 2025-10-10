<?php
/**
 * Création d'un club - Backend PHP
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
$creer_nouvel_organisateur = false; 
// Variables pour stocker les données et les erreurs
$nom_club = $description = $id_admin_club = $logo_existant = '';
$organisateur_email = $organisateur_nom = $organisateur_prenom = $organisateur_telephone = '';
$errors = [];
$success = false;
$edition_mode = false;
$club_id = null;
$email_sent = false;

// Vérifier si on est en mode édition
if (isset($_GET['edit']) || isset($_POST['club_id'])) {
    $edition_mode = true;
    $club_id = $_GET['edit'] ?? $_POST['club_id'] ?? null;
    
    if ($club_id) {
        // Récupérer les données du club existant
        try {
            $sql = "SELECT * FROM Club WHERE IdClub = :club_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':club_id', $club_id);
            $stmt->execute();
            $club_existant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($club_existant) {
                $nom_club = $club_existant['NomClub'];
                $description = $club_existant['Description'];
                $id_admin_club = $club_existant['IdAdminClub'];
                $logo_existant = $club_existant['Logo'];
            }
        } catch (PDOException $e) {
            $errors['general'] = "Erreur lors du chargement du club.";
        }
    }
}

// Traitement du formulaire lorsqu'il est soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données
    $nom_club = trim($_POST['nom_club'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $id_admin_club = trim($_POST['admin_existant'] ?? '');
    $logo_existant = $_POST['logo_existant'] ?? '';
    
    // Données pour nouvel organisateur
    $organisateur_email = trim($_POST['organisateur_email'] ?? '');
    $organisateur_nom = trim($_POST['organisateur_nom'] ?? '');
    $organisateur_prenom = trim($_POST['organisateur_prenom'] ?? '');
    $organisateur_telephone = trim($_POST['organisateur_telephone'] ?? '');
    $creer_nouvel_organisateur = isset($_POST['creer_nouvel_organisateur']);
    
    // Si en mode édition, récupérer l'ID du club
    if ($edition_mode) {
        $club_id = $_POST['club_id'] ?? null;
    }
    
    // Validation du nom du club
    if (empty($nom_club)) {
        $errors['nom_club'] = "Le nom du club est obligatoire.";
    } elseif (strlen($nom_club) > 150) {
        $errors['nom_club'] = "Le nom du club ne peut pas dépasser 150 caractères.";
    } else {
        // Vérifier si le nom du club existe déjà (sauf pour le club en cours d'édition)
        try {
            $sql_check = "SELECT IdClub FROM Club WHERE NomClub = :nom_club";
            $params = [':nom_club' => $nom_club];
            
            if ($edition_mode && $club_id) {
                $sql_check .= " AND IdClub != :club_id";
                $params[':club_id'] = $club_id;
            }
            
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->execute($params);
            
            if ($stmt_check->rowCount() > 0) {
                $errors['nom_club'] = "Un club avec ce nom existe déjà.";
            }
        } catch (PDOException $e) {
            $errors['general'] = "Erreur lors de la vérification du nom du club.";
        }
    }
    
    // Validation de la description
    if (strlen($description) > 1000) {
        $errors['description'] = "La description ne peut pas dépasser 1000 caractères.";
    }
    
    // Validation de l'administrateur
    if ($creer_nouvel_organisateur) {
        // Validation pour nouvel organisateur
        if (empty($organisateur_email)) {
            $errors['organisateur_email'] = "L'email de l'organisateur est obligatoire.";
        } elseif (!filter_var($organisateur_email, FILTER_VALIDATE_EMAIL)) {
            $errors['organisateur_email'] = "L'email n'est pas valide.";
        } else {
            // Vérifier si l'email existe déjà
            try {
                $sql_check_email = "SELECT IdUtilisateur, Role FROM Utilisateur WHERE Email = :email";
                $stmt_check_email = $conn->prepare($sql_check_email);
                $stmt_check_email->bindParam(':email', $organisateur_email);
                $stmt_check_email->execute();
                $user_existant = $stmt_check_email->fetch(PDO::FETCH_ASSOC);
                
                if ($user_existant) {
                    // L'utilisateur existe, on va changer son rôle en organisateur
                    $id_admin_club = $user_existant['IdUtilisateur'];
                } else {
                    // L'utilisateur n'existe pas, on va le créer
                    if (empty($organisateur_nom)) {
                        $errors['organisateur_nom'] = "Le nom est obligatoire pour créer un nouvel organisateur.";
                    }
                    if (empty($organisateur_prenom)) {
                        $errors['organisateur_prenom'] = "Le prénom est obligatoire pour créer un nouvel organisateur.";
                    }
                }
            } catch (PDOException $e) {
                $errors['general'] = "Erreur lors de la vérification de l'email.";
            }
        }
    } elseif (!empty($id_admin_club)) {
        // Validation de l'administrateur existant
        try {
            $sql_check_admin = "SELECT IdUtilisateur, Role FROM Utilisateur WHERE IdUtilisateur = :id_admin";
            $stmt_check_admin = $conn->prepare($sql_check_admin);
            $stmt_check_admin->bindParam(':id_admin', $id_admin_club);
            $stmt_check_admin->execute();
            $admin_data = $stmt_check_admin->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin_data) {
                $errors['admin_existant'] = "L'administrateur sélectionné n'existe pas.";
            } else {
                // Changer le rôle en organisateur si ce n'est pas déjà le cas
                if ($admin_data['Role'] !== 'organisateur') {
                    try {
                        $sql_update_role = "UPDATE Utilisateur SET Role = 'organisateur' WHERE IdUtilisateur = :id_admin";
                        $stmt_update_role = $conn->prepare($sql_update_role);
                        $stmt_update_role->bindParam(':id_admin', $id_admin_club);
                        $stmt_update_role->execute();
                    } catch (PDOException $e) {
                        $errors['general'] = "Erreur lors de la mise à jour du rôle.";
                    }
                }
            }
        } catch (PDOException $e) {
            $errors['general'] = "Erreur lors de la vérification de l'administrateur.";
        }
    }
    
    // Gestion du fichier logo
    $logo_name = $logo_existant; // Conserver l'ancien logo par défaut
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        
        // Validation du type de fichier
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['logo'] = "Le format de fichier n'est pas autorisé. Formats acceptés : JPG, PNG, GIF.";
        }
        
        // Validation de la taille (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors['logo'] = "Le fichier est trop volumineux. Taille maximum : 2MB.";
        }
        
        // Si pas d'erreur, traiter le fichier
        if (!isset($errors['logo'])) {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $logo_name = 'club_' . uniqid() . '.' . $file_extension;
            $upload_dir = '../uploads/clubs/';
            
            // Créer le dossier s'il n'existe pas
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $upload_path = $upload_dir . $logo_name;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Supprimer l'ancien logo s'il existe
                if (!empty($logo_existant) && file_exists($upload_dir . $logo_existant)) {
                    unlink($upload_dir . $logo_existant);
                }
            } else {
                $errors['logo'] = "Erreur lors du téléchargement du fichier.";
                $logo_name = $logo_existant; // Garder l'ancien logo en cas d'erreur
            }
        }
    } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['logo'] = "Erreur lors du téléchargement du fichier : " . $_FILES['logo']['error'];
    }
    
    // Si aucune erreur, insérer ou mettre à jour dans la base de données
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Gestion de l'organisateur
            if ($creer_nouvel_organisateur && !empty($organisateur_email)) {
                // Vérifier si l'utilisateur existe déjà
                $sql_check_user = "SELECT IdUtilisateur, Nom, Prenom, Role FROM Utilisateur WHERE Email = :email";
                $stmt_check_user = $conn->prepare($sql_check_user);
                $stmt_check_user->bindParam(':email', $organisateur_email);
                $stmt_check_user->execute();
                $user_existant = $stmt_check_user->fetch(PDO::FETCH_ASSOC);
                
                if ($user_existant) {
                    // L'utilisateur existe, changer son rôle en organisateur
                    $id_admin_club = $user_existant['IdUtilisateur'];
                    $organisateur_nom = $user_existant['Nom'];
                    $organisateur_prenom = $user_existant['Prenom'];
                    
                    if ($user_existant['Role'] !== 'organisateur') {
                        $sql_update_role = "UPDATE Utilisateur SET Role = 'organisateur' WHERE IdUtilisateur = :id_user";
                        $stmt_update_role = $conn->prepare($sql_update_role);
                        $stmt_update_role->bindParam(':id_user', $id_admin_club);
                        $stmt_update_role->execute();
                    }
                } else {
                    // Créer un nouvel utilisateur organisateur
                    $mot_de_passe_temporaire = bin2hex(random_bytes(8));
                    $mot_de_passe_hash = password_hash($mot_de_passe_temporaire, PASSWORD_DEFAULT);
                    
                    $sql_create_user = "INSERT INTO Utilisateur (Nom, Prenom, Email, Telephone, MotDePasse, Role, DateInscription) 
                                       VALUES (:nom, :prenom, :email, :telephone, :mot_de_passe, 'organisateur', CURDATE())";
                    
                    $stmt_create_user = $conn->prepare($sql_create_user);
                    $stmt_create_user->bindParam(':nom', $organisateur_nom);
                    $stmt_create_user->bindParam(':prenom', $organisateur_prenom);
                    $stmt_create_user->bindParam(':email', $organisateur_email);
                    $stmt_create_user->bindParam(':telephone', $organisateur_telephone);
                    $stmt_create_user->bindParam(':mot_de_passe', $mot_de_passe_hash);
                    
                    if ($stmt_create_user->execute()) {
                        $id_admin_club = $conn->lastInsertId();
                    } else {
                        throw new Exception("Erreur lors de la création de l'organisateur.");
                    }
                }
            } elseif (empty($id_admin_club)) {
                // Si aucun admin n'est sélectionné, assigner le super admin actuel
                $id_admin_club = $_SESSION['user_id'];
            }
            
            if ($edition_mode && $club_id) {
                // MODE ÉDITION - Mise à jour
                $sql = "UPDATE Club 
                        SET NomClub = :nom_club, 
                            Description = :description, 
                            Logo = :logo, 
                            IdAdminClub = :id_admin_club 
                        WHERE IdClub = :club_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':club_id', $club_id);
                $stmt->bindParam(':nom_club', $nom_club);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':logo', $logo_name);
                $stmt->bindParam(':id_admin_club', $id_admin_club);
                
                if ($stmt->execute()) {
                    $conn->commit();
                    // Redirection vers la page de récapitulatif
                    header("Location: recap_club.php?id=" . $club_id . "&updated=1");
                    exit;
                } else {
                    throw new Exception("Erreur lors de la modification du club.");
                }
            } else {
                // MODE CRÉATION - Insertion
                $sql = "INSERT INTO Club (NomClub, Description, DateCreation, Logo, IdAdminClub) 
                        VALUES (:nom_club, :description, CURDATE(), :logo, :id_admin_club)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':nom_club', $nom_club);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':logo', $logo_name);
                $stmt->bindParam(':id_admin_club', $id_admin_club);
                
                if ($stmt->execute()) {
                    $club_id = $conn->lastInsertId();
                    
                    // Envoyer un email à l'organisateur
                    if ($id_admin_club && $id_admin_club != $_SESSION['user_id']) {
                        try {
                            // Récupérer les informations de l'organisateur
                            $sql_organisateur = "SELECT Nom, Prenom, Email FROM Utilisateur WHERE IdUtilisateur = :id_admin";
                            $stmt_organisateur = $conn->prepare($sql_organisateur);
                            $stmt_organisateur->bindParam(':id_admin', $id_admin_club);
                            $stmt_organisateur->execute();
                            $organisateur_info = $stmt_organisateur->fetch(PDO::FETCH_ASSOC);
                            
                            if ($organisateur_info) {
                                // Préparer l'email
                                $objet = "Vous êtes maintenant organisateur du club : " . $nom_club;
                                $contenu = "Bonjour " . $organisateur_info['Prenom'] . " " . $organisateur_info['Nom'] . ",\n\n";
                                $contenu .= "Vous avez été désigné comme organisateur du club \"" . $nom_club . "\".\n\n";
                                
                                if (isset($mot_de_passe_temporaire)) {
                                    $contenu .= "Vos identifiants de connexion sont :\n";
                                    $contenu .= "Email : " . $organisateur_info['Email'] . "\n";
                                    $contenu .= "Mot de passe temporaire : " . $mot_de_passe_temporaire . "\n\n";
                                    $contenu .= "Veuillez changer votre mot de passe lors de votre première connexion.\n\n";
                                }
                                
                                $contenu .= "Vous pouvez maintenant gérer les événements de ce club depuis votre espace organisateur.\n\n";
                                $contenu .= "Cordialement,\nL'équipe GestionEvents";
                                
                                // Enregistrer l'email dans la base de données
                                $sql_email = "INSERT INTO EmailAdmin (IdAdmin, DestinataireEmail, DestinataireNom, Objet, Contenu, TypeEmail, IdClub) 
                                            VALUES (:id_admin, :destinataire_email, :destinataire_nom, :objet, :contenu, 'notification_organisateur', :id_club)";
                                
                                $stmt_email = $conn->prepare($sql_email);
                                $stmt_email->bindParam(':id_admin', $_SESSION['user_id']);
                                $stmt_email->bindParam(':destinataire_email', $organisateur_info['Email']);
                                $stmt_email->bindParam(':destinataire_nom', $organisateur_info['Prenom'] . ' ' . $organisateur_info['Nom']);
                                $stmt_email->bindParam(':objet', $objet);
                                $stmt_email->bindParam(':contenu', $contenu);
                                $stmt_email->bindParam(':id_club', $club_id);
                                $stmt_email->execute();
                                
                                $email_sent = true;
                            }
                        } catch (Exception $e) {
                            // L'email n'a pas pu être envoyé, mais on continue
                            error_log("Erreur envoi email organisateur: " . $e->getMessage());
                        }
                    }
                    
                    $conn->commit();
                    // Redirection vers la page de récapitulatif
                    header("Location: recap_club.php?id=" . $club_id . ($email_sent ? "&email_sent=1" : ""));
                    exit;
                } else {
                    throw new Exception("Erreur lors de la création du club.");
                }
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors['general'] = "Erreur : " . $e->getMessage();
            
            // Supprimer le fichier uploadé en cas d'erreur
            if ($logo_name != $logo_existant && $logo_name && file_exists($upload_dir . $logo_name)) {
                unlink($upload_dir . $logo_name);
            }
        }
    }
}

// Récupérer la liste des utilisateurs disponibles (pour sélection d'organisateur)
$utilisateurs = [];
try {
    $sql_utilisateurs = "SELECT IdUtilisateur, Nom, Prenom, Email, Role 
                        FROM Utilisateur 
                        WHERE Role IN ('participant', 'organisateur','')
                        ORDER BY Role, Nom, Prenom";
    $stmt_utilisateurs = $conn->prepare($sql_utilisateurs);
    $stmt_utilisateurs->execute();
    $utilisateurs = $stmt_utilisateurs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['general'] = "Erreur lors du chargement des utilisateurs.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edition_mode ? 'Modifier le Club' : 'Créer un Club'; ?></title>
    <link rel="stylesheet" href="../frontend/css.css">
    <style>
        /* Votre CSS existant reste inchangé */
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
            max-width: 800px;
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
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload:hover {
            border-color: #667eea;
            background: #f9f9ff;
        }
        .file-upload input[type="file"] {
            display: none;
        }
        .file-upload-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 15px auto;
            border-radius: 10px;
            display: none;
        }
        .logo-existant {
            max-width: 200px;
            max-height: 200px;
            margin: 15px auto;
            border-radius: 10px;
            border: 3px solid #bdc3c7;
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
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        .btn-warning:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #555;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .info-box strong {
            color: #1565c0;
        }
        .char-counter {
            text-align: right;
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }
        .error {
            color: #f44336;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
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
            <h1><?php echo $edition_mode ? '✏️ Modifier le club' : '🏢 Créer un nouveau club'; ?></h1>
            <p><?php echo $edition_mode ? 'Modifiez les informations du club' : 'Remplissez les informations du club'; ?></p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="error" style="background: #ffebee; border: 1px solid #f44336; color: #c62828; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="clubForm">
            <?php if ($edition_mode && $club_id): ?>
                <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                <input type="hidden" name="logo_existant" value="<?php echo htmlspecialchars($logo_existant); ?>">
            <?php endif; ?>

            <div class="form-section">
                <h3>📝 Informations générales</h3>
                
                <div class="form-group">
                    <label for="nom_club">Nom du club *</label>
                    <input type="text" 
                           id="nom_club" 
                           name="nom_club" 
                           placeholder="Ex: Club Informatique" 
                           required
                           maxlength="150"
                           value="<?php echo htmlspecialchars($nom_club); ?>">
                    <?php if (isset($errors['nom_club'])): ?>
                        <span class="error"><?php echo htmlspecialchars($errors['nom_club']); ?></span>
                    <?php endif; ?>
                    <div class="char-counter">
                        <span id="nom-counter"><?php echo strlen($nom_club); ?></span>/150
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description du club</label>
                    <textarea id="description" 
                              name="description" 
                              placeholder="Décrivez les objectifs et activités du club..."
                              maxlength="1000"><?php echo htmlspecialchars($description); ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <span class="error"><?php echo htmlspecialchars($errors['description']); ?></span>
                    <?php endif; ?>
                    <div class="char-counter">
                        <span id="desc-counter"><?php echo strlen($description); ?></span>/1000
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>🎨 Logo du club</h3>
                
                <div class="form-group">
                    <?php if (!empty($logo_existant)): ?>
                        <div style="text-align: center; margin-bottom: 15px;">
                            <p><strong>Logo actuel :</strong></p>
                            <img src="../uploads/clubs/<?php echo htmlspecialchars($logo_existant); ?>" 
                                 class="logo-existant" 
                                 alt="Logo actuel">
                        </div>
                    <?php endif; ?>
                    
                    <label class="file-upload" for="logo">
                        <div class="file-upload-icon">📷</div>
                        <div><strong>Cliquez pour <?php echo $edition_mode ? 'changer le logo' : 'télécharger un logo'; ?></strong></div>
                        <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                            PNG, JPG ou GIF (max 2MB)
                        </div>
                        <input type="file" 
                               id="logo" 
                               name="logo" 
                               accept="image/*">
                    </label>
                    <?php if (isset($errors['logo'])): ?>
                        <span class="error"><?php echo htmlspecialchars($errors['logo']); ?></span>
                    <?php endif; ?>
                    <img id="logo-preview" class="image-preview" alt="Aperçu du logo">
                </div>
            </div>

            <div class="form-section">
                <h3>👤 Organisateur du club</h3>
                
                <div class="info-box">
                    <strong>ℹ️ Information :</strong> Vous pouvez choisir un utilisateur existant (qui deviendra organisateur) ou créer un nouveau compte organisateur.
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label>
                        <input type="radio" name="choix_organisateur" value="existant" id="choix_existant" 
                               <?php echo empty($creer_nouvel_organisateur) ? 'checked' : ''; ?>>
                        Choisir un utilisateur existant
                    </label>
                </div>

                <div id="section_utilisateur_existant" class="form-group">
                    <label for="admin_existant">Sélectionner un utilisateur</label>
                    <select id="admin_existant" name="admin_existant">
                        <option value="">-- Choisir un utilisateur --</option>
                        <?php foreach ($utilisateurs as $user): ?>
                            <option value="<?php echo $user['IdUtilisateur']; ?>" 
                                <?php echo ($id_admin_club == $user['IdUtilisateur']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['Prenom'] . ' ' . $user['Nom'] . ' (' . $user['Email'] . ') - ' . ucfirst($user['Role'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['admin_existant'])): ?>
                        <span class="error"><?php echo htmlspecialchars($errors['admin_existant']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>
                        <input type="radio" name="choix_organisateur" value="nouveau" id="choix_nouveau" 
                               <?php echo $creer_nouvel_organisateur ? 'checked' : ''; ?>>
                        Créer un nouvel organisateur
                    </label>
                </div>

                <div id="section_nouvel_organisateur" class="form-group" style="display: none;">
                    <div class="form-group">
                        <label for="organisateur_email">Email de l'organisateur *</label>
                        <input type="email" 
                               id="organisateur_email" 
                               name="organisateur_email" 
                               placeholder="Ex: organisateur@email.com" 
                               value="<?php echo htmlspecialchars($organisateur_email); ?>">
                        <?php if (isset($errors['organisateur_email'])): ?>
                            <span class="error"><?php echo htmlspecialchars($errors['organisateur_email']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="organisateur_nom">Nom</label>
                        <input type="text" 
                               id="organisateur_nom" 
                               name="organisateur_nom" 
                               placeholder="Ex: Dupont" 
                               value="<?php echo htmlspecialchars($organisateur_nom); ?>">
                        <?php if (isset($errors['organisateur_nom'])): ?>
                            <span class="error"><?php echo htmlspecialchars($errors['organisateur_nom']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="organisateur_prenom">Prénom</label>
                        <input type="text" 
                               id="organisateur_prenom" 
                               name="organisateur_prenom" 
                               placeholder="Ex: Jean" 
                               value="<?php echo htmlspecialchars($organisateur_prenom); ?>">
                        <?php if (isset($errors['organisateur_prenom'])): ?>
                            <span class="error"><?php echo htmlspecialchars($errors['organisateur_prenom']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="organisateur_telephone">Téléphone (optionnel)</label>
                        <input type="tel" 
                               id="organisateur_telephone" 
                               name="organisateur_telephone" 
                               placeholder="Ex: 0123456789" 
                               value="<?php echo htmlspecialchars($organisateur_telephone); ?>">
                    </div>

                    <input type="hidden" name="creer_nouvel_organisateur" id="creer_nouvel_organisateur" value="0">
                </div>
            </div>

            <div class="form-actions">
                <a href="<?php echo $edition_mode ? 'recap_club.php?id=' . $club_id : 'dashboard.php'; ?>" class="btn btn-secondary">
                    Annuler
                </a>
                <button type="submit" class="btn <?php echo $edition_mode ? 'btn-warning' : 'btn-primary'; ?>">
                    <?php echo $edition_mode ? '✓ Modifier le club' : '✓ Créer le club'; ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        // Gestion des sections organisateur
        function toggleOrganisateurSections() {
            const choixExistant = document.getElementById('choix_existant');
            const choixNouveau = document.getElementById('choix_nouveau');
            const sectionExistant = document.getElementById('section_utilisateur_existant');
            const sectionNouveau = document.getElementById('section_nouvel_organisateur');
            const inputCreerNouveau = document.getElementById('creer_nouvel_organisateur');
            
            if (choixExistant.checked) {
                sectionExistant.style.display = 'block';
                sectionNouveau.style.display = 'none';
                inputCreerNouveau.value = '0';
            } else if (choixNouveau.checked) {
                sectionExistant.style.display = 'none';
                sectionNouveau.style.display = 'block';
                inputCreerNouveau.value = '1';
            }
        }

        // Événements pour les radio buttons
        document.getElementById('choix_existant').addEventListener('change', toggleOrganisateurSections);
        document.getElementById('choix_nouveau').addEventListener('change', toggleOrganisateurSections);

        // Initialiser l'affichage
        toggleOrganisateurSections();

        // Compteur de caractères pour le nom
        document.getElementById('nom_club').addEventListener('input', function() {
            document.getElementById('nom-counter').textContent = this.value.length;
        });

        // Compteur de caractères pour la description
        document.getElementById('description').addEventListener('input', function() {
            document.getElementById('desc-counter').textContent = this.value.length;
        });

        // Preview du logo
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('logo-preview');
            
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(file);
            }
        });

        // Animation du label file upload
        document.querySelector('.file-upload').addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#667eea';
            this.style.background = '#f9f9ff';
        });

        document.querySelector('.file-upload').addEventListener('dragleave', function() {
            this.style.borderColor = '#e0e0e0';
            this.style.background = 'white';
        });

        // Confirmation avant de quitter si formulaire modifié
        let formModified = false;
        document.getElementById('clubForm').addEventListener('input', function() {
            formModified = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (formModified) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.getElementById('clubForm').addEventListener('submit', function() {
            formModified = false;
        });
    </script>
</body>
</html>