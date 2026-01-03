<?php 
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();
    // Récupération de la date depuis la requête ou par défaut la date actuelle
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    // Initialisation des variables
    $chiffre_affaires = 0;
    $total_ventes = 0;
    $total_versement = 0;
    $total_remise = 0;
    $prixachat = 0;

    // CORRECTION : Logique simplifiée et plus claire pour le chiffre d'affaires
    // 1. Ventes normales (comptant + crédit soldé)
    $sql_ventes_normales = "
        SELECT 
            SUM(MontantTotal) AS montant_ventes,
            COUNT(*) AS nombre_ventes
        FROM vente 
        WHERE DATE(DateIns) = :date
    ";
    
    // 2. Acomptes des ventes à crédit non soldées
    $sql_acomptes_credit = "
        SELECT 
            SUM(vcp.AccompteVerse) AS montant_acomptes,
            COUNT(*) AS nombre_acomptes
        FROM ventes_credit_paiement vcp
        JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
        WHERE DATE(vcp.DateIns) = :date 
        AND vc.Statut != 'Transféré'
    ";
    
    // 3. Ventes à crédit créées ce jour (pour information)
    $sql_ventes_credit_jour = "
        SELECT 
            SUM(MontantTotalCredit) AS montant_credit,
            COUNT(*) AS nombre_credit
        FROM ventes_credit 
        WHERE DATE(DateIns) = :date 
    ";

    // 4. AJOUT : Chiffre d'affaires SAV (paiements SAV)
    $sql_sav_paiements = "
        SELECT 
            SUM(sp.montant) AS montant_sav,
            COUNT(*) AS nombre_paiements_sav
        FROM sav_paiement sp
        JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE DATE(sp.date_paiement) = :date
    ";

    // Exécution des requêtes
    $stmt = $cnx->prepare($sql_ventes_normales);
    $stmt->execute(['date' => $date]);
    $ventes_normales = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_ventes = $ventes_normales['montant_ventes'] ?: 0;
    $nombre_ventes = $ventes_normales['nombre_ventes'] ?: 0;

    $stmt = $cnx->prepare($sql_acomptes_credit);
    $stmt->execute(['date' => $date]);
    $acomptes_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_acomptes = $acomptes_data['montant_acomptes'] ?: 0;
    $nombre_acomptes = $acomptes_data['nombre_acomptes'] ?: 0;

    $stmt = $cnx->prepare($sql_ventes_credit_jour);
    $stmt->execute(['date' => $date]);
    $credit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_credit = $credit_data['montant_credit'] ?: 0;
    $nombre_credit = $credit_data['nombre_credit'] ?: 0;

    $stmt = $cnx->prepare($sql_sav_paiements);
    $stmt->execute(['date' => $date]);
    $sav_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_sav = $sav_data['montant_sav'] ?: 0;
    $nombre_paiements_sav = $sav_data['nombre_paiements_sav'] ?: 0;

    // Chiffre d'affaires total = Ventes normales + Acomptes crédit + SAV
    $chiffre_affaires_total = $montant_ventes + $montant_acomptes + $montant_sav;
    $total_ventes_complet = $nombre_ventes + $nombre_credit + $nombre_paiements_sav;

    // CORRECTION : Calcul du coût d'achat avec PMP (Prix Moyen Pondéré)
    // Suppression des frais annexes des articles car ils sont maintenant dans entree_en_stock
    $sql_cout_total = "
        SELECT SUM(a.PrixAchatHT * fa.QuantiteVendue) AS prixachat
    FROM facture_article fa
    JOIN article a ON fa.IDARTICLE = a.IDARTICLE
    JOIN vente v ON fa.NumeroVente = v.NumeroVente
    WHERE DATE(v.DateIns) = :date
    ";

    $stmt = $cnx->prepare($sql_cout_total);
    $stmt->execute(['date' => $date]);
    $prixachat = $stmt->fetchColumn() ?: 0;

    // Autres métriques
    $sql_total_versement = "SELECT SUM(MontantVersement) AS total_versement FROM versement WHERE DATE(DateIns) = :date";
    $sql_total_remise = "SELECT SUM(MontantRemise) AS total_remise FROM vente WHERE DATE(DateIns) = :date";

    $stmt = $cnx->prepare($sql_total_versement);
    $stmt->execute(['date' => $date]);
    $total_versement = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_total_remise);
    $stmt->execute(['date' => $date]);
    $total_remise = $stmt->fetchColumn() ?: 0;

    // CALCUL COMPLET DU BÉNÉFICE - VERSION FIABLE ET JUSTE
    // 1. Bénéfice sur les ventes normales
    $sql_cout_ventes_normales = "
        SELECT COALESCE(SUM(a.PrixAchatHT * fa.QuantiteVendue), 0) AS cout_ventes
        FROM facture_article fa
        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
        JOIN vente v ON fa.NumeroVente = v.NumeroVente
        WHERE DATE(v.DateIns) = :date
    ";
    $stmt = $cnx->prepare($sql_cout_ventes_normales);
    $stmt->execute(['date' => $date]);
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
        WHERE DATE(vcp.DateIns) = :date 
        AND vc.Statut != 'Transféré'
    ";
    $stmt = $cnx->prepare($sql_cout_acomptes_credit);
    $stmt->execute(['date' => $date]);
    $cout_acomptes_credit = $stmt->fetchColumn() ?: 0;
    $benefice_acomptes_credit = $montant_acomptes - $cout_acomptes_credit;

    // 3. Bénéfice sur les paiements SAV (CORRECTION APPLIQUÉE)
    // Récupérer le coût estimatif total des dossiers SAV payés ce jour
    $sql_cout_estime_sav = "
        SELECT COALESCE(SUM(sd.cout_estime), 0) AS cout_estime_total
        FROM sav_dossier sd
        JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
        WHERE DATE(sp.date_paiement) = :date
    ";
    $stmt = $cnx->prepare($sql_cout_estime_sav);
    $stmt->execute(['date' => $date]);
    $cout_estime_sav = $stmt->fetchColumn() ?: 0;
    
    // Récupérer le coût réel des matériaux SAV
    $sql_cout_materiaux_sav = "
        SELECT COALESCE(SUM(spiece.cout_total), 0) AS cout_materiaux
        FROM sav_piece spiece
        JOIN sav_dossier sd ON spiece.id_sav = sd.id_sav
        JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
        WHERE DATE(sp.date_paiement) = :date
    ";
    $stmt = $cnx->prepare($sql_cout_materiaux_sav);
    $stmt->execute(['date' => $date]);
    $cout_materiaux_sav = $stmt->fetchColumn() ?: 0;
    
    // Bénéfice SAV = Coût estimatif - Coût matériaux (CORRIGÉ)
    $benefice_sav = $cout_estime_sav - $cout_materiaux_sav;

    // Calcul de la marge SAV en pourcentage (sur le coût estimatif total)
    $marge_sav_pourcentage = $cout_estime_sav > 0 ? (($benefice_sav / $cout_estime_sav) * 100) : 0;

    // 4. Bénéfice total
    $benefice_total = $benefice_ventes_normales + $benefice_acomptes_credit + $benefice_sav;

    // 5. Calcul de la marge brute en pourcentage (sur le CA total)
    $marge_brute_pourcentage = $chiffre_affaires_total > 0 ? ($benefice_total / $chiffre_affaires_total) * 100 : 0;

    // Affichage du bénéfice total
    if ($benefice_total < 0) {
        $benefice_format = '<span style="color: red; animation: blink 1s infinite;">-' . number_format(abs($benefice_total), 0, ',', ' ') . ' FCFA</span>';
    } elseif ($benefice_total == 0) {
        $benefice_format = '0 FCFA';
    } else {
        $benefice_format = '<span style="color: green;">' . number_format($benefice_total, 0, ',', ' ') . ' FCFA</span>';
    }

    // Calcul de l'écart entre les versements et le chiffre d'affaires
    $ecart_versement = $total_versement - $chiffre_affaires_total;

    // Affichage amélioré de l'écart de versement avec explications
    if ($ecart_versement < 0) {
        $ecart_versement_format = '<span style="color: red; animation: blink 1s infinite;">
            <i class="fas fa-exclamation-triangle"></i> 
            Manque : ' . number_format(abs($ecart_versement), 0, ',', ' ') . ' FCFA
        </span>';
        $ecart_versement_explication = 'Il manque de l\'argent en caisse par rapport aux encaissements attendus.';
        $ecart_versement_classe = 'alert-danger';
    } elseif ($ecart_versement > 0) {
        $ecart_versement_format = '<span style="color: orange;">
            <i class="fas fa-info-circle"></i> 
            Excédent : ' . number_format($ecart_versement, 0, ',', ' ') . ' FCFA
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

    // Récupération du meilleur article vendu
    $sql_meilleur_article = "
        SELECT a.libelle, COUNT(fa.IDARTICLE) AS total_ventes
        FROM facture_article fa
        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
        JOIN vente v ON fa.NumeroVente = v.NumeroVente
        WHERE DATE(v.DateIns) = :date
        GROUP BY a.libelle, a.IDARTICLE
        ORDER BY total_ventes DESC
        LIMIT 1
    ";
    $stmt = $cnx->prepare($sql_meilleur_article);
    $stmt->execute(['date' => $date]);
    $meilleur_article = $stmt->fetch(PDO::FETCH_ASSOC);
    $meilleur_article_nom = $meilleur_article ? $meilleur_article['libelle'] : 'Aucun';

    // CORRECTION : Récupération des ventes par heure (ventes normales + acomptes crédit + SAV)
    $sql_ventes_horaires = "
        SELECT 
            HOUR(DateIns) AS heure, 
            SUM(MontantTotal) AS montant,
            COUNT(*) AS nombre_ventes
        FROM vente
        WHERE DATE(DateIns) = :date
        GROUP BY HOUR(DateIns)
        ORDER BY heure
    ";
    
    $sql_acomptes_horaires = "
        SELECT 
            HOUR(vcp.DateIns) AS heure, 
            SUM(vcp.AccompteVerse) AS montant,
            COUNT(*) AS nombre_acomptes
        FROM ventes_credit_paiement vcp
        JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
        WHERE DATE(vcp.DateIns) = :date 
        AND vc.Statut != 'Transféré'
        GROUP BY HOUR(vcp.DateIns)
        ORDER BY heure
    ";

    $sql_sav_horaires = "
        SELECT 
            HOUR(sp.date_paiement) AS heure, 
            SUM(sp.montant) AS montant,
            COUNT(*) AS nombre_paiements
        FROM sav_paiement sp
        JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE DATE(sp.date_paiement) = :date
        GROUP BY HOUR(sp.date_paiement)
        ORDER BY heure
    ";

    $stmt = $cnx->prepare($sql_ventes_horaires);
    $stmt->execute(['date' => $date]);
    $ventes_horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $cnx->prepare($sql_acomptes_horaires);
    $stmt->execute(['date' => $date]);
    $acomptes_horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $cnx->prepare($sql_sav_horaires);
    $stmt->execute(['date' => $date]);
    $sav_horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialisation des montants horaires à 0 pour chaque heure
    $montants = array_fill(0, 24, 0);
    $nombre_ventes_par_heure = array_fill(0, 24, 0);
    
    // Remplissage des données de ventes normales
    foreach ($ventes_horaires as $row) {
        $heure = (int)$row['heure'];
        $montants[$heure] += (float)$row['montant'];
        $nombre_ventes_par_heure[$heure] += (int)$row['nombre_ventes'];
    }
    
    // Ajout des acomptes crédit
    foreach ($acomptes_horaires as $row) {
        $heure = (int)$row['heure'];
        $montants[$heure] += (float)$row['montant'];
        $nombre_ventes_par_heure[$heure] += (int)$row['nombre_acomptes'];
    }

    // Ajout des paiements SAV
    foreach ($sav_horaires as $row) {
        $heure = (int)$row['heure'];
        $montants[$heure] += (float)$row['montant'];
        $nombre_ventes_par_heure[$heure] += (int)$row['nombre_paiements'];
    }

    // Récupération des informations des opérateurs pour la journée
 /*   $sql_operateur_ventes = "
        SELECT 
            v.IDFactureVente, 
            v.MontantVerse AS MontantEncaisse, 
            v.MontantTotal AS MontantTotalFacture,
            mr.ModeReglement AS ModePaiement,
            u.NomPrenom AS NomOperateur,
            v.DateIns
        FROM vente v
        LEFT JOIN mode_reglement mr ON v.ModePaiement = mr.IDMODE_REGLEMENT
        LEFT JOIN utilisateur u ON v.IDUTILISATEUR = u.IDUTILISATEUR
        WHERE DATE(v.DateIns) = :dateVente
        ORDER BY v.DateIns DESC
        LIMIT 10
    ";
    $stmt = $cnx->prepare($sql_operateur_ventes);
    $stmt->execute(['dateVente' => $date]);
    $operateurData = "";

    if ($stmt->rowCount() > 0) {
        $operateurData = "<div class='table-responsive'><table class='table table-striped table-sm'>";
        $operateurData .= "<thead class='thead-dark'><tr>";
        $operateurData .= "<th>Heure</th><th>Facture</th><th>Total</th><th>Paiement</th><th>Opérateur</th>";
        $operateurData .= "</tr></thead><tbody>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $heure = date('H:i', strtotime($row['DateIns']));
            $idFacture = $row['IDFactureVente'];
            $montantTotalFacture = number_format($row['MontantTotalFacture'], 0, ',', ' ') . " FCFA";
            $modePaiement = $row['ModePaiement'] ?: 'Non défini';
            $nomOperateur = $row['NomOperateur'] ?: 'Non défini';
            
            $operateurData .= "<tr>";
            $operateurData .= "<td><strong>$heure</strong></td>";
            $operateurData .= "<td>$idFacture</td>";
            $operateurData .= "<td>$montantTotalFacture</td>";
            $operateurData .= "<td>$modePaiement</td>";
            $operateurData .= "<td>$nomOperateur</td>";
            $operateurData .= "</tr>";
        }
        $operateurData .= "</tbody></table></div>";
    } else {
        $operateurData = "<div class='alert alert-warning'>Aucune vente enregistrée pour cette date</div>";
    }*/

    // Informations de debug pour diagnostic
    $debug_info = "=== ANALYSE CHIFFRE D'AFFAIRES ===\n";
    $debug_info .= "Date analysée: " . date('d/m/Y', strtotime($date)) . "\n\n";
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
    $debug_info .= "VENTES CRÉDIT (créées ce jour):\n";
    $debug_info .= "- Montant: " . number_format($montant_credit, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Nombre: $nombre_credit\n\n";
    $debug_info .= "PAIEMENTS SAV (Acomptes):\n";
    $debug_info .= "- Acomptes versés: " . number_format($montant_sav, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Nombre: $nombre_paiements_sav\n";
    $debug_info .= "- Coût estimatif total: " . number_format($cout_estime_sav, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Coût matériaux: " . number_format($cout_materiaux_sav, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Bénéfice SAV ($marge_sav_pourcentage%): " . number_format($benefice_sav, 0, ',', ' ') . " FCFA\n\n";
    $debug_info .= "CHIFFRE D'AFFAIRES TOTAL: " . number_format($chiffre_affaires_total, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "BÉNÉFICE TOTAL: " . number_format($benefice_total, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "MARGE BRUTE: " . number_format($marge_brute_pourcentage, 1) . "%\n\n";
    
    $debug_info .= "DONNÉES HORAIRES:\n";
    for ($i = 0; $i < 24; $i++) {
        if ($montants[$i] > 0) {
            $debug_info .= sprintf("%02d:00 - %s FCFA (%d transactions)\n", 
                $i, 
                number_format($montants[$i], 0, ',', ' '), 
                $nombre_ventes_par_heure[$i]
            );
        }
    }

} catch (Throwable $th) {
    echo 'Erreur serveur : ' . $th->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Chiffre d'Affaires Horaire</title>
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
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, #ff0000, #cc0000);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .date-picker {
            text-align: center;
            margin-bottom: 30px;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .date-picker input[type="date"] {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            width: 220px;
            margin-right: 15px;
            transition: border-color 0.3s;
        }
        
        .date-picker input[type="date"]:focus {
            border-color: #ff0000;
            outline: none;
        }
        
        .date-picker button {
            background: linear-gradient(135deg, #ff0000, #cc0000);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .date-picker button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        
        .info-box {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin: 15px 0;
            transition: all 0.3s;
            border-left: 4px solid #ff0000;
        }
        
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .info-icon {
            font-size: 35px;
            color: #ff0000;
            margin-bottom: 15px;
        }
        
        .info-box h5 {
            color: #333;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .info-box p {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
            color: #ff0000;
        }
        
        .chart-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
        
        .chart-container h3 {
            color: #333;
            margin-bottom: 25px;
            text-align: center;
        }
        
        /* CORRECTION : Fixer la hauteur du graphique */
        .chart-container canvas {
            max-height: 400px !important;
            height: 400px !important;
        }
        
        .debug-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }
        
        .debug-section h5 {
            color: #007bff;
            margin-bottom: 15px;
        }
        
        .debug-section pre {
            background: white;
            padding: 15px;
            border-radius: 8px;
            font-size: 12px;
            margin: 0;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        
        .stats-detail {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            line-height: 1.4;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .table th {
            background-color: #343a40;
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .badge-success {
            background-color: #28a745;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-danger {
            background-color: #dc3545;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1><i class="fas fa-chart-line"></i> Tableau de Bord Chiffre d'Affaires Horaire</h1>
        <p class="mb-0">Analyse détaillée des performances commerciales</p>
    </header>

    <div class="container">
        <div class="date-picker">
            <form method="GET" action="">
                <label for="date"><strong>Sélectionnez une date :</strong></label>
                <input type="date" id="date" name="date" value="<?php echo $date; ?>" required>
                <button type="submit"><i class="fas fa-search"></i> Analyser</button>
            </form>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Date analysée :</strong> <?php echo date('d/m/Y', strtotime($date)); ?>
            <span class="badge badge-primary ml-2"><?php echo date('l', strtotime($date)); ?></span>
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
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-exclamation-circle"></i>
                    <h5>Écart Versements</h5>
                    <p><?php echo $ecart_versement_format; ?></p>
                    <div class="stats-detail">
                        <i class="fas fa-balance-scale"></i> Différence CA/Versements
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-star"></i>
                    <h5>Meilleur Article</h5>
                    <p><?php echo htmlspecialchars($meilleur_article_nom); ?></p>
                    <div class="stats-detail">
                        <i class="fas fa-trophy"></i> Plus vendu du jour
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
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
        </div>

        <!-- Graphique des ventes horaires -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-line"></i> Évolution des Ventes par Heure</h3>
            <canvas id="ventesHoraires" width="400" height="200"></canvas>
        </div>

        <!-- Dernières transactions -->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('ventesHoraires').getContext('2d');
        
        const ventesHoraires = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                datasets: [{
                    label: 'Montant des ventes (FCFA)',
                    data: <?php echo json_encode($montants); ?>,
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
                maintainAspectRatio: true,
                aspectRatio: 2,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Montant (FCFA)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            stepSize: 10000,
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Heures de la journée',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
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
                    },
                    legend: {
                        display: true,
                        position: 'top'
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
