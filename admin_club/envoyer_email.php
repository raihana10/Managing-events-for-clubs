<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les informations du club
$club_query = "SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les événements du club
$events_query = "SELECT IdEvenement, NomEvenement, Date FROM Evenement WHERE IdClub = :club_id ORDER BY Date DESC";
$stmt = $db->prepare($events_query);
$stmt->bindParam(':club_id', $club['IdClub']);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error_message = '';
$success_message = '';

// Traitement du formulaire d'envoi d'email
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_id = (int)$_POST['event_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error_message = "Veuillez remplir tous les champs.";
    } else {
        // Récupérer les participants de l'événement
        $participants_query = "SELECT DISTINCT u.Email, u.Prenom, u.Nom 
                              FROM Inscription i 
                              JOIN Utilisateur u ON i.IdParticipant = u.IdUtilisateur 
                              WHERE i.IdEvenement = :event_id";
        $stmt = $db->prepare($participants_query);
        $stmt->bindParam(':event_id', $event_id);
        $stmt->execute();
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($participants)) {
            $error_message = "Aucun participant trouvé pour cet événement.";
        } else {
            // Simuler l'envoi d'emails (dans un vrai projet, utiliser une bibliothèque d'email)
            $emails_sent = 0;
            foreach ($participants as $participant) {
                // Ici, vous intégreriez votre système d'envoi d'email
                // mail($participant['Email'], $subject, $message);
                $emails_sent++;
            }
            
            $success_message = "Email envoyé avec succès à {$emails_sent} participant(s).";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer un email - Event Manager</title>
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
                                <a href="gerer_event.php" class="sidebar-nav-link-modern">
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
                        <div class="sidebar-title-modern">Communication</div>
                        <ul class="sidebar-nav-modern">
                            <li class="sidebar-nav-item-modern">
                                <a href="envoyer_email.php" class="sidebar-nav-link-modern active">
                                    <div class="sidebar-nav-icon-modern">📧</div>
                                    Envoyer un email
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
                    <h1>Envoyer un email</h1>
                    <p>Communiquez avec les participants de vos événements</p>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert-modern alert-error-modern">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert-modern alert-success-modern">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="form-modern">
                    <!-- Section Sélection de l'événement -->
                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">Sélectionner l'événement</h3>
                        
                        <div class="form-group-modern">
                            <label for="event_id" class="form-label-modern">Événement *</label>
                            <select id="event_id" name="event_id" class="form-input-modern form-select-modern" required>
                                <option value="">Sélectionnez un événement</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['IdEvenement']; ?>">
                                        <?php echo htmlspecialchars($event['NomEvenement']); ?> 
                                        (<?php echo date('d/m/Y', strtotime($event['Date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Section Contenu de l'email -->
                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">Contenu de l'email</h3>
                        
                        <div class="form-group-modern">
                            <label for="subject" class="form-label-modern">Sujet *</label>
                            <input type="text" id="subject" name="subject" class="form-input-modern" 
                                   placeholder="Ex: Rappel pour l'événement de demain" required>
                        </div>

                        <div class="form-group-modern">
                            <label for="message" class="form-label-modern">Message *</label>
                            <textarea id="message" name="message" class="form-input-modern form-textarea-modern" 
                                      rows="8" placeholder="Rédigez votre message ici..." required></textarea>
                        </div>
                    </div>

                    <!-- Section Aperçu -->
                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">Aperçu</h3>
                        <div class="admin-section-modern">
                            <div class="email-preview-modern">
                                <div class="email-preview-header-modern">
                                    <div class="email-preview-subject-modern" id="preview-subject">Sujet de l'email</div>
                                    <div class="email-preview-meta-modern">
                                        <span>De: <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?> (<?php echo htmlspecialchars($club['NomClub']); ?>)</span>
                                        <span>À: Participants de l'événement sélectionné</span>
                                    </div>
                                </div>
                                <div class="email-preview-content-modern" id="preview-message">
                                    Le contenu de votre message apparaîtra ici...
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-modern">
                        <button type="submit" class="btn btn-primary btn-lg">Envoyer l'email</button>
                        <a href="dashboard.php" class="btn btn-outline btn-lg">Annuler</a>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Mise à jour de l'aperçu en temps réel
        document.getElementById('subject').addEventListener('input', function() {
            document.getElementById('preview-subject').textContent = this.value || 'Sujet de l\'email';
        });

        document.getElementById('message').addEventListener('input', function() {
            document.getElementById('preview-message').textContent = this.value || 'Le contenu de votre message apparaîtra ici...';
        });

        // Mise à jour du nombre de participants
        document.getElementById('event_id').addEventListener('change', function() {
            if (this.value) {
                // Ici, vous pourriez faire un appel AJAX pour récupérer le nombre de participants
                // Pour l'instant, on affiche juste un message générique
                console.log('Événement sélectionné:', this.value);
            }
        });
    </script>
</body>
</html>
