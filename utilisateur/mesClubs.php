<?php
// utilisateur/clubs.php
require_once '../config/database.php';
require_once '../config/session.php';

$currentPage = 'mes-clubs';

requireLogin(); // Redirige si l'utilisateur n'est pas connecté
requireRole(['participant']); // S'assure que seul un participant peut accéder

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Récupérer UNIQUEMENT les clubs dont l'utilisateur est membre avec leurs statistiques réelles
$user_id = $_SESSION['user_id'];
$query = "SELECT c.IdClub, c.NomClub, c.Description, c.Logo, u.Nom as AdminNom, u.Prenom as AdminPrenom,
          (SELECT COUNT(*) FROM Adhesion a2 WHERE a2.IdClub = c.IdClub AND a2.Status = 'actif') as nb_membres,
          (SELECT COUNT(*) FROM Evenement e WHERE e.IdClub = c.IdClub AND e.Etat = 'valide' AND e.Date >= CURDATE()) as nb_evenements
          FROM Adhesion a
          JOIN Club c ON a.IdClub = c.IdClub
          JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
          WHERE a.IdParticipant = :user_id AND a.Status = 'actif'
          ORDER BY c.NomClub ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
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
    <title>Mes Clubs - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_sidebar.php'; ?>
    <?php include '_navbar.php'; ?>
    
    <!-- Contenu principal avec padding pour éviter la sidebar -->
    <div style="padding: 20px;">
        <div class="page-title">
            <h1>Mes Clubs </h1>
            <p>EVous n'êtes membre d'aucun club pour le moment.</p>
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
                            
                        <?php endif; ?>
                    </div>
                    
                    <div class="club-name-modern"><?php echo htmlspecialchars($club['NomClub']); ?></div>
                    <div class="club-description-modern">
                        <?php echo htmlspecialchars(mb_strimwidth($club['description'] ?? 'Aucune description fournie.', 0, 120, '...')); ?>
                    </div>
                    
                    <div class="club-admin-modern">
                        <div class="club-admin-label-modern">Administrateur</div>
                        <div class="club-admin-name-modern"><?php echo htmlspecialchars($club['AdminPrenom'] . ' ' . $club['AdminNom']); ?></div>
                    </div>
                    
                    <div class="club-stats-modern">
                        <div class="club-stat-modern">
                            <div class="club-stat-value-modern"><?php echo (int)$club['nb_membres']; ?></div>
                            <div class="club-stat-label-modern">Membres</div>
                        </div>
                        <div class="club-stat-modern">
                            <div class="club-stat-value-modern"><?php echo (int)$club['nb_evenements']; ?></div>
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
    
    <!-- Fermer la div de contenu principal -->
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>