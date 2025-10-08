<?php
session_start();

// Supprimer toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Détruire le cookie remember me
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time()-42000, '/');
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header("Location: login.php");
exit();
?>