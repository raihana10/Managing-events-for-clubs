<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'participant') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Récupérer les données de l'utilisateur
$user_id = $_SESSION['user_id'];

// Statistiques simplifiées (les tables Inscription/Adhesion ne correspondent pas au schéma fourni)
$stats = [
    'nb_inscriptions' => 0,
    'nb_clubs' => 0,
];

// Événements à venir
$query = "SELECT e.*, c.NomClub, c.Logo 
          FROM Evenement e 
          JOIN Club c ON e.IdClub = c.IdClub 
          WHERE e.Date >= CURDATE() AND e.Etat = 'valide'
          ORDER BY e.Date 
          LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clubs récents (le schéma fourni n'a pas de compteur de membres)
$query = "SELECT c.* FROM Club c ORDER BY c.DateCreation DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace - Utilisateur</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .navbar { background: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .navbar-brand { font-size: 1.5em; font-weight: bold; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .navbar-menu { display: flex; gap: 30px; align-items: center; }
        .navbar-menu a { color: #555; text-decoration: none; font-weight: 500; transition: color 0.3s; }
        .navbar-menu a:hover { color: #667eea; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .btn-logout { padding: 8px 20px; background: #f44336; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 40px; color: white; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(102,126,234,0.3); }
        .welcome-section h1 { font-size: 2.5em; margin-bottom: 10px; }
        .welcome-section p { font-size: 1.1em; opacity: 0.95; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); text-align: center; }
        .stat-icon { font-size: 3em; margin-bottom: 10px; }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #667eea; }
        .stat-label { color: #666; font-size: 0.95em; margin-top: 5px; }
        .section { background: white; border-radius: 15px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .section-header h2 { color: #333; font-size: 1.8em; }
        .btn { padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-outline { background: white; border: 2px solid #667eea; color: #667eea; }
        .btn-outline:hover { background: #667eea; color: white; }
        .clubs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .club-card { border: 2px solid #f0f0f0; border-radius: 15px; padding: 25px; transition: all 0.3s; cursor: pointer; }
        .club-card:hover { border-color: #667eea; box-shadow: 0 8px 20px rgba(102,126,234,0.15); transform: translateY(-5px); }
        .club-logo { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 2.5em; margin-bottom: 15px; color: #fff; }
        .club-name { font-size: 1.3em; font-weight: 600; color: #333; margin-bottom: 10px; }
        .club-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn-sm { padding: 8px 15px; font-size: 0.9em; }
        .event-list { display: flex; flex-direction: column; gap: 20px; }
        .event-item { border: 2px solid #f0f0f0; border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s; }
        .event-item:hover { border-color: #667eea; box-shadow: 0 4px 15px rgba(102,126,234,0.1); }
        .event-left { flex: 1; }
        .event-date-badge { background: #ede7f6; color: #667eea; padding: 8px 15px; border-radius: 8px; font-weight: 600; font-size: 0.9em; display: inline-block; margin-bottom: 10px; }
        .event-title { font-size: 1.3em; font-weight: 600; color: #333; margin-bottom: 10px; }
        .event-meta { display: flex; gap: 20px; color: #666; font-size: 0.95em; }
        .event-meta span { display: flex; align-items: center; gap: 5px; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 500; }
        @media (max-width: 768px) { .navbar-menu { display: none; } .welcome-section { padding: 25px; } .welcome-section h1 { font-size: 1.8em; } .event-item { flex-direction: column; align-items: flex-start; gap: 15px; } }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // rien pour l'instant
    });
    </script>
    
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🎓 GestionEvents</div>
        <div class="navbar-menu">
            <a href="dashboard.php">Accueil</a>
            <a href="clubs.php">Clubs</a>
            <a href="events.php">Événements</a>
            <a href="mes_events.php">Mes Événements</a>
        </div>
        <div class="user-info">
            <?php $initials = strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)); ?>
            <div class="user-avatar"><?php echo $initials; ?></div>
            <span><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></span>
            <button class="btn-logout" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-section">
            <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['prenom']); ?> ! 👋</h1>
            <p>Découvrez les clubs et événements de votre école</p>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon">🏢</div>
                <div class="stat-number"><?php echo (int)$stats['nb_clubs']; ?></div>
                <div class="stat-label">Clubs rejoints</div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">📅</div>
                <div class="stat-number"><?php echo (int)$stats['nb_inscriptions']; ?></div>
                <div class="stat-label">Événements inscrits</div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">✓</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Événements participés</div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">📜</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Attestations</div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>🏢 Mes clubs</h2>
                <a href="clubs.php" class="btn btn-primary">Découvrir plus →</a>
            </div>
            <div class="clubs-grid">
                <?php foreach ($clubs as $club): ?>
                <div class="club-card">
                    <div class="club-logo">🏷️</div>
                    <div class="club-name"><?php echo htmlspecialchars($club['NomClub']); ?></div>
                    <p style="color: #666; font-size: 0.95em;">
                        <?php echo htmlspecialchars(mb_strimwidth($club['Description'] ?? '', 0, 100, '...')); ?>
                    </p>
                    <div class="club-actions">
                        <a href="club_detail.php?id=<?php echo (int)$club['IdClub']; ?>" class="btn btn-primary btn-sm">Voir les événements</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($clubs)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1; color:#666;">
                        <div class="empty-state-icon">🏷️</div>
                        <h3>Aucun club disponible</h3>
                        <p>Découvrez les clubs depuis la page Clubs.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>📅 Prochains événements</h2>
                <a href="events.php" class="btn btn-outline">Voir tous les événements →</a>
            </div>
            <div class="event-list">
                <?php foreach ($evenements as $event): ?>
                <div class="event-item">
                    <div class="event-left">
                        <div class="event-date-badge"><?php echo date('d F Y', strtotime($event['Date'])); ?></div>
                        <div class="event-title"><?php echo htmlspecialchars($event['NomEvenement']); ?></div>
                        <div class="event-meta">
                            <span>🏢 <?php echo htmlspecialchars($event['NomClub']); ?></span>
                            <span>📍 <?php echo htmlspecialchars($event['Lieu']); ?></span>
                            <span>⏰ <?php echo htmlspecialchars($event['HeureDebut'] . ' - ' . $event['HeureFin']); ?></span>
                        </div>
                    </div>
                    <div>
                        <a href="inscription_event.php?id=<?php echo (int)$event['IdEvenement']; ?>" class="btn btn-primary btn-sm">Voir détails</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($evenements)): ?>
                <div class="empty-state" style="width:100%">
                    <div class="empty-state-icon">📭</div>
                    <h3>Aucun événement à venir</h3>
                    <p>Revenez bientôt pour découvrir les prochains événements.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>