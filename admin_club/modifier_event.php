<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole(['organisateur']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$error = '';
$success = '';

// Récupérer l'événement à modifier
if ($event_id > 0) {
    $query = "SELECT e.*, c.NomClub FROM Evenement e 
              JOIN Club c ON e.IdClub = c.IdClub 
              WHERE e.IdEvenement = :event_id AND c.IdAdminClub = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':event_id', $event_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $error = "Événement introuvable ou vous n'avez pas les droits pour le modifier.";
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $event) {
    $nom_evenement = trim($_POST['nom_evenement']);
    $type = trim($_POST['type']);
    $lieu = trim($_POST['lieu']);
    $date = $_POST['date'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $participant = trim($_POST['participant']);
    $capacite_max = !empty($_POST['capacite_max']) ? (int)$_POST['capacite_max'] : null;
    $prix_adherent = isset($_POST['prix_adherent']) && $_POST['prix_adherent'] !== '' ? (float)str_replace(',', '.', $_POST['prix_adherent']) : 0;
    $prix_non_adherent = isset($_POST['prix_non_adherent']) && $_POST['prix_non_adherent'] !== '' ? (float)str_replace(',', '.', $_POST['prix_non_adherent']) : 0;
    $prix_externe = isset($_POST['prix_externe']) && $_POST['prix_externe'] !== '' ? (float)str_replace(',', '.', $_POST['prix_externe']) : 0;
    $description = trim($_POST['description'] ?? '');

    // Validation
    if (empty($nom_evenement) || empty($type) || empty($lieu) || empty($date) || empty($heure_debut) || empty($heure_fin)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($heure_fin <= $heure_debut) {
        $error = "L'heure de fin doit être après l'heure de début.";
    } else {
        // Mise à jour de l'événement
        $update_query = "UPDATE Evenement SET 
                        NomEvenement = :nom_evenement,
                        TypeEvenement = :type,
                        Lieu = :lieu,
                        Date = :date,
                        HeureDebut = :heure_debut,
                        HeureFin = :heure_fin,
                        TypeParticipant = :participant,
                        CapaciteMax = :capacite_max,
                        PrixAdherent = :prix_adherent,
                        PrixNonAdherent = :prix_non_adherent,
                        PrixExterne = :prix_externe,
                        description = :description
                        WHERE IdEvenement = :event_id";

        $stmt = $db->prepare($update_query);
        $stmt->bindParam(':nom_evenement', $nom_evenement);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':lieu', $lieu);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':heure_debut', $heure_debut);
        $stmt->bindParam(':heure_fin', $heure_fin);
        $stmt->bindParam(':participant', $participant);
        $stmt->bindParam(':capacite_max', $capacite_max);
        $stmt->bindParam(':prix_adherent', $prix_adherent);
        $stmt->bindParam(':prix_non_adherent', $prix_non_adherent);
        $stmt->bindParam(':prix_externe', $prix_externe);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':event_id', $event_id);

        try {
            $stmt->execute();
            $success = "Événement modifié avec succès !";
            // Recharger les données de l'événement
            $query = "SELECT e.*, c.NomClub FROM Evenement e 
                      JOIN Club c ON e.IdClub = c.IdClub 
                      WHERE e.IdEvenement = :event_id AND c.IdAdminClub = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':event_id', $event_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'événement - Event Manager</title>
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
                    <span><?php echo htmlspecialchars($event['NomClub'] ?? 'Mon Club'); ?></span>
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
            <aside class="sidebar-modern">
                <nav class="sidebar-nav-modern">
                    <div class="sidebar-section-modern">
                        <div class="sidebar-title-modern">Gestion</div>
                        <ul class="sidebar-nav-modern">
                            <li class="sidebar-nav-item-modern">
                                <a href="dashboard.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">📊</div>
                                    Tableau de bord
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="creer_event.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">➕</div>
                                    Créer un événement
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="gerer_event.php" class="sidebar-nav-link-modern active">
                                    <div class="sidebar-nav-icon-modern">📅</div>
                                    Gérer les événements
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="membres.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">👥</div>
                                    Membres
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="recap_evenements.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">📈</div>
                                    Récapitulatif
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="sidebar-section-modern">
                        <div class="sidebar-title-modern">Personnel</div>
                        <ul class="sidebar-nav-modern">
                            <li class="sidebar-nav-item-modern">
                                <a href="../utilisateur/mes_inscriptions.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">📋</div>
                                    Mes inscriptions
                                </a>
                            </li>
                            <li class="sidebar-nav-item-modern">
                                <a href="../utilisateur/parametres.php" class="sidebar-nav-link-modern">
                                    <div class="sidebar-nav-icon-modern">⚙️</div>
                                    Paramètres
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </aside>

            <main class="main-content">
                <div class="page-title">
                    <h1>Modifier l'événement</h1>
                    <p>Modifiez les informations de votre événement</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert-modern alert-error-modern">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert-modern alert-success-modern">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($event): ?>
                    <form method="POST" class="form-modern">
                        <!-- Section Informations générales -->
                        <div class="form-section-modern">
                            <h3 class="form-section-title-modern">Informations générales</h3>
                            
                            <div class="form-group-modern">
                                <label for="nom_evenement" class="form-label-modern">Nom de l'événement *</label>
                                <input type="text" id="nom_evenement" name="nom_evenement" class="form-input-modern"
                                       value="<?php echo htmlspecialchars($event['NomEvenement']); ?>" required>
                            </div>

                            <div class="form-row-modern">
                                <div class="form-group-modern">
                                    <label for="type" class="form-label-modern">Type d'événement *</label>
                                    <input type="text" id="type" name="type" class="form-input-modern"
                                           value="<?php echo htmlspecialchars($event['TypeEvenement']); ?>" required>
                                </div>

                                <div class="form-group-modern">
                                    <label for="lieu" class="form-label-modern">Lieu *</label>
                                    <input type="text" id="lieu" name="lieu" class="form-input-modern"
                                           value="<?php echo htmlspecialchars($event['Lieu']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Section Date et heure -->
                        <div class="form-section-modern">
                            <h3 class="form-section-title-modern">Date et heure</h3>
                            
                            <div class="form-group-modern">
                                <label for="date" class="form-label-modern">Date *</label>
                                <input type="date" id="date" name="date" class="form-input-modern"
                                       value="<?php echo $event['Date']; ?>" required>
                            </div>

                            <div class="form-row-modern">
                                <div class="form-group-modern">
                                    <label for="heure_debut" class="form-label-modern">Heure de début *</label>
                                    <input type="time" id="heure_debut" name="heure_debut" class="form-input-modern"
                                           value="<?php echo substr($event['HeureDebut'], 0, 5); ?>" required>
                                </div>

                                <div class="form-group-modern">
                                    <label for="heure_fin" class="form-label-modern">Heure de fin *</label>
                                    <input type="time" id="heure_fin" name="heure_fin" class="form-input-modern"
                                           value="<?php echo substr($event['HeureFin'], 0, 5); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Section Participants et prix -->
                        <div class="form-section-modern">
                            <h3 class="form-section-title-modern">Participants et prix</h3>
                            
                            <div class="form-group-modern">
                                <label for="participant" class="form-label-modern">Type de participant *</label>
                                <select id="participant" name="participant" class="form-input-modern form-select-modern" required onchange="togglePriceFields()">
                                    <option value="Tous" <?php echo $event['TypeParticipant'] == 'Tous' ? 'selected' : ''; ?>>Tous</option>
                                    <option value="Adhérents" <?php echo $event['TypeParticipant'] == 'Adhérents' ? 'selected' : ''; ?>>Adhérents uniquement</option>
                                    <option value="Membres uniquement" <?php echo $event['TypeParticipant'] == 'Membres uniquement' ? 'selected' : ''; ?>>Membres uniquement</option>
                                </select>
                            </div>

                            <div class="form-group-modern">
                                <label for="capacite_max" class="form-label-modern">Capacité maximale</label>
                                <input type="number" id="capacite_max" name="capacite_max" class="form-input-modern"
                                       value="<?php echo $event['CapaciteMax']; ?>" min="1">
                            </div>

                            <div class="admin-section-modern" id="price-fields">
                                <h4 style="margin-bottom: var(--space-lg); color: var(--neutral-700);">Prix selon le type de participant</h4>
                                
                                <div class="form-row-modern">
                                    <div class="form-group-modern">
                                        <label for="prix_adherent" class="form-label-modern">Prix adhérent (€)</label>
                                        <input type="number" step="0.01" min="0" id="prix_adherent" name="prix_adherent" class="form-input-modern"
                                               value="<?php echo $event['PrixAdherent']; ?>">
                                    </div>
                                    <div class="form-group-modern">
                                        <label for="prix_non_adherent" class="form-label-modern">Prix non-adhérent (€)</label>
                                        <input type="number" step="0.01" min="0" id="prix_non_adherent" name="prix_non_adherent" class="form-input-modern"
                                               value="<?php echo $event['PrixNonAdherent']; ?>">
                                    </div>
                                    <div class="form-group-modern">
                                        <label for="prix_externe" class="form-label-modern">Prix externe (€)</label>
                                        <input type="number" step="0.01" min="0" id="prix_externe" name="prix_externe" class="form-input-modern"
                                               value="<?php echo $event['PrixExterne']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section Description -->
                        <div class="form-section-modern">
                            <h3 class="form-section-title-modern">Description</h3>
                            
                            <div class="form-group-modern">
                                <label for="description" class="form-label-modern">Description de l'événement</label>
                                <textarea id="description" name="description" class="form-input-modern form-textarea-modern" rows="4"><?php echo htmlspecialchars($event['description']); ?></textarea>
                            </div>
                        </div>

                        <div class="form-actions-modern">
                            <button type="submit" class="btn btn-primary btn-lg">Modifier l'événement</button>
                            <a href="dashboard.php" class="btn btn-outline btn-lg">Annuler</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty-state-modern">
                        <div class="empty-state-icon-modern">❌</div>
                        <h3>Événement introuvable</h3>
                        <p>L'événement que vous cherchez n'existe pas ou vous n'avez pas les droits pour le modifier.</p>
                        <a href="dashboard.php" class="btn btn-primary">Retour au dashboard</a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function togglePriceFields() {
            const participant = document.getElementById('participant').value;
            const priceFields = document.getElementById('price-fields');
            const prixAdherent = document.getElementById('prix_adherent');
            const prixNonAdherent = document.getElementById('prix_non_adherent');
            const prixExterne = document.getElementById('prix_externe');

            if (participant === 'Adhérents') {
                priceFields.style.display = 'block';
                prixAdherent.parentElement.style.display = 'block';
                prixNonAdherent.parentElement.style.display = 'none';
                prixExterne.parentElement.style.display = 'none';
            } else if (participant === 'Membres uniquement') {
                priceFields.style.display = 'block';
                prixAdherent.parentElement.style.display = 'block';
                prixNonAdherent.parentElement.style.display = 'block';
                prixExterne.parentElement.style.display = 'none';
            } else if (participant === 'Tous') {
                priceFields.style.display = 'block';
                prixAdherent.parentElement.style.display = 'block';
                prixNonAdherent.parentElement.style.display = 'block';
                prixExterne.parentElement.style.display = 'block';
            } else {
                priceFields.style.display = 'none';
            }
        }

        // Initialiser l'affichage des prix
        document.addEventListener('DOMContentLoaded', function() {
            togglePriceFields();
        });
    </script>
</body>
</html>
