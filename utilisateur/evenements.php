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
    <title>Tous les événements - GestionEvents</title>
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
        <h1>Tous les événements disponibles</h1>
        
        <div style="margin-top: 20px;">
            <?php 
            $event_found = false;
            foreach ($all_events as $event): 
                $is_event_visible = false;

                // 3. Logique de filtrage en PHP
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
                <div style="background-color: white; border: 1px solid #eee; padding: 15px; margin-bottom: 10px;">
                    <h3><?php echo htmlspecialchars($event['NomEvenement']); ?> (Club: <?php echo htmlspecialchars($event['NomClub']); ?>)</h3>
                     <p>
                        <?php if ($event['TypeParticipant'] == 'Adhérents'): ?>
                            <span style="background-color: #ffc107; color: black; padding: 3px 8px; border-radius: 10px; font-size: 0.8em;">Réservé aux adhérents</span>
                        <?php elseif ($event['TypeParticipant'] == 'Tous les étudiants'): ?>
                            <span style="background-color: #cfe2ff; color: #055160; padding: 3px 8px; border-radius: 10px; font-size: 0.8em;">Ouvert à tous les étudiants</span>
                        <?php else: ?>
                            <span style="background-color: #cfe2ff; color: #055160; padding: 3px 8px; border-radius: 10px; font-size: 0.8em;">Ouvert à tous</span>
                        <?php endif; ?>
                    </p>
                    
                    <!-- Affichage des prix selon le type d'utilisateur -->
                    <div style="margin: 10px 0; font-size: 0.9em;">
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
                        <strong>Prix (<?php echo $user_type; ?>):</strong>
                        <?php if ($price == 0 || $price === null): ?>
                            <span style="color: #28a745; font-weight: bold;">Gratuit</span>
                        <?php else: ?>
                            <span style="color: #007bff; font-weight: bold;"><?php echo number_format(floatval($price), 2); ?> €</span>
                        <?php endif; ?>
                    </div>
                    
                    <a href="inscription_evenement.php?id=<?php echo (int)$event['IdEvenement']; ?>">Voir détails</a>
                </div>
            <?php 
                endif; // Fin de la condition d'affichage
            endforeach; 

            if (!$event_found):
            ?>
                <p>Aucun événement disponible pour vous pour le moment.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>