<?php
// Script de diagnostic pour Hostinger
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Diagnostic Hostinger\n";
echo "======================\n\n";

// Test 1: Vérifier PHP
echo "1. Version PHP: " . PHP_VERSION . "\n";
echo "   Extensions chargées: " . implode(', ', get_loaded_extensions()) . "\n\n";

// Test 2: Vérifier les fichiers critiques
echo "2. Vérification des fichiers:\n";
$criticalFiles = [
    'db/connecting.php',
    'fonction_traitement/fonction.php',
    'includes/header.php',
    'includes/user_indicator.php',
    'includes/navigation_buttons.php',
    'includes/theme_switcher.php'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file (MANQUANT)\n";
    }
}

// Test 3: Vérifier la base de données
echo "\n3. Test de connexion base de données:\n";
try {
    if (file_exists('db/connecting.php')) {
        include('db/connecting.php');
        echo "   ✅ Connexion DB réussie\n";
        
        // Test simple
        $stmt = $cnx->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result) {
            echo "   ✅ Requête test réussie\n";
        }
    } else {
        echo "   ❌ Fichier de connexion manquant\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur DB: " . $e->getMessage() . "\n";
}

// Test 4: Vérifier les sessions
echo "\n4. Test des sessions:\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "   Status session: " . session_status() . "\n";
echo "   ID session: " . session_id() . "\n";

// Test 5: Vérifier les permissions
echo "\n5. Permissions des dossiers:\n";
$dirs = ['logs', 'uploads', 'db', 'fonction_traitement', 'includes'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "   ✅ $dir (accessible en écriture)\n";
        } else {
            echo "   ⚠️ $dir (lecture seule)\n";
        }
    } else {
        echo "   ❌ $dir (n'existe pas)\n";
    }
}

// Test 6: Vérifier les erreurs PHP
echo "\n6. Logs d'erreurs:\n";
$errorLog = ini_get('error_log');
echo "   Fichier de log: " . ($errorLog ?: 'Non défini') . "\n";

// Test 7: Vérifier la mémoire
echo "\n7. Configuration PHP:\n";
echo "   Mémoire limite: " . ini_get('memory_limit') . "\n";
echo "   Temps d'exécution: " . ini_get('max_execution_time') . "\n";
echo "   Upload max: " . ini_get('upload_max_filesize') . "\n";

// Test 8: Vérifier les includes
echo "\n8. Test des includes:\n";
try {
    if (file_exists('includes/header.php')) {
        ob_start();
        include('includes/header.php');
        $headerContent = ob_get_clean();
        echo "   ✅ includes/header.php chargé\n";
    } else {
        echo "   ❌ includes/header.php manquant\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur include header: " . $e->getMessage() . "\n";
}

echo "\n🎯 Résumé:\n";
echo "==========\n";
echo "Si vous voyez des ❌, ce sont les problèmes à corriger.\n";
echo "Les erreurs 500 sont souvent causées par:\n";
echo "- Fichiers manquants\n";
echo "- Erreurs de syntaxe PHP\n";
echo "- Problèmes de permissions\n";
echo "- Erreurs de base de données\n";
?>
