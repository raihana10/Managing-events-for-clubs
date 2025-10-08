<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'utilisateur') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: clubs.php");
    exit();
}

$club_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les infos du club
$query = "SELECT c.*, u.Nom as AdminNom, u.Prenom as AdminPrenom, u.Email as AdminEmail,
          (SELECT COUNT(*) FROM Adhesion a WHERE a.IdClub = c.IdClub AND a.Statut = 'actif') as nb_membres
          FROM Club c 
          JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur 
          WHERE c.IdClub = ? AND c.Statut = 'actif'";
$stmt = $db->prepare($query);
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: clubs.php");
    exit();
}

// Récupérer les événements du club
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM Inscription i WHERE i.IdEvenement = e.IdEvenement) as nb_inscrits
          FROM Evenement e 
          WHERE e.IdClub = ? AND e.Date >= CURDATE() AND e.Etat = 'valide'
          ORDER BY e.Date, e.HeureDebut";
$stmt = $db->prepare($query);
$stmt->execute([$club_id]);
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier si l'utilisateur est déjà membre
$query = "SELECT * FROM Adhesion 
          WHERE IdUtilisateur = ? AND IdClub = ? AND Statut = 'actif'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $club_id]);
$is_member = $stmt->rowCount() > 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $club['NomClub']; ?> - EventManager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>EventManager</h1>
            <nav class="nav">
                <a href="dashboard.php">Accueil</a>
                <a href="clubs.php">Clubs</a>
                <a href="evenements.php">Événements</a>
                <a href="mes_inscriptions.php">Mes Inscriptions</a>
            </nav>
            <div class="user-info">
                <span>Bonjour, <?php echo $_SESSION['user_prenom']; ?></span>
                <div class="dropdown">
                    <button class="dropbtn">Mon compte ▼</button>
                    <div class="dropdown-content">
                        <a href="parametres.php">Paramètres</a>
                        <a href="../auth/logout.php">Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <!-- En-tête du club -->
            <div class="club-header">
                <div class="club-info">
                    <?php if (!empty($club['Logo'])): ?>
                        <img src="../uploads/<?php echo $club['Logo']; ?>" alt="<?php echo $club['NomClub']; ?>" class="club-logo-large">
                    <?php else: ?>
                        <div class="club-logo-large placeholder"><?php echo substr($club['NomClub'], 0, 2); ?></div>
                    <?php endif; ?>
                    <div class="club-details">
                        <h1><?php echo $club['NomClub']; ?></h1>
                        <p class="club-description"><?php echo $club['Description']; ?></p>
                        <div class="club-stats">
                            <span class="stat">👥 <?php echo $club['nb_membres']; ?> membres</span>
                            <span class="stat">📅 Créé le <?php echo date('d/m/Y', strtotime($club['DateCreation'])); ?></span>
                        </div>
                        <div class="admin-info">
                            <strong>Administrateur:</strong> <?php echo $club['AdminPrenom'] . ' ' . $club['AdminNom']; ?>
                        </div>
                    </div>
                </div>
                <div class="club-actions">
                    <?php if ($is_member): ?>
                        <span class="badge member-badge">✅ Membre</span>
                    <?php else: ?>
                        <button class="btn btn-primary" onclick="rejoindreClub(<?php echo $club_id; ?>)">Rejoindre le club</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Événements du club -->
            <section class="section">
                <h2>Événements à Venir</h2>
                <?php if (!empty($evenements)): ?>
                    <div class="grid">
                        <?php foreach ($evenements as $event): ?>
                        <div class="card event-card">
                            <div class="event-date">
                                <span class="day"><?php echo date('d', strtotime($event['Date'])); ?></span>
                                <span class="month"><?php echo date('M', strtotime($event['Date'])); ?></span>
                            </div>
                            <div class="card-content">
                                <h3><?php echo $event['NomEvenement']; ?></h3>
                                <div class="event-info">
                                    <p><strong>Heure:</strong> <?php echo $event['HeureDebut']; ?> - <?php echo $event['HeureFin']; ?></p>
                                    <p><strong>Lieu:</strong> <?php echo $event['Lieu']; ?></p>
                                    <p><strong>Type:</strong> <?php echo $event['TypeParticipant']; ?></p>
                                    <p><strong>Inscrits:</strong> <?php echo $event['nb_inscrits']; ?>/<?php echo $event['CapaciteMax'] ?: '∞'; ?></p>
                                </div>
                                <div class="event-actions">
                                    <a href="evenement_detail.php?id=<?php echo $event['IdEvenement']; ?>" class="btn btn-outline">Détails</a>
                                    <a href="inscription_event.php?id=<?php echo $event['IdEvenement']; ?>" class="btn btn-primary">S'inscrire</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>Aucun événement à venir</h3>
                        <p>Ce club n'a pas d'événements programmés pour le moment.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 EventManager. Tous droits réservés.</p>
        </div>
    </footer>

    <script>
    function rejoindreClub(clubId) {
        if (confirm('Voulez-vous rejoindre ce club ?')) {
            // Ici vous ajouterez la logique AJAX pour rejoindre le club
            alert('Fonctionnalité à implémenter avec AJAX');
        }
    }
    </script>
</body>
</html>