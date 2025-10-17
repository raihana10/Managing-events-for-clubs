<?php
/**
 * Liste des administrateurs de clubs - Backend PHP
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
                $admin_id = $_POST['admin_id'] ?? null;
                if ($admin_id) {
                    try {
                        // Vérifier si l'admin gère des clubs
                        $sql_check_clubs = "SELECT COUNT(*) as nb_clubs FROM Club WHERE IdAdminClub = :admin_id";
                        $stmt_check = $conn->prepare($sql_check_clubs);
                        $stmt_check->bindParam(':admin_id', $admin_id);
                        $stmt_check->execute();
                        $nb_clubs = $stmt_check->fetch(PDO::FETCH_ASSOC)['nb_clubs'];
                        
                        if ($nb_clubs > 0) {
                            $error_message = "Impossible de modifier le rôle de cet administrateur car il gère des clubs.";
                        } else {
                            // Changer le rôle de organisateur à participant
                            $sql_update = "UPDATE Utilisateur SET Role = 'participant' WHERE IdUtilisateur = :admin_id AND Role = 'organisateur'";
                            $stmt_update = $conn->prepare($sql_update);
                            $stmt_update->bindParam(':admin_id', $admin_id);
                            
                            if ($stmt_update->execute()) {
                                $success_message = "Rôle de l'administrateur modifié avec succès (devenu participant).";
                            } else {
                                $error_message = "Erreur lors de la modification du rôle.";
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

// Récupérer tous les administrateurs avec leurs clubs
try {
    $sql = "SELECT 
                u.IdUtilisateur,
                u.Nom,
                u.Prenom,
                u.Email,
                u.DateInscription,
                COUNT(c.IdClub) as nb_clubs_geres,
                GROUP_CONCAT(c.NomClub SEPARATOR ', ') as clubs_geres
            FROM Utilisateur u
            LEFT JOIN Club c ON u.IdUtilisateur = c.IdAdminClub
            WHERE u.Role = 'organisateur'
            GROUP BY u.IdUtilisateur
            ORDER BY u.DateInscription DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrateurs des clubs - Event Manager</title>
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
                        <a href="gerer_clubs.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">🏛️</div>
                            Gérer les clubs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="liste_admins.php" class="sidebar-nav-link-modern active">
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
                <h1>Administrateurs des clubs</h1>
                <p>Gérez les organisateurs de clubs</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert-modern alert-success-modern">
                    <div class="alert-icon-modern">✅</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Succès</div>
                        <div class="alert-message-modern"><?php echo htmlspecialchars($success_message); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert-modern alert-error-modern">
                    <div class="alert-icon-modern">❌</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Erreur</div>
                        <div class="alert-message-modern"><?php echo htmlspecialchars($error_message); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($admins)): ?>
                <div class="empty-state-modern">
                    <div class="empty-state-icon-modern">👥</div>
                    <h3>Aucun administrateur trouvé</h3>
                    <p>Commencez par créer votre premier administrateur de club.</p>
                    <a href="creer_admin_club.php" class="btn btn-primary" style="margin-top: 20px;">
                        <span class="btn-icon">➕</span>
                        Créer un administrateur
                    </a>
                </div>
            <?php else: ?>
                <div class="form-section-modern">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                        <h3>Liste des administrateurs</h3>
                        <a href="creer_admin_club.php" class="btn btn-primary">
                            <span class="btn-icon">➕</span>
                            Créer un administrateur
                        </a>
                    </div>
                    <div class="table-modern">
                        <div class="table-header-modern">
                            <div class="table-title-modern"><?php echo count($admins); ?> administrateur(s)</div>
                        </div>
                        <div class="table-content-modern">
                            <table>
                    <thead>
                        <tr>
                            <th>Administrateur</th>
                            <th>Email</th>
                            <th>Clubs gérés</th>
                            <th>Date d'inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-md">
                                        <div class="user-avatar-modern">
                                            <?php echo strtoupper(substr($admin['Prenom'], 0, 1) . substr($admin['Nom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-base">
                                                <?php echo htmlspecialchars($admin['Prenom'] . ' ' . $admin['Nom']); ?>
                                            </div>
                                            <div class="text-sm" style="color: var(--neutral-500);">
                                                ID: <?php echo $admin['IdUtilisateur']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($admin['Email']); ?></td>
                                <td>
                                    <?php if ($admin['nb_clubs_geres'] > 0): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                            <?php 
                                            $clubs = explode(', ', $admin['clubs_geres']);
                                            foreach ($clubs as $club): 
                                            ?>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($club); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Aucun club</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($admin['DateInscription'])); ?></td>
                                <td>
                                    <div class="flex gap-sm">
                                        <button class="btn btn-outline btn-sm" title="Supprimer" 
                                                onclick="confirmDelete(<?php echo $admin['IdUtilisateur']; ?>, '<?php echo htmlspecialchars($admin['Prenom'] . ' ' . $admin['Nom']); ?>')">
                                            Rétrograder
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Formulaire de suppression caché -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="admin_id" id="deleteAdminId">
    </form>

    <script>
        function confirmDelete(adminId, adminName) {
            if (confirm(`Êtes-vous sûr de vouloir modifier le rôle de l'administrateur "${adminName}" ?\n\nIl deviendra un simple participant.`)) {
                document.getElementById('deleteAdminId').value = adminId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>