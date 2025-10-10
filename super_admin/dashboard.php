<?php
/**
 * Dashboard Super Admin - Backend PHP Complet
 */

require_once '../config/database.php';
require_once '../config/session.php';
//require_once '../config/auto_login.php';

// Vérifier que c'est bien un super admin
requireRole(['administrateur']);
// Initialiser la connexion à la base de données
$database = new Database();
$conn = $database->getConnection();

// Vérifier la connexion
if (!$conn) {
    die("Erreur de connexion à la base de données");
}

// Récupérer les statistiques
try {
    // Nombre de clubs actifs
    $sql_clubs = "SELECT COUNT(*) as total FROM club";
    $stmt_clubs = $conn->prepare($sql_clubs);
    $stmt_clubs->execute();
    $total_clubs = $stmt_clubs->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre d'événements ce mois
    $sql_events = "SELECT COUNT(*) as total FROM evenement 
                   WHERE MONTH(Date) = MONTH(CURRENT_DATE()) 
                   AND YEAR(Date) = YEAR(CURRENT_DATE())";
    $stmt_events = $conn->prepare($sql_events);
    $stmt_events->execute();
    $total_events_mois = $stmt_events->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre total d'utilisateurs
    $sql_users = "SELECT COUNT(*) as total FROM utilisateur";
    $stmt_users = $conn->prepare($sql_users);
    $stmt_users->execute();
    $total_users = $stmt_users->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre d'emails envoyés
    $sql_emails = "SELECT COUNT(*) as total FROM email";
    $stmt_emails = $conn->prepare($sql_emails);
    $stmt_emails->execute();
    $total_emails = $stmt_emails->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer les clubs récents avec leurs informations
    $sql_clubs_recents = "
        SELECT 
            c.IdClub,
            c.NomClub,
            c.DateCreation,
            u.Nom as admin_nom,
            u.Prenom as admin_prenom,
            (SELECT COUNT(*) FROM Adhesion a WHERE a.IdParticipant = u.IdUtilisateur AND a.Status = 'actif') as nb_membres,
            (SELECT COUNT(*) FROM Evenement e WHERE e.IdClub = c.IdClub) as nb_evenements
        FROM Club c
        LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
        ORDER BY c.DateCreation DESC
        LIMIT 3
    ";
    $stmt_clubs_recents = $conn->prepare($sql_clubs_recents);
    $stmt_clubs_recents->execute();
    $clubs_recents = $stmt_clubs_recents->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Récupérer les informations du super admin connecté
$nom_admin = $_SESSION['prenom'] . ' ' . $_SESSION['nom'];
$initiales_admin = strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Super Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; background: #f5f7fa; }
        .navbar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand {
            font-size: 1.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .btn-logout {
            padding: 8px 20px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .layout { display: flex; min-height: calc(100vh - 70px); }
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 30px 0;
        }
        .sidebar-section {
            margin-bottom: 30px;
        }
        .sidebar-title {
            padding: 0 25px;
            color: #999;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sidebar-menu .icon {
            margin-right: 15px;
            font-size: 1.3em;
        }
        .main-content { flex: 1; padding: 30px; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2em; margin-bottom: 10px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            margin-bottom: 15px;
        }
        .stat-icon.blue { background: #e3f2fd; color: #2196f3; }
        .stat-icon.green { background: #e8f5e9; color: #4caf50; }
        .stat-icon.orange { background: #fff3e0; color: #ff9800; }
        .stat-icon.purple { background: #f3e5f5; color: #9c27b0; }
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-label { color: #666; font-size: 0.95em; }
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .quick-actions h2 { margin-bottom: 20px; color: #333; }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .action-btn {
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: transform 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .action-btn:hover { transform: translateY(-2px); }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f5f7fa; }
        th {
            padding: 15px;
            text-align: left;
            color: #666;
            font-weight: 600;
            font-size: 0.9em;
        }
        td {
            padding: 15px;
            border-top: 1px solid #eee;
            color: #555;
        }
        tbody tr:hover { background: #f9f9f9; }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .badge.success { background: #e8f5e9; color: #4caf50; }
        .badge.danger { background: #ffebee; color: #f44336; }
        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 3px;
        }
        .btn-view { background: #e3f2fd; color: #2196f3; }
        .btn-view:hover { background: #2196f3; color: white; }
        .btn-edit { background: #fff3e0; color: #ff9800; }
        .btn-edit:hover { background: #ff9800; color: white; }
        .btn-delete { background: #ffebee; color: #f44336; }
        .btn-delete:hover { background: #f44336; color: white; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">GestionEvents</div>
        <div class="user-info">
            <div class="user-avatar"><?php echo $initiales_admin; ?></div>
            <span><strong> <?php echo htmlspecialchars($nom_admin); ?></strong></span>
            <button class="btn-logout" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
        </div>
    </nav>

    <div class="layout">
        <aside class="sidebar">
            <!-- Section Administration -->
            <div class="sidebar-section">
                <div class="sidebar-title">Administration</div>
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php" class="active"><span class="icon">■</span>Tableau de bord</a></li>
                    <li><a href="gerer_clubs.php"><span class="icon">■</span>Gérer les clubs</a></li>
                    <li><a href="liste_admins.php"><span class="icon">■</span>Admins des clubs</a></li>
                    <li><a href="evenements.php"><span class="icon">■</span>Les événements</a></li>
                    <li><a href="utilisateurs.php"><span class="icon">■</span>Les utilisateurs de l'application</a></li>
                    <li><a href="emails.php"><span class="icon">■</span>Envoyer un email</a></li>
                    <li><a href="validations.php"><span class="icon">■</span>Validations</a></li>
                    
                </ul>
            </div>
            
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Tableau de bord</h1>
                <p>Vue d'ensemble de la plateforme</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">■</div>
                    <div class="stat-value"><?php echo $total_clubs; ?></div>
                    <div class="stat-label">Clubs actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">■</div>
                    <div class="stat-value"><?php echo $total_events_mois; ?></div>
                    <div class="stat-label">Événements ce mois</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">■</div>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Utilisateurs inscrits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">■</div>
                    <div class="stat-value"><?php echo $total_emails; ?></div>
                    <div class="stat-label">Emails envoyés</div>
                </div>
            </div>

            <div class="quick-actions">
                <h2>Actions rapides</h2>
                <div class="action-buttons">
                    <a href="creer_club.php" class="action-btn">
                        <span>+</span> Créer un club
                    </a>
                    <a href="creer_admin_club.php" class="action-btn">
                        <span>+</span> Ajouter un admin
                    </a>
                    <a href="gerer_clubs.php" class="action-btn">
                        <span>■</span> Voir tous les clubs
                    </a>
                    <a href="evenements.php" class="action-btn">
                        <span>■</span> Voir les événements
                    </a>
                    <a href="validations.php" class="action-btn">
                        <span>✓</span> Validations
                    </a>
                    
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h2>Clubs récents</h2>
                    <a href="gerer_clubs.php" class="btn btn-primary">Voir tout →</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Admin</th>
                            <th>Membres</th>
                            <th>Événements</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($clubs_recents)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px; color: #999;">
                                    Aucun club récent trouvé.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clubs_recents as $club): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($club['NomClub']); ?></strong></td>
                                <td><?php echo htmlspecialchars($club['admin_prenom'] . ' ' . $club['admin_nom']); ?></td>
                                <td><?php echo $club['nb_membres']; ?></td>
                                <td><?php echo $club['nb_evenements']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($club['DateCreation'])); ?></td>
                                <td>
                                    <button class="btn-icon btn-view" title="Voir" onclick="window.location.href='voir_club.php?id=<?php echo $club['IdClub']; ?>'">V</button>
                                    <button class="btn-icon btn-edit" title="Modifier" onclick="window.location.href='modifier_club.php?id=<?php echo $club['IdClub']; ?>'">E</button>
                                    <button class="btn-icon btn-delete" title="Supprimer" onclick="confirmDelete(<?php echo $club['IdClub']; ?>)">D</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
        <script>
        function confirmDelete(clubId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce club ?')) {
                window.location.href = 'supprimer_club.php?id=' + clubId;
            }
        }
    </script>

</body>
</html>