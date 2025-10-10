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
                            $error_message = "Impossible de supprimer cet administrateur car il gère des clubs.";
                        } else {
                            // Supprimer l'administrateur
                            $sql_delete = "DELETE FROM Utilisateur WHERE IdUtilisateur = :admin_id AND Role = 'organisateur'";
                            $stmt_delete = $conn->prepare($sql_delete);
                            $stmt_delete->bindParam(':admin_id', $admin_id);
                            
                            if ($stmt_delete->execute()) {
                                $success_message = "Administrateur supprimé avec succès.";
                            } else {
                                $error_message = "Erreur lors de la suppression de l'administrateur.";
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
                /* u.Status, */
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
    <title>Administrateurs des clubs</title>
    <link rel="stylesheet" href="../frontend/css.css">
    <style>
        body { margin: 0; background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 2em;
            color: #333;
            margin: 0;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #555;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f5f7fa;
        }
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
        tbody tr:hover {
            background: #f9f9f9;
        }
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        .admin-info {
            display: flex;
            align-items: center;
        }
        .admin-details h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .admin-details p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .badge.success {
            background: #e8f5e9;
            color: #4caf50;
        }
        .badge.warning {
            background: #fff3e0;
            color: #ff9800;
        }
        .badge.danger {
            background: #ffebee;
            color: #f44336;
        }
        .clubs-list {
            max-width: 200px;
            font-size: 0.9em;
        }
        .clubs-list .club-item {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 8px;
            border-radius: 12px;
            margin: 2px;
            display: inline-block;
            font-size: 0.8em;
        }
        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 3px;
            font-size: 0.9em;
        }
        .btn-view {
            background: #e3f2fd;
            color: #2196f3;
        }
        .btn-view:hover {
            background: #2196f3;
            color: white;
        }
        .btn-edit {
            background: #fff3e0;
            color: #ff9800;
        }
        .btn-edit:hover {
            background: #ff9800;
            color: white;
        }
        .btn-delete {
            background: #ffebee;
            color: #f44336;
        }
        .btn-delete:hover {
            background: #f44336;
            color: white;
        }
        .no-admins {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-admins-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🎓 GestionEvents</div>
        <a href="dashboard.php" class="btn btn-secondary">Retour au dashboard</a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>👥 Administrateurs des clubs</h1>
            <a href="creer_admin_club.php" class="btn btn-primary">
                ➕ Créer un administrateur
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if (empty($admins)): ?>
                <div class="no-admins">
                    <div class="no-admins-icon">👥</div>
                    <h3>Aucun administrateur trouvé</h3>
                    <p>Commencez par créer votre premier administrateur de club.</p>
                    <a href="creer_admin_club.php" class="btn btn-primary" style="margin-top: 20px;">
                        ➕ Créer un administrateur
                    </a>
                </div>
            <?php else: ?>
                <div class="table-header">
                    <h2>Liste des administrateurs</h2>
                    <span class="badge success"><?php echo count($admins); ?> administrateur(s)</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Administrateur</th>
                            <th>Email</th>
                            <th>Clubs gérés</th>
                            <th>Date d'inscription</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td>
                                    <div class="admin-info">
                                        <div class="admin-avatar">
                                            <?php echo strtoupper(substr($admin['Prenom'], 0, 1) . substr($admin['Nom'], 0, 1)); ?>
                                        </div>
                                        <div class="admin-details">
                                            <h4><?php echo htmlspecialchars($admin['Prenom'] . ' ' . $admin['Nom']); ?></h4>
                                            <p>ID: <?php echo $admin['IdUtilisateur']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($admin['Email']); ?></td>
                                <td>
                                    <div class="clubs-list">
                                        <?php if ($admin['nb_clubs_geres'] > 0): ?>
                                            <?php 
                                            $clubs = explode(', ', $admin['clubs_geres']);
                                            foreach ($clubs as $club): 
                                            ?>
                                                <span class="club-item"><?php echo htmlspecialchars($club); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="badge warning">Aucun club</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($admin['DateInscription'])); ?></td>
                                <!--
                                <td>
                                    <?php if ($admin['Status'] == 'actif'): ?>
                                        <span class="badge success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge danger">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                -->
                                <td>
                                    <button class="btn-icon btn-view" title="Voir le profil">
                                        👁️
                                    </button>
                                    <button class="btn-icon btn-edit" title="Modifier">
                                        ✏️
                                    </button>
                                    <button class="btn-icon btn-delete" 
                                            title="Supprimer" 
                                            onclick="confirmDelete(<?php echo $admin['IdUtilisateur']; ?>, '<?php echo htmlspecialchars($admin['Prenom'] . ' ' . $admin['Nom']); ?>')">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulaire de suppression caché -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="admin_id" id="deleteAdminId">
    </form>

    <script>
        function confirmDelete(adminId, adminName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'administrateur "${adminName}" ?\n\nCette action est irréversible.`)) {
                document.getElementById('deleteAdminId').value = adminId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
