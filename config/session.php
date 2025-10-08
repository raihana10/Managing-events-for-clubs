<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Backward compatible helper used by some pages
function redirectIfNotLoggedIn() {
    requireLogin();
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: ../index.php");
        exit();
    }
}

function isSuperAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'super_admin';
}

function isAdminClub() {
    return isLoggedIn() && $_SESSION['role'] === 'admin_club';
}

function isParticipant() {
    return isLoggedIn() && $_SESSION['role'] === 'participant';
}
?>