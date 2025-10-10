<?php
// utilisateur/club_detail.php
require_once '../config/database.php';
require_once '../config/session.php';

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
    <title>Détails du Club - <?php echo htmlspecialchars($club['NomClub'] ?? 'Club Inconnu'); ?></title>
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
        <?php if ($club): ?>
            <div style="background-color: white; padding: 30px; border-radius: 8px;">
                <!-- ... (Infos du club, inchangées) ... -->
                <h1><?php echo htmlspecialchars($club['NomClub']); ?></h1>
                <?php if ($is_member): ?>
                    <p style="background-color: #d4edda; color: #155724; padding: 5px 10px; border-radius: 5px; display: inline-block;">✓ Vous êtes membre de ce club</p>
                <?php endif; ?>
                <!-- ... (Reste des infos du club) ... -->
            </div>

            <div style="margin-top: 30px;">
                <h2>Événements de <?php echo htmlspecialchars($club['NomClub']); ?></h2>
                <?php if (!empty($evenements_club)): ?>
                    <?php foreach ($evenements_club as $event): ?>
                    <div style="background-color: white; border: 1px solid #eee; padding: 15px; margin-bottom: 10px;">
                        <h3><?php echo htmlspecialchars($event['NomEvenement']); ?></h3>
                        <p>
                            <!-- ... (Détails de l'événement) ... -->
                            <!-- NOUVEAU : Afficher un badge pour les événements réservés -->
                            <?php if ($event['TypeParticipant'] == 'Adhérents'): ?>
                                <span style="background-color: #ffc107; color: black; padding: 3px 8px; border-radius: 10px; font-size: 0.8em;">Réservé aux adhérents</span>
                            <?php elseif ($event['TypeParticipant'] == 'Tous les étudiants'): ?>
                                <span style="background-color: #cfe2ff; color: #055160; padding: 3px 8px; border-radius: 10px; font-size: 0.8em;">Ouvert à tous les étudiants</span>
                            <?php else: ?>
                                <span style="background-color: #cfe2ff; color: #055160; padding: 3px 8px; border-radius: 10px; font-size: 0.8em;">Ouvert à tous</span>
                            <?php endif; ?>
                        </p>
                        <a href="inscription_evenement.php?id=<?php echo (int)$event['IdEvenement']; ?>">Voir détails</a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucun événement disponible pour vous dans ce club pour le moment.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>