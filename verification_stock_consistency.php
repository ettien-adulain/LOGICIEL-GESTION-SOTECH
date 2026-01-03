<?php
session_start();
include('db/connecting.php');

require_once 'fonction_traitement/fonction.php';
check_access();

try {
    echo "<div style='font-family: Arial, sans-serif; max-width: 1400px; margin: 50px auto; padding: 20px;'>";
    echo "<h2>🔍 Vérification de la Cohérence Stock vs Numéros de Série</h2>";
    echo "<p><strong>Date de vérification :</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    // 1. Vérifier les articles avec leurs stocks et numéros de série
    $stmt = $cnx->prepare("
        SELECT 
            a.IDARTICLE,
            a.libelle,
            a.CodePersoArticle,
            COALESCE(s.StockActuel, 0) as stock_actuel,
            COUNT(ns.IDNUM_SERIE) as total_series,
            SUM(CASE WHEN ns.statut = 'disponible' THEN 1 ELSE 0 END) as series_disponibles,
            SUM(CASE WHEN ns.statut = 'vendue' THEN 1 ELSE 0 END) as series_vendues,
            SUM(CASE WHEN ns.statut = 'vendue_credit' THEN 1 ELSE 0 END) as series_vendues_credit,
            SUM(CASE WHEN ns.statut = 'introuvable' THEN 1 ELSE 0 END) as series_introuvables
        FROM article a
        LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE
        LEFT JOIN num_serie ns ON a.IDARTICLE = ns.IDARTICLE
        WHERE a.desactiver != 'oui'
        GROUP BY a.IDARTICLE, a.libelle, a.CodePersoArticle, s.StockActuel
        ORDER BY a.libelle
    ");
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='width: 100%; border-collapse: collapse; margin-top: 20px;'>";
    echo "<thead>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Article</th>";
    echo "<th>Code</th>";
    echo "<th>Stock Actuel</th>";
    echo "<th>Total Série</th>";
    echo "<th>Disponibles</th>";
    echo "<th>Vendues</th>";
    echo "<th>Vendues Crédit</th>";
    echo "<th>Introuvables</th>";
    echo "<th>Incohérence</th>";
    echo "<th>Action</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $incoherences = [];
    $total_incoherences = 0;
    
    foreach ($articles as $article) {
        $stock_actuel = $article['stock_actuel'];
        $series_disponibles = $article['series_disponibles'];
        $incoherence = false;
        $message_incoherence = '';
        
        // Vérifier la cohérence : Stock actuel doit être égal au nombre de séries disponibles
        if ($stock_actuel != $series_disponibles) {
            $incoherence = true;
            $total_incoherences++;
            $message_incoherence = "Stock: $stock_actuel ≠ Série dispo: $series_disponibles";
            $incoherences[] = $article;
        }
        
        $row_color = $incoherence ? 'background-color: #ffebee;' : '';
        $incoherence_text = $incoherence ? "<span style='color: red; font-weight: bold;'>❌ $message_incoherence</span>" : "<span style='color: green;'>✅ OK</span>";
        
        echo "<tr style='$row_color'>";
        echo "<td>" . htmlspecialchars($article['libelle']) . "</td>";
        echo "<td>" . htmlspecialchars($article['CodePersoArticle']) . "</td>";
        echo "<td style='text-align: center; font-weight: bold;'>" . $stock_actuel . "</td>";
        echo "<td style='text-align: center;'>" . $article['total_series'] . "</td>";
        echo "<td style='text-align: center; color: green; font-weight: bold;'>" . $series_disponibles . "</td>";
        echo "<td style='text-align: center; color: blue;'>" . $article['series_vendues'] . "</td>";
        echo "<td style='text-align: center; color: orange;'>" . $article['series_vendues_credit'] . "</td>";
        echo "<td style='text-align: center; color: red;'>" . $article['series_introuvables'] . "</td>";
        echo "<td style='text-align: center;'>" . $incoherence_text . "</td>";
        echo "<td style='text-align: center;'>";
        if ($incoherence) {
            echo "<button onclick='corrigerStock(" . $article['IDARTICLE'] . ", " . $series_disponibles . ")' style='background: #4CAF50; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;'>Corriger</button>";
        }
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    
    // Résumé des incohérences
    echo "<div style='margin-top: 30px; padding: 20px; background-color: #f5f5f5; border-radius: 10px;'>";
    echo "<h3>📊 Résumé de la Vérification</h3>";
    echo "<p><strong>Total d'articles vérifiés :</strong> " . count($articles) . "</p>";
    echo "<p><strong>Incohérences détectées :</strong> <span style='color: " . ($total_incoherences > 0 ? 'red' : 'green') . "; font-weight: bold;'>" . $total_incoherences . "</span></p>";
    
    if ($total_incoherences > 0) {
        echo "<div style='background-color: #ffebee; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
        echo "<h4 style='color: red; margin-top: 0;'>⚠️ Articles avec des incohérences :</h4>";
        echo "<ul>";
        foreach ($incoherences as $incoherence) {
            echo "<li><strong>" . htmlspecialchars($incoherence['libelle']) . "</strong> - Stock: " . $incoherence['stock_actuel'] . " ≠ Série dispo: " . $incoherence['series_disponibles'] . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
        echo "<h4 style='color: green; margin-top: 0;'>✅ Tous les stocks sont cohérents !</h4>";
        echo "<p>Le stock actuel correspond parfaitement au nombre de numéros de série disponibles pour tous les articles.</p>";
        echo "</div>";
    }
    echo "</div>";
    
    // Boutons d'action
    echo "<div style='margin-top: 30px; text-align: center;'>";
    echo "<button onclick='window.location.reload()' style='background: #2196F3; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px;'>🔄 Actualiser</button>";
    echo "<button onclick='window.location.href=\"listes_vente.php\"' style='background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px;'>📋 Retour aux Ventes</button>";
    echo "<button onclick='window.location.href=\"liste_stock.php\"' style='background: #FF9800; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;'>📦 Voir le Stock</button>";
    echo "</div>";
    
    echo "</div>";
    
    // JavaScript pour la correction automatique
    echo "<script>
    function corrigerStock(idArticle, nouveauStock) {
        if (confirm('Êtes-vous sûr de vouloir corriger le stock de cet article ?\\n\\nLe stock sera mis à jour pour correspondre au nombre de numéros de série disponibles.')) {
            // Créer un formulaire pour la correction
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'fonction_traitement/request.php';
            
            // Ajouter les champs cachés
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'corriger_stock_auto';
            form.appendChild(actionInput);
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id_article';
            idInput.value = idArticle;
            form.appendChild(idInput);
            
            const stockInput = document.createElement('input');
            stockInput.type = 'hidden';
            stockInput.name = 'nouveau_stock';
            stockInput.value = nouveauStock;
            form.appendChild(stockInput);
            
            // Soumettre le formulaire
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>";
    
} catch (\Throwable $th) {
    error_log("Erreur dans verification_stock_consistency.php : " . $th->getMessage());
    echo "<div style='color: red; padding: 20px;'>";
    echo "<h3>❌ Erreur lors de la vérification</h3>";
    echo "<p>Une erreur s'est produite : " . htmlspecialchars($th->getMessage()) . "</p>";
    echo "<p><a href='listes_vente.php'>← Retour aux ventes</a></p>";
    echo "</div>";
}
?>
