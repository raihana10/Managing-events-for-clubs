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
    <title>Dashboard Super Admin - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">🎓 Event Manager</a>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($nom_admin); ?></div>
                    <div class="user-role">Super Administrateur</div>
                </div>
                <div class="user-avatar-modern"><?php echo $initiales_admin; ?></div>
                <a href="../auth/logout.php" class="btn btn-ghost btn-sm">Déconnexion</a>
            </div>
        </div>
    </nav>

    <aside class="sidebar-modern">
        <nav class="sidebar-nav-modern">
            <div class="sidebar-section-modern">
                <div class="sidebar-title-modern">Administration</div>
                <ul class="sidebar-nav-modern">
                    <li class="sidebar-nav-item-modern">
                        <a href="dashboard.php" class="sidebar-nav-link-modern active">
                            <div class="sidebar-nav-icon-modern">📊</div>
                            Tableau de bord
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="gerer_clubs.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">🏛️</div>
                            Gérer les clubs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="liste_admins.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">👥</div>
                            Admins des clubs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="evenements.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">📅</div>
                            Les événements
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="utilisateurs.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">👤</div>
                            Les utilisateurs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="emails.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">📧</div>
                            Envoyer un email
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="validations.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">✅</div>
                            Validations
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>

    <div class="layout">
        <main class="main-content">
                <div class="page-title">
                    <h1>Tableau de bord</h1>
                    <p>Vue d'ensemble de la plateforme</p>
                </div>

                <div class="stats-grid-modern">
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Clubs actifs</div>
                            <div class="stat-icon-modern coral">🏛️</div>
                        </div>
                        <div class="stat-value-modern"><?php echo $total_clubs; ?></div>
                        <div class="stat-change-modern positive">+3 ce mois</div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Événements ce mois</div>
                            <div class="stat-icon-modern teal">📅</div>
                        </div>
                        <div class="stat-value-modern"><?php echo $total_events_mois; ?></div>
                        <div class="stat-change-modern positive">+12 cette semaine</div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Utilisateurs inscrits</div>
                            <div class="stat-icon-modern blue">👥</div>
                        </div>
                        <div class="stat-value-modern"><?php echo $total_users; ?></div>
                        <div class="stat-change-modern positive">+25 cette semaine</div>
                    </div>
                    <div class="stat-card-modern">
                        <div class="stat-header-modern">
                            <div class="stat-label-modern">Emails envoyés</div>
                            <div class="stat-icon-modern purple">📧</div>
                        </div>
                        <div class="stat-value-modern"><?php echo $total_emails; ?></div>
                        <div class="stat-change-modern positive">+8 cette semaine</div>
                    </div>
                </div>

                <div class="quick-actions-modern">
                    <h2 class="quick-actions-title-modern">Actions rapides</h2>
                    <div class="actions-grid-modern">
                        <a href="creer_club.php" class="action-card-modern">
                            <div class="action-icon-modern">➕</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Créer un club</div>
                                <div class="action-description-modern">Ajoutez un nouveau club</div>
                            </div>
                        </a>
                        <a href="creer_admin_club.php" class="action-card-modern">
                            <div class="action-icon-modern">👤</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Ajouter un admin</div>
                                <div class="action-description-modern">Créez un administrateur</div>
                            </div>
                        </a>
                        <a href="gerer_clubs.php" class="action-card-modern">
                            <div class="action-icon-modern">🏛️</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Voir tous les clubs</div>
                                <div class="action-description-modern">Gérez les clubs existants</div>
                            </div>
                        </a>
                        <a href="evenements.php" class="action-card-modern">
                            <div class="action-icon-modern">📅</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Voir les événements</div>
                                <div class="action-description-modern">Supervisez les événements</div>
                            </div>
                        </a>
                        <a href="validations.php" class="action-card-modern">
                            <div class="action-icon-modern">✅</div>
                            <div class="action-content-modern">
                                <div class="action-title-modern">Validations</div>
                                <div class="action-description-modern">Validez les demandes</div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="table-modern">
                    <div class="table-header-modern">
                        <h2 class="table-title-modern">Clubs récents</h2>
                        <a href="gerer_clubs.php" class="btn btn-primary btn-sm">Voir tout →</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Club</th>
                                <th>Admin</th>
                                <th>Membres</th>
                                <th>Événements</th>
                                <th>Date création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clubs_recents)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 20px; color: var(--neutral-500);">
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
                                        <div class="flex gap-sm">
                                            <button class="btn btn-outline btn-sm" title="Voir" onclick="window.location.href='voir_club.php?id=<?php echo $club['IdClub']; ?>'">Voir</button>
                                            <button class="btn btn-outline btn-sm" title="Modifier" onclick="window.location.href='modifier_club.php?id=<?php echo $club['IdClub']; ?>'">Modifier</button>
                                            <button class="btn btn-outline btn-sm" title="Supprimer" onclick="confirmDelete(<?php echo $club['IdClub']; ?>)">Supprimer</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function confirmDelete(clubId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce club ?')) {
                window.location.href = 'supprimer_club.php?id=' + clubId;
            }
        }
    </script>
</body>
</html>