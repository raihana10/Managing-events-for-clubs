<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);

$database = new Database();
$db = $database->getConnection();
try { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Exception $e) {}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../auth/login.php'); exit(); }

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($event_id <= 0) { header('Location: mes_evenements.php'); exit(); }

// L'événement doit appartenir à un club de l'organisateur connecté
$sql = "SELECT e.*, c.NomClub FROM Evenement e INNER JOIN Club c ON c.IdClub = e.IdClub WHERE e.IdEvenement = :idevt AND c.IdAdminClub = :uid LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->bindParam(':idevt', $event_id, PDO::PARAM_INT);
$stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
$stmt->execute();
$e = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$e) { header('Location: mes_evenements.php'); exit(); }

function badge_class_for_state($etat) {
    $etat = strtolower(trim((string)$etat));
    switch ($etat) {
        case 'approuvé':
        case 'approuve':
        case 'validé':
        case 'valide':
            return 'badge-success';
        case 'refusé':
        case 'refuse':
        case 'annulé':
        case 'annule':
            return 'badge-danger';
        case 'en attente':
        default:
            return 'badge-warning';
    }
}

$date_fr = $e['Date'] ? date('d/m/Y', strtotime($e['Date'])) : '';
$heure_debut = $e['HeureDebut'] ? date('H:i', strtotime($e['HeureDebut'])) : '';
$heure_fin = $e['HeureFin'] ? date('H:i', strtotime($e['HeureFin'])) : '';
$etatClass = badge_class_for_state($e['Etat'] ?? '');
$affiche = $e['Affiche'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'événement - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/_sidebar.php'; ?>

    <div class="main-content">
        <header class="header-modern">
            <div class="header-content">
                <a href="dashboard.php" class="logo-modern">🎓 GestionEvents</a>
                <div class="header-right">
                    <a href="mes_evenements.php" class="btn btn-secondary">← Retour</a>
                    <div class="user-avatar-modern">
                        <?php echo strtoupper(substr($_SESSION['prenom'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="page-header">
                <h1><?php echo htmlspecialchars($e['NomEvenement'] ?? ''); ?></h1>
                <p>Organisé par <strong><?php echo htmlspecialchars($e['NomClub'] ?? ''); ?></strong></p>
            </div>

            <div class="grid grid-cols-2 gap-lg">
                <div class="card">
                    <div class="card-body text-center">
                        <?php if (!empty($affiche)): ?>
                            <img src="<?php echo htmlspecialchars($affiche); ?>" alt="Affiche de l'événement" class="rounded-lg shadow-md" style="max-width: 100%; height: auto;">
                        <?php else: ?>
                            <div class="empty-state-modern" style="margin:0;">
                                <div class="empty-state-icon-modern">📷</div>
                                <h3>Aucune affiche</h3>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="text-xs font-semibold text-secondary">Etat</div>
                        <div class="text-base font-medium" style="margin-bottom:14px;">
                            <span class="badge <?php echo $etatClass; ?>"><?php echo htmlspecialchars($e['Etat'] ?? ''); ?></span>
                        </div>
                        <div class="text-xs font-semibold text-secondary">Date</div>
                        <div class="text-base font-medium" style="margin-bottom:14px;">📅 <?php echo htmlspecialchars($date_fr); ?></div>
                        <div class="text-xs font-semibold text-secondary">Heures</div>
                        <div class="text-base font-medium" style="margin-bottom:14px;">🕒 <?php echo htmlspecialchars($heure_debut . ($heure_fin ? ' - ' . $heure_fin : '')); ?></div>
                        <div class="text-xs font-semibold text-secondary">Lieu</div>
                        <div class="text-base font-medium" style="margin-bottom:14px;">📍 <?php echo htmlspecialchars($e['Lieu'] ?? ''); ?></div>
                        <div class="text-xs font-semibold text-secondary">Type</div>
                        <div class="text-base font-medium" style="margin-bottom:14px;">
                            <span class="badge badge-info"><?php echo htmlspecialchars($e['TypeEvenement'] ?? ''); ?></span>
                        </div>
                        <div class="text-xs font-semibold text-secondary">Participants</div>
                        <div class="text-base font-medium" style="margin-bottom:14px;">👥 <?php echo htmlspecialchars($e['TypeParticipant'] ?? ''); ?></div>
                        <div class="text-xs font-semibold text-secondary">Capacité</div>
                        <div class="text-base font-medium" style="margin-bottom:14px;">
                            <?php if (!empty($e['CapaciteMax'])): ?>
                                🎟️ <?php echo (int)$e['CapaciteMax']; ?> places
                            <?php else: ?>
                                <span class="badge badge-success">Illimitée</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs font-semibold text-secondary">Tarifs</div>
                        <div class="text-base" style="margin-bottom:14px;">
                            <div>Adhérent: <strong><?php echo number_format((float)($e['PrixAdherent'] ?? 0), 2, ',', ' '); ?> DH</strong></div>
                            <div>Non-adhérent: <strong><?php echo number_format((float)($e['PrixNonAdherent'] ?? 0), 2, ',', ' '); ?> DH</strong></div>
                            <div>Externe: <strong><?php echo number_format((float)($e['PrixExterne'] ?? 0), 2, ',', ' '); ?> DH</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section-modern">
                <h3 class="form-section-title-modern">Description</h3>
                <div class="card">
                    <div class="card-body">
                        <p style="white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($e['description'] ?? '')); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
