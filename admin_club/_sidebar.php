<?php
// Sidebar partial for admin_club pages
// Determines the current script to set active classes dynamically
$current = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

function sidebar_is_active($targets, $current)
{
    if (is_string($targets)) {
        $targets = [$targets];
    }
    return in_array($current, $targets, true) ? ' active' : '';
}
?>
<aside class="sidebar-modern">
    <nav class="sidebar-nav-modern">
        <div class="sidebar-section-modern">
            <div class="sidebar-title-modern">Gestion</div>
            <ul class="sidebar-nav-modern">
                <li class="sidebar-nav-item-modern">
                    <a href="dashboard.php" class="sidebar-nav-link-modern<?php echo sidebar_is_active(['dashboard.php'], $current); ?>">
                        <div class="sidebar-nav-icon-modern">📊</div>
                        Tableau de bord
                    </a>
                </li>
                <li class="sidebar-nav-item-modern">
                    <a href="gerer_event.php" class="sidebar-nav-link-modern<?php echo sidebar_is_active(['gerer_event.php', 'mes_evenements.php', 'recap_evenements.php', 'evenement_detail.php', 'modifier_event.php'], $current); ?>">
                        <div class="sidebar-nav-icon-modern">📅</div>
                        Mes événements
                    </a>
                </li>
                <li class="sidebar-nav-item-modern">
                    <a href="creer_event.php" class="sidebar-nav-link-modern<?php echo sidebar_is_active(['creer_event.php'], $current); ?>">
                        <div class="sidebar-nav-icon-modern">➕</div>
                        Créer événement
                    </a>
                </li>
                <li class="sidebar-nav-item-modern">
                    <a href="membres.php" class="sidebar-nav-link-modern<?php echo sidebar_is_active(['membres.php'], $current); ?>">
                        <div class="sidebar-nav-icon-modern">👥</div>
                        Membres
                    </a>
                </li>
                <li class="sidebar-nav-item-modern">
                    <a href="envoyer_email.php" class="sidebar-nav-link-modern<?php echo sidebar_is_active(['envoyer_email.php'], $current); ?>">
                        <div class="sidebar-nav-icon-modern">📧</div>
                        Communication
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section-modern">
            <div class="sidebar-title-modern">Personnel</div>
            <ul class="sidebar-nav-modern">
                <li class="sidebar-nav-item-modern">
                    <a href="../admin_club/mes_inscriptions.php" class="sidebar-nav-link-modern<?php echo sidebar_is_active(['mes_inscriptions.php'], $current); ?>">
                        <div class="sidebar-nav-icon-modern">📋</div>
                        Mes inscriptions
                    </a>
                </li>
                <li class="sidebar-nav-item-modern">
                    <a href="parametres.php" class="sidebar-nav-link-modern<?php echo sidebar_is_active(['parametres.php'], $current); ?>">
                        <div class="sidebar-nav-icon-modern">⚙️</div>
                        Paramètres
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>
