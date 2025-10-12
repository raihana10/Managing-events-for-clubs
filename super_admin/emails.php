<?php
/**
 * Envoi d'emails - Backend PHP
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

// Variables pour le formulaire
$destinataires = $sujet = $message = '';
$errors = [];
$success = false;
$emails_envoyes = 0;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $destinataires = $_POST['destinataires'] ?? [];
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($destinataires)) {
        $errors['destinataires'] = "Veuillez sélectionner au moins un destinataire.";
    }
    
    if (empty($sujet)) {
        $errors['sujet'] = "Le sujet est obligatoire.";
    } elseif (strlen($sujet) > 200) {
        $errors['sujet'] = "Le sujet ne peut pas dépasser 200 caractères.";
    }
    
    if (empty($message)) {
        $errors['message'] = "Le message est obligatoire.";
    } elseif (strlen($message) > 5000) {
        $errors['message'] = "Le message ne peut pas dépasser 5000 caractères.";
    }
    
    // Si aucune erreur, traiter l'envoi
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Récupérer les informations des destinataires
            $destinataires_info = [];
            
            if (in_array('tous_organisateurs', $destinataires)) {
                $sql_org = "SELECT IdUtilisateur, Nom, Prenom, Email FROM Utilisateur WHERE Role = 'organisateur'";
                $stmt_org = $conn->prepare($sql_org);
                $stmt_org->execute();
                $destinataires_info = array_merge($destinataires_info, $stmt_org->fetchAll(PDO::FETCH_ASSOC));
            } else {
                // Destinataires spécifiques sélectionnés
                foreach ($destinataires as $dest_id) {
                    if (is_numeric($dest_id)) {
                        $sql_user = "SELECT IdUtilisateur, Nom, Prenom, Email FROM Utilisateur WHERE IdUtilisateur = :id AND Role = 'organisateur'";
                        $stmt_user = $conn->prepare($sql_user);
                        $stmt_user->bindParam(':id', $dest_id);
                        $stmt_user->execute();
                        $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
                        if ($user_info) {
                            $destinataires_info[] = $user_info;
                        }
                    }
                }
            }
            
            // Supprimer les doublons
            $destinataires_info = array_unique($destinataires_info, SORT_REGULAR);
            
            if (!empty($destinataires_info)) {
                // Enregistrer chaque email individuellement
                foreach ($destinataires_info as $dest) {
                    $sql_email = "INSERT INTO EmailAdmin (IdAdmin, DestinataireEmail, DestinataireNom, Objet, Contenu, TypeEmail) 
                                 VALUES (:id_admin, :destinataire_email, :destinataire_nom, :objet, :contenu, 'general')";
                    
                    $stmt_email = $conn->prepare($sql_email);
                    $stmt_email->bindParam(':id_admin', $_SESSION['user_id']);
                    $stmt_email->bindParam(':destinataire_email', $dest['Email']);
                    $stmt_email->bindParam(':destinataire_nom', $dest['Prenom'] . ' ' . $dest['Nom']);
                    $stmt_email->bindParam(':objet', $sujet);
                    $stmt_email->bindParam(':contenu', $message);
                    $stmt_email->execute();
                    
                    $emails_envoyes++;
                }
                
                $conn->commit();
                $success = true;
                $success_message = "Email envoyé avec succès à " . $emails_envoyes . " organisateur(s).";
                
                // Réinitialiser le formulaire
                $destinataires = $sujet = $message = '';
            } else {
                $errors['general'] = "Aucun organisateur trouvé.";
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors['general'] = "Erreur lors de l'envoi de l'email : " . $e->getMessage();
        }
    }
}

// Récupérer les organisateurs pour la sélection
$organisateurs = [];
try {
    $sql_org = "SELECT IdUtilisateur, Nom, Prenom, Email FROM Utilisateur WHERE Role = 'organisateur' ORDER BY Nom, Prenom";
    $stmt_org = $conn->prepare($sql_org);
    $stmt_org->execute();
    $organisateurs = $stmt_org->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['general'] = "Erreur lors du chargement des organisateurs.";
}

// Récupérer les statistiques des emails
try {
    $sql_stats = "SELECT 
                    COUNT(*) as total_emails,
                    COUNT(DISTINCT DestinataireEmail) as total_destinataires,
                    COUNT(CASE WHEN DateEnvoi >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as emails_30j
                  FROM EmailAdmin WHERE IdAdmin = :id_admin";
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bindParam(':id_admin', $_SESSION['user_id']);
    $stmt_stats->execute();
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les derniers emails envoyés
    $sql_recent = "SELECT IdEmail, DestinataireEmail, DestinataireNom, Objet, DateEnvoi, TypeEmail 
                   FROM EmailAdmin 
                   WHERE IdAdmin = :id_admin 
                   ORDER BY DateEnvoi DESC LIMIT 10";
    $stmt_recent = $conn->prepare($sql_recent);
    $stmt_recent->bindParam(':id_admin', $_SESSION['user_id']);
    $stmt_recent->execute();
    $emails_recents = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = ['total_emails' => 0, 'total_destinataires' => 0, 'emails_30j' => 0];
    $emails_recents = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoi d'emails - Event Manager</title>
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
                        <a href="emails.php" class="sidebar-nav-link-modern active">
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
                <h1>Envoi d'emails</h1>
                <p>Communiquez avec les organisateurs de clubs</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert-modern alert-success-modern">
                    <div class="alert-icon-modern">✅</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Succès</div>
                        <div class="alert-message-modern"><?php echo htmlspecialchars($success_message); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert-modern alert-error-modern">
                    <div class="alert-icon-modern">❌</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Erreur</div>
                        <div class="alert-message-modern">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistiques des emails -->
            <div class="stats-grid-modern">
                <div class="stat-card-modern">
                    <div class="stat-icon-modern">📧</div>
                    <div class="stat-content-modern">
                        <div class="stat-value-modern"><?php echo $stats['total_emails']; ?></div>
                        <div class="stat-label-modern">Emails envoyés</div>
                    </div>
                </div>
                <div class="stat-card-modern">
                    <div class="stat-icon-modern">👥</div>
                    <div class="stat-content-modern">
                        <div class="stat-value-modern"><?php echo $stats['total_destinataires']; ?></div>
                        <div class="stat-label-modern">Destinataires uniques</div>
                    </div>
                </div>
                <div class="stat-card-modern">
                    <div class="stat-icon-modern">📅</div>
                    <div class="stat-content-modern">
                        <div class="stat-value-modern"><?php echo $stats['emails_30j']; ?></div>
                        <div class="stat-label-modern">Ce mois-ci</div>
                    </div>
                </div>
            </div>

            <!-- Formulaire d'envoi d'email -->
            <div class="form-section-modern">
                <h3>Nouvel email</h3>
                <form method="POST" class="form-modern">
                    <div class="form-group-modern">
                        <label class="form-label-modern">Destinataires</label>
                        <select name="destinataires[]" multiple class="form-select-modern" required>
                            <option value="all">Tous les organisateurs</option>
                            <?php foreach ($organisateurs as $org): ?>
                                <option value="<?php echo $org['IdUtilisateur']; ?>" 
                                        <?php echo (isset($_POST['destinataires']) && in_array($org['IdUtilisateur'], $_POST['destinataires'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($org['Nom'] . ' ' . $org['Prenom'] . ' (' . $org['Email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help-modern">Maintenez Ctrl (Cmd sur Mac) pour sélectionner plusieurs destinataires</div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Sujet</label>
                        <input type="text" name="sujet" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($sujet); ?>" required>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Message</label>
                        <textarea name="message" class="form-textarea-modern" rows="8" required><?php echo htmlspecialchars($message); ?></textarea>
                    </div>

                    <div class="form-actions-modern">
                        <button type="submit" name="envoyer_email" class="btn btn-primary">
                            <span class="btn-icon">📧</span>
                            Envoyer l'email
                        </button>
                    </div>
                </form>
        </div>

            <!-- Historique des emails récents -->
            <?php if (!empty($emails_recents)): ?>
                <div class="form-section-modern">
                    <h3>Emails récents</h3>
                    <div class="table-modern">
                        <div class="table-header-modern">
                            <div class="table-title-modern">Historique des envois</div>
                        </div>
                        <div class="table-content-modern">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Destinataire</th>
                                        <th>Sujet</th>
                                        <th>Type</th>
                                        <th>Date d'envoi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($emails_recents as $email): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo htmlspecialchars($email['DestinataireNom']); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($email['DestinataireEmail']); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($email['Objet']); ?></td>
                                            <td>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($email['TypeEmail']); ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($email['DateEnvoi'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>