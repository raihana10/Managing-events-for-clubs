<?php
// utilisateur/evenements.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
requireRole(['participant']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// 1. Récupérer la liste des ID de clubs dont l'utilisateur est membre
$query_memberships = "SELECT IdClub FROM Adhesion WHERE IdParticipant = :user_id AND Status = 'actif'";
$stmt_memberships = $db->prepare($query_memberships);
$stmt_memberships->bindParam(':user_id', $user_id);
$stmt_memberships->execute();
// fetchAll(PDO::FETCH_COLUMN) crée un simple tableau d'IDs : [1, 5, 12]
$user_memberships = $stmt_memberships->fetchAll(PDO::FETCH_COLUMN); 

// 2. Récupérer tous les événements à venir (publics et privés)
$query = "SELECT e.*, c.NomClub FROM Evenement e JOIN Club c ON e.IdClub = c.IdClub WHERE e.Date >= CURDATE() AND e.Etat = 'valide' ORDER BY e.Date ASC, e.HeureDebut ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tous les événements - Event Manager</title>
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
                <a href="clubs.php" class="nav-link-modern">Clubs</a>
                <a href="evenements.php" class="nav-link-modern active">Événements</a>
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
            <h1>Tous les événements disponibles</h1>
            <p>Découvrez tous les événements auxquels vous pouvez participer</p>
        </div>
        
        <div class="events-grid-modern">
            <?php 
            $event_found = false;
            foreach ($all_events as $event): 
                $is_event_visible = false;

                // Logique de filtrage en PHP
                if ($event['TypeParticipant'] == 'Tous' || $event['TypeParticipant'] == 'Tous les étudiants') {
                    $is_event_visible = true;
                } elseif ($event['TypeParticipant'] == 'Adhérents' && in_array($event['IdClub'], $user_memberships)) {
                    // L'événement est pour les membres, et l'utilisateur est membre de ce club
                    $is_event_visible = true;
                }
                
                // Si l'événement est visible pour cet utilisateur, on l'affiche
                if ($is_event_visible):
                    $event_found = true;
            ?>
                <div class="event-card-modern">
                    <div class="event-image-modern">
                        <div class="event-date-badge-modern">
                            <?php echo date('d M Y', strtotime($event['Date'])); ?>
                        </div>
                    </div>
                    
                    <div class="event-content-modern">
                        <div class="event-title-modern"><?php echo htmlspecialchars($event['NomEvenement']); ?></div>
                        
                        <div class="event-meta-modern">
                            <div class="event-meta-item-modern">
                                <div class="event-meta-icon-modern">🏛️</div>
                                <span><?php echo htmlspecialchars($event['NomClub']); ?></span>
                            </div>
                            <div class="event-meta-item-modern">
                                <div class="event-meta-icon-modern">🕒</div>
                                <span><?php echo date('H:i', strtotime($event['HeureDebut'])); ?></span>
                            </div>
                            <div class="event-meta-item-modern">
                                <div class="event-meta-icon-modern">📍</div>
                                <span><?php echo htmlspecialchars($event['Lieu'] ?? 'Lieu à confirmer'); ?></span>
                            </div>
                        </div>
                        
                        <div class="event-type-modern">
                            <?php if ($event['TypeParticipant'] == 'Adhérents'): ?>
                                <div class="badge badge-warning">Réservé aux adhérents</div>
                            <?php elseif ($event['TypeParticipant'] == 'Tous les étudiants'): ?>
                                <div class="badge badge-info">Ouvert à tous les étudiants</div>
                            <?php else: ?>
                                <div class="badge badge-info">Ouvert à tous</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-price-modern">
                            <?php 
                            $is_member = in_array($event['IdClub'], $user_memberships);
                            if ($is_member): 
                                // Utilisateur est membre du club
                                $price = $event['PrixAdherent'];
                                $user_type = "Adhérent";
                            else:
                                // Utilisateur n'est pas membre du club
                                $price = $event['PrixNonAdherent'];
                                $user_type = "Non-adhérent";
                            endif;
                            ?>
                            <div class="price-modern">
                                <?php if ($price == 0 || $price === null): ?>
                                    <span class="price-free-modern">Gratuit</span>
                                <?php else: ?>
                                    <?php echo number_format(floatval($price), 2); ?> €
                                <?php endif; ?>
                            </div>
                            <div class="badge badge-info"><?php echo $user_type; ?></div>
                        </div>
                        
                        <div class="event-actions-modern">
                            <a href="inscription_evenement.php?id=<?php echo (int)$event['IdEvenement']; ?>" class="btn btn-primary btn-sm">Voir détails</a>
                        </div>
                    </div>
                </div>
            <?php 
                endif; // Fin de la condition d'affichage
            endforeach; 

            if (!$event_found):
            ?>
                <div class="empty-state-modern">
                    <div class="empty-state-icon-modern">📅</div>
                    <h3>Aucun événement disponible</h3>
                    <p>Aucun événement disponible pour vous pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>