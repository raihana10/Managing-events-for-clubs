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
                // Use absolute filesystem path for storing file and compute a web-accessible path
                $upload_dir_fs = realpath(__DIR__ . '/../uploads/affiches') ?: (__DIR__ . '/../uploads/affiches');
                if (!is_dir($upload_dir_fs)) {
                    mkdir($upload_dir_fs, 0755, true);
                }
                $new_file_name = uniqid('affiche_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                $destination_fs = rtrim($upload_dir_fs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $new_file_name;

                if (move_uploaded_file($file_tmp, $destination_fs)) {
                    // Try to compute a web path from the filesystem destination
                    $affiche_path = null;
                    if (isset($_SERVER['DOCUMENT_ROOT'])) {
                        $docroot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
                        $dest_norm = str_replace('\\', '/', realpath($destination_fs));
                        if ($docroot && $dest_norm && strpos($dest_norm, $docroot) === 0) {
                            $web_path = '/' . ltrim(substr($dest_norm, strlen($docroot)), '/');
                            $affiche_path = $web_path;
                        }
                    }

                    // Fallback to a relative web path if we couldn't build one from DOCUMENT_ROOT
                    if (empty($affiche_path)) {
                        $affiche_path = '../uploads/affiches/' . $new_file_name;
                    }

                    // Store both filesystem and web paths so other pages can reliably check existence
                    $affiche_path = [
                        'web' => $affiche_path,
                        'fs' => $destination_fs,
                    ];
                } else {
                    $error = "Erreur lors de l'enregistrement du fichier.";
                }
            }
        }

        // If no new file uploaded, keep previous affiche from session
        if ($affiche_path === null && !isset($error) && !empty($_SESSION['event_preview']['affiche'])) {
            $affiche_path = $_SESSION['event_preview']['affiche'];
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
            // Only store the web path in DB. $affiche_path may be array {web, fs} or string or null
            $affiche_db_value = null;
            if (!empty($affiche_path)) {
                if (is_array($affiche_path)) {
                    $affiche_db_value = $affiche_path['web'] ?? null;
                } else {
                    $affiche_db_value = $affiche_path;
                }
            }
            $stmt->bindParam(':affiche', $affiche_db_value);
            $stmt->bindParam(':prix_adherent', $prix_adherent);
            $stmt->bindParam(':prix_non_adherent', $prix_non_adherent);
            $stmt->bindParam(':prix_externe', $prix_externe);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':etat', $etat);

            try {
                $stmt->execute();
                    // Normalize affiche storage: always store array with 'web' and 'fs' keys
                    $affiche_for_session = null;
                    if (!empty($affiche_path)) {
                        if (is_array($affiche_path)) {
                            $affiche_for_session = $affiche_path;
                        } else {
                            $affiche_for_session = ['web' => $affiche_path, 'fs' => null];
                        }
                    }

                    $_SESSION['event_preview']=[
                    'id_club'=>$id_club,
                    'nom_evenement'=>$nom_evenement,
                    'heure_debut'=>$heure_debut,
                    'heure_fin'=>$heure_fin,
                    'date'=>$date,
                    'lieu'=>$lieu,
                    'type'=>$type,
                    'participant'=>$participant,
                    'capacite_max'=>$capacite_max,
                    'affiche'=>$affiche_for_session,
                    'prix_adherent'=>$prix_adherent,
                    'prix_non_adherent'=>$prix_non_adherent,
                    'prix_externe'=>$prix_externe,
                    'description'=>$description,
                    'etat'=>$etat
                ];
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
    <title>Créer un Événement - Event Manager</title>
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
                <div class="page-header">
                    <h1>📅 Créer un nouvel événement</h1>
                    <p>Remplissez les informations de l'événement</p>
                </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert-error-modern">
                            <strong>Erreur :</strong> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert-success-modern">
                            <strong>Succès :</strong> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="eventForm">
                    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token'] ?? ''); ?>">
                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">📝 Informations générales</h3>
                        
                        <div class="form-group-modern">
                            <label class="form-label-modern" for="nom_evenement">Nom de l'événement </label>
                            <input type="text" 
                                id="nom_evenement" 
                                name="nom_evenement" class="form-input-modern"
                                placeholder="Ex: Conférence sur l'IA" 
                                required
                                maxlength="200">
                            <div class="char-counter">
                                <span id="nom-counter">0</span>/200
                            </div>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern" for="id_club">Club organisateur *</label>
                            <select id="id_club" name="id_club" class="form-select-modern" required>
                                <option value="">-- Sélectionner un club --</option>
                                <?php foreach ($clubs as $club): ?>
                                    <option value="<?php echo $club['IdClub']; ?>">
                                        <?php echo htmlspecialchars($club['NomClub']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern" for="type">Type d'événement *</label>
                            <select id="type" name="type" class="form-select-modern" required>
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
                            <input type="text" id="type_autre" name="type_autre" class="form-input-modern" placeholder="Précisez le type d'événement" style="display:none; margin-top:8px;">
                        </div>
                    </div>

                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">📍 Lieu et date</h3>

                        <div class="form-group-modern">
                            <label class="form-label-modern" for="lieu">Lieu *</label>
                            <select id="lieu" name="lieu" class="form-select-modern" required>
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
                            <input type="text" id="lieu_autre" name="lieu_autre" class="form-input-modern" placeholder="Précisez le lieu" style="display:none; margin-top:8px;">
                        </div>

                        <!-- inscription single label and three price fields (moved below capacity in layout) -->

                        <div class="form-row">
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="date">Date de l'événement *</label>
                                <input type="date" 
                                    id="date" 
                                    name="date"
                                    class="form-input-modern" 
                                    required>
                            </div>

                            <div class="form-group-modern">
                                <label class="form-label-modern" for="participant">Participants</label>
                                <select id="participant" name="participant" class="form-select-modern">
                                    <option value="Adhérents">Adhérents</option>
                                    <option value="Ensatiens">Tous les Ensatiens</option>
                                    <option value="Tous">Ensatiens + Externes </option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="heure_debut">Heure de début *</label>
                                <input type="time" 
                                    id="heure_debut" 
                                    name="heure_debut" 
                                    class="form-input-modern"
                                    required>
                            </div>

                            <div class="form-group-modern">
                                <label class="form-label-modern" for="heure_fin">Heure de fin *</label>
                                <input type="time" 
                                    id="heure_fin" 
                                    name="heure_fin" 
                                    class="form-input-modern"
                                    required>
                            </div>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern" for="capacite_max">Capacité maximale</label>
                            <input type="number" 
                                id="capacite_max" 
                                name="capacite_max"
                                class="form-input-modern" 
                                placeholder="Ex: 50" 
                                min="1"
                                max="1000">
                            <div class="info-box" style="margin-top: 10px;">
                                <strong>ℹ️ Conseil :</strong> Laissez vide pour une capacité illimitée
                            </div>
                        </div>
                        <div class="form-group-modern" id="prix-fields" style="margin-top:10px; display:none;">
                            <label class="form-label-modern">Prix selon type de participant</label>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="number" step="0.01" min="0" id="prix_adherent" name="prix_adherent"
                                class="form-input-modern" placeholder="Prix adhérent" style="flex:1;">
                                <input type="number" step="0.01" min="0" id="prix_non_adherent" name="prix_non_adherent" class="form-input-modern" placeholder="Prix non-adhérent" style="flex:1;">
                                <input type="number" step="0.01" min="0" id="prix_externe" name="prix_externe" class="form-input-modern" placeholder="Prix externe" style="flex:1;">
                            </div>
                            
                        </div>

                        <div class="form-group-modern full-width">
                            <label class="form-label-modern" for="description">Description de l'événement</label>
                            <textarea id="description" name="description" class="form-textarea-modern" placeholder="Décrivez l'événement, le programme, les intervenants..." maxlength="2000"></textarea>
                            <div class="char-counter"><span id="desc-counter">0</span>/2000</div>
                        </div>
                    </div>

                    <div class="form-section-modern">
                        <h3 class="form-section-title-modern">Affiche de l'événement</h3>

                        <div class="form-group-modern">
                            <label class="file-upload "  for="affiche">
                                <div class="file-upload-content">
                                    
                                    <div class="file-upload-text">
                                        <div><strong>Cliquez pour télécharger une affiche</strong></div>
                                        <div class="file-upload-hint">PNG, JPG ou GIF (max 5MB)</div>
                                    </div>
                                </div>
                                <input type="file" 
                                    id="affiche" 
                                    name="affiche" 
                                    class="form-input-modern"
                                    accept="image/*">
                                <img id="affiche-preview" class="image-preview" alt="Aperçu de l'affiche">
                            </label>
                        </div>
                    </div>

                    

                    <div class="form-actions">
                        <a href="recap_evenements.php" class="btn btn-secondary">Annuler</a>
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

                    // Show existing affiche preview if present. eventPreview.affiche can be a string or an object {web, fs}
                    if (eventPreview.affiche) {
                        const preview = document.getElementById('affiche-preview');
                        const fileUploadLabel = document.querySelector('.file-upload');
                        let webPath = null;
                        if (typeof eventPreview.affiche === 'object') {
                            webPath = eventPreview.affiche.web || null;
                        } else if (typeof eventPreview.affiche === 'string') {
                            webPath = eventPreview.affiche;
                        }
                        if (preview && webPath) preview.src = webPath;
                        if (fileUploadLabel && webPath) fileUploadLabel.classList.add('has-preview');
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

       
        const participantSelect = document.getElementById('participant');
        const prixFields = document.getElementById('prix-fields');
        const prixAdherent = document.getElementById('prix_adherent');
        const prixNonAdherent = document.getElementById('prix_non_adherent');
        const prixExterne = document.getElementById('prix_externe');

        function updatePrixVisibility() {
            const val = participantSelect.value;
            
            if (val === 'Adhérents') {
                prixFields.style.display = 'block';
                prixAdherent.style.display = 'block'; prixNonAdherent.style.display = 'none'; prixExterne.style.display = 'none';
            } else if (val === 'Ensatiens') {
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
   
    <script src="../assets/js/main.js"></script>
</body>
</html>