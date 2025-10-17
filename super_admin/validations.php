<?php
require_once '../config/database.php';
require_once '../config/session.php';
require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header('Location: ../auth/login.php');
    exit;
}

requireRole(['administrateur']);

$database = new Database();
$conn = $database->getConnection();

function envoyerEmailEvenement($destinataire_email, $destinataire_nom, $nom_evenement, $type_email, $raison_rejet = null) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mohito.raihana@gmail.com';
        $mail->Password = 'pqie uzik iuym wsgl';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->SMTPAutoTLS = false;
        $mail->Timeout = 30;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('mohito.raihana@gmail.com', 'Event Manager - Administration');
        $mail->addAddress($destinataire_email, $destinataire_nom);
        
        $mail->isHTML(true);
        
        if ($type_email === 'validation') {
            $mail->Subject = "✓ Votre événement « " . $nom_evenement . " » a été validé";
            $statut_couleur = '#10b981';
            $statut_bg = '#d1fae5';
            $icone = '✓';
            $titre = 'Événement Validé !';
            $message = 'Votre événement « <strong>' . htmlspecialchars($nom_evenement) . '</strong> » a été approuvé par l\'équipe d\'administration.';
            $sous_message = 'Il est maintenant visible sur la plateforme et les participants peuvent s\'y inscrire.';
        } else {
            $mail->Subject = "✗ Votre événement « " . $nom_evenement . " » a été refusé";
            $statut_couleur = '#ef4444';
            $statut_bg = '#fee2e2';
            $icone = '✗';
            $titre = 'Événement Refusé';
            $message = 'Votre événement « <strong>' . htmlspecialchars($nom_evenement) . '</strong> » a été refusé par l\'équipe d\'administration.';
            $sous_message = 'Veuillez consulter la raison du refus ci-dessous.';
        }
        
        $raison_html = '';
        if ($raison_rejet) {
            $raison_html = '
                <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <h3 style="color: #856404; margin-top: 0;">📋 Raison du refus</h3>
                    <p style="color: #856404; margin: 10px 0; line-height: 1.6;">' . nl2br(htmlspecialchars($raison_rejet)) . '</p>
                </div>
            ';
        }
        
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                    <h1 style="color: white; margin: 0;">🎟️ Event Manager</h1>
                    <p style="color: #f0f0f0; margin: 5px 0 0 0;">Gestion des événements de clubs</p>
                </div>
                
                <div style="background: white; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;">
                    <h2 style="color: #333; margin-top: 0;">' . $icone . ' ' . $titre . '</h2>
                    
                    <p style="font-size: 16px; line-height: 1.6; color: #555;">
                        ' . $message . '
                    </p>
                    
                    <p style="font-size: 16px; line-height: 1.6; color: #555;">
                        ' . $sous_message . '
                    </p>
                    
                    ' . $raison_html . '
                    
                    <div style="background: ' . $statut_bg . '; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid ' . $statut_couleur . ';">
                        <h3 style="color: ' . $statut_couleur . '; margin-top: 0;">Statut de votre événement</h3>
                        <p style="color: ' . $statut_couleur . '; margin: 10px 0;">
                            Événement : <strong>' . strtoupper($type_email === 'validation' ? 'VALIDÉ' : 'REFUSÉ') . '</strong>
                        </p>
                    </div>
                    
                    ' . ($type_email === 'validation' ? '
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="https://votre-site.com" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                                🎯 Consulter votre événement
                            </a>
                        </div>
                    ' : '') . '
                    
                    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
                    
                    <p style="color: #666; font-size: 14px; margin: 0;">
                        Cordialement,<br>
                        <strong>L\'équipe Event Manager</strong>
                    </p>
                    
                    <p style="color: #999; font-size: 12px; margin-top: 20px;">
                        📧 Email envoyé le ' . date('d/m/Y à H:i') . '
                    </p>
                </div>
            </div>
        ';
        
        $mail->AltBody = $titre . "\n\n" . strip_tags($message) . "\n\n" . 
                        ($raison_rejet ? "Raison : " . $raison_rejet : "");

        $mail->send();
        return ['success' => true, 'message' => 'Email envoyé avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $mail->ErrorInfo];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {    
           case 'validate_event':
            $event_id = $_POST['event_id'] ?? null;
            if ($event_id) {
                try {
                    // Récupérer les infos de l'événement et de l'organisateur
                    $sql_get_event = "SELECT e.NomEvenement, e.IdClub, c.IdAdminClub, u.Email, u.Nom, u.Prenom 
                                    FROM Evenement e
                                    JOIN Club c ON e.IdClub = c.IdClub
                                    LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
                                    WHERE e.IdEvenement = :event_id";
                    
                    $stmt_get = $conn->prepare($sql_get_event);
                    $stmt_get->bindParam(':event_id', $event_id);
                    $stmt_get->execute();
                    $event_info = $stmt_get->fetch(PDO::FETCH_ASSOC);
                    
                    // Mettre à jour l'événement
                    $sql = "UPDATE Evenement SET Etat = 'validé' WHERE IdEvenement = :event_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':event_id', $event_id);
                    
                    if ($stmt->execute()) {
                        // Envoyer l'email à l'organisateur si disponible
                        if ($event_info && $event_info['Email']) {
                            $nom_complet = $event_info['Prenom'] . ' ' . $event_info['Nom'];
                            $resultat = envoyerEmailEvenement(
                                $event_info['Email'],
                                $nom_complet,
                                $event_info['NomEvenement'],
                                'validation'
                            );
                            
                            // Enregistrer dans la BDD
                            if ($resultat['success']) {
                                $objet = "✓ Votre événement « " . $event_info['NomEvenement'] . " » a été validé";
                                $contenu = "Bonjour " . $nom_complet . ",\n\n";
                                $contenu .= "Votre événement \"" . $event_info['NomEvenement'] . "\" a été approuvé.\n";
                                $contenu .= "Il est maintenant visible sur la plateforme.\n\n";
                                $contenu .= "Cordialement,\nL'équipe Event Manager";
                                
                                $sql_email = "INSERT INTO EmailAdmin (IdAdmin, DestinataireEmail, DestinataireNom, Objet, Contenu, TypeEmail, IdEvenement, DateEnvoi) 
                                            VALUES (:id_admin, :destinataire_email, :destinataire_nom, :objet, :contenu, 'validation_evenement', :id_event, NOW())";

                                $stmt_email = $conn->prepare($sql_email);
                                $id_admin = $_SESSION['user_id'];
                                $stmt_email->bindParam(':id_admin', $id_admin);
                                $stmt_email->bindParam(':destinataire_email', $event_info['Email']);
                                $stmt_email->bindParam(':destinataire_nom', $nom_complet);
                                $stmt_email->bindParam(':objet', $objet);
                                $stmt_email->bindParam(':contenu', $contenu);
                                $stmt_email->bindParam(':id_event', $event_id);
                                $stmt_email->execute();
                            }
                        }
                        
                        $success_message = "Événement validé avec succès.";
                    } else {
                        $error_message = "Erreur lors de la validation de l'événement.";
                    }
                } catch (PDOException $e) {
                    $error_message = "Erreur de base de données : " . $e->getMessage();
                }
            }
            break;
                
            case 'reject_event':
            $event_id = $_POST['event_id'] ?? null;
            $raison = trim($_POST['raison'] ?? '');
            if ($event_id && $raison) {
                try {
                    // Récupérer les infos de l'événement et de l'organisateur
                    $sql_get_event = "SELECT e.NomEvenement, e.IdClub, c.IdAdminClub, u.Email, u.Nom, u.Prenom 
                                    FROM Evenement e
                                    JOIN Club c ON e.IdClub = c.IdClub
                                    LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
                                    WHERE e.IdEvenement = :event_id";
                    
                    $stmt_get = $conn->prepare($sql_get_event);
                    $stmt_get->bindParam(':event_id', $event_id);
                    $stmt_get->execute();
                    $event_info = $stmt_get->fetch(PDO::FETCH_ASSOC);
                    
                    // Mettre à jour l'événement
                    $sql = "UPDATE Evenement SET Etat = 'refusé', RaisonRejet = :raison WHERE IdEvenement = :event_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':event_id', $event_id);
                    $stmt->bindParam(':raison', $raison);
                    
                    if ($stmt->execute()) {
                        // Envoyer l'email à l'organisateur si disponible
                        if ($event_info && $event_info['Email']) {
                            $nom_complet = $event_info['Prenom'] . ' ' . $event_info['Nom'];
                            $resultat = envoyerEmailEvenement(
                                $event_info['Email'],
                                $nom_complet,
                                $event_info['NomEvenement'],
                                'refus',
                                $raison
                            );
                            
                            // Enregistrer dans la BDD
                            if ($resultat['success']) {
                                $objet = "✗ Votre événement « " . $event_info['NomEvenement'] . " » a été refusé";
                                $contenu = "Bonjour " . $nom_complet . ",\n\n";
                                $contenu .= "Votre événement \"" . $event_info['NomEvenement'] . "\" a été refusé.\n\n";
                                $contenu .= "Raison du refus :\n" . $raison . "\n\n";
                                $contenu .= "Cordialement,\nL'équipe Event Manager";
                                
                                $sql_email = "INSERT INTO EmailAdmin (IdAdmin, DestinataireEmail, DestinataireNom, Objet, Contenu, TypeEmail, IdEvenement, DateEnvoi) 
                                            VALUES (:id_admin, :destinataire_email, :destinataire_nom, :objet, :contenu, 'refus_evenement', :id_event, NOW())";

                                $stmt_email = $conn->prepare($sql_email);
                                $id_admin = $_SESSION['user_id'];
                                $stmt_email->bindParam(':id_admin', $id_admin);
                                $stmt_email->bindParam(':destinataire_email', $event_info['Email']);
                                $stmt_email->bindParam(':destinataire_nom', $nom_complet);
                                $stmt_email->bindParam(':objet', $objet);
                                $stmt_email->bindParam(':contenu', $contenu);
                                $stmt_email->bindParam(':id_event', $event_id);
                                $stmt_email->execute();
                            }
                        }
                        
                        $success_message = "Événement refusé avec succès.";
                    } else {
                        $error_message = "Erreur lors du rejet de l'événement.";
                    }
                } catch (PDOException $e) {
                    $error_message = "Erreur de base de données : " . $e->getMessage();
                }
            }
            break;
        }
    }
}

try {
    $sql_events = "SELECT 
                      e.IdEvenement,
                      e.NomEvenement,
                      e.Description,
                      e.Date,
                      e.HeureDebut as Heure,
                      e.Lieu,
                      e.CapaciteMax,
                      e.PrixAdherent,
                      e.PrixNonAdherent,
                      e.PrixExterne,
                      e.TypeParticipant,
                      c.NomClub,
                      u.Nom as admin_nom,
                      u.Prenom as admin_prenom
                    FROM Evenement e
                    JOIN Club c ON e.IdClub = c.IdClub
                    LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
                    WHERE e.Etat = 'en attente'
                    ORDER BY e.Date ASC";
    
    $stmt_events = $conn->prepare($sql_events);
    $stmt_events->execute();
    $events_en_attente = $stmt_events->fetchAll(PDO::FETCH_ASSOC);

    $sql_valides = "SELECT COUNT(*) as total FROM Evenement WHERE Etat = 'validé'";
    $stmt_valides = $conn->prepare($sql_valides);
    $stmt_valides->execute();
    $events_valides_count = $stmt_valides->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_refuses = "SELECT COUNT(*) as total FROM Evenement WHERE Etat = 'refusé'";
    $stmt_refuses = $conn->prepare($sql_refuses);
    $stmt_refuses->execute();
    $events_refuses_count = $stmt_refuses->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_events_valides = "SELECT 
                            e.IdEvenement,
                            e.NomEvenement,
                            e.Date,
                            e.HeureDebut as Heure,
                            e.Lieu,
                            c.NomClub,
                            e.Etat as Status
                          FROM Evenement e
                          JOIN Club c ON e.IdClub = c.IdClub
                          WHERE e.Etat = 'validé'
                          ORDER BY e.Date ASC";
    
    $stmt_events_valides = $conn->prepare($sql_events_valides);
    $stmt_events_valides->execute();
    $events_valides = $stmt_events_valides->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des Événements</title>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #ff6b6b 0%, #4ecdc4 50%, #45b7d1 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --primary-coral: #ff6b6b;
            --primary-teal: #4ecdc4;
            --neutral-50: #fafbfc;
            --neutral-100: #f4f6f8;
            --neutral-200: #e8ecf0;
            --neutral-600: #4b5563;
            --neutral-700: #374151;
            --neutral-800: #1f2937;
            --neutral-900: #111827;
            --success: #10b981;
            --success-light: #d1fae5;
            --error: #ef4444;
            --error-light: #fee2e2;
            --warning: #f59e0b;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius-lg: 12px;
            --border-radius-xl: 16px;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            --space-2xl: 3rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--neutral-50);
            color: var(--neutral-800);
        }

        .header-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--neutral-200);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-md);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-lg) var(--space-xl);
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo-modern {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-secondary {
            background: var(--neutral-200);
            color: var(--neutral-700);
        }

        .btn-secondary:hover {
            background: var(--neutral-300);
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-2xl) var(--space-xl);
        }

        .page-title {
            margin-bottom: var(--space-2xl);
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: var(--space-md);
        }

        .page-title p {
            color: var(--neutral-600);
        }

        .alert {
            padding: var(--space-lg);
            border-radius: var(--border-radius-lg);
            margin-bottom: var(--space-lg);
            border-left: 4px solid;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success-modern {
            background: var(--success-light);
            color: var(--success);
            border-left-color: var(--success);
        }

        .alert-error-modern {
            background: var(--error-light);
            color: var(--error);
            border-left-color: var(--error);
        }

        .stats-grid-modern {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-xl);
            margin-bottom: var(--space-2xl);
        }

        .stat-card-modern {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-md);
            border-top: 4px solid var(--primary-coral);
            transition: all 0.3s ease;
        }

        .stat-card-modern:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .stat-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value-modern {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--space-sm);
        }

        .stat-label-modern {
            color: var(--neutral-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-icon-modern {
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon-modern.coral {
            background: var(--primary-coral);
        }

        .stat-icon-modern.teal {
            background: var(--primary-teal);
        }

        .calendar-section {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--space-2xl);
        }

        .calendar-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: var(--space-xl);
            padding-bottom: var(--space-lg);
            border-bottom: 2px solid var(--neutral-100);
        }

        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
        }

        .month-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--neutral-900);
        }

        .nav-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-lg);
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: var(--neutral-200);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .calendar-header {
            background: var(--primary-gradient);
            color: white;
            padding: var(--space-lg);
            text-align: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .calendar-day {
            background: white;
            padding: var(--space-md);
            min-height: 100px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .calendar-day.other-month {
            background: var(--neutral-50);
            color: var(--neutral-400);
        }

        .calendar-day.today {
            background: #e3f2fd;
            border: 2px solid #2196f3;
        }

        .calendar-day.has-events {
            background: #e8f5e9;
        }

        .day-number {
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: var(--space-sm);
            font-size: 0.9rem;
        }

        .calendar-day.other-month .day-number {
            color: var(--neutral-400);
        }

        .events-in-day {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
        }

        .event-item {
            background: var(--success);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .event-item:hover {
            opacity: 0.8;
            box-shadow: var(--shadow-md);
        }

        .validation-section {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--space-2xl);
        }

        .form-section-title-modern {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: var(--space-xl);
            padding-bottom: var(--space-lg);
            border-bottom: 2px solid var(--neutral-100);
        }

        .events-grid-validation {
            display: flex;
            flex-direction: column;
            gap: var(--space-xl);
        }

        .event-card-validation {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: var(--space-xl);
            border: 2px solid var(--neutral-200);
            transition: all 0.3s ease;
        }

        .event-card-validation:hover {
            border-color: var(--primary-coral);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .event-header-validation {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
            flex-wrap: wrap;
        }

        .event-title-validation {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--neutral-900);
        }

        .event-date-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            background: var(--neutral-100);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--border-radius-lg);
            font-size: 0.875rem;
            color: var(--neutral-700);
            font-weight: 500;
            white-space: nowrap;
        }

        .event-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-lg);
            border-bottom: 1px solid var(--neutral-200);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--neutral-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            margin-bottom: var(--space-sm);
        }

        .detail-value {
            font-weight: 500;
            color: var(--neutral-800);
        }

        .pricing-box {
            background: var(--neutral-50);
            padding: var(--space-lg);
            border-radius: var(--border-radius-lg);
            margin-bottom: var(--space-lg);
        }

        .pricing-title {
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: var(--space-md);
            font-size: 0.875rem;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--space-md);
        }

        .price-item {
            background: white;
            padding: var(--space-md);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--neutral-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price-label {
            font-size: 0.875rem;
            color: var(--neutral-600);
        }

        .price-value {
            font-weight: 600;
            color: var(--primary-coral);
        }

        .event-actions {
            display: flex;
            gap: var(--space-md);
            flex-wrap: wrap;
            margin-top: var(--space-lg);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--neutral-200);
        }

        .reject-form {
            display: none;
            margin-top: var(--space-lg);
            padding: var(--space-lg);
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: var(--border-radius-lg);
        }

        .reject-form.active {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        .form-group-validation {
            margin-bottom: var(--space-lg);
        }

        .form-label-validation {
            display: block;
            font-weight: 600;
            color: var(--neutral-800);
            margin-bottom: var(--space-sm);
            font-size: 0.875rem;
        }

        .textarea-validation {
            width: 100%;
            padding: var(--space-md);
            border: 2px solid var(--neutral-200);
            border-radius: var(--border-radius-lg);
            font-family: inherit;
            font-size: 0.875rem;
            resize: vertical;
            min-height: 100px;
            transition: all 0.2s ease;
        }

        .textarea-validation:focus {
            outline: none;
            border-color: var(--primary-coral);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .form-actions-validation {
            display: flex;
            gap: var(--space-md);
            flex-wrap: wrap;
        }

        .no-items-message {
            text-align: center;
            padding: var(--space-2xl);
            color: var(--neutral-600);
        }

        .no-items-icon {
            font-size: 3rem;
            margin-bottom: var(--space-lg);
            opacity: 0.5;
        }

        .no-items-message h4 {
            color: var(--neutral-800);
            font-size: 1.25rem;
            margin-bottom: var(--space-sm);
        }

        @keyframes slideIn {
            from { opacity: 0; max-height: 0; }
            to { opacity: 1; max-height: 500px; }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: var(--space-lg) var(--space-md);
            }

            .event-header-validation {
                flex-direction: column;
            }

            .event-details-grid {
                grid-template-columns: 1fr;
            }

            .event-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .calendar-day {
                min-height: 80px;
                padding: var(--space-sm);
            }

            .day-number {
                font-size: 0.8rem;
            }

            .event-item {
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>
    <nav class="header-modern">
        <div class="header-content">
            <div class="logo-modern">Event Manager</div>
            <a href="dashboard.php" class="btn btn-secondary"> Retour au dashboard</a>
        </div>
    </nav>

    <div class="main-content">
        <div class="page-title">
            <h1>Validation des Événements</h1>
            <p>Validez ou rejetez les événements en attente d'approbation</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success-modern">✓ <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error-modern">✗ <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="stats-grid-modern">
            <div class="stat-card-modern">
                <div class="stat-header-modern">
                    <div>
                        <div class="stat-value-modern"><?php echo count($events_en_attente); ?></div>
                        <div class="stat-label-modern">Événements en attente</div>
                    </div>
                    <div class="stat-icon-modern coral">⏳</div>
                </div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-header-modern">
                    <div>
                        <div class="stat-value-modern"><?php echo $events_valides_count; ?></div>
                        <div class="stat-label-modern">Événements validés</div>
                    </div>
                    <div class="stat-icon-modern teal">✓</div>
                </div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-header-modern">
                    <div>
                        <div class="stat-value-modern"><?php echo $events_refuses_count; ?></div>
                        <div class="stat-label-modern">Événements refusés</div>
                    </div>
                    <div class="stat-icon-modern teal">✓</div>
                </div>
            </div>
        </div>

        <div class="calendar-section">
            <h3>Calendrier des événements validés</h3>
            <div class="month-navigation">
                <button class="nav-btn" onclick="changeMonth(-1)">‹</button>
                <div class="month-title" id="current-month"><?php echo date('F Y'); ?></div>
                <button class="nav-btn" onclick="changeMonth(1)">›</button>
            </div>
            <div class="calendar-grid" id="calendar-grid">
                <div class="calendar-header">Lun</div>
                <div class="calendar-header">Mar</div>
                <div class="calendar-header">Mer</div>
                <div class="calendar-header">Jeu</div>
                <div class="calendar-header">Ven</div>
                <div class="calendar-header">Sam</div>
                <div class="calendar-header">Dim</div>
            </div>
        </div>

        <div class="validation-section">
            <h3 class="form-section-title-modern">Événements en attente de validation</h3>

            <?php if (empty($events_en_attente)): ?>
                <div class="no-items-message">
                    <div class="no-items-icon">✓</div>
                    <h4>Aucun événement en attente</h4>
                    <p>Tous les événements ont été traités.</p>
                </div>
            <?php else: ?>
                <div class="events-grid-validation">
                    <?php foreach ($events_en_attente as $event): ?>
                        <div class="event-card-validation">
                            <div class="event-header-validation">
                                <div>
                                    <h4 class="event-title-validation"><?php echo htmlspecialchars($event['NomEvenement']); ?></h4>
                                </div>
                                <div class="event-date-badge">
                                    📅 <?php echo date('d/m/Y à H:i', strtotime($event['Date'] . ' ' . $event['Heure'])); ?>
                                </div>
                            </div>

                            <div class="event-details-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Club Organisateur</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['NomClub']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Lieu</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['Lieu']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Capacité Maximale</div>
                                    <div class="detail-value"><?php echo $event['CapaciteMax']; ?> personnes</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Type de Participants</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['TypeParticipant']); ?></div>
                                </div>
                            </div>

                            <?php if (!empty($event['Description'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Description</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['Description']); ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="pricing-box">
                                <div class="pricing-title">Tarification</div>
                                <div class="pricing-grid">
                                    <div class="price-item">
                                        <span class="price-label">Adhérents</span>
                                        <span class="price-value"><?php
                                        if($event['TypeParticipant'] == 'Adhérents' || $event['TypeParticipant'] == 'Ensatiens') {
                                         echo $event['PrixAdherent'] > 0 ? number_format($event['PrixAdherent'], 2) . ' DH' : 'Gratuit';} ?></span>
                                    </div>
                                    <div class="price-item">
                                        <span class="price-label">Non-adhérents</span>
                                        <span class="price-value"><?php
                                        if( $event['TypeParticipant'] == 'Ensatiens') {
                                         echo $event['PrixNonAdherent'] > 0 ? number_format($event['PrixNonAdherent'], 2) . ' DH' : 'Gratuit'; }?></span>
                                    </div>
                                    <div class="price-item">
                                        <span class="price-label">Externes</span>
                                        <span class="price-value"><?php 
                                        if($event['TypeParticipant'] == 'Tous') {
                                        echo $event['PrixExterne'] > 0 ? number_format($event['PrixExterne'], 2) . ' DH' : 'Gratuit';} ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="event-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="validate_event">
                                    <input type="hidden" name="event_id" value="<?php echo $event['IdEvenement']; ?>">
                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Êtes-vous sûr de valider cet événement ?')">
                                        ✓ Valider
                                    </button>
                                </form>

                                <button type="button" class="btn btn-secondary" onclick="toggleRejectForm('event_<?php echo $event['IdEvenement']; ?>')">
                                    ✗ Rejeter
                                </button>
                            </div>

                            <div id="event_<?php echo $event['IdEvenement']; ?>" class="reject-form">
                                <form method="POST">
                                    <input type="hidden" name="action" value="reject_event">
                                    <input type="hidden" name="event_id" value="<?php echo $event['IdEvenement']; ?>">
                                    
                                    <div class="form-group-validation">
                                        <label class="form-label-validation" for="raison_<?php echo $event['IdEvenement']; ?>">Raison du rejet *</label>
                                        <textarea class="textarea-validation" name="raison" id="raison_<?php echo $event['IdEvenement']; ?>" placeholder="Expliquez pourquoi cet événement est rejeté..." required></textarea>
                                    </div>

                                    <div class="form-actions-validation">
                                        <button type="submit" class="btn btn-primary" onclick="return confirm('Êtes-vous sûr de rejeter cet événement ?')">
                                            Confirmer le rejet
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="toggleRejectForm('event_<?php echo $event['IdEvenement']; ?>')">
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const eventsData = {
            validated: <?php echo json_encode($events_valides); ?>,
            pending: <?php echo json_encode($events_en_attente); ?>
        };
        
        let currentDate = new Date();
        
        function toggleRejectForm(formId) {
            const form = document.getElementById(formId);
            form.classList.toggle('active');
        }

        function changeMonth(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            renderCalendar();
        }
        
        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                              'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            document.getElementById('current-month').textContent = monthNames[month] + ' ' + year;
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = (firstDay.getDay() + 6) % 7;
            
            const prevMonth = new Date(year, month, 0);
            const daysInPrevMonth = prevMonth.getDate();
            
            const calendarGrid = document.getElementById('calendar-grid');
            
            const headers = calendarGrid.querySelectorAll('.calendar-header');
            calendarGrid.innerHTML = '';
            headers.forEach(header => calendarGrid.appendChild(header));
            
            for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                const dayElement = createDayElement(day, true, year, month - 1);
                calendarGrid.appendChild(dayElement);
            }
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = createDayElement(day, false, year, month);
                calendarGrid.appendChild(dayElement);
            }
            
            const totalCells = calendarGrid.children.length - 7;
            const remainingCells = 42 - totalCells;
            
            for (let day = 1; day <= remainingCells; day++) {
                const dayElement = createDayElement(day, true, year, month + 1);
                calendarGrid.appendChild(dayElement);
            }
        }
        
        function createDayElement(day, isOtherMonth, year, month) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            if (isOtherMonth) {
                dayElement.classList.add('other-month');
            }
            
            const today = new Date();
            if (!isOtherMonth && year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayElement.classList.add('today');
            }
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const dayEvents = getEventsForDate(dateStr);
            
            if (dayEvents.length > 0) {
                dayElement.classList.add('has-events');
                
                const eventsContainer = document.createElement('div');
                eventsContainer.className = 'events-in-day';
                
                dayEvents.forEach(event => {
                    const eventElement = document.createElement('div');
                    eventElement.className = 'event-item';
                    eventElement.textContent = event.nom;
                    eventElement.title = event.nom + ' - ' + event.club + ' (' + event.heure + ')';
                    eventsContainer.appendChild(eventElement);
                });
                
                dayElement.appendChild(eventsContainer);
            }
            
            return dayElement;
        }
        
        function getEventsForDate(dateStr) {
            const events = [];
            
            eventsData.validated.forEach(event => {
                if (event.Date === dateStr) {
                    events.push({
                        nom: event.NomEvenement,
                        club: event.NomClub,
                        heure: event.Heure,
                        status: 'validé'
                    });
                }
            });
            
            return events;
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            renderCalendar();
        });
    </script>
</body>
</html>