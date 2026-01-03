<?php
require_once 'db/connecting.php';

echo "<h2>🔍 Vérification des données SAV</h2>";

$date_test = '2025-07-06'; // Date du rapport

try {
    // 1. Récupérer tous les dossiers SAV avec paiements pour cette date
    $sql_sav_complet = "
        SELECT 
            sd.id_sav,
            sd.numero_sav,
            sd.cout_estime,
            sd.date_creation,
            sp.montant as montant_paiement,
            sp.date_paiement,
            COUNT(spiece.id_piece) as nombre_materiaux,
            SUM(spiece.cout_total) as cout_total_materiaux
        FROM sav_dossier sd
        JOIN sav_paiement sp ON sd.id_sav = sp.id_sav
        LEFT JOIN sav_piece spiece ON sd.id_sav = spiece.id_sav
        WHERE DATE(sp.date_paiement) = :date
        GROUP BY sd.id_sav, sd.numero_sav, sd.cout_estime, sd.date_creation, sp.montant, sp.date_paiement
        ORDER BY sp.date_paiement
    ";
    
    $stmt = $cnx->prepare($sql_sav_complet);
    $stmt->execute(['date' => $date_test]);
    $dossiers_sav = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Dossiers SAV du $date_test :</h3>";
    
    if (empty($dossiers_sav)) {
        echo "❌ Aucun dossier SAV trouvé pour cette date<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>N° SAV</th><th>Coût Estimé</th><th>Paiement</th><th>Matériaux</th><th>Coût Matériaux</th><th>Bénéfice</th><th>Marge</th>";
        echo "</tr>";
        
        foreach ($dossiers_sav as $dossier) {
            $benefice = $dossier['montant_paiement'] - $dossier['cout_total_materiaux'];
            $marge = $dossier['montant_paiement'] > 0 ? (($benefice / $dossier['montant_paiement']) * 100) : 0;
            
            $couleur_benefice = $benefice < 0 ? 'red' : 'green';
            $couleur_marge = $marge < 0 ? 'red' : 'green';
            
            echo "<tr>";
            echo "<td>" . $dossier['numero_sav'] . "</td>";
            echo "<td>" . number_format($dossier['cout_estime'], 0, ',', ' ') . " FCFA</td>";
            echo "<td>" . number_format($dossier['montant_paiement'], 0, ',', ' ') . " FCFA</td>";
            echo "<td>" . $dossier['nombre_materiaux'] . "</td>";
            echo "<td>" . number_format($dossier['cout_total_materiaux'], 0, ',', ' ') . " FCFA</td>";
            echo "<td style='color: $couleur_benefice;'>" . number_format($benefice, 0, ',', ' ') . " FCFA</td>";
            echo "<td style='color: $couleur_marge;'>" . number_format($marge, 1) . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Détail des matériaux pour chaque dossier
    echo "<h3>🔧 Détail des matériaux :</h3>";
    
    foreach ($dossiers_sav as $dossier) {
        echo "<h4>Dossier " . $dossier['numero_sav'] . " :</h4>";
        
        $sql_materiaux = "
            SELECT designation, cout_unitaire, quantite, cout_total, date_achat
            FROM sav_piece 
            WHERE id_sav = :id_sav
            ORDER BY date_achat
        ";
        
        $stmt = $cnx->prepare($sql_materiaux);
        $stmt->execute(['id_sav' => $dossier['id_sav']]);
        $materiaux = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($materiaux)) {
            echo "⚠️ Aucun matériau saisi pour ce dossier<br>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; width: 80%;'>";
            echo "<tr style='background: #f0f0f0;'>";
            echo "<th>Désignation</th><th>Coût unitaire</th><th>Quantité</th><th>Total</th><th>Date achat</th>";
            echo "</tr>";
            
            foreach ($materiaux as $materiau) {
                echo "<tr>";
                echo "<td>" . $materiau['designation'] . "</td>";
                echo "<td>" . number_format($materiau['cout_unitaire'], 0, ',', ' ') . " FCFA</td>";
                echo "<td>" . $materiau['quantite'] . "</td>";
                echo "<td>" . number_format($materiau['cout_total'], 0, ',', ' ') . " FCFA</td>";
                echo "<td>" . $materiau['date_achat'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        echo "<br>";
    }
    
    // 3. Analyse du problème
    echo "<h3>🎯 Analyse du problème :</h3>";
    
    $total_paiements = array_sum(array_column($dossiers_sav, 'montant_paiement'));
    $total_couts = array_sum(array_column($dossiers_sav, 'cout_total_materiaux'));
    $total_benefice = $total_paiements - $total_couts;
    $marge_globale = $total_paiements > 0 ? (($total_benefice / $total_paiements) * 100) : 0;
    
    echo "💰 Total paiements : " . number_format($total_paiements, 0, ',', ' ') . " FCFA<br>";
    echo "🔧 Total coûts matériaux : " . number_format($total_couts, 0, ',', ' ') . " FCFA<br>";
    echo "💵 Bénéfice total : " . number_format($total_benefice, 0, ',', ' ') . " FCFA<br>";
    echo "📈 Marge globale : " . number_format($marge_globale, 1) . "%<br><br>";
    
    if ($total_benefice < 0) {
        echo "⚠️ <strong>PROBLÈME IDENTIFIÉ :</strong> Les coûts des matériaux dépassent les revenus<br>";
        echo "🔍 <strong>CAUSES POSSIBLES :</strong><br>";
        echo "- Coûts des matériaux surestimés<br>";
        echo "- Coût estimatif sous-évalué<br>";
        echo "- Erreur de saisie des matériaux<br>";
        echo "- Main d'œuvre non incluse dans le coût estimatif<br><br>";
        
        echo "💡 <strong>SOLUTIONS :</strong><br>";
        echo "1. Vérifier les coûts des matériaux saisis<br>";
        echo "2. Ajuster le coût estimatif pour inclure la marge<br>";
        echo "3. Saisir 'Main d'œuvre' comme matériau si nécessaire<br>";
        echo "4. Revoir la stratégie de tarification SAV<br>";
    } else {
        echo "✅ Les données semblent cohérentes<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
}

echo "<br><a href='chiffre_daffaire_horaire.php'>← Retour au rapport horaire</a>";
?> 