<?php
/**
 * OUTILS D'ADMINISTRATION DU JOURNAL UNIFIÉ
 * Interface d'administration complète pour le système de journalisation
 */

require_once 'fonction_traitement/fonction.php';
require_once 'fonction_traitement/JournalUnifie.php';

// Vérification des droits d'accès (admin uniquement)

try {
    include('db/connecting.php');
    
    $journalUnifie = new JournalUnifie($cnx);
    
    // Actions d'administration
    $action = $_GET['action'] ?? 'dashboard';
    $message = '';
    $error = '';
    
    switch($action) {
        case 'migrer':
            if (isset($_POST['confirmer_migration'])) {
                $totalMigre = $journalUnifie->migrerAnciennesTables();
                $message = "Migration terminée : $totalMigre entrées migrées";
            }
            break;
            
        case 'nettoyer':
            if (isset($_POST['confirmer_nettoyage'])) {
                $jours = (int)$_POST['jours_conservation'];
                $supprimees = $journalUnifie->nettoyerJournal($jours);
                $message = "Nettoyage terminé : $supprimees entrées supprimées";
            }
            break;
            
        case 'exporter':
            if (isset($_POST['exporter'])) {
                $filters = [
                    'date_debut' => $_POST['date_debut'] ?? '',
                    'date_fin' => $_POST['date_fin'] ?? '',
                    'module' => $_POST['module'] ?? '',
                    'action' => $_POST['action'] ?? ''
                ];
                $journalUnifie->exporterCSV($filters, 'journal_admin_' . date('Y-m-d_H-i-s') . '.csv');
                exit;
            }
            break;
            
        case 'statistiques':
            $stats = $journalUnifie->getStatistiques([
                'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
                'date_fin' => $_GET['date_fin'] ?? date('Y-m-d')
            ]);
            break;
    }
    
    // Récupération des données pour le dashboard
    $totalEntrees = $cnx->query("SELECT COUNT(*) as count FROM journal_unifie")->fetch()['count'];
    $entreesAujourdhui = $cnx->query("SELECT COUNT(*) as count FROM journal_unifie WHERE DATE(date_action) = CURDATE()")->fetch()['count'];
    $modulesPopulaires = $cnx->query("SELECT module, COUNT(*) as count FROM journal_unifie GROUP BY module ORDER BY count DESC LIMIT 5")->fetchAll();
    $actionsPopulaires = $cnx->query("SELECT action, COUNT(*) as count FROM journal_unifie GROUP BY action ORDER BY count DESC LIMIT 5")->fetchAll();
    $utilisateursActifs = $cnx->query("SELECT u.NomPrenom, COUNT(j.IDJOURNAL) as count FROM journal_unifie j JOIN utilisateur u ON j.IDUTILISATEUR = u.IDUTILISATEUR GROUP BY j.IDUTILISATEUR ORDER BY count DESC LIMIT 5")->fetchAll();
    
} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Journal Unifié</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .admin-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stat-number { font-size: 2.5rem; font-weight: bold; color: #667eea; }
        .admin-nav { background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px; }
        .chart-container { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .table-admin { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .btn-admin { border-radius: 8px; padding: 10px 20px; font-weight: 600; }
    </style>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header text-center">
            <h1><i class="fas fa-cogs"></i> Administration Journal Unifié</h1>
            <p class="mb-0">Gestion et maintenance du système de journalisation</p>
        </div>

        <!-- Navigation -->
        <div class="admin-nav">
            <div class="row">
                <div class="col-md-2">
                    <a href="?action=dashboard" class="btn btn-admin <?php echo $action == 'dashboard' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="?action=migrer" class="btn btn-admin <?php echo $action == 'migrer' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                        <i class="fas fa-sync"></i> Migration
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="?action=nettoyer" class="btn btn-admin <?php echo $action == 'nettoyer' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                        <i class="fas fa-broom"></i> Nettoyage
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="?action=exporter" class="btn btn-admin <?php echo $action == 'exporter' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="?action=statistiques" class="btn btn-admin <?php echo $action == 'statistiques' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                        <i class="fas fa-chart-bar"></i> Statistiques
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="journal.php" class="btn btn-admin btn-success w-100">
                        <i class="fas fa-eye"></i> Voir Journal
                    </a>
                </div>
            </div>
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

        <!-- Contenu selon l'action -->
        <?php if ($action == 'dashboard'): ?>
            <!-- Dashboard -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo number_format($totalEntrees); ?></div>
                        <div class="text-muted">Total Entrées</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo number_format($entreesAujourdhui); ?></div>
                        <div class="text-muted">Aujourd'hui</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo count($modulesPopulaires); ?></div>
                        <div class="text-muted">Modules Actifs</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo count($utilisateursActifs); ?></div>
                        <div class="text-muted">Utilisateurs Actifs</div>
                    </div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5><i class="fas fa-chart-pie"></i> Modules Populaires</h5>
                        <canvas id="modulesChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5><i class="fas fa-chart-bar"></i> Actions Populaires</h5>
                        <canvas id="actionsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tableaux -->
            <div class="row">
                <div class="col-md-6">
                    <div class="table-admin">
                        <div class="p-3 border-bottom">
                            <h5><i class="fas fa-users"></i> Utilisateurs Actifs</h5>
                        </div>
                        <div class="p-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($utilisateursActifs as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['NomPrenom']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $user['count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-admin">
                        <div class="p-3 border-bottom">
                            <h5><i class="fas fa-cogs"></i> Modules Populaires</h5>
                        </div>
                        <div class="p-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modulesPopulaires as $module): ?>
                                        <tr>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($module['module']); ?></span></td>
                                            <td><?php echo $module['count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action == 'migrer'): ?>
            <!-- Migration -->
            <div class="stat-card">
                <h4><i class="fas fa-sync"></i> Migration des Données</h4>
                <p>Migrer les données des anciennes tables vers le journal unifié.</p>
                
                <form method="POST">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention :</strong> Cette opération va migrer toutes les données des anciennes tables vers journal_unifie.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmer la migration</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirmer_migration" required>
                            <label class="form-check-label">Je confirme vouloir migrer les données</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-sync"></i> Lancer la Migration
                    </button>
                </form>
            </div>

        <?php elseif ($action == 'nettoyer'): ?>
            <!-- Nettoyage -->
            <div class="stat-card">
                <h4><i class="fas fa-broom"></i> Nettoyage du Journal</h4>
                <p>Supprimer les anciennes entrées du journal pour optimiser les performances.</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Conserver les données des X derniers jours</label>
                        <select name="jours_conservation" class="form-control">
                            <option value="30">30 jours</option>
                            <option value="90">90 jours</option>
                            <option value="180">6 mois</option>
                            <option value="365" selected>1 an</option>
                            <option value="730">2 ans</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention :</strong> Cette opération est irréversible. Les données plus anciennes seront définitivement supprimées.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmer le nettoyage</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirmer_nettoyage" required>
                            <label class="form-check-label">Je confirme vouloir nettoyer le journal</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-broom"></i> Lancer le Nettoyage
                    </button>
                </form>
            </div>

        <?php elseif ($action == 'exporter'): ?>
            <!-- Export -->
            <div class="stat-card">
                <h4><i class="fas fa-download"></i> Export des Données</h4>
                <p>Exporter les données du journal vers un fichier CSV.</p>
                
                <form method="POST">
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
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Module</label>
                                <select name="module" class="form-control">
                                    <option value="">Tous les modules</option>
                                    <option value="article">Articles</option>
                                    <option value="client">Clients</option>
                                    <option value="stock">Stock</option>
                                    <option value="vente">Ventes</option>
                                    <option value="connexion">Connexions</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Action</label>
                                <select name="action" class="form-control">
                                    <option value="">Toutes les actions</option>
                                    <option value="CREATION">Création</option>
                                    <option value="MODIFICATION">Modification</option>
                                    <option value="SUPPRESSION">Suppression</option>
                                    <option value="ENTREE">Entrée</option>
                                    <option value="SORTIE">Sortie</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="exporter" class="btn btn-success">
                        <i class="fas fa-download"></i> Exporter en CSV
                    </button>
                </form>
            </div>

        <?php elseif ($action == 'statistiques'): ?>
            <!-- Statistiques -->
            <div class="stat-card">
                <h4><i class="fas fa-chart-bar"></i> Statistiques Détaillées</h4>
                
                <form method="GET" class="mb-4">
                    <input type="hidden" name="action" value="statistiques">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Date de début</label>
                            <input type="date" name="date_debut" class="form-control" value="<?php echo $_GET['date_debut'] ?? date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date de fin</label>
                            <input type="date" name="date_fin" class="form-control" value="<?php echo $_GET['date_fin'] ?? date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">
                                <i class="fas fa-chart-line"></i> Générer
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if (isset($stats) && !empty($stats)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Nombre</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats as $stat): ?>
                                    <tr>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($stat['module']); ?></span></td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($stat['action']); ?></span></td>
                                        <td><?php echo $stat['nombre_actions']; ?></td>
                                        <td><?php echo $stat['date_action']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Graphique des modules
        const modulesData = <?php echo json_encode($modulesPopulaires); ?>;
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

        // Graphique des actions
        const actionsData = <?php echo json_encode($actionsPopulaires); ?>;
        const actionsLabels = actionsData.map(item => item.action);
        const actionsCounts = actionsData.map(item => item.count);
        
        new Chart(document.getElementById('actionsChart'), {
            type: 'bar',
            data: {
                labels: actionsLabels,
                datasets: [{
                    label: 'Nombre d\'actions',
                    data: actionsCounts,
                    backgroundColor: '#36A2EB'
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
    </script>
</body>
</html>
