<?php
require_once '../config/database.php';
require_once '../config/session.php';
// Vérifier que c'est bien un admin de club
requireRole(['organisateur']);
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
// Récupérer les informations du club
$club_query = "SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$club = $stmt->fetch(PDO::FETCH_ASSOC);
$eventCountValide = 0;
if ($club) {
    // Compter les événements actifs du club
    $event_query = "SELECT COUNT(*) as event_count FROM Evenement 
                    WHERE IdClub = :club_id AND (Etat = 'valide' ) AND Date >= CURDATE()";
    $stmt = $db->prepare($event_query);
    $stmt->bindParam(':club_id', $club['IdClub']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $eventCountValide = $result ? (int)$result['event_count'] : 0;
}
$eventCountRefuse = 0;
if ($club) {
    // Compter les événements refusés du club
    $event_query = "SELECT COUNT(*) as event_count FROM Evenement 
                    WHERE IdClub = :club_id AND Etat = 'refuse' AND Date >= CURDATE()";
    $stmt = $db->prepare($event_query);
    $stmt->bindParam(':club_id', $club['IdClub']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $eventCountRefuse = $result ? (int)$result['event_count'] : 0;
}
$membreClub = 0;
if ($club) {
    // Compter les membres inscrits aux événements du club
   $membre_query = "SELECT COUNT(DISTINCT a.IdParticipant) AS membre_count
                 FROM Adhesion a
                 WHERE a.IdClub = :club_id AND a.Status = 'actif'";
    $stmt = $db->prepare($membre_query);
    $stmt->bindParam(':club_id', $club['IdClub']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $membreClub = $result ? (int)$result['membre_count'] : 0;
}
?>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Club</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #fafbfc;
            color: #1a202c;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .header-content {
            max-width: 1440px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .logo {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a202c;
            letter-spacing: -0.5px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .club-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f7fafc;
            border-radius: 6px;
            font-size: 0.875rem;
            color: #4a5568;
        }

        .club-badge {
            width: 8px;
            height: 8px;
            background: #48bb78;
            border-radius: 50%;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #2d3748;
        }

        .user-role {
            font-size: 0.75rem;
            color: #718096;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .btn-logout {
            padding: 0.625rem 1.25rem;
            background: #ffffff;
            color: #e53e3e;
            border: 1.5px solid #e53e3e;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #e53e3e;
            color: white;
        }

        /* Layout */
        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 2.5rem 2rem;
        }

        .layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 2.5rem;
        }

        /* Sidebar */
        .sidebar {
            background: #ffffff;
            border-radius: 10px;
            padding: 1.5rem 0;
            height: fit-content;
            position: sticky;
            top: 95px;
            border: 1px solid #e2e8f0;
        }

        .nav-section {
            margin-bottom: 2.5rem;
        }

        .nav-section:last-child {
            margin-bottom: 0;
        }

        .nav-title {
            font-size: 0.6875rem;
            font-weight: 700;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 1.25rem;
            margin-bottom: 0.75rem;
        }

        .nav-list {
            list-style: none;
        }

        .nav-item {
            margin: 0.125rem 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #4a5568;
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: #f7fafc;
            color: #2d3748;
        }

        .nav-link.active {
            background: #edf2f7;
            color: #667eea;
            font-weight: 600;
        }

        .nav-icon {
            width: 20px;
            margin-right: 0.875rem;
            font-size: 1rem;
            opacity: 0.7;
        }

        .nav-link.active .nav-icon {
            opacity: 1;
        }

        /* Main Content */
        .main-content {
            min-height: calc(100vh - 165px);
        }

        .page-title {
            margin-bottom: 2rem;
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .page-title p {
            color: #718096;
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.75rem;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: #cbd5e0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #718096;
            font-weight: 500;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.blue { background: #ebf4ff; color: #3182ce; }
        .stat-icon.green { background: #e6fffa; color: #38b2ac; }
        .stat-icon.orange { background: #fffaf0; color: #dd6b20; }
        .stat-icon.purple { background: #faf5ff; color: #805ad5; }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: #1a202c;
            line-height: 1;
        }

        .stat-change {
            font-size: 0.8125rem;
            color: #48bb78;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        /* Actions */
        .section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            background: #f7fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #2d3748;
            font-size: 0.9375rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .action-icon {
            font-size: 1.125rem;
        }

        /* Events */
        .btn-view-all {
            padding: 0.625rem 1.25rem;
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-view-all:hover {
            background: #edf2f7;
            color: #2d3748;
        }

        .event-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .event-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.75rem;
            transition: all 0.2s;
            background: #ffffff;
        }

        .event-card:hover {
            border-color: #cbd5e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.25rem;
            gap: 1rem;
        }

        .event-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a202c;
            flex: 1;
        }

        .event-date {
            background: #f7fafc;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #4a5568;
            white-space: nowrap;
            border: 1px solid #e2e8f0;
        }

        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-size: 0.875rem;
            color: #4a5568;
        }

        .meta-icon {
            color: #a0aec0;
            font-size: 1rem;
        }

        .badge {
            padding: 0.375rem 0.875rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.warning {
            background: #fffaf0;
            color: #c05621;
            border: 1px solid #fbd38d;
        }

        .badge.success {
            background: #f0fff4;
            color: #276749;
            border: 1px solid #9ae6b4;
        }

        .event-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline {
            background: #ffffff;
            color: #4a5568;
            border: 1.5px solid #e2e8f0;
        }

        .btn-outline:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            color: #2d3748;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            border: 1.5px solid #667eea;
        }

        .btn-primary:hover {
            background: #5a67d8;
            border-color: #5a67d8;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

            .club-info {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem 1rem;
            }

            .header-content {
                padding: 0 1rem;
            }

            .user-info {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .event-header {
                flex-direction: column;
                align-items: stretch;
            }

            .event-meta {
                grid-template-columns: 1fr;
            }

            .page-title h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">GestionEvents</div>
            <div class="header-right">
                <div class="club-info">
                    <span class="club-badge"></span>
                    <span>
                        <?php echo htmlspecialchars($club['NomClub']); ?> </span>
                </div>
                <div class="user-section">
                    <div class="user-info">
                         <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>
                        <div class="user-role">Administrateur du club </div>
                    </div>
                    <div class="user-avatar">AO</div>
                    <button class="btn-logout" onclick="window.location.href='../auth/logout.php'">Déconnexion</button>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="layout">
            <aside class="sidebar">
                <nav class="nav-section">
                    <div class="nav-title">Gestion</div>
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active">
                                <span class="nav-icon">▪</span>
                                Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="mes_evenements.php" class="nav-link">
                                <span class="nav-icon">▪</span>
                                Mes événements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="creer_evenement.php" class="nav-link">
                                <span class="nav-icon">▪</span>
                                Créer événement
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="membres.php" class="nav-link">
                                <span class="nav-icon">▪</span>
                                Membres
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="envoyer_email.php" class="nav-link">
                                <span class="nav-icon">▪</span>
                                Communication
                            </a>
                        </li>
                    </ul>
                </nav>

                <nav class="nav-section">
                    <div class="nav-title">Personnel</div>
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="../utilisateur/mes_inscriptions.php" class="nav-link">
                                <span class="nav-icon">▪</span>
                                Mes inscriptions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../utilisateur/clubs.php" class="nav-link">
                                <span class="nav-icon">▪</span>
                                Autres clubs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="parametres.php" class="nav-link">
                                <span class="nav-icon">▪</span>
                                Paramètres
                            </a>
                        </li>
                    </ul>
                </nav>
            </aside>

            <main class="main-content">
                <div class="page-title">
                    <h1>Tableau de bord</h1>
                    <p>Vue d'ensemble de l'activité du club</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-label">Événements validés</div>
                            <div class="stat-icon blue">✔</div>
                        </div>
                        <div class="stat-value"><?php echo htmlspecialchars($eventCountValide); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-label">Événements refusés</div>
                            <div class="stat-icon green">❌</div>
                        </div>
                        <div class="stat-value"><?php echo htmlspecialchars($eventCountRefuse); ?></div>
                        
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-label">Les Adherents</div>
                            <div class="stat-icon orange">✓</div>
                        </div>
                        <div class="stat-value"><?php echo htmlspecialchars($membreClub); ?></div>
                        <div class="stat-change">↑ 18 cette semaine</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-label">Communications</div>
                            <div class="stat-icon purple">📧</div>
                        </div>
                        <div class="stat-value">52</div>
                        
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-header">
                        <h2>Actions rapides</h2>
                    </div>
                    <div class="actions-grid">
                        <a href="creer_event.php" class="action-btn">
                            <span class="action-icon">+</span>
                            Créer un événement
                        </a>
                        <a href="membres.php" class="action-btn">
                            <span class="action-icon">👥</span>
                            Gérer les membres
                        </a>
                        <a href="envoyer_email.php" class="action-btn">
                            <span class="action-icon">📧</span>
                            Envoyer une communication
                        </a>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-header">
                        <h2>Événements à venir</h2>
                        <a href="mes_evenements.php" class="btn-view-all">Voir tout →</a>
                    </div>

                    <div class="event-list">
                        <div class="event-card">
                            <div class="event-header">
                                <div class="event-title">Hackathon 2025</div>
                                <div class="event-date">15 Oct 2025</div>
                            </div>
                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <span class="meta-icon">📍</span>
                                    <span>Amphithéâtre</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="meta-icon">⏰</span>
                                    <span>09:00 - 18:00</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="meta-icon">👥</span>
                                    <span>45/50 inscrits</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="badge warning">Bientôt complet</span>
                                </div>
                            </div>
                            <div class="event-actions">
                                <a href="modifier_evenement.php?id=1" class="btn btn-outline">Modifier</a>
                                <a href="participants.php?id=1" class="btn btn-outline">Participants</a>
                                <a href="envoyer_rappel.php?id=1" class="btn btn-primary">Envoyer rappel</a>
                            </div>
                        </div>

                        <div class="event-card">
                            <div class="event-header">
                                <div class="event-title">Conférence Intelligence Artificielle</div>
                                <div class="event-date">22 Oct 2025</div>
                            </div>
                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <span class="meta-icon">📍</span>
                                    <span>Salle de conférence</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="meta-icon">⏰</span>
                                    <span>14:00 - 17:00</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="meta-icon">👥</span>
                                    <span>28/40 inscrits</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="badge success">Places disponibles</span>
                                </div>
                            </div>
                            <div class="event-actions">
                                <a href="modifier_evenement.php?id=2" class="btn btn-outline">Modifier</a>
                                <a href="participants.php?id=2" class="btn btn-outline">Participants</a>
                                <a href="envoyer_rappel.php?id=2" class="btn btn-primary">Envoyer rappel</a>
                            </div>
                        </div>

                        <div class="event-card">
                            <div class="event-header">
                                <div class="event-title">Formation Git & GitHub</div>
                                <div class="event-date">05 Nov 2025</div>
                            </div>
                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <span class="meta-icon">📍</span>
                                    <span>Salle 3</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="meta-icon">⏰</span>
                                    <span>10:00 - 13:00</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="meta-icon">👥</span>
                                    <span>15/25 inscrits</span>
                                </div>
                                <div class="event-meta-item">
                                    <span class="badge success">Places disponibles</span>
                                </div>
                            </div>
                            <div class="event-actions">
                                <a href="modifier_evenement.php?id=3" class="btn btn-outline">Modifier</a>
                                <a href="participants.php?id=3" class="btn btn-outline">Participants</a>
                                <a href="envoyer_rappel.php?id=3" class="btn btn-primary">Envoyer rappel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>