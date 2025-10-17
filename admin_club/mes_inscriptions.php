<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Only admin club (organisateur)
requireRole(['organisateur']);

$database = new Database();
$db = $database->getConnection();
try { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Exception $e) {}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/login.php');
    exit;
}

// Handle unsubscription (désinscription) POST — process before output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscription_id'])) {
    $id_inscription = (int)$_POST['inscription_id'];

    // Verify the inscription belongs to this user
    $check = $db->prepare("SELECT IdInscription FROM Inscription WHERE IdInscription = :id AND IdUtilisateur = :uid LIMIT 1");
    $check->bindParam(':id', $id_inscription, PDO::PARAM_INT);
    $check->bindParam(':uid', $user_id, PDO::PARAM_INT);
    $check->execute();
    if ($check->rowCount() === 1) {
        $del = $db->prepare("DELETE FROM Inscription WHERE IdInscription = :id AND IdUtilisateur = :uid");
        $del->bindParam(':id', $id_inscription, PDO::PARAM_INT);
        $del->bindParam(':uid', $user_id, PDO::PARAM_INT);
        if ($del->execute()) {
            $_SESSION['success_message'] = "Vous avez été désinscrit de l'événement.";
        } else {
            $_SESSION['error_message'] = "Erreur lors de la désinscription.";
        }
    } else {
        $_SESSION['error_message'] = "Inscription introuvable ou non autorisée.";
    }
    header('Location: mes_inscriptions.php');
    exit;
}

// Get club of this admin (if any)
$club = null;
$stmt = $db->prepare("SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :uid LIMIT 1");
$stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
$stmt->execute();
$club = $stmt->fetch(PDO::FETCH_ASSOC);
$club_id = $club['IdClub'] ?? null;

// Fetch future events that are not created by this admin's club and are validated
$events = [];
$query = "SELECT e.IdEvenement, e.NomEvenement, e.Date, e.HeureDebut, e.HeureFin, e.Lieu, c.NomClub, e.Affiche
          FROM Evenement e
          JOIN Club c ON e.IdClub = c.IdClub
          WHERE e.Date >= CURDATE() AND e.Etat = 'valide'";
if ($club_id !== null) {
    $query .= " AND e.IdClub <> :club_id";
}
$query .= " ORDER BY e.Date ASC, e.HeureDebut ASC";
$stmt = $db->prepare($query);
if ($club_id !== null) {
    $stmt->bindParam(':club_id', $club_id, PDO::PARAM_INT);
}
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch inscriptions of this admin user (from Inscription table)
$inscriptions = [];
$q2 = "SELECT i.IdInscription, e.IdEvenement, e.NomEvenement, e.Date, e.HeureDebut, e.Lieu, c.NomClub
       FROM Inscription i
       JOIN Evenement e ON i.IdEvenement = e.IdEvenement
       JOIN Club c ON e.IdClub = c.IdClub
       WHERE i.IdUtilisateur = :uid
       ORDER BY e.Date ASC, e.HeureDebut ASC";
$stmt2 = $db->prepare($q2);
$stmt2->bindParam(':uid', $user_id, PDO::PARAM_INT);
$stmt2->execute();
$inscriptions = $stmt2->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes inscriptions - GestionEvents</title>
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
                    <div class="user-avatar-modern">
                        <?php echo strtoupper(substr($_SESSION['prenom'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="page-header">
                <h1>Mes inscriptions</h1>
                <p>Gérez vos inscriptions</p>
            </div>
                <div class="card">
                    <div class="card-header">
                        <h2>Événements disponibles</h2>
                        <?php if (!empty($club)): ?>
                            <div class="muted">Événements futurs (hors <?php echo htmlspecialchars($club['NomClub']); ?>)</div>
                        <?php else: ?>
                            <div class="muted">Événements futurs</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events)): ?>
                            <p>Aucun événement futur disponible.</p>
                        <?php else: ?>
                            <div class="events-list">
                                <?php foreach ($events as $ev): ?>
                                    <div class="event-item">
                                        <div class="event-meta">
                                            <strong><?php echo htmlspecialchars($ev['NomEvenement']); ?></strong>
                                            <div class="muted"><?php echo date('d/m/Y', strtotime($ev['Date'])); ?> - <?php echo htmlspecialchars($ev['Lieu']); ?></div>
                                            <div class="muted">Organisé par: <?php echo htmlspecialchars($ev['NomClub']); ?></div>
                                        </div>
                                        <div class="event-actions">
                                            <a class="btn btn-primary btn-sm" href="../utilisateur/inscription_evenement.php?id=<?php echo (int)$ev['IdEvenement']; ?>">Voir / Inscrire</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" style="margin-top:20px;">
                    <div class="card-header">
                        <h2>Mes inscriptions</h2>
                        <div class="muted">Liste des inscriptions effectuées par votre compte</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inscriptions)): ?>
                            <p>Vous n'avez aucune inscription.</p>
                        <?php else: ?>
                            <table class="table-modern responsive-table">
                                <thead>
                                    <tr>
                                        <th>Événement</th>
                                        <th>Date</th>
                                        <th>Heure</th>
                                        <th>Lieu</th>
                                        <th>Club</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inscriptions as $ins): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ins['NomEvenement']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($ins['Date'])); ?></td>
                                            <td><?php echo htmlspecialchars($ins['HeureDebut']); ?></td>
                                            <td><?php echo htmlspecialchars($ins['Lieu']); ?></td>
                                            <td><?php echo htmlspecialchars($ins['NomClub']); ?></td>
                                            <td>
                                                <form method="POST" action="mes_inscriptions.php" onsubmit="return confirm('Êtes-vous sûr de vouloir vous désinscrire ?');">
                                                    <input type="hidden" name="inscription_id" value="<?php echo (int)$ins['IdInscription']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Se désinscrire</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

