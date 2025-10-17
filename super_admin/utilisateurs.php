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
    <title>Gestion des utilisateurs - Event Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="header-modern" >
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

    

    <div class="layout" style="margin-left: 0;">
        <main class="main-content" style="margin-left: 0; padding: var(--space-xl);">
            <div class="page-title">
                <h1>Gestion des utilisateurs</h1>
                <a href="dashboard.php" class="btn btn-secondary"> Retour au dashboard</a>
                
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert-modern alert-success-modern">
                    <div class="alert-icon-modern">✅</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Succès</div>
                        <div class="alert-message-modern"><?php echo htmlspecialchars($success_message); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert-modern alert-error-modern">
                    <div class="alert-icon-modern">❌</div>
                    <div class="alert-content-modern">
                        <div class="alert-title-modern">Erreur</div>
                        <div class="alert-message-modern"><?php echo htmlspecialchars($error_message); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des utilisateurs -->
            <?php if (!empty($utilisateurs)): ?>
                <div class="form-section-modern">
                    <h3>Liste des utilisateurs</h3>
                    <div class="table-modern">
                        <div class="table-header-modern">
                            <div class="table-title-modern"><?php echo count($utilisateurs); ?> utilisateur(s) inscrit(s)</div>
                        </div>
                        <div class="table-content-modern">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Rôle</th>
                                        <th>Clubs</th>
                                        <th>Événements</th>
                                        <th>Date d'inscription</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($utilisateurs as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo htmlspecialchars($user['Prenom'] . ' ' . $user['Nom']); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($user['Email']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $role_badge = '';
                                                switch($user['Role']) {
                                                    case 'administrateur':
                                                        $role_badge = '<span class="badge badge-error">Super Admin</span>';
                                                        break;
                                                    case 'organisateur':
                                                        $role_badge = '<span class="badge badge-warning">Organisateur</span>';
                                                        break;
                                                    case 'participant':
                                                        $role_badge = '<span class="badge badge-info">Participant</span>';
                                                        break;
                                                }
                                                echo $role_badge;
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $user['nb_clubs']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success"><?php echo $user['nb_inscriptions']; ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($user['DateInscription'])); ?></td>
                                            <td>
                                                <div class="flex gap-sm">
                                        <button class="btn btn-outline btn-sm" title="Supprimer" 
                                                            onclick="confirmDelete(<?php echo $user['IdUtilisateur']; ?>, '<?php echo htmlspecialchars($user['Prenom'] . ' ' . $user['Nom']); ?>')">
                                                        Supprimer
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-state-icon-modern">👤</div>
                    <h3>Aucun utilisateur</h3>
                    <p>Aucun utilisateur n'est encore inscrit sur la plateforme.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        function confirmDelete(adminId, adminName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'administrateur "${adminName}" ?\n\nCette action est irréversible.`)) {
                document.getElementById('deleteAdminId').value = adminId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
