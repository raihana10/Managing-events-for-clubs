<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Vérifier que c'est bien un participant
requireRole(['participant']);

$database = new Database();
$db = $database->getConnection();

// Récupérer les données de l'utilisateur
$user_id = $_SESSION['user_id'];

// Statistiques simplifiées (les tables Inscription/Adhesion ne correspondent pas au schéma fourni)
$stats = [
    'nb_inscriptions' => 0,
    'nb_clubs' => 0,
];

// Événements à venir - récupérer tous les événements valides
$query = "SELECT e.*, c.NomClub, c.Logo 
          FROM Evenement e 
          JOIN Club c ON e.IdClub = c.IdClub 
          WHERE e.Date >= CURDATE() AND e.Etat = 'valide'
          ORDER BY e.Date 
          LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrer les événements selon le type de participant et les adhésions de l'utilisateur
$evenements = [];
foreach ($all_events as $event) {
    $is_event_visible = false;
    
    // Vérifier si l'événement est visible pour cet utilisateur
    if ($event['TypeParticipant'] == 'Tous' || $event['TypeParticipant'] == 'Tous les étudiants') {
        $is_event_visible = true;
    } elseif ($event['TypeParticipant'] == 'Adhérents') {
        // Vérifier si l'utilisateur est membre du club
        $query_membership = "SELECT COUNT(*) FROM Adhesion WHERE IdParticipant = :user_id AND IdClub = :club_id AND Status = 'actif'";
        $stmt_membership = $db->prepare($query_membership);
        $stmt_membership->bindParam(':user_id', $user_id);
        $stmt_membership->bindParam(':club_id', $event['IdClub']);
        $stmt_membership->execute();
        $is_member = $stmt_membership->fetchColumn() > 0;
        
        if ($is_member) {
            $is_event_visible = true;
        }
    }
    
    if ($is_event_visible) {
        $evenements[] = $event;
    }
}

// Ajouter les informations de prix pour chaque événement
foreach ($evenements as &$event) {
    // Vérifier si l'utilisateur est membre du club
    $query_membership = "SELECT COUNT(*) FROM Adhesion WHERE IdParticipant = :user_id AND IdClub = :club_id AND Status = 'actif'";
    $stmt_membership = $db->prepare($query_membership);
    $stmt_membership->bindParam(':user_id', $user_id);
    $stmt_membership->bindParam(':club_id', $event['IdClub']);
    $stmt_membership->execute();
    $is_member = $stmt_membership->fetchColumn() > 0;
    
    if ($is_member) {
        $event['user_price'] = $event['PrixAdherent'];
        $event['user_type'] = "Adhérent";
    } else {
        $event['user_price'] = $event['PrixNonAdherent'];
        $event['user_type'] = "Non-adhérent";
    }
}

// Clubs récents (le schéma fourni n'a pas de compteur de membres)
$query = "SELECT c.* FROM Club c ORDER BY c.DateCreation DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">Event Manager</a>
            <nav class="nav-main">
                <a href="dashboard.php" class="nav-link-modern active">Accueil</a>
                <a href="clubs.php" class="nav-link-modern">Clubs</a>
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
        <div class="dashboard-hero">
            <div class="dashboard-hero-content">
                <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['prenom']); ?> !</h1>
                <p>Découvrez les clubs et événements de votre école</p>
            </div>
        </div>

        <div class="stats-grid-modern">
            <div class="stat-card-modern">
                <div class="stat-header-modern">
                    <div class="stat-label-modern">Clubs rejoints</div>
                    <div class="stat-icon-modern coral">👥</div>
                </div>
                <div class="stat-value-modern"><?php echo (int)$stats['nb_clubs']; ?></div>
                <div class="stat-change-modern positive">+2 cette semaine</div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-header-modern">
                    <div class="stat-label-modern">Événements inscrits</div>
                    <div class="stat-icon-modern teal">📅</div>
                </div>
                <div class="stat-value-modern"><?php echo (int)$stats['nb_inscriptions']; ?></div>
                <div class="stat-change-modern positive">+1 cette semaine</div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-header-modern">
                    <div class="stat-label-modern">Événements participés</div>
                    <div class="stat-icon-modern blue">✅</div>
                </div>
                <div class="stat-value-modern">0</div>
                <div class="stat-change-modern">Aucun récent</div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-header-modern">
                    <div class="stat-label-modern">Attestations</div>
                    <div class="stat-icon-modern purple">📜</div>
                </div>
                <div class="stat-value-modern">0</div>
                <div class="stat-change-modern">Aucune disponible</div>
            </div>
        </div>

        <div class="quick-actions-modern">
            <h2 class="quick-actions-title-modern">Actions rapides</h2>
            <div class="actions-grid-modern">
                <a href="clubs.php" class="action-card-modern">
                    <div class="action-icon-modern">🏛️</div>
                    <div class="action-content-modern">
                        <div class="action-title-modern">Découvrir les clubs</div>
                        <div class="action-description-modern">Explorez tous les clubs disponibles</div>
                    </div>
                </a>
                <a href="evenements.php" class="action-card-modern">
                    <div class="action-icon-modern">🎯</div>
                    <div class="action-content-modern">
                        <div class="action-title-modern">Voir les événements</div>
                        <div class="action-description-modern">Découvrez les prochains événements</div>
                    </div>
                </a>
                <a href="mes_inscriptions.php" class="action-card-modern">
                    <div class="action-icon-modern">📋</div>
                    <div class="action-content-modern">
                        <div class="action-title-modern">Mes inscriptions</div>
                        <div class="action-description-modern">Gérez vos inscriptions</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-xl">
            <div class="card">
                <div class="card-header">
                    <h3>Mes clubs</h3>
                    <a href="clubs.php" class="btn btn-primary btn-sm">Voir tout →</a>
                </div>
                <div class="card-body">
                    <div class="clubs-grid-modern">
                        <?php foreach ($clubs as $club): ?>
                        <div class="club-card-modern">
                            <div class="club-logo-modern">🏛️</div>
                            <div class="club-name-modern"><?php echo htmlspecialchars($club['NomClub']); ?></div>
                            <div class="club-description-modern">
                                <?php echo htmlspecialchars(mb_strimwidth($club['Description'] ?? '', 0, 100, '...')); ?>
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
                            <div class="flex gap-sm">
                                <a href="club_detail.php?id=<?php echo (int)$club['IdClub']; ?>" class="btn btn-primary btn-sm">Voir les événements</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($clubs)): ?>
                            <div class="text-center p-xl" style="grid-column: 1 / -1;">
                                <div class="text-neutral-500 mb-md">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">🏛️</div>
                                    <h3>Aucun club disponible</h3>
                                    <p>Découvrez les clubs depuis la page Clubs.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Prochains événements</h3>
                    <a href="evenements.php" class="btn btn-outline btn-sm">Voir tous →</a>
                </div>
                <div class="card-body">
                    <div class="events-grid-modern">
                        <?php foreach ($evenements as $event): ?>
                        <div class="event-card-modern">
                            <div class="event-image-modern">
                                <div class="event-date-badge-modern"><?php echo date('d F Y', strtotime($event['Date'])); ?></div>
                            </div>
                            <div class="event-content-modern">
                                <div class="event-title-modern"><?php echo htmlspecialchars($event['NomEvenement']); ?></div>
                                <div class="event-meta-modern">
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">🏛️</div>
                                        <span><?php echo htmlspecialchars($event['NomClub']); ?></span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">📍</div>
                                        <span><?php echo htmlspecialchars($event['Lieu']); ?></span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">⏰</div>
                                        <span><?php echo htmlspecialchars($event['HeureDebut'] . ' - ' . $event['HeureFin']); ?></span>
                                    </div>
                                </div>
                                <div class="event-price-modern">
                                    <div class="price-modern">
                                        <?php if ($event['user_price'] == 0 || $event['user_price'] === null): ?>
                                            <span class="price-free-modern">Gratuit</span>
                                        <?php else: ?>
                                            <?php echo number_format(floatval($event['user_price']), 2); ?> €
                                        <?php endif; ?>
                                    </div>
                                    <div class="badge badge-info"><?php echo $event['user_type']; ?></div>
                                </div>
                                <div class="event-actions-modern">
                                    <a href="inscription_evenement.php?id=<?php echo (int)$event['IdEvenement']; ?>" class="btn btn-primary btn-sm">Voir détails</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($evenements)): ?>
                            <div class="text-center p-xl">
                                <div class="text-neutral-500 mb-md">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                                    <h3>Aucun événement à venir</h3>
                                    <p>Revenez bientôt pour découvrir les prochains événements.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>