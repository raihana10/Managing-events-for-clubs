<?php
// --- REMPLACER TOUT LE PHP PAR CECI ---
require_once '../config/database.php';
require_once '../config/session.php';


// Vérifier que c'est bien un participant
requireRole(['participant']);
$currentPage = 'accueil';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// 1. Récupérer les clubs dont l'utilisateur EST MEMBRE
$query_mes_clubs = "SELECT c.IdClub, c.NomClub, c.Description, c.Logo FROM Adhesion a
                    JOIN Club c ON a.IdClub = c.IdClub
                    WHERE a.IdParticipant = :user_id AND a.Status = 'actif'
                    ORDER BY c.NomClub ASC";
$stmt_mes_clubs = $db->prepare($query_mes_clubs);
$stmt_mes_clubs->bindParam(':user_id', $user_id);
$stmt_mes_clubs->execute();
$mes_clubs = $stmt_mes_clubs->fetchAll(PDO::FETCH_ASSOC);
$mes_clubs_ids = array_column($mes_clubs, 'IdClub'); // Un tableau simple avec juste les IDs des clubs de l'utilisateur

// 2. Mettre à jour les statistiques
$stats = [
    'nb_clubs' => count($mes_clubs),
    'nb_inscriptions' => 0, // À faire : Compter les inscriptions depuis la table Inscription
];

// 3. Récupérer une sélection d'autres clubs à DÉCOUVRIR
$query_decouvrir = "SELECT IdClub, NomClub, Description, Logo FROM Club ORDER BY DateCreation DESC LIMIT 3";
$stmt_decouvrir = $db->prepare($query_decouvrir);
$stmt_decouvrir->execute();
$clubs_a_decouvrir = $stmt_decouvrir->fetchAll(PDO::FETCH_ASSOC);

// 4. Récupérer TOUS les prochains événements pour le dashboard
$query_all_events = "SELECT e.*, c.NomClub FROM Evenement e 
                     JOIN Club c ON e.IdClub = c.IdClub 
                     WHERE e.Date >= CURDATE() AND e.Etat = 'valide'
                     ORDER BY e.Date ASC LIMIT 6";
$stmt_all_events = $db->prepare($query_all_events);
$stmt_all_events->execute();
$all_events = $stmt_all_events->fetchAll(PDO::FETCH_ASSOC);

// --- FIN DU BLOC PHP ---
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
    <?php include '_sidebar.php'; ?>
    <?php include '_navbar.php'; ?>
    
    <!-- Contenu principal avec padding pour éviter la sidebar -->
    <div style="padding: 20px;">

        <div class="dashboard-hero">
            <div class="dashboard-hero-content">
                <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['prenom']); ?> !</h1>
                <p>Découvrez les clubs et événements de votre école</p>
            </div>
        </div>

        <div class="container"></div>

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
            <!-- === DÉBUT DU BLOC FINAL À COLLER === -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-xl">

    <!-- COLONNE DE GAUCHE : MES CLUBS -->
    <div class="card">
        <div class="card-header">
            <h3>Mes clubs</h3>
            <a href="mesClubs.php" class="btn btn-outline btn-sm">Voir tout →</a>
        </div>
        <div class="card-body">
            <div class="clubs-grid-modern">
                <?php if (!empty($mes_clubs)): ?>
                    <?php foreach ($mes_clubs as $club): ?>
                        <div class="club-card-modern">
                            <div class="club-logo-modern">
                                <?php if (!empty($club['Logo'])): ?>
                                    <img src="../uploads/clubs/<?php echo htmlspecialchars($club['Logo']); ?>" alt="<?php echo htmlspecialchars($club['NomClub']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    🏛️
                                <?php endif; ?>
                            </div>
                            <div class="club-name-modern"><?php echo htmlspecialchars($club['NomClub']); ?></div>
                            <a href="club_detail.php?id=<?php echo (int)$club['IdClub']; ?>" class="btn btn-primary btn-sm">Voir le club</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center p-xl" style="grid-column: 1 / -1;">
                        <div class="text-neutral-500 mb-md">
                            <h3>Vous n'êtes membre d'aucun club</h3>
                            <a href="clubs.php" class="btn btn-primary" style="margin-top: 1rem;">Découvrir les clubs</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COLONNE DE DROITE : PROCHAINS ÉVÉNEMENTS -->
    <div class="card">
        <div class="card-header">
            <h3>Prochains événements</h3>
            <a href="evenements.php" class="btn btn-outline btn-sm">Voir tous →</a>
        </div>
        <div class="card-body">
            <div class="events-grid-modern">
                <?php
                $event_found = false;
                // On utilise la variable $all_events qui contient tous les événements à venir
                foreach ($all_events as $event):
                    $is_visible = false;
                    // L'événement est visible s'il est pour 'Tous' OU si l'utilisateur est membre du club
                    if ($event['TypeParticipant'] == 'Tous' || in_array($event['IdClub'], $mes_clubs_ids)) {
                        $is_visible = true;
                    }

                    if ($is_visible):
                        $event_found = true;
                ?>
                        <div class="event-card-modern">
                            <div class="event-image-modern">
                                <?php if (!empty($event['Affiche'])): ?>
                                    <img src="../uploads/affiches/<?php echo htmlspecialchars($event['Affiche']); ?>" alt="<?php echo htmlspecialchars($event['NomEvenement']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: linear-gradient(135deg, #ff6b6b, #4ecdc4); color: white; font-size: 2rem;">
                                        📅
                                    </div>
                                <?php endif; ?>
                                <div class="event-date-badge-modern"><?php echo date('d F Y', strtotime($event['Date'])); ?></div>
                            </div>
                            <div class="event-content-modern">
                                <div class="event-title-modern"><?php echo htmlspecialchars($event['NomEvenement']); ?></div>
                                
                                <?php if (!empty($event['Description'])): ?>
                                    <div class="event-description-modern" style="margin: 10px 0; color: #666; font-size: 0.9rem; line-height: 1.4;">
                                        <?php echo htmlspecialchars(mb_strimwidth($event['Description'], 0, 100, '...')); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-meta-modern">
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">🏛️</div>
                                        <span><?php echo htmlspecialchars($event['NomClub']); ?></span>
                                    </div>
                                    <?php if (!empty($event['Lieu'])): ?>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">📍</div>
                                        <span><?php echo htmlspecialchars($event['Lieu']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($event['HeureDebut'])): ?>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">🕐</div>
                                        <span><?php echo substr($event['HeureDebut'], 0, 5); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="event-actions-modern">
                                    <a href="inscription_evenement.php?id=<?php echo (int)$event['IdEvenement']; ?>" class="btn btn-primary btn-sm">Voir détails</a>
                                </div>
                            </div>
                        </div>
                <?php
                    endif;
                endforeach;
                if (!$event_found):
                ?>
                    <div class="text-center p-xl">
                        <div class="text-neutral-500 mb-md">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                            <h3>Aucun événement disponible pour vous</h3>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div> <!-- FIN DE LA GRILLE À DEUX COLONNES -->

<!-- SECTION PLEINE LARGEUR EN DESSOUS : DÉCOUVRIR LES CLUBS -->
<div class="card">
    <div class="card-header">
        <h3>Découvrir d'autres clubs</h3>
        <a href="clubs.php" class="btn btn-primary btn-sm">Voir la liste complète →</a>
    </div>
    <div class="card-body">
        <div class="clubs-grid-modern">
            <?php foreach ($clubs_a_decouvrir as $club): ?>
                <div class="club-card-modern">
                    <div class="club-logo-modern">
                        <?php if (!empty($club['Logo'])): ?>
                            <img src="../uploads/clubs/<?php echo htmlspecialchars($club['Logo']); ?>" alt="<?php echo htmlspecialchars($club['NomClub']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                            🏛️
                        <?php endif; ?>
                    </div>
                    <div class="club-name-modern"><?php echo htmlspecialchars($club['NomClub']); ?></div>
                    <div class="flex gap-sm">
                        <a href="club_detail.php?id=<?php echo (int)$club['IdClub']; ?>" class="btn btn-primary btn-sm">Voir les événements</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- === FIN DU BLOC FINAL À COLLER === -->
        
            </div>
        </div>
        </main>
    
    <!-- Fermer la div de contenu principal -->
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>