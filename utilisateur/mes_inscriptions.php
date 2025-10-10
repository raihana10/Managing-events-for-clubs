<?php
// utilisateur/mes_inscriptions.php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
requireRole(['participant']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Gérer la désinscription
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'desinscrire') {
    if (isset($_POST['inscription_id']) && !empty($_POST['inscription_id'])) {
        $id_inscription = (int)$_POST['inscription_id'];
        
        // S'assurer que l'inscription appartient bien à l'utilisateur connecté pour la sécurité
        $query_delete = "DELETE FROM Inscription WHERE IdInscription = :id_inscription AND IdUtilisateur = :id_utilisateur";
        $stmt_delete = $db->prepare($query_delete);
        $stmt_delete->bindParam(':id_inscription', $id_inscription);
        $stmt_delete->bindParam(':id_utilisateur', $user_id);
        
        if ($stmt_delete->execute() && $stmt_delete->rowCount() > 0) {
            $_SESSION['message'] = "Vous avez été désinscrit de l'événement avec succès.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Erreur lors de la désinscription ou inscription introuvable.";
            $_SESSION['message_type'] = "error";
        }
        // Rediriger pour éviter la resoumission du formulaire
        header("Location: mes_inscriptions.php");
        exit();
    }
}

// Récupérer la liste des inscriptions de l'utilisateur
$query = "SELECT i.IdInscription, e.IdEvenement, e.NomEvenement, e.Date, e.HeureDebut, e.Lieu, c.NomClub 
          FROM Inscription i
          JOIN Evenement e ON i.IdEvenement = e.IdEvenement
          JOIN Club c ON e.IdClub = c.IdClub
          WHERE i.IdUtilisateur = :id_utilisateur AND e.Date >= CURDATE()
          ORDER BY e.Date ASC, e.HeureDebut ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_utilisateur', $user_id);
$stmt->execute();
$inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gérer les messages de session
if (isset($_SESSION['message'])) {
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
    <title>Mes Inscriptions - GestionEvents</title>
    <!-- Votre CSS sera inclus ici -->
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
        <h1>Mes Inscriptions</h1>
        <p>Voici la liste des événements à venir auxquels vous êtes inscrit.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="padding: 10px; margin-bottom: 15px; border-radius: 5px; <?php echo $message_type === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
            <?php if (!empty($inscriptions)): ?>
                <?php foreach ($inscriptions as $inscription): ?>
                <div style="background-color: white; border: 1px solid #eee; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="background-color: #e6f7ff; color: #007bff; padding: 5px 10px; border-radius: 5px; font-size: 0.9em; font-weight: bold;"><?php echo date('d F Y', strtotime($inscription['Date'])); ?></span>
                        <h3 style="font-size: 1.1em; margin-top: 10px; margin-bottom: 5px;"><?php echo htmlspecialchars($inscription['NomEvenement']); ?></h3>
                        <p style="font-size: 0.9em; color: #666;">
                            Organisé par : <?php echo htmlspecialchars($inscription['NomClub']); ?> &nbsp;|&nbsp; 
                            Lieu : <?php echo htmlspecialchars($inscription['Lieu']); ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="inscription_evenement.php?id=<?php echo (int)$inscription['IdEvenement']; ?>" style="background-color: #17a2b8; color: white; padding: 8px 12px; border-radius: 5px; text-decoration: none;">Voir Détails</a>
                        <form method="POST" action="mes_inscriptions.php" onsubmit="return confirm('Êtes-vous sûr de vouloir vous désinscrire de cet événement ?');">
                            <input type="hidden" name="action" value="desinscrire">
                            <input type="hidden" name="inscription_id" value="<?php echo (int)$inscription['IdInscription']; ?>">
                            <button type="submit" style="background-color: #dc3545; color: white; padding: 8px 12px; border-radius: 5px; border: none; cursor: pointer;">Se désinscrire</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #888; padding: 40px; background-color: #f8f9fa; border-radius: 8px;">Vous n'êtes inscrit à aucun événement à venir. <a href="evenements.php">Découvrez les événements disponibles</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>