<?php    
try {
    include('db/connecting.php');
   
    require_once 'fonction_traitement/fonction.php';
    check_access();

    // Récupération de l'année depuis la requête ou par défaut l'année actuelle
    $annee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');

    // Initialisation des montants pour chaque mois
    $montants_mensuels = array_fill(0, 12, 0);

    // CORRECTION : Logique simplifiée et plus claire pour le chiffre d'affaires
    // 1. Ventes normales (comptant + crédit soldé)
    $sql_ventes_normales = "
        SELECT 
            SUM(MontantTotal) AS montant_ventes,
            COUNT(*) AS nombre_ventes
        FROM vente 
        WHERE YEAR(DateIns) = :annee
    ";
    
    // 2. Acomptes des ventes à crédit non soldées
    $sql_acomptes_credit = "
        SELECT 
            SUM(vcp.AccompteVerse) AS montant_acomptes,
            COUNT(*) AS nombre_acomptes
        FROM ventes_credit_paiement vcp
        JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
        WHERE YEAR(vcp.DateIns) = :annee 
        AND vc.Statut != 'Transféré'
    ";
    
    // 3. Ventes à crédit créées cette année (pour information)
    $sql_ventes_credit_annee = "
        SELECT 
            SUM(MontantTotalCredit) AS montant_credit,
            COUNT(*) AS nombre_credit
        FROM ventes_credit 
        WHERE YEAR(DateIns) = :annee
    ";

    // 4. AJOUT : Chiffre d'affaires SAV (paiements SAV)
    $sql_sav_paiements = "
        SELECT 
            SUM(sp.montant) AS montant_sav,
            COUNT(*) AS nombre_paiements_sav
        FROM sav_paiement sp
        JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE YEAR(sp.date_paiement) = :annee
    ";

    // Exécution des requêtes
    $stmt = $cnx->prepare($sql_ventes_normales);
    $stmt->execute(['annee' => $annee]);
    $ventes_normales = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_ventes = $ventes_normales['montant_ventes'] ?: 0;
    $nombre_ventes = $ventes_normales['nombre_ventes'] ?: 0;

    $stmt = $cnx->prepare($sql_acomptes_credit);
    $stmt->execute(['annee' => $annee]);
    $acomptes_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_acomptes = $acomptes_data['montant_acomptes'] ?: 0;
    $nombre_acomptes = $acomptes_data['nombre_acomptes'] ?: 0;

    $stmt = $cnx->prepare($sql_ventes_credit_annee);
    $stmt->execute(['annee' => $annee]);
    $credit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_credit = $credit_data['montant_credit'] ?: 0;
    $nombre_credit = $credit_data['nombre_credit'] ?: 0;

    $stmt = $cnx->prepare($sql_sav_paiements);
    $stmt->execute(['annee' => $annee]);
    $sav_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_sav = $sav_data['montant_sav'] ?: 0;
    $nombre_paiements_sav = $sav_data['nombre_paiements_sav'] ?: 0;

    // Chiffre d'affaires total = Ventes normales + Acomptes crédit + SAV
    $chiffre_affaires_total = $montant_ventes + $montant_acomptes + $montant_sav;
    $total_ventes_complet = $nombre_ventes + $nombre_credit + $nombre_paiements_sav;

    // Initialisation des variables pour le tableau de bord
    $total_versement = 0;
    $total_remise = 0;
    $prixachat = 0;

    // Requêtes pour les totaux annuels
    $sql_total_versement = "SELECT SUM(MontantVersement) AS total_versement FROM versement WHERE YEAR(DateIns) = :annee";
    $sql_total_remise = "SELECT SUM(MontantRemise) AS total_remise FROM vente WHERE YEAR(DateIns) = :annee";

    // CORRECTION : Calcul du coût d'achat avec PMP (Prix Moyen Pondéré)
    // Suppression des frais annexes des articles car ils sont maintenant dans entree_en_stock
    $sql_cout_total = "
        SELECT SUM(a.PrixAchatHT * fa.QuantiteVendue) AS prixachat
        FROM facture_article fa
        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
        JOIN vente v ON fa.NumeroVente = v.NumeroVente
        WHERE YEAR(v.DateIns) = :annee
    ";

    $stmt = $cnx->prepare($sql_total_versement);
    $stmt->execute(['annee' => $annee]);
    $total_versement = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_total_remise);
    $stmt->execute(['annee' => $annee]);
    $total_remise = $stmt->fetchColumn() ?: 0;

    // Récupération du prix d'achat total (PMP)
    $stmt = $cnx->prepare($sql_cout_total);
    $stmt->execute(['annee' => $annee]);
    $prixachat = $stmt->fetchColumn() ?: 0;

    // CALCUL COMPLET DU BÉNÉFICE - VERSION FIABLE ET JUSTE
    // 1. Bénéfice sur les ventes normales
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

    // 2. Bénéfice sur les acomptes des ventes à crédit (AVEC VÉRIFICATIONS DE SÉCURITÉ)
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
    $benefice_acomptes_credit = $montant_acomptes - $cout_acomptes_credit;

    // 3. Bénéfice sur les paiements SAV (CORRECTION APPLIQUÉE)
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
    
    // Calcul de la marge SAV en pourcentage (sur le coût estimatif total)
    $marge_sav_pourcentage = $cout_estime_sav > 0 ? (($benefice_sav / $cout_estime_sav) * 100) : 0;

    // 4. Bénéfice total
    $benefice_total = $benefice_ventes_normales + $benefice_acomptes_credit + $benefice_sav;
    
    // 5. Calcul de la marge brute en pourcentage (sur le CA total)
    $marge_brute_pourcentage = $chiffre_affaires_total > 0 ? ($benefice_total / $chiffre_affaires_total) * 100 : 0;

    // Affichage du bénéfice formaté
    if ($benefice_total < 0) {
        $benefice_format = '<span style="color: red; animation: blink 1s infinite;">-' . number_format(abs($benefice_total), 0, ',', ' ') . ' FCFA</span>';
    } elseif ($benefice_total == 0) {
        $benefice_format = '0 FCFA';
    } else {
        $benefice_format = '<span style="color: green;">' . number_format($benefice_total, 0, ',', ' ') . ' FCFA</span>';
    }

    // Calcul de l'écart
    $total_ecart = $total_versement - $chiffre_affaires_total;

    // Affichage amélioré de l'écart de versement avec explications
    if ($total_ecart < 0) {
        $ecart_format = '<span style="color: red; animation: blink 1s infinite;">
            <i class="fas fa-exclamation-triangle"></i> 
            Manque : ' . number_format(abs($total_ecart), 0, ',', ' ') . ' FCFA
        </span>';
        $ecart_explication = 'Il manque de l\'argent en caisse par rapport aux encaissements attendus.';
        $ecart_classe = 'alert-danger';
    } elseif ($total_ecart > 0) {
        $ecart_format = '<span style="color: orange;">
            <i class="fas fa-info-circle"></i> 
            Excédent : ' . number_format($total_ecart, 0, ',', ' ') . ' FCFA
        </span>';
        $ecart_explication = 'Il y a plus d\'argent en caisse que prévu. Vérifiez les sources.';
        $ecart_classe = 'alert-warning';
    } else {
        $ecart_format = '<span style="color: green;">
            <i class="fas fa-check-circle"></i> 
            Équilibre parfait
        </span>';
        $ecart_explication = 'Le versement correspond exactement aux encaissements attendus.';
        $ecart_classe = 'alert-success';
    }

    // Récupérer les 20 meilleurs articles vendus
    $sql_meilleurs_articles = "
        SELECT a.libelle, COUNT(fa.IDARTICLE) AS total_ventes
        FROM facture_article fa
        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
        JOIN vente v ON fa.NumeroVente = v.NumeroVente
        WHERE YEAR(v.DateIns) = :annee
        GROUP BY a.libelle, a.IDARTICLE
        ORDER BY total_ventes DESC
        LIMIT 20
    ";

    $stmt = $cnx->prepare($sql_meilleurs_articles);
    $stmt->execute(['annee' => $annee]);
    $meilleurs_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // DEBUG : Informations sur les données
    $debug_info = "=== ANALYSE CHIFFRE D'AFFAIRES ANNÉE $annee ===\n\n";
    $debug_info .= "VENTES NORMALES:\n";
    $debug_info .= "- Montant: " . number_format($montant_ventes, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Nombre: $nombre_ventes\n";
    $debug_info .= "- Coût d'achat: " . number_format($cout_ventes_normales, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Bénéfice: " . number_format($benefice_ventes_normales, 0, ',', ' ') . " FCFA\n\n";
    $debug_info .= "ACOMPTES CRÉDIT:\n";
    $debug_info .= "- Montant: " . number_format($montant_acomptes, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Nombre: $nombre_acomptes\n";
    $debug_info .= "- Coût d'achat: " . number_format($cout_acomptes_credit, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Bénéfice: " . number_format($benefice_acomptes_credit, 0, ',', ' ') . " FCFA\n\n";
    $debug_info .= "VENTES CRÉDIT (créées cette année):\n";
    $debug_info .= "- Montant: " . number_format($montant_credit, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Nombre: $nombre_credit\n";
    $debug_info .= "- Marge SAV ($marge_sav_pourcentage%): " . number_format($benefice_sav, 0, ',', ' ') . " FCFA\n\n";
    $debug_info .= "PAIEMENTS SAV (Acomptes):\n";
    $debug_info .= "- Acomptes versés: " . number_format($montant_sav, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Nombre: $nombre_paiements_sav\n";
    $debug_info .= "- Coût estimatif total: " . number_format($cout_estime_sav, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Coût matériaux: " . number_format($cout_materiaux_sav, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Bénéfice SAV ($marge_sav_pourcentage%): " . number_format($benefice_sav, 0, ',', ' ') . " FCFA\n\n";
    $debug_info .= "CHIFFRE D'AFFAIRES TOTAL: " . number_format($chiffre_affaires_total, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "BÉNÉFICE TOTAL: " . number_format($benefice_total, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "MARGE BRUTE: " . number_format($marge_brute_pourcentage, 1) . "%\n\n";

    // Requête SQL pour obtenir les montants des ventes par mois
    $sql_ventes_mensuelles = "
        SELECT MONTH(DateIns) AS mois, SUM(MontantTotal) AS montant, COUNT(*) AS nombre_ventes
        FROM vente
        WHERE YEAR(DateIns) = :annee
        GROUP BY MONTH(DateIns)
        ORDER BY mois
    ";

    $stmt = $cnx->prepare($sql_ventes_mensuelles);
    $stmt->execute(['annee' => $annee]);
    $ventes_mensuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ventes_mensuelles as $row) {
        $montants_mensuels[$row['mois'] - 1] = (float)$row['montant'];
    }

    // AJOUT : Acomptes des ventes à crédit non soldées par mois
    $sql_acomptes_credit_mensuels = "
        SELECT MONTH(vcp.DateIns) AS mois, SUM(vcp.AccompteVerse) AS montant, COUNT(*) AS nombre_ventes
        FROM ventes_credit_paiement vcp
        JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
        WHERE YEAR(vcp.DateIns) = :annee AND vc.Statut != 'Transféré'
        GROUP BY MONTH(vcp.DateIns)
        ORDER BY mois
    ";

    $stmt = $cnx->prepare($sql_acomptes_credit_mensuels);
    $stmt->execute(['annee' => $annee]);
    $acomptes_credit_mensuels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($acomptes_credit_mensuels as $row) {
        $montants_mensuels[$row['mois'] - 1] += (float)$row['montant'];
    }

    // AJOUT : Paiements SAV par mois
    $sql_sav_mensuels = "
        SELECT MONTH(sp.date_paiement) AS mois, SUM(sp.montant) AS montant, COUNT(*) AS nombre_paiements
        FROM sav_paiement sp
        JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE YEAR(sp.date_paiement) = :annee
        GROUP BY MONTH(sp.date_paiement)
        ORDER BY mois
    ";

    $stmt = $cnx->prepare($sql_sav_mensuels);
    $stmt->execute(['annee' => $annee]);
    $sav_mensuels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sav_mensuels as $row) {
        $montants_mensuels[$row['mois'] - 1] += (float)$row['montant'];
    }

    $debug_info .= "DONNÉES MENSUELLES:\n";
    for ($i = 0; $i < 12; $i++) {
        if ($montants_mensuels[$i] > 0) {
            $debug_info .= sprintf("%s - %s FCFA\n", 
                date('F', mktime(0, 0, 0, $i + 1, 1)), 
                number_format($montants_mensuels[$i], 0, ',', ' ')
            );
        }
    }

} catch (Throwable $th) {
    echo 'Erreur serveur : ' . $th->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Chiffre d'Affaires Mensuel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0; }
            100% { opacity: 1; }
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        
        header {
            background-color: #ff0000;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .year-picker {
            text-align: center;
            margin-bottom: 20px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .year-picker input[type="number"] {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            font-size: 16px;
            width: 120px;
            margin-right: 10px;
        }
        
        .year-picker button {
            background-color: #ff0000;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .year-picker button:hover {
            background-color: #e60000;
        }
        
        .info-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin: 10px 0;
            transition: transform 0.3s;
        }
        
        .info-box:hover {
            transform: translateY(-5px);
        }
        
        .info-icon {
            font-size: 30px;
            color: #ff0000;
            margin-bottom: 10px;
        }
        
        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        .debug-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .debug-section pre {
            background: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            margin: 0;
            overflow-x: auto;
        }
        
        .stats-detail {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .articles-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        .articles-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }
        
        .article-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .article-item:last-child {
            border-bottom: none;
        }
        
        .article-name {
            font-weight: bold;
            color: #333;
        }
        
        .article-sales {
            background: #ff0000;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1><i class="fas fa-chart-bar"></i> Tableau de Bord Chiffre d'Affaires Mensuel</h1>
    </header>

    <div class="container">
        <div class="year-picker">
            <form method="GET" action="">
                <label for="annee"><strong>Sélectionnez l'année :</strong></label>
                <input type="number" name="annee" id="annee" value="<?php echo $annee; ?>" min="2000" max="2030" required>
                <button type="submit"><i class="fas fa-search"></i> Afficher</button>
            </form>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Année sélectionnée :</strong> <?php echo $annee; ?>
        </div>

        <!-- Explication de l'écart de versement -->
        <div class="alert <?php echo $ecart_classe; ?>">
            <h5><i class="fas fa-balance-scale"></i> Analyse de l'Écart de Versement</h5>
            <div class="row">
                <div class="col-md-8">
                    <p><strong>Écart :</strong> <?php echo $ecart_format; ?></p>
                    <p class="mb-0"><?php echo $ecart_explication; ?></p>
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
                    <h5>Chiffre d'Affaires</h5>
                    <p><?php echo number_format($chiffre_affaires_total, 0, ',', ' '); ?> FCFA</p>
                    <div class="stats-detail">
                        <i class="fas fa-shopping-cart"></i> Ventes: <?php echo number_format($montant_ventes, 0, ',', ' '); ?> FCFA<br>
                        <i class="fas fa-credit-card"></i> Acomptes: <?php echo number_format($montant_acomptes, 0, ',', ' '); ?> FCFA<br>
                        <i class="fas fa-tools"></i> SAV: <?php echo number_format($montant_sav, 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-shopping-cart"></i>
                    <h5>Total Transactions</h5>
                    <p><?php echo $total_ventes_complet; ?></p>
                    <div class="stats-detail">
                        <i class="fas fa-cash-register"></i> Ventes: <?php echo $nombre_ventes; ?><br>
                        <i class="fas fa-file-invoice"></i> Crédit: <?php echo $nombre_credit; ?><br>
                        <i class="fas fa-wrench"></i> SAV: <?php echo $nombre_paiements_sav; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-dollar-sign"></i>
                    <h5>Total Versements</h5>
                    <p><?php echo number_format($total_versement, 0, ',', ' '); ?> FCFA</p>
                    <div class="stats-detail">
                        <i class="fas fa-hand-holding-usd"></i> Encaissements
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-tags"></i>
                    <h5>Total Remises</h5>
                    <p><?php echo number_format($total_remise, 0, ',', ' '); ?> FCFA</p>
                    <div class="stats-detail">
                        <i class="fas fa-percentage"></i> Réductions accordées
                    </div>
                </div>
            </div>
        </div>

        <!-- Métriques secondaires -->
        <div class="row">
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-exclamation-circle"></i>
                    <h5>Écart Versements</h5>
                    <p><?php echo $ecart_format; ?></p>
                    <div class="stats-detail">
                        <i class="fas fa-balance-scale"></i> Différence CA/Versements
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-chart-line"></i>
                    <h5>Bénéfice Réalisé</h5>
                    <p><?php echo $benefice_format; ?></p>
                    <div class="stats-detail">
                        <i class="fas fa-coins"></i> Coût PMP: <?php echo number_format($prixachat, 0, ',', ' '); ?> FCFA<br>
                        <i class="fas fa-percentage"></i> Marge: <?php echo number_format($marge_brute_pourcentage, 1); ?>%
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-trophy"></i>
                    <h5>Moyenne Mensuelle</h5>
                    <p><?php echo number_format($chiffre_affaires_total / 12, 0, ',', ' '); ?> FCFA</p>
                    <div class="stats-detail">
                        <i class="fas fa-chart-line"></i> CA moyen par mois
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-tools"></i>
                    <h5>Total SAV</h5>
                    <p><?php echo number_format($montant_sav, 0, ',', ' '); ?> FCFA</p>
                    <div class="stats-detail">
                        <i class="fas fa-wrench"></i> <?php echo $nombre_paiements_sav; ?> paiements<br>
                        <i class="fas fa-percentage"></i> <?php echo $chiffre_affaires_total > 0 ? number_format(($montant_sav / $chiffre_affaires_total) * 100, 1) : 0; ?>% du CA
                    </div>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <h3><i class="fas fa-chart-line"></i> Évolution des Ventes par Mois</h3>
            <canvas id="ventesChart"></canvas>
        </div>

        <div class="articles-section">
            <h3><i class="fas fa-star"></i> Meilleurs Articles Vendus</h3>
            <div class="articles-list">
                <?php if (!empty($meilleurs_articles)): ?>
                    <?php foreach ($meilleurs_articles as $article): ?>
                        <div class="article-item">
                            <span class="article-name"><?php echo htmlspecialchars($article['libelle']); ?></span>
                            <span class="article-sales"><?php echo $article['total_ventes']; ?> ventes</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted">Aucun article vendu pour cette année</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var ctx = document.getElementById('ventesChart').getContext('2d');
        var ventesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Montant des Ventes (FCFA)',
                    data: <?php echo json_encode($montants_mensuels); ?>,
                    backgroundColor: 'rgba(255, 0, 0, 0.1)',
                    borderColor: 'rgba(255, 0, 0, 1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(255, 0, 0, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Montant (FCFA)'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Mois'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                return tooltipItem.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(tooltipItem.raw) + ' FCFA';
                            }
                        }
                    }
                }
            }
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

