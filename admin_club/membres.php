<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);

$database = new Database();
$db = $database->getConnection();
try { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Exception $e) {}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/login.php');
    exit();
}

// Générer un token CSRF simple
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Récupérer le club de l'admin connecté (supposé 1 club/admin)
$club_stmt = $db->prepare('SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :uid LIMIT 1');
$club_stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
$club_stmt->execute();
$club = $club_stmt->fetch(PDO::FETCH_ASSOC);
$club_id = $club['IdClub'] ?? null;

// Messages flash
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($club_id) {
    // Mise à jour automatique: toute adhésion de plus d'un an passe à "inactif"
    $expire_sql = "UPDATE Adhesion 
                   SET Status = 'inactif'
                   WHERE IdClub = :cid
                     AND Status = 'actif'
                     AND DateAdhesion < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $stmt = $db->prepare($expire_sql);
    $stmt->bindParam(':cid', $club_id, PDO::PARAM_INT);
    try { $stmt->execute(); } catch (Exception $e) {}

    // Traitement des actions POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted_token = $_POST['csrf_token'] ?? '';
        if (!$posted_token || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
            $_SESSION['error_message'] = "Erreur de sécurité (CSRF).";
            header('Location: membres.php');
            exit();
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'toggle_status') {
            $adhesion_id = (int)($_POST['adhesion_id'] ?? 0);
            if ($adhesion_id > 0) {
                // Vérifier l'adhésion appartient à ce club
                $q = $db->prepare("SELECT IdAdhesion, IdParticipant, Status, DateAdhesion FROM Adhesion WHERE IdAdhesion = :id AND IdClub = :cid");
                $q->bindParam(':id', $adhesion_id, PDO::PARAM_INT);
                $q->bindParam(':cid', $club_id, PDO::PARAM_INT);
                $q->execute();
                $adh = $q->fetch(PDO::FETCH_ASSOC);
                if ($adh) {
                    $current = strtolower(trim((string)$adh['Status']));
                    $dateAdh = $adh['DateAdhesion'] ?? null;
                    $can_activate = true;
                    if (!empty($dateAdh)) {
                        // Si plus d'un an, on bloque l'activation
                        $check = $db->prepare("SELECT (DATE(:d) < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)) AS expired");
                        $check->bindParam(':d', $dateAdh);
                        $check->execute();
                        $expired = (int)$check->fetchColumn() === 1;
                        if ($expired) { $can_activate = false; }
                    }

                    if ($current === 'actif') {
                        $new_status = 'inactif';
                    } else {
                        $new_status = $can_activate ? 'actif' : 'inactif';
                    }

                    if (!$can_activate && $new_status === 'inactif' && $current !== 'inactif') {
                        $_SESSION['error_message'] = "Impossible d'activer: adhésion expirée (plus d'un an).";
                    }

                    $u = $db->prepare("UPDATE Adhesion SET Status = :st WHERE IdAdhesion = :id AND IdClub = :cid");
                    $u->bindParam(':st', $new_status, PDO::PARAM_STR);
                    $u->bindParam(':id', $adhesion_id, PDO::PARAM_INT);
                    $u->bindParam(':cid', $club_id, PDO::PARAM_INT);
                    try {
                        $u->execute();
                        if (empty($_SESSION['error_message'])) {
                            $_SESSION['success_message'] = "Statut mis à jour.";
                        }
                    } catch (Exception $e) {
                        $_SESSION['error_message'] = "Erreur lors de la mise à jour.";
                    }
                } else {
                    $_SESSION['error_message'] = "Adhésion introuvable.";
                }
            }
            header('Location: membres.php');
            exit();
        }

            if ($action === 'delete_member') {
                $adhesion_id = (int)($_POST['adhesion_id'] ?? 0);
                if ($adhesion_id > 0) {
                    // Vérifier l'adhésion appartient à ce club
                    $q = $db->prepare("SELECT IdAdhesion FROM Adhesion WHERE IdAdhesion = :id AND IdClub = :cid");
                    $q->bindParam(':id', $adhesion_id, PDO::PARAM_INT);
                    $q->bindParam(':cid', $club_id, PDO::PARAM_INT);
                    $q->execute();
                    $adh = $q->fetch(PDO::FETCH_ASSOC);
                    if ($adh) {
                        $d = $db->prepare("DELETE FROM Adhesion WHERE IdAdhesion = :id AND IdClub = :cid");
                        $d->bindParam(':id', $adhesion_id, PDO::PARAM_INT);
                        $d->bindParam(':cid', $club_id, PDO::PARAM_INT);
                        try {
                            $d->execute();
                            $_SESSION['success_message'] = "Adhésion supprimée.";
                        } catch (Exception $e) {
                            $_SESSION['error_message'] = "Erreur lors de la suppression.";
                        }
                    } else {
                        $_SESSION['error_message'] = "Adhésion introuvable.";
                    }
                }
                header('Location: membres.php');
                exit();
            }

        if ($action === 'add_member') {
            $new_user_id = (int)($_POST['user_id'] ?? 0);
            if ($new_user_id > 0) {
                // Vérifier rôle et non-adhésion au club
                $uq = $db->prepare("SELECT IdUtilisateur FROM Utilisateur WHERE IdUtilisateur = :uid AND Role IN ('organisateur','participant')");
                $uq->bindParam(':uid', $new_user_id, PDO::PARAM_INT);
                $uq->execute();
                $ok_user = $uq->fetch(PDO::FETCH_ASSOC);

                $exists = false;
                if ($ok_user) {
                    $eq = $db->prepare("SELECT 1 FROM Adhesion WHERE IdClub = :cid AND IdParticipant = :uid LIMIT 1");
                    $eq->bindParam(':cid', $club_id, PDO::PARAM_INT);
                    $eq->bindParam(':uid', $new_user_id, PDO::PARAM_INT);
                    $eq->execute();
                    $exists = (bool)$eq->fetchColumn();
                }

                if ($ok_user && !$exists) {
                    $ins = $db->prepare("INSERT INTO Adhesion (IdParticipant, IdClub, Status, DateAdhesion) VALUES (:uid, :cid, 'actif', CURDATE())");
                    $ins->bindParam(':uid', $new_user_id, PDO::PARAM_INT);
                    $ins->bindParam(':cid', $club_id, PDO::PARAM_INT);
                    try {
                        $ins->execute();
                        $_SESSION['success_message'] = "Adhérent ajouté avec succès.";
                    } catch (Exception $e) {
                        $_SESSION['error_message'] = "Erreur lors de l'ajout de l'adhérent.";
                    }
                } else {
                    $_SESSION['error_message'] = $exists ? "Cet utilisateur est déjà adhérent du club." : "Utilisateur invalide.";
                }
            }
            header('Location: membres.php');
            exit();
        }
    }
}

// Filtres (GET)
$search = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$members = [];
$total_members = 0;
$active_members = 0;
$eligible_users = [];

if ($club_id) {
    // Comptages
    $stmt = $db->prepare('SELECT COUNT(*) FROM Adhesion WHERE IdClub = :cid');
    $stmt->bindParam(':cid', $club_id, PDO::PARAM_INT);
    $stmt->execute();
    $total_members = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM Adhesion WHERE IdClub = :cid AND Status = 'actif'");
    $stmt->bindParam(':cid', $club_id, PDO::PARAM_INT);
    $stmt->execute();
    $active_members = (int)$stmt->fetchColumn();

    // Liste des membres
    $sql = "SELECT a.IdAdhesion, a.IdParticipant, a.IdClub, a.Status, a.DateAdhesion,
                   u.IdUtilisateur, u.Prenom, u.Nom, u.Email
            FROM Adhesion a
            JOIN Utilisateur u ON u.IdUtilisateur = a.IdParticipant
            WHERE a.IdClub = :cid";
    if ($status_filter !== '') { $sql .= " AND a.Status = :status"; }
    if ($search !== '') { $sql .= " AND (u.Nom LIKE :search OR u.Prenom LIKE :search OR u.Email LIKE :search)"; }
    $sql .= ' ORDER BY a.DateAdhesion DESC, u.Nom ASC, u.Prenom ASC';

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':cid', $club_id, PDO::PARAM_INT);
    if ($status_filter !== '') { $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR); }
    if ($search !== '') { $like = "%{$search}%"; $stmt->bindParam(':search', $like, PDO::PARAM_STR); }
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Utilisateurs éligibles à l'ajout: organisateur/participant non adhérents de ce club
    $eu = $db->prepare("SELECT u.IdUtilisateur, u.Prenom, u.Nom, u.Email, u.Role
                         FROM Utilisateur u
                         WHERE u.Role IN ('organisateur','participant')
                           AND NOT EXISTS (
                                SELECT 1 FROM Adhesion a
                                WHERE a.IdClub = :cid AND a.IdParticipant = u.IdUtilisateur
                           )
                         ORDER BY u.Nom, u.Prenom");
    $eu->bindParam(':cid', $club_id, PDO::PARAM_INT);
    $eu->execute();
    $eligible_users = $eu->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres du Club - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header-modern">
        <div class="header-content">
            <a href="dashboard.php" class="logo-modern">Event Manager</a>
            <div class="header-right">
                <div class="club-info">
                    <span class="club-badge"></span>
                    <span><?php echo htmlspecialchars($club['NomClub'] ?? 'Mon Club'); ?></span>
                </div>
                <div class="user-section">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                        <div class="user-role">Administrateur du club</div>
                    </div>
                    <?php $initials = strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)); ?>
                    <div class="user-avatar-modern"><?php echo $initials; ?></div>
                    <button class="btn btn-ghost btn-sm" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="layout">
            <?php include __DIR__ . '/_sidebar.php'; ?>

            <main class="main-content">
                <div class="page-title">
                    <h1>Membres du club</h1>
                    <p>Gestion des adhésions: activation/inactivation et ajout de membres</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert-modern alert-error-modern"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert-modern alert-success-modern"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <?php if (!$club_id): ?>
                    <div class="empty-state-modern">
                        <div class="empty-state-icon-modern">🏛️</div>
                        <h3>Aucun club associé</h3>
                        <p>Votre compte n'est associé à aucun club en tant qu'organisateur.</p>
                    </div>
                <?php else: ?>
                    <div class="stats-grid-modern">
                        <div class="stat-card-modern">
                            <div class="stat-header-modern">
                                <div class="stat-label-modern">Total adhérents</div>
                                <div class="stat-icon-modern blue">👥</div>
                            </div>
                            <div class="stat-value-modern"><?php echo htmlspecialchars((string)$total_members); ?></div>
                        </div>
                        <div class="stat-card-modern">
                            <div class="stat-header-modern">
                                <div class="stat-label-modern">Actifs</div>
                                <div class="stat-icon-modern teal">✅</div>
                            </div>
                            <div class="stat-value-modern"><?php echo htmlspecialchars((string)$active_members); ?></div>
                        </div>
                    </div>

                    <div class="admin-section-modern">
                        <form method="GET" class="form-row-modern" style="gap: var(--space-md); align-items:flex-end;">
                            <div class="form-group-modern" style="flex: 1;">
                                <label class="form-label-modern" for="q">Rechercher</label>
                                <input type="text" id="q" name="q" class="form-input-modern" placeholder="Nom, prénom ou email..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="status">Statut</label>
                                <select id="status" name="status" class="form-input-modern form-select-modern">
                                    <option value="">Tous</option>
                                    <option value="actif" <?php echo $status_filter==='actif'?'selected':''; ?>>Actif</option>
                                    <option value="inactif" <?php echo $status_filter==='inactif'?'selected':''; ?>>Inactif</option>
                                    <option value="suspendu" <?php echo $status_filter==='suspendu'?'selected':''; ?>>Suspendu</option>
                                </select>
                            </div>
                            <div class="form-group-modern">
                                <button type="submit" class="btn btn-primary">Filtrer</button>
                            </div>
                        </form>
                    </div>

                    <div class="admin-section-modern">
                        <h3 style="margin-bottom: var(--space-md);">Ajouter un adhérent</h3>
                        <form method="POST" class="form-row-modern" style="gap: var(--space-md); align-items:flex-end;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="add_member">
                            <div class="form-group-modern" style="flex: 1;">
                                <label class="form-label-modern" for="user_id">Utilisateur éligible</label>
                                <select id="user_id" name="user_id" class="form-input-modern form-select-modern" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($eligible_users as $u): ?>
                                        <option value="<?php echo (int)$u['IdUtilisateur']; ?>">
                                            <?php echo htmlspecialchars(($u['Nom'] ?? '') . ' ' . ($u['Prenom'] ?? '') . ' — ' . ($u['Email'] ?? '') . ' (' . ($u['Role'] ?? '') . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-modern">
                                <button type="submit" class="btn btn-primary">Ajouter</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-modern">
                        <div class="table-header-modern">
                            <h2 class="table-title-modern">Adhérents</h2>
                        </div>

                        <?php if (empty($members)): ?>
                            <div class="empty-state-modern">
                                <div class="empty-state-icon-modern">📭</div>
                                <h3>Aucun adhérent trouvé</h3>
                                <p>Aucun résultat pour les filtres appliqués.</p>
                            </div>
                        <?php else: ?>
                            <div class="responsive-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Membre</th>
                                            <th>Email</th>
                                            <th>Statut</th>
                                            <th>Date d'adhésion</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $idx => $m): ?>
                                            <?php $status = strtolower(trim((string)($m['Status'] ?? ''))); ?>
                                            <tr>
                                                <td><?php echo (int)($idx + 1); ?></td>
                                                <td><?php echo htmlspecialchars(trim(($m['Prenom'] ?? '') . ' ' . ($m['Nom'] ?? ''))); ?></td>
                                                <td><?php echo htmlspecialchars($m['Email'] ?? ''); ?></td>
                                                <td>
                                                    <?php if ($status === 'actif'): ?>
                                                        <span class="badge badge-success">Actif</span>
                                                    <?php elseif ($status === 'suspendu'): ?>
                                                        <span class="badge badge-warning">Suspendu</span>
                                                    <?php else: ?>
                                                        <span class="badge">Inactif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo !empty($m['DateAdhesion']) ? htmlspecialchars(date('d/m/Y', strtotime($m['DateAdhesion']))) : '-'; ?></td>
                                                <td>
                                                        <div style="display:flex; gap:6px;">
                                                            <form method="POST" style="display:inline-block;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="adhesion_id" value="<?php echo (int)$m['IdAdhesion']; ?>">
                                                                <?php if ($status === 'actif'): ?>
                                                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--error); border-color: var(--error);">Désactiver</button>
                                                                <?php else: ?>
                                                                    <button type="submit" class="btn btn-primary btn-sm">Activer</button>
                                                                <?php endif; ?>
                                                            </form>

                                                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Voulez-vous vraiment supprimer cette adhésion ? Cette action est irréversible.');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="delete_member">
                                                                <input type="hidden" name="adhesion_id" value="<?php echo (int)$m['IdAdhesion']; ?>">
                                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--error); border-color: var(--error);">Supprimer</button>
                                                            </form>
                                                        </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>