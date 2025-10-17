<?php
require('fpdf186/fpdf.php');
require('config.php');

// Vérification du paramètre
if (!isset($_GET['IdInscription'])) {
    die("❌ IdInscription manquant !");
}

$idInscription = intval($_GET['IdInscription']);

// 🔹 Requête principale avec vérification d’adhésion
$sql = "
SELECT 
    u.Nom, u.Prenom,
    e.NomEvenement, e.Date, e.Lieu,
    c.NomClub,
    a.Status AS StatutAdhesion
FROM inscription i
JOIN utilisateur u ON u.IdUtilisateur = i.IdUtilisateur
JOIN evenement e ON e.IdEvenement = i.IdEvenement
JOIN club c ON c.IdClub = e.IdClub
LEFT JOIN adhesion a ON a.IdParticipant = i.IdUtilisateur AND a.IdClub = e.IdClub
WHERE i.IdInscription = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idInscription]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("❌ Aucune donnée trouvée pour cette inscription.");
}

// Variables
$nomComplet = strtoupper($data['Prenom'] . ' ' . $data['Nom']);
$evenement = $data['NomEvenement'];
$dateEvent = date("d/m/Y", strtotime($data['Date']));
$lieu = $data['Lieu'];
$club = strtoupper($data['NomClub']);
$adhesion = ($data['StatutAdhesion'] === 'actif') ? "Adhérent actif du club" : "Participant invité";

// Classe personnalisée
class PDF_Attestation extends FPDF {

    // Simulation d’un dégradé horizontal
    function Gradient($x, $y, $w, $h, $startColor, $endColor) {
        [$r1, $g1, $b1] = $startColor;
        [$r2, $g2, $b2] = $endColor;
        $steps = 100;
        for ($i = 0; $i <= $steps; $i++) {
            $r = $r1 + ($r2 - $r1) * $i / $steps;
            $g = $g1 + ($g2 - $g1) * $i / $steps;
            $b = $b1 + ($b2 - $b1) * $i / $steps;
            $this->SetFillColor($r, $g, $b);
            $this->Rect($x + ($i * $w / $steps), $y, $w / $steps, $h, 'F');
        }
    }

    function Header() {
        // Bande dégradée en haut
        $this->Gradient(0, 0, 297, 25, [255,107,107], [69,183,209]); // rouge→bleu
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 15, utf8_decode("Attestation officielle de participation"), 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer() {
        // Bande dégradée bas (violet→rose)
        $this->Gradient(0, 190, 297, 25, [102,126,234], [118,75,162]);
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, utf8_decode("© " . date("Y") . " - Tous droits réservés"), 0, 0, 'C');
    }
}

// Création du PDF
$pdf = new PDF_Attestation('L', 'mm', 'A4');
$pdf->AddPage();

// Cadre extérieur doux
$pdf->SetDrawColor(240, 85, 120); // rose
$pdf->SetLineWidth(1.8);
$pdf->Rect(10, 10, 277, 190, 'D');

// Logo du club
if (file_exists("logos/$club.png")) {
    $pdf->Image("logos/$club.png", 20, 30, 35);
}

// Titre
$pdf->Ln(20);
$pdf->SetFont('Arial', 'B', 28);
$pdf->SetTextColor(69,183,209); // bleu clair
$pdf->Cell(0, 15, utf8_decode("ATTESTATION DE PARTICIPATION"), 0, 1, 'C');
$pdf->Ln(10);

// Texte principal
$pdf->SetFont('Arial', '', 16);
$pdf->SetTextColor(40, 40, 40);
$pdf->MultiCell(0, 10, utf8_decode("
Nous, membres du club $club, certifions que :
"), 0, 'C');
$pdf->Ln(5);

// Nom du participant
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor(255,107,107); // rouge corail
$pdf->Cell(0, 12, utf8_decode($nomComplet), 0, 1, 'C');
$pdf->Ln(8);

$pdf->SetFont('Arial', '', 16);
$pdf->SetTextColor(50, 50, 50);
$pdf->MultiCell(0, 10, utf8_decode("
a participé activement à l’événement :

« $evenement »

qui s’est tenu le $dateEvent à $lieu.
"), 0, 'C');
$pdf->Ln(5);

// Statut adhésion
$pdf->SetFont('Arial', 'I', 15);
$pdf->SetTextColor(118,75,162); // violet
$pdf->Cell(0, 10, utf8_decode($adhesion . " $club."), 0, 1, 'C');
$pdf->Ln(10);

// Date et signature
$pdf->SetFont('Arial', '', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, utf8_decode("Fait à Tétouan, le " . date("d/m/Y")), 0, 1, 'R');
$pdf->Ln(15);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(69,183,209);
$pdf->Cell(0, 10, utf8_decode("Le Président du Club $club"), 0, 1, 'R');

// Signature image
if (file_exists("uploads/signature/signaturePresident.jpg")) {
    $pdf->Image("uploads/signature/signaturePresident.jpg", 225, 145, 45);
}

// Sortie finale
$pdf->Output("I", "Attestation_$nomComplet.pdf");
?>
