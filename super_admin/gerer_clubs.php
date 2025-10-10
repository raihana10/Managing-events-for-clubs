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
    <title>Gérer les clubs</title>
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
        .clubs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        .club-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .club-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .club-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .club-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #f0f0f0;
        }
        .club-logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
            margin-right: 15px;
        }
        .club-info h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.3em;
        }
        .club-date {
            color: #666;
            font-size: 0.9em;
        }
        .club-description {
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .club-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 0.85em;
            color: #666;
        }
        .admin-info {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .admin-info strong {
            color: #1565c0;
        }
        .club-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
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
        .no-clubs {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-clubs-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            .clubs-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            .club-actions {
                justify-content: center;
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
            <h1>🏢 Gérer les clubs</h1>
            <a href="creer_club.php" class="btn btn-primary">
                ➕ Créer un nouveau club
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

        <?php if (empty($clubs)): ?>
            <div class="no-clubs">
                <div class="no-clubs-icon">🏢</div>
                <h3>Aucun club trouvé</h3>
                <p>Commencez par créer votre premier club.</p>
                <a href="creer_club.php" class="btn btn-primary" style="margin-top: 20px;">
                    ➕ Créer un club
                </a>
            </div>
        <?php else: ?>
            <div class="clubs-grid">
                <?php foreach ($clubs as $club): ?>
                    <div class="club-card">
                        <div class="club-header">
                            <?php if (!empty($club['Logo'])): ?>
                                <img src="../uploads/clubs/<?php echo htmlspecialchars($club['Logo']); ?>" 
                                     class="club-logo" 
                                     alt="Logo <?php echo htmlspecialchars($club['NomClub']); ?>">
                            <?php else: ?>
                                <div class="club-logo-placeholder">
                                    🏢
                                </div>
                            <?php endif; ?>
                            <div class="club-info">
                                <h3><?php echo htmlspecialchars($club['NomClub']); ?></h3>
                                <div class="club-date">
                                    Créé le <?php echo date('d/m/Y', strtotime($club['DateCreation'])); ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($club['Description'])): ?>
                            <div class="club-description">
                                <?php echo htmlspecialchars($club['Description']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="club-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $club['nb_membres']; ?></div>
                                <div class="stat-label">Membres</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $club['nb_evenements']; ?></div>
                                <div class="stat-label">Événements</div>
                            </div>
                        </div>

                        <?php if (!empty($club['admin_prenom'])): ?>
                            <div class="admin-info">
                                <strong>👤 Administrateur :</strong><br>
                                <?php echo htmlspecialchars($club['admin_prenom'] . ' ' . $club['admin_nom']); ?><br>
                                <small><?php echo htmlspecialchars($club['admin_email']); ?></small>
                            </div>
                        <?php else: ?>
                            <div class="admin-info" style="background: #fff3e0; color: #e65100;">
                                <strong>⚠️ Aucun administrateur assigné</strong>
                            </div>
                        <?php endif; ?>

                        <div class="club-actions">
                            <a href="recap_club.php?id=<?php echo $club['IdClub']; ?>" 
                               class="btn-icon btn-view" title="Voir les détails">
                                👁️ Détails
                            </a>
                            <a href="creer_club.php?edit=<?php echo $club['IdClub']; ?>" 
                               class="btn-icon btn-edit" title="Modifier">
                                ✏️ Modifier
                            </a>
                            <button class="btn-icon btn-delete" 
                                    title="Supprimer" 
                                    onclick="confirmDelete(<?php echo $club['IdClub']; ?>, '<?php echo htmlspecialchars($club['NomClub']); ?>')">
                                🗑️ Supprimer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Formulaire de suppression caché -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="club_id" id="deleteClubId">
    </form>

    <script>
        function confirmDelete(clubId, clubName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le club "${clubName}" ?\n\nCette action est irréversible.`)) {
                document.getElementById('deleteClubId').value = clubId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
