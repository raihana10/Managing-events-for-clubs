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
    $logo_existant = $_POST['logo_existant'] ?? '';
    
    // Gestion du choix d'administrateur
    $admin_choice = $_POST['admin_choice'] ?? 'existing';
    $creer_nouvel_organisateur = ($admin_choice === 'new');
    
    if ($creer_nouvel_organisateur) {
        // Données pour nouvel administrateur
    $organisateur_email = trim($_POST['organisateur_email'] ?? '');
    $organisateur_nom = trim($_POST['organisateur_nom'] ?? '');
    $organisateur_prenom = trim($_POST['organisateur_prenom'] ?? '');
    $organisateur_telephone = trim($_POST['organisateur_telephone'] ?? '');
        $id_admin_club = '';
    } else {
        // Administrateur existant
        $id_admin_club = trim($_POST['id_admin_club'] ?? '');
        $organisateur_email = $organisateur_nom = $organisateur_prenom = $organisateur_telephone = '';
    }
    
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
            if ($edition_mode && $club_id) {
                // Mode édition : vérifier si un autre club a le même nom
                $sql_check = "SELECT IdClub FROM Club WHERE NomClub = :nom_club AND IdClub != :club_id";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':nom_club', $nom_club);
                $stmt_check->bindParam(':club_id', $club_id);
            } else {
                // Mode création : vérifier si un club a déjà ce nom
                $sql_check = "SELECT IdClub FROM Club WHERE NomClub = :nom_club";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':nom_club', $nom_club);
            }
            
            $stmt_check->execute();
            
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
    } else {
        // Validation de l'administrateur existant
        if (empty($id_admin_club)) {
            $errors['id_admin_club'] = "Veuillez sélectionner un administrateur.";
        } else {
        try {
            $sql_check_admin = "SELECT IdUtilisateur, Role FROM Utilisateur WHERE IdUtilisateur = :id_admin";
            $stmt_check_admin = $conn->prepare($sql_check_admin);
            $stmt_check_admin->bindParam(':id_admin', $id_admin_club);
            $stmt_check_admin->execute();
            $admin_data = $stmt_check_admin->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin_data) {
                    $errors['id_admin_club'] = "L'administrateur sélectionné n'existe pas.";
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

                                // Créer des variables pour les valeurs qui ne peuvent pas être passées directement
                                $id_admin = $_SESSION['user_id'];
                                $destinataire_email = $organisateur_info['Email'];
                                $destinataire_nom = $organisateur_info['Prenom'] . ' ' . $organisateur_info['Nom'];

                                $stmt_email->bindParam(':id_admin', $id_admin);
                                $stmt_email->bindParam(':destinataire_email', $destinataire_email);
                                $stmt_email->bindParam(':destinataire_nom', $destinataire_nom);
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
    <title><?php echo $edition_mode ? 'Modifier le Club' : 'Créer un Club'; ?> - Event Manager</title>
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
                        <a href="liste_admins.php" class="sidebar-nav-link-modern">
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
                <h1><?php echo $edition_mode ? 'Modifier le Club' : 'Créer un Club'; ?></h1>
                <p><?php echo $edition_mode ? 'Modifiez les informations du club' : 'Ajoutez un nouveau club à la plateforme'; ?></p>
        </div>

        <?php if (!empty($errors['general'])): ?>
                <div class="alert-modern alert-error-modern">
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="clubForm" class="form-modern">
            <?php if ($edition_mode && $club_id): ?>
                <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                <input type="hidden" name="logo_existant" value="<?php echo htmlspecialchars($logo_existant); ?>">
            <?php endif; ?>

                <div class="form-section-modern">
                    <div class="form-section-title-modern">📝 Informations générales</div>
                
                    <div class="form-group-modern">
                        <label for="nom_club" class="form-label-modern">Nom du club *</label>
                    <input type="text" 
                           id="nom_club" 
                           name="nom_club" 
                               class="form-input-modern"
                           placeholder="Ex: Club Informatique" 
                           required
                           maxlength="150"
                           value="<?php echo htmlspecialchars($nom_club); ?>">
                    <?php if (isset($errors['nom_club'])): ?>
                            <div class="error-modern"><?php echo htmlspecialchars($errors['nom_club']); ?></div>
                    <?php endif; ?>
                    <div class="char-counter">
                        <span id="nom-counter"><?php echo strlen($nom_club); ?></span>/150
                    </div>
                </div>

                    <div class="form-group-modern">
                        <label for="description" class="form-label-modern">Description du club</label>
                    <textarea id="description" 
                              name="description" 
                                  class="form-input-modern form-textarea-modern"
                              placeholder="Décrivez les objectifs et activités du club..."
                              maxlength="1000"><?php echo htmlspecialchars($description); ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                            <div class="error-modern"><?php echo htmlspecialchars($errors['description']); ?></div>
                    <?php endif; ?>
                    <div class="char-counter">
                        <span id="desc-counter"><?php echo strlen($description); ?></span>/1000
                    </div>
                </div>
            </div>

                <div class="form-section-modern">
                    <div class="form-section-title-modern">🎨 Logo du club</div>
                
                    <div class="form-group-modern">
                    <?php if (!empty($logo_existant)): ?>
                            <div class="logo-preview-modern">
                            <p><strong>Logo actuel :</strong></p>
                            <img src="../uploads/clubs/<?php echo htmlspecialchars($logo_existant); ?>" 
                                     alt="Logo actuel" style="max-width: 200px; max-height: 200px; border-radius: var(--border-radius-md);">
                        </div>
                    <?php endif; ?>
                    
                    <label class="file-upload" for="logo">
                            <div class="file-upload-content">
                        <div class="file-upload-icon">📷</div>
                                <div class="file-upload-text">
                                    <strong>Cliquez pour <?php echo $edition_mode ? 'changer le logo' : 'télécharger un logo'; ?></strong>
                                </div>
                                <div class="file-upload-hint">
                            PNG, JPG ou GIF (max 2MB)
                                </div>
                        </div>
                        <input type="file" 
                               id="logo" 
                               name="logo" 
                               accept="image/*">
                    </label>
                    <?php if (isset($errors['logo'])): ?>
                            <div class="error-modern"><?php echo htmlspecialchars($errors['logo']); ?></div>
                    <?php endif; ?>
                        <div class="image-preview" id="imagePreview"></div>
            </div>
                </div>

                <div class="form-section-modern">
                    <div class="form-section-title-modern">👤 Administrateur du club</div>
                    
                    <div class="form-group-modern">
                        <div class="radio-group-modern">
                            <label class="radio-option-modern">
                                <input type="radio" name="admin_choice" value="existing" <?php echo !$creer_nouvel_organisateur ? 'checked' : ''; ?>>
                                <span class="radio-label-modern">Utiliser un administrateur existant</span>
                            </label>
                            <label class="radio-option-modern">
                                <input type="radio" name="admin_choice" value="new" <?php echo $creer_nouvel_organisateur ? 'checked' : ''; ?>>
                                <span class="radio-label-modern">Créer un nouvel administrateur</span>
                    </label>
                        </div>
                </div>

                    <div id="existing-admin" class="form-group-modern" style="<?php echo $creer_nouvel_organisateur ? 'display: none;' : ''; ?>">
                        <label for="id_admin_club" class="form-label-modern">Sélectionner un administrateur</label>
                        <select id="id_admin_club" name="id_admin_club" class="form-input-modern form-select-modern">
                            <option value="">-- Choisir un administrateur --</option>
                        <?php foreach ($utilisateurs as $user): ?>
                            <option value="<?php echo $user['IdUtilisateur']; ?>" 
                                        <?php echo $id_admin_club == $user['IdUtilisateur'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['Prenom'] . ' ' . $user['Nom'] . ' (' . $user['Email'] . ') - ' . ucfirst($user['Role'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                        <?php if (isset($errors['id_admin_club'])): ?>
                            <div class="error-modern"><?php echo htmlspecialchars($errors['id_admin_club']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div id="new-admin" class="admin-section-modern" style="<?php echo !$creer_nouvel_organisateur ? 'display: none;' : ''; ?>">
                        <div class="form-row">
                            <div class="form-group-modern">
                                <label for="organisateur_prenom" class="form-label-modern">Prénom *</label>
                                <input type="text" 
                                       id="organisateur_prenom" 
                                       name="organisateur_prenom" 
                                       class="form-input-modern"
                                       placeholder="Prénom de l'administrateur"
                                       value="<?php echo htmlspecialchars($organisateur_prenom); ?>">
                                <?php if (isset($errors['organisateur_prenom'])): ?>
                                    <div class="error-modern"><?php echo htmlspecialchars($errors['organisateur_prenom']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group-modern">
                                <label for="organisateur_nom" class="form-label-modern">Nom *</label>
                        <input type="text" 
                               id="organisateur_nom" 
                               name="organisateur_nom" 
                                       class="form-input-modern"
                                       placeholder="Nom de l'administrateur"
                               value="<?php echo htmlspecialchars($organisateur_nom); ?>">
                        <?php if (isset($errors['organisateur_nom'])): ?>
                                    <div class="error-modern"><?php echo htmlspecialchars($errors['organisateur_nom']); ?></div>
                        <?php endif; ?>
                            </div>
                    </div>

                        <div class="form-row">
                            <div class="form-group-modern">
                                <label for="organisateur_email" class="form-label-modern">Email *</label>
                                <input type="email" 
                                       id="organisateur_email" 
                                       name="organisateur_email" 
                                       class="form-input-modern"
                                       placeholder="email@exemple.com"
                                       value="<?php echo htmlspecialchars($organisateur_email); ?>">
                                <?php if (isset($errors['organisateur_email'])): ?>
                                    <div class="error-modern"><?php echo htmlspecialchars($errors['organisateur_email']); ?></div>
                        <?php endif; ?>
                    </div>
                            <div class="form-group-modern">
                                <label for="organisateur_telephone" class="form-label-modern">Téléphone</label>
                        <input type="tel" 
                               id="organisateur_telephone" 
                               name="organisateur_telephone" 
                                       class="form-input-modern"
                                       placeholder="06 12 34 56 78"
                               value="<?php echo htmlspecialchars($organisateur_telephone); ?>">
                                <?php if (isset($errors['organisateur_telephone'])): ?>
                                    <div class="error-modern"><?php echo htmlspecialchars($errors['organisateur_telephone']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <strong>Note :</strong> Un mot de passe temporaire sera généré et envoyé par email à l'administrateur.
                        </div>
                </div>
            </div>

                <div class="form-actions-modern">
                    <a href="gerer_clubs.php" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edition_mode ? 'Modifier le club' : 'Créer le club'; ?>
                </button>
            </div>
        </form>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Gestion de l'affichage des sections administrateur
        document.addEventListener('DOMContentLoaded', function() {
            const adminChoice = document.querySelectorAll('input[name="admin_choice"]');
            const existingAdmin = document.getElementById('existing-admin');
            const newAdmin = document.getElementById('new-admin');
            
            adminChoice.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'existing') {
                        existingAdmin.style.display = 'block';
                        newAdmin.style.display = 'none';
                    } else {
                        existingAdmin.style.display = 'none';
                        newAdmin.style.display = 'block';
                    }
                });
            });
            
            // Compteur de caractères
            const nomInput = document.getElementById('nom_club');
            const descInput = document.getElementById('description');
            const nomCounter = document.getElementById('nom-counter');
            const descCounter = document.getElementById('desc-counter');
            
            nomInput.addEventListener('input', function() {
                nomCounter.textContent = this.value.length;
            });
            
            descInput.addEventListener('input', function() {
                descCounter.textContent = this.value.length;
            });
            
            // Prévisualisation d'image
            const logoInput = document.getElementById('logo');
            const imagePreview = document.getElementById('imagePreview');
            
            logoInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                        imagePreview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: var(--border-radius-md);">';
                        imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
                } else {
                    imagePreview.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>