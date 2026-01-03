<?php
/**
 * POINT SUR LA JOURNALISATION UNIFIÉE
 * Vérification complète du système de journalisation
 */

echo "<h1>📊 POINT SUR LA JOURNALISATION UNIFIÉE</h1>";

// Vérifier si l'utilisateur est connecté
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ Vous n'êtes pas connecté</h3>";
    echo "<p>Connectez-vous d'abord pour consulter le journal.</p>";
    echo "<a href='connexion.php' class='btn btn-primary'>Se connecter</a>";
    echo "</div>";
    exit();
}

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h3>✅ Vous êtes connecté</h3>";
echo "<p><strong>Utilisateur :</strong> {$_SESSION['nom_complet']} ({$_SESSION['nom_utilisateur']})</p>";
echo "<p><strong>Fonction :</strong> {$_SESSION['type_utilisateur']}</p>";
echo "</div>";

// =====================================================
// 1. VÉRIFICATION DE LA TABLE JOURNAL_UNIFIE
// =====================================================
echo "<h2>📋 Vérification de la table journal_unifie</h2>";

try {
    include_once('db/connecting.php');
    
    // Vérifier si la table existe
    $sql = "SHOW TABLES LIKE 'journal_unifie'";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<p>✅ <strong>Table journal_unifie existe</strong></p>";
        
        // Compter les entrées
        $sql = "SELECT COUNT(*) as total FROM journal_unifie";
        $stmt = $cnx->prepare($sql);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<p><strong>Total des entrées :</strong> {$total}</p>";
        
        // Vérifier la structure
        $sql = "DESCRIBE journal_unifie";
        $stmt = $cnx->prepare($sql);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📊 Structure de la table :</h3>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><strong>{$column['Field']}</strong> - {$column['Type']} - {$column['Null']} - {$column['Key']}</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p>❌ <strong>Table journal_unifie n'existe pas</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la vérification : " . $e->getMessage() . "</p>";
}

// =====================================================
// 2. VÉRIFICATION DES MODULES JOURNALISÉS
// =====================================================
echo "<h2>🔍 Vérification des modules journalisés</h2>";

try {
    // Modules par type
    $sql = "SELECT module, COUNT(*) as count 
            FROM journal_unifie 
            GROUP BY module 
            ORDER BY count DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($modules) > 0) {
        echo "<h3>📊 Répartition par module :</h3>";
        echo "<ul>";
        foreach ($modules as $module) {
            echo "<li><strong>{$module['module']}</strong> : {$module['count']} entrées</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Aucun module journalisé.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la vérification des modules : " . $e->getMessage() . "</p>";
}

// =====================================================
// 3. VÉRIFICATION DES ACTIONS JOURNALISÉES
// =====================================================
echo "<h2>🎯 Vérification des actions journalisées</h2>";

try {
    // Actions par type
    $sql = "SELECT action, COUNT(*) as count 
            FROM journal_unifie 
            GROUP BY action 
            ORDER BY count DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($actions) > 0) {
        echo "<h3>📊 Répartition par action :</h3>";
        echo "<ul>";
        foreach ($actions as $action) {
            $style = '';
            if ($action['action'] === 'CONNEXION') {
                $style = 'color: green;';
            } elseif ($action['action'] === 'DECONNEXION') {
                $style = 'color: red;';
            } elseif ($action['action'] === 'ECHEC_CONNEXION') {
                $style = 'color: orange;';
            }
            echo "<li style='{$style}'><strong>{$action['action']}</strong> : {$action['count']} occurrences</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Aucune action journalisée.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la vérification des actions : " . $e->getMessage() . "</p>";
}

// =====================================================
// 4. VÉRIFICATION DES DERNIÈRES ENTREES
// =====================================================
echo "<h2>📋 Dernières entrées du journal</h2>";

try {
    $sql = "SELECT ju.*, u.NomPrenom as nom_utilisateur 
            FROM journal_unifie ju
            LEFT JOIN utilisateur u ON ju.IDUTILISATEUR = u.IDUTILISATEUR
            ORDER BY ju.date_action DESC
            LIMIT 20";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($entries) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Date</th><th>Module</th><th>Action</th><th>Utilisateur</th><th>Description</th><th>IP</th></tr>";
        
        foreach ($entries as $entry) {
            $style = '';
            if ($entry['action'] === 'CONNEXION') {
                $style = 'background-color: #d4edda;';
            } elseif ($entry['action'] === 'DECONNEXION') {
                $style = 'background-color: #f8d7da;';
            } elseif ($entry['action'] === 'ECHEC_CONNEXION') {
                $style = 'background-color: #fff3cd;';
            }
            
            echo "<tr style='{$style}'>";
            echo "<td>" . $entry['date_action'] . "</td>";
            echo "<td><strong>" . $entry['module'] . "</strong></td>";
            echo "<td><strong>" . $entry['action'] . "</strong></td>";
            echo "<td>" . ($entry['nom_utilisateur'] ?? 'Utilisateur inconnu') . "</td>";
            echo "<td>" . $entry['description_action'] . "</td>";
            echo "<td>" . $entry['ip_address'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Aucune entrée dans le journal.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la récupération des entrées : " . $e->getMessage() . "</p>";
}

// =====================================================
// 5. VÉRIFICATION DE LA CLASSE JOURNALUNIFIE
// =====================================================
echo "<h2>🔧 Vérification de la classe JournalUnifie</h2>";

try {
    if (file_exists('fonction_traitement/JournalUnifie.php')) {
        echo "<p>✅ <strong>Fichier JournalUnifie.php existe</strong></p>";
        
        include_once('fonction_traitement/JournalUnifie.php');
        
        if (class_exists('JournalUnifie')) {
            echo "<p>✅ <strong>Classe JournalUnifie chargée</strong></p>";
            
            // Test de la classe
            $journal = new JournalUnifie($cnx);
            echo "<p>✅ <strong>Instance JournalUnifie créée</strong></p>";
            
        } else {
            echo "<p>❌ <strong>Classe JournalUnifie non trouvée</strong></p>";
        }
        
    } else {
        echo "<p>❌ <strong>Fichier JournalUnifie.php n'existe pas</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la vérification de la classe : " . $e->getMessage() . "</p>";
}

// =====================================================
// 6. STATUT GLOBAL DU SYSTÈME
// =====================================================
echo "<h2>✅ Statut global du système</h2>";

$status = [
    'table_exists' => false,
    'class_exists' => false,
    'entries_count' => 0,
    'modules_count' => 0,
    'actions_count' => 0
];

try {
    // Vérifier la table
    $sql = "SHOW TABLES LIKE 'journal_unifie'";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $status['table_exists'] = $stmt->fetch() ? true : false;
    
    // Vérifier la classe
    $status['class_exists'] = class_exists('JournalUnifie');
    
    // Compter les entrées
    $sql = "SELECT COUNT(*) as total FROM journal_unifie";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $status['entries_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les modules
    $sql = "SELECT COUNT(DISTINCT module) as total FROM journal_unifie";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $status['modules_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les actions
    $sql = "SELECT COUNT(DISTINCT action) as total FROM journal_unifie";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $status['actions_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la vérification du statut : " . $e->getMessage() . "</p>";
}

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h3>📊 RÉSUMÉ DU SYSTÈME</h3>";
echo "<ul>";
echo "<li><strong>Table journal_unifie :</strong> " . ($status['table_exists'] ? '✅ Existe' : '❌ N\'existe pas') . "</li>";
echo "<li><strong>Classe JournalUnifie :</strong> " . ($status['class_exists'] ? '✅ Chargée' : '❌ Non chargée') . "</li>";
echo "<li><strong>Entrées totales :</strong> {$status['entries_count']}</li>";
echo "<li><strong>Modules actifs :</strong> {$status['modules_count']}</li>";
echo "<li><strong>Actions différentes :</strong> {$status['actions_count']}</li>";
echo "</ul>";
echo "</div>";

echo "<h2>🔗 Liens utiles</h2>";
echo "<ul>";
echo "<li><a href='journal.php'>📊 Consulter le journal complet</a></li>";
echo "<li><a href='test_systeme_final.php'>🧪 Test du système</a></li>";
echo "<li><a href='test_deconnexion_simple.php'>🚪 Test de déconnexion</a></li>";
echo "</ul>";

echo "<br><h1>🎉 POINT TERMINÉ !</h1>";
echo "<p><strong>Le système de journalisation unifiée a été analysé.</strong></p>";
echo "<p>Consultez les résultats ci-dessus pour identifier les problèmes éventuels.</p>";
?>
