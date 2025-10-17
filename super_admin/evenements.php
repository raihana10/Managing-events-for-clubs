<?php
/**
 * Gestion des événements - Backend PHP
 */

require_once '../config/database.php';
require_once '../config/session.php';
require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    <title>Gestion des événements - Event Manager</title>
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
                        <a href="liste_admins.php" class="sidebar-nav-link-modern">
                            <div class="sidebar-nav-icon-modern">👥</div>
                            Admins des clubs
                        </a>
                    </li>
                    <li class="sidebar-nav-item-modern">
                        <a href="evenements.php" class="sidebar-nav-link-modern active">
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
                <h1>Gestion des événements</h1>
                <p>Supervisez tous les événements de la plateforme</p>
            </div>

            <?php if (empty($evenements)): ?>
                <div class="empty-state-modern">
                    <div class="empty-state-icon-modern">📅</div>
                    <h3>Aucun événement trouvé</h3>
                    <p>Les clubs n'ont pas encore créé d'événements.</p>
                </div>
            <?php else: ?>
                <div class="table-modern">
                    <div class="table-header-modern">
                        <h2 class="table-title-modern">Liste des événements</h2>
                        <div class="flex gap-sm">
                            <span class="badge badge-info"><?php echo count($evenements); ?> événement(s)</span>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Événement</th>
                                <th>Club</th>
                                <th>Date & Heure</th>
                                <th>Lieu</th>
                                <th>Type de participants</th>
                                <th>Participants</th>
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
                                        <strong><?php echo htmlspecialchars($event['NomClub']); ?></strong>
                                        <div class="event-details">
                                            Admin: <?php echo htmlspecialchars($event['admin_prenom'] . ' ' . $event['admin_nom']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo date('d/m/Y', strtotime($event['Date'])); ?></strong>
                                        <div class="event-details">
                                            <?php echo date('H:i', strtotime($event['Heure'])); ?> - <?php echo date('H:i', strtotime($event['HeureFin'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($event['Lieu']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(ucfirst($event['TypeParticipant'])); ?></strong><br>
                                    <?php
                                    $prix_adherent = $event['PrixAdherent'];
                                    $prix_non_adherent = $event['PrixNonAdherent'];
                                    $prix_externe = $event['PrixExterne'];
                                        if ($event['TypeParticipant'] === 'Adhérents') {
                                            echo 'Adhérents :' . ($prix_adherent > 0 ? ' ' . number_format($prix_adherent, 2) . ' DH' : ' Gratuit');
                                        } elseif ($event['TypeParticipant'] === 'Ensatiens') {
                                            echo 'Adhérents :' . ($prix_adherent > 0 ? ' ' . number_format($prix_adherent, 2) . ' DH' : ' Gratuit') . '<br>';
                                            echo 'Non adhérents  :' . ($prix_non_adherent > 0 ? ' ' . number_format($prix_non_adherent, 2) . ' DH' : ' Gratuit');
                                        } elseif ($event['TypeParticipant'] === 'Tous') {
                                            echo 'Adhérents :' . ($prix_adherent > 0 ? ' ' . number_format($prix_adherent, 2) . ' DH' : ' Gratuit') . '<br>';
                                            echo 'Non adhérents  :' . ($prix_non_adherent > 0 ? ' ' . number_format($prix_non_adherent, 2) . ' DH' : ' Gratuit') . '<br>';
                                            echo 'Externes :' . ($prix_externe > 0 ? ' ' . number_format($prix_externe, 2) . ' DH' : ' Gratuit');
                                        }
                                    ?>
                                    </td>
                                    <td>
                                        <div class="capacity-info">
                                            <strong><?php echo $event['nb_inscrits']; ?>/<?php echo $event['CapaciteMax']; ?></strong>
                                            <div class="capacity-bar">
                                                <div class="capacity-fill <?php echo ($event['nb_inscrits'] / $event['CapaciteMax']) > 0.8 ? 'warning' : ''; ?>" 
                                                     style="width: <?php echo min(100, ($event['nb_inscrits'] / $event['CapaciteMax']) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch($event['Status']) {
                                            case 'validé':
                                                $status_class = 'badge-success';
                                                $status_text = 'Validé';
                                                break;
                                            case 'en attente':
                                                $status_class = 'badge-warning';
                                                $status_text = 'En attente';
                                                break;
                                            case 'refusé':
                                                $status_class = 'badge-error';
                                                $status_text = 'Refusé';
                                                break;
                                            default:
                                                $status_class = 'badge-info';
                                                $status_text = ucfirst($event['Status']);
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
