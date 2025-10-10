<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);
$database = new Database();
$db = $database->getConnection();
// Ensure PDO throws exceptions for easier debugging
try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // ignore if already set or not supported; we'll still try to continue
}

$user_id = $_SESSION['user_id'];
$club_query = "SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD']==='POST'){
    // Verify single-use form token to prevent duplicate submissions
    if (!isset($_POST['form_token']) || !isset($_SESSION['form_token']) || !hash_equals($_SESSION['form_token'], $_POST['form_token'])) {
        $error = "Formulaire invalide ou déjà soumis.";
    }

    
    if (isset($_SESSION['form_token'])) {
        unset($_SESSION['form_token']);
    }

    $nom_evenement = trim($_POST['nom_evenement']);
    $id_club = (int)$_POST['id_club'];
    $type = trim($_POST['type']);
    $lieu = trim($_POST['lieu']);
    $type_autre = isset($_POST['type_autre']) ? trim($_POST['type_autre']) : '';
    $lieu_autre = isset($_POST['lieu_autre']) ? trim($_POST['lieu_autre']) : '';
    $date = $_POST['date'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $participant = trim($_POST['participant']);
    $capacite_max = !empty($_POST['capacite_max']) ? (int)$_POST['capacite_max'] : null;
    // Prices for different participant types
    $prix_adherent = isset($_POST['prix_adherent']) && $_POST['prix_adherent'] !== '' ? (float)str_replace(',', '.', $_POST['prix_adherent']) : 0;
    $prix_non_adherent = isset($_POST['prix_non_adherent']) && $_POST['prix_non_adherent'] !== '' ? (float)str_replace(',', '.', $_POST['prix_non_adherent']) : 0;
    $prix_externe = isset($_POST['prix_externe']) && $_POST['prix_externe'] !== '' ? (float)str_replace(',', '.', $_POST['prix_externe']) : 0;
    $description = trim($_POST['description'] ?? '');

    $etat = 'en attente';

    // If 'Autre' selected, override with the custom value
    if ($type === 'Autre' && $type_autre !== '') {
        $type = $type_autre;
    }
    if ($lieu === 'Autre' && $lieu_autre !== '') {
        $lieu = $lieu_autre;
    }

    // Validation simple
    if (empty($nom_evenement) || empty($id_club) || empty($type) || empty($lieu) || empty($date) || empty($heure_debut) || empty($heure_fin)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($heure_fin <= $heure_debut) {
        $error = "L'heure de fin doit être après l'heure de début.";
    } else {
        // Gestion de l'image entré comme affiche de l'événement
        $affiche_path = null;
        if (isset($_FILES['affiche']) && $_FILES['affiche']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['affiche']['tmp_name'];
            $file_name = basename($_FILES['affiche']['name']);
            $file_size = $_FILES['affiche']['size'];
            $file_type = mime_content_type($file_tmp);
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];

            if (!in_array($file_type, $allowed_types)) {
                $error = "Type de fichier non autorisé.";
            } elseif ($file_size > 5 * 1024 * 1024) {
                $error = "Le fichier est trop volumineux.";
            } else {
                $upload_dir = '../uploads/affiches/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $new_file_name = uniqid('affiche_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $destination)) {
                    $affiche_path = $destination;
                } else {
                    $error = "Erreur lors de l'enregistrement du fichier.";
                }
            }
        }

        // Si pas d'erreur, on insère l'événement
        if (!isset($error)) {
            // Use separate price columns for adherent / non-adherent / externe

            $insert_query = "INSERT INTO Evenement (
                IdClub, NomEvenement, HeureDebut, HeureFin, Date, Lieu,
                TypeEvenement, TypeParticipant, CapaciteMax, Affiche, PrixAdherent, PrixNonAdherent, PrixExterne, description, Etat
            ) VALUES (
                :id_club, :nom_evenement, :heure_debut, :heure_fin, :date, :lieu,
                :type_evenement, :participant, :capacite_max, :affiche, :prix_adherent, :prix_non_adherent, :prix_externe, :description, :etat
            )";

            $stmt = $db->prepare($insert_query);
            $stmt->bindParam(':id_club', $id_club);
            $stmt->bindParam(':nom_evenement', $nom_evenement);
            $stmt->bindParam(':heure_debut', $heure_debut);
            $stmt->bindParam(':heure_fin', $heure_fin);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':lieu', $lieu);
            $stmt->bindParam(':type_evenement', $type);
            $stmt->bindParam(':participant', $participant);
            $stmt->bindParam(':capacite_max', $capacite_max);
            $stmt->bindParam(':affiche', $affiche_path);
            $stmt->bindParam(':prix_adherent', $prix_adherent);
            $stmt->bindParam(':prix_non_adherent', $prix_non_adherent);
            $stmt->bindParam(':prix_externe', $prix_externe);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':etat', $etat);

            try {
                $stmt->execute();
                // Redirect without GET parameters (user requested no GET usage)
                header("Location: recap_evenements.php");
                exit();
            } catch (PDOException $e) {
                // Provide a readable error on the page and write to PHP error log
                $error = 'Erreur lors de l\'enregistrement: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }
}
$club_query ="SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate a one-time form token
if (empty($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(16));
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Événement</title>
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
            min-height: 100px;
        }
        
        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: #667eea;
            background: #f9f9ff;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        .file-upload-content { display:flex; flex-direction:column; align-items:center; gap:8px; }
        .file-upload-icon {
            font-size: 2.8em;
            margin-bottom: 0;
        }

        .file-upload-text { color: #333; }
        .file-upload-hint { font-size: 0.9em; color: #666; margin-top: 5px; }

        .image-preview {
            max-width: 100%;
            max-height: 250px;
            margin: 15px auto 0;
            border-radius: 8px;
            display: none;
            object-fit: contain;
        }

        /* When a preview is visible, hide the helper text/icon */
        .file-upload.has-preview .file-upload-content { display: none; }
        .file-upload.has-preview .image-preview { display: block; }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .info-box strong {
            color: #1565c0;
        }
        
        .char-counter {
            text-align: right;
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }

        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .input-icon {
            font-size: 1.2em;
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
            
            .form-row {
                grid-template-columns: 1fr;
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
                <h1>📅 Créer un nouvel événement</h1>
                <p>Remplissez les informations de l'événement</p>
            </div>

                <?php if (!empty($error)): ?>
                    <div class="info-box" style="background:#fdecea; border-left-color:#f44336; color:#611a15; margin-bottom:15px;">
                        <strong>Erreur :</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="info-box" style="background:#e8f5e9; border-left-color:#4caf50; color:#144620; margin-bottom:15px;">
                        <strong>Succès :</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="eventForm">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token'] ?? ''); ?>">
                <div class="form-section">
                    <h3>📝 Informations générales</h3>
                    
                    <div class="form-group">
                        <label for="nom_evenement">Nom de l'événement </label>
                        <input type="text" 
                               id="nom_evenement" 
                               name="nom_evenement" 
                               placeholder="Ex: Conférence sur l'IA" 
                               required
                               maxlength="200">
                        <div class="char-counter">
                            <span id="nom-counter">0</span>/200
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_club">Club organisateur *</label>
                        <select id="id_club" name="id_club" required>
                            <option value="">-- Sélectionner un club --</option>
                            <?php foreach ($clubs as $club): ?>
                                <option value="<?php echo $club['IdClub']; ?>">
                                    <?php echo htmlspecialchars($club['NomClub']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="type">Type d'événement *</label>
                        <select id="type" name="type" required>
                            <option value="">-- Sélectionner un type --</option>
                            <option value="Conférence">Conférence</option>
                            <option value="Atelier">Atelier</option>
                            <option value="Compétition">Compétition</option>
                            <option value="Formation">Formation</option>
                            <option value="Séminaire">Séminaire</option>
                            <option value="Sortie">Sortie</option>
                            <option value="AG">Assemblée Générale</option>
                            <option value="Soirée">Soirée Traditionnelle</option>
                            <option value="Autre">Autre</option>
                        </select>
                        <input type="text" id="type_autre" name="type_autre" placeholder="Précisez le type d'événement" style="display:none; margin-top:8px;">
                    </div>
                </div>

                <div class="form-section">
                    <h3>📍 Lieu et date</h3>
                    
                    <div class="form-group">
                        <label for="lieu">Lieu *</label>
                            <select id="lieu" name="lieu" required>
                            <option value="">-- Sélectionner un lieu --</option>
                            <option value="Amphi">Amphi</option>
                            <option value="salle de lecture">Salle de lecture</option>
                            <option value="Salle 001">Salle 001</option>
                            <option value="Salle 002">Salle 002</option>
                            <option value="Salle 003">Salle 003</option>
                            <option value="Salle 004">Salle 004</option>
                            <option value="Salle 005">Salle 005</option>
                            <option value="Salle 101">Salle 101</option>
                            <option value="Salle 102">Salle 102</option>
                            <option value="Salle 103">Salle 103</option>
                            <option value="Salle 104">Salle 104</option>
                            <option value="Salle 105">Salle 105</option>
                            <option value="Salle 200">Salle 200</option>
                            <option value="Salle 201">Salle 201</option>
                            <option value="Salle 202">Salle 202</option>
                            <option value="Salle 203">Salle 203</option>
                            <option value="Salle 204">Salle 204</option>
                            <option value="Salle 205">Salle 205</option>
                            <option value="Extérieur">Extérieur</option>
                            <option value="En ligne">En ligne</option>

                            <option value="Autre">Autre</option>
                        </select>
                        <input type="text" id="lieu_autre" name="lieu_autre" placeholder="Précisez le lieu" style="display:none; margin-top:8px;">
                    </div>

                    <!-- inscription single label and three price fields (moved below capacity in layout) -->

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date de l'événement *</label>
                            <input type="date" 
                                   id="date" 
                                   name="date" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="participant">Participants</label>
                            <select id="participant" name="participant">
                                <option value="Adhérents">Adhérents</option>
                                <option value="Membres uniquement">Tous les Ensatiens</option>
                                <option value="Tous">Ensatiens + Externes </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="heure_debut">Heure de début *</label>
                            <input type="time" 
                                   id="heure_debut" 
                                   name="heure_debut" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="heure_fin">Heure de fin *</label>
                            <input type="time" 
                                   id="heure_fin" 
                                   name="heure_fin" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="capacite_max">Capacité maximale</label>
                        <input type="number" 
                               id="capacite_max" 
                               name="capacite_max" 
                               placeholder="Ex: 50" 
                               min="1"
                               max="1000">
                        <div class="info-box" style="margin-top: 10px;">
                            <strong>ℹ️ Conseil :</strong> Laissez vide pour une capacité illimitée
                        </div>
                    </div>
                    <div class="form-group" id="prix-fields" style="margin-top:10px; display:none;">
                        <label>Prix selon type de participant</label>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input type="number" step="0.01" min="0" id="prix_adherent" name="prix_adherent" placeholder="Prix adhérent" style="flex:1;">
                            <input type="number" step="0.01" min="0" id="prix_non_adherent" name="prix_non_adherent" placeholder="Prix non-adhérent" style="flex:1;">
                            <input type="number" step="0.01" min="0" id="prix_externe" name="prix_externe" placeholder="Prix externe" style="flex:1;">
                        </div>
                        <div class="info-box" style="margin-top:8px;">Ces prix seront stockés dans PrixAdherent, PrixNonAdherent, PrixExterne.</div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description de l'événement</label>
                        <textarea id="description" name="description" placeholder="Décrivez l'événement, le programme, les intervenants..." maxlength="2000"></textarea>
                        <div class="char-counter"><span id="desc-counter">0</span>/2000</div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Affiche de l'événement</h3>
                    
                    <div class="form-group">
                        <label class="file-upload" for="affiche">
                            <div class="file-upload-content">
                                
                                <div class="file-upload-text">
                                    <div><strong>Cliquez pour télécharger une affiche</strong></div>
                                    <div class="file-upload-hint">PNG, JPG ou GIF (max 5MB)</div>
                                </div>
                            </div>
                            <input type="file" 
                                   id="affiche" 
                                   name="affiche" 
                                   accept="image/*">
                            <img id="affiche-preview" class="image-preview" alt="Aperçu de l'affiche">
                        </label>
                    </div>
                </div>

                

                <div class="form-actions">
                    <a href="mes_evenements.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">✓ Créer l'événement</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($_SESSION['event_preview']) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <script>
        // Prefill the form when coming back from recap (Modifier)
        (function(){
            const eventPreview = <?php echo json_encode($_SESSION['event_preview']); ?>;
            document.addEventListener('DOMContentLoaded', function(){
                try {
                    const setEl = (id, val) => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        el.value = val !== undefined && val !== null ? val : '';
                        // trigger input/change listeners where appropriate
                        el.dispatchEvent(new Event('input'));
                    };

                    setEl('nom_evenement', eventPreview.nom_evenement);
                    setEl('id_club', eventPreview.id_club);
                    setEl('type', eventPreview.type);
                    setEl('type_autre', eventPreview.type_autre || '');
                    setEl('lieu', eventPreview.lieu);
                    setEl('lieu_autre', eventPreview.lieu_autre || '');
                    setEl('date', eventPreview.date);
                    setEl('heure_debut', eventPreview.heure_debut);
                    setEl('heure_fin', eventPreview.heure_fin);
                    setEl('participant', eventPreview.participant);
                    setEl('capacite_max', eventPreview.capacite_max);
                    setEl('prix_adherent', eventPreview.prix_adherent);
                    setEl('prix_non_adherent', eventPreview.prix_non_adherent);
                    setEl('prix_externe', eventPreview.prix_externe);
                    setEl('description', eventPreview.description || '');

                    // Update counters
                    const nomCounter = document.getElementById('nom-counter');
                    if (nomCounter && eventPreview.nom_evenement) nomCounter.textContent = eventPreview.nom_evenement.length;
                    const descCounter = document.getElementById('desc-counter');
                    if (descCounter && (eventPreview.description || '')) descCounter.textContent = (eventPreview.description || '').length;

                    // Trigger change events so that 'Autre' inputs and price visibility update
                    ['type','lieu','participant','id_club'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.dispatchEvent(new Event('change'));
                    });

                    // Show existing affiche preview if present
                    if (eventPreview.affiche) {
                        const preview = document.getElementById('affiche-preview');
                        const fileUploadLabel = document.querySelector('.file-upload');
                        if (preview) preview.src = eventPreview.affiche;
                        if (fileUploadLabel) fileUploadLabel.classList.add('has-preview');
                    }
                } catch (e) {
                    console.error('Error pre-filling form:', e);
                }
            });
        })();
    </script>
    <?php endif; ?>

    <script>
        // Compteur de caractères pour le nom
        document.getElementById('nom_evenement').addEventListener('input', function() {
            document.getElementById('nom-counter').textContent = this.value.length;
        });

        // Preview de l'affiche
        (function(){
            const fileInput = document.getElementById('affiche');
            const preview = document.getElementById('affiche-preview');
            const fileUploadLabel = fileInput.closest('.file-upload');

            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];

                if (!file) {
                    // no file chosen
                    preview.src = '';
                    fileUploadLabel.classList.remove('has-preview');
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    alert('Type de fichier non autorisé. Seules les images sont acceptées.');
                    fileInput.value = '';
                    preview.src = '';
                    fileUploadLabel.classList.remove('has-preview');
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    alert('Le fichier est trop volumineux. Taille maximale: 5MB');
                    fileInput.value = '';
                    preview.src = '';
                    fileUploadLabel.classList.remove('has-preview');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(ev) {
                    preview.src = ev.target.result;
                    fileUploadLabel.classList.add('has-preview');
                };
                reader.readAsDataURL(file);
            });

            // Support drag and drop file onto the label
            fileUploadLabel.addEventListener('drop', function(e) {
                e.preventDefault();
                const dtFile = e.dataTransfer.files[0];
                if (dtFile) {
                    fileInput.files = e.dataTransfer.files;
                    const evt = new Event('change');
                    fileInput.dispatchEvent(evt);
                }
                this.style.borderColor = '#e0e0e0';
                this.style.background = 'white';
            });
        })();

        // Animation du label file upload
        const fileUpload = document.querySelector('.file-upload');
        
        fileUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#667eea';
            this.style.background = '#f9f9ff';
        });

        fileUpload.addEventListener('dragleave', function() {
            this.style.borderColor = '#e0e0e0';
            this.style.background = 'white';
        });

        // Show/hide 'Autre' inputs for type and lieu
        const typeSelect = document.getElementById('type');
        const typeAutre = document.getElementById('type_autre');
        const lieuSelect = document.getElementById('lieu');
        const lieuAutre = document.getElementById('lieu_autre');

        function toggleTypeAutre() {
            if (typeSelect.value === 'Autre') {
                typeAutre.style.display = 'block';
                typeAutre.required = true;
            } else {
                typeAutre.style.display = 'none';
                typeAutre.required = false;
                typeAutre.value = '';
            }
        }

        function toggleLieuAutre() {
            if (lieuSelect.value === 'Autre') {
                lieuAutre.style.display = 'block';
                lieuAutre.required = true;
            } else {
                lieuAutre.style.display = 'none';
                lieuAutre.required = false;
                lieuAutre.value = '';
            }
        }

        typeSelect.addEventListener('change', toggleTypeAutre);
        lieuSelect.addEventListener('change', toggleLieuAutre);
        // initialize
        toggleTypeAutre(); toggleLieuAutre();

        // Toggle price fields based on participant selection
        const participantSelect = document.getElementById('participant');
        const prixFields = document.getElementById('prix-fields');
        const prixAdherent = document.getElementById('prix_adherent');
        const prixNonAdherent = document.getElementById('prix_non_adherent');
        const prixExterne = document.getElementById('prix_externe');

        function updatePrixVisibility() {
            const val = participantSelect.value;
            // Show the whole block if participants have pricing options
            if (val === 'Adhérents') {
                prixFields.style.display = 'block';
                prixAdherent.style.display = 'block'; prixNonAdherent.style.display = 'none'; prixExterne.style.display = 'none';
            } else if (val === 'Membres uniquement') {
                prixFields.style.display = 'block';
                prixAdherent.style.display = 'block'; prixNonAdherent.style.display = 'block'; prixExterne.style.display = 'none';
            } else if (val === 'Tous') {
                prixFields.style.display = 'block';
                prixAdherent.style.display = 'block'; prixNonAdherent.style.display = 'block'; prixExterne.style.display = 'block';
            } else {
                prixFields.style.display = 'none';
            }
        }

        participantSelect.addEventListener('change', updatePrixVisibility);
        updatePrixVisibility();

        

        // Validation des heures
        document.getElementById('heure_fin').addEventListener('change', function() {
            const debut = document.getElementById('heure_debut').value;
            const fin = this.value;
            
            if (debut && fin && fin <= debut) {
                alert('L\'heure de fin doit être après l\'heure de début');
                this.value = '';
            }
        });

        // Définir la date minimale à aujourd'hui
        const dateInput = document.getElementById('date');
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;

        // Confirmation avant de quitter si formulaire modifié
        let formModified = false;
        document.getElementById('eventForm').addEventListener('input', function() {
            formModified = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (formModified) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.getElementById('eventForm').addEventListener('submit', function(e) {
            formModified = false;
            // disable submit button to prevent double-click
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Envoi en cours...';
            }
            
            // Validation finale avant soumission
            const capacite = document.getElementById('capacite_max').value;
            if (capacite && capacite < 1) {
                e.preventDefault();
                alert('La capacité maximale doit être au moins 1');
                return false;
            }
        });
    </script>
</body>
</html>