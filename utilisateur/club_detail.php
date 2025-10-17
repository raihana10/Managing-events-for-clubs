<?php
// utilisateur/club_detail.php
require_once '../config/database.php';
require_once '../config/session.php';

$currentPage = 'club_detail.php';

requireLogin();
requireRole(['participant']);

$database = new Database();
$db = $database->getConnection();

$club = null;
$evenements_club = [];
$is_member = false; // Nouvelle variable !
$user_id = $_SESSION['user_id'];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_club = (int)$_GET['id'];

    // Récupérer les détails du club
    $query_club = "SELECT c.*, u.Nom as AdminNom, u.Prenom as AdminPrenom FROM Club c JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur WHERE c.IdClub = :id_club LIMIT 1";
    $stmt_club = $db->prepare($query_club);
    $stmt_club->bindParam(':id_club', $id_club);
    $stmt_club->execute();
    $club = $stmt_club->fetch(PDO::FETCH_ASSOC);

    if ($club) {
        // VÉRIFICATION : L'utilisateur est-il membre de ce club ?
        $query_check_adhesion = "SELECT IdAdhesion FROM Adhesion WHERE IdParticipant = :user_id AND IdClub = :club_id AND Status = 'actif' LIMIT 1";
        $stmt_check_adhesion = $db->prepare($query_check_adhesion);
        $stmt_check_adhesion->bindParam(':user_id', $user_id);
        $stmt_check_adhesion->bindParam(':club_id', $id_club);
        $stmt_check_adhesion->execute();
        if ($stmt_check_adhesion->rowCount() > 0) {
            $is_member = true;
        }

        // Récupérer les événements de ce club en appliquant la logique de filtrage
        $query_events = "SELECT e.* FROM Evenement e WHERE e.IdClub = :id_club AND e.Etat = 'valide' AND e.Date >= CURDATE()";
        
        // Si l'utilisateur n'est PAS membre, il ne voit que les événements pour 'Tous' ou 'Tous les étudiants'
        if (!$is_member) {
            $query_events .= " AND (e.TypeParticipant = 'Tous' OR e.TypeParticipant = 'Tous les étudiants')";
        }
        // Si l'utilisateur EST membre, il voit tout ('Tous' ET 'Adhérents'), donc pas de filtre supplémentaire.

        $query_events .= " ORDER BY e.Date ASC, e.HeureDebut ASC";

        $stmt_events = $db->prepare($query_events);
        $stmt_events->bindParam(':id_club', $id_club);
        $stmt_events->execute();
        $evenements_club = $stmt_events->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // ... (gestion d'erreur, inchangée)
    }
} else {
    // ... (gestion d'erreur, inchangée)
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Club - <?php echo htmlspecialchars($club['NomClub'] ?? 'Club Inconnu'); ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_sidebar.php'; ?>
    <?php include '_navbar.php'; ?>
    
    <!-- Contenu principal avec padding pour éviter la sidebar -->
    <div style="padding: 20px;">
        <?php if ($club): ?>
            <div class="page-title">
                <h1><?php echo htmlspecialchars($club['NomClub']); ?></h1>
                <?php if ($is_member): ?>
                    <div class="alert-modern alert-success-modern" style="margin-top: 10px;">
                        ✓ Vous êtes membre de ce club
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2>À propos du club</h2>
                    <p><?php echo htmlspecialchars($club['Description'] ?? 'Aucune description disponible.'); ?></p>
                    
                    <div class="grid grid-cols-2 gap-lg" style="margin-top: 20px;">
                        <div>
                            <strong>Administrateur :</strong><br>
                            <?php echo htmlspecialchars($club['AdminPrenom'] . ' ' . $club['AdminNom']); ?>
                        </div>
                        <div>
                            <strong>Date de création :</strong><br>
                            <?php echo date('d/m/Y', strtotime($club['DateCreation'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 30px;">
                <div class="card-header">
                    <h2>Événements de <?php echo htmlspecialchars($club['NomClub']); ?></h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($evenements_club)): ?>
                        <div class="events-grid-modern">
                            <?php foreach ($evenements_club as $event): ?>
                            <div class="event-card-modern">
                                <div class="event-content-modern">
                                    <h3 class="event-title-modern"><?php echo htmlspecialchars($event['NomEvenement']); ?></h3>
                                    
                                    <div class="event-meta-modern">
                                        <div class="event-meta-item-modern">
                                            <span class="event-meta-icon-modern">📅</span>
                                            <?php echo date('d/m/Y', strtotime($event['Date'])); ?>
                                        </div>
                                        <?php if (!empty($event['Lieu'])): ?>
                                        <div class="event-meta-item-modern">
                                            <span class="event-meta-icon-modern">📍</span>
                                            <?php echo htmlspecialchars($event['Lieu']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="margin-bottom: 15px;">
                                        <?php if ($event['TypeParticipant'] == 'Adhérents'): ?>
                                            <span class="badge badge-warning">Réservé aux adhérents</span>
                                        <?php elseif ($event['TypeParticipant'] == 'Tous les étudiants'): ?>
                                            <span class="badge badge-info">Ouvert à tous les étudiants</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Ouvert à tous</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-actions-modern">
                                        <a href="inscription_evenement.php?id=<?php echo (int)$event['IdEvenement']; ?>" class="btn btn-primary btn-sm">Voir détails</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-modern">
                            <div class="empty-state-icon-modern">📅</div>
                            <h3>Aucun événement disponible</h3>
                            <p>Aucun événement disponible pour vous dans ce club pour le moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Fermer la div de contenu principal -->
    </div>
</body>
</html>