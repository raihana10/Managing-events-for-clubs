<?php
// utilisateur/clubs.php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin(); // Redirige si l'utilisateur n'est pas connecté
requireRole(['organisateur']); // S'assure que seul un organisateur peut accéder

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Récupérer la liste de tous les clubs
$query = "SELECT c.IdClub, c.NomClub, c.Description, c.Logo, u.Nom as AdminNom, u.Prenom as AdminPrenom 
          FROM Club c 
          JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur 
          ORDER BY c.NomClub ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gérer les messages de session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubs - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">Event Manager</a>
            <nav class="nav-main">
                <a href="dashboard.php" class="nav-link-modern">Accueil</a>
                <a href="clubs.php" class="nav-link-modern active">Clubs</a>
                <a href="evenements.php" class="nav-link-modern">Événements</a>
                <a href="mes_inscriptions.php" class="nav-link-modern">Mes inscriptions</a>
            </nav>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                    <div class="user-role">Participant</div>
                </div>
                <?php $initials = strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)); ?>
                <div class="user-avatar-modern"><?php echo $initials; ?></div>
                <button class="btn btn-ghost btn-sm" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="page-title">
            <h1>Nos Clubs</h1>
            <p>Découvrez tous les clubs disponibles sur la plateforme</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-modern <?php echo $message_type === 'success' ? 'alert-success-modern' : 'alert-error-modern'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="clubs-grid-modern">
            <?php if (!empty($clubs)): ?>
                <?php foreach ($clubs as $club): ?>
                <div class="club-card-modern">
                    <div class="club-logo-modern">
                        <?php 
                        $logo_path = !empty($club['Logo']) && file_exists('../uploads/clubs/' . $club['Logo']) 
                                   ? '../uploads/clubs/' . $club['Logo'] 
                                   : null;
                        ?>
                        <?php if ($logo_path): ?>
                            <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo <?php echo htmlspecialchars($club['NomClub']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--border-radius-lg);">
                        <?php else: ?>
                            🏛️
                        <?php endif; ?>
                    </div>
                    
                    <div class="club-name-modern"><?php echo htmlspecialchars($club['NomClub']); ?></div>
                    <div class="club-description-modern">
                        <?php echo htmlspecialchars(mb_strimwidth($club['Description'] ?? 'Aucune description fournie.', 0, 120, '...')); ?>
                    </div>
                    
                    <div class="club-admin-modern">
                        <div class="club-admin-label-modern">Administrateur</div>
                        <div class="club-admin-name-modern"><?php echo htmlspecialchars($club['AdminPrenom'] . ' ' . $club['AdminNom']); ?></div>
                    </div>
                    
                    <div class="club-stats-modern">
                        <div class="club-stat-modern">
                            <div class="club-stat-value-modern">12</div>
                            <div class="club-stat-label-modern">Membres</div>
                        </div>
                        <div class="club-stat-modern">
                            <div class="club-stat-value-modern">5</div>
                            <div class="club-stat-label-modern">Événements</div>
                        </div>
                    </div>
                    
                    <div class="club-actions-modern">
                        <a href="club_detail.php?id=<?php echo (int)$club['IdClub']; ?>" class="btn btn-primary btn-sm">Voir les événements</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-state-icon-modern">🏛️</div>
                    <h3>Aucun club disponible</h3>
                    <p>Revenez bientôt pour découvrir les nouveaux clubs.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>