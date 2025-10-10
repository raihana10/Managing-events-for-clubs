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
    <title>Modifier l'événement</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .price-fields { display: none; margin-top: 10px; }
        .price-fields.show { display: block; }
        .price-input { display: inline-block; width: 30%; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Modifier l'événement</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($event): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="nom_evenement">Nom de l'événement *</label>
                    <input type="text" id="nom_evenement" name="nom_evenement" 
                           value="<?php echo htmlspecialchars($event['NomEvenement']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="type">Type d'événement *</label>
                    <input type="text" id="type" name="type" 
                           value="<?php echo htmlspecialchars($event['TypeEvenement']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="lieu">Lieu *</label>
                    <input type="text" id="lieu" name="lieu" 
                           value="<?php echo htmlspecialchars($event['Lieu']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" 
                           value="<?php echo $event['Date']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="heure_debut">Heure de début *</label>
                    <input type="time" id="heure_debut" name="heure_debut" 
                           value="<?php echo substr($event['HeureDebut'], 0, 5); ?>" required>
                </div>

                <div class="form-group">
                    <label for="heure_fin">Heure de fin *</label>
                    <input type="time" id="heure_fin" name="heure_fin" 
                           value="<?php echo substr($event['HeureFin'], 0, 5); ?>" required>
                </div>

                <div class="form-group">
                    <label for="participant">Type de participant *</label>
                    <select id="participant" name="participant" required onchange="togglePriceFields()">
                        <option value="Tous" <?php echo $event['TypeParticipant'] == 'Tous' ? 'selected' : ''; ?>>Tous</option>
                        <option value="Adhérents" <?php echo $event['TypeParticipant'] == 'Adhérents' ? 'selected' : ''; ?>>Adhérents uniquement</option>
                        <option value="Membres uniquement" <?php echo $event['TypeParticipant'] == 'Membres uniquement' ? 'selected' : ''; ?>>Membres uniquement</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="capacite_max">Capacité maximale</label>
                    <input type="number" id="capacite_max" name="capacite_max" 
                           value="<?php echo $event['CapaciteMax']; ?>" min="1">
                </div>

                <div class="form-group">
                    <label>Prix selon le type de participant</label>
                    <div class="price-fields" id="price-fields">
                        <div class="price-input">
                            <label for="prix_adherent">Prix adhérent (€)</label>
                            <input type="number" step="0.01" min="0" id="prix_adherent" name="prix_adherent" 
                                   value="<?php echo $event['PrixAdherent']; ?>">
                        </div>
                        <div class="price-input">
                            <label for="prix_non_adherent">Prix non-adhérent (€)</label>
                            <input type="number" step="0.01" min="0" id="prix_non_adherent" name="prix_non_adherent" 
                                   value="<?php echo $event['PrixNonAdherent']; ?>">
                        </div>
                        <div class="price-input">
                            <label for="prix_externe">Prix externe (€)</label>
                            <input type="number" step="0.01" min="0" id="prix_externe" name="prix_externe" 
                                   value="<?php echo $event['PrixExterne']; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($event['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Modifier l'événement</button>
                    <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        <?php else: ?>
            <p>Événement introuvable.</p>
            <a href="dashboard.php" class="btn btn-secondary">Retour au dashboard</a>
        <?php endif; ?>
    </div>

    <script>
        function togglePriceFields() {
            const participant = document.getElementById('participant').value;
            const priceFields = document.getElementById('price-fields');
            const prixAdherent = document.getElementById('prix_adherent');
            const prixNonAdherent = document.getElementById('prix_non_adherent');
            const prixExterne = document.getElementById('prix_externe');

            if (participant === 'Adhérents') {
                priceFields.classList.add('show');
                prixAdherent.style.display = 'block';
                prixNonAdherent.style.display = 'none';
                prixExterne.style.display = 'none';
            } else if (participant === 'Membres uniquement') {
                priceFields.classList.add('show');
                prixAdherent.style.display = 'block';
                prixNonAdherent.style.display = 'block';
                prixExterne.style.display = 'none';
            } else if (participant === 'Tous') {
                priceFields.classList.add('show');
                prixAdherent.style.display = 'block';
                prixNonAdherent.style.display = 'block';
                prixExterne.style.display = 'block';
            } else {
                priceFields.classList.remove('show');
            }
        }

        // Initialiser l'affichage des prix
        togglePriceFields();
    </script>
</body>
</html>
