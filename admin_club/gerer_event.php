<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les informations du club
$club_query = "SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les événements du club
$events_query = "SELECT e.*, 
                 COUNT(i.IdInscription) as nb_inscriptions,
                 CASE 
                     WHEN e.CapaciteMax IS NOT NULL AND COUNT(i.IdInscription) >= e.CapaciteMax THEN 'Complet'
                     WHEN e.Date < CURDATE() THEN 'Terminé'
                     WHEN e.Etat = 'valide' THEN 'Actif'
                     WHEN e.Etat = 'en attente' THEN 'En attente'
                     WHEN e.Etat = 'refuse' THEN 'Refusé'
                     ELSE 'Inconnu'
                 END as statut
                 FROM Evenement e 
                 LEFT JOIN Inscription i ON e.IdEvenement = i.IdEvenement
                 WHERE e.IdClub = :club_id 
                 GROUP BY e.IdEvenement
                 ORDER BY e.Date DESC, e.HeureDebut DESC";
$stmt = $db->prepare($events_query);
$stmt->bindParam(':club_id', $club['IdClub']);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['event_id'])) {
        $action = $_POST['action'];
        $event_id = (int)$_POST['event_id'];
        
        switch ($action) {
            case 'delete':
                try {
                    // Vérifier s'il y a des inscriptions
                    $check_query = "SELECT COUNT(*) as nb_inscriptions FROM Inscription WHERE IdEvenement = :event_id";
                    $stmt = $db->prepare($check_query);
                    $stmt->bindParam(':event_id', $event_id);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['nb_inscriptions'] > 0) {
                        $error_message = "Impossible de supprimer cet événement car il contient des inscriptions.";
                    } else {
                        // Supprimer l'affiche si elle existe
                        $affiche_query = "SELECT Affiche FROM Evenement WHERE IdEvenement = :event_id";
                        $stmt = $db->prepare($affiche_query);
                        $stmt->bindParam(':event_id', $event_id);
                        $stmt->execute();
                        $affiche = $stmt->fetch(PDO::FETCH_ASSOC)['Affiche'];
                        
                        if ($affiche && file_exists('../uploads/affiches/' . $affiche)) {
                            unlink('../uploads/affiches/' . $affiche);
                        }
                        
                        // Supprimer l'événement
                        $delete_query = "DELETE FROM Evenement WHERE IdEvenement = :event_id AND IdClub = :club_id";
                        $stmt = $db->prepare($delete_query);
                        $stmt->bindParam(':event_id', $event_id);
                        $stmt->bindParam(':club_id', $club['IdClub']);
                        $stmt->execute();
                        
                        $success_message = "Événement supprimé avec succès.";
                        // Recharger la page pour mettre à jour la liste
                        header("Location: gerer_event.php");
                        exit;
                    }
                } catch (PDOException $e) {
                    $error_message = "Erreur lors de la suppression : " . $e->getMessage();
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les événements - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">Event Manager</a>
            <div class="header-right">
                <div class="club-info">
                    <span class="club-badge"></span>
                    <span><?php echo htmlspecialchars($club['NomClub'] ?? 'Mon Club'); ?></span>
                </div>
                <div class="user-section">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                        <div class="user-role">Administrateur du club</div>
                    </div>
                    <?php $initials = strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)); ?>
                    <div class="user-avatar-modern"><?php echo $initials; ?></div>
                    <button class="btn btn-ghost btn-sm" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="layout">
            <?php include __DIR__ . '/_sidebar.php'; ?>

            <main class="main-content">
                <div class="page-title">
                    <h1>Gérer les événements</h1>
                    <p>Gérez tous les événements de votre club</p>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert-modern alert-error-modern">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success_message)): ?>
                    <div class="alert-modern alert-success-modern">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <div class="quick-actions-modern">
                    <h2 class="quick-actions-title-modern">Actions rapides</h2>
                    <div class="actions-grid-modern">
                        <a href="creer_event.php" class="action-card-modern">
                            <div class="action-icon-modern">➕</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Créer un événement</div>
                                <div class="action-description-modern">Organisez un nouvel événement</div>
                            </div>
                        </a>
                        <a href="membres.php" class="action-card-modern">
                            <div class="action-icon-modern">👥</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Voir les membres</div>
                                <div class="action-description-modern">Consultez la liste des membres</div>
                            </div>
                        </a>
                        <a href="recap_evenements.php" class="action-card-modern">
                            <div class="action-icon-modern">📈</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Récapitulatif</div>
                                <div class="action-description-modern">Statistiques et rapports</div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="table-modern">
                    <div class="table-header-modern">
                        <h2 class="table-title-modern">Événements du club</h2>
                        <div class="flex gap-sm">
                            <a href="creer_event.php" class="btn btn-primary btn-sm">Créer un événement</a>
                        </div>
                    </div>
                    
                    <?php if (!empty($events)): ?>
                        <div class="events-grid-modern">
                            <?php foreach ($events as $event): ?>
                                <div class="event-card-modern">
                                    <div class="event-image-modern">
                                        <div class="event-date-badge-modern">
                                            <?php echo date('d M Y', strtotime($event['Date'])); ?>
                                        </div>
                                        <?php if ($event['statut'] == 'Complet'): ?>
                                            <div class="event-status-badge-modern" style="background: var(--error); color: white; padding: var(--space-xs) var(--space-sm); border-radius: var(--border-radius-sm); font-size: 0.75rem; font-weight: 600; position: absolute; top: var(--space-sm); left: var(--space-sm);">
                                                COMPLET
                                            </div>
                                        <?php elseif ($event['statut'] == 'En attente'): ?>
                                            <div class="event-status-badge-modern" style="background: var(--warning); color: white; padding: var(--space-xs) var(--space-sm); border-radius: var(--border-radius-sm); font-size: 0.75rem; font-weight: 600; position: absolute; top: var(--space-sm); left: var(--space-sm);">
                                                EN ATTENTE
                                            </div>
                                        <?php elseif ($event['statut'] == 'Refusé'): ?>
                                            <div class="event-status-badge-modern" style="background: var(--error); color: white; padding: var(--space-xs) var(--space-sm); border-radius: var(--border-radius-sm); font-size: 0.75rem; font-weight: 600; position: absolute; top: var(--space-sm); left: var(--space-sm);">
                                                REFUSÉ
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-content-modern">
                                        <div class="event-title-modern"><?php echo htmlspecialchars($event['NomEvenement']); ?></div>
                                        
                                        <div class="event-meta-modern">
                                            <div class="event-meta-item-modern">
                                                <div class="event-meta-icon-modern">🕒</div>
                                                <span><?php echo date('H:i', strtotime($event['HeureDebut'])); ?> - <?php echo date('H:i', strtotime($event['HeureFin'])); ?></span>
                                            </div>
                                            <div class="event-meta-item-modern">
                                                <div class="event-meta-icon-modern">📍</div>
                                                <span><?php echo htmlspecialchars($event['Lieu']); ?></span>
                                            </div>
                                            <div class="event-meta-item-modern">
                                                <div class="event-meta-icon-modern">👥</div>
                                                <span><?php echo $event['nb_inscriptions']; ?> inscriptions</span>
                                            </div>
                                        </div>
                                        
                                        <div class="event-price-modern">
                                            <div class="badge badge-info"><?php echo $event['statut']; ?></div>
                                        </div>
                                        
                                        <div class="event-actions-modern">
                                            <a href="modifier_event.php?id=<?php echo $event['IdEvenement']; ?>" class="btn btn-outline btn-sm">Modifier</a>
                                            <a href="participants.php?id=<?php echo $event['IdEvenement']; ?>" class="btn btn-outline btn-sm">Participants</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="event_id" value="<?php echo $event['IdEvenement']; ?>">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--error); border-color: var(--error);">Supprimer</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-modern">
                            <div class="empty-state-icon-modern">📅</div>
                            <h3>Aucun événement</h3>
                            <p>Vous n'avez pas encore créé d'événements pour votre club.</p>
                            <a href="creer_event.php" class="btn btn-primary">Créer votre premier événement</a>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
