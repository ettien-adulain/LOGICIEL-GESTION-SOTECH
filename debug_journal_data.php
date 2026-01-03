<?php
/**
 * DEBUG DES DONNÉES DU JOURNAL
 * Vérifier les données réelles dans journal_unifie
 */

echo "<h1>🔍 Debug des Données du Journal</h1>";

try {
    require_once 'db/connecting.php';
    
    echo "<h2>1. Vérification des données dans journal_unifie</h2>";
    
    // Vérifier les modules disponibles
    $sql = "SELECT module, COUNT(*) as count FROM journal_unifie GROUP BY module ORDER BY count DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Modules disponibles:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Module</th><th>Nombre</th></tr>";
    foreach ($modules as $module) {
        echo "<tr><td>" . $module['module'] . "</td><td>" . $module['count'] . "</td></tr>";
    }
    echo "</table>";
    
    // Vérifier les actions disponibles
    $sql = "SELECT action, COUNT(*) as count FROM journal_unifie GROUP BY action ORDER BY count DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Actions disponibles:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Action</th><th>Nombre</th></tr>";
    foreach ($actions as $action) {
        echo "<tr><td>" . $action['action'] . "</td><td>" . $action['count'] . "</td></tr>";
    }
    echo "</table>";
    
    // Vérifier les entite_id pour le module 'article'
    $sql = "SELECT entite_id, COUNT(*) as count FROM journal_unifie WHERE module = 'article' GROUP BY entite_id ORDER BY count DESC LIMIT 10";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $entite_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Entite_id pour module 'article' (top 10):</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Entite_id</th><th>Nombre</th></tr>";
    foreach ($entite_ids as $entite) {
        echo "<tr><td>" . $entite['entite_id'] . "</td><td>" . $entite['count'] . "</td></tr>";
    }
    echo "</table>";
    
    // Vérifier si les entite_id correspondent aux IDARTICLE
    echo "<h2>2. Vérification des correspondances</h2>";
    
    $sql = "SELECT ju.entite_id, a.IDARTICLE, a.libelle 
            FROM journal_unifie ju 
            LEFT JOIN article a ON ju.entite_id = a.IDARTICLE 
            WHERE ju.module = 'article' 
            LIMIT 10";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $correspondances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Correspondances entite_id vs IDARTICLE:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Entite_id</th><th>IDARTICLE</th><th>Libelle</th></tr>";
    foreach ($correspondances as $corr) {
        $match = $corr['IDARTICLE'] ? "✅" : "❌";
        echo "<tr><td>" . $corr['entite_id'] . "</td><td>" . $corr['IDARTICLE'] . "</td><td>" . $corr['libelle'] . " $match</td></tr>";
    }
    echo "</table>";
    
    // Vérifier les dernières entrées
    echo "<h2>3. Dernières entrées du journal</h2>";
    
    $sql = "SELECT * FROM journal_unifie ORDER BY date_action DESC LIMIT 5";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Module</th><th>Entite_id</th><th>Action</th><th>Description</th><th>Date</th></tr>";
    foreach ($entries as $entry) {
        echo "<tr>";
        echo "<td>" . $entry['IDJOURNAL'] . "</td>";
        echo "<td>" . $entry['module'] . "</td>";
        echo "<td>" . $entry['entite_id'] . "</td>";
        echo "<td>" . $entry['action'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($entry['description_action'], 0, 50)) . "...</td>";
        echo "<td>" . $entry['date_action'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>✅ Debug terminé</h2>";
    
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Journal Data</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2, h3 { color: #333; }
        table { margin: 10px 0; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
</body>
</html>
