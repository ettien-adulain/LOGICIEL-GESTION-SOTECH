<?php
/**
 * Configuration automatique des chemins pour DOMPDF
 * Ce fichier détecte automatiquement où se trouve l'autoloader Composer
 */

function getAutoloadPath() {
    // Essayer plusieurs chemins possibles
    $possiblePaths = [
        __DIR__ . '/vendor/autoload.php',                    // Dossier local
        __DIR__ . '/../vendor/autoload.php',                 // Dossier parent
        __DIR__ . '/../../vendor/autoload.php',              // Dossier grand-parent
        __DIR__ . '/../../../vendor/autoload.php',           // Dossier arrière-grand-parent
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return false;
}

function loadDompdf() {
    $autoloadPath = getAutoloadPath();
    
    if (!$autoloadPath) {
        throw new Exception("Aucun autoloader Composer trouvé. Veuillez installer DOMPDF via Composer.");
    }
    
    require_once($autoloadPath);
    
    if (!class_exists('Dompdf\Dompdf')) {
        throw new Exception("DOMPDF n'est pas installé. Veuillez exécuter : composer require dompdf/dompdf");
    }
    
    return true;
}

// Fonction pour afficher les informations de débogage
function debugPaths() {
    echo "<h2>Débogage des chemins</h2>";
    echo "Dossier courant : " . __DIR__ . "<br>";
    
    $possiblePaths = [
        'Local' => __DIR__ . '/vendor/autoload.php',
        'Parent' => __DIR__ . '/../vendor/autoload.php',
        'Grand-parent' => __DIR__ . '/../../vendor/autoload.php',
        'Arrière-grand-parent' => __DIR__ . '/../../../vendor/autoload.php',
    ];
    
    foreach ($possiblePaths as $name => $path) {
        if (file_exists($path)) {
            echo "✅ $name : $path<br>";
        } else {
            echo "❌ $name : $path<br>";
        }
    }
    
    $autoloadPath = getAutoloadPath();
    if ($autoloadPath) {
        echo "<br>🎯 Autoloader trouvé : $autoloadPath<br>";
    } else {
        echo "<br>❌ Aucun autoloader trouvé<br>";
    }
}
?> 