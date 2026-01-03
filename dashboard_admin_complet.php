<?php
require_once 'db/connecting.php';
require_once 'fonction_traitement/fonction.php';
check_access();

// Récupération de la période
$annee_debut = isset($_GET['annee_debut']) ? intval($_GET['annee_debut']) : (date('Y') - 4);
$annee_fin = isset($_GET['annee_fin']) ? intval($_GET['annee_fin']) : date('Y');
$date_analyse = isset($_GET['date_analyse']) ? $_GET['date_analyse'] : date('Y-m-d');

try {
    // 1. RÉCUPÉRATION DE TOUTES LES ANNÉES
    $sql_annees_completes = "
        SELECT DISTINCT YEAR(DateIns) AS annee 
        FROM vente 
        UNION
        SELECT DISTINCT YEAR(DateIns) AS annee 
        FROM ventes_credit_paiement
        UNION
        SELECT DISTINCT YEAR(date_paiement) AS annee 
        FROM sav_paiement
        ORDER BY annee
    ";
    $stmt = $cnx->query($sql_annees_completes);
    $annees_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. ANALYSE JOURNÉE ACTUELLE (CORRIGÉ - REQUÊTES SÉPARÉES)
    // Ventes du jour
    $sql_ventes_jour = "
        SELECT 
            COALESCE(SUM(MontantTotal), 0) AS ventes_jour,
            COUNT(*) AS nombre_ventes_jour
        FROM vente 
        WHERE DATE(DateIns) = DATE(NOW())
    ";
    $stmt = $cnx->prepare($sql_ventes_jour);
    $stmt->execute();
    $ventes_jour = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // SAV du jour
    $sql_sav_jour = "
        SELECT 
            COALESCE(SUM(montant), 0) AS sav_jour,
            COUNT(*) AS nombre_sav_jour
        FROM sav_paiement 
        WHERE DATE(date_paiement) = DATE(NOW())
    ";
    $stmt = $cnx->prepare($sql_sav_jour);
    $stmt->execute();
    $sav_jour = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Versements du jour
    $sql_versements_jour = "
        SELECT COALESCE(SUM(MontantVersement), 0) AS versements_jour
        FROM versement 
        WHERE DATE(DateIns) = DATE(NOW())
    ";
    $stmt = $cnx->prepare($sql_versements_jour);
    $stmt->execute();
    $versements_jour = $stmt->fetchColumn() ?: 0;
    
    // Combinaison des résultats
    $journee = [
        'ventes_jour' => $ventes_jour['ventes_jour'],
        'nombre_ventes_jour' => $ventes_jour['nombre_ventes_jour'],
        'sav_jour' => $sav_jour['sav_jour'],
        'nombre_sav_jour' => $sav_jour['nombre_sav_jour'],
        'versements_jour' => $versements_jour
    ];
    
    // 3. ANALYSE MOIS ACTUEL (CORRIGÉ - REQUÊTES SÉPARÉES)
    // Ventes du mois
    $sql_ventes_mois = "
        SELECT 
            COALESCE(SUM(MontantTotal), 0) AS ventes_mois,
            COUNT(*) AS nombre_ventes_mois
        FROM vente 
        WHERE MONTH(DateIns) = MONTH(NOW()) AND YEAR(DateIns) = YEAR(NOW())
    ";
    $stmt = $cnx->prepare($sql_ventes_mois);
    $stmt->execute();
    $ventes_mois = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // SAV du mois
    $sql_sav_mois = "
        SELECT 
            COALESCE(SUM(montant), 0) AS sav_mois,
            COUNT(*) AS nombre_sav_mois
        FROM sav_paiement 
        WHERE MONTH(date_paiement) = MONTH(NOW()) AND YEAR(date_paiement) = YEAR(NOW())
    ";
    $stmt = $cnx->prepare($sql_sav_mois);
    $stmt->execute();
    $sav_mois = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Versements du mois
    $sql_versements_mois = "
        SELECT COALESCE(SUM(MontantVersement), 0) AS versements_mois
        FROM versement 
        WHERE MONTH(DateIns) = MONTH(NOW()) AND YEAR(DateIns) = YEAR(NOW())
    ";
    $stmt = $cnx->prepare($sql_versements_mois);
    $stmt->execute();
    $versements_mois = $stmt->fetchColumn() ?: 0;
    
    // Combinaison des résultats
    $mois = [
        'ventes_mois' => $ventes_mois['ventes_mois'],
        'nombre_ventes_mois' => $ventes_mois['nombre_ventes_mois'],
        'sav_mois' => $sav_mois['sav_mois'],
        'nombre_sav_mois' => $sav_mois['nombre_sav_mois'],
        'versements_mois' => $versements_mois
    ];
    
    // 4. ANALYSE ANNÉE ACTUELLE (CORRIGÉ - REQUÊTES SÉPARÉES)
    // Ventes de l'année
    $sql_ventes_annee = "
        SELECT 
            COALESCE(SUM(MontantTotal), 0) AS ventes_annee,
            COUNT(*) AS nombre_ventes_annee
        FROM vente 
        WHERE YEAR(DateIns) = YEAR(NOW())
    ";
    $stmt = $cnx->prepare($sql_ventes_annee);
    $stmt->execute();
    $ventes_annee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // SAV de l'année
    $sql_sav_annee = "
        SELECT 
            COALESCE(SUM(montant), 0) AS sav_annee,
            COUNT(*) AS nombre_sav_annee
        FROM sav_paiement 
        WHERE YEAR(date_paiement) = YEAR(NOW())
    ";
    $stmt = $cnx->prepare($sql_sav_annee);
    $stmt->execute();
    $sav_annee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Versements de l'année
    $sql_versements_annee = "
        SELECT COALESCE(SUM(MontantVersement), 0) AS versements_annee
        FROM versement 
        WHERE YEAR(DateIns) = YEAR(NOW())
    ";
    $stmt = $cnx->prepare($sql_versements_annee);
    $stmt->execute();
    $versements_annee = $stmt->fetchColumn() ?: 0;
    
    // Combinaison des résultats
    $annee = [
        'ventes_annee' => $ventes_annee['ventes_annee'],
        'nombre_ventes_annee' => $ventes_annee['nombre_ventes_annee'],
        'sav_annee' => $sav_annee['sav_annee'],
        'nombre_sav_annee' => $sav_annee['nombre_sav_annee'],
        'versements_annee' => $versements_annee
    ];
    
    // 5. VÉRIFICATION INTÉGRITÉ
    $sql_integrite = "
        SELECT 
            (SELECT COUNT(*) FROM vente WHERE DATE(DateIns) = DATE(NOW())) AS ventes_aujourd_hui,
            (SELECT COUNT(*) FROM sav_paiement WHERE DATE(date_paiement) = DATE(NOW())) AS sav_aujourd_hui,
            (SELECT COUNT(*) FROM versement WHERE DATE(DateIns) = DATE(NOW())) AS versements_aujourd_hui,
            (SELECT COUNT(*) FROM sav_dossier sd LEFT JOIN sav_paiement sp ON sd.id_sav = sp.id_sav WHERE sp.id_sav IS NULL) AS sav_sans_paiement,
            (SELECT COUNT(*) FROM vente v LEFT JOIN facture_article fa ON v.NumeroVente = fa.NumeroVente WHERE fa.NumeroVente IS NULL) AS ventes_sans_articles
    ";
    $stmt = $cnx->query($sql_integrite);
    $integrite = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 6. TOP 5 MEILLEURS ARTICLES
    $sql_top_articles = "
        SELECT a.libelle, COUNT(fa.IDARTICLE) AS total_ventes
        FROM facture_article fa
        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
        JOIN vente v ON fa.NumeroVente = v.NumeroVente
        WHERE YEAR(v.DateIns) = YEAR(NOW())
        GROUP BY a.libelle, a.IDARTICLE
        ORDER BY total_ventes DESC
        LIMIT 5
    ";
    $stmt = $cnx->prepare($sql_top_articles);
    $stmt->execute();
    $top_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. DERNIERS DOSSERS SAV
    $sql_derniers_sav = "
        SELECT sd.numero_sav, sd.cout_estime, sp.montant, sp.date_paiement, 
               COUNT(spiece.id_piece) as nombre_materiaux
        FROM sav_dossier sd
        LEFT JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
        LEFT JOIN sav_piece spiece ON sd.id_sav = spiece.id_sav
        GROUP BY sd.id_sav, sd.numero_sav, sd.cout_estime, sp.montant, sp.date_paiement
        ORDER BY sp.date_paiement DESC
        LIMIT 5
    ";
    $stmt = $cnx->query($sql_derniers_sav);
    $derniers_sav = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Complet - Gestion Commerciale</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #ecf0f1;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .container-fluid {
            max-width: 1800px;
            margin: 0 auto;
        }
        
        .metric-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 5px solid var(--secondary-color);
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .metric-icon {
            font-size: 40px;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .metric-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-success { background-color: var(--success-color); }
        .status-warning { background-color: var(--warning-color); }
        .status-danger { background-color: var(--danger-color); }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--light-bg);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--primary-color);
            font-weight: 600;
            padding: 15px 25px;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--secondary-color);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .table-custom {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .table-custom th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px;
        }
        
        .table-custom td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .period-selector {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <div class="dashboard-header text-center">
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Admin Complet</h1>
        <p class="mb-0">Tableau de bord intelligent pour la gestion commerciale</p>
        <small>Dernière mise à jour : <?php echo date('d/m/Y H:i'); ?></small>
    </div>

    <div class="container-fluid">
        <!-- Sélecteur de période -->
        <div class="period-selector">
            <form method="GET" action="" class="row align-items-center">
                <div class="col-md-3">
                    <label><strong>Période d'analyse :</strong></label>
                    <div class="d-flex">
                        <input type="number" name="annee_debut" value="<?php echo $annee_debut; ?>" 
                               class="form-control mr-2" min="2000" max="2030" placeholder="Début">
                        <input type="number" name="annee_fin" value="<?php echo $annee_fin; ?>" 
                               class="form-control" min="2000" max="2030" placeholder="Fin">
                    </div>
                </div>
                <div class="col-md-3">
                    <label><strong>Date spécifique :</strong></label>
                    <input type="date" name="date_analyse" value="<?php echo $date_analyse; ?>" 
                           class="form-control">
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label><br>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Analyser
                    </button>
                </div>
                <div class="col-md-3 text-right">
                    <a href="analyse_complete_annuelle.php" class="btn btn-info">
                        <i class="fas fa-chart-line"></i> Analyse Détaillée
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistiques rapides -->
        <div class="quick-stats">
            <div class="metric-card">
                <i class="metric-icon fas fa-calendar-day"></i>
                <div class="metric-label">AUJOURD'HUI</div>
                <div class="metric-value"><?php echo number_format($journee['ventes_jour'] + $journee['sav_jour'], 0, ',', ' '); ?> FCFA</div>
                <small class="text-muted">
                    <?php echo $journee['nombre_ventes_jour']; ?> ventes, 
                    <?php echo $journee['nombre_sav_jour']; ?> SAV
                </small>
            </div>
            
            <div class="metric-card">
                <i class="metric-icon fas fa-calendar-week"></i>
                <div class="metric-label">CE MOIS</div>
                <div class="metric-value"><?php echo number_format($mois['ventes_mois'] + $mois['sav_mois'], 0, ',', ' '); ?> FCFA</div>
                <small class="text-muted">
                    <?php echo $mois['nombre_ventes_mois']; ?> ventes, 
                    <?php echo $mois['nombre_sav_mois']; ?> SAV
                </small>
            </div>
            
            <div class="metric-card">
                <i class="metric-icon fas fa-calendar-alt"></i>
                <div class="metric-label">CETTE ANNÉE</div>
                <div class="metric-value"><?php echo number_format($annee['ventes_annee'] + $annee['sav_annee'], 0, ',', ' '); ?> FCFA</div>
                <small class="text-muted">
                    <?php echo $annee['nombre_ventes_annee']; ?> ventes, 
                    <?php echo $annee['nombre_sav_annee']; ?> SAV
                </small>
            </div>
            
            <div class="metric-card">
                <i class="metric-icon fas fa-shield-alt"></i>
                <div class="metric-label">INTÉGRITÉ</div>
                <div class="metric-value">
                    <?php 
                    $anomalies = 0;
                    if ($integrite['sav_sans_paiement'] > 0) $anomalies++;
                    if ($integrite['ventes_sans_articles'] > 0) $anomalies++;
                    echo $anomalies == 0 ? "✅ OK" : "⚠️ $anomalies";
                    ?>
                </div>
                <small class="text-muted">
                    <?php echo $anomalies; ?> anomalie(s) détectée(s)
                </small>
            </div>
        </div>

        <!-- Onglets principaux -->
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab">
                    <i class="fas fa-eye"></i> Vue d'ensemble
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="analysis-tab" data-toggle="tab" href="#analysis" role="tab">
                    <i class="fas fa-chart-bar"></i> Analyse détaillée
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="integrity-tab" data-toggle="tab" href="#integrity" role="tab">
                    <i class="fas fa-shield-alt"></i> Vérification intégrité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="performance-tab" data-toggle="tab" href="#performance" role="tab">
                    <i class="fas fa-trophy"></i> Performance
                </a>
            </li>
        </ul>

        <div class="tab-content" id="dashboardTabContent">
            <!-- Vue d'ensemble -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h4><i class="fas fa-chart-line"></i> Évolution du Chiffre d'Affaires</h4>
                            <div id="caChart" style="height: 400px;"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-container">
                            <h4><i class="fas fa-chart-pie"></i> Répartition des activités</h4>
                            <div id="pieChart" style="height: 400px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analyse détaillée -->
            <div class="tab-pane fade" id="analysis" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h4><i class="fas fa-shopping-cart"></i> Top 5 Articles Vendus</h4>
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>Article</th>
                                            <th>Ventes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_articles as $article): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($article['libelle']); ?></td>
                                            <td><span class="badge badge-primary"><?php echo $article['total_ventes']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h4><i class="fas fa-tools"></i> Derniers Dossiers SAV</h4>
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>N° SAV</th>
                                            <th>Coût Estimé</th>
                                            <th>Paiement</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($derniers_sav as $sav): ?>
                                        <tr>
                                            <td><?php echo $sav['numero_sav']; ?></td>
                                            <td><?php echo number_format($sav['cout_estime'], 0, ',', ' '); ?> FCFA</td>
                                            <td><?php echo number_format($sav['montant'], 0, ',', ' '); ?> FCFA</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vérification intégrité -->
            <div class="tab-pane fade" id="integrity" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-custom alert-info">
                            <h5><i class="fas fa-info-circle"></i> État de la base de données</h5>
                            <p><strong>Années disponibles :</strong> <?php echo implode(', ', $annees_disponibles); ?></p>
                            <p><strong>Données aujourd'hui :</strong></p>
                            <ul>
                                <li>Ventes : <?php echo $integrite['ventes_aujourd_hui']; ?></li>
                                <li>SAV : <?php echo $integrite['sav_aujourd_hui']; ?></li>
                                <li>Versements : <?php echo $integrite['versements_aujourd_hui']; ?></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-custom <?php echo ($integrite['sav_sans_paiement'] > 0 || $integrite['ventes_sans_articles'] > 0) ? 'alert-warning' : 'alert-success'; ?>">
                            <h5><i class="fas fa-exclamation-triangle"></i> Vérifications d'intégrité</h5>
                            <?php if ($integrite['sav_sans_paiement'] > 0): ?>
                                <p><span class="status-indicator status-warning"></span>
                                   <?php echo $integrite['sav_sans_paiement']; ?> dossiers SAV sans paiement</p>
                            <?php endif; ?>
                            <?php if ($integrite['ventes_sans_articles'] > 0): ?>
                                <p><span class="status-indicator status-warning"></span>
                                   <?php echo $integrite['ventes_sans_articles']; ?> ventes sans articles</p>
                            <?php endif; ?>
                            <?php if ($integrite['sav_sans_paiement'] == 0 && $integrite['ventes_sans_articles'] == 0): ?>
                                <p><span class="status-indicator status-success"></span> Aucune anomalie détectée</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance -->
            <div class="tab-pane fade" id="performance" role="tabpanel">
                <div class="row">
                    <div class="col-md-12">
                        <div class="chart-container">
                            <h4><i class="fas fa-tachometer-alt"></i> Indicateurs de Performance</h4>
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <div class="metric-card">
                                        <i class="metric-icon fas fa-percentage"></i>
                                        <div class="metric-label">Marge Globale</div>
                                        <div class="metric-value">
                                            <?php 
                                            $ca_total = $annee['ventes_annee'] + $annee['sav_annee'];
                                            $marge = $ca_total > 0 ? (($annee['versements_annee'] - $ca_total) / $ca_total) * 100 : 0;
                                            echo number_format($marge, 1) . '%';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="metric-card">
                                        <i class="metric-icon fas fa-chart-line"></i>
                                        <div class="metric-label">Tendance</div>
                                        <div class="metric-value">
                                            <?php 
                                            $tendance = $journee['ventes_jour'] > 0 ? '📈' : '📉';
                                            echo $tendance;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="metric-card">
                                        <i class="metric-icon fas fa-users"></i>
                                        <div class="metric-label">Activité</div>
                                        <div class="metric-value">
                                            <?php echo $journee['nombre_ventes_jour'] + $journee['nombre_sav_jour']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="metric-card">
                                        <i class="metric-icon fas fa-star"></i>
                                        <div class="metric-label">Score</div>
                                        <div class="metric-value">
                                            <?php 
                                            $score = 0;
                                            if ($journee['ventes_jour'] > 0) $score += 25;
                                            if ($journee['sav_jour'] > 0) $score += 25;
                                            if ($integrite['sav_sans_paiement'] == 0) $score += 25;
                                            if ($integrite['ventes_sans_articles'] == 0) $score += 25;
                                            echo $score . '/100';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Données pour les graphiques
        const annees = <?php echo json_encode($annees_disponibles); ?>;
        const caData = annees.map(annee => {
            // Simulation des données - à remplacer par les vraies données
            return Math.floor(Math.random() * 1000000) + 100000;
        });
        
        // Graphique CA
        const caTrace = {
            x: annees,
            y: caData,
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Chiffre d\'Affaires',
            line: {color: '#3498db', width: 3},
            marker: {size: 8, color: '#3498db'}
        };
        
        const caLayout = {
            title: 'Évolution du Chiffre d\'Affaires',
            xaxis: {title: 'Années'},
            yaxis: {title: 'Montant (FCFA)'},
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)'
        };
        
        Plotly.newPlot('caChart', [caTrace], caLayout);
        
        // Graphique circulaire
        const pieData = [{
            values: [<?php echo $annee['ventes_annee']; ?>, <?php echo $annee['sav_annee']; ?>],
            labels: ['Ventes Normales', 'SAV'],
            type: 'pie',
            marker: {
                colors: ['#3498db', '#e74c3c']
            }
        }];
        
        const pieLayout = {
            title: 'Répartition des activités',
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)'
        };
        
        Plotly.newPlot('pieChart', pieData, pieLayout);
        
        // Actualisation automatique
        setInterval(function() {
            location.reload();
        }, 300000); // Actualise toutes les 5 minutes
    </script>
</body>
</html> 