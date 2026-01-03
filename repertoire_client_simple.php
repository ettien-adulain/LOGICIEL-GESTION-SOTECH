<?php
// Version simplifiée pour diagnostiquer l'erreur 500
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Test de repertoire_client.php\n";
echo "================================\n\n";

// Test 1: Vérifier les includes de base
echo "1. Test des includes:\n";

try {
    echo "   Test include db/connecting.php...\n";
    if (file_exists('db/connecting.php')) {
        include('db/connecting.php');
        echo "   ✅ db/connecting.php chargé\n";
    } else {
        echo "   ❌ db/connecting.php manquant\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ❌ Erreur db/connecting.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    echo "   Test include fonction_traitement/fonction.php...\n";
    if (file_exists('fonction_traitement/fonction.php')) {
        require_once 'fonction_traitement/fonction.php';
        echo "   ✅ fonction_traitement/fonction.php chargé\n";
    } else {
        echo "   ❌ fonction_traitement/fonction.php manquant\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ❌ Erreur fonction_traitement/fonction.php: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Vérifier les fonctions critiques
echo "\n2. Test des fonctions:\n";

if (function_exists('check_access')) {
    echo "   ✅ Fonction check_access disponible\n";
} else {
    echo "   ❌ Fonction check_access manquante\n";
}

if (function_exists('can_user')) {
    echo "   ✅ Fonction can_user disponible\n";
} else {
    echo "   ❌ Fonction can_user manquante\n";
}

// Test 3: Test de session
echo "\n3. Test des sessions:\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "   ✅ Session démarrée\n";
} else {
    echo "   ✅ Session déjà active\n";
}

// Test 4: Test de base de données
echo "\n4. Test de base de données:\n";
try {
    $sql = "SELECT COUNT(*) as total FROM client";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Connexion DB OK - Nombre de clients: " . $result['total'] . "\n";
} catch (Exception $e) {
    echo "   ❌ Erreur DB: " . $e->getMessage() . "\n";
}

// Test 5: Test des includes optionnels
echo "\n5. Test des includes optionnels:\n";

$optionalFiles = [
    'integrate_journal_global.php',
    'includes/header.php',
    'includes/user_indicator.php',
    'includes/navigation_buttons.php',
    'includes/theme_switcher.php'
];

foreach ($optionalFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file trouvé\n";
    } else {
        echo "   ⚠️ $file manquant (optionnel)\n";
    }
}

echo "\n🎯 Si tous les tests sont ✅, le problème vient probablement d'un include manquant.\n";
echo "Uploadez d'abord debug_hostinger.php pour voir les détails complets.\n";
?>
