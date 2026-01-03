<?php
session_start();
require_once 'includes/header.php';
require_once 'db/connecting.php';

// Configuration
$logs_dir = 'logs/';
$error_log_file = $logs_dir . 'errors.log';
$production_log_file = $logs_dir . 'production_monitor.log';
$max_errors = 100; // Nombre maximum d'erreurs à afficher

// Fonction pour lire les logs
function readLogFile($file_path, $max_lines = 100) {
    if (!file_exists($file_path)) {
        return [];
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES);
    return array_slice($lines, -$max_lines);
}

// Fonction pour parser les logs d'erreurs
function parseErrorLog($lines) {
    $errors = [];
    foreach ($lines as $line) {
        if (preg_match('/\[(.*?)\] (.*?): (.*?) dans (.*?) ligne (\d+)/', $line, $matches)) {
            $errors[] = [
                'timestamp' => $matches[1],
                'type' => $matches[2],
                'message' => $matches[3],
                'file' => $matches[4],
                'line' => $matches[5],
                'raw' => $line
            ];
        }
    }
    return array_reverse($errors);
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
    return array_reverse($logs);
}

// Fonction pour obtenir les statistiques
function getErrorStats($errors) {
    $stats = [
        'total' => count($errors),
        'by_type' => [],
        'by_hour' => [],
        'critical' => 0,
        'today' => 0
    ];
    
    $today = date('Y-m-d');
    
    foreach ($errors as $error) {
        // Par type
        $stats['by_type'][$error['type']] = ($stats['by_type'][$error['type']] ?? 0) + 1;
        
        // Par heure
        $hour = date('H', strtotime($error['timestamp']));
        $stats['by_hour'][$hour] = ($stats['by_hour'][$hour] ?? 0) + 1;
        
        // Critiques
        if (in_array($error['type'], ['ERREUR PHP', 'EXCEPTION', 'ARRÊT FATAL'])) {
            $stats['critical']++;
        }
        
        // Aujourd'hui
        if (strpos($error['timestamp'], $today) === 0) {
            $stats['today']++;
        }
    }
    
    return $stats;
}

// Lire les logs
$error_lines = readLogFile($error_log_file, $max_errors);
$production_lines = readLogFile($production_log_file, $max_errors);

$errors = parseErrorLog($error_lines);
$production_logs = parseProductionLog($production_lines);
$stats = getErrorStats($errors);

// Filtrer par période si demandé
$filter_period = $_GET['period'] ?? 'all';
$filtered_errors = $errors;

if ($filter_period === 'today') {
    $today = date('Y-m-d');
    $filtered_errors = array_filter($errors, function($error) use ($today) {
        return strpos($error['timestamp'], $today) === 0;
    });
} elseif ($filter_period === 'week') {
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $filtered_errors = array_filter($errors, function($error) use ($week_ago) {
        return strtotime($error['timestamp']) >= strtotime($week_ago);
    });
}

// Filtrer par type si demandé
$filter_type = $_GET['type'] ?? 'all';
if ($filter_type !== 'all') {
    $filtered_errors = array_filter($filtered_errors, function($error) use ($filter_type) {
        return $error['type'] === $filter_type;
    });
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes Système - Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .alert-card {
            border-left: 4px solid #dc3545;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .error-type {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .error-type.ERREUR {
            background-color: #dc3545;
            color: white;
        }
        
        .error-type.EXCEPTION {
            background-color: #fd7e14;
            color: white;
        }
        
        .error-type.ARRÊT {
            background-color: #6f42c1;
            color: white;
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .file-path {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #495057;
            background-color: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .auto-refresh {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
        }
        
        .auto-refresh.active {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .log-entry {
            border-left: 3px solid #dee2e6;
            padding: 1rem;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }
        
        .log-entry:hover {
            border-left-color: #007bff;
            background: #f8f9fa;
        }
        
        .log-entry.critical {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .log-entry.warning {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="mb-0">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        Alertes Système
                    </h1>
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Accueil
                        </a>
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Actualiser
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?= $stats['total'] ?></h3>
                            <p class="mb-0">Total Erreurs</p>
                        </div>
                        <i class="fas fa-bug fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?= $stats['critical'] ?></h3>
                            <p class="mb-0">Critiques</p>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?= $stats['today'] ?></h3>
                            <p class="mb-0">Aujourd'hui</p>
                        </div>
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?= count($production_logs) ?></h3>
                            <p class="mb-0">Logs Production</p>
                        </div>
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Période</label>
                                <select name="period" class="form-select">
                                    <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>Toutes</option>
                                    <option value="today" <?= $filter_period === 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
                                    <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>Cette semaine</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type d'erreur</label>
                                <select name="type" class="form-select">
                                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>Tous</option>
                                    <option value="ERREUR PHP" <?= $filter_type === 'ERREUR PHP' ? 'selected' : '' ?>>Erreurs PHP</option>
                                    <option value="EXCEPTION" <?= $filter_type === 'EXCEPTION' ? 'selected' : '' ?>>Exceptions</option>
                                    <option value="ARRÊT FATAL" <?= $filter_type === 'ARRÊT FATAL' ? 'selected' : '' ?>>Arrêts fatals</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">
                                    <i class="fas fa-filter"></i> Filtrer
                                </button>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <a href="alertes_systeme.php" class="btn btn-outline-secondary d-block">
                                    <i class="fas fa-times"></i> Réinitialiser
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des erreurs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i>
                            Erreurs récentes (<?= count($filtered_errors) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($filtered_errors)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h4>Aucune erreur trouvée</h4>
                                <p class="text-muted">Le système fonctionne correctement !</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($filtered_errors as $error): ?>
                                    <div class="col-12 mb-3">
                                        <div class="log-entry <?= in_array($error['type'], ['ERREUR PHP', 'EXCEPTION', 'ARRÊT FATAL']) ? 'critical' : 'warning' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span class="error-type <?= $error['type'] ?>"><?= htmlspecialchars($error['type']) ?></span>
                                                    <span class="timestamp ms-2">
                                                        <i class="fas fa-clock"></i> <?= htmlspecialchars($error['timestamp']) ?>
                                                    </span>
                                                </div>
                                                <button class="btn btn-sm btn-outline-info" onclick="showErrorDetails('<?= htmlspecialchars($error['raw'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-eye"></i> Détails
                                                </button>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Message:</strong> <?= htmlspecialchars($error['message']) ?>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Fichier:</strong> 
                                                <span class="file-path"><?= htmlspecialchars($error['file']) ?>:<?= $error['line'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs de production -->
        <?php if (!empty($production_logs)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line"></i>
                            Logs de Production
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Type</th>
                                        <th>Valeur</th>
                                        <th>Utilisateur</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($production_logs, 0, 20) as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['timestamp'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($log['type'] ?? 'N/A') ?></span></td>
                                        <td><?= htmlspecialchars($log['value'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['user'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['ip'] ?? 'N/A') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bouton de rafraîchissement automatique -->
    <button class="btn btn-success refresh-btn auto-refresh" id="autoRefreshBtn" onclick="toggleAutoRefresh()">
        <i class="fas fa-sync-alt"></i>
    </button>

    <!-- Modal pour les détails d'erreur -->
    <div class="modal fade" id="errorDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'erreur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="errorDetailsContent" class="bg-light p-3 rounded"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let autoRefreshInterval;
        let isAutoRefresh = false;

        function toggleAutoRefresh() {
            if (isAutoRefresh) {
                clearInterval(autoRefreshInterval);
                document.getElementById('autoRefreshBtn').classList.remove('active');
                isAutoRefresh = false;
            } else {
                autoRefreshInterval = setInterval(() => {
                    location.reload();
                }, 30000); // Actualiser toutes les 30 secondes
                document.getElementById('autoRefreshBtn').classList.add('active');
                isAutoRefresh = true;
            }
        }

        function showErrorDetails(rawError) {
            document.getElementById('errorDetailsContent').textContent = rawError;
            new bootstrap.Modal(document.getElementById('errorDetailsModal')).show();
        }

        // Démarrer l'auto-refresh si demandé
        if (new URLSearchParams(window.location.search).get('autorefresh') === '1') {
            toggleAutoRefresh();
        }
    </script>
</body>
</html>
