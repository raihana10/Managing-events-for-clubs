<?php
/**
 * Système de validation - Backend PHP
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

// Traitement des actions de validation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {    
            case 'validate_event':
                $event_id = $_POST['event_id'] ?? null;
                if ($event_id) {
                    try {
                        $sql = "UPDATE Evenement SET Etat = 'valide' WHERE IdEvenement = :event_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':event_id', $event_id);
                        
                        if ($stmt->execute()) {
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
                if ($event_id) {
                    try {
                        $sql = "UPDATE Evenement SET Etat = 'rejete', RaisonRejet = :raison WHERE IdEvenement = :event_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':event_id', $event_id);
                        $stmt->bindParam(':raison', $raison);
                        
                        if ($stmt->execute()) {
                            $success_message = "Événement rejeté avec succès.";
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

// Récupérer les éléments en attente de validation
try {
    // Clubs en attente
    $sql_clubs = "SELECT 
                    c.IdClub,
                    c.NomClub,
                    c.Description,
                    c.DateCreation,
                    c.Logo,
                    u.Nom as admin_nom,
                    u.Prenom as admin_prenom,
                    u.Email as admin_email
                  FROM Club c
                  LEFT JOIN Utilisateur u ON c.IdAdminClub = u.IdUtilisateur
                  ORDER BY c.DateCreation ASC";
    
    $stmt_clubs = $conn->prepare($sql_clubs);
    $stmt_clubs->execute();
    $clubs_en_attente = $stmt_clubs->fetchAll(PDO::FETCH_ASSOC);
    
    // Événements en attente
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
                    WHERE e.Etat = 'en_attente'
                    ORDER BY e.Date ASC";
    
    $stmt_events = $conn->prepare($sql_events);
    $stmt_events->execute();
    $events_en_attente = $stmt_events->fetchAll(PDO::FETCH_ASSOC);
    
    // Événements validés pour le calendrier
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
                          WHERE e.Etat = 'valide'
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
    <title>Validations</title>
    <link rel="stylesheet" href="../frontend/css.css">
    <style>
        body { margin: 0; background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand {
            font-size: 1.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 2em;
            color: #333;
            margin-bottom: 10px;
        }
        .page-header p {
            color: #666;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #555;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .validation-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .validation-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .item-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .item-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .item-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .item-date {
            color: #666;
            font-size: 0.9em;
        }
        .item-details {
            margin-bottom: 20px;
        }
        .item-details p {
            margin: 5px 0;
            color: #555;
        }
        .item-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-validate {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-validate:hover {
            transform: translateY(-2px);
        }
        .btn-reject {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-reject:hover {
            transform: translateY(-2px);
        }
        .reject-form {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #fff3e0;
            border-radius: 8px;
        }
        .reject-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
            min-height: 80px;
        }
        .reject-form .form-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        .no-items {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .no-items-icon {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .calendar-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .calendar-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .calendar-header {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }
        .calendar-day {
            background: white;
            padding: 10px;
            min-height: 80px;
            border: 1px solid #e0e0e0;
            position: relative;
        }
        .calendar-day.other-month {
            background: #f5f5f5;
            color: #999;
        }
        .calendar-day.today {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        .calendar-day.has-events {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .event-item {
            background: #4caf50;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8em;
            margin: 2px 0;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .event-item.pending {
            background: #ff9800;
        }
        .event-item:hover {
            opacity: 0.8;
        }
        .event-tooltip {
            position: absolute;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9em;
            z-index: 1000;
            max-width: 250px;
            display: none;
        }
        .event-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .month-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }
        .nav-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.2em;
        }
        .nav-btn:hover {
            background: #5a6fd8;
        }
        @media (max-width: 768px) {
            .item-header {
                flex-direction: column;
                gap: 10px;
            }
            .item-actions {
                justify-content: center;
            }
            .calendar-grid {
                font-size: 0.8em;
            }
            .calendar-day {
                min-height: 60px;
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">GestionEvents</div>
        <a href="dashboard.php" class="btn btn-secondary">Retour au dashboard</a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Validations</h1>
            <p>Validez ou rejetez les clubs et événements en attente</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($clubs_en_attente); ?></div>
                <div class="stat-label">Clubs en attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($events_en_attente); ?></div>
                <div class="stat-label">Événements en attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($clubs_en_attente) + count($events_en_attente); ?></div>
                <div class="stat-label">Total en attente</div>
            </div>
        </div>

        <!-- Calendrier des événements -->
        <div class="calendar-section">
            <h3>Calendrier des événements</h3>
            
            <div class="month-navigation">
                <button class="nav-btn" onclick="changeMonth(-1)">‹</button>
                <div class="month-title" id="current-month"><?php echo date('F Y'); ?></div>
                <button class="nav-btn" onclick="changeMonth(1)">›</button>
            </div>
            
            <div class="calendar-grid" id="calendar-grid">
                <!-- Headers -->
                <div class="calendar-header">Lun</div>
                <div class="calendar-header">Mar</div>
                <div class="calendar-header">Mer</div>
                <div class="calendar-header">Jeu</div>
                <div class="calendar-header">Ven</div>
                <div class="calendar-header">Sam</div>
                <div class="calendar-header">Dim</div>
                
                <!-- Les jours seront générés par JavaScript -->
            </div>
        </div>

        <!-- Clubs en attente -->
        <div class="validation-section">
            <h3>Clubs en attente de validation</h3>
            
            <?php if (empty($clubs_en_attente)): ?>
                <div class="no-items">
                    <div class="no-items-icon">✓</div>
                    <h4>Aucun club en attente</h4>
                    <p>Tous les clubs ont été traités.</p>
                </div>
            <?php else: ?>
                <?php foreach ($clubs_en_attente as $club): ?>
                    <div class="item-card">
                        <div class="item-header">
                            <div>
                                <h4 class="item-title"><?php echo htmlspecialchars($club['NomClub']); ?></h4>
                                <p class="item-date">Créé le <?php echo date('d/m/Y', strtotime($club['DateCreation'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="item-details">
                            <?php if (!empty($club['Description'])): ?>
                                <p><strong>Description :</strong> <?php echo htmlspecialchars($club['Description']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($club['admin_prenom'])): ?>
                                <p><strong>Administrateur :</strong> <?php echo htmlspecialchars($club['admin_prenom'] . ' ' . $club['admin_nom']); ?></p>
                                <p><strong>Email :</strong> <?php echo htmlspecialchars($club['admin_email']); ?></p>
                            <?php else: ?>
                                <p><strong>Administrateur :</strong> <span style="color: #ff9800;">Non assigné</span></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="item-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="validate_club">
                                <input type="hidden" name="club_id" value="<?php echo $club['IdClub']; ?>">
                                <button type="submit" class="btn-validate" 
                                        onclick="return confirm('Valider ce club ?')">
                                    Valider
                                </button>
                            </form>
                            
                            <button type="button" class="btn-reject" 
                                    onclick="toggleRejectForm('club_<?php echo $club['IdClub']; ?>')">
                                Rejeter
                            </button>
                        </div>
                        
                        <div id="club_<?php echo $club['IdClub']; ?>" class="reject-form">
                            <form method="POST">
                                <input type="hidden" name="action" value="reject_club">
                                <input type="hidden" name="club_id" value="<?php echo $club['IdClub']; ?>">
                                <label for="raison_club_<?php echo $club['IdClub']; ?>">Raison du rejet :</label>
                                <textarea name="raison" id="raison_club_<?php echo $club['IdClub']; ?>" 
                                          placeholder="Expliquez pourquoi ce club est rejeté..." required></textarea>
                                <div class="form-actions">
                                    <button type="submit" class="btn-reject">Confirmer le rejet</button>
                                    <button type="button" class="btn-secondary" 
                                            onclick="toggleRejectForm('club_<?php echo $club['IdClub']; ?>')">
                                        Annuler
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Événements en attente -->
        <div class="validation-section">
            <h3>Événements en attente de validation</h3>
            
            <?php if (empty($events_en_attente)): ?>
                <div class="no-items">
                    <div class="no-items-icon">✓</div>
                    <h4>Aucun événement en attente</h4>
                    <p>Tous les événements ont été traités.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events_en_attente as $event): ?>
                    <div class="item-card">
                        <div class="item-header">
                            <div>
                                <h4 class="item-title"><?php echo htmlspecialchars($event['NomEvenement']); ?></h4>
                                <p class="item-date"><?php echo date('d/m/Y à H:i', strtotime($event['Date'] . ' ' . $event['Heure'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="item-details">
                            <p><strong>Club :</strong> <?php echo htmlspecialchars($event['NomClub']); ?></p>
                            <p><strong>Lieu :</strong> <?php echo htmlspecialchars($event['Lieu']); ?></p>
                            <p><strong>Capacité :</strong> <?php echo $event['CapaciteMax']; ?> personnes</p>
                            <p><strong>Type de participants :</strong> <?php echo htmlspecialchars($event['TypeParticipant']); ?></p>
                            <div style="margin: 10px 0;">
                                <strong>Prix :</strong>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li>Adhérents : <?php echo $event['PrixAdherent'] > 0 ? number_format($event['PrixAdherent'], 2) . ' €' : 'Gratuit'; ?></li>
                                    <li>Non-adhérents : <?php echo $event['PrixNonAdherent'] > 0 ? number_format($event['PrixNonAdherent'], 2) . ' €' : 'Gratuit'; ?></li>
                                    <li>Externes : <?php echo $event['PrixExterne'] > 0 ? number_format($event['PrixExterne'], 2) . ' €' : 'Gratuit'; ?></li>
                                </ul>
                            </div>
                            <?php if (!empty($event['Description'])): ?>
                                <p><strong>Description :</strong> <?php echo htmlspecialchars($event['Description']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="item-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="validate_event">
                                <input type="hidden" name="event_id" value="<?php echo $event['IdEvenement']; ?>">
                                <button type="submit" class="btn-validate" 
                                        onclick="return confirm('Valider cet événement ?')">
                                    Valider
                                </button>
                            </form>
                            
                            <button type="button" class="btn-reject" 
                                    onclick="toggleRejectForm('event_<?php echo $event['IdEvenement']; ?>')">
                                Rejeter
                            </button>
                        </div>
                        
                        <div id="event_<?php echo $event['IdEvenement']; ?>" class="reject-form">
                            <form method="POST">
                                <input type="hidden" name="action" value="reject_event">
                                <input type="hidden" name="event_id" value="<?php echo $event['IdEvenement']; ?>">
                                <label for="raison_event_<?php echo $event['IdEvenement']; ?>">Raison du rejet :</label>
                                <textarea name="raison" id="raison_event_<?php echo $event['IdEvenement']; ?>" 
                                          placeholder="Expliquez pourquoi cet événement est rejeté..." required></textarea>
                                <div class="form-actions">
                                    <button type="submit" class="btn-reject">Confirmer le rejet</button>
                                    <button type="button" class="btn-secondary" 
                                            onclick="toggleRejectForm('event_<?php echo $event['IdEvenement']; ?>')">
                                        Annuler
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Données des événements depuis PHP
        const eventsData = {
            validated: <?php echo json_encode($events_valides); ?>,
            pending: <?php echo json_encode($events_en_attente); ?>
        };
        
        let currentDate = new Date();
        
        function toggleRejectForm(formId) {
            const form = document.getElementById(formId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
        
        function changeMonth(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            renderCalendar();
        }
        
        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Mettre à jour le titre du mois
            const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                              'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            document.getElementById('current-month').textContent = monthNames[month] + ' ' + year;
            
            // Obtenir le premier jour du mois et le nombre de jours
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = (firstDay.getDay() + 6) % 7; // Convertir dimanche=0 à lundi=0
            
            // Obtenir le dernier jour du mois précédent
            const prevMonth = new Date(year, month, 0);
            const daysInPrevMonth = prevMonth.getDate();
            
            const calendarGrid = document.getElementById('calendar-grid');
            
            // Nettoyer le calendrier (garder seulement les headers)
            const headers = calendarGrid.querySelectorAll('.calendar-header');
            calendarGrid.innerHTML = '';
            headers.forEach(header => calendarGrid.appendChild(header));
            
            // Ajouter les jours du mois précédent
            for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                const dayElement = createDayElement(day, true, year, month - 1);
                calendarGrid.appendChild(dayElement);
            }
            
            // Ajouter les jours du mois actuel
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = createDayElement(day, false, year, month);
                calendarGrid.appendChild(dayElement);
            }
            
            // Ajouter les jours du mois suivant pour compléter la grille
            const totalCells = calendarGrid.children.length - 7; // -7 pour les headers
            const remainingCells = 42 - totalCells; // 6 semaines * 7 jours = 42 cellules
            
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
            
            // Vérifier si c'est aujourd'hui
            const today = new Date();
            if (!isOtherMonth && year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayElement.classList.add('today');
            }
            
            // Créer le numéro du jour
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            // Ajouter les événements pour ce jour
            const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const dayEvents = getEventsForDate(dateStr);
            
            if (dayEvents.length > 0) {
                dayElement.classList.add('has-events');
                
                dayEvents.forEach(event => {
                    const eventElement = document.createElement('div');
                    eventElement.className = 'event-item';
                    if (event.status === 'en_attente') {
                        eventElement.classList.add('pending');
                    }
                    eventElement.textContent = event.nom;
                    eventElement.title = event.nom + ' - ' + event.club + ' (' + event.heure + ')';
                    dayElement.appendChild(eventElement);
                });
            }
            
            return dayElement;
        }
        
        function getEventsForDate(dateStr) {
            const events = [];
            
            // Événements validés
            eventsData.validated.forEach(event => {
                if (event.Date === dateStr) {
                    events.push({
                        nom: event.NomEvenement,
                        club: event.NomClub,
                        heure: event.Heure,
                        status: 'valide'
                    });
                }
            });
            
            // Événements en attente
            eventsData.pending.forEach(event => {
                if (event.Date === dateStr) {
                    events.push({
                        nom: event.NomEvenement,
                        club: event.NomClub,
                        heure: event.Heure,
                        status: 'en_attente'
                    });
                }
            });
            
            return events;
        }
        
        // Initialiser le calendrier
        renderCalendar();
    </script>
</body>
</html>
