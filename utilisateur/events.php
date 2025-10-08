<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'participant') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Filtres et recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$club_filter = isset($_GET['club']) ? (int)$_GET['club'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

// Construction requête (sans nb_inscrits car table différente)
$query = "SELECT e.*, c.NomClub, c.Logo,
          u.Prenom as AdminPrenom, u.Nom as AdminNom
          FROM Evenement e 
          JOIN Club c ON e.IdClub = c.IdClub 
          JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
          WHERE e.Date >= CURDATE() AND (e.Etat = 'valide' OR e.Etat IS NULL)";

$params = [];
$count_query = "SELECT COUNT(*) FROM Evenement e 
                JOIN Club c ON e.IdClub = c.IdClub 
                WHERE e.Date >= CURDATE() AND e.Etat = 'valide'";

if (!empty($search)) {
    $query .= " AND (e.NomEvenement LIKE ? OR e.Description LIKE ? OR c.NomClub LIKE ?)";
    $count_query .= " AND (e.NomEvenement LIKE ? OR e.Description LIKE ? OR c.NomClub LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($club_filter > 0) {
    $query .= " AND e.IdClub = ?";
    $count_query .= " AND e.IdClub = ?";
    $params = array_merge($params, [$club_filter]);
}

$query .= " ORDER BY e.Date, e.HeureDebut LIMIT ? OFFSET ?";
$params = array_merge($params, [$limit, $offset]);

// Exécution
$stmt = $db->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter total
$stmt_count = $db->prepare($count_query);
$count_params = [];
if (!empty($search)) {
    $count_params = array_merge($count_params, [$search_param, $search_param, $search_param]);
}
if ($club_filter > 0) {
    $count_params = array_merge($count_params, [$club_filter]);
}
$stmt_count->execute($count_params);
$total_events = $stmt_count->fetchColumn();
$total_pages = ceil($total_events / $limit);

// Récupérer la liste des clubs pour le filtre
$clubs_query = "SELECT IdClub, NomClub FROM Club WHERE Statut = 'actif' ORDER BY NomClub";
$clubs_stmt = $db->prepare($clubs_query);
$clubs_stmt->execute();
$all_clubs = $clubs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Événements - EventManager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">EventManager</div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Accueil</a></li>
                <li><a href="clubs.php" class="nav-link">Clubs</a></li>
                <li><a href="events.php" class="nav-link active">Événements</a></li>
                <li><a href="mes_events.php" class="nav-link">Mes Événements</a></li>
                <li>
                    <div class="dropdown">
                        <button class="btn btn-outline dropbtn">
                            <?php echo $_SESSION['user_prenom']; ?> ▼
                        </button>
                        <div class="dropdown-content">
                            <a href="parametres.php">Paramètres</a>
                            <a href="../auth/logout.php">Déconnexion</a>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <main class="main">
        <div class="container">
            <div class="page-header slide-up">
                <h1>Tous les Événements</h1>
                <p class="text-light">Découvrez et inscrivez-vous aux événements qui vous intéressent</p>
            </div>

            <!-- Filtres et recherche -->
            <div class="filters-section card slide-up">
                <form method="GET" action="">
                    <div class="grid grid-3">
                        <div class="form-group">
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Rechercher un événement..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="club" class="form-control">
                                <option value="0">Tous les clubs</option>
                                <?php foreach ($all_clubs as $club): ?>
                                    <option value="<?php echo $club['IdClub']; ?>" 
                                            <?php echo $club_filter == $club['IdClub'] ? 'selected' : ''; ?>>
                                        <?php echo $club['NomClub']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Filtrer</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Résultats -->
            <section class="section">
                <?php if (!empty($search) || $club_filter > 0): ?>
                    <div class="alert alert-info">
                        <?php echo $total_events; ?> événement(s) trouvé(s)
                        <?php if (!empty($search)): ?> pour "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-3">
                    <?php foreach ($events as $event): ?>
                    <div class="event-card card fade-in">
                        <div class="event-header">
                            <div class="event-date">
                                <span class="date-day"><?php echo date('d', strtotime($event['Date'])); ?></span>
                                <span class="date-month"><?php echo date('M', strtotime($event['Date'])); ?></span>
                            </div>
                            <div class="event-club">
                                <?php if (!empty($event['Logo'])): ?>
                                    <img src="../uploads/<?php echo $event['Logo']; ?>" 
                                         alt="<?php echo $event['NomClub']; ?>" 
                                         class="club-logo-xs">
                                <?php endif; ?>
                                <span><?php echo $event['NomClub']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h3><?php echo $event['NomEvenement']; ?></h3>
                            <div class="event-details">
                                <p><strong>🕒 Heure:</strong> <?php echo $event['HeureDebut']; ?> - <?php echo $event['HeureFin']; ?></p>
                                <p><strong>📍 Lieu:</strong> <?php echo $event['Lieu']; ?></p>
                                <p><strong>👥 Type:</strong> <?php echo $event['TypeParticipant']; ?></p>
                                <?php if (!empty($event['CapaciteMax'])): ?>
                                    <p><strong>🎯 Capacité:</strong> <?php echo (int)$event['CapaciteMax']; ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($event['Description'])): ?>
                                <p class="event-description"><?php echo substr($event['Description'], 0, 100); ?>...</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex gap-2">
                                <a href="evenement_detail.php?id=<?php echo $event['IdEvenement']; ?>" 
                                   class="btn btn-outline btn-sm" style="flex: 1;">Détails</a>
                                <a href="inscription_event.php?id=<?php echo $event['IdEvenement']; ?>" 
                                   class="btn btn-primary btn-sm">S'inscrire</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination et état vide -->
                <?php if ($total_pages > 1): ?>
                    <!-- Même pagination que clubs.php -->
                <?php endif; ?>

                <?php if (empty($events)): ?>
                    <div class="empty-state text-center">
                        <div class="alert alert-warning">
                            <h3>Aucun événement trouvé</h3>
                            <p>Essayez de modifier vos critères de recherche.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>