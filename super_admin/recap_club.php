<?php
/**
 * Récapitulatif création de club - Backend PHP
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

// Récupérer les données de la session ou des paramètres GET
$club_id = $_GET['id'] ?? null;
$club_data = [];
$email_sent = isset($_GET['email_sent']) && $_GET['email_sent'] == '1';

if ($club_id) {
    // Récupérer les informations du club depuis la base de données
    try {
        $sql = "SELECT c.*, u.Nom as admin_nom, u.Prenom as admin_prenom, u.Email as admin_email
                FROM Club c 
                LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur 
                WHERE c.IdClub = :club_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':club_id', $club_id);
        $stmt->execute();
        $club_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$club_data) {
            die("Club non trouvé");
        }
    } catch (PDOException $e) {
        die("Erreur de base de données : " . $e->getMessage());
    }
} else {
    die("ID du club non spécifié");
}

// Récupérer les statistiques du club
try {
    // Nombre de membres
    $sql_membres = "SELECT COUNT(*) as nb_membres 
                    FROM Adhesion a 
                    JOIN Utilisateur u ON a.IdParticipant = u.IdUtilisateur 
                    WHERE a.IdClub = :club_id AND a.Status = 'actif'";
    $stmt_membres = $conn->prepare($sql_membres);
    $stmt_membres->bindParam(':club_id', $club_id);
    $stmt_membres->execute();
    $nb_membres = $stmt_membres->fetch(PDO::FETCH_ASSOC)['nb_membres'];
    
    // Nombre d'événements
    $sql_events = "SELECT COUNT(*) as nb_evenements 
                   FROM Evenement 
                   WHERE IdClub = :club_id";
    $stmt_events = $conn->prepare($sql_events);
    $stmt_events->bindParam(':club_id', $club_id);
    $stmt_events->execute();
    $nb_evenements = $stmt_events->fetch(PDO::FETCH_ASSOC)['nb_evenements'];
    
    // Prochains événements
    $sql_prochains_events = "SELECT NomEvenement, Date, Lieu 
                             FROM evenement 
                             WHERE IdClub = :club_id AND Date >= CURDATE() 
                             ORDER BY Date ASC 
                             LIMIT 3";
    $stmt_prochains = $conn->prepare($sql_prochains_events);
    $stmt_prochains->bindParam(':club_id', $club_id);
    $stmt_prochains->execute();
    $prochains_events = $stmt_prochains->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $nb_membres = 0;
    $nb_evenements = 0;
    $prochains_events = [];
}
// Vérifier si c'est une mise à jour
$is_updated = isset($_GET['updated']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club créé avec succès</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header-success h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header-success .icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .header-success p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .club-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid #667eea;
        }

        .info-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .info-label {
            font-weight: bold;
            width: 180px;
            color: #2c3e50;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
            color: #555;
        }

        .logo-container {
            text-align: center;
            margin: 20px 0;
        }

        .logo-container img {
            border: 3px solid #bdc3c7;
            border-radius: 10px;
            max-width: 200px;
            max-height: 200px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid #f1f3f4;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .events-list {
            margin-top: 20px;
        }

        .event-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .event-name {
            font-weight: bold;
            color: #2c3e50;
        }

        .event-details {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .buttons-container {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-align: center;
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
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(243, 156, 18, 0.4);
        }

        .no-data {
            color: #7f8c8d;
            font-style: italic;
        }

        .admin-info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .club-info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .buttons-container {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    
    <div class="container">
        <!-- En-tête de succès -->
        <div class="header-success">
            <div class="icon">🎉</div>
            <h1><?php echo $is_updated ? 'Club modifié avec succès !' : 'Club créé avec succès !'; ?></h1>
            <p><?php echo $is_updated ? 'Votre club a été modifié avec succès' : 'Votre club a été créé et est maintenant opérationnel'; ?></p>
        </div>

        <?php if ($email_sent): ?>
            <div class="alert alert-success" style="margin: 20px 0; padding: 15px; background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; border-radius: 8px;">
                ✅ <strong>Email envoyé avec succès !</strong><br>
                L'organisateur a été notifié par email de sa nomination.
            </div>
        <?php endif; ?>

        <div class="content">
            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $nb_membres; ?></div>
                    <div class="stat-label">Membres</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $nb_evenements; ?></div>
                    <div class="stat-label">Événements</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('d/m/Y', strtotime($club_data['DateCreation'])); ?></div>
                    <div class="stat-label">Date de création</div>
                </div>
            </div>

            <!-- Informations du club -->
            <div class="club-info-grid">
                <!-- Informations générales -->
                <div class="info-section">
                    <h3>🏢 Informations du club</h3>
                    
                    <div class="info-row">
                        <div class="info-label">Nom du club :</div>
                        <div class="info-value"><?php echo htmlspecialchars($club_data['NomClub']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Date de création :</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($club_data['DateCreation'])); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Description :</div>
                        <div class="info-value">
                            <?php if (!empty($club_data['Description'])): ?>
                                <?php echo nl2br(htmlspecialchars($club_data['Description'])); ?>
                            <?php else: ?>
                                <span class="no-data">Aucune description</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Logo -->
                    <div class="logo-container">
                        <?php if (!empty($club_data['Logo'])): ?>
                            <img src="../uploads/clubs/<?php echo htmlspecialchars($club_data['Logo']); ?>" 
                                 alt="Logo du club <?php echo htmlspecialchars($club_data['NomClub']); ?>">
                        <?php else: ?>
                            <div class="no-data">Aucun logo</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Administrateur et événements -->
                <div class="info-section">
                    <h3>👤 Administration</h3>
                    
                    <div class="info-row">
                        <div class="info-label">Administrateur :</div>
                        <div class="info-value">
                            <?php if (!empty($club_data['admin_prenom'])): ?>
                                <?php echo htmlspecialchars($club_data['admin_prenom'] . ' ' . $club_data['admin_nom']); ?>
                                <div class="admin-info">
                                    <strong>Email :</strong> <?php echo htmlspecialchars($club_data['admin_email']); ?>
                                </div>
                            <?php else: ?>
                                <span class="no-data">À assigner</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Prochains événements -->
                    <h3 style="margin-top: 30px;">📅 Prochains événements</h3>
                    <div class="events-list">
                        <?php if (!empty($prochains_events)): ?>
                            <?php foreach ($prochains_events as $event): ?>
                                <div class="event-item">
                                    <div class="event-name"><?php echo htmlspecialchars($event['NomEvenement']); ?></div>
                                    <div class="event-details">
                                        📍 <?php echo htmlspecialchars($event['Lieu']); ?> 
                                        | 📅 <?php echo date('d/m/Y', strtotime($event['Date'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">Aucun événement à venir</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="buttons-container">
                <a href="gerer_clubs.php" class="btn btn-primary">
                    📋 Voir tous les clubs
                </a>
                
                <!-- Bouton Modifier avec GET -->
                <a href="creer_club.php?edit=<?php echo $club_id; ?>" class="btn btn-warning">
                    ✏️ Modifier le club
                </a>
                
                <a href="dashboard.php" class="btn btn-secondary">
                    🏠 Retour au dashboard
                </a>
            </div>
        </div>
    </div>

</body>
</html>