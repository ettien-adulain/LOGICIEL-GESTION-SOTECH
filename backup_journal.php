<?php
/**
 * SYSTÈME DE SAUVEGARDE DU JOURNAL UNIFIÉ
 * Sauvegarde automatique et manuelle du système de journalisation
 */

require_once 'fonction_traitement/fonction.php';
require_once 'fonction_traitement/JournalUnifie.php';

// Vérification des droits d'accès
check_access();

try {
    include('db/connecting.php');
    
    $journalUnifie = new JournalUnifie($cnx);
    
    // Configuration de la sauvegarde
    $backupDir = 'backups/journal/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $action = $_GET['action'] ?? 'dashboard';
    $message = '';
    $error = '';
    
    // Actions de sauvegarde
    switch($action) {
        case 'backup_full':
            if (isset($_POST['confirmer_backup'])) {
                $filename = 'journal_backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backupDir . $filename;
                
                // Sauvegarde complète de la table
                $sql = "SELECT * FROM journal_unifie ORDER BY date_action";
                $stmt = $cnx->prepare($sql);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Création du fichier SQL
                $sqlContent = "-- Sauvegarde du Journal Unifié\n";
                $sqlContent .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
                $sqlContent .= "-- Total: " . count($data) . " entrées\n\n";
                
                $sqlContent .= "CREATE TABLE IF NOT EXISTS `journal_unifie_backup` LIKE `journal_unifie`;\n";
                $sqlContent .= "TRUNCATE TABLE `journal_unifie_backup`;\n\n";
                
                foreach ($data as $row) {
                    $values = array_map(function($value) use ($cnx) {
                        return $value === null ? 'NULL' : $cnx->quote($value);
                    }, $row);
                    
                    $sqlContent .= "INSERT INTO `journal_unifie_backup` VALUES (" . implode(', ', $values) . ");\n";
                }
                
                file_put_contents($filepath, $sqlContent);
                $message = "Sauvegarde créée: $filename (" . count($data) . " entrées)";
            }
            break;
            
        case 'backup_csv':
            if (isset($_POST['confirmer_csv'])) {
                $dateDebut = $_POST['date_debut'] ?? date('Y-m-01');
                $dateFin = $_POST['date_fin'] ?? date('Y-m-d');
                $module = $_POST['module'] ?? '';
                
                $filters = [
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin
                ];
                
                if ($module) {
                    $filters['module'] = $module;
                }
                
                $filename = 'journal_csv_' . date('Y-m-d_H-i-s') . '.csv';
                $filepath = $backupDir . $filename;
                
                // Export CSV
                $donnees = $journalUnifie->getJournalComplet($filters);
                
                $csv = fopen($filepath, 'w');
                
                // BOM pour UTF-8
                fprintf($csv, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // En-têtes
                $headers = [
                    'ID', 'Date', 'Module', 'Entité ID', 'Type Entité', 'Action',
                    'Utilisateur', 'Description', 'Article ID', 'Stock Avant', 'Stock Après',
                    'Vente ID', 'Client ID', 'Montant Total', 'IP Address'
                ];
                fputcsv($csv, $headers, ';');
                
                // Données
                foreach ($donnees as $row) {
                    $ligne = [
                        $row['IDJOURNAL'],
                        $row['date_action'],
                        $row['module'],
                        $row['entite_id'],
                        $row['entite_type'],
                        $row['action'],
                        $row['nom_utilisateur'],
                        $row['description_action'],
                        $row['IDARTICLE'],
                        $row['stock_avant'],
                        $row['stock_apres'],
                        $row['IDVENTE'],
                        $row['IDCLIENT'],
                        $row['MontantTotal'],
                        $row['ip_address']
                    ];
                    fputcsv($csv, $ligne, ';');
                }
                
                fclose($csv);
                $message = "Export CSV créé: $filename (" . count($donnees) . " entrées)";
            }
            break;
            
        case 'restore':
            if (isset($_POST['confirmer_restore'])) {
                $backupFile = $_POST['backup_file'] ?? '';
                if ($backupFile && file_exists($backupDir . $backupFile)) {
                    $sqlContent = file_get_contents($backupDir . $backupFile);
                    
                    // Exécuter le script de restauration
                    $cnx->exec($sqlContent);
                    $message = "Restauration effectuée depuis: $backupFile";
                } else {
                    $error = "Fichier de sauvegarde non trouvé";
                }
            }
            break;
            
        case 'cleanup':
            if (isset($_POST['confirmer_cleanup'])) {
                $jours = (int)$_POST['jours_conservation'];
                $pattern = $backupDir . 'journal_backup_*';
                $files = glob($pattern);
                $deleted = 0;
                
                foreach ($files as $file) {
                    if (filemtime($file) < (time() - ($jours * 24 * 60 * 60))) {
                        unlink($file);
                        $deleted++;
                    }
                }
                
                $message = "Nettoyage effectué: $deleted fichiers supprimés";
            }
            break;
    }
    
    // Liste des sauvegardes existantes
    $backups = [];
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '*');
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'date' => filemtime($file),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            ];
        }
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
    
    // Statistiques
    $totalEntrees = $cnx->query("SELECT COUNT(*) as count FROM journal_unifie")->fetch()['count'];
    $tailleTable = $cnx->query("
        SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'journal_unifie'
    ")->fetch()['size_mb'];
    
} catch (Exception $e) {
    $error = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegarde Journal Unifié</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .backup-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .backup-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 15px; }
        .backup-list { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .file-item { padding: 10px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="backup-container">
        <!-- Header -->
        <div class="backup-card">
            <h1><i class="fas fa-database"></i> Sauvegarde Journal Unifié</h1>
            <p class="mb-0">Gestion des sauvegardes et restauration du système de journalisation</p>
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

        <!-- Statistiques -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="h3 text-primary"><?php echo number_format($totalEntrees); ?></div>
                    <div class="text-muted">Total Entrées</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="h3 text-info"><?php echo $tailleTable; ?>MB</div>
                    <div class="text-muted">Taille Table</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="h3 text-success"><?php echo count($backups); ?></div>
                    <div class="text-muted">Sauvegardes</div>
                </div>
            </div>
        </div>

        <!-- Actions de sauvegarde -->
        <div class="row">
            <div class="col-md-6">
                <div class="backup-card">
                    <h4><i class="fas fa-download"></i> Sauvegarde Complète</h4>
                    <p>Créer une sauvegarde SQL complète de la table journal_unifie.</p>
                    
                    <form method="POST" action="?action=backup_full">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Info:</strong> Cette sauvegarde inclut toutes les données de la table journal_unifie.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmer la sauvegarde</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirmer_backup" required>
                                <label class="form-check-label">Je confirme vouloir créer une sauvegarde</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-database"></i> Créer Sauvegarde SQL
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="backup-card">
                    <h4><i class="fas fa-file-csv"></i> Export CSV</h4>
                    <p>Exporter les données vers un fichier CSV avec filtres.</p>
                    
                    <form method="POST" action="?action=backup_csv">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date de début</label>
                                    <input type="date" name="date_debut" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date de fin</label>
                                    <input type="date" name="date_fin" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Module (optionnel)</label>
                            <select name="module" class="form-control">
                                <option value="">Tous les modules</option>
                                <option value="article">Articles</option>
                                <option value="client">Clients</option>
                                <option value="stock">Stock</option>
                                <option value="vente">Ventes</option>
                                <option value="connexion">Connexions</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirmer_csv" required>
                                <label class="form-check-label">Je confirme vouloir exporter en CSV</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-csv"></i> Exporter CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Restauration -->
        <div class="backup-card">
            <h4><i class="fas fa-upload"></i> Restauration</h4>
            <p>Restaurer les données depuis une sauvegarde SQL.</p>
            
            <form method="POST" action="?action=restore">
                <div class="mb-3">
                    <label class="form-label">Fichier de sauvegarde</label>
                    <select name="backup_file" class="form-control" required>
                        <option value="">Sélectionner un fichier</option>
                        <?php foreach ($backups as $backup): ?>
                            <?php if ($backup['type'] == 'sql'): ?>
                                <option value="<?php echo $backup['name']; ?>">
                                    <?php echo $backup['name']; ?> 
                                    (<?php echo round($backup['size']/1024, 2); ?>KB - <?php echo date('d/m/Y H:i', $backup['date']); ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Attention:</strong> La restauration va remplacer les données actuelles.
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirmer_restore" required>
                        <label class="form-check-label">Je confirme vouloir restaurer les données</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-upload"></i> Restaurer
                </button>
            </form>
        </div>

        <!-- Nettoyage -->
        <div class="backup-card">
            <h4><i class="fas fa-broom"></i> Nettoyage des Sauvegardes</h4>
            <p>Supprimer les anciennes sauvegardes pour libérer de l'espace.</p>
            
            <form method="POST" action="?action=cleanup">
                <div class="mb-3">
                    <label class="form-label">Conserver les sauvegardes des X derniers jours</label>
                    <select name="jours_conservation" class="form-control">
                        <option value="7">7 jours</option>
                        <option value="15">15 jours</option>
                        <option value="30" selected>30 jours</option>
                        <option value="60">60 jours</option>
                        <option value="90">90 jours</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirmer_cleanup" required>
                        <label class="form-check-label">Je confirme vouloir nettoyer les sauvegardes</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-broom"></i> Nettoyer
                </button>
            </form>
        </div>

        <!-- Liste des sauvegardes -->
        <div class="backup-list">
            <h4><i class="fas fa-list"></i> Sauvegardes Disponibles</h4>
            
            <?php if (empty($backups)): ?>
                <p class="text-muted">Aucune sauvegarde disponible.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nom du fichier</th>
                                <th>Taille</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                    <td><?php echo round($backup['size']/1024, 2); ?>KB</td>
                                    <td><?php echo date('d/m/Y H:i', $backup['date']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $backup['type'] == 'sql' ? 'primary' : 'success'; ?>">
                                            <?php echo strtoupper($backup['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo $backupDir . $backup['name']; ?>" class="btn btn-sm btn-outline-primary" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Liens utiles -->
        <div class="row">
            <div class="col-md-4">
                <div class="backup-card text-center">
                    <h5><i class="fas fa-cogs"></i> Administration</h5>
                    <a href="admin_journal.php" class="btn btn-primary">
                        <i class="fas fa-tools"></i> Outils Admin
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="backup-card text-center">
                    <h5><i class="fas fa-heartbeat"></i> Monitoring</h5>
                    <a href="monitoring_journal.php" class="btn btn-info">
                        <i class="fas fa-chart-line"></i> Surveillance
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="backup-card text-center">
                    <h5><i class="fas fa-eye"></i> Journal</h5>
                    <a href="journal.php" class="btn btn-success">
                        <i class="fas fa-list"></i> Voir Journal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
