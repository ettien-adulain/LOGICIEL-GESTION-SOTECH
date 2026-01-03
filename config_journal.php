<?php
/**
 * CONFIGURATION ET OPTIMISATION DU JOURNAL UNIFIÉ
 * Interface de configuration avancée pour le système de journalisation
 */

require_once 'fonction_traitement/fonction.php';
require_once 'fonction_traitement/JournalUnifie.php';

// Vérification des droits d'accès
check_access();

try {
    include('db/connecting.php');
    
    $journalUnifie = new JournalUnifie($cnx);
    
    $action = $_GET['action'] ?? 'dashboard';
    $message = '';
    $error = '';
    
    // Actions de configuration
    switch($action) {
        case 'optimize':
            if (isset($_POST['confirmer_optimisation'])) {
                // Optimisation des index
                $cnx->exec("OPTIMIZE TABLE journal_unifie");
                
                // Analyse de la table
                $cnx->exec("ANALYZE TABLE journal_unifie");
                
                // Nettoyage des index inutilisés
                $cnx->exec("ALTER TABLE journal_unifie ENGINE=InnoDB");
                
                $message = "Optimisation de la table journal_unifie terminée";
            }
            break;
            
        case 'index':
            if (isset($_POST['confirmer_index'])) {
                $indexName = $_POST['index_name'] ?? '';
                $columns = $_POST['columns'] ?? '';
                
                if ($indexName && $columns) {
                    $sql = "CREATE INDEX $indexName ON journal_unifie ($columns)";
                    $cnx->exec($sql);
                    $message = "Index créé: $indexName sur ($columns)";
                } else {
                    $error = "Nom d'index et colonnes requis";
                }
            }
            break;
            
        case 'partition':
            if (isset($_POST['confirmer_partition'])) {
                $type = $_POST['partition_type'] ?? 'RANGE';
                $column = $_POST['partition_column'] ?? 'date_action';
                
                // Créer une table partitionnée (exemple)
                $sql = "ALTER TABLE journal_unifie PARTITION BY $type (YEAR($column))";
                $cnx->exec($sql);
                $message = "Partitionnement configuré sur $column";
            }
            break;
            
        case 'settings':
            if (isset($_POST['sauvegarder_config'])) {
                $config = [
                    'auto_cleanup_days' => (int)$_POST['auto_cleanup_days'],
                    'max_entries_per_page' => (int)$_POST['max_entries_per_page'],
                    'enable_monitoring' => isset($_POST['enable_monitoring']),
                    'enable_export' => isset($_POST['enable_export']),
                    'log_level' => $_POST['log_level'],
                    'backup_frequency' => $_POST['backup_frequency']
                ];
                
                file_put_contents('config/journal_config.json', json_encode($config, JSON_PRETTY_PRINT));
                $message = "Configuration sauvegardée";
            }
            break;
    }
    
    // Chargement de la configuration
    $configFile = 'config/journal_config.json';
    $defaultConfig = [
        'auto_cleanup_days' => 365,
        'max_entries_per_page' => 100,
        'enable_monitoring' => true,
        'enable_export' => true,
        'log_level' => 'INFO',
        'backup_frequency' => 'daily'
    ];
    
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        $config = array_merge($defaultConfig, $config);
    } else {
        $config = $defaultConfig;
    }
    
    // Statistiques de performance
    $stats = [];
    
    // Taille de la table
    $stats['taille_table'] = $cnx->query("
        SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'journal_unifie'
    ")->fetch()['size_mb'];
    
    // Nombre d'entrées
    $stats['total_entrees'] = $cnx->query("SELECT COUNT(*) as count FROM journal_unifie")->fetch()['count'];
    
    // Index existants
    $stats['indexes'] = $cnx->query("SHOW INDEX FROM journal_unifie")->fetchAll();
    
    // Performance des requêtes
    $startTime = microtime(true);
    $journalUnifie->getJournalComplet(['limit' => 1000]);
    $stats['temps_requete'] = round((microtime(true) - $startTime) * 1000, 2);
    
    // Recommandations
    $recommandations = [];
    
    if ($stats['taille_table'] > 1000) {
        $recommandations[] = "Table volumineuse ({$stats['taille_table']}MB). Considérer le partitionnement.";
    }
    
    if ($stats['temps_requete'] > 500) {
        $recommandations[] = "Requêtes lentes ({$stats['temps_requete']}ms). Optimiser les index.";
    }
    
    if ($stats['total_entrees'] > 1000000) {
        $recommandations[] = "Grand nombre d'entrées ({$stats['total_entrees']}). Activer le nettoyage automatique.";
    }
    
} catch (Exception $e) {
    $error = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Journal Unifié</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .config-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .config-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 15px; }
        .recommendation { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .performance-good { color: #28a745; }
        .performance-warning { color: #ffc107; }
        .performance-danger { color: #dc3545; }
    </style>
</head>
<body>
    <div class="config-container">
        <!-- Header -->
        <div class="config-card">
            <h1><i class="fas fa-cogs"></i> Configuration Journal Unifié</h1>
            <p class="mb-0">Optimisation et configuration avancée du système de journalisation</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques de performance -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="h3 <?php echo $stats['taille_table'] > 1000 ? 'performance-danger' : ($stats['taille_table'] > 500 ? 'performance-warning' : 'performance-good'); ?>">
                        <?php echo $stats['taille_table']; ?>MB
                    </div>
                    <div class="text-muted">Taille Table</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="h3 <?php echo $stats['total_entrees'] > 1000000 ? 'performance-danger' : ($stats['total_entrees'] > 500000 ? 'performance-warning' : 'performance-good'); ?>">
                        <?php echo number_format($stats['total_entrees']); ?>
                    </div>
                    <div class="text-muted">Total Entrées</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="h3 <?php echo $stats['temps_requete'] > 1000 ? 'performance-danger' : ($stats['temps_requete'] > 500 ? 'performance-warning' : 'performance-good'); ?>">
                        <?php echo $stats['temps_requete']; ?>ms
                    </div>
                    <div class="text-muted">Temps Requête</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="h3 text-info"><?php echo count($stats['indexes']); ?></div>
                    <div class="text-muted">Index</div>
                </div>
            </div>
        </div>

        <!-- Recommandations -->
        <?php if (!empty($recommandations)): ?>
            <div class="config-card">
                <h4><i class="fas fa-lightbulb"></i> Recommandations</h4>
                <?php foreach ($recommandations as $recommandation): ?>
                    <div class="recommendation">
                        <i class="fas fa-info-circle"></i> <?php echo $recommandation; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Configuration -->
        <div class="config-card">
            <h4><i class="fas fa-sliders-h"></i> Configuration Générale</h4>
            
            <form method="POST" action="?action=settings">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nettoyage automatique (jours)</label>
                            <input type="number" name="auto_cleanup_days" class="form-control" 
                                   value="<?php echo $config['auto_cleanup_days']; ?>" min="30" max="3650">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Entrées par page</label>
                            <input type="number" name="max_entries_per_page" class="form-control" 
                                   value="<?php echo $config['max_entries_per_page']; ?>" min="10" max="1000">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Niveau de log</label>
                            <select name="log_level" class="form-control">
                                <option value="DEBUG" <?php echo $config['log_level'] == 'DEBUG' ? 'selected' : ''; ?>>DEBUG</option>
                                <option value="INFO" <?php echo $config['log_level'] == 'INFO' ? 'selected' : ''; ?>>INFO</option>
                                <option value="WARNING" <?php echo $config['log_level'] == 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                                <option value="ERROR" <?php echo $config['log_level'] == 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Fréquence de sauvegarde</label>
                            <select name="backup_frequency" class="form-control">
                                <option value="hourly" <?php echo $config['backup_frequency'] == 'hourly' ? 'selected' : ''; ?>>Horaire</option>
                                <option value="daily" <?php echo $config['backup_frequency'] == 'daily' ? 'selected' : ''; ?>>Quotidienne</option>
                                <option value="weekly" <?php echo $config['backup_frequency'] == 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                                <option value="monthly" <?php echo $config['backup_frequency'] == 'monthly' ? 'selected' : ''; ?>>Mensuelle</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_monitoring" 
                                       <?php echo $config['enable_monitoring'] ? 'checked' : ''; ?>>
                                <label class="form-check-label">Activer le monitoring</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_export" 
                                       <?php echo $config['enable_export'] ? 'checked' : ''; ?>>
                                <label class="form-check-label">Activer l'export</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="sauvegarder_config" class="btn btn-primary">
                    <i class="fas fa-save"></i> Sauvegarder Configuration
                </button>
            </form>
        </div>

        <!-- Optimisation -->
        <div class="config-card">
            <h4><i class="fas fa-tachometer-alt"></i> Optimisation</h4>
            
            <form method="POST" action="?action=optimize">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Optimisation:</strong> Cette opération va optimiser la table journal_unifie pour améliorer les performances.
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirmer_optimisation" required>
                        <label class="form-check-label">Je confirme vouloir optimiser la table</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-tachometer-alt"></i> Optimiser Table
                </button>
            </form>
        </div>

        <!-- Gestion des index -->
        <div class="config-card">
            <h4><i class="fas fa-database"></i> Gestion des Index</h4>
            
            <form method="POST" action="?action=index">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nom de l'index</label>
                            <input type="text" name="index_name" class="form-control" placeholder="idx_exemple">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Colonnes</label>
                            <input type="text" name="columns" class="form-control" placeholder="module, action">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirmer_index" required>
                        <label class="form-check-label">Je confirme vouloir créer cet index</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-plus"></i> Créer Index
                </button>
            </form>
            
            <!-- Liste des index existants -->
            <div class="mt-4">
                <h5>Index Existants</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Colonnes</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['indexes'] as $index): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($index['Key_name']); ?></td>
                                    <td><?php echo htmlspecialchars($index['Column_name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($index['Index_type']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Liens utiles -->
        <div class="row">
            <div class="col-md-3">
                <div class="config-card text-center">
                    <h5><i class="fas fa-tools"></i> Administration</h5>
                    <a href="admin_journal.php" class="btn btn-primary">
                        <i class="fas fa-cogs"></i> Outils Admin
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="config-card text-center">
                    <h5><i class="fas fa-heartbeat"></i> Monitoring</h5>
                    <a href="monitoring_journal.php" class="btn btn-info">
                        <i class="fas fa-chart-line"></i> Surveillance
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="config-card text-center">
                    <h5><i class="fas fa-database"></i> Sauvegarde</h5>
                    <a href="backup_journal.php" class="btn btn-success">
                        <i class="fas fa-save"></i> Sauvegardes
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="config-card text-center">
                    <h5><i class="fas fa-eye"></i> Journal</h5>
                    <a href="journal.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Voir Journal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
