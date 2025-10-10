<?php
// utilisateur/inscription_evenement.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
requireRole(['participant']);

$database = new Database();
$db = $database->getConnection();

$event = null;
$already_registered = false;
$message = '';
$message_type = '';

$user_id = $_SESSION['user_id'];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_evenement = (int)$_GET['id'];

    // Récupérer les détails de l'événement
    $query_event = "SELECT e.*, c.NomClub, c.Description as ClubDescription, c.Logo as ClubLogo 
                    FROM Evenement e 
                    JOIN Club c ON e.IdClub = c.IdClub 
                    WHERE e.IdEvenement = :id_evenement AND e.Etat = 'valide' LIMIT 1";
    $stmt_event = $db->prepare($query_event);
    $stmt_event->bindParam(':id_evenement', $id_evenement);
    $stmt_event->execute();
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);

    if ($event) {
        // Déterminer le prix selon le type d'utilisateur
        $query_membership = "SELECT COUNT(*) FROM Adhesion WHERE IdParticipant = :user_id AND IdClub = :club_id AND Status = 'actif'";
        $stmt_membership = $db->prepare($query_membership);
        $stmt_membership->bindParam(':user_id', $user_id);
        $stmt_membership->bindParam(':club_id', $event['IdClub']);
        $stmt_membership->execute();
        $is_member = $stmt_membership->fetchColumn() > 0;
        
        if ($is_member) {
            $event_price = $event['PrixAdherent'];
            $user_type = "Adhérent";
        } else {
            $event_price = $event['PrixNonAdherent'];
            $user_type = "Non-adhérent";
        }
        
        // Vérifier si l'utilisateur est déjà inscrit à cet événement
        $query_check_inscription = "SELECT IdInscription FROM Inscription 
                                    WHERE IdUtilisateur = :id_utilisateur AND IdEvenement = :id_evenement LIMIT 1";
        $stmt_check_inscription = $db->prepare($query_check_inscription);
        $stmt_check_inscription->bindParam(':id_utilisateur', $user_id);
        $stmt_check_inscription->bindParam(':id_evenement', $id_evenement);
        $stmt_check_inscription->execute();
        if ($stmt_check_inscription->rowCount() > 0) {
            $already_registered = true;
            $message = "Vous êtes déjà inscrit à cet événement.";
            $message_type = "info";
        }

        // Gérer l'inscription
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'inscrire') {
            if (!$already_registered) {
                // Vérifier la capacité maximale si elle est définie
                if (!empty($event['CapaciteMax'])) {
                    $query_count_participants = "SELECT COUNT(IdInscription) as count FROM Inscription WHERE IdEvenement = :id_evenement";
                    $stmt_count = $db->prepare($query_count_participants);
                    $stmt_count->bindParam(':id_evenement', $id_evenement);
                    $stmt_count->execute();
                    $current_participants = $stmt_count->fetchColumn();

                    if ($current_participants >= $event['CapaciteMax']) {
                        $message = "Désolé, la capacité maximale pour cet événement est atteinte.";
                        $message_type = "error";
                    }
                }

                if (empty($message)) { // Si aucune erreur de capacité
                    try {
                        $db->beginTransaction();

                        $query_inscription = "INSERT INTO Inscription (IdUtilisateur, IdEvenement, DateInscription) 
                                              VALUES (:id_utilisateur, :id_evenement, NOW())"; 
                        $stmt_inscription = $db->prepare($query_inscription);
                        $stmt_inscription->bindParam(':id_utilisateur', $user_id);
                        $stmt_inscription->bindParam(':id_evenement', $id_evenement);
                        
                        if ($stmt_inscription->execute()) {
                            $db->commit();
                            $_SESSION['message'] = "Inscription à l'événement '{$event['NomEvenement']}' réussie !";
                            $_SESSION['message_type'] = "success";
                            header("Location: mes_inscriptions.php"); // Rediriger vers mes inscriptions
                            exit();
                        } else {
                            $db->rollBack();
                            $message = "Erreur lors de l'inscription. Veuillez réessayer.";
                            $message_type = "error";
                        }
                    } catch (PDOException $e) {
                        $db->rollBack();
                        if ($e->getCode() == 23000) { // Erreur de duplicata (UNIQUE constraint)
                            $message = "Vous êtes déjà inscrit à cet événement.";
                            $message_type = "info";
                            $already_registered = true; // Mettre à jour l'état
                        } else {
                            $message = "Une erreur de base de données est survenue : " . $e->getMessage();
                            $message_type = "error";
                        }
                    }
                }
            }
        }
    } else {
        $_SESSION['message'] = "Événement introuvable ou non valide.";
        $_SESSION['message_type'] = "error";
        header("Location: evenements.php");
        exit();
    }
} else {
    $_SESSION['message'] = "ID d'événement manquant.";
    $_SESSION['message_type'] = "error";
    header("Location: evenements.php");
    exit();
}

// Gérer les messages de session (pour les redirections)
if (isset($_SESSION['message']) && empty($message)) { // Si un message de session existe et qu'il n'y a pas déjà un message local
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
    <title>Détails de l'événement - <?php echo htmlspecialchars($event['NomEvenement'] ?? 'Événement Inconnu'); ?></title>
    <!-- Votre CSS sera inclus ici -->
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="padding: 10px; margin-bottom: 15px; border-radius: 5px; <?php echo $message_type === 'success' ? 'background-color: #d4edda; color: #155724;' : ($message_type === 'info' ? 'background-color: #cfe2ff; color: #055160;' : 'background-color: #f8d7da; color: #721c24;'); ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($event): ?>
            <div style="background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;">
                <h1 style="font-size: 2.2em; margin-bottom: 10px;"><?php echo htmlspecialchars($event['NomEvenement']); ?></h1>
                <p style="color: #666; margin-bottom: 15px;">Organisé par : <a href="club_detail.php?id=<?php echo (int)$event['IdClub']; ?>" style="color: #007bff; text-decoration: none;"><?php echo htmlspecialchars($event['NomClub']); ?></a></p>
                
                <?php 
                $affiche_path = !empty($event['Affiche']) && file_exists('../assets/images/evenements/' . $event['Affiche']) 
                              ? '../assets/images/evenements/' . $event['Affiche'] 
                              : 'https://via.placeholder.com/600x400?text=Affiche+Evenement';
                ?>
                <img src="<?php echo htmlspecialchars($affiche_path); ?>" alt="Affiche de l'événement" style="max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 20px;">

                <p style="font-size: 1.1em; color: #333; margin-bottom: 20px;"><?php echo nl2br(htmlspecialchars($event['Description'] ?? 'Aucune description disponible pour cet événement.')); ?></p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.95em; color: #555;">
                    <p><strong>Date :</strong> <?php echo date('d F Y', strtotime($event['Date'])); ?></p>
                    <p><strong>Heure :</strong> <?php echo htmlspecialchars(substr($event['HeureDebut'], 0, 5) . ' - ' . substr($event['HeureFin'], 0, 5)); ?></p>
                    <p><strong>Lieu :</strong> <?php echo htmlspecialchars($event['Lieu']); ?></p>
                    <p><strong>Type de participant :</strong> <?php echo htmlspecialchars($event['TypeParticipant']); ?></p>
                    <p><strong>Prix (<?php echo $user_type; ?>) :</strong> 
                        <?php if ($event_price == 0 || $event_price === null): ?>
                            <span style="color: #28a745; font-weight: bold;">Gratuit</span>
                        <?php else: ?>
                            <span style="color: #007bff; font-weight: bold;"><?php echo number_format(floatval($event_price), 2); ?> €</span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($event['CapaciteMax'])): ?>
                        <?php
                            $query_count_participants = "SELECT COUNT(IdInscription) as count FROM Inscription WHERE IdEvenement = :id_evenement";
                            $stmt_count = $db->prepare($query_count_participants);
                            $stmt_count->bindParam(':id_evenement', $id_evenement);
                            $stmt_count->execute();
                            $current_participants = $stmt_count->fetchColumn();
                            $places_restantes = $event['CapaciteMax'] - $current_participants;
                        ?>
                    <p><strong>Capacité :</strong> <?php echo (int)$event['CapaciteMax']; ?> (<?php echo $places_restantes > 0 ? $places_restantes : '0'; ?> places restantes)</p>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center;">
                   <form method="POST" action="inscription_evenement.php?id=<?php echo (int)$id_evenement; ?>">
    <input type="hidden" name="action" value="inscrire">
    <button type="submit">S'inscrire à cet événement</button>
</form>
<p>Info: Déjà inscrit ? <?php echo $already_registered ? 'Oui' : 'Non'; ?></p>
<p>Info: Places restantes: <?php echo isset($places_restantes) ? $places_restantes : 'N/A'; ?></p> 
                </div>

            </div>
        <?php else: ?>
             <div style="text-align: center; padding: 50px; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h3 style="color: #dc3545;">Événement introuvable.</h3>
                <p style="color: #6c757d; margin-top: 10px;">L'événement que vous recherchez n'existe pas ou n'est plus disponible.</p>
                <a href="evenements.php" style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin-top: 20px; display: inline-block;">Retourner aux événements</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>