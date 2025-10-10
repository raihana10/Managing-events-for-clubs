<?php
// utilisateur/clubs.php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin(); // Redirige si l'utilisateur n'est pas connecté
requireRole(['participant']); // S'assure que seul un participant peut accéder

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Récupérer la liste de tous les clubs
$query = "SELECT c.IdClub, c.NomClub, c.Description, c.Logo, u.Nom as AdminNom, u.Prenom as AdminPrenom 
          FROM Club c 
          JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur 
          ORDER BY c.NomClub ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Clubs - GestionEvents</title>
    <!-- Votre CSS sera inclus ici -->
</head>
<body>
    <?php include '_navbar.php'; // Inclure la barre de navigation ?>

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
        <h1>Nos Clubs</h1>
        <p>Découvrez tous les clubs disponibles sur la plateforme.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="padding: 10px; margin-bottom: 15px; border-radius: 5px; <?php echo $message_type === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php if (!empty($clubs)): ?>
                <?php foreach ($clubs as $club): ?>
                <div style="background-color: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center;">
                    <?php 
                    $logo_path = !empty($club['Logo']) && file_exists('../assets/images/clubs/' . $club['Logo']) 
                               ? '../assets/images/clubs/' . $club['Logo'] 
                               : 'https://via.placeholder.com/100?text=Club'; // Placeholder si pas de logo
                    ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo <?php echo htmlspecialchars($club['NomClub']); ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 15px;">
                    
                    <h2 style="font-size: 1.2em; margin-bottom: 10px;"><?php echo htmlspecialchars($club['NomClub']); ?></h2>
                    <p style="font-size: 0.9em; color: #666; margin-bottom: 15px;">
                        <?php echo htmlspecialchars(mb_strimwidth($club['Description'] ?? 'Aucune description fournie.', 0, 100, '...')); ?>
                    </p>
                    <p style="font-size: 0.8em; color: #888; margin-bottom: 15px;">Admin: <?php echo htmlspecialchars($club['AdminPrenom'] . ' ' . $club['AdminNom']); ?></p>
                    <a href="club_detail.php?id=<?php echo (int)$club['IdClub']; ?>" style="background-color: #007bff; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none;">Voir les événements</a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="grid-column: 1 / -1; text-align: center; color: #888;">Aucun club disponible pour le moment.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>