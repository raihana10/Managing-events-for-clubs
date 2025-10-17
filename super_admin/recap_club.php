<?php
/**
 * Récapitulatif création de club - Backend PHP
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

// Récupérer les données de la session ou des paramètres GET
$club_id = $_GET['id'] ?? null;
$club_data = [];
$email_sent = isset($_GET['email_sent']) && $_GET['email_sent'] == '1';

if ($club_id) {
    // Récupérer les informations du club depuis la base de données
    try {
        $sql = "SELECT c.*, u.Nom as admin_nom, u.Prenom as admin_prenom, u.Email as admin_email
                FROM Club c 
                LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur 
                WHERE c.IdClub = :club_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':club_id', $club_id);
        $stmt->execute();
        $club_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$club_data) {
            die("Club non trouvé");
        }
    } catch (PDOException $e) {
        die("Erreur de base de données : " . $e->getMessage());
    }
} else {
    die("ID du club non spécifié");
}

// Récupérer les statistiques du club
try {
    // Nombre de membres
    $sql_membres = "SELECT COUNT(*) as nb_membres 
                    FROM Adhesion a 
                    JOIN Utilisateur u ON a.IdParticipant = u.IdUtilisateur 
                    WHERE a.IdClub = :club_id AND a.Status = 'actif'";
    $stmt_membres = $conn->prepare($sql_membres);
    $stmt_membres->bindParam(':club_id', $club_id);
    $stmt_membres->execute();
    $nb_membres = $stmt_membres->fetch(PDO::FETCH_ASSOC)['nb_membres'];
    
    // Nombre d'événements
    $sql_events = "SELECT COUNT(*) as nb_evenements 
                   FROM Evenement 
                   WHERE IdClub = :club_id";
    $stmt_events = $conn->prepare($sql_events);
    $stmt_events->bindParam(':club_id', $club_id);
    $stmt_events->execute();
    $nb_evenements = $stmt_events->fetch(PDO::FETCH_ASSOC)['nb_evenements'];
    
    // Prochains événements
    $sql_prochains_events = "SELECT NomEvenement, Date, Lieu 
                             FROM evenement 
                             WHERE IdClub = :club_id AND Date >= CURDATE() 
                             ORDER BY Date ASC 
                             LIMIT 3";
    $stmt_prochains = $conn->prepare($sql_prochains_events);
    $stmt_prochains->bindParam(':club_id', $club_id);
    $stmt_prochains->execute();
    $prochains_events = $stmt_prochains->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $nb_membres = 0;
    $nb_evenements = 0;
    $prochains_events = [];
}
// Vérifier si c'est une mise à jour
$is_updated = isset($_GET['updated']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_updated ? 'Club modifié' : 'Club créé'; ?> avec succès - Event Manager</title>
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
                        <a href="gerer_clubs.php" class="sidebar-nav-link-modern active">
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
            <div class="alert-modern alert-success-modern" style="margin-bottom: var(--space-xl);">
                <div class="alert-icon-modern">🎉</div>
                <div class="alert-content-modern">
                    <div class="alert-title-modern">
                        <?php echo $is_updated ? 'Club modifié avec succès !' : 'Club créé avec succès !'; ?>
                    </div>
                    <div class="alert-message-modern">
                        <?php if ($email_sent): ?>
                            Un email de confirmation a été envoyé à l'administrateur du club.
                        <?php else: ?>
                            Le club a été créé et est maintenant disponible dans le système.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section-modern">
                <h3>📋 Récapitulatif du club</h3>
                
                <div class="club-header-modern" style="margin-bottom: var(--space-xl);">
                    <?php if (!empty($club_data['Logo'])): ?>
                        <img src="../uploads/clubs/<?php echo htmlspecialchars($club_data['Logo']); ?>" 
                             alt="Logo" class="club-logo-modern" style="width: 120px; height: 120px; border-radius: var(--radius-lg); margin-bottom: var(--space-md);">
                    <?php endif; ?>
                    <div>
                        <h2 style="color: var(--primary); margin-bottom: var(--space-sm);"><?php echo htmlspecialchars($club_data['NomClub']); ?></h2>
                        
                    </div>
                </div>

                <div class="stats-grid-modern" style="margin-bottom: var(--space-xl);">
                    <div class="stat-card-modern">
                        <div class="stat-icon-modern">👥</div>
                        <div class="stat-content-modern">
                            <div class="stat-value-modern"><?php echo $nb_membres; ?></div>
                            <div class="stat-label-modern">Membres</div>
                        </div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-icon-modern">📅</div>
                        <div class="stat-content-modern">
                            <div class="stat-value-modern"><?php echo $nb_evenements; ?></div>
                            <div class="stat-label-modern">Événements</div>
                        </div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-icon-modern">👤</div>
                        <div class="stat-content-modern">
                            <div class="stat-value-modern"><?php echo htmlspecialchars($club_data['admin_prenom'] ?? 'N/A'); ?></div>
                            <div class="stat-label-modern">Administrateur</div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; gap: var(--space-lg); margin-bottom: var(--space-xl);">
                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: var(--space-sm);">Informations du club</h4>
                        <div style="display: grid; gap: var(--space-sm); background: var(--bg-secondary); padding: var(--space-lg); border-radius: var(--radius-md);">
                            <div><strong>Nom :</strong> <?php echo htmlspecialchars($club_data['NomClub']); ?></div>
                            <div><strong>Description :</strong> <span class="badge badge-info"><?php echo htmlspecialchars($club_data['Description']); ?></span></div>
                            
                            <div><strong>Date de création :</strong> <?php echo date('d/m/Y', strtotime($club_data['DateCreation'])); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($club_data['admin_nom'])): ?>
                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: var(--space-sm);">Administrateur</h4>
                        <div style="display: grid; gap: var(--space-sm); background: var(--bg-secondary); padding: var(--space-lg); border-radius: var(--radius-md);">
                            <div><strong>Nom complet :</strong> <?php echo htmlspecialchars($club_data['admin_prenom'] . ' ' . $club_data['admin_nom']); ?></div>
                            <div><strong>Email :</strong> <a href="mailto:<?php echo htmlspecialchars($club_data['admin_email']); ?>"><?php echo htmlspecialchars($club_data['admin_email']); ?></a></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($prochains_events)): ?>
                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: var(--space-sm);">Prochains événements</h4>
                        <div style="display: grid; gap: var(--space-sm);">
                            <?php foreach ($prochains_events as $event): ?>
                                <div style="background: var(--bg-secondary); padding: var(--space-md); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($event['NomEvenement']); ?></div>
                                        <div style="font-size: 0.9em; color: var(--text-secondary);">📍 <?php echo htmlspecialchars($event['Lieu']); ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="badge badge-info"><?php echo date('d/m/Y', strtotime($event['Date'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions-modern">
                    <a href="dashboard.php" class="btn btn-ghost">
                        ← Retour au dashboard
                    </a>
                    <div style="display: flex; gap: var(--space-sm);">
                        <a href="gerer_clubs.php" class="btn btn-outline">
                            📋 Gérer les clubs
                        </a>
                        <a href="creer_club.php" class="btn btn-primary">
                            <span class="btn-icon">➕</span>
                            Créer un autre club
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
