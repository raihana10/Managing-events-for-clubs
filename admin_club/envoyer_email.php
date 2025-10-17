<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

 $database = new Database();
 $db = $database->getConnection();
try { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (\Exception $e) {}

// Helper: generate a simple attestation PDF using bundled FPDF
function generate_attestation_pdf($db, $userId, $eventId, $destPath) {
    // Load FPDF
    $fpdfPath = __DIR__ . '/../fpdf186/fpdf.php';
    if (!file_exists($fpdfPath)) return false;
    require_once $fpdfPath;

    try {
        // Fetch user and event info
        $uq = $db->prepare('SELECT Nom, Prenom FROM Utilisateur WHERE IdUtilisateur = ? LIMIT 1');
        $uq->execute([(int)$userId]);
        $u = $uq->fetch(PDO::FETCH_ASSOC) ?: [];

        $eq = $db->prepare('SELECT NomEvenement, Date, Lieu FROM Evenement WHERE IdEvenement = ? LIMIT 1');
        $eq->execute([(int)$eventId]);
        $ev = $eq->fetch(PDO::FETCH_ASSOC) ?: [];

        $pdf = new FPDF('P','mm','A4');
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true,20);

        $pdf->SetFont('Arial','B',16);
        $pdf->Cell(0,10,utf8_decode('Attestation de présence'),0,1,'C');
        $pdf->Ln(8);

        $pdf->SetFont('Arial','',12);
        $fullname = trim(($u['Prenom'] ?? '') . ' ' . ($u['Nom'] ?? '')) ?: 'Participant';
        $pdf->MultiCell(0,8,utf8_decode("Nous attestons que : \n
$fullname\n
a participé à l'événement : " . ($ev['NomEvenement'] ?? '')));
        $pdf->Ln(4);

        $dateStr = $ev['Date'] ?? date('Y-m-d');
        $pdf->Cell(0,6,utf8_decode('Date de l\'événement : ' . date('d/m/Y', strtotime($dateStr))),0,1);
        if (!empty($ev['Lieu'])) {
            $pdf->Cell(0,6,utf8_decode('Lieu : ' . $ev['Lieu']),0,1);
        }

        $pdf->Ln(20);
        $pdf->Cell(0,6,utf8_decode('Fait le : ' . date('d/m/Y')),0,1);
        $pdf->Ln(10);
        $pdf->Cell(60,6,utf8_decode('Signature:'),0,0);

        // Ensure destination dir exists
        $dir = dirname($destPath);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

        $pdf->Output('F', $destPath);
        return file_exists($destPath);
    } catch (\Exception $e) {
        return false;
    }
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations du club
$club_query = "SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$club = $stmt->fetch(PDO::FETCH_ASSOC);
$club_id = $club['IdClub'] ?? null;

// Récupérer les événements du club
$events = [];
if ($club_id) {
    $events_query = "SELECT IdEvenement, NomEvenement, Date FROM Evenement WHERE IdClub = :club_id ORDER BY Date DESC";
    $stmt = $db->prepare($events_query);
    $stmt->bindParam(':club_id', $club_id);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer la liste des adhérents (actifs) du club pour envoi personnalisé
$adherents = [];
if ($club_id) {
    $adh_query = "SELECT u.IdUtilisateur, u.Nom, u.Prenom, u.Email
                  FROM Adhesion a
                  JOIN Utilisateur u ON u.IdUtilisateur = a.IdParticipant
                  WHERE a.IdClub = :cid AND a.Status = 'actif'
                  ORDER BY u.Nom, u.Prenom";
    $stmt = $db->prepare($adh_query);
    $stmt->bindParam(':cid', $club_id, PDO::PARAM_INT);
    $stmt->execute();
    $adherents = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ensure persistent email history table exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS email (
    IdEmail INT AUTO_INCREMENT PRIMARY KEY,
    IdEvenement INT DEFAULT NULL,
    DateEnvoie DATETIME NOT NULL,
    Objet VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
try { $db->exec($create_table_sql); } catch (\Exception $e) {}

// Historique des emails envoyés: read from DB
$email_logs = [];
if ($club_id) {
    try {
        $hist_q = $db->prepare("SELECT e.IdEmail, e.IdEvenement, e.DateEnvoie, e.Objet
                                FROM email e
                                JOIN Evenement ev ON ev.IdEvenement = e.IdEvenement
                                WHERE ev.IdClub = :cid
                                ORDER BY e.DateEnvoie DESC
                                LIMIT 200");
        $hist_q->bindParam(':cid', $club_id, PDO::PARAM_INT);
        $hist_q->execute();
        $email_logs = $hist_q->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $ex) {
        // fallback: empty logs
        $email_logs = [];
    }
}

$error_message = '';
$success_message = '';

// Utiliser PHPMailer pour l'envoi réel
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';

// Charger l'email de l'utilisateur connecté
$current_user = null;
try {
    $uq = $db->prepare("SELECT Email, Nom, Prenom FROM Utilisateur WHERE IdUtilisateur = :uid");
    $uq->bindParam(':uid', $user_id, PDO::PARAM_INT);
    $uq->execute();
    $current_user = $uq->fetch(PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Traitement du formulaire d'envoi d'email
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $club_id) {
    $send_type = $_POST['send_type'] ?? 'event'; // 'event' ou 'members'
    $event_id = (int)($_POST['event_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $send_attestations = isset($_POST['send_attestations']) && $_POST['send_attestations'] == '1';

    // Pièce jointe (optionnelle)
    $attachment_web = null;
    $attachment_fs = null;
    $attachment_name = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['attachment']['tmp_name'];
            $file_name = basename($_FILES['attachment']['name']);
            $file_size = (int)$_FILES['attachment']['size'];
            $mime = mime_content_type($file_tmp);
            $allowed = [
                'image/png', 'image/jpeg', 'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/x-zip-compressed'
            ];
            if (!in_array($mime, $allowed)) {
                $error_message = "Type de fichier non autorisé (images, PDF, DOC/DOCX, ZIP).";
            } elseif ($file_size > 10 * 1024 * 1024) {
                $error_message = "La pièce jointe dépasse 10MB.";
            } else {
                $upload_dir_fs = realpath(__DIR__ . '/../uploads/emails');
                if ($upload_dir_fs === false) {
                    $upload_dir_fs = __DIR__ . '/../uploads/emails';
                }
                if (!is_dir($upload_dir_fs)) { @mkdir($upload_dir_fs, 0755, true); }
                $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                $new_file = uniqid('attach_') . '_' . $safe_name;
                $dest_fs = rtrim($upload_dir_fs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $new_file;
                if (move_uploaded_file($file_tmp, $dest_fs)) {
                    $attachment_fs = $dest_fs;
                    $attachment_web = '../uploads/emails/' . $new_file;
                    $attachment_name = $file_name;
                } else {
                    $error_message = "Échec de l'upload de la pièce jointe.";
                }
            }
        } else {
            $error_message = "Erreur d'upload de la pièce jointe.";
        }
    }

    if (empty($subject) || empty($message)) {
        $error_message = $error_message ?: "Veuillez remplir tous les champs obligatoires.";
    }

    $recipients = [];
    $context_label = '';
    $context_event = null;

    if (!$error_message) {
                if ($send_type === 'event') {
            if (!$event_id) {
                $error_message = "Veuillez sélectionner un événement.";
            } else {
                $evt_stmt = $db->prepare("SELECT IdEvenement, NomEvenement, Date FROM Evenement WHERE IdEvenement = :id AND IdClub = :cid LIMIT 1");
                $evt_stmt->bindParam(':id', $event_id, PDO::PARAM_INT);
                $evt_stmt->bindParam(':cid', $club_id, PDO::PARAM_INT);
                $evt_stmt->execute();
                $context_event = $evt_stmt->fetch(PDO::FETCH_ASSOC);
                if (!$context_event) {
                    $error_message = "Événement introuvable.";
                } else {
                    $participants_query = "SELECT DISTINCT u.Email, u.Prenom, u.Nom, u.IdUtilisateur 
                                           FROM Inscription i 
                                           JOIN Utilisateur u ON i.IdUtilisateur = u.IdUtilisateur 
                                           WHERE i.IdEvenement = :event_id";
                    $stmt = $db->prepare($participants_query);
                    $stmt->bindParam(':event_id', $event_id);
                    $stmt->execute();
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $context_label = ($context_event['NomEvenement'] ?? 'Événement') . ' (' . date('d/m/Y', strtotime($context_event['Date'] ?? date('Y-m-d'))) . ')';
                }
            }
        } else {
            $ids = $_POST['recipient_ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                $error_message = "Veuillez sélectionner au moins un adhérent.";
            } else {
                $ids_int = array_map('intval', $ids);
                $placeholders = implode(',', array_fill(0, count($ids_int), '?'));
                $sql = "SELECT DISTINCT u.Email, u.Prenom, u.Nom
                        FROM Utilisateur u
                        JOIN Adhesion a ON a.IdParticipant = u.IdUtilisateur
                        WHERE a.IdClub = ? AND a.Status = 'actif' AND u.IdUtilisateur IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $bind_index = 1;
                $stmt->bindValue($bind_index++, $club_id, PDO::PARAM_INT);
                foreach ($ids_int as $idv) { $stmt->bindValue($bind_index++, $idv, PDO::PARAM_INT); }
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $context_label = 'Sélection d\'adhérents (' . count($recipients) . ')';
            }
        }
    }

    if (!$error_message) {
        if (empty($recipients)) {
            $error_message = "Aucun destinataire trouvé.";
        } else {
                // Configurer PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mohito.raihana@gmail.com';
                $mail->Password = 'pqie uzik iuym wsgl';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';

                $senderEmail = $current_user['Email'] ?? 'mohito.raihana@gmail.com';
                $senderName = ($current_user['Prenom'] ?? '') . ' ' . ($current_user['Nom'] ?? '');
                $senderName = trim($senderName) ?: 'Organisateur';

                $mail->setFrom($senderEmail, $senderName);
                $mail->addReplyTo($senderEmail, $senderName);

                // For event sends, optionally generate an attestation for each participant
                $sentCount = 0;
                foreach ($recipients as $r) {
                    $name = trim(($r['Prenom'] ?? '') . ' ' . ($r['Nom'] ?? ''));
                    $mail->clearAddresses();
                    $mail->clearAttachments();
                    $mail->addAddress($r['Email'], $name ?: $r['Email']);

                    // Attach uploaded attachment if any
                    if ($attachment_fs && file_exists($attachment_fs)) {
                        $mail->addAttachment($attachment_fs, $attachment_name ?: basename($attachment_fs));
                    }

                    // If attestations requested and send_type is event, generate per-user attestation
                    $generated_attestation = null;
                    if ($send_attestations && $send_type === 'event') {
                        // Assume ettettaion.php can generate and save a PDF when called with GET params user_id & event_id
                        // We'll call it via output buffering and save to file, or request it if available locally.
                        $att_dir = realpath(__DIR__ . '/../uploads/emails');
                        if ($att_dir === false) { $att_dir = __DIR__ . '/../uploads/emails'; }
                        if (!is_dir($att_dir)) { @mkdir($att_dir, 0755, true); }
                        $att_file = rtrim($att_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'attestation_' . $event_id . '_' . (int)$r['IdUtilisateur'] . '_' . uniqid() . '.pdf';

                        // Build URL to local script
                        $ett_url = __DIR__ . '/ettettaion.php?user_id=' . (int)$r['IdUtilisateur'] . '&event_id=' . (int)$event_id . '&output=save&dest=' . urlencode($att_file);
                        // If ettettaion.php expects to be included, try to include and let it write the file when output=save
                        try {
                            // Provide expected GET params
                            $_GET['user_id'] = (int)$r['IdUtilisateur'];
                            $_GET['event_id'] = (int)$event_id;
                            $_GET['output'] = 'save';
                            $_GET['dest'] = $att_file;
                            // Capture output to avoid breaking page
                            ob_start();
                            include __DIR__ . '/ettettaion.php';
                            ob_end_clean();
                            if (file_exists($att_file)) {
                                $generated_attestation = $att_file;
                                $mail->addAttachment($generated_attestation, basename($generated_attestation));
                            }
                        } catch (\Exception $ie) {
                            // If generation fails, continue without attachment
                        }
                    }

                    $mail->isHTML(false); // envoyer en texte brut (adapter si nécessaire)
                    $mail->Subject = $subject;
                    // Personalize message maybe
                    $personal_message = str_replace(['{prenom}', '{nom}', '{evenement}'], [($r['Prenom'] ?? ''), ($r['Nom'] ?? ''), ($context_event['NomEvenement'] ?? '')], $message);
                    $mail->Body = $personal_message;

                    if ($mail->send()) {
                        $sentCount++;
                    }

                    // Cleanup generated attestation file if desired (keep for records) - we'll keep it so attachments links work
                }

                $success_message = 'Email envoyé avec succès à ' . $sentCount . ' destinataire(s).';

                // Enregistrer dans la table `email` (one record per send for the event)
                try {
                    $ins = $db->prepare("INSERT INTO email (IdEvenement, DateEnvoie, Objet) VALUES (:idevent, NOW(), :objet)");
                    $ins->bindValue(':idevent', $context_event['IdEvenement'] ?? null, PDO::PARAM_INT);
                    $ins->bindValue(':objet', $subject, PDO::PARAM_STR);
                    $ins->execute();
                } catch (\Exception $ex) {
                    // ignore logging errors
                }
            } catch (\Exception $e) {
                $error_message = "Échec de l'envoi de l'email: " . $mail->ErrorInfo;
            }
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
            <?php include __DIR__ . '/_sidebar.php'; ?>

            <main class="main-content">
                <!-- Historique des emails envoyés -->
                <div class="table-modern" style="margin-bottom: var(--space-xl); width: 100%;">
                    <div class="table-header-modern">
                        <h2 class="table-title-modern">Historique des emails envoyés</h2>
                    </div>
                    <?php if (empty($email_logs)): ?>
                        <div class="empty-state-modern">
                            <div class="empty-state-icon-modern">📧</div>
                            <h3>Aucun email envoyé pour le moment</h3>
                            <p>Les informations des emails envoyés apparaîtront ici.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid var(--neutral-200); border-radius: var(--border-radius-md);">
                            <table style="min-width: 1100px;">
                                <thead>
                                    <tr>
                                        <th style="white-space: nowrap;">Date/Heure</th>
                                        <th style="white-space: nowrap;">Contexte</th>
                                        <th style="white-space: nowrap;">Sujet</th>
                                        <th style="white-space: nowrap;">Destinataires</th>
                                        <th style="white-space: nowrap;">Pièce jointe</th>
                                        <th style="white-space: nowrap;">Aperçu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($email_logs as $log):
                                        // Lookup event name
                                        $evt_name = 'Événement';
                                        $evt_date = null;
                                        if (!empty($log['IdEvenement'])) {
                                            try {
                                                $qev = $db->prepare('SELECT NomEvenement, Date FROM Evenement WHERE IdEvenement = ? LIMIT 1');
                                                $qev->execute([(int)$log['IdEvenement']]);
                                                $evr = $qev->fetch(PDO::FETCH_ASSOC);
                                                if ($evr) { $evt_name = $evr['NomEvenement']; $evt_date = $evr['Date']; }
                                            } catch (\Exception $ex) {}
                                        }
                                        $preview = htmlspecialchars(mb_substr($log['Objet'] ?? '', 0, 120));
                                        $context = htmlspecialchars($evt_name . ($evt_date ? ' (' . date('d/m/Y', strtotime($evt_date)) . ')' : ''));
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($log['DateEnvoie'] ?? $log['DateEnvoie']))); ?></td>
                                            <td><?php echo $context; ?></td>
                                            <td><?php echo htmlspecialchars($log['Objet'] ?? ''); ?></td>
                                            <td>-</td>
                                            <td>
                                                <?php
                                                    // try to find attachments related to this event in uploads/emails
                                                    $pattern = __DIR__ . '/../uploads/emails/attestation_' . ($log['IdEvenement'] ?? '');
                                                    $found = [];
                                                    if (is_dir(__DIR__ . '/../uploads/emails')) {
                                                        foreach (glob(__DIR__ . '/../uploads/emails/*' . ($log['IdEvenement'] ? ('_' . $log['IdEvenement'] . '_') : '') . '*.pdf') as $f) {
                                                            $found[] = $f;
                                                        }
                                                    }
                                                    if (!empty($found)) {
                                                        echo '<a href="' . htmlspecialchars(str_replace('\\', '/', '../uploads/emails/' . basename($found[0]))) . '" target="_blank" class="btn btn-outline btn-sm">Voir</a>';
                                                    } else {
                                                        echo '<span class="text-xs text-secondary">—</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo $preview; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="page-title">
                    <h1>Envoyer un email</h1>
                    <p>Communiquez avec les participants d'un événement ou des adhérents sélectionnés</p>
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

                <form method="POST" class="form-modern" enctype="multipart/form-data">
                    <!-- Type d'envoi -->
                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">Type d'envoi</h3>
                        <div class="form-row-modern">
                            <label class="form-radio">
                                <input type="radio" name="send_type" value="event" checked onclick="toggleSendType()">
                                <span>Participants d'un événement</span>
                            </label>
                            <label class="form-radio" style="margin-left: 16px;">
                                <input type="radio" name="send_type" value="members" onclick="toggleSendType()">
                                <span>Adhérents sélectionnés du club</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section Sélection de l'événement -->
                    <div class="form-section-modern" id="section-event">
                        <h3 class="form-section-title-modern">Sélectionner l'événement</h3>
                        <div class="form-group-modern">
                            <label for="event_id" class="form-label-modern">Événement *</label>
                            <select id="event_id" name="event_id" class="form-input-modern form-select-modern">
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

                    <!-- Section Sélection des adhérents -->
                    <div class="form-section-modern" id="section-members" style="display:none;">
                        <h3 class="form-section-title-modern">Sélectionner les adhérents</h3>
                        <div class="admin-section-modern">
                            <?php if (empty($adherents)): ?>
                                <div class="empty-state-modern">
                                    <div class="empty-state-icon-modern">👥</div>
                                    <h3>Aucun adhérent actif</h3>
                                    <p>Aucun adhérent actif n'a été trouvé pour votre club.</p>
                                </div>
                            <?php else: ?>
                                <div style="max-height:300px; overflow:auto; border:1px solid var(--neutral-200); border-radius: var(--border-radius-md); padding: var(--space-md);">
                                    <?php foreach ($adherents as $a): ?>
                                        <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                            <input type="checkbox" name="recipient_ids[]" value="<?php echo (int)$a['IdUtilisateur']; ?>">
                                            <span><?php echo htmlspecialchars(($a['Nom'] ?? '') . ' ' . ($a['Prenom'] ?? '')); ?> — <?php echo htmlspecialchars($a['Email'] ?? ''); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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

                        <div class="form-group-modern">
                            <label for="attachment" class="form-label-modern">Pièce jointe (optionnelle)</label>
                            <input type="file" id="attachment" name="attachment" class="form-input-modern" accept="image/*,.pdf,.doc,.docx,.zip">
                            <div class="text-xs text-secondary">Formats acceptés: images, PDF, DOC/DOCX, ZIP. Taille max 10MB.</div>
                        </div>
                    </div>

                        <div class="form-section-modern">
                            <label style="display:flex; align-items:center; gap:8px;">
                                <input type="checkbox" name="send_attestations" value="1" id="send_attestations">
                                <span>Envoyer une attestation PDF individuelle à chaque participant (événement uniquement)</span>
                            </label>
                        </div>

                    <!-- Section Aperçu -->
                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">Aperçu</h3>
                        <div class="admin-section-modern">
                            <div class="email-preview-modern">
                                <div class="email-preview-header-modern">
                                    <div class="email-preview-subject-modern" id="preview-subject">Sujet de l'email</div>
                                    <div class="email-preview-meta-modern">
                                        <span>De: <?php echo htmlspecialchars(($current_user['Prenom'] ?? '') . ' ' . ($current_user['Nom'] ?? '')); ?> (<?php echo htmlspecialchars($club['NomClub'] ?? ''); ?>)</span>
                                        <span id="preview-context">À: Participants de l'événement sélectionné</span>
                                    </div>
                                </div>
                                <div class="email-preview-content-modern" id="preview-message">Le contenu de votre message apparaîtra ici...</div>
                                <div class="text-xs text-secondary" id="preview-attachment" style="margin-top:8px; display:none;">Pièce jointe: <span id="preview-attachment-name"></span></div>
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
        function toggleSendType() {
            const type = document.querySelector('input[name="send_type"]:checked').value;
            const secEvent = document.getElementById('section-event');
            const secMembers = document.getElementById('section-members');
            const ctx = document.getElementById('preview-context');
            if (type === 'event') {
                secEvent.style.display = '';
                secMembers.style.display = 'none';
                ctx.textContent = "À: Participants de l'événement sélectionné";
            } else {
                secEvent.style.display = 'none';
                secMembers.style.display = '';
                ctx.textContent = "À: Adhérents sélectionnés du club";
            }
        }
        toggleSendType();

        // Mise à jour de l'aperçu en temps réel
        document.getElementById('subject').addEventListener('input', function() {
            document.getElementById('preview-subject').textContent = this.value || "Sujet de l'email";
        });
        document.getElementById('message').addEventListener('input', function() {
            document.getElementById('preview-message').textContent = this.value || 'Le contenu de votre message apparaîtra ici...';
        });
        const attachInput = document.getElementById('attachment');
        if (attachInput) {
            attachInput.addEventListener('change', function() {
                const info = document.getElementById('preview-attachment');
                const nameSpan = document.getElementById('preview-attachment-name');
                if (this.files && this.files[0]) {
                    nameSpan.textContent = this.files[0].name;
                    info.style.display = '';
                } else {
                    info.style.display = 'none';
                    nameSpan.textContent = '';
                }
            });
        }
    </script>
</body>
</html>