<?php
// utilisateur/mes_inscriptions.php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
requireRole(['participant']);

// --- DÉFINIR LA PAGE ACTUELLE POUR LE HEADER ---
$currentPage = 'mes-inscriptions';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Logique pour la désinscription (déjà correcte)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'desinscrire') {
    if (isset($_POST['inscription_id']) && !empty($_POST['inscription_id'])) {
        $id_inscription = (int)$_POST['inscription_id'];
        $query_delete = "DELETE FROM Inscription WHERE IdInscription = :id_inscription AND IdUtilisateur = :id_utilisateur";
        $stmt_delete = $db->prepare($query_delete);
        $stmt_delete->bindParam(':id_inscription', $id_inscription);
        $stmt_delete->bindParam(':id_utilisateur', $user_id);
        
        if ($stmt_delete->execute() && $stmt_delete->rowCount() > 0) {
            $_SESSION['message'] = "Vous avez été désinscrit avec succès.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Erreur lors de la désinscription.";
            $_SESSION['message_type'] = "error";
        }
        header("Location: mes_inscriptions.php");
        exit();
    }
}

// Récupérer la liste des inscriptions
$query = "SELECT i.IdInscription, e.IdEvenement, e.NomEvenement, e.Date, e.Lieu, c.NomClub 
          FROM Inscription i
          JOIN Evenement e ON i.IdEvenement = e.IdEvenement
          JOIN Club c ON e.IdClub = c.IdClub
          WHERE i.IdUtilisateur = :id_utilisateur AND e.Date >= CURDATE()
          ORDER BY e.Date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_utilisateur', $user_id);
$stmt->execute();
$inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gérer les messages
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
    <title>Mes Inscriptions - Event Manager</title>
    <!-- LIENS VERS VOS FICHIERS CSS MODERNES -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_sidebar.php'; ?>
    <?php include '_navbar.php'; ?>
    
    <!-- Contenu principal avec padding pour éviter la sidebar -->
    <div style="padding: 20px;">
        <div class="page-title">
            <h1>Mes Inscriptions</h1>
            <p>Voici la liste des événements à venir auxquels vous êtes inscrit.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-modern <?php echo $message_type === 'success' ? 'alert-success-modern' : 'alert-error-modern'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="events-list-container">
            <?php if (!empty($inscriptions)): ?>
                <?php foreach ($inscriptions as $inscription): ?>
                <div class="inscription-card-modern">
                    <div class="inscription-card-date"><?php echo date('d M', strtotime($inscription['Date'])); ?></div>
                    <div class="inscription-card-content">
                        <div class="inscription-card-title"><?php echo htmlspecialchars($inscription['NomEvenement']); ?></div>
                        <div class="inscription-card-meta">
                            <span>Organisé par : <?php echo htmlspecialchars($inscription['NomClub']); ?></span>
                            <span>Lieu : <?php echo htmlspecialchars($inscription['Lieu']); ?></span>
                        </div>
                    </div>
                    <div class="inscription-card-actions">
                        <a href="inscription_evenement.php?id=<?php echo (int)$inscription['IdEvenement']; ?>" class="btn btn-primary btn-sm">Voir Détails</a>
                        <form method="POST" action="mes_inscriptions.php" onsubmit="return confirm('Êtes-vous sûr de vouloir vous désinscrire ?');">
                            <input type="hidden" name="action" value="desinscrire">
                            <input type="hidden" name="inscription_id" value="<?php echo (int)$inscription['IdInscription']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm">Se désinscrire</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-state-icon-modern">📋</div>
                    <h3>Aucune inscription à venir</h3>
                    <p>Vous n'êtes inscrit à aucun événement pour le moment. <a href="evenements.php">Découvrez les événements disponibles !</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Fermer la div de contenu principal -->
    </div>

</body>
</html>