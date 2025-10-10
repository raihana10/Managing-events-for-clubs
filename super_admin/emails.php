<?php
/**
 * Envoi d'emails - Backend PHP
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

// Variables pour le formulaire
$destinataires = $sujet = $message = '';
$errors = [];
$success = false;
$emails_envoyes = 0;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $destinataires = $_POST['destinataires'] ?? [];
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($destinataires)) {
        $errors['destinataires'] = "Veuillez sélectionner au moins un destinataire.";
    }
    
    if (empty($sujet)) {
        $errors['sujet'] = "Le sujet est obligatoire.";
    } elseif (strlen($sujet) > 200) {
        $errors['sujet'] = "Le sujet ne peut pas dépasser 200 caractères.";
    }
    
    if (empty($message)) {
        $errors['message'] = "Le message est obligatoire.";
    } elseif (strlen($message) > 5000) {
        $errors['message'] = "Le message ne peut pas dépasser 5000 caractères.";
    }
    
    // Si aucune erreur, traiter l'envoi
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Récupérer les informations des destinataires
            $destinataires_info = [];
            
            if (in_array('tous_organisateurs', $destinataires)) {
                $sql_org = "SELECT IdUtilisateur, Nom, Prenom, Email FROM Utilisateur WHERE Role = 'organisateur'";
                $stmt_org = $conn->prepare($sql_org);
                $stmt_org->execute();
                $destinataires_info = array_merge($destinataires_info, $stmt_org->fetchAll(PDO::FETCH_ASSOC));
            } else {
                // Destinataires spécifiques sélectionnés
                foreach ($destinataires as $dest_id) {
                    if (is_numeric($dest_id)) {
                        $sql_user = "SELECT IdUtilisateur, Nom, Prenom, Email FROM Utilisateur WHERE IdUtilisateur = :id AND Role = 'organisateur'";
                        $stmt_user = $conn->prepare($sql_user);
                        $stmt_user->bindParam(':id', $dest_id);
                        $stmt_user->execute();
                        $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
                        if ($user_info) {
                            $destinataires_info[] = $user_info;
                        }
                    }
                }
            }
            
            // Supprimer les doublons
            $destinataires_info = array_unique($destinataires_info, SORT_REGULAR);
            
            if (!empty($destinataires_info)) {
                // Enregistrer chaque email individuellement
                foreach ($destinataires_info as $dest) {
                    $sql_email = "INSERT INTO EmailAdmin (IdAdmin, DestinataireEmail, DestinataireNom, Objet, Contenu, TypeEmail) 
                                 VALUES (:id_admin, :destinataire_email, :destinataire_nom, :objet, :contenu, 'general')";
                    
                    $stmt_email = $conn->prepare($sql_email);
                    $stmt_email->bindParam(':id_admin', $_SESSION['user_id']);
                    $stmt_email->bindParam(':destinataire_email', $dest['Email']);
                    $stmt_email->bindParam(':destinataire_nom', $dest['Prenom'] . ' ' . $dest['Nom']);
                    $stmt_email->bindParam(':objet', $sujet);
                    $stmt_email->bindParam(':contenu', $message);
                    $stmt_email->execute();
                    
                    $emails_envoyes++;
                }
                
                $conn->commit();
                $success = true;
                $success_message = "Email envoyé avec succès à " . $emails_envoyes . " organisateur(s).";
                
                // Réinitialiser le formulaire
                $destinataires = $sujet = $message = '';
            } else {
                $errors['general'] = "Aucun organisateur trouvé.";
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors['general'] = "Erreur lors de l'envoi de l'email : " . $e->getMessage();
        }
    }
}

// Récupérer les organisateurs pour la sélection
$organisateurs = [];
try {
    $sql_org = "SELECT IdUtilisateur, Nom, Prenom, Email FROM Utilisateur WHERE Role = 'organisateur' ORDER BY Nom, Prenom";
    $stmt_org = $conn->prepare($sql_org);
    $stmt_org->execute();
    $organisateurs = $stmt_org->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['general'] = "Erreur lors du chargement des organisateurs.";
}

// Récupérer les statistiques des emails
try {
    $sql_stats = "SELECT 
                    COUNT(*) as total_emails,
                    COUNT(DISTINCT DestinataireEmail) as total_destinataires,
                    COUNT(CASE WHEN DateEnvoi >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as emails_30j
                  FROM EmailAdmin WHERE IdAdmin = :id_admin";
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bindParam(':id_admin', $_SESSION['user_id']);
    $stmt_stats->execute();
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les derniers emails envoyés
    $sql_recent = "SELECT IdEmail, DestinataireEmail, DestinataireNom, Objet, DateEnvoi, TypeEmail 
                   FROM EmailAdmin 
                   WHERE IdAdmin = :id_admin 
                   ORDER BY DateEnvoi DESC LIMIT 10";
    $stmt_recent = $conn->prepare($sql_recent);
    $stmt_recent->bindParam(':id_admin', $_SESSION['user_id']);
    $stmt_recent->execute();
    $emails_recents = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = ['total_emails' => 0, 'total_destinataires' => 0, 'emails_30j' => 0];
    $emails_recents = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoi d'emails</title>
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
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .form-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .checkbox-item:hover {
            border-color: #667eea;
            background: #f9f9ff;
        }
        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        .checkbox-item.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .error {
            color: #f44336;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        .char-counter {
            text-align: right;
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }
        .history-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .history-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .email-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .email-item:hover {
            background: #f9f9f9;
        }
        .email-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .email-info p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        .email-date {
            color: #999;
            font-size: 0.85em;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .badge.general {
            background: #e3f2fd;
            color: #2196f3;
        }
        .badge.notification {
            background: #e8f5e9;
            color: #4caf50;
        }
        @media (max-width: 768px) {
            .checkbox-group {
                grid-template-columns: 1fr;
            }
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🎓 GestionEvents</div>
        <a href="dashboard.php" class="btn btn-secondary">Retour au dashboard</a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>📧 Envoi d'emails</h1>
            <p>Envoyez des emails aux organisateurs de clubs</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_emails']; ?></div>
                <div class="stat-label">Emails envoyés</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_destinataires']; ?></div>
                <div class="stat-label">Destinataires uniques</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['emails_30j']; ?></div>
                <div class="stat-label">Ce mois</div>
            </div>
        </div>

        <!-- Formulaire d'envoi -->
        <div class="form-section">
            <h3>✉️ Nouvel email</h3>
            
            <form method="POST" id="emailForm">
                <div class="form-group">
                    <label>Destinataires *</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item" for="tous_organisateurs">
                            <input type="checkbox" id="tous_organisateurs" name="destinataires[]" value="tous_organisateurs">
                            <span>📢 Tous les organisateurs (<?php echo count($organisateurs); ?>)</span>
                        </label>
                        
                        <?php foreach ($organisateurs as $org): ?>
                            <label class="checkbox-item" for="org_<?php echo $org['IdUtilisateur']; ?>">
                                <input type="checkbox" id="org_<?php echo $org['IdUtilisateur']; ?>" 
                                       name="destinataires[]" value="<?php echo $org['IdUtilisateur']; ?>">
                                <span><?php echo htmlspecialchars($org['Prenom'] . ' ' . $org['Nom']); ?><br>
                                    <small><?php echo htmlspecialchars($org['Email']); ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['destinataires'])): ?>
                        <span class="error"><?php echo htmlspecialchars($errors['destinataires']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="sujet">Sujet *</label>
                    <input type="text" 
                           id="sujet" 
                           name="sujet" 
                           placeholder="Ex: Information importante" 
                           required
                           maxlength="200"
                           value="<?php echo htmlspecialchars($sujet); ?>">
                    <div class="char-counter">
                        <span id="sujet-counter">0</span>/200
                    </div>
                    <?php if (isset($errors['sujet'])): ?>
                        <span class="error"><?php echo htmlspecialchars($errors['sujet']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="message">Message *</label>
                    <textarea id="message" 
                              name="message" 
                              placeholder="Tapez votre message ici..." 
                              required
                              maxlength="5000"><?php echo htmlspecialchars($message); ?></textarea>
                    <div class="char-counter">
                        <span id="message-counter">0</span>/5000
                    </div>
                    <?php if (isset($errors['message'])): ?>
                        <span class="error"><?php echo htmlspecialchars($errors['message']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('emailForm').reset();">
                        Effacer
                    </button>
                    <button type="submit" class="btn btn-primary">
                        📤 Envoyer l'email
                    </button>
                </div>
            </form>
        </div>

        <!-- Historique des emails -->
        <?php if (!empty($emails_recents)): ?>
            <div class="history-section">
                <h3>📋 Emails récents</h3>
                
                <?php foreach ($emails_recents as $email): ?>
                    <div class="email-item">
                        <div class="email-info">
                            <h4><?php echo htmlspecialchars($email['Objet']); ?></h4>
                            <p>
                                <strong>À :</strong> <?php echo htmlspecialchars($email['DestinataireNom']); ?>
                                (<?php echo htmlspecialchars($email['DestinataireEmail']); ?>)
                            </p>
                        </div>
                        <div class="email-date">
                            <?php echo date('d/m/Y à H:i', strtotime($email['DateEnvoi'])); ?>
                            <br>
                            <span class="badge <?php echo $email['TypeEmail'] == 'general' ? 'general' : 'notification'; ?>">
                                <?php echo ucfirst($email['TypeEmail']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Compteur de caractères pour le sujet
        document.getElementById('sujet').addEventListener('input', function() {
            document.getElementById('sujet-counter').textContent = this.value.length;
        });

        // Compteur de caractères pour le message
        document.getElementById('message').addEventListener('input', function() {
            document.getElementById('message-counter').textContent = this.value.length;
        });

        // Gestion des checkboxes
        document.querySelectorAll('.checkbox-item input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const item = this.closest('.checkbox-item');
                if (this.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        });

        // Gestion du "Tous les organisateurs"
        document.getElementById('tous_organisateurs').addEventListener('change', function() {
            const individualCheckboxes = document.querySelectorAll('.checkbox-item input[type="checkbox"]:not(#tous_organisateurs)');
            
            if (this.checked) {
                // Décocher tous les autres
                individualCheckboxes.forEach(cb => {
                    cb.checked = false;
                    cb.closest('.checkbox-item').classList.remove('selected');
                });
            }
        });

        // Gestion des checkboxes individuelles
        document.querySelectorAll('.checkbox-item input[type="checkbox"]:not(#tous_organisateurs)').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    // Décocher "Tous les organisateurs"
                    document.getElementById('tous_organisateurs').checked = false;
                    document.getElementById('tous_organisateurs').closest('.checkbox-item').classList.remove('selected');
                }
            });
        });

        // Initialiser les compteurs
        document.getElementById('sujet-counter').textContent = document.getElementById('sujet').value.length;
        document.getElementById('message-counter').textContent = document.getElementById('message').value.length;
    </script>
</body>
</html>