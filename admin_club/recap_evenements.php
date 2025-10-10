<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les informations de la session si disponibles
$event_data = $_SESSION['event_preview'] ?? null;

$admin_club_query = "SELECT Email FROM utilisateur WHERE Role = 'administrateur'";
$stmt = $db->prepare($admin_club_query);
$stmt->execute();
$admin_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Récupérer le nom du club
$club_query = "SELECT NomClub FROM Club WHERE IdClub = :id_club";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':id_club', $event_data['id_club']);
$stmt->execute();
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement de la confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Insérer l'événement dans la base de données
        $insert_query = "INSERT INTO Evenement (
            IdClub, NomEvenement, HeureDebut, HeureFin, Date, Lieu,
            TypeParticipant, CapaciteMax, Affiche, Etat
        ) VALUES (
            :id_club, :nom_evenement, :heure_debut, :heure_fin, :date, :lieu,
            :participant, :capacite_max, :affiche, :etat
        )";

        $stmt = $db->prepare($insert_query);
        $stmt->bindParam(':id_club', $event_data['id_club']);
        $stmt->bindParam(':nom_evenement', $event_data['nom_evenement']);
        $stmt->bindParam(':heure_debut', $event_data['heure_debut']);
        $stmt->bindParam(':heure_fin', $event_data['heure_fin']);
        $stmt->bindParam(':date', $event_data['date']);
        $stmt->bindParam(':lieu', $event_data['lieu']);
        $stmt->bindParam(':participant', $event_data['participant']);
        $stmt->bindParam(':capacite_max', $event_data['capacite_max']);
        $stmt->bindParam(':affiche', $event_data['affiche']);
        $stmt->bindParam(':etat', $event_data['etat']);

        if ($stmt->execute()) {
            // Nettoyer la session
            unset($_SESSION['event_preview']);
            unset($_SESSION['csrf_token']);
            
            $_SESSION['success_message'] = "Événement créé avec succès !";
            header("Location: mes_evenements.php");
            exit();
        } else {
            $error = "Erreur lors de l'enregistrement de l'événement.";
        }
    }
}

// Générer un token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Formater la date pour l'affichage
$date_formatted = date('d/m/Y', strtotime($event_data['date']));
$heure_debut_formatted = date('H:i', strtotime($event_data['heure_debut']));
$heure_fin_formatted = date('H:i', strtotime($event_data['heure_fin']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récapitulatif de l'Événement</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            margin: 0; 
            background: #f5f7fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            min-height: 100vh;
        }
        
        .navbar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 100;
        }
        
        .navbar-brand {
            font-size: 1.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .sidebar {
            width: 250px;
            background: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            padding: 20px 0;
        }
        
        .nav-section {
            margin-bottom: 30px;
        }
        
        .nav-title {
            padding: 0 20px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            color: #999;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .nav-list {
            list-style: none;
        }
        
        .nav-item {
            margin: 2px 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background: #f5f7fa;
            color: #667eea;
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-right: 3px solid #764ba2;
        }
        
        .nav-icon {
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            flex: 1;
            padding: 30px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
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

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }

        .alert-warning {
            background: #fff3e0;
            color: #ef6c00;
            border-left: 4px solid #ef6c00;
        }
        
        .recap-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .recap-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 3px solid #667eea;
        }

        .info-item.full-width {
            grid-column: 1 / -1;
        }

        .info-label {
            font-size: 0.85em;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 1.1em;
            color: #333;
            font-weight: 500;
        }

        .affiche-preview {
            width: 100%;
            max-width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 20px auto;
            display: block;
        }

        .no-affiche {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #999;
            font-style: italic;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-info {
            background: #e3f2fd;
            color: #1565c0;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #555;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-outline {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .highlight-box {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border: 2px solid #667eea;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }

        .highlight-box h2 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .highlight-box p {
            color: #666;
            font-size: 0.95em;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .navbar {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <nav class="nav-section">
            <div class="nav-title">Gestion</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <span class="nav-icon">▪</span>
                        Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mes_evenements.php" class="nav-link">
                        <span class="nav-icon">▪</span>
                        Mes événements
                    </a>
                </li>
                <li class="nav-item">
                    <a href="creer_event.php" class="nav-link active">
                        <span class="nav-icon">▪</span>
                        Créer événement
                    </a>
                </li>
                <li class="nav-item">
                    <a href="membres.php" class="nav-link">
                        <span class="nav-icon">▪</span>
                        Membres
                    </a>
                </li>
                <li class="nav-item">
                    <a href="envoyer_email.php" class="nav-link">
                        <span class="nav-icon">▪</span>
                        Communication
                    </a>
                </li>
            </ul>
        </nav>

        <nav class="nav-section">
            <div class="nav-title">Personnel</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../utilisateur/mes_inscriptions.php" class="nav-link">
                        <span class="nav-icon">▪</span>
                        Mes inscriptions
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../utilisateur/clubs.php" class="nav-link">
                        <span class="nav-icon">▪</span>
                        Autres clubs
                    </a>
                </li>
                <li class="nav-item">
                    <a href="parametres.php" class="nav-link">
                        <span class="nav-icon">▪</span>
                        Paramètres
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="main-content">
        <nav class="navbar">
            <div class="navbar-brand">🎓 GestionEvents</div>
            <a href="dashboard.php" class="btn btn-secondary">← Retour au dashboard</a>
        </nav>

        <div class="container">
            <div class="page-header">
                <h1> Récapitulatif de l'événement</h1>
                <p>Vérifiez les informations avant de confirmer la création</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <span style="font-size: 1.3em;"></span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <div class="highlight-box">
                <h2> <?php echo htmlspecialchars($event_data['nom_evenement']); ?></h2>
                <p>Organisé par <strong><?php echo htmlspecialchars($club['NomClub'] ?? 'Club inconnu'); ?></strong></p>
            </div>

            <div class="recap-section">
                <h3>Informations générales</h3>
                
                <div class="info-grid">
                    <div class="info-item full-width">
                        <div class="info-label">Nom de l'événement</div>
                        <div class="info-value"><?php echo htmlspecialchars($event_data['nom_evenement']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Club organisateur</div>
                        <div class="info-value"><?php echo htmlspecialchars($club['NomClub'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Type d'événement</div>
                        <div class="info-value">
                            <span class="badge badge-info"><?php echo htmlspecialchars($event_data['type'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="recap-section">
                <h3> Date, heure et lieu</h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"> Date</div>
                        <div class="info-value"><?php echo $date_formatted; ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label"> Lieu</div>
                        <div class="info-value"><?php echo htmlspecialchars($event_data['lieu']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label"> Heure de début</div>
                        <div class="info-value"><?php echo $heure_debut_formatted; ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label"> Heure de fin</div>
                        <div class="info-value"><?php echo $heure_fin_formatted; ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label"> Participants</div>
                        <div class="info-value"><?php echo htmlspecialchars($event_data['participant']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label"> Capacité maximale</div>
                        <div class="info-value">
                            <?php 
                            echo $event_data['capacite_max'] 
                                ? htmlspecialchars($event_data['capacite_max']) . ' personnes' 
                                : '<span class="badge badge-success">Illimitée</span>'; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="recap-section">
                <h3> Affiche de l'événement</h3>
                
                <?php if (!empty($event_data['affiche']) && file_exists($event_data['affiche'])): ?>
                    <img src="<?php echo htmlspecialchars($event_data['affiche']); ?>" 
                         alt="Affiche de l'événement" 
                         class="affiche-preview">
                <?php else: ?>
                    <div class="no-affiche">
                        <p>📷 Aucune affiche téléchargée</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="recap-section">
                <h3> État de l'événement</h3>
                
                <div class="info-item">
                    <div class="info-label">État initial</div>
                    <div class="info-value">
                        <span class="badge badge-warning"><?php echo htmlspecialchars($event_data['etat']); ?></span>
                    </div>
                </div>

               
            </div>

            <form method="POST" id="confirmForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-actions">
                    <a href="creer_event.php" class="btn btn-outline">← Modifier</a>
                    <button type="submit" name="confirm" value="1" class="btn btn-primary">
                        ✓ Confirmer et créer l'événement
                    </button>
                </div>
            </form>
        </div>
    </div>
    

    <script>
        // Désactiver le bouton après soumission pour éviter les doubles clics
        document.getElementById('confirmForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Création en cours...';
            }
        });
    </script>
</body>
</html>