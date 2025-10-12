<?php
/**
 * Gestion des clubs - Backend PHP
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Vérifier que c'est bien un super admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header('Location: ../auth/login.php');
    exit;
}

requireRole(['administrateur']);

// Initialiser la connexion à la base de données
$database = new Database();
$conn = $database->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                $club_id = $_POST['club_id'] ?? null;
                if ($club_id) {
                    try {
                        // Vérifier s'il y a des événements liés
                        $sql_check_events = "SELECT COUNT(*) as nb_events FROM Evenement WHERE IdClub = :club_id";
                        $stmt_check = $conn->prepare($sql_check_events);
                        $stmt_check->bindParam(':club_id', $club_id);
                        $stmt_check->execute();
                        $nb_events = $stmt_check->fetch(PDO::FETCH_ASSOC)['nb_events'];
                        
                        if ($nb_events > 0) {
                            $error_message = "Impossible de supprimer ce club car il contient des événements.";
                        } else {
                            // Supprimer le logo s'il existe
                            $sql_logo = "SELECT Logo FROM Club WHERE IdClub = :club_id";
                            $stmt_logo = $conn->prepare($sql_logo);
                            $stmt_logo->bindParam(':club_id', $club_id);
                            $stmt_logo->execute();
                            $logo = $stmt_logo->fetch(PDO::FETCH_ASSOC)['Logo'];
                            
                            if ($logo && file_exists('../uploads/clubs/' . $logo)) {
                                unlink('../uploads/clubs/' . $logo);
                            }
                            
                            // Supprimer le club
                            $sql_delete = "DELETE FROM Club WHERE IdClub = :club_id";
                            $stmt_delete = $conn->prepare($sql_delete);
                            $stmt_delete->bindParam(':club_id', $club_id);
                            
                            if ($stmt_delete->execute()) {
                                $success_message = "Club supprimé avec succès.";
                            } else {
                                $error_message = "Erreur lors de la suppression du club.";
                            }
                        }
                    } catch (PDOException $e) {
                        $error_message = "Erreur de base de données : " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Récupérer tous les clubs avec leurs statistiques
try {
    $sql = "SELECT 
                c.IdClub,
                c.NomClub,
                c.Description,
                c.DateCreation,
                c.Logo,
                u.Nom as admin_nom,
                u.Prenom as admin_prenom,
                u.Email as admin_email,
                (SELECT COUNT(*) FROM Adhesion a WHERE a.IdClub = c.IdClub AND a.Status = 'actif') as nb_membres,
                (SELECT COUNT(*) FROM Evenement e WHERE e.IdClub = c.IdClub) as nb_evenements
            FROM Club c
            LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
            ORDER BY c.DateCreation DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les clubs - Event Manager</title>
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
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                    <div class="user-role">Super Administrateur</div>
                </div>
                <div class="user-avatar-modern"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1)); ?></div>
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
                        <a href="dashboard.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">📊</div>
                            Tableau de bord
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="gerer_clubs.php" class="sidebar-nav-link-modern active">
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
            <?php if (isset($success_message)): ?>
                <div class="alert-modern alert-success-modern">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert-modern alert-error-modern">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="page-title">
                <div>
                    <h1>Gérer les clubs</h1>
                    <p>Supervisez et gérez tous les clubs de la plateforme</p>
                </div>
                <a href="creer_club.php" class="btn btn-primary">
                    ➕ Créer un club
                </a>
            </div>

            <?php if (empty($clubs)): ?>
                <div class="empty-state-modern">
                    <div class="empty-state-icon-modern">🏛️</div>
                    <h3>Aucun club trouvé</h3>
                    <p>Commencez par créer votre premier club pour organiser des événements.</p>
                    <a href="creer_club.php" class="btn btn-primary">Créer un club</a>
                </div>
            <?php else: ?>
                <div class="clubs-grid-modern">
                    <?php foreach ($clubs as $club): ?>
                        <div class="club-card-modern">
                            <div class="club-header-modern">
                                <?php if (!empty($club['Logo'])): ?>
                                    <img src="../uploads/clubs/<?php echo htmlspecialchars($club['Logo']); ?>" 
                                         alt="Logo <?php echo htmlspecialchars($club['NomClub']); ?>" 
                                         class="club-logo-modern">
                                <?php else: ?>
                                    <div class="club-logo-modern">🏛️</div>
                                <?php endif; ?>
                                <div class="club-info-modern">
                                    <h3 class="club-name-modern"><?php echo htmlspecialchars($club['NomClub']); ?></h3>
                                    <p class="club-date-modern">Créé le <?php echo date('d/m/Y', strtotime($club['DateCreation'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="club-description-modern">
                                <?php echo htmlspecialchars($club['Description'] ?: 'Aucune description disponible.'); ?>
                            </div>
                            
                            <div class="club-stats-modern">
                                <div class="club-stat-modern">
                                    <div class="club-stat-value-modern"><?php echo $club['nb_membres']; ?></div>
                                    <div class="club-stat-label-modern">Membres</div>
                                </div>
                                <div class="club-stat-modern">
                                    <div class="club-stat-value-modern"><?php echo $club['nb_evenements']; ?></div>
                                    <div class="club-stat-label-modern">Événements</div>
                                </div>
                            </div>
                            
                            <div class="club-admin-modern">
                                <div class="club-admin-label-modern">Administrateur</div>
                                <div><?php echo htmlspecialchars($club['admin_prenom'] . ' ' . $club['admin_nom']); ?></div>
                            </div>
                            
                            <div class="club-actions-modern">
                                <a href="recap_club.php?id=<?php echo $club['IdClub']; ?>" class="btn btn-outline btn-sm">Voir</a>
                                <a href="creer_club.php?edit=<?php echo $club['IdClub']; ?>" class="btn btn-outline btn-sm">Modifier</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce club ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="club_id" value="<?php echo $club['IdClub']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--error); border-color: var(--error);">Supprimer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
