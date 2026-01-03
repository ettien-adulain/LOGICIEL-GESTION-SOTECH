<?php
/**
 * CRÉATION DE LA TABLE JOURNAL UNIFIÉE
 * Script pour créer la table journal_unifie si elle n'existe pas
 */

echo "<h1>🏗️ Création de la Table Journal Unifiée</h1>";

try {
    // 1. Connexion à la base de données
    echo "<h2>1. Connexion à la base de données</h2>";
    require_once 'db/connecting.php';
    
    if (!isset($cnx)) {
        throw new Exception("Impossible de se connecter à la base de données");
    }
    echo "<p>✅ Connexion à la base de données réussie</p>";
    
    // 2. Vérifier si la table existe déjà
    echo "<h2>2. Vérification de l'existence de la table</h2>";
    
    $sql = "SHOW TABLES LIKE 'journal_unifie'";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($table) {
        echo "<p>✅ Table journal_unifie existe déjà</p>";
        
        // Vérifier le nombre d'entrées
        $sql = "SELECT COUNT(*) as total FROM journal_unifie";
        $stmt = $cnx->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Nombre d'entrées actuelles: " . $result['total'] . "</p>";
        
    } else {
        echo "<p>❌ Table journal_unifie n'existe pas</p>";
        
        // 3. Créer la table
        echo "<h2>3. Création de la table journal_unifie</h2>";
        
        $sql = "
        CREATE TABLE IF NOT EXISTS `journal_unifie` (
          `IDJOURNAL` int NOT NULL AUTO_INCREMENT,
          
          -- CHAMPS PRINCIPAUX
          `module` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Module: article, client, stock, commande, vente, numero_serie, connexion, comptabilite',
          `entite_id` int NOT NULL COMMENT 'ID de l\'entité concernée',
          `entite_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Type: article, client, stock, commande, vente, numero_serie, utilisateur',
          `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Action: CREATION, MODIFICATION, VALIDATION, SUPPRESSION, ENTREE, SORTIE, AFFECTATION, CONNEXION, DECONNEXION',
          `IDUTILISATEUR` int NOT NULL COMMENT 'ID de l\'utilisateur qui a effectué l\'action',
          `description_action` text COLLATE utf8mb4_general_ci COMMENT 'Description détaillée de l\'action',
          
          -- CHAMPS SPÉCIFIQUES AU STOCK
          `IDARTICLE` int DEFAULT NULL COMMENT 'ID de l\'article (si applicable)',
          `IDSTOCK` int DEFAULT NULL COMMENT 'ID du stock (si applicable)',
          `stock_avant` int DEFAULT NULL COMMENT 'Stock avant l\'action',
          `stock_apres` int DEFAULT NULL COMMENT 'Stock après l\'action',
          
          -- CHAMPS SPÉCIFIQUES AUX VENTES
          `IDVENTE` int DEFAULT NULL COMMENT 'ID de la vente (si applicable)',
          `IDCLIENT` int DEFAULT NULL COMMENT 'ID du client (si applicable)',
          `montant_vente` decimal(10,2) DEFAULT NULL COMMENT 'Montant de la vente',
          `mode_paiement` varchar(50) DEFAULT NULL COMMENT 'Mode de paiement',
          
          -- CHAMPS SPÉCIFIQUES AUX NUMÉROS DE SÉRIE
          `IDNUMERO_SERIE` int DEFAULT NULL COMMENT 'ID du numéro de série (si applicable)',
          `numero_serie` varchar(100) DEFAULT NULL COMMENT 'Numéro de série',
          
          -- CHAMPS SPÉCIFIQUES AUX CORRECTIONS
          `IDCORRECTION` int DEFAULT NULL COMMENT 'ID de la correction (si applicable)',
          `type_correction` varchar(50) DEFAULT NULL COMMENT 'Type de correction',
          `motif_correction` text COMMENT 'Motif de la correction',
          
          -- CHAMPS SPÉCIFIQUES AUX INVENTAIRES
          `IDINVENTAIRE` int DEFAULT NULL COMMENT 'ID de l\'inventaire (si applicable)',
          `nom_inventaire` varchar(100) DEFAULT NULL COMMENT 'Nom de l\'inventaire',
          
          -- CHAMPS SPÉCIFIQUES AUX DOSSIERS SAV
          `IDSAV` int DEFAULT NULL COMMENT 'ID du dossier SAV (si applicable)',
          `numero_sav` varchar(50) DEFAULT NULL COMMENT 'Numéro du dossier SAV',
          
          -- CHAMPS SPÉCIFIQUES AUX COMMANDES
          `IDCOMMANDE` int DEFAULT NULL COMMENT 'ID de la commande (si applicable)',
          `numero_commande` varchar(50) DEFAULT NULL COMMENT 'Numéro de la commande',
          
          -- CHAMPS SPÉCIFIQUES AUX PROFORMA
          `IDPROFORMA` int DEFAULT NULL COMMENT 'ID de la proforma (si applicable)',
          `numero_proforma` varchar(50) DEFAULT NULL COMMENT 'Numéro de la proforma',
          
          -- CHAMPS SPÉCIFIQUES AUX ENTREES EN STOCK
          `IDENTREE_STOCK` int DEFAULT NULL COMMENT 'ID de l\'entrée en stock (si applicable)',
          `numero_bon` varchar(50) DEFAULT NULL COMMENT 'Numéro du bon d\'entrée',
          `quantite_entree` int DEFAULT NULL COMMENT 'Quantité entrée',
          `prix_achat` decimal(10,2) DEFAULT NULL COMMENT 'Prix d\'achat',
          
          -- CHAMPS GÉNÉRAUX
          `date_action` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date et heure de l\'action',
          `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Adresse IP de l\'utilisateur',
          `user_agent` text COLLATE utf8mb4_general_ci COMMENT 'User Agent du navigateur',
          `desactiver` varchar(3) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'non' COMMENT 'Indicateur de désactivation',
          
          PRIMARY KEY (`IDJOURNAL`),
          KEY `idx_module` (`module`),
          KEY `idx_entite_id` (`entite_id`),
          KEY `idx_entite_type` (`entite_type`),
          KEY `idx_action` (`action`),
          KEY `idx_utilisateur` (`IDUTILISATEUR`),
          KEY `idx_date_action` (`date_action`),
          KEY `idx_article` (`IDARTICLE`),
          KEY `idx_vente` (`IDVENTE`),
          KEY `idx_client` (`IDCLIENT`),
          KEY `idx_stock` (`IDSTOCK`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Table unifiée pour la journalisation de toutes les actions du système LOGICIEL_SOTECH';
        ";
        
        $stmt = $cnx->prepare($sql);
        $result = $stmt->execute();
        
        if ($result) {
            echo "<p>✅ Table journal_unifie créée avec succès</p>";
        } else {
            echo "<p>❌ Erreur lors de la création de la table</p>";
        }
    }
    
    // 4. Test de la table
    echo "<h2>4. Test de la table</h2>";
    
    try {
        // Test d'insertion
        $sql = "INSERT INTO journal_unifie (module, entite_id, entite_type, action, IDUTILISATEUR, description_action) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $result = $stmt->execute(['test', 1, 'test', 'TEST', 1, 'Test de création de table']);
        
        if ($result) {
            echo "<p>✅ Test d'insertion réussi</p>";
            
            // Supprimer l'entrée de test
            $sql = "DELETE FROM journal_unifie WHERE module = 'test'";
            $stmt = $cnx->prepare($sql);
            $stmt->execute();
            echo "<p>✅ Entrée de test supprimée</p>";
        } else {
            echo "<p>❌ Test d'insertion échoué</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erreur test table: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>✅ Création de table terminée</h2>";
    echo "<p>La table journal_unifie est maintenant prête à être utilisée !</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p>❌ Erreur fatale: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création Table Journal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        p { margin: 10px 0; }
    </style>
</head>
<body>
</body>
</html>
