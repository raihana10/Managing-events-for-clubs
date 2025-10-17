<!-- utilisateur/_sidebar.php -->
<aside style="
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: white;
    border-right: 1px solid #e2e6ea;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 100;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
">
    <!-- Logo -->
    <div style="padding: 20px; border-bottom: 1px solid #e2e6ea;">
        <a href="dashboard.php" style="
            font-size: 1.5rem;
            font-weight: 700;
            color: #ff6b6b;
            text-decoration: none;
            display: block;
        ">Event Manager</a>
    </div>

    <!-- Section utilisateur -->
    <div style="padding: 20px; border-bottom: 1px solid #e2e6ea; text-align: center;">
        <div style="
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ff6b6b, #4ecdc4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin: 0 auto 10px auto;
        ">
            <?php echo strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1)); ?>
        </div>
        <div style="font-weight: 600; color: #333; margin-bottom: 5px;">
            <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>
        </div>
        <div style="font-size: 0.75rem; color: #666; text-transform: uppercase;">
            Participant
        </div>
    </div>

    <!-- Navigation -->
    <div style="flex: 1; padding: 20px 0;">
        <!-- Section Personnel -->
        <div style="margin-bottom: 30px;">
            <h3 style="
                font-size: 0.75rem;
                font-weight: 700;
                color: #999;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin: 0 20px 15px 20px;
            ">Personnel</h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="margin-bottom: 5px;">
                    <a href="dashboard.php" style="
                        display: flex;
                        align-items: center;
                        padding: 12px 20px;
                        color: <?php echo ($currentPage === 'accueil') ? 'white' : '#666'; ?>;
                        text-decoration: none;
                        background: <?php echo ($currentPage === 'accueil') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.background='<?php echo ($currentPage === 'accueil') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : '#f8f9fa'; ?>'" onmouseout="this.style.background='<?php echo ($currentPage === 'accueil') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>'">
                        <span style="margin-right: 10px; font-size: 1.1rem;">📊</span>
                        Tableau de bord
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="mes_inscriptions.php" style="
                        display: flex;
                        align-items: center;
                        padding: 12px 20px;
                        color: <?php echo ($currentPage === 'mes-inscriptions') ? 'white' : '#666'; ?>;
                        text-decoration: none;
                        background: <?php echo ($currentPage === 'mes-inscriptions') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.background='<?php echo ($currentPage === 'mes-inscriptions') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : '#f8f9fa'; ?>'" onmouseout="this.style.background='<?php echo ($currentPage === 'mes-inscriptions') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>'">
                        <span style="margin-right: 10px; font-size: 1.1rem;">📋</span>
                        Mes inscriptions
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="parametres.php" style="
                        display: flex;
                        align-items: center;
                        padding: 12px 20px;
                        color: <?php echo ($currentPage === 'parametres') ? 'white' : '#666'; ?>;
                        text-decoration: none;
                        background: <?php echo ($currentPage === 'parametres') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.background='<?php echo ($currentPage === 'parametres') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : '#f8f9fa'; ?>'" onmouseout="this.style.background='<?php echo ($currentPage === 'parametres') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>'">
                        <span style="margin-right: 10px; font-size: 1.1rem;">⚙️</span>
                        Paramètres
                    </a>
                </li>
            </ul>
        </div>

        <!-- Section Clubs & Événements -->
        <div style="margin-bottom: 30px;">
            <h3 style="
                font-size: 0.75rem;
                font-weight: 700;
                color: #999;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin: 0 20px 15px 20px;
            ">Clubs & Événements</h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="margin-bottom: 5px;">
                    <a href="mesClubs.php" style="
                        display: flex;
                        align-items: center;
                        padding: 12px 20px;
                        color: <?php echo ($currentPage === 'mes-clubs') ? 'white' : '#666'; ?>;
                        text-decoration: none;
                        background: <?php echo ($currentPage === 'mes-clubs') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.background='<?php echo ($currentPage === 'mes-clubs') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : '#f8f9fa'; ?>'" onmouseout="this.style.background='<?php echo ($currentPage === 'mes-clubs') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>'">
                        <span style="margin-right: 10px; font-size: 1.1rem;">🏛️</span>
                        Mes Clubs
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="clubs.php" style="
                        display: flex;
                        align-items: center;
                        padding: 12px 20px;
                        color: <?php echo ($currentPage === 'tous-les-clubs') ? 'white' : '#666'; ?>;
                        text-decoration: none;
                        background: <?php echo ($currentPage === 'tous-les-clubs') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.background='<?php echo ($currentPage === 'tous-les-clubs') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : '#f8f9fa'; ?>'" onmouseout="this.style.background='<?php echo ($currentPage === 'tous-les-clubs') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>'">
                        <span style="margin-right: 10px; font-size: 1.1rem;">🌍</span>
                        Tous les Clubs
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="evenements.php" style="
                        display: flex;
                        align-items: center;
                        padding: 12px 20px;
                        color: <?php echo ($currentPage === 'evenements') ? 'white' : '#666'; ?>;
                        text-decoration: none;
                        background: <?php echo ($currentPage === 'evenements') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.background='<?php echo ($currentPage === 'evenements') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : '#f8f9fa'; ?>'" onmouseout="this.style.background='<?php echo ($currentPage === 'evenements') ? 'linear-gradient(135deg, #ff6b6b, #4ecdc4)' : 'transparent'; ?>'">
                        <span style="margin-right: 10px; font-size: 1.1rem;">📅</span>
                        Événements
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Bouton de déconnexion -->
    <div style="padding: 20px; border-top: 1px solid #e2e6ea;">
        <a href="../auth/logout.php" style="
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #dc3545;
            text-decoration: none;
            background: transparent;
            transition: all 0.2s ease;
            border-radius: 8px;
        " onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='transparent'">
            <span style="margin-right: 10px; font-size: 1.1rem;">🚪</span>
            Déconnexion
        </a>
    </div>
</aside>

<!-- Espace pour que le contenu ne soit pas caché par la sidebar -->
<div style="margin-left: 250px; min-height: 100vh; background: #f8f9fa;">