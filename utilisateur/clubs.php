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

// Gestion de la recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

// Construction de la requête (sans compteurs de membres/événements)
$query = "SELECT c.*, u.Prenom as AdminPrenom, u.Nom as AdminNom
          FROM Club c 
          JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur 
          WHERE (c.Statut = 'actif' OR c.Statut IS NULL)";

$params = [];
$count_query = "SELECT COUNT(*) FROM Club c WHERE c.Statut = 'actif'";

if (!empty($search)) {
    $query .= " AND (c.NomClub LIKE ? OR c.Description LIKE ?)";
    $count_query .= " AND (c.NomClub LIKE ? OR c.Description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$query .= " ORDER BY c.DateCreation DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$limit, $offset]);

// Exécution
$stmt = $db->prepare($query);
$stmt->execute($params);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le total
$stmt_count = $db->prepare($count_query);
if (!empty($search)) {
    $stmt_count->execute([$search_param, $search_param]);
} else {
    $stmt_count->execute();
}
$total_clubs = $stmt_count->fetchColumn();
$total_pages = ceil($total_clubs / $limit);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tous les Clubs - EventManager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">EventManager</div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Accueil</a></li>
                <li><a href="clubs.php" class="nav-link active">Clubs</a></li>
                <li><a href="events.php" class="nav-link">Événements</a></li>
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
                <h1>Explorer les Clubs</h1>
                <p class="text-light">Découvrez tous les clubs disponibles et rejoignez ceux qui vous intéressent</p>
            </div>

            <!-- Barre de recherche -->
            <div class="search-section card slide-up">
                <form method="GET" action="" class="d-flex gap-2 align-center">
                    <div class="form-group" style="flex: 1;">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Rechercher un club par nom ou description..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Rechercher</button>
                    <?php if (!empty($search)): ?>
                        <a href="clubs.php" class="btn btn-outline">Effacer</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Résultats -->
            <section class="section">
                <?php if (!empty($search)): ?>
                    <div class="alert alert-info">
                        <?php echo $total_clubs; ?> club(s) trouvé(s) pour "<?php echo htmlspecialchars($search); ?>"
                    </div>
                <?php endif; ?>

                <div class="grid grid-3">
                    <?php foreach ($clubs as $club): ?>
                    <div class="club-card card fade-in">
                        <div class="card-header">
                            <div class="d-flex justify-between align-center">
                                <h3><?php echo $club['NomClub']; ?></h3>
                                <?php if (!empty($club['Logo'])): ?>
                                    <img src="../uploads/<?php echo $club['Logo']; ?>" 
                                         alt="<?php echo $club['NomClub']; ?>" 
                                         class="club-logo-sm">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="club-description"><?php echo $club['Description']; ?></p>
                            <!-- Compteurs non disponibles dans le schéma actuel -->
                            <div class="club-meta">
                                <p><strong>Admin:</strong> <?php echo $club['AdminPrenom'] . ' ' . $club['AdminNom']; ?></p>
                                <p><strong>Créé le:</strong> <?php echo date('d/m/Y', strtotime($club['DateCreation'])); ?></p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex gap-2">
                                <a href="club_detail.php?id=<?php echo $club['IdClub']; ?>" 
                                   class="btn btn-primary btn-sm" style="flex: 1;">Voir détails</a>
                                <button class="btn btn-outline btn-sm" 
                                        onclick="rejoindreClub(<?php echo $club['IdClub']; ?>)">
                                    Rejoindre
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination mt-4">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn btn-outline">← Précédent</a>
                    <?php endif; ?>

                    <div class="pagination-numbers">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-outline'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn btn-outline">Suivant →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (empty($clubs)): ?>
                    <div class="empty-state text-center">
                        <div class="alert alert-warning">
                            <h3>Aucun club trouvé</h3>
                            <p><?php echo !empty($search) ? 'Essayez avec d\'autres termes de recherche.' : 'Aucun club n\'est disponible pour le moment.'; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script>
    function rejoindreClub(clubId) {
        if (confirm('Voulez-vous rejoindre ce club ?')) {
            // Simulation - À remplacer par appel AJAX
            fetch('../ajax/rejoindre_club.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'club_id=' + clubId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Vous avez rejoint le club avec succès !');
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la requête');
            });
        }
    }

    // Animation des cartes au scroll
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.club-card');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationDelay = (Math.random() * 0.5) + 's';
                    entry.target.classList.add('fade-in');
                }
            });
        }, { threshold: 0.1 });

        cards.forEach(card => {
            observer.observe(card);
        });
    });
    </script>
</body>
</html>