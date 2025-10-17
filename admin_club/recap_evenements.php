<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);

// VÉRIFIER si des données d'événement sont en session
if (!isset($_SESSION['event_preview'])) {
    // Si pas de données, retourner au formulaire
    header("Location: creer_event.php");
    exit();
}

// RÉCUPÉRER les données de la session
$event_data = $_SESSION['event_preview'];
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur connecté
$user_query = "SELECT Email, Nom, Prenom FROM Utilisateur WHERE IdUtilisateur = :user_id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer le nom du club pour l'affichage
$club_query = "SELECT NomClub FROM Club WHERE IdClub = :id_club";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':id_club', $event_data['id_club']);
$stmt->execute();
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les emails des super administrateurs
$super_admin = "SELECT Email FROM Utilisateur WHERE Role = 'administrateur'";
$stmt = $db->prepare($super_admin);
$stmt->execute();
$super_admin_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

$club_nom = 'Club inconnu';
if ($club && isset($club['NomClub'])) {
    $club_nom = $club['NomClub'];
}

// TRAITEMENT de la confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Vérifier le token CSRF pour la sécurité
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // INSÉRER l'événement dans la base de données
        $insert_query = "INSERT INTO Evenement (
            IdClub, NomEvenement, HeureDebut, HeureFin, Date, Lieu,
            TypeEvenement, TypeParticipant, CapaciteMax, Affiche, 
            PrixAdherent, PrixNonAdherent, PrixExterne, description, Etat
        ) VALUES (
            :id_club, :nom_evenement, :heure_debut, :heure_fin, :date, :lieu,
            :type_evenement, :participant, :capacite_max, :affiche, 
            :prix_adherent, :prix_non_adherent, :prix_externe, :description, :etat
        )";

        $stmt = $db->prepare($insert_query);
        // Lier tous les paramètres avec les données de la session
        $stmt->bindParam(':id_club', $event_data['id_club']);
        $stmt->bindParam(':nom_evenement', $event_data['nom_evenement']);
        $stmt->bindParam(':heure_debut', $event_data['heure_debut']);
        $stmt->bindParam(':heure_fin', $event_data['heure_fin']);
        $stmt->bindParam(':date', $event_data['date']);
        $stmt->bindParam(':lieu', $event_data['lieu']);
        $stmt->bindParam(':type_evenement', $event_data['type']);
        $stmt->bindParam(':participant', $event_data['participant']);
        $stmt->bindParam(':capacite_max', $event_data['capacite_max']);
        $affiche_db_value = null;
        if (!empty($event_data['affiche'])) {
            if (is_array($event_data['affiche'])) {
                $affiche_db_value = $event_data['affiche']['web'] ?? null;
            } else {
                $affiche_db_value = $event_data['affiche'];
            }
        }
        $stmt->bindParam(':affiche', $affiche_db_value);
        $stmt->bindParam(':prix_adherent', $event_data['prix_adherent']);
        $stmt->bindParam(':prix_non_adherent', $event_data['prix_non_adherent']);
        $stmt->bindParam(':prix_externe', $event_data['prix_externe']);
        $stmt->bindParam(':description', $event_data['description']);
        $stmt->bindParam(':etat', $event_data['etat']);

        if ($stmt->execute()) {
            // Récupérer l'ID de l'événement créé
            $event_id = $db->lastInsertId();
            
            // ENVOI DE L'EMAIL aux administrateurs
            if (!empty($super_admin_emails) && !empty($current_user)) {
                // Charger PHPMailer (adapter le chemin selon votre structure)
                require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
                require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
                require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';

                try {
                    $mail = new PHPMailer(true);
                    
                    // Configuration SMTP (À ADAPTER selon votre serveur)
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'cratech.tech@gmail.com'; 
                    $mail->Password = "swtp vyuq invp vfro";
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';

                    // Expéditeur : l'utilisateur connecté
                    $expediteur_nom = $current_user['Nom'] . ' ' . $current_user['Prenom'];
                    $mail->setFrom($current_user['Email'], $expediteur_nom);
                    $mail->addReplyTo($current_user['Email'], $expediteur_nom);

                    // Destinataires : tous les administrateurs
                    foreach ($super_admin_emails as $admin_email) {
                        if (filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                            $mail->addAddress($admin_email);
                        }
                    }

                    // Préparer les liens d'approbation/rejet
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $base_path = dirname($_SERVER['PHP_SELF']);
                    $base_url = $scheme . '://' . $host . $base_path . '/';
                    
                    $approve_link = $base_url . 'gerer_event.php?action=approve&event_id=' . $event_id;
                    $reject_link = $base_url . 'gerer_event.php?action=reject&event_id=' . $event_id;

                    $mail->isHTML(false);
                        $mail->Subject = 'Validation - Nouvel événement : ' . $event_data['nom_evenement'];

                        $message = "Bonjour,\n\n";
                        $message .= "Un nouvel événement a été soumis et nécessite validation.\n\n";

                        $message .= "Titre : {$event_data['nom_evenement']}\n";
                        $message .= "Club : {$club_nom}\n";
                        $message .= "Date : " . date('d/m/Y', strtotime($event_data['date'])) . "\n";
                        $message .= "Heure : " . date('H:i', strtotime($event_data['heure_debut'])) . " - " . date('H:i', strtotime($event_data['heure_fin'])) . "\n";
                        $message .= "Lieu : {$event_data['lieu']}\n";

                        if (!empty($event_data['capacite_max'])) {
                            $message .= "Capacité : {$event_data['capacite_max']} personnes\n";
                        }

                        $message .= "Créé par : {$expediteur_nom} ({$current_user['Email']})\n";

                        if (!empty($event_data['description'])) {
                            $message .= "\nDescription :\n{$event_data['description']}\n";
                        }

                        $message .= "\nActions :\n";
                        $message .= "- Approuver : $approve_link\n";
                        $message .= "- Rejeter   : $reject_link\n\n";

                        $message .= "Merci.\n";

                        $mail->Body = $message;


                    // ENVOYER l'email
                    $mail->send();
                    
                    $success_message = "Événement créé avec succès ! Les administrateurs ont été notifiés par email.";
                    
                } catch (Exception $e) {
                    // Log l'erreur mais continue le processus
                    error_log("Erreur d'envoi d'email: " . $mail->ErrorInfo);
                    $success_message = "Événement créé avec succès ! (Impossible d'envoyer la notification par email)";
                }
            } else {
                $success_message = "Événement créé avec succès !";
            }
            
            // SUCCÈS : nettoyer la session et rediriger
            unset($_SESSION['event_preview']);
            unset($_SESSION['csrf_token']);
            
            $_SESSION['success_message'] = $success_message;
            header("Location: mes_evenements.php");
            exit();
        } else {
            $error = "Erreur lors de l'enregistrement de l'événement.";
        }
    }
}

// GÉNÉRER un token CSRF pour la sécurité
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// FORMATER les dates pour l'affichage
$date_formatted = date('d/m/Y', strtotime($event_data['date']));
$heure_debut_formatted = date('H:i', strtotime($event_data['heure_debut']));
$heure_fin_formatted = date('H:i', strtotime($event_data['heure_fin']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récapitulatif de l'Événement - Event Manager</title>
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
                    <a href="dashboard.php" class="btn btn-secondary">← Retour</a>
                    <div class="user-avatar-modern">
                        <?php echo strtoupper(substr($_SESSION['prenom'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="page-title">
                <div>
                    <h1>Récapitulatif de l'événement</h1>
                    <p>Vérifiez les informations avant de confirmer la création</p>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert-error-modern">
                    <strong>Erreur :</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card mb-lg">
                <div class="card-body text-center">
                    <h2 class="text-secondary"><?php echo htmlspecialchars($event_data['nom_evenement'] ?? ''); ?></h2>
                    <p>Organisé par <strong><?php echo htmlspecialchars($club['NomClub'] ?? 'Club inconnu'); ?></strong></p>
                </div>
            </div>

            <div class="form-section-modern">
                <h3 class="form-section-title-modern">Informations générales</h3>
                <div class="grid grid-cols-2 gap-lg">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Club organisateur</div>
                            <div class="text-base font-medium"><?php echo htmlspecialchars($club['NomClub'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Type d'événement</div>
                            <div class="text-base font-medium">
                                <span class="badge badge-info"><?php echo htmlspecialchars($event_data['type'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section-modern">
                <h3 class="form-section-title-modern">Date, heure et lieu</h3>
                <div class="grid grid-cols-2 gap-lg">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Date</div>
                            <div class="text-base font-medium"><?php echo $date_formatted; ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Lieu</div>
                            <div class="text-base font-medium"><?php echo htmlspecialchars($event_data['lieu'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Heure de début</div>
                            <div class="text-base font-medium"><?php echo $heure_debut_formatted; ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Heure de fin</div>
                            <div class="text-base font-medium"><?php echo $heure_fin_formatted; ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Participants</div>
                            <div class="text-base font-medium"><?php echo htmlspecialchars($event_data['participant'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="text-xs font-semibold text-secondary">Capacité maximale</div>
                            <div class="text-base font-medium">
                                <?php 
                                echo !empty($event_data['capacite_max'])
                                    ? htmlspecialchars($event_data['capacite_max']) . ' personnes' 
                                    : '<span class="badge badge-success">Illimitée</span>'; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section-modern">
                <h3 class="form-section-title-modern">Affiche de l'événement</h3>
                <?php
                    $affiche_web = null;
                    $affiche_fs = null;
                    if (!empty($event_data['affiche'])) {
                        if (is_array($event_data['affiche'])) {
                            $affiche_web = $event_data['affiche']['web'] ?? null;
                            $affiche_fs = $event_data['affiche']['fs'] ?? null;
                        } else {
                            // legacy string path
                            $affiche_web = $event_data['affiche'];
                            $affiche_fs = null;
                        }
                    }

                    $affiche_exists = false;
                    if ($affiche_fs && file_exists($affiche_fs)) {
                        $affiche_exists = true;
                    } elseif ($affiche_web) {
                        // try to resolve a filesystem path from web path
                        $docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
                        if ($docroot) {
                            $candidate = realpath($docroot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $affiche_web), DIRECTORY_SEPARATOR));
                            if ($candidate && file_exists($candidate)) {
                                $affiche_exists = true;
                                // update web path to normalized path
                                $affiche_fs = $candidate;
                            }
                        }
                    }
                ?>
                <?php if (!empty($affiche_web)): ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <img src="<?php echo htmlspecialchars($affiche_web); ?>" alt="Affiche de l'événement" class="rounded-lg shadow-md" style="max-width: 100%; height: auto;">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state-modern">
                        <div class="empty-state-icon-modern">📷</div>
                        <h3>Aucune affiche téléchargée</h3>
                        <p>Vous pouvez ajouter une affiche lors de la création de l'événement.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-section-modern">
                <h3 class="form-section-title-modern">État de l'événement</h3>
                <div class="card">
                    <div class="card-body">
                        <div class="text-xs font-semibold text-secondary">État initial</div>
                        <div class="text-base font-medium">
                            <span class="badge badge-warning"><?php echo htmlspecialchars($event_data['etat'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" id="confirmForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-actions">
                    <a href="creer_event.php" class="btn btn-outline">← Modifier</a>
                    <button type="submit" name="confirm" value="1" class="btn btn-primary"> Confirmer l'événement</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
