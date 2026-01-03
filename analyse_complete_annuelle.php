<?php
try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    check_access();

    // Récupération de la période depuis la requête ou par défaut les 5 dernières années
    $annee_debut = isset($_GET['annee_debut']) ? intval($_GET['annee_debut']) : (date('Y') - 4);
    $annee_fin = isset($_GET['annee_fin']) ? intval($_GET['annee_fin']) : date('Y');

    // Récupération de TOUTES les années disponibles dans la base (ROBUSTE)
    $sql_annees_disponibles = "
        SELECT DISTINCT YEAR(DateIns) AS annee 
        FROM vente 
        WHERE YEAR(DateIns) BETWEEN :debut AND :fin
        UNION
        SELECT DISTINCT YEAR(DateIns) AS annee 
        FROM ventes_credit_paiement
        WHERE YEAR(DateIns) BETWEEN :debut AND :fin
        UNION
        SELECT DISTINCT YEAR(date_paiement) AS annee 
        FROM sav_paiement
        WHERE YEAR(date_paiement) BETWEEN :debut AND :fin
        ORDER BY annee
    ";
    
    $stmt = $cnx->prepare($sql_annees_disponibles);
    $stmt->execute(['debut' => $annee_debut, 'fin' => $annee_fin]);
    $annees_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Créer un tableau avec TOUTES les années de la période
    $labels = [];
    for ($annee = $annee_debut; $annee <= $annee_fin; $annee++) {
        $labels[] = $annee;
    }
    
    $ca_data = [];
    $ventes_data = [];
    $sav_data = [];
    $benefice_data = [];
    $acomptes_data = [];
    $versements_data = [];
    $remises_data = [];
    $ecarts_versement = [];
    
    // Récupération des données pour chaque année
    foreach ($labels as $annee) {
        // 1. Ventes normales (comptant)
        $sql_ventes_normales = "
            SELECT 
                COALESCE(SUM(v.MontantTotal), 0) AS montant_ventes,
                COUNT(*) AS nombre_ventes,
                COALESCE(SUM(v.MontantRemise), 0) AS total_remises
            FROM vente v
            WHERE YEAR(v.DateIns) = :annee
        ";
        $stmt = $cnx->prepare($sql_ventes_normales);
        $stmt->execute(['annee' => $annee]);
        $ventes_normales = $stmt->fetch(PDO::FETCH_ASSOC);
        $montant_ventes = $ventes_normales['montant_ventes'] ?: 0;
        $nombre_ventes = $ventes_normales['nombre_ventes'] ?: 0;
        $total_remises = $ventes_normales['total_remises'] ?: 0;
        
        // 2. Acomptes des ventes à crédit
        $sql_acomptes_credit = "
            SELECT 
                COALESCE(SUM(vcp.AccompteVerse), 0) AS montant_acomptes,
                COUNT(*) AS nombre_acomptes
            FROM ventes_credit_paiement vcp
            JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
            WHERE YEAR(vcp.DateIns) = :annee AND vc.Statut != 'Transféré'
        ";
        $stmt = $cnx->prepare($sql_acomptes_credit);
        $stmt->execute(['annee' => $annee]);
        $acomptes_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $montant_acomptes = $acomptes_result['montant_acomptes'] ?: 0;
        $nombre_acomptes = $acomptes_result['nombre_acomptes'] ?: 0;
        
        // 3. Paiements SAV
        $sql_sav_paiements = "
            SELECT 
                COALESCE(SUM(sp.montant), 0) AS montant_sav,
                COUNT(*) AS nombre_paiements_sav
            FROM sav_paiement sp
            JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
            WHERE YEAR(sp.date_paiement) = :annee
        ";
        $stmt = $cnx->prepare($sql_sav_paiements);
        $stmt->execute(['annee' => $annee]);
        $sav_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $montant_sav = $sav_result['montant_sav'] ?: 0;
        $nombre_paiements_sav = $sav_result['nombre_paiements_sav'] ?: 0;
        
        // 4. Versements
        $sql_versements = "
            SELECT COALESCE(SUM(MontantVersement), 0) AS total_versements
            FROM versement 
            WHERE YEAR(DateIns) = :annee
        ";
        $stmt = $cnx->prepare($sql_versements);
        $stmt->execute(['annee' => $annee]);
        $total_versements = $stmt->fetchColumn() ?: 0;
        
        // 5. CALCULS DE BÉNÉFICE (AVEC CORRECTIONS APPLIQUÉES)
        
        // 5.1. Bénéfice sur les ventes normales
        $sql_cout_ventes_normales = "
            SELECT COALESCE(SUM(a.PrixAchatHT * fa.QuantiteVendue), 0) AS cout_ventes
            FROM facture_article fa
            JOIN article a ON fa.IDARTICLE = a.IDARTICLE
            JOIN vente v ON fa.NumeroVente = v.NumeroVente
            WHERE YEAR(v.DateIns) = :annee
        ";
        $stmt = $cnx->prepare($sql_cout_ventes_normales);
        $stmt->execute(['annee' => $annee]);
        $cout_ventes_normales = $stmt->fetchColumn() ?: 0;
        $benefice_ventes_normales = $montant_ventes - $cout_ventes_normales;
        
        // 5.2. Bénéfice sur les acomptes des ventes à crédit (AVEC VÉRIFICATIONS DE SÉCURITÉ)
        $sql_cout_acomptes_credit = "
            SELECT COALESCE(SUM(
                CASE 
                    WHEN vc.MontantTotalCredit > 0 THEN 
                        a.PrixAchatHT * vcl.QuantiteVendue * (vcp.AccompteVerse / vc.MontantTotalCredit)
                    ELSE 0
                END
            ), 0) AS cout_acomptes
            FROM ventes_credit_paiement vcp
            JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
            JOIN ventes_credit_ligne vcl ON vc.IDVenteCredit = vcl.IDVenteCredit
            JOIN article a ON vcl.IDARTICLE = a.IDARTICLE
            WHERE YEAR(vcp.DateIns) = :annee AND vc.Statut != 'Transféré'
        ";
        $stmt = $cnx->prepare($sql_cout_acomptes_credit);
        $stmt->execute(['annee' => $annee]);
        $cout_acomptes_credit = $stmt->fetchColumn() ?: 0;
        $benefice_acomptes_credit = $montant_acomptes - $cout_acomptes_credit;
        
        // 5.3. Bénéfice sur les paiements SAV (CORRECTION APPLIQUÉE)
        // Récupérer le coût estimatif total des dossiers SAV payés cette année
        $sql_cout_estime_sav = "
            SELECT COALESCE(SUM(sd.cout_estime), 0) AS cout_estime_total
            FROM sav_dossier sd
            JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
            WHERE YEAR(sp.date_paiement) = :annee
        ";
        $stmt = $cnx->prepare($sql_cout_estime_sav);
        $stmt->execute(['annee' => $annee]);
        $cout_estime_sav = $stmt->fetchColumn() ?: 0;
        
        // Récupérer le coût réel des matériaux SAV
        $sql_cout_materiaux_sav = "
            SELECT COALESCE(SUM(spiece.cout_total), 0) AS cout_materiaux
            FROM sav_piece spiece
            JOIN sav_dossier sd ON spiece.id_sav = sd.id_sav
            JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
            WHERE YEAR(sp.date_paiement) = :annee
        ";
        $stmt = $cnx->prepare($sql_cout_materiaux_sav);
        $stmt->execute(['annee' => $annee]);
        $cout_materiaux_sav = $stmt->fetchColumn() ?: 0;
        
        // Bénéfice SAV = Coût estimatif - Coût matériaux (CORRIGÉ)
        $benefice_sav = $cout_estime_sav - $cout_materiaux_sav;
        
        // 6. CALCULS GLOBAUX
        $ca_total = $montant_ventes + $montant_acomptes + $montant_sav;
        $benefice_total = $benefice_ventes_normales + $benefice_acomptes_credit + $benefice_sav;
        $ecart_versement = $total_versements - $ca_total;
        
        // Stockage des données
        $ca_data[] = $ca_total;
        $ventes_data[] = $nombre_ventes;
        $sav_data[] = $montant_sav;
        $benefice_data[] = $benefice_total;
        $acomptes_data[] = $montant_acomptes;
        $versements_data[] = $total_versements;
        $remises_data[] = $total_remises;
        $ecarts_versement[] = $ecart_versement;
    }
    
    // Calculs globaux
    $ca_total_periode = array_sum($ca_data);
    $ventes_total_periode = array_sum($ventes_data);
    $sav_total_periode = array_sum($sav_data);
    $benefice_total_periode = array_sum($benefice_data);
    $versements_total_periode = array_sum($versements_data);
    $remises_total_periode = array_sum($remises_data);
    $ecart_versement_periode = array_sum($ecarts_versement);
    
    // Moyennes
    $nb_annees = count($labels);
    $ca_moyen = $nb_annees > 0 ? $ca_total_periode / $nb_annees : 0;
    $ventes_moyen = $nb_annees > 0 ? $ventes_total_periode / $nb_annees : 0;
    
    // Évolution en pourcentage
    $evolution_ca = 0;
    if (count($ca_data) >= 2 && $ca_data[0] > 0) {
        $evolution_ca = (($ca_data[count($ca_data)-1] - $ca_data[0]) / $ca_data[0]) * 100;
    } elseif (count($ca_data) >= 2 && $ca_data[0] == 0 && $ca_data[count($ca_data)-1] > 0) {
        $evolution_ca = 100;
    }
    
} catch (Throwable $th) {
    error_log('Erreur analyse_complete_annuelle.php: ' . $th->getMessage());
    $erreur = 'Erreur lors de l\'analyse des données';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse Complète Annuelle - SO-TECH</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <style>
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0; }
            100% { opacity: 1; }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        header {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: white;
            padding: 25px;
            text-align: center;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .period-picker {
            text-align: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .period-picker input[type="number"] {
            border: 3px solid #ddd;
            border-radius: 15px;
            padding: 15px 20px;
            font-size: 18px;
            width: 150px;
            margin: 0 10px;
            text-align: center;
        }
        
        .period-picker button {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 10px;
        }
        
        .period-picker button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .info-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
            margin: 15px 0;
            transition: all 0.3s;
            border-left: 5px solid #ff0000;
        }
        
        .info-box:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .info-icon {
            font-size: 45px;
            color: #ff0000;
            margin-bottom: 15px;
        }
        
        .metric-highlight {
            font-size: 28px;
            font-weight: bold;
            color: #ff0000;
            margin: 15px 0;
        }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            margin: 30px 0;
        }
        
        .chart-container h3 {
            color: #333;
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border: none;
            color: #0c5460;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .evolution-indicator {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .evolution-positive {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .evolution-negative {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }
        
        .evolution-neutral {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        
        .section-title {
            color: #333;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .chart-wrapper {
            height: 600px;
            width: 100%;
        }
        
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .table th {
            background: linear-gradient(135deg, #343a40, #495057);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }
        
        .table td {
            vertical-align: middle;
            padding: 12px 15px;
        }
        
        .badge-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .stats-detail {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
            line-height: 1.6;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1><i class="fas fa-chart-line"></i> Analyse Complète Annuelle - SO-TECH</h1>
        <p>Analyse robuste et fiable de toutes les années avec calculs de bénéfice corrigés</p>
    </header>

    <div class="container">
        <div class="period-picker">
            <form method="GET" action="">
                <label for="annee_debut"><strong>Période d'analyse :</strong></label>
                <input type="number" name="annee_debut" id="annee_debut" value="<?php echo $annee_debut; ?>" min="2000" max="2030" required>
                <span style="font-size: 18px; margin: 0 10px;">à</span>
                <input type="number" name="annee_fin" id="annee_fin" value="<?php echo $annee_fin; ?>" min="2000" max="2030" required>
                <button type="submit"><i class="fas fa-search"></i> Analyser</button>
            </form>
            <div style="margin-top: 15px;">
                <a href="chiffre_daffaire_annuel.php" class="btn btn-info">
                    <i class="fas fa-chart-bar"></i> Retour au Rapport Annuel
                </a>
            </div>
        </div>

        <?php if (isset($erreur)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Erreur :</strong> <?php echo $erreur; ?>
            </div>
        <?php else: ?>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Période analysée :</strong> <?php echo $annee_debut; ?> - <?php echo $annee_fin; ?> 
            <span class="evolution-indicator <?php echo $evolution_ca >= 0 ? 'evolution-positive' : 'evolution-negative'; ?>">
                <i class="fas fa-<?php echo $evolution_ca >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                <?php echo number_format(abs($evolution_ca), 1); ?>% d'évolution
            </span>
        </div>

        <!-- Métriques principales -->
        <div class="row">
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-euro-sign"></i>
                    <h5>CA Total Période</h5>
                    <div class="metric-highlight"><?php echo number_format($ca_total_periode, 0, ',', ' '); ?> FCFA</div>
                    <div class="stats-detail">
                        <i class="fas fa-chart-line"></i> <?php echo $nb_annees; ?> années analysées<br>
                        <i class="fas fa-calculator"></i> Moyenne: <?php echo number_format($ca_moyen, 0, ',', ' '); ?> FCFA/an
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-shopping-cart"></i>
                    <h5>Total Ventes</h5>
                    <div class="metric-highlight"><?php echo number_format($ventes_total_periode, 0, ',', ' '); ?></div>
                    <div class="stats-detail">
                        <i class="fas fa-chart-bar"></i> <?php echo $nb_annees; ?> années<br>
                        <i class="fas fa-calculator"></i> Moyenne: <?php echo number_format($ventes_moyen, 0, ',', ' '); ?>/an
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-tools"></i>
                    <h5>Total SAV</h5>
                    <div class="metric-highlight"><?php echo number_format($sav_total_periode, 0, ',', ' '); ?> FCFA</div>
                    <div class="stats-detail">
                        <i class="fas fa-wrench"></i> Service après-vente<br>
                        <i class="fas fa-percentage"></i> <?php echo $ca_total_periode > 0 ? number_format(($sav_total_periode / $ca_total_periode) * 100, 1) : 0; ?>% du CA
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-chart-line"></i>
                    <h5>Bénéfice Total</h5>
                    <div class="metric-highlight"><?php echo number_format($benefice_total_periode, 0, ',', ' '); ?> FCFA</div>
                    <div class="stats-detail">
                        <i class="fas fa-coins"></i> Marge réalisée<br>
                        <i class="fas fa-percentage"></i> <?php echo $ca_total_periode > 0 ? number_format(($benefice_total_periode / $ca_total_periode) * 100, 1) : 0; ?>% du CA
                    </div>
                </div>
            </div>
        </div>

        <!-- Métriques secondaires -->
        <div class="row">
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-dollar-sign"></i>
                    <h5>Total Versements</h5>
                    <div class="metric-highlight"><?php echo number_format($versements_total_periode, 0, ',', ' '); ?> FCFA</div>
                    <div class="stats-detail">
                        <i class="fas fa-hand-holding-usd"></i> Encaissements totaux
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-tags"></i>
                    <h5>Total Remises</h5>
                    <div class="metric-highlight"><?php echo number_format($remises_total_periode, 0, ',', ' '); ?> FCFA</div>
                    <div class="stats-detail">
                        <i class="fas fa-percentage"></i> Réductions accordées
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-balance-scale"></i>
                    <h5>Écart Versements</h5>
                    <div class="metric-highlight">
                        <?php if ($ecart_versement_periode < 0): ?>
                            <span style="color: red; animation: blink 1s infinite;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Manque : <?php echo number_format(abs($ecart_versement_periode), 0, ',', ' '); ?> FCFA
                            </span>
                        <?php elseif ($ecart_versement_periode > 0): ?>
                            <span style="color: orange;">
                                <i class="fas fa-info-circle"></i> 
                                Excédent : <?php echo number_format($ecart_versement_periode, 0, ',', ' '); ?> FCFA
                            </span>
                        <?php else: ?>
                            <span style="color: green;">
                                <i class="fas fa-check-circle"></i> 
                                Équilibre parfait
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="stats-detail">
                        <i class="fas fa-balance-scale"></i> Différence CA/Versements
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique d'évolution -->
        <div class="chart-container">
            <h3 class="section-title"><i class="fas fa-chart-line"></i> Évolution du Chiffre d'Affaires</h3>
            <div class="chart-wrapper" id="chartEvolution"></div>
        </div>

        <!-- Tableau détaillé par année -->
        <div class="chart-container">
            <h3 class="section-title"><i class="fas fa-table"></i> Détail par Année</h3>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Ventes Normales</th>
                            <th>Acomptes Crédit</th>
                            <th>SAV</th>
                            <th>CA Total</th>
                            <th>Bénéfice</th>
                            <th>Marge %</th>
                            <th>Versements</th>
                            <th>Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < count($labels); $i++): ?>
                            <tr>
                                <td><strong><?php echo $labels[$i]; ?></strong></td>
                                <td><?php echo number_format($ca_data[$i] - $acomptes_data[$i] - $sav_data[$i], 0, ',', ' '); ?> FCFA</td>
                                <td><?php echo number_format($acomptes_data[$i], 0, ',', ' '); ?> FCFA</td>
                                <td><?php echo number_format($sav_data[$i], 0, ',', ' '); ?> FCFA</td>
                                <td><strong><?php echo number_format($ca_data[$i], 0, ',', ' '); ?> FCFA</strong></td>
                                <td>
                                    <?php if ($benefice_data[$i] < 0): ?>
                                        <span class="badge badge-danger"><?php echo number_format($benefice_data[$i], 0, ',', ' '); ?> FCFA</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?php echo number_format($benefice_data[$i], 0, ',', ' '); ?> FCFA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $marge_annee = $ca_data[$i] > 0 ? (($benefice_data[$i] / $ca_data[$i]) * 100) : 0;
                                    if ($marge_annee < 10): ?>
                                        <span class="badge badge-warning"><?php echo number_format($marge_annee, 1); ?>%</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?php echo number_format($marge_annee, 1); ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($versements_data[$i], 0, ',', ' '); ?> FCFA</td>
                                <td>
                                    <?php if ($ecarts_versement[$i] < 0): ?>
                                        <span style="color: red;">-<?php echo number_format(abs($ecarts_versement[$i]), 0, ',', ' '); ?> FCFA</span>
                                    <?php elseif ($ecarts_versement[$i] > 0): ?>
                                        <span style="color: orange;">+<?php echo number_format($ecarts_versement[$i], 0, ',', ' '); ?> FCFA</span>
                                    <?php else: ?>
                                        <span style="color: green;">0 FCFA</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recommandations -->
        <div class="chart-container">
            <h3 class="section-title"><i class="fas fa-lightbulb"></i> Recommandations</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-chart-line"></i> Performance</h5>
                        <?php if ($evolution_ca > 0): ?>
                            <p><strong>✅ Croissance positive</strong> de <?php echo number_format($evolution_ca, 1); ?>% sur la période</p>
                        <?php elseif ($evolution_ca < 0): ?>
                            <p><strong>⚠️ Décroissance</strong> de <?php echo number_format(abs($evolution_ca), 1); ?>% sur la période</p>
                        <?php else: ?>
                            <p><strong>➖ Stagnation</strong> du chiffre d'affaires</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-balance-scale"></i> Versements</h5>
                        <?php if ($ecart_versement_periode < 0): ?>
                            <p><strong>⚠️ Manque de liquidités</strong> de <?php echo number_format(abs($ecart_versement_periode), 0, ',', ' '); ?> FCFA</p>
                        <?php elseif ($ecart_versement_periode > 0): ?>
                            <p><strong>ℹ️ Excédent de liquidités</strong> de <?php echo number_format($ecart_versement_periode, 0, ',', ' '); ?> FCFA</p>
                        <?php else: ?>
                            <p><strong>✅ Équilibre parfait</strong> entre CA et versements</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <script>
        // Données PHP vers JavaScript
        const labels = <?php echo json_encode($labels); ?>;
        const caData = <?php echo json_encode($ca_data); ?>;
        const beneficeData = <?php echo json_encode($benefice_data); ?>;
        const versementsData = <?php echo json_encode($versements_data); ?>;

        // Configuration du graphique avec Plotly.js
        const data = [
            {
                x: labels,
                y: caData,
                name: 'Chiffre d\'Affaires',
                type: 'bar',
                marker: {
                    color: 'rgba(255, 0, 0, 0.8)',
                    line: {
                        color: 'rgba(255, 0, 0, 1)',
                        width: 2
                    }
                }
            },
            {
                x: labels,
                y: beneficeData,
                name: 'Bénéfice',
                type: 'bar',
                marker: {
                    color: 'rgba(40, 167, 69, 0.8)',
                    line: {
                        color: 'rgba(40, 167, 69, 1)',
                        width: 2
                    }
                }
            },
            {
                x: labels,
                y: versementsData,
                name: 'Versements',
                type: 'line',
                marker: {
                    color: 'rgba(0, 123, 255, 1)',
                    size: 8
                },
                line: {
                    color: 'rgba(0, 123, 255, 1)',
                    width: 3
                }
            }
        ];

        const layout = {
            title: {
                text: 'Évolution du Chiffre d\'Affaires et des Bénéfices',
                font: {
                    size: 24,
                    color: '#333'
                }
            },
            xaxis: {
                title: 'Années',
                titlefont: {
                    size: 16,
                    color: '#333'
                },
                tickfont: {
                    size: 14
                }
            },
            yaxis: {
                title: 'Montant (FCFA)',
                titlefont: {
                    size: 16,
                    color: '#333'
                },
                tickfont: {
                    size: 14
                },
                tickformat: ',.0f'
            },
            barmode: 'group',
            bargap: 0.15,
            bargroupgap: 0.1,
            showlegend: true,
            legend: {
                x: 0.5,
                y: 1.1,
                orientation: 'h',
                font: {
                    size: 14
                }
            },
            margin: {
                l: 80,
                r: 80,
                t: 100,
                b: 80
            },
            paper_bgcolor: 'rgba(255, 255, 255, 0)',
            plot_bgcolor: 'rgba(255, 255, 255, 0.9)',
            font: {
                family: 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif'
            }
        };

        const config = {
            responsive: true,
            displayModeBar: true,
            displaylogo: false,
            modeBarButtonsToRemove: ['pan2d', 'lasso2d', 'select2d'],
            toImageButtonOptions: {
                format: 'png',
                filename: 'analyse_complete_annuelle',
                height: 600,
                width: 1200,
                scale: 2
            }
        };

        // Création du graphique
        Plotly.newPlot('chartEvolution', data, layout, config);

        // Fonction pour redimensionner le graphique
        window.addEventListener('resize', function() {
            Plotly.Plots.resize('chartEvolution');
        });
    </script>
</body>
</html>
