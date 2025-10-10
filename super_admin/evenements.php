<?php
/**
 * Gestion des événements - Backend PHP
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

// Récupérer tous les événements avec leurs informations
try {
    $sql = "SELECT 
                e.IdEvenement,
                e.NomEvenement,
                e.Date,
                e.HeureDebut as Heure,
                e.HeureFin,
                e.Lieu,
                e.CapaciteMax,
                e.Etat as Status,
                e.TypeParticipant,
                e.Description,
                e.PrixAdherent,
                e.PrixNonAdherent,
                e.PrixExterne,
                c.NomClub,
                c.IdClub,
                u.Nom as admin_nom,
                u.Prenom as admin_prenom,
                (SELECT COUNT(*) FROM Inscription ie WHERE ie.IdEvenement = e.IdEvenement) as nb_inscrits
            FROM Evenement e
            JOIN Club c ON e.IdClub = c.IdClub
            LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
            ORDER BY e.Date DESC, Heure DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des événements</title>
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
        .event-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .event-details {
            font-size: 0.9em;
            color: #666;
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
        .capacity-bar {
            width: 100px;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        .capacity-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
        }
        .capacity-fill.warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }
        .capacity-fill.danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
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
        .no-events {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-events-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
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
            <h1>📅 Gestion des événements</h1>
            <p>Vue d'ensemble de tous les événements de la plateforme</p>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($evenements); ?></div>
                <div class="stat-label">Total événements</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $evenements_actifs = array_filter($evenements, function($e) { 
                        return $e['Status'] == 'actif'; 
                    });
                    echo count($evenements_actifs);
                    ?>
                </div>
                <div class="stat-label">Événements actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $total_inscrits = array_sum(array_column($evenements, 'nb_inscrits'));
                    echo $total_inscrits;
                    ?>
                </div>
                <div class="stat-label">Total inscriptions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $evenements_ce_mois = array_filter($evenements, function($e) { 
                        return date('Y-m', strtotime($e['Date'])) == date('Y-m'); 
                    });
                    echo count($evenements_ce_mois);
                    ?>
                </div>
                <div class="stat-label">Ce mois</div>
            </div>
        </div>

        <div class="table-container">
            <?php if (empty($evenements)): ?>
                <div class="no-events">
                    <div class="no-events-icon">📅</div>
                    <h3>Aucun événement trouvé</h3>
                    <p>Les événements créés par les clubs apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <div class="table-header">
                    <h2>Liste des événements</h2>
                    <span class="badge info"><?php echo count($evenements); ?> événement(s)</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Club</th>
                            <th>Date & Heure</th>
                            <th>Lieu</th>
                            <th>Prix</th>
                            <th>Capacité</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evenements as $event): ?>
                            <tr>
                                <td>
                                    <div class="event-info">
                                        <h4><?php echo htmlspecialchars($event['NomEvenement']); ?></h4>
                                        <div class="event-details">
                                            <?php if (!empty($event['Description'])): ?>
                                                <?php echo htmlspecialchars(substr($event['Description'], 0, 100)) . (strlen($event['Description']) > 100 ? '...' : ''); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($event['NomClub']); ?></strong><br>
                                    <small>Admin: <?php echo htmlspecialchars($event['admin_prenom'] . ' ' . $event['admin_nom']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo date('d/m/Y', strtotime($event['Date'])); ?></strong><br>
                                    <small><?php echo date('H:i', strtotime($event['Heure'])); ?> - <?php echo date('H:i', strtotime($event['HeureFin'])); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($event['Lieu']); ?></td>
                                <td>
                                    <div style="font-size: 0.9em;">
                                        <?php if ($event['PrixAdherent'] == 0 || $event['PrixAdherent'] === null): ?>
                                            <div><strong>Adhérent:</strong> <span class="badge success">Gratuit</span></div>
                                        <?php else: ?>
                                            <div><strong>Adhérent:</strong> <?php echo number_format(floatval($event['PrixAdherent']), 2); ?> €</div>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['PrixNonAdherent'] == 0 || $event['PrixNonAdherent'] === null): ?>
                                            <div><strong>Non-adhérent:</strong> <span class="badge success">Gratuit</span></div>
                                        <?php else: ?>
                                            <div><strong>Non-adhérent:</strong> <?php echo number_format(floatval($event['PrixNonAdherent']), 2); ?> €</div>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['PrixExterne'] == 0 || $event['PrixExterne'] === null): ?>
                                            <div><strong>Externe:</strong> <span class="badge success">Gratuit</span></div>
                                        <?php else: ?>
                                            <div><strong>Externe:</strong> <?php echo number_format(floatval($event['PrixExterne']), 2); ?> €</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="capacity-bar">
                                            <?php 
                                            $pourcentage = $event['CapaciteMax'] > 0 ? ($event['nb_inscrits'] / $event['CapaciteMax']) * 100 : 0;
                                            $classe_capacite = $pourcentage >= 90 ? 'danger' : ($pourcentage >= 70 ? 'warning' : '');
                                            ?>
                                            <div class="capacity-fill <?php echo $classe_capacite; ?>" 
                                                 style="width: <?php echo min($pourcentage, 100); ?>%"></div>
                                        </div>
                                        <span><?php echo $event['nb_inscrits']; ?>/<?php echo $event['CapaciteMax']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($event['Status']) {
                                        case 'actif':
                                            $status_class = 'success';
                                            $status_text = 'Actif';
                                            break;
                                        case 'annule':
                                            $status_class = 'danger';
                                            $status_text = 'Annulé';
                                            break;
                                        case 'termine':
                                            $status_class = 'warning';
                                            $status_text = 'Terminé';
                                            break;
                                        default:
                                            $status_class = 'info';
                                            $status_text = ucfirst($event['Status']);
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
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
