<?php
/**
 * SYSTÈME DE MONITORING DU JOURNAL UNIFIÉ
 * Surveillance en temps réel du système de journalisation
 */

require_once 'fonction_traitement/fonction.php';
require_once 'fonction_traitement/JournalUnifie.php';

// Vérification des droits d'accès
check_access();

try {
    include('db/connecting.php');
    
    $journalUnifie = new JournalUnifie($cnx);
    
    // Configuration du monitoring
    $seuils = [
        'erreurs_par_heure' => 10,
        'actions_par_minute' => 100,
        'taille_table_mb' => 1000,
        'utilisateurs_inactifs_jours' => 7
    ];
    
    // Collecte des métriques
    $metriques = [];
    
    // 1. Erreurs dans la dernière heure
    $erreursHeure = $cnx->query("
        SELECT COUNT(*) as count 
        FROM journal_unifie 
        WHERE date_action >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND description_action LIKE '%erreur%'
    ")->fetch()['count'];
    $metriques['erreurs_heure'] = $erreursHeure;
    
    // 2. Actions dans la dernière minute
    $actionsMinute = $cnx->query("
        SELECT COUNT(*) as count 
        FROM journal_unifie 
        WHERE date_action >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ")->fetch()['count'];
    $metriques['actions_minute'] = $actionsMinute;
    
    // 3. Taille de la table
    $tailleTable = $cnx->query("
        SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'journal_unifie'
    ")->fetch()['size_mb'];
    $metriques['taille_table'] = $tailleTable;
    
    // 4. Utilisateurs inactifs
    $utilisateursInactifs = $cnx->query("
        SELECT COUNT(DISTINCT u.IDUTILISATEUR) as count
        FROM utilisateur u
        LEFT JOIN journal_unifie j ON u.IDUTILISATEUR = j.IDUTILISATEUR 
        AND j.date_action >= DATE_SUB(NOW(), INTERVAL ? DAY)
        WHERE j.IDUTILISATEUR IS NULL
    ")->execute([$seuils['utilisateurs_inactifs_jours']])->fetch()['count'];
    $metriques['utilisateurs_inactifs'] = $utilisateursInactifs;
    
    // 5. Performance des requêtes
    $startTime = microtime(true);
    $journalUnifie->getJournalComplet(['limit' => 100]);
    $tempsRequete = round((microtime(true) - $startTime) * 1000, 2);
    $metriques['temps_requete_ms'] = $tempsRequete;
    
    // 6. Modules les plus actifs
    $modulesActifs = $cnx->query("
        SELECT module, COUNT(*) as count
        FROM journal_unifie 
        WHERE date_action >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY module 
        ORDER BY count DESC 
        LIMIT 5
    ")->fetchAll();
    $metriques['modules_actifs'] = $modulesActifs;
    
    // 7. Actions récentes
    $actionsRecentes = $cnx->query("
        SELECT j.*, u.NomPrenom as nom_utilisateur
        FROM journal_unifie j
        JOIN utilisateur u ON j.IDUTILISATEUR = u.IDUTILISATEUR
        ORDER BY j.date_action DESC 
        LIMIT 10
    ")->fetchAll();
    $metriques['actions_recentes'] = $actionsRecentes;
    
    // 8. Statistiques par heure (dernières 24h)
    $statsParHeure = $cnx->query("
        SELECT HOUR(date_action) as heure, COUNT(*) as count
        FROM journal_unifie 
        WHERE date_action >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(date_action)
        ORDER BY heure
    ")->fetchAll();
    $metriques['stats_par_heure'] = $statsParHeure;
    
    // Détermination du statut
    $statut = 'OK';
    $alertes = [];
    
    if ($erreursHeure > $seuils['erreurs_par_heure']) {
        $statut = 'ATTENTION';
        $alertes[] = "Trop d'erreurs dans la dernière heure: $erreursHeure";
    }
    
    if ($actionsMinute > $seuils['actions_par_minute']) {
        $statut = 'ATTENTION';
        $alertes[] = "Trop d'actions par minute: $actionsMinute";
    }
    
    if ($tailleTable > $seuils['taille_table_mb']) {
        $statut = 'ATTENTION';
        $alertes[] = "Table trop volumineuse: {$tailleTable}MB";
    }
    
    if ($tempsRequete > 1000) {
        $statut = 'ATTENTION';
        $alertes[] = "Requêtes lentes: {$tempsRequete}ms";
    }
    
    // Auto-refresh
    $autoRefresh = $_GET['refresh'] ?? false;
    
} catch (Exception $e) {
    $error = "Erreur de monitoring: " . $e->getMessage();
    $statut = 'ERREUR';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Journal Unifié</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .monitoring-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .status-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .status-ok { border-left: 5px solid #28a745; }
        .status-attention { border-left: 5px solid #ffc107; }
        .status-erreur { border-left: 5px solid #dc3545; }
        .metric-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 15px; }
        .metric-value { font-size: 2rem; font-weight: bold; }
        .metric-label { color: #6c757d; font-size: 0.9rem; }
        .chart-container { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .alert-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .auto-refresh { position: fixed; top: 20px; right: 20px; z-index: 1000; }
    </style>
</head>
<body>
    <!-- Auto-refresh -->
    <div class="auto-refresh">
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                <i class="fas fa-sync"></i> Actualiser
            </button>
            <button class="btn btn-sm btn-outline-success" onclick="toggleAutoRefresh()">
                <i class="fas fa-play" id="refreshIcon"></i> Auto
            </button>
        </div>
    </div>

    <div class="monitoring-container">
        <!-- Header -->
        <div class="status-card <?php echo 'status-' . strtolower($statut); ?>">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-heartbeat"></i> Monitoring Journal Unifié</h1>
                    <p class="mb-0">Surveillance en temps réel du système de journalisation</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="metric-value text-<?php echo $statut == 'OK' ? 'success' : ($statut == 'ATTENTION' ? 'warning' : 'danger'); ?>">
                        <?php echo $statut; ?>
                    </div>
                    <div class="metric-label">Statut Système</div>
                </div>
            </div>
        </div>

        <!-- Alertes -->
        <?php if (!empty($alertes)): ?>
            <div class="alert-card">
                <h4><i class="fas fa-exclamation-triangle text-warning"></i> Alertes</h4>
                <?php foreach ($alertes as $alerte): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-bell"></i> <?php echo $alerte; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Métriques principales -->
        <div class="row">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value text-<?php echo $erreursHeure > $seuils['erreurs_par_heure'] ? 'danger' : 'success'; ?>">
                        <?php echo $erreursHeure; ?>
                    </div>
                    <div class="metric-label">Erreurs/Heure</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value text-<?php echo $actionsMinute > $seuils['actions_par_minute'] ? 'warning' : 'info'; ?>">
                        <?php echo $actionsMinute; ?>
                    </div>
                    <div class="metric-label">Actions/Minute</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value text-<?php echo $tailleTable > $seuils['taille_table_mb'] ? 'warning' : 'info'; ?>">
                        <?php echo $tailleTable; ?>MB
                    </div>
                    <div class="metric-label">Taille Table</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value text-info">
                        <?php echo $tempsRequete; ?>ms
                    </div>
                    <div class="metric-label">Temps Requête</div>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-line"></i> Activité par Heure (24h)</h5>
                    <canvas id="activiteChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i> Modules Actifs (1h)</h5>
                    <canvas id="modulesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Actions récentes -->
        <div class="status-card">
            <h5><i class="fas fa-history"></i> Actions Récentes</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Utilisateur</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actionsRecentes as $action): ?>
                            <tr>
                                <td><?php echo date('H:i:s', strtotime($action['date_action'])); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($action['module']); ?></span></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($action['action']); ?></span></td>
                                <td><?php echo htmlspecialchars($action['nom_utilisateur']); ?></td>
                                <td><?php echo htmlspecialchars(substr($action['description_action'], 0, 50)) . '...'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Liens utiles -->
        <div class="row">
            <div class="col-md-4">
                <div class="status-card text-center">
                    <h5><i class="fas fa-cogs"></i> Administration</h5>
                    <a href="admin_journal.php" class="btn btn-primary">
                        <i class="fas fa-tools"></i> Outils Admin
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="status-card text-center">
                    <h5><i class="fas fa-eye"></i> Journal</h5>
                    <a href="journal.php" class="btn btn-success">
                        <i class="fas fa-list"></i> Voir Journal
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="status-card text-center">
                    <h5><i class="fas fa-download"></i> Export</h5>
                    <a href="admin_journal.php?action=exporter" class="btn btn-info">
                        <i class="fas fa-file-csv"></i> Exporter
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh
        let autoRefreshInterval;
        
        function toggleAutoRefresh() {
            const icon = document.getElementById('refreshIcon');
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                icon.className = 'fas fa-play';
            } else {
                autoRefreshInterval = setInterval(() => {
                    location.reload();
                }, 30000); // 30 secondes
                icon.className = 'fas fa-pause';
            }
        }

        // Graphique activité par heure
        const activiteData = <?php echo json_encode($statsParHeure); ?>;
        const heures = [];
        const activites = [];
        
        // Créer un tableau complet des 24 heures
        for (let i = 0; i < 24; i++) {
            heures.push(i + 'h');
            const data = activiteData.find(item => item.heure == i);
            activites.push(data ? data.count : 0);
        }
        
        new Chart(document.getElementById('activiteChart'), {
            type: 'line',
            data: {
                labels: heures,
                datasets: [{
                    label: 'Actions',
                    data: activites,
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Graphique modules actifs
        const modulesData = <?php echo json_encode($modulesActifs); ?>;
        const modulesLabels = modulesData.map(item => item.module);
        const modulesCounts = modulesData.map(item => item.count);
        
        new Chart(document.getElementById('modulesChart'), {
            type: 'doughnut',
            data: {
                labels: modulesLabels,
                datasets: [{
                    data: modulesCounts,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
