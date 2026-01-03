<?php
// Lister tous les inventaires disponibles
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>📋 LISTE DES INVENTAIRES</h1>";

try {
    // Charger les fichiers requis
    require_once 'fonction_traitement/fonction.php';
    include('db/connecting.php');
    
    // Récupérer tous les inventaires
    $inventaires = $cnx->query("
        SELECT 
            i.*,
            COUNT(il.id) as nombre_articles,
            SUM(CASE WHEN il.ecart != 0 THEN 1 ELSE 0 END) as articles_avec_ecart
        FROM inventaire i
        LEFT JOIN inventaire_ligne il ON i.IDINVENTAIRE = il.id_inventaire
        GROUP BY i.IDINVENTAIRE
        ORDER BY i.DateInventaire DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($inventaires)) {
        echo "<p>❌ Aucun inventaire trouvé dans la base de données.</p>";
        echo "<p>💡 <a href='create_test_inventaire.php'>Créer un inventaire de test</a></p>";
        echo "<p>💡 <a href='inventaire_lancement.php'>Créer un nouvel inventaire</a></p>";
    } else {
        echo "<p>✅ " . count($inventaires) . " inventaire(s) trouvé(s)</p>";
        
        echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<thead>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th>";
        echo "<th>Nom</th>";
        echo "<th>Date</th>";
        echo "<th>Statut</th>";
        echo "<th>Articles</th>";
        echo "<th>Écarts</th>";
        echo "<th>Actions</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($inventaires as $inv) {
            $statut_color = $inv['StatutInventaire'] == 'en_attente' ? 'orange' : 'green';
            $statut_text = $inv['StatutInventaire'] == 'en_attente' ? 'En attente' : 'Validé';
            
            echo "<tr>";
            echo "<td><strong>{$inv['IDINVENTAIRE']}</strong></td>";
            echo "<td>" . htmlspecialchars($inv['Commentaires']) . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($inv['DateInventaire'])) . "</td>";
            echo "<td style='color: $statut_color;'><strong>$statut_text</strong></td>";
            echo "<td>{$inv['nombre_articles']}</td>";
            echo "<td>{$inv['articles_avec_ecart']}</td>";
            echo "<td>";
            
            // Liens d'impression
            echo "<a href='inventaire_impression_categorie.php?IDINVENTAIRE={$inv['IDINVENTAIRE']}&type_comptage=comptage1' target='_blank' style='margin-right: 5px;'>🔵 C1</a>";
            echo "<a href='inventaire_impression_categorie.php?IDINVENTAIRE={$inv['IDINVENTAIRE']}&type_comptage=comptage2' target='_blank' style='margin-right: 5px;'>🟢 C2</a>";
            echo "<a href='inventaire_impression_categorie.php?IDINVENTAIRE={$inv['IDINVENTAIRE']}&type_comptage=comptage3' target='_blank' style='margin-right: 5px;'>🟡 C3</a>";
            echo "<a href='inventaire_impression_categorie.php?IDINVENTAIRE={$inv['IDINVENTAIRE']}&type_comptage=comptage4' target='_blank' style='margin-right: 5px;'>🔴 C4</a>";
            
            // Autres actions
            echo "<br>";
            echo "<a href='inventaire_saisie.php?IDINVENTAIRE={$inv['IDINVENTAIRE']}' style='margin-right: 5px;'>📝 Saisie</a>";
            echo "<a href='inventaire_rapport.php?IDINVENTAIRE={$inv['IDINVENTAIRE']}' style='margin-right: 5px;'>📊 Rapport</a>";
            
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        
        echo "<h2>📝 LÉGENDE</h2>";
        echo "<ul>";
        echo "<li><strong>🔵 C1:</strong> 1er Comptage (Fiches vides)</li>";
        echo "<li><strong>🟢 C2:</strong> 2ème Comptage (Vérification)</li>";
        echo "<li><strong>🟡 C3:</strong> 3ème Comptage (Contrôle)</li>";
        echo "<li><strong>🔴 C4:</strong> 4ème Comptage (Audit final)</li>";
        echo "<li><strong>📝 Saisie:</strong> Saisir les comptages</li>";
        echo "<li><strong>📊 Rapport:</strong> Voir le rapport</li>";
        echo "</ul>";
    }
    
    echo "<hr>";
    echo "<h2>🔗 LIENS UTILES</h2>";
    echo "<ul>";
    echo "<li><a href='create_test_inventaire.php'>Créer un inventaire de test</a></li>";
    echo "<li><a href='inventaire_lancement.php'>Créer un nouvel inventaire</a></li>";
    echo "<li><a href='debug_inventaire_impression.php'>Diagnostic</a></li>";
    echo "<li><a href='check_php_errors.php'>Vérification PHP</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERREUR</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
}
?>

