<?php
require_once 'db/connecting.php';

echo "<h2>🔍 VÉRIFICATION DE L'INTÉGRITÉ DES DONNÉES</h2>";

try {
    echo "<h3>📊 État général de la base de données</h3>";
    
    // 1. Vérification des tables principales
    $tables = ['vente', 'ventes_credit_paiement', 'sav_paiement', 'sav_dossier', 'sav_piece', 'versement'];
    
    foreach ($tables as $table) {
        $stmt = $cnx->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Table <strong>$table</strong> : $count enregistrements<br>";
    }
    
    echo "<br><h3>🎯 Analyse des données par année</h3>";
    
    // 2. Récupération de toutes les années
    $sql_annees = "
        SELECT DISTINCT annee FROM (
            SELECT YEAR(DateIns) AS annee FROM vente
            UNION
            SELECT YEAR(DateIns) AS annee FROM ventes_credit_paiement
            UNION
            SELECT YEAR(date_paiement) AS annee FROM sav_paiement
            UNION
            SELECT YEAR(DateIns) AS annee FROM versement
        ) toutes_annees
        ORDER BY annee
    ";
    
    $stmt = $cnx->query($sql_annees);
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Années trouvées : " . implode(', ', $annees) . "<br><br>";
    
    // 3. Analyse détaillée par année
    foreach ($annees as $annee) {
        echo "<h4>📅 Année $annee</h4>";
        
        // Ventes normales
        $stmt = $cnx->prepare("SELECT COUNT(*), COALESCE(SUM(MontantTotal), 0) FROM vente WHERE YEAR(DateIns) = ?");
        $stmt->execute([$annee]);
        $ventes = $stmt->fetch();
        
        // Acomptes crédit
        $stmt = $cnx->prepare("
            SELECT COUNT(*), COALESCE(SUM(vcp.AccompteVerse), 0) 
            FROM ventes_credit_paiement vcp
            JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
            WHERE YEAR(vcp.DateIns) = ? AND vc.Statut != 'Transféré'
        ");
        $stmt->execute([$annee]);
        $acomptes = $stmt->fetch();
        
        // SAV
        $stmt = $cnx->prepare("
            SELECT COUNT(*), COALESCE(SUM(sp.montant), 0) 
            FROM sav_paiement sp
            JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
            WHERE YEAR(sp.date_paiement) = ?
        ");
        $stmt->execute([$annee]);
        $sav = $stmt->fetch();
        
        // Versements
        $stmt = $cnx->prepare("SELECT COUNT(*), COALESCE(SUM(MontantVersement), 0) FROM versement WHERE YEAR(DateIns) = ?");
        $stmt->execute([$annee]);
        $versements = $stmt->fetch();
        
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "<strong>Ventes normales :</strong> {$ventes[0]} transactions, " . number_format($ventes[1], 0, ',', ' ') . " FCFA<br>";
        echo "<strong>Acomptes crédit :</strong> {$acomptes[0]} acomptes, " . number_format($acomptes[1], 0, ',', ' ') . " FCFA<br>";
        echo "<strong>SAV :</strong> {$sav[0]} paiements, " . number_format($sav[1], 0, ',', ' ') . " FCFA<br>";
        echo "<strong>Versements :</strong> {$versements[0]} versements, " . number_format($versements[1], 0, ',', ' ') . " FCFA<br>";
        
        // Détection des anomalies
        $anomalies = [];
        
        if ($ventes[0] == 0 && $acomptes[0] == 0 && $sav[0] == 0) {
            $anomalies[] = "Aucune activité commerciale";
        }
        
        if ($ventes[1] > 0 && $versements[1] == 0) {
            $anomalies[] = "Ventes sans versements enregistrés";
        }
        
        if ($sav[1] > 0) {
            // Vérifier les dossiers SAV
            $stmt = $cnx->prepare("
                SELECT COUNT(*) FROM sav_dossier sd
                JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
                WHERE YEAR(sp.date_paiement) = ?
            ");
            $stmt->execute([$annee]);
            $dossiers_sav = $stmt->fetchColumn();
            
            if ($dossiers_sav == 0) {
                $anomalies[] = "Paiements SAV sans dossiers correspondants";
            }
        }
        
        if (!empty($anomalies)) {
            echo "<div style='background: #fff3cd; padding: 5px; margin-top: 5px; border-radius: 3px;'>";
            echo "<strong>⚠️ Anomalies détectées :</strong><br>";
            foreach ($anomalies as $anomalie) {
                echo "- $anomalie<br>";
            }
            echo "</div>";
        } else {
            echo "<div style='background: #d4edda; padding: 5px; margin-top: 5px; border-radius: 3px;'>";
            echo "✅ Données cohérentes";
            echo "</div>";
        }
        
        echo "</div>";
    }
    
    // 4. Vérification des cohérences
    echo "<h3>🔗 Vérification des cohérences</h3>";
    
    // Vérifier les dossiers SAV sans paiements
    $stmt = $cnx->query("
        SELECT COUNT(*) FROM sav_dossier sd
        LEFT JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
        WHERE sp.id_sav IS NULL
    ");
    $sav_sans_paiement = $stmt->fetchColumn();
    
    if ($sav_sans_paiement > 0) {
        echo "⚠️ <strong>$sav_sans_paiement dossiers SAV sans paiement</strong><br>";
    }
    
    // Vérifier les paiements SAV sans dossiers
    $stmt = $cnx->query("
        SELECT COUNT(*) FROM sav_paiement sp
        LEFT JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE sd.id_sav IS NULL
    ");
    $paiements_sans_dossier = $stmt->fetchColumn();
    
    if ($paiements_sans_dossier > 0) {
        echo "⚠️ <strong>$paiements_sans_dossier paiements SAV sans dossier</strong><br>";
    }
    
    // Vérifier les ventes sans articles
    $stmt = $cnx->query("
        SELECT COUNT(*) FROM vente v
        LEFT JOIN facture_article fa ON v.NumeroVente = fa.NumeroVente
        WHERE fa.NumeroVente IS NULL
    ");
    $ventes_sans_articles = $stmt->fetchColumn();
    
    if ($ventes_sans_articles > 0) {
        echo "⚠️ <strong>$ventes_sans_articles ventes sans articles</strong><br>";
    }
    
    // 5. Recommandations
    echo "<h3>💡 Recommandations</h3>";
    
    if (count($annees) == 0) {
        echo "🚨 <strong>CRITIQUE : Aucune donnée trouvée</strong><br>";
        echo "- Vérifiez que les données sont bien saisies<br>";
        echo "- Contrôlez les dates d'enregistrement<br>";
        echo "- Vérifiez la configuration de la base de données<br>";
    } elseif (count($annees) == 1) {
        echo "⚠️ <strong>Données limitées</strong> - Une seule année de données<br>";
        echo "- Enrichissez la base avec plus de données historiques<br>";
    } else {
        echo "✅ <strong>Données suffisantes</strong> pour l'analyse<br>";
    }
    
    echo "<br><strong>Actions recommandées :</strong><br>";
    echo "1. Vérifiez la saisie des ventes normales<br>";
    echo "2. Contrôlez les coûts SAV<br>";
    echo "3. Assurez la cohérence des données<br>";
    echo "4. Formez les utilisateurs à la saisie correcte<br>";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
}

echo "<br><a href='chiffre_daffaire_annuel.php'>← Retour au rapport annuel</a>";
echo "<br><a href='analyse_complete_annuelle.php'>← Analyse complète</a>";
?> 