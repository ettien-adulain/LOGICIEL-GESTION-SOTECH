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

    // CORRECTION : Créer un tableau avec TOUTES les années de la période
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

    // Récupération des données pour chaque année
    foreach ($labels as $annee) {
        // 1. Chiffre d'affaires (ventes normales + acomptes crédit + SAV)
        $sql_ca = "
            SELECT 
                COALESCE(SUM(v.MontantTotal), 0) AS ventes_normales
            FROM vente v
        WHERE YEAR(v.DateIns) = :annee
    ";

        $stmt = $cnx->prepare($sql_ca);
    $stmt->execute(['annee' => $annee]);
        $ca_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $ventes_normales = $ca_result['ventes_normales'] ?: 0;
        
        // Acomptes crédit séparément
        $sql_acomptes = "
            SELECT COALESCE(SUM(vcp.AccompteVerse), 0) AS acomptes_credit
            FROM ventes_credit_paiement vcp
            JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
            WHERE YEAR(vcp.DateIns) = :annee AND vc.Statut != 'Transféré'
        ";
        $stmt = $cnx->prepare($sql_acomptes);
    $stmt->execute(['annee' => $annee]);
    $acomptes_credit = $stmt->fetchColumn() ?: 0;

        // SAV séparément
        $sql_sav_ca = "
            SELECT COALESCE(SUM(sp.montant), 0) AS montant_sav
            FROM sav_paiement sp
            JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
            WHERE YEAR(sp.date_paiement) = :annee
        ";
        $stmt = $cnx->prepare($sql_sav_ca);
    $stmt->execute(['annee' => $annee]);
        $montant_sav = $stmt->fetchColumn() ?: 0;
        

        
        $ca_total = $ventes_normales + $acomptes_credit + $montant_sav;
        $ca_data[] = $ca_total;
        
        // 2. Nombre de ventes
        $sql_ventes = "SELECT COUNT(*) AS total_ventes FROM vente WHERE YEAR(DateIns) = :annee";
        $stmt = $cnx->prepare($sql_ventes);
    $stmt->execute(['annee' => $annee]);
    $total_ventes = $stmt->fetchColumn() ?: 0;
        $ventes_data[] = $total_ventes;
        
        // 3. Montant SAV (déjà calculé plus haut)
        $sav_data[] = $montant_sav;
        
        // 4. Coût d'achat (PMP)
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
        $benefice_ventes_normales = $ventes_normales - $cout_ventes_normales;

        // 5. Bénéfice sur les acomptes des ventes à crédit (AVEC VÉRIFICATIONS DE SÉCURITÉ)
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
            WHERE YEAR(vcp.DateIns) = :annee 
            AND vc.Statut != 'Transféré'
        ";
        $stmt = $cnx->prepare($sql_cout_acomptes_credit);
        $stmt->execute(['annee' => $annee]);
        $cout_acomptes_credit = $stmt->fetchColumn() ?: 0;
        $benefice_acomptes_credit = $acomptes_credit - $cout_acomptes_credit;

        // 6. Bénéfice sur les paiements SAV (CORRECTION APPLIQUÉE)
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

        // 7. Bénéfice total
        $benefice = $benefice_ventes_normales + $benefice_acomptes_credit + $benefice_sav;
        $benefice_data[] = $benefice;
        
        // 8. Acomptes crédit
        $acomptes_data[] = $acomptes_credit;
        
        // 9. Versements
        $sql_versements = "SELECT COALESCE(SUM(MontantVersement), 0) AS total_versements FROM versement WHERE YEAR(DateIns) = :annee";
        $stmt = $cnx->prepare($sql_versements);
    $stmt->execute(['annee' => $annee]);
        $total_versements = $stmt->fetchColumn() ?: 0;
        $versements_data[] = $total_versements;

        // 10. Remises
        $sql_remises = "SELECT COALESCE(SUM(MontantRemise), 0) AS total_remises FROM vente WHERE YEAR(DateIns) = :annee";
        $stmt = $cnx->prepare($sql_remises);
    $stmt->execute(['annee' => $annee]);
        $total_remises = $stmt->fetchColumn() ?: 0;
        $remises_data[] = $total_remises;
    }

    // Calculs globaux
    $ca_total_periode = array_sum($ca_data);
    $ventes_total_periode = array_sum($ventes_data);
    $sav_total_periode = array_sum($sav_data);
    $benefice_total_periode = array_sum($benefice_data);
    $versements_total_periode = array_sum($versements_data);
    $remises_total_periode = array_sum($remises_data);
    
    // Calcul de l'écart de versement
    $ecart_versement_periode = $versements_total_periode - $ca_total_periode;
    
    // Affichage amélioré de l'écart de versement avec explications
    if ($ecart_versement_periode < 0) {
        $ecart_versement_format = '<span style="color: red; animation: blink 1s infinite;">
            <i class="fas fa-exclamation-triangle"></i> 
            Manque : ' . number_format(abs($ecart_versement_periode), 0, ',', ' ') . ' FCFA
        </span>';
        $ecart_versement_explication = 'Il manque de l\'argent en caisse par rapport aux encaissements attendus.';
        $ecart_versement_classe = 'alert-danger';
    } elseif ($ecart_versement_periode > 0) {
        $ecart_versement_format = '<span style="color: orange;">
            <i class="fas fa-info-circle"></i> 
            Excédent : ' . number_format($ecart_versement_periode, 0, ',', ' ') . ' FCFA
        </span>';
        $ecart_versement_explication = 'Il y a plus d\'argent en caisse que prévu. Vérifiez les sources.';
        $ecart_versement_classe = 'alert-warning';
    } else {
        $ecart_versement_format = '<span style="color: green;">
            <i class="fas fa-check-circle"></i> 
            Équilibre parfait
        </span>';
        $ecart_versement_explication = 'Le versement correspond exactement aux encaissements attendus.';
        $ecart_versement_classe = 'alert-success';
    }
    
    // Moyennes
    $nb_annees = count($labels);
    $ca_moyen = $nb_annees > 0 ? $ca_total_periode / $nb_annees : 0;
    $ventes_moyen = $nb_annees > 0 ? $ventes_total_periode / $nb_annees : 0;
    
    // Évolution en pourcentage - CORRECTION : éviter division par zéro
    $evolution_ca = 0;
    if (count($ca_data) >= 2 && $ca_data[0] > 0) {
        $evolution_ca = (($ca_data[count($ca_data)-1] - $ca_data[0]) / $ca_data[0]) * 100;
    } elseif (count($ca_data) >= 2 && $ca_data[0] == 0 && $ca_data[count($ca_data)-1] > 0) {
        $evolution_ca = 100; // Si on part de 0 et qu'on a des données, c'est une croissance de 100%
    } elseif (count($ca_data) >= 2 && $ca_data[0] == 0 && $ca_data[count($ca_data)-1] == 0) {
        $evolution_ca = 0; // Si on reste à 0, pas d'évolution
    }
    
    // Debug info
    $debug_info = "=== ANALYSE ÉVOLUTION ANNUELLE ===\n";
    $debug_info .= "Période analysée: $annee_debut - $annee_fin\n";
    $debug_info .= "Années analysées: " . implode(', ', $labels) . "\n";
    $debug_info .= "Années avec données: " . implode(', ', $annees_disponibles) . "\n\n";
    
    for ($i = 0; $i < count($labels); $i++) {
        $debug_info .= "ANNÉE {$labels[$i]}:\n";
        $debug_info .= "- CA: " . number_format($ca_data[$i], 0, ',', ' ') . " FCFA\n";
        $debug_info .= "- Ventes: " . number_format($ventes_data[$i], 0, ',', ' ') . "\n";
        $debug_info .= "- SAV (Acomptes): " . number_format($sav_data[$i], 0, ',', ' ') . " FCFA\n";
        $debug_info .= "- Bénéfice: " . number_format($benefice_data[$i], 0, ',', ' ') . " FCFA\n";
        $debug_info .= "- Versements: " . number_format($versements_data[$i], 0, ',', ' ') . " FCFA\n";
        $debug_info .= "- Coût estimatif SAV: " . number_format($cout_estime_sav, 0, ',', ' ') . " FCFA\n";
        $debug_info .= "- Coût matériaux SAV: " . number_format($cout_materiaux_sav, 0, ',', ' ') . " FCFA\n\n";
    }
    
    $debug_info .= "TOTAUX PÉRIODE:\n";
    $debug_info .= "- CA total: " . number_format($ca_total_periode, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Ventes total: " . number_format($ventes_total_periode, 0, ',', ' ') . "\n";
    $debug_info .= "- SAV total: " . number_format($sav_total_periode, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Bénéfice total: " . number_format($benefice_total_periode, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Évolution CA: " . number_format($evolution_ca, 1) . "%\n";

} catch (Throwable $th) {
    // Gestion d'erreur professionnelle - ne pas afficher les détails techniques au client
    error_log('Erreur chiffre_daffaire_annuel.php: ' . $th->getMessage());
    
    // Affichage d'une page d'erreur propre
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur - Chiffre d'Affaires Annuel</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-container {
                background: rgba(255, 255, 255, 0.95);
                padding: 40px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                max-width: 500px;
            }
            .error-icon {
                font-size: 60px;
                color: #dc3545;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <i class="fas fa-exclamation-triangle error-icon"></i>
            <h2>Erreur de chargement</h2>
            <p class="text-muted">Une erreur s'est produite lors du chargement des données.</p>
            <p class="text-muted">Veuillez réessayer ou contacter l'administrateur.</p>
            <a href="javascript:history.back()" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évolution Annuelle - Chiffre d'Affaires</title>
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
        
        .debug-section {
            background: rgba(248, 249, 250, 0.95);
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            border-left: 5px solid #007bff;
        }
        
        .debug-section pre {
            background: white;
            padding: 20px;
            border-radius: 15px;
            font-size: 14px;
            margin: 0;
            overflow-x: auto;
        }
        
        .stats-detail {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
            line-height: 1.6;
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
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1><i class="fas fa-chart-bar"></i> Évolution Annuelle du Chiffre d'Affaires</h1>
        <p>Analyse comparative sur plusieurs années avec graphique 3D</p>
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
                <a href="dashboard_admin_complet.php" class="btn btn-info">
                    <i class="fas fa-chart-line"></i> Analyse Complète et Robuste
                </a>
                <small class="text-muted ml-2">Analyse détaillée de toutes les années avec recommandations</small>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Période analysée :</strong> <?php echo $annee_debut; ?> - <?php echo $annee_fin; ?> 
            <?php if (count($labels) >= 2): ?>
                <span class="evolution-indicator <?php echo $evolution_ca >= 0 ? 'evolution-positive' : 'evolution-negative'; ?>">
                    <i class="fas fa-<?php echo $evolution_ca >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo number_format(abs($evolution_ca), 1); ?>% d'évolution
                </span>
            <?php else: ?>
                <span class="evolution-indicator evolution-neutral">
                    <i class="fas fa-minus"></i>
                    Données insuffisantes
                </span>
            <?php endif; ?>
        </div>

        <!-- Explication de l'écart de versement -->
        <div class="alert <?php echo $ecart_versement_classe; ?>">
            <h5><i class="fas fa-balance-scale"></i> Analyse de l'Écart de Versement</h5>
            <div class="row">
                <div class="col-md-8">
                    <p><strong>Écart :</strong> <?php echo $ecart_versement_format; ?></p>
                    <p class="mb-0"><?php echo $ecart_versement_explication; ?></p>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-light">
                        <h6><i class="fas fa-info-circle"></i> Légende :</h6>
                        <ul class="mb-0" style="font-size: 0.9em;">
                            <li><i class="fas fa-exclamation-triangle text-danger"></i> <strong>Manque</strong> : Vérifiez les encaissements</li>
                            <li><i class="fas fa-info-circle text-warning"></i> <strong>Excédent</strong> : Vérifiez les sources</li>
                            <li><i class="fas fa-check-circle text-success"></i> <strong>Équilibre</strong> : Parfait !</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bouton pour afficher/masquer les détails techniques -->
        <div class="text-center mb-3">
            <button class="btn btn-outline-secondary btn-sm" onclick="toggleDebug()" id="debugToggleBtn">
                <i class="fas fa-bug"></i> Afficher les détails techniques
            </button>
        </div>

        <!-- Debug Section (masquée par défaut) -->
        <div class="debug-section" style="display: none;" id="debugSection">
            <h5><i class="fas fa-bug"></i> Analyse détaillée des données :</h5>
            <pre><?php echo htmlspecialchars($debug_info); ?></pre>
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
                    <i class="info-icon fas fa-trophy"></i>
                    <h5>Performance</h5>
                    <div class="metric-highlight"><?php echo count($labels); ?> années</div>
                    <div class="stats-detail">
                        <i class="fas fa-calendar-alt"></i> Période analysée<br>
                        <i class="fas fa-chart-bar"></i> Données complètes
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique 3D -->
        <div class="chart-container">
            <h3 class="section-title"><i class="fas fa-chart-bar"></i> Évolution Annuelle - Graphique 3D</h3>
            <div class="chart-wrapper" id="chart3d"></div>
        </div>
    </div>

    <script>
        // Données PHP vers JavaScript
        const labels = <?php echo json_encode($labels); ?>;
        const caData = <?php echo json_encode($ca_data); ?>;
        const ventesData = <?php echo json_encode($ventes_data); ?>;
        const savData = <?php echo json_encode($sav_data); ?>;
        const beneficeData = <?php echo json_encode($benefice_data); ?>;
        const acomptesData = <?php echo json_encode($acomptes_data); ?>;
        const versementsData = <?php echo json_encode($versements_data); ?>;
        const remisesData = <?php echo json_encode($remises_data); ?>;

        // Configuration du graphique 3D avec Plotly.js
        const data = [
            {
                x: labels,
                y: caData,
                name: 'Chiffre d\'Affaires Total',
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
                y: savData,
                name: 'SAV (Acomptes)',
                type: 'bar',
                marker: {
                    color: 'rgba(255, 193, 7, 0.8)',
                    line: {
                        color: 'rgba(255, 193, 7, 1)',
                        width: 2
                    }
                }
            },
            {
                x: labels,
                y: beneficeData,
                name: 'Bénéfice Net',
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
                y: acomptesData,
                name: 'Acomptes Ventes Crédit',
                type: 'bar',
                marker: {
                    color: 'rgba(0, 123, 255, 0.8)',
                    line: {
                        color: 'rgba(0, 123, 255, 1)',
                        width: 2
                    }
                }
            }
        ];

        const layout = {
            title: {
                text: 'Évolution du Chiffre d\'Affaires par Année',
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
                filename: 'evolution_annuelle_ca',
                height: 600,
                width: 1200,
                scale: 2
            }
        };

        // Création du graphique
        Plotly.newPlot('chart3d', data, layout, config);

        // Fonction pour redimensionner le graphique
        window.addEventListener('resize', function() {
            Plotly.Plots.resize('chart3d');
        });

        // Tooltips personnalisés
        const chartDiv = document.getElementById('chart3d');
        chartDiv.on('plotly_hover', function(data) {
            const point = data.points[0];
            const value = new Intl.NumberFormat('fr-FR').format(point.y);
            const year = point.x;
            const indicator = point.data.name;
            
            // Personnalisation du tooltip
            const tooltip = document.createElement('div');
            tooltip.innerHTML = `
                <strong>${year}</strong><br>
                ${indicator}: ${value} FCFA
            `;
        });

        // Fonction pour afficher/masquer la section debug
        function toggleDebug() {
            const debugSection = document.getElementById('debugSection');
            const toggleBtn = document.getElementById('debugToggleBtn');
            
            if (debugSection.style.display === 'none') {
                debugSection.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Masquer les détails techniques';
                toggleBtn.classList.remove('btn-outline-secondary');
                toggleBtn.classList.add('btn-secondary');
            } else {
                debugSection.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-bug"></i> Afficher les détails techniques';
                toggleBtn.classList.remove('btn-secondary');
                toggleBtn.classList.add('btn-outline-secondary');
            }
        }
    </script>
</body>
</html>