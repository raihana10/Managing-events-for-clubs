<?php
/**
 * Gestion des utilisateurs - Backend PHP
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
            case 'toggle_status':
                $user_id = $_POST['user_id'] ?? null;
                if ($user_id) {
                    try {
                        // Récupérer le statut actuel
                        $sql_status = "SELECT Status FROM Utilisateur WHERE IdUtilisateur = :user_id";
                        $stmt_status = $conn->prepare($sql_status);
                        $stmt_status->bindParam(':user_id', $user_id);
                        $stmt_status->execute();
                        $current_status = $stmt_status->fetch(PDO::FETCH_ASSOC)['Status'];
                        
                        // Changer le statut
                        $new_status = $current_status == 'actif' ? 'inactif' : 'actif';
                        
                        $sql_update = "UPDATE Utilisateur SET Status = :status WHERE IdUtilisateur = :user_id";
                        $stmt_update = $conn->prepare($sql_update);
                        $stmt_update->bindParam(':status', $new_status);
                        $stmt_update->bindParam(':user_id', $user_id);
                        
                        if ($stmt_update->execute()) {
                            $success_message = "Statut de l'utilisateur modifié avec succès.";
                        } else {
                            $error_message = "Erreur lors de la modification du statut.";
                        }
                    } catch (PDOException $e) {
                        $error_message = "Erreur de base de données : " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Récupérer tous les utilisateurs avec leurs statistiques
try {
    $sql = "SELECT 
                u.IdUtilisateur,
                u.Nom,
                u.Prenom,
                u.Email,
                u.Telephone,
                u.Role,
                u.DateInscription,
                (SELECT COUNT(*) FROM Adhesion a WHERE a.IdParticipant = u.IdUtilisateur AND a.Status = 'actif') as nb_clubs,
                (SELECT COUNT(*) FROM Inscription ie WHERE ie.IdUtilisateur = u.IdUtilisateur) as nb_inscriptions
            FROM Utilisateur u
            ORDER BY u.DateInscription DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
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
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 2em;
            color: #333;
            margin-bottom: 10px;
        }
        .page-header p {
            color: #666;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
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
            margin-right: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-details h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .user-details p {
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
        .badge.info {
            background: #e3f2fd;
            color: #2196f3;
        }
        .badge.purple {
            background: #f3e5f5;
            color: #9c27b0;
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
        .btn-toggle {
            background: #fff3e0;
            color: #ff9800;
        }
        .btn-toggle:hover {
            background: #ff9800;
            color: white;
        }
        .no-users {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-users-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            .stats-grid {
                grid-template-columns: 1fr;
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
            <h1>👥 Gestion des utilisateurs</h1>
            <p>Vue d'ensemble de tous les utilisateurs de la plateforme</p>
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

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($utilisateurs); ?></div>
                <div class="stat-label">Total utilisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $organisateurs = array_filter($utilisateurs, function($u) { 
                        return $u['Role'] == 'organisateur'; 
                    });
                    echo count($organisateurs);
                    ?>
                </div>
                <div class="stat-label">Organisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $utilisateurs_actifs = array_filter($utilisateurs, function($u) { 
                        // Considérer comme actif s'il a au moins une adhésion active ou une inscription
                        return $u['nb_clubs'] > 0 || $u['nb_inscriptions'] > 0;
                    });
                    echo count($utilisateurs_actifs);
                    ?>
                </div>
                <div class="stat-label">Utilisateurs actifs</div>
            </div>
        </div>

        <div class="table-container">
            <?php if (empty($utilisateurs)): ?>
                <div class="no-users">
                    <div class="no-users-icon">👥</div>
                    <h3>Aucun utilisateur trouvé</h3>
                    <p>Les utilisateurs inscrits apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <div class="table-header">
                    <h2>Liste des utilisateurs</h2>
                    <span class="badge info"><?php echo count($utilisateurs); ?> utilisateur(s)</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Clubs</th>
                            <th>Inscriptions</th>
                            <th>Date d'inscription</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['Prenom'], 0, 1) . substr($user['Nom'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($user['Prenom'] . ' ' . $user['Nom']); ?></h4>
                                            <p>ID: <?php echo $user['IdUtilisateur']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                <td>
                                    <?php 
                                    $role_class = '';
                                    $role_text = '';
                                    switch ($user['Role']) {
                                        case 'administrateur':
                                            $role_class = 'purple';
                                            $role_text = 'Administrateur';
                                            break;
                                        case 'organisateur':
                                            $role_class = 'info';
                                            $role_text = 'Organisateur';
                                            break;
                                        case 'participant':
                                            $role_class = 'success';
                                            $role_text = 'Participant';
                                            break;
                                        default:
                                            $role_class = 'warning';
                                            $role_text = ucfirst($user['Role']);
                                    }
                                    ?>
                                    <span class="badge <?php echo $role_class; ?>"><?php echo $role_text; ?></span>
                                </td>
                                <td>
                                    <?php if ($user['Role'] !== 'administrateur'): ?>
                                        <span class="badge info"><?php echo $user['nb_clubs']; ?> club(s)</span>
                                    <?php else: ?>
                                        <span class="badge warning">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['Role'] !== 'administrateur'): ?>
                                        <span class="badge info"><?php echo $user['nb_inscriptions']; ?> inscription(s)</span>
                                    <?php else: ?>
                                        <span class="badge warning">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($user['DateInscription'])); ?></td>
                                <td>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['IdUtilisateur']; ?>">
                                        
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
