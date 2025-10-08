<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Activer les erreurs JSON
header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($club_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID club invalide']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Vérifier si le club existe et est actif
    $query = "SELECT IdClub, NomClub FROM Club WHERE IdClub = :club_id AND Actif = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':club_id', $club_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Club introuvable']);
        exit();
    }

    $club = $stmt->fetch();

    // Vérifier si l'utilisateur est déjà membre
    $query = "SELECT IdAdhesion, Status FROM Adhesion 
              WHERE IdUtilisateur = :user_id AND IdClub = :club_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':club_id', $club_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $adhesion = $stmt->fetch();
        if ($adhesion['Status'] === 'actif') {
            echo json_encode(['success' => false, 'message' => 'Vous êtes déjà membre de ce club']);
            exit();
        } else {
            // Réactiver l'adhésion
            $query = "UPDATE Adhesion SET Status = 'actif', DateAdhesion = CURDATE() 
                      WHERE IdAdhesion = :adhesion_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':adhesion_id', $adhesion['IdAdhesion']);
            $stmt->execute();

            echo json_encode([
                'success' => true, 
                'message' => 'Vous avez rejoint le club ' . htmlspecialchars($club['NomClub'])
            ]);
            exit();
        }
    }

    // Créer une nouvelle adhésion
    $query = "INSERT INTO Adhesion (IdUtilisateur, IdClub, DateAdhesion, Status) 
              VALUES (:user_id, :club_id, CURDATE(), 'actif')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':club_id', $club_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Vous avez rejoint le club ' . htmlspecialchars($club['NomClub']) . ' avec succès !'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'adhésion']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>