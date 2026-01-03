<?php
require_once 'db/connecting.php';

echo "<h2>🔍 Vérification de la table sav_piece</h2>";

try {
    // Vérifier si la table existe
    $stmt = $cnx->query("SHOW TABLES LIKE 'sav_piece'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table sav_piece existe<br>";
        
        // Afficher la structure
        echo "<h3>Structure actuelle :</h3>";
        $stmt = $cnx->query("DESCRIBE sav_piece");
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Vérifier les colonnes requises
        $colonnes_requises = ['id_piece', 'id_sav', 'designation', 'cout_unitaire', 'quantite', 'cout_total', 'date_achat'];
        $stmt = $cnx->query("SHOW COLUMNS FROM sav_piece");
        $colonnes_existantes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $colonnes_existantes[] = $row['Field'];
        }
        
        echo "<h3>Vérification des colonnes :</h3>";
        foreach ($colonnes_requises as $colonne) {
            if (in_array($colonne, $colonnes_existantes)) {
                echo "✅ $colonne existe<br>";
            } else {
                echo "❌ $colonne MANQUANTE<br>";
            }
        }
        
        // Test d'insertion
        echo "<h3>Test d'insertion :</h3>";
        try {
            $stmt = $cnx->prepare("INSERT INTO sav_piece (id_sav, designation, cout_unitaire, quantite, cout_total, date_achat) VALUES (999, 'TEST', 100.00, 1, 100.00, NOW())");
            $stmt->execute();
            echo "✅ Insertion de test réussie<br>";
            
            // Supprimer l'enregistrement de test
            $stmt = $cnx->prepare("DELETE FROM sav_piece WHERE id_sav = 999");
            $stmt->execute();
            echo "✅ Suppression de test réussie<br>";
            
        } catch (Exception $e) {
            echo "❌ Erreur lors du test d'insertion : " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ Table sav_piece N'EXISTE PAS<br>";
        echo "<h3>Création de la table :</h3>";
        
        $sql = "CREATE TABLE IF NOT EXISTS `sav_piece` (
            `id_piece` int NOT NULL AUTO_INCREMENT,
            `id_sav` int NOT NULL,
            `designation` varchar(255) NOT NULL,
            `cout_unitaire` decimal(10,2) NOT NULL DEFAULT 0.00,
            `quantite` int NOT NULL DEFAULT 1,
            `cout_total` decimal(10,2) NOT NULL DEFAULT 0.00,
            `date_achat` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_piece`),
            KEY `id_sav` (`id_sav`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        try {
            $cnx->exec($sql);
            echo "✅ Table sav_piece créée avec succès<br>";
        } catch (Exception $e) {
            echo "❌ Erreur lors de la création : " . $e->getMessage() . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
}

echo "<br><a href='sav.php'>← Retour à la création SAV</a>";
?> 