<?php
session_start();
require_once '../db/connecting.php';
require_once '../fonction_traitement/fonction.php';

// Vérification des droits d'accès (niveau 3+ requis)
if (!isset($_SESSION['id_utilisateur'])) {
    header('Location: ../connexion.php');
    exit();
}

// Fonction pour lire les fichiers de logs
function readLogFile($file_path, $max_lines = 100) {
    if (!file_exists($file_path)) {
        return [];
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }
    
    // Retourner les dernières lignes
    return array_slice($lines, -$max_lines);
}

// Fonction pour parser les logs d'erreurs
function parseErrorLog($lines) {
    $errors = [];
    foreach ($lines as $line) {
        if (preg_match('/^\[(.*?)\].*?PHP (.*?): (.*?) in (.*?) on line (\d+)/', $line, $matches)) {
            $errors[] = [
                'timestamp' => $matches[1],
                'type' => $matches[2],
                'message' => $matches[3],
                'file' => basename($matches[4]),
                'line' => $matches[5],
                'raw' => $line
            ];
        }
    }
    return $errors;
}

// Fonction pour parser les logs de production
function parseProductionLog($lines) {
    $logs = [];
    foreach ($lines as $line) {
        $data = json_decode($line, true);
        if ($data) {
            $logs[] = $data;
        }
    }
    return $logs;
}

// Fonction pour calculer les statistiques
function getErrorStats($errors) {
    $stats = [
        'total' => count($errors),
        'critical' => 0,
        'today' => 0,
        'types' => []
    ];
    
    $today = date('Y-m-d');
    
    foreach ($errors as $error) {
        // Compter les erreurs critiques
        if (strpos($error['type'], 'Fatal') !== false || strpos($error['type'], 'Error') !== false) {
            $stats['critical']++;
        }
        
        // Compter les erreurs d'aujourd'hui
        if (strpos($error['timestamp'], $today) !== false) {
            $stats['today']++;
        }
        
        // Compter par type
        $type = $error['type'];
        $stats['types'][$type] = ($stats['types'][$type] ?? 0) + 1;
    }
    
    return $stats;
}

// Lire les logs
$error_log_path = '../logs/errors.log';
$production_log_path = '../logs/production_monitor.log';

$error_lines = readLogFile($error_log_path, 200);
$production_lines = readLogFile($production_log_path, 100);

$errors = parseErrorLog($error_lines);
$production_logs = parseProductionLog($production_lines);
$error_stats = getErrorStats($errors);

// Filtres
$filter_period = $_GET['period'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';
$auto_refresh = isset($_GET['auto_refresh']);

// Filtrer les erreurs selon les critères
$filtered_errors = $errors;
if ($filter_period === 'today') {
    $today = date('Y-m-d');
    $filtered_errors = array_filter($errors, function($error) use ($today) {
        return strpos($error['timestamp'], $today) !== false;
    });
} elseif ($filter_period === 'week') {
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $filtered_errors = array_filter($errors, function($error) use ($week_ago) {
        return $error['timestamp'] >= $week_ago;
    });
}

if ($filter_type !== 'all') {
    $filtered_errors = array_filter($filtered_errors, function($error) use ($filter_type) {
        return strpos($error['type'], $filter_type) !== false;
    });
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes Système - SOTech</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #d84315, #b71c1c);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #d84315;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .filters {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group label {
            font-weight: bold;
            color: #333;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .auto-refresh input[type="checkbox"] {
            transform: scale(1.2);
        }
        
        .content {
            padding: 20px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h3 {
            color: #d84315;
            border-bottom: 2px solid #d84315;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .error-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .error-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .error-item:hover {
            background: #f8f9fa;
        }
        
        .error-item:last-child {
            border-bottom: none;
        }
        
        .error-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .error-type {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .error-type.warning {
            background: #ffc107;
            color: #000;
        }
        
        .error-type.notice {
            background: #17a2b8;
            color: white;
        }
        
        .error-timestamp {
            color: #666;
            font-size: 0.9em;
        }
        
        .error-message {
            color: #333;
            margin: 5px 0;
        }
        
        .error-location {
            color: #666;
            font-size: 0.9em;
        }
        
        .production-logs {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .log-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .btn {
            background: #d84315;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn:hover {
            background: #b71c1c;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            padding: 40px;
            font-style: italic;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .close:hover {
            color: #000;
        }
        
        .raw-error {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .navigation {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .navigation a {
            color: #d84315;
            text-decoration: none;
            margin-right: 20px;
            font-weight: bold;
        }
        
        .navigation a:hover {
            color: #b71c1c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navigation">
            <a href="../index.php">🏠 Accueil</a>
            <a href="../journal_systeme.php">📋 Journal Système</a>
            <a href="../utilisateur.php">👤 Gestion Utilisateurs</a>
        </div>
        
        <div class="header">
            <h1>🚨 Alertes Système SOTech</h1>
            <p>Monitoring en temps réel des erreurs et performances</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $error_stats['total'] ?></div>
                <div class="stat-label">Erreurs Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $error_stats['critical'] ?></div>
                <div class="stat-label">Erreurs Critiques</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $error_stats['today'] ?></div>
                <div class="stat-label">Erreurs Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($production_logs) ?></div>
                <div class="stat-label">Logs Production</div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" class="filter-group">
                <label for="period">Période :</label>
                <select name="period" id="period">
                    <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>Toutes</option>
                    <option value="today" <?= $filter_period === 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
                    <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>Cette semaine</option>
                </select>
                
                <label for="type">Type :</label>
                <select name="type" id="type">
                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="Fatal" <?= $filter_type === 'Fatal' ? 'selected' : '' ?>>Fatal</option>
                    <option value="Error" <?= $filter_type === 'Error' ? 'selected' : '' ?>>Error</option>
                    <option value="Warning" <?= $filter_type === 'Warning' ? 'selected' : '' ?>>Warning</option>
                    <option value="Notice" <?= $filter_type === 'Notice' ? 'selected' : '' ?>>Notice</option>
                </select>
                
                <div class="auto-refresh">
                    <input type="checkbox" name="auto_refresh" id="auto_refresh" <?= $auto_refresh ? 'checked' : '' ?>>
                    <label for="auto_refresh">Actualisation auto (30s)</label>
                </div>
                
                <button type="submit" class="btn">Filtrer</button>
                <a href="?" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>
        
        <div class="content">
            <div class="section">
                <h3>📋 Erreurs Récentes (<?= count($filtered_errors) ?>)</h3>
                <?php if (empty($filtered_errors)): ?>
                    <div class="no-data">Aucune erreur trouvée avec ces filtres</div>
                <?php else: ?>
                    <div class="error-list">
                        <?php foreach (array_reverse($filtered_errors) as $error): ?>
                            <div class="error-item" onclick="showErrorDetails('<?= htmlspecialchars($error['raw']) ?>')">
                                <div class="error-header">
                                    <span class="error-type <?= strtolower($error['type']) ?>"><?= htmlspecialchars($error['type']) ?></span>
                                    <span class="error-timestamp"><?= htmlspecialchars($error['timestamp']) ?></span>
                                </div>
                                <div class="error-message"><?= htmlspecialchars($error['message']) ?></div>
                                <div class="error-location"><?= htmlspecialchars($error['file']) ?>:<?= htmlspecialchars($error['line']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h3>📊 Logs de Production</h3>
                <?php if (empty($production_logs)): ?>
                    <div class="no-data">Aucun log de production disponible</div>
                <?php else: ?>
                    <div class="production-logs">
                        <?php foreach (array_reverse($production_logs) as $log): ?>
                            <div class="log-item">
                                <strong><?= htmlspecialchars($log['timestamp'] ?? 'N/A') ?></strong> - 
                                <?= htmlspecialchars($log['type'] ?? 'N/A') ?> - 
                                <?= htmlspecialchars($log['message'] ?? 'N/A') ?>
                                <?php if (isset($log['user'])): ?>
                                    (Utilisateur: <?= htmlspecialchars($log['user']) ?>)
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal pour les détails d'erreur -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Détails de l'erreur</h3>
                <span class="close" onclick="closeErrorModal()">&times;</span>
            </div>
            <div id="errorDetails" class="raw-error"></div>
        </div>
    </div>
    
    <script>
        // Auto-refresh si activé
        <?php if ($auto_refresh): ?>
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
        
        // Fonction pour afficher les détails d'erreur
        function showErrorDetails(rawError) {
            document.getElementById('errorDetails').textContent = rawError;
            document.getElementById('errorModal').style.display = 'block';
        }
        
        // Fonction pour fermer le modal
        function closeErrorModal() {
            document.getElementById('errorModal').style.display = 'none';
        }
        
        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('errorModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
