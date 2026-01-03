<?php
// Script pour créer les tables d'inventaire manquantes
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Création des tables d'inventaire</h1>";

try {
    if (file_exists('db/connecting.php')) {
        require_once('db/connecting.php');
        
        if (isset($cnx) && $cnx !== null) {
            echo "✅ Connexion à la base de données réussie<br><br>";
            
            // Vérifier si les tables existent
            $tables_to_check = [
                'inventaire',
                'inventaire_ligne',
                'inventaire_temp',
                'inventaire_temp_series',
                'inventaire_series_attendues',
                'inventaire_log'
            ];
            
            echo "<h2>Vérification des tables existantes :</h2>";
            $existing_tables = [];
            foreach ($tables_to_check as $table) {
                $stmt = $cnx->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->fetch();
                if ($exists) {
                    echo "✅ Table '$table' existe<br>";
                    $existing_tables[] = $table;
                } else {
                    echo "❌ Table '$table' n'existe pas<br>";
                }
            }
            
            // Créer les tables manquantes
            echo "<h2>Création des tables manquantes :</h2>";
            
            // Table inventaire
            if (!in_array('inventaire', $existing_tables)) {
                echo "Création de la table 'inventaire'...<br>";
                $sql = "
                    CREATE TABLE `inventaire` (
                        `IDINVENTAIRE` int(11) NOT NULL AUTO_INCREMENT,
                        `Commentaires` varchar(255) DEFAULT NULL,
                        `DateInventaire` datetime DEFAULT CURRENT_TIMESTAMP,
                        `StatutInventaire` enum('en_attente','valide','annule') DEFAULT 'en_attente',
                        `ModifieLe` datetime DEFAULT NULL,
                        `ModifiePar` varchar(100) DEFAULT NULL,
                        `DateIns` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`IDINVENTAIRE`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $cnx->exec($sql);
                echo "✅ Table 'inventaire' créée<br>";
            }
            
            // Table inventaire_ligne
            if (!in_array('inventaire_ligne', $existing_tables)) {
                echo "Création de la table 'inventaire_ligne'...<br>";
                $sql = "
                    CREATE TABLE `inventaire_ligne` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `id_inventaire` int(11) NOT NULL,
                        `id_article` int(11) NOT NULL,
                        `code_article` varchar(100) DEFAULT NULL,
                        `designation` varchar(255) DEFAULT NULL,
                        `categorie` varchar(100) DEFAULT NULL,
                        `qte_theorique` int(11) DEFAULT 0,
                        `qte_physique` int(11) DEFAULT NULL,
                        `ecart` int(11) DEFAULT 0,
                        `statut` enum('en_cours','valide') DEFAULT 'en_cours',
                        `date_saisie` datetime DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `id_inventaire` (`id_inventaire`),
                        KEY `id_article` (`id_article`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $cnx->exec($sql);
                echo "✅ Table 'inventaire_ligne' créée<br>";
            }
            
            // Table inventaire_temp
            if (!in_array('inventaire_temp', $existing_tables)) {
                echo "Création de la table 'inventaire_temp'...<br>";
                $sql = "
                    CREATE TABLE `inventaire_temp` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `id_inventaire` int(11) NOT NULL,
                        `id_article` int(11) NOT NULL,
                        `id_utilisateur` int(11) NOT NULL,
                        `code_article` varchar(100) DEFAULT NULL,
                        `designation` varchar(255) DEFAULT NULL,
                        `categorie` varchar(100) DEFAULT NULL,
                        `qte_theorique` int(11) DEFAULT 0,
                        `qte_physique` int(11) DEFAULT NULL,
                        `ecart` int(11) DEFAULT 0,
                        `statut` enum('en_cours','valide') DEFAULT 'en_cours',
                        `date_saisie` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `id_inventaire` (`id_inventaire`),
                        KEY `id_article` (`id_article`),
                        KEY `id_utilisateur` (`id_utilisateur`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $cnx->exec($sql);
                echo "✅ Table 'inventaire_temp' créée<br>";
            }
            
            // Table inventaire_temp_series
            if (!in_array('inventaire_temp_series', $existing_tables)) {
                echo "Création de la table 'inventaire_temp_series'...<br>";
                $sql = "
                    CREATE TABLE `inventaire_temp_series` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `id_inventaire_temp` int(11) NOT NULL,
                        `numero_serie` varchar(100) NOT NULL,
                        `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `id_inventaire_temp` (`id_inventaire_temp`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $cnx->exec($sql);
                echo "✅ Table 'inventaire_temp_series' créée<br>";
            }
            
            // Table inventaire_series_attendues
            if (!in_array('inventaire_series_attendues', $existing_tables)) {
                echo "Création de la table 'inventaire_series_attendues'...<br>";
                $sql = "
                    CREATE TABLE `inventaire_series_attendues` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `id_inventaire` int(11) NOT NULL,
                        `id_article` int(11) NOT NULL,
                        `id_num_serie` int(11) NOT NULL,
                        `numero_serie` varchar(100) NOT NULL,
                        `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `id_inventaire` (`id_inventaire`),
                        KEY `id_article` (`id_article`),
                        KEY `id_num_serie` (`id_num_serie`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $cnx->exec($sql);
                echo "✅ Table 'inventaire_series_attendues' créée<br>";
            }
            
            // Table inventaire_log
            if (!in_array('inventaire_log', $existing_tables)) {
                echo "Création de la table 'inventaire_log'...<br>";
                $sql = "
                    CREATE TABLE `inventaire_log` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `id_inventaire` int(11) NOT NULL,
                        `id_article` int(11) NOT NULL,
                        `utilisateur` varchar(100) NOT NULL,
                        `date_action` datetime NOT NULL,
                        `action` enum('creation','modification','suppression','validation') NOT NULL,
                        `qte_avant` int(11) DEFAULT NULL,
                        `qte_apres` int(11) DEFAULT NULL,
                        `commentaire` text DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `id_inventaire` (`id_inventaire`),
                        KEY `id_article` (`id_article`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $cnx->exec($sql);
                echo "✅ Table 'inventaire_log' créée<br>";
            }
            
            echo "<h2>Vérification finale :</h2>";
            foreach ($tables_to_check as $table) {
                $stmt = $cnx->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->fetch();
                if ($exists) {
                    echo "✅ Table '$table' existe<br>";
                } else {
                    echo "❌ Table '$table' n'existe toujours pas<br>";
                }
            }
            
            echo "<h2>Tables créées avec succès !</h2>";
            echo "<p>Vous pouvez maintenant tester vos pages d'inventaire.</p>";
            
        } else {
            echo "❌ Connexion à la base de données échouée<br>";
        }
    } else {
        echo "❌ Fichier connecting.php introuvable<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}
?>
