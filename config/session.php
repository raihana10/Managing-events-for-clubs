<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Éviter les boucles de redirection
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'login.php') {
            header("Location: ../auth/login.php");
            exit();
        }
    }
}

// Backward compatible helper used by some pages
function redirectIfNotLoggedIn() {
    requireLogin();
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        // Rediriger vers la page appropriée selon le rôle
        switch($_SESSION['role']) {
            case 'administrateur':
                header("Location: ../super_admin/dashboard.php");
                break;
            case 'organisateur':
                header("Location: ../admin_club/dashboard.php");
                break;
            case 'participant':
                header("Location: ../utilisateur/dashboard.php");
                break;
            default:
                header("Location: ../auth/login.php");
                break;
        }
        exit();
    }
}

function isSuperAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'administrateur';
}

function isAdminClub() {
    return isLoggedIn() && $_SESSION['role'] === 'organisateur';
}

function isParticipant() {
    return isLoggedIn() && $_SESSION['role'] === 'participant';
}
?>