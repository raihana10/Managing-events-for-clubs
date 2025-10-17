<?php
// utilisateur/inscription_evenement.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '../config/database.php';
require_once '../config/session.php';

$currentPage = 'inscription_evenement.php';
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

        $places_restantes = null; // On initialise la variable

        // On ne fait le calcul que si une capacité maximale est définie pour l'événement
        if (!empty($event['CapaciteMax'])) {
            // 1. On compte combien de personnes sont déjà inscrites
            $query_count_participants = "SELECT COUNT(IdInscription) FROM Inscription WHERE IdEvenement = :id_evenement";
            $stmt_count = $db->prepare($query_count_participants);
            $stmt_count->bindParam(':id_evenement', $id_evenement);
            $stmt_count->execute();
            $current_participants = $stmt_count->fetchColumn();

            // 2. On calcule les places restantes
            $places_restantes = $event['CapaciteMax'] - $current_participants;
        }
        // --- FIN DU BLOC À AJOUTER ---

        // Vérifier si l'utilisateur est déjà inscrit à cet événement
        $query_check_inscription = "SELECT IdInscription FROM Inscription ...";


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
    <title>Détails de l'événement - <?php echo htmlspecialchars($event['NomEvenement'] ?? 'Événement'); ?></title>
    <!-- LIENS VERS VOS FICHIERS CSS MODERNES -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_sidebar.php'; ?>
    
    <!-- Contenu principal avec padding pour éviter la sidebar -->
    <div style="padding: 20px;">
        <?php if ($message): ?>
            <div class="alert-modern alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>-modern">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($event): ?>
            <div class="card">
                <div class="card-body">
                    <div class="page-title" style="text-align: left; margin-bottom: 2rem;">
                        <h1><?php echo htmlspecialchars($event['NomEvenement']); ?></h1>
                        <p>Organisé par : <a href="club_detail.php?id=<?php echo (int)$event['IdClub']; ?>"><?php echo htmlspecialchars($event['NomClub']); ?></a></p>
                    </div>

                    <?php 
                    $upload_directory = '../uploads/affiches/';
                    if (!empty($event['Affiche']) && file_exists($upload_directory . $event['Affiche'])) {
                        $affiche_path = $upload_directory . htmlspecialchars($event['Affiche']);
                    } else {
                        $affiche_path = 'https://via.placeholder.com/800x400/ff6b6b/ffffff?text=Affiche+de+l\'Evenement';
                    }
                    ?>
                    
                    <?php if (!empty($event['Affiche'])): ?>
                        <div style="margin-bottom: 2rem;">
                            <img src="<?php echo $affiche_path; ?>" alt="Affiche de l'événement" style="width: 100%; max-width: 600px; height: auto; border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md);">
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 2rem; text-align: center; padding: 2rem; background: var(--neutral-100); border-radius: var(--border-radius-lg); border: 2px dashed var(--neutral-300);">
                            <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--neutral-400);">📅</div>
                            <p style="color: var(--neutral-500); margin: 0;">Aucune affiche disponible pour cet événement</p>
                        </div>
                    <?php endif; ?>

                    <div style="background: var(--neutral-50); padding: 1.5rem; border-radius: var(--border-radius-lg); margin-bottom: 2rem; border-left: 4px solid var(--primary-coral);">
                        <h3 style="margin: 0 0 1rem 0; color: var(--neutral-800); font-size: 1.2rem;">Description de l'événement</h3>
                        <?php if (!empty($event['description'])): ?>
                            <p style="font-size: 1.1em; line-height: 1.6; margin: 0; color: var(--neutral-700);"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        <?php else: ?>
                            <p style="font-size: 1.1em; color: var(--neutral-500); margin: 0; font-style: italic;">Aucune description disponible pour cet événement.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-lg" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--neutral-200);">
                        <p><strong>Date :</strong> <?php echo date('d F Y', strtotime($event['Date'])); ?></p>
                        <p><strong>Heure :</strong> <?php echo htmlspecialchars(substr($event['HeureDebut'], 0, 5) . ' - ' . substr($event['HeureFin'], 0, 5)); ?></p>
                        <p><strong>Lieu :</strong> <?php echo htmlspecialchars($event['Lieu']); ?></p>
                         <?php if ($places_restantes !== null): ?>
        <p>
            <strong>Capacité :</strong> <?php echo (int)$event['CapaciteMax']; ?>
            (<strong><?php echo max(0, $places_restantes); ?></strong> places restantes)
        </p>
    <?php endif; ?>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <?php if ($already_registered): ?>
                            <button class="btn btn-secondary" disabled>Vous êtes déjà inscrit</button>
                        <?php else: ?>
                            <form method="POST" action="inscription_evenement.php?id=<?php echo (int)$id_evenement; ?>">
                                <input type="hidden" name="action" value="inscrire">
                                <button type="submit" class="btn btn-primary btn-lg">S'inscrire à cet événement</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-modern">
                <h3>Événement introuvable</h3>
                <p>L'événement que vous cherchez n'existe pas ou n'est plus disponible.</p>
                <a href="evenements.php" class="btn btn-primary">Retour aux événements</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Fermer la div de contenu principal -->
    </div>
</body>
</html>