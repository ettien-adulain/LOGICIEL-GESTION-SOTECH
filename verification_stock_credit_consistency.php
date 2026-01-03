<?php
session_start();
include('db/connecting.php');

require_once 'fonction_traitement/fonction.php';
check_access();

try {
    echo "<div style='font-family: Arial, sans-serif; max-width: 1400px; margin: 50px auto; padding: 20px;'>";
    echo "<h2>🔍 Vérification de la Cohérence Stock vs Numéros de Série (Ventes à Crédit)</h2>";
    echo "<p><strong>Date de vérification :</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    // 1. Vérifier les articles avec leurs stocks et numéros de série (incluant les ventes à crédit)
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
            SUM(CASE WHEN ns.statut = 'perdue' THEN 1 ELSE 0 END) as series_perdues,
            SUM(CASE WHEN ns.statut = 'casse' THEN 1 ELSE 0 END) as series_cassees
        FROM article a
        LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE
        LEFT JOIN num_serie ns ON a.IDARTICLE = ns.IDARTICLE
        WHERE a.desactiver != 'oui'
        GROUP BY a.IDARTICLE, a.libelle, a.CodePersoArticle, s.StockActuel
        HAVING total_series > 0
        ORDER BY a.libelle
    ");
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($articles)) {
        echo "<div class='alert alert-info'>Aucun article avec numéros de série trouvé.</div>";
        echo "</div>";
        exit();
    }
    
    // 2. Analyser les incohérences
    $incoherences = [];
    $total_articles = count($articles);
    $articles_ok = 0;
    
    foreach ($articles as $article) {
        $stock_actuel = (int)$article['stock_actuel'];
        $series_disponibles = (int)$article['series_disponibles'];
        $series_vendues = (int)$article['series_vendues'];
        $series_vendues_credit = (int)$article['series_vendues_credit'];
        $series_perdues = (int)$article['series_perdues'];
        $series_cassees = (int)$article['series_cassees'];
        $total_series = (int)$article['total_series'];
        
        // Vérifier la cohérence : StockActuel doit égaler series_disponibles
        if ($stock_actuel !== $series_disponibles) {
            $incoherences[] = [
                'article' => $article,
                'stock_actuel' => $stock_actuel,
                'series_disponibles' => $series_disponibles,
                'difference' => $stock_actuel - $series_disponibles,
                'series_vendues' => $series_vendues,
                'series_vendues_credit' => $series_vendues_credit,
                'series_perdues' => $series_perdues,
                'series_cassees' => $series_cassees,
                'total_series' => $total_series
            ];
        } else {
            $articles_ok++;
        }
    }
    
    // 3. Afficher le résumé
    echo "<div class='row mb-4'>";
    echo "<div class='col-md-3'>";
    echo "<div class='card bg-success text-white'>";
    echo "<div class='card-body text-center'>";
    echo "<h4>" . $articles_ok . "</h4>";
    echo "<p>Articles cohérents</p>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-3'>";
    echo "<div class='card bg-danger text-white'>";
    echo "<div class='card-body text-center'>";
    echo "<h4>" . count($incoherences) . "</h4>";
    echo "<p>Incohérences détectées</p>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-3'>";
    echo "<div class='card bg-info text-white'>";
    echo "<div class='card-body text-center'>";
    echo "<h4>" . $total_articles . "</h4>";
    echo "<p>Total articles</p>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-3'>";
    echo "<div class='card bg-warning text-white'>";
    echo "<div class='card-body text-center'>";
    echo "<h4>" . round(($articles_ok / $total_articles) * 100, 1) . "%</h4>";
    echo "<p>Taux de cohérence</p>";
    echo "</div></div></div>";
    echo "</div>";
    
    // 4. Afficher les incohérences
    if (!empty($incoherences)) {
        echo "<h3>🚨 Incohérences Détectées</h3>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped table-bordered'>";
        echo "<thead class='thead-dark'>";
        echo "<tr>";
        echo "<th>Article</th>";
        echo "<th>Code</th>";
        echo "<th>Stock Actuel</th>";
        echo "<th>Séries Disponibles</th>";
        echo "<th>Différence</th>";
        echo "<th>Séries Vendues</th>";
        echo "<th>Séries Vendues Crédit</th>";
        echo "<th>Séries Perdues</th>";
        echo "<th>Séries Cassées</th>";
        echo "<th>Total Séries</th>";
        echo "<th>Action</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($incoherences as $incoherence) {
            $article = $incoherence['article'];
            $difference = $incoherence['difference'];
            $couleur_diff = $difference > 0 ? 'text-success' : 'text-danger';
            $icone_diff = $difference > 0 ? '↗️' : '↘️';
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($article['libelle']) . "</td>";
            echo "<td>" . htmlspecialchars($article['CodePersoArticle']) . "</td>";
            echo "<td><strong>" . $incoherence['stock_actuel'] . "</strong></td>";
            echo "<td><strong>" . $incoherence['series_disponibles'] . "</strong></td>";
            echo "<td class='" . $couleur_diff . "'><strong>" . $icone_diff . " " . $difference . "</strong></td>";
            echo "<td>" . $incoherence['series_vendues'] . "</td>";
            echo "<td>" . $incoherence['series_vendues_credit'] . "</td>";
            echo "<td>" . $incoherence['series_perdues'] . "</td>";
            echo "<td>" . $incoherence['series_cassees'] . "</td>";
            echo "<td>" . $incoherence['total_series'] . "</td>";
            echo "<td>";
            echo "<form method='POST' action='fonction_traitement/request.php' style='display: inline;'>";
            echo "<input type='hidden' name='action' value='corriger_stock_credit_auto'>";
            echo "<input type='hidden' name='id_article' value='" . $article['IDARTICLE'] . "'>";
            echo "<input type='hidden' name='nouveau_stock' value='" . $incoherence['series_disponibles'] . "'>";
            echo "<button type='submit' class='btn btn-warning btn-sm' onclick='return confirm(\"Corriger le stock de " . htmlspecialchars($article['libelle']) . " de " . $incoherence['stock_actuel'] . " à " . $incoherence['series_disponibles'] . " ?\")'>";
            echo "<i class='fas fa-wrench'></i> Corriger";
            echo "</button>";
            echo "</form>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        
        // 5. Bouton de correction en masse
        echo "<div class='mt-4'>";
        echo "<form method='POST' action='fonction_traitement/request.php'>";
        echo "<input type='hidden' name='action' value='corriger_stock_credit_masse'>";
        echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Corriger automatiquement le stock de tous les articles incohérents ? Cette action est irréversible.\")'>";
        echo "<i class='fas fa-tools'></i> Corriger Toutes les Incohérences";
        echo "</button>";
        echo "</form>";
        echo "</div>";
        
    } else {
        echo "<div class='alert alert-success'>";
        echo "<h4>✅ Excellent !</h4>";
        echo "<p>Tous les articles sont cohérents. Le stock correspond parfaitement aux numéros de série disponibles.</p>";
        echo "</div>";
    }
    
    // 6. Afficher les détails complets
    echo "<h3>📊 Détails Complets</h3>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-striped table-bordered'>";
    echo "<thead class='thead-dark'>";
    echo "<tr>";
    echo "<th>Article</th>";
    echo "<th>Code</th>";
    echo "<th>Stock Actuel</th>";
    echo "<th>Séries Disponibles</th>";
    echo "<th>Séries Vendues</th>";
    echo "<th>Séries Vendues Crédit</th>";
    echo "<th>Séries Perdues</th>";
    echo "<th>Séries Cassées</th>";
    echo "<th>Total Séries</th>";
    echo "<th>Statut</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($articles as $article) {
        $stock_actuel = (int)$article['stock_actuel'];
        $series_disponibles = (int)$article['series_disponibles'];
        $is_coherent = ($stock_actuel === $series_disponibles);
        $couleur_ligne = $is_coherent ? '' : 'table-warning';
        $statut = $is_coherent ? '✅ Cohérent' : '⚠️ Incohérent';
        
        echo "<tr class='" . $couleur_ligne . "'>";
        echo "<td>" . htmlspecialchars($article['libelle']) . "</td>";
        echo "<td>" . htmlspecialchars($article['CodePersoArticle']) . "</td>";
        echo "<td>" . $stock_actuel . "</td>";
        echo "<td>" . $series_disponibles . "</td>";
        echo "<td>" . $article['series_vendues'] . "</td>";
        echo "<td>" . $article['series_vendues_credit'] . "</td>";
        echo "<td>" . $article['series_perdues'] . "</td>";
        echo "<td>" . $article['series_cassees'] . "</td>";
        echo "<td>" . $article['total_series'] . "</td>";
        echo "<td>" . $statut . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    
    // 7. Liens de navigation
    echo "<div class='mt-4'>";
    echo "<a href='verification_stock_consistency.php' class='btn btn-primary'>";
    echo "<i class='fas fa-arrow-left'></i> Vérification Stock Normal";
    echo "</a>";
    echo "<a href='suivi_vente_credit.php' class='btn btn-secondary'>";
    echo "<i class='fas fa-credit-card'></i> Ventes à Crédit";
    echo "</a>";
    echo "<a href='liste_stock.php' class='btn btn-info'>";
    echo "<i class='fas fa-boxes'></i> Liste des Stocks";
    echo "</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Erreur</h4>";
    echo "<p>Une erreur est survenue : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</div>";
?>
