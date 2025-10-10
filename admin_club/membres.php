<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['organisateur']);
$database = new Database();
$db = $database->getConnection();
// Ensure PDO throws exceptions for easier debugging
try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // ignore if already set or not supported; we'll still try to continue
}

$user_id = $_SESSION['user_id'];
$club_query = "SELECT IdClub, NomClub FROM Club WHERE IdAdminClub = :user_id";
$stmt = $db->prepare($club_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participants - Hackathon 2025</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .navbar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-size: 1.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        .event-header h1 { font-size: 2em; margin-bottom: 15px; }
        .event-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-label { color: #666; }
        .actions-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
        }
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-success {
            background: #4caf50;
            color: white;
        }
        .btn-outline {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
        }
        .participants-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f9f9f9;
        }
        th {
            padding: 15px;
            text-align: left;
            color: #666;
            font-weight: 600;
            font-size: 0.9em;
            border-bottom: 2px solid #e0e0e0;
        }
        th input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
        }
        tbody tr:hover {
            background: #f9f9ff;
        }
        .participant-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .participant-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .participant-details h4 {
            margin: 0 0 3px 0;
            color: #333;
        }
        .participant-details p {
            margin: 0;
            font-size: 0.85em;
            color: #999;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .badge.success { background: #e8f5e9; color: #4caf50; }
        .badge.warning { background: #fff3e0; color: #ff9800; }
        .badge.info { background: #e3f2fd; color: #2196f3; }
        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 3px;
        }
        .btn-send { background: #e3f2fd; color: #2196f3; }
        .btn-send:hover { background: #2196f3; color: white; }
        .btn-check { background: #e8f5e9; color: #4caf50; }
        .btn-check:hover { background: #4caf50; color: white; }
        .btn-remove { background: #ffebee; color: #f44336; }
        .btn-remove:hover { background: #f44336; color: white; }
        .bulk-actions {
            background: #fff3e0;
            border: 2px solid #ff9800;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 15px;
        }
        .bulk-actions.active { display: flex; }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .actions-bar { flex-direction: column; }
            table { font-size: 0.85em; }
            .participant-info { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🎓 GestionEvents</div>
        <a href="mes_evenements.php" class="btn btn-outline" style="padding: 8px 20px;">← Mes événements</a>
    </nav>

    <div class="container">
        <div class="event-header">
            <h1>📋 Participants - Hackathon 2025</h1>
            <div class="event-meta">
                <div class="event-meta-item">
                    <span>📅</span>
                    <span>15 Octobre 2025</span>
                </div>
                <div class="event-meta-item">
                    <span>📍</span>
                    <span>Amphithéâtre </span>
                </div>
                <div class="event-meta-item">
                    <span>⏰</span>
                    <span>09:00 - 18:00</span>
                </div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value">45</div>
                <div class="stat-label">Total inscrits</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">38</div>
                <div class="stat-label">Présents</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">5</div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">32</div>
                <div class="stat-label">Attestations envoyées</div>
            </div>
        </div>

        <div class="actions-bar">
            <div class="search-box">
                <input type="text" 
                       placeholder="🔍 Rechercher un participant..." 
                       id="searchParticipant">
            </div>
            <button class="btn btn-primary" onclick="envoyerRappel()">
                📧 Envoyer un rappel
            </button>
            <button class="btn btn-success" onclick="exporterPDF()">
                📊 Exporter PDF
            </button>
            
        </div>

        <div class="bulk-actions" id="bulkActions">
            <strong><span id="selectedCount">0</span> participant(s) sélectionné(s)</strong>
            <button class="btn btn-primary btn-sm" onclick="envoyerEmailSelectionnes()">
                📧 Envoyer email
            </button>
            <button class="btn btn-success btn-sm" onclick="marquerPresents()">
                ✓ Marquer présents
            </button>
            <button class="btn btn-sm" style="background: #f44336; color: white;" onclick="supprimerSelectionnes()">
                🗑️ Supprimer
            </button>
        </div>

        <div class="participants-table">
            <div class="table-header">
                <h2>Liste des participants</h2>
                
            </div>

            <table>
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" 
                                   id="selectAll" 
                                   onchange="toggleSelectAll()">
                        </th>
                        <th>Participant</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Date d'inscription</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="checkbox" class="participant-checkbox"></td>
                        <td>
                            <div class="participant-info">
                                <div class="participant-avatar">MA</div>
                                <div class="participant-details">
                                    <h4>Mohamed Ali</h4>
                                    <p>Étudiant 3ème année</p>
                                </div>
                            </div>
                        </td>
                        <td>mohamed.ali@ecole.ma</td>
                        <td>+212 6XX XXX XXX</td>
                        <td>01/10/2025</td>
                        <td><span class="badge success">✓ Présent</span></td>
                        <td>
                            <button class="btn-icon btn-send" title="Envoyer email">📧</button>
                            <button class="btn-icon btn-check" title="Marquer présent">✓</button>
                            <button class="btn-icon btn-remove" title="Supprimer">🗑️</button>
                        </td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" class="participant-checkbox"></td>
                        <td>
                            <div class="participant-info">
                                <div class="participant-avatar">SF</div>
                                <div class="participant-details">
                                    <h4>Sara Fadili</h4>
                                    <p>Étudiante 2ème année</p>
                                </div>
                            </div>
                        </td>
                        <td>sara.fadili@ecole.ma</td>
                        <td>+212 6XX XXX XXX</td>
                        <td>02/10/2025</td>
                        <td><span class="badge success">✓ Présent</span></td>
                        <td>
                            <button class="btn-icon btn-send">📧</button>
                            <button class="btn-icon btn-check">✓</button>
                            <button class="btn-icon btn-remove">🗑️</button>
                        </td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" class="participant-checkbox"></td>
                        <td>
                            <div class="participant-info">
                                <div class="participant-avatar">KL</div>
                                <div class="participant-details">
                                    <h4>Karim Lamrani</h4>
                                    <p>Étudiant 4ème année</p>
                                </div>
                            </div>
                        </td>
                        <td>karim.lamrani@ecole.ma</td>
                        <td>+212 6XX XXX XXX</td>
                        <td>28/09/2025</td>
                        <td><span class="badge warning">⏳ En attente</span></td>
                        <td>
                            <button class="btn-icon btn-send">📧</button>
                            <button class="btn-icon btn-check">✓</button>
                            <button class="btn-icon btn-remove">🗑️</button>
                        </td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" class="participant-checkbox"></td>
                        <td>
                            <div class="participant-info">
                                <div class="participant-avatar">YB</div>
                                <div class="participant-details">
                                    <h4>Yassine Benjelloun</h4>
                                    <p>Étudiant 3ème année</p>
                                </div>
                            </div>
                        </td>
                        <td>yassine.b@ecole.ma</td>
                        <td>+212 6XX XXX XXX</td>
                        <td>30/09/2025</td>
                        <td><span class="badge info">📧 Email envoyé</span></td>
                        <td>
                            <button class="btn-icon btn-send">📧</button>
                            <button class="btn-icon btn-check">✓</button>
                            <button class="btn-icon btn-remove">🗑️</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Recherche
        document.getElementById('searchParticipant').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('.participant-details h4').textContent.toLowerCase();
                const email = row.querySelectorAll('td')[2].textContent.toLowerCase();
                row.style.display = (name.includes(search) || email.includes(search)) ? '' : 'none';
            });
        });

        // Sélection multiple
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.participant-checkbox');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }

        document.querySelectorAll('.participant-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });

        function updateBulkActions() {
            const checked = document.querySelectorAll('.participant-checkbox:checked').length;
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = checked;
            if (checked > 0) {
                bulkActions.classList.add('active');
            } else {
                bulkActions.classList.remove('active');
            }
        }

        // Actions
        function envoyerRappel() {
            if (confirm('Envoyer un rappel à tous les participants ?')) {
                alert('📧 Rappel envoyé à 45 participants !');
            }
        }

        function exporterExcel() {
            alert('📊 Export Excel en cours...');
            // Simuler le téléchargement
            setTimeout(() => {
                alert('✓ Fichier téléchargé : participants_hackathon2025.xlsx');
            }, 1000);
        }

        function imprimerListe() {
            window.print();
        }

        function envoyerEmailSelectionnes() {
            const count = document.querySelectorAll('.participant-checkbox:checked').length;
            if (confirm(`Envoyer un email à ${count} participant(s) ?`)) {
                alert(`✓ Email envoyé à ${count} participant(s) !`);
            }
        }

        function marquerPresents() {
            const count = document.querySelectorAll('.participant-checkbox:checked').length;
            if (confirm(`Marquer ${count} participant(s) comme présents ?`)) {
                alert(`✓ ${count} participant(s) marqué(s) comme présents !`);
                location.reload();
            }
        }

        function supprimerSelectionnes() {
            const count = document.querySelectorAll('.participant-checkbox:checked').length;
            if (confirm(`⚠️ Supprimer ${count} participant(s) de l'événement ?`)) {
                alert(`✓ ${count} participant(s) supprimé(s) !`);
                location.reload();
            }
        }

        function toggleFiltre() {
            alert('🎯 Système de filtres à venir...');
        }
    </script>
</body>
</html>