<?php
require_once '../config/database.php';
require_once '../config/session.php';
// Vérifier que c'est bien un admin de club
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
$eventCountValide = 0;
if ($club) {
    // Compter les événements actifs du club
    $event_query = "SELECT COUNT(*) as event_count FROM Evenement 
                    WHERE IdClub = :club_id AND (Etat = 'valide' ) AND Date >= CURDATE()";
    $stmt = $db->prepare($event_query);
    $stmt->bindParam(':club_id', $club['IdClub']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $eventCountValide = $result ? (int)$result['event_count'] : 0;
}
$eventCountRefuse = 0;
if ($club) {
    // Compter les événements refusés du club
    $event_query = "SELECT COUNT(*) as event_count FROM Evenement 
                    WHERE IdClub = :club_id AND Etat = 'refuse' AND Date >= CURDATE()";
    $stmt = $db->prepare($event_query);
    $stmt->bindParam(':club_id', $club['IdClub']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $eventCountRefuse = $result ? (int)$result['event_count'] : 0;
}
$membreClub = 0;
if ($club) {
    // Compter les membres inscrits aux événements du club
   $membre_query = "SELECT COUNT(DISTINCT a.IdParticipant) AS membre_count
                 FROM Adhesion a
                 WHERE a.IdClub = :club_id AND a.Status = 'actif'";
    $stmt = $db->prepare($membre_query);
    $stmt->bindParam(':club_id', $club['IdClub']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $membreClub = $result ? (int)$result['membre_count'] : 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Club - Event Manager</title>
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
                    <span><?php echo htmlspecialchars($club['NomClub']); ?></span>
                </div>
                <div class="user-section">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                        <div class="user-role">Administrateur du club</div>
                    </div>
                    <div class="user-avatar-modern">AO</div>
                    <button class="btn btn-ghost btn-sm" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="layout">
            <aside class="sidebar-modern">
                <nav class="sidebar-nav-modern">
                    <div class="sidebar-section-modern">
                        <div class="sidebar-title-modern">Gestion</div>
                        <ul class="sidebar-nav-modern">
                            <li class="sidebar-nav-item-modern">
                                <a href="dashboard.php" class="sidebar-nav-link-modern active">
                                    <div class="sidebar-nav-icon-modern">📊</div>
                                    Tableau de bord
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="gerer_event.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">📅</div>
                                    Mes événements
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="creer_event.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">➕</div>
                                    Créer événement
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="membres.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">👥</div>
                                    Membres
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="envoyer_email.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">📧</div>
                                    Communication
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="sidebar-section-modern">
                        <div class="sidebar-title-modern">Personnel</div>
                        <ul class="sidebar-nav-modern">
                            <li class="sidebar-nav-item-modern">
                                <a href="../utilisateur/mes_inscriptions.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">📋</div>
                                    Mes inscriptions
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="../utilisateur/clubs.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">🏛️</div>
                                    Autres clubs
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="parametres.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">⚙️</div>
                                    Paramètres
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </aside>

            <main class="main-content">
                <div class="page-title">
                    <h1>Tableau de bord</h1>
                    <p>Vue d'ensemble de l'activité du club</p>
                </div>

                <div class="stats-grid-modern">
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Événements validés</div>
                            <div class="stat-icon-modern coral">✅</div>
                        </div>
                        <div class="stat-value-modern"><?php echo htmlspecialchars($eventCountValide); ?></div>
                        <div class="stat-change-modern positive">+2 cette semaine</div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Événements refusés</div>
                            <div class="stat-icon-modern teal">❌</div>
                        </div>
                        <div class="stat-value-modern"><?php echo htmlspecialchars($eventCountRefuse); ?></div>
                        <div class="stat-change-modern">Aucun récent</div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Adhérents</div>
                            <div class="stat-icon-modern blue">👥</div>
                        </div>
                        <div class="stat-value-modern"><?php echo htmlspecialchars($membreClub); ?></div>
                        <div class="stat-change-modern positive">+18 cette semaine</div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Communications</div>
                            <div class="stat-icon-modern purple">📧</div>
                        </div>
                        <div class="stat-value-modern">52</div>
                        <div class="stat-change-modern positive">+5 cette semaine</div>
                    </div>
                </div>

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
                                <div class="action-title-modern">Gérer les membres</div>
                                <div class="action-description-modern">Administrez les adhérents</div>
                            </div>
                        </a>
                        <a href="envoyer_email.php" class="action-card-modern">
                            <div class="action-icon-modern">📧</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Envoyer une communication</div>
                                <div class="action-description-modern">Communiquez avec les membres</div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="table-modern">
                    <div class="table-header-modern">
                        <h2 class="table-title-modern">Événements à venir</h2>
                        <a href="gerer_event.php" class="btn btn-outline btn-sm">Voir tout →</a>
                    </div>
                    <div class="events-grid-modern">
                        <div class="event-card-modern">
                            <div class="event-image-modern">
                                <div class="event-date-badge-modern">15 Oct 2025</div>
                            </div>
                            <div class="event-content-modern">
                                <div class="event-title-modern">Hackathon 2025</div>
                                <div class="event-meta-modern">
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">📍</div>
                                        <span>Amphithéâtre</span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">⏰</div>
                                        <span>09:00 - 18:00</span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">👥</div>
                                        <span>45/50 inscrits</span>
                                    </div>
                                </div>
                                <div class="event-price-modern">
                                    <div class="badge badge-warning">Bientôt complet</div>
                                </div>
                                <div class="event-actions-modern">
                                    <a href="modifier_event.php?id=1" class="btn btn-outline btn-sm">Modifier</a>
                                    <a href="participants.php?id=1" class="btn btn-outline btn-sm">Participants</a>
                                    <a href="envoyer_rappel.php?id=1" class="btn btn-primary btn-sm">Envoyer rappel</a>
                                </div>
                            </div>
                        </div>

                        <div class="event-card-modern">
                            <div class="event-image-modern">
                                <div class="event-date-badge-modern">22 Oct 2025</div>
                            </div>
                            <div class="event-content-modern">
                                <div class="event-title-modern">Conférence Intelligence Artificielle</div>
                                <div class="event-meta-modern">
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">📍</div>
                                        <span>Salle de conférence</span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">⏰</div>
                                        <span>14:00 - 17:00</span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">👥</div>
                                        <span>28/40 inscrits</span>
                                    </div>
                                </div>
                                <div class="event-price-modern">
                                    <div class="badge badge-success">Places disponibles</div>
                                </div>
                                <div class="event-actions-modern">
                                    <a href="modifier_event.php?id=2" class="btn btn-outline btn-sm">Modifier</a>
                                    <a href="participants.php?id=2" class="btn btn-outline btn-sm">Participants</a>
                                    <a href="envoyer_rappel.php?id=2" class="btn btn-primary btn-sm">Envoyer rappel</a>
                                </div>
                            </div>
                        </div>

                        <div class="event-card-modern">
                            <div class="event-image-modern">
                                <div class="event-date-badge-modern">05 Nov 2025</div>
                            </div>
                            <div class="event-content-modern">
                                <div class="event-title-modern">Formation Git & GitHub</div>
                                <div class="event-meta-modern">
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">📍</div>
                                        <span>Salle 3</span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">⏰</div>
                                        <span>10:00 - 13:00</span>
                                    </div>
                                    <div class="event-meta-item-modern">
                                        <div class="event-meta-icon-modern">👥</div>
                                        <span>15/25 inscrits</span>
                                    </div>
                                </div>
                                <div class="event-price-modern">
                                    <div class="badge badge-success">Places disponibles</div>
                                </div>
                                <div class="event-actions-modern">
                                    <a href="modifier_event.php?id=3" class="btn btn-outline btn-sm">Modifier</a>
                                    <a href="participants.php?id=3" class="btn btn-outline btn-sm">Participants</a>
                                    <a href="envoyer_rappel.php?id=3" class="btn btn-primary btn-sm">Envoyer rappel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>