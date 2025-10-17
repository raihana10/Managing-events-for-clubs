<?php
// utilisateur/_navbar.php
// Assurez-vous que session_start() a été appelé avant d'inclure ce fichier,
// généralement par session.php ou le fichier principal de la page.
if (!isset($_SESSION['user_id'])) {
    // Si l'utilisateur n'est pas connecté ici, quelque chose s'est mal passé
    // ou la page n'a pas inclus session.php avec redirectIfNotLoggedIn()
    header("Location: ../auth/login.php");
    exit();
}
?>
<nav style="display: none !important;">
    <div style="font-size: 1.5em; font-weight: bold;">GestionEvents</div>
    <div style="display: flex; gap: 15px;">
        <a href="dashboard.php" style="color: #007bff; text-decoration: none;">Accueil</a>
        <a href="clubs.php" style="color: #007bff; text-decoration: none;">Clubs</a>
        <a href="evenements.php" style="color: #007bff; text-decoration: none;">Événements</a>
        <a href="mes_inscriptions.php" style="color: #007bff; text-decoration: none;">Mes Inscriptions</a>
        <a href="parametres.php" style="color: #007bff; text-decoration: none;">Paramètres</a>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-weight: bold;"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></span>
        <a href="../auth/logout.php" style="background-color: #dc3545; color: white; padding: 8px 12px; border-radius: 5px; text-decoration: none;">Déconnexion</a>
    </div>
</nav>