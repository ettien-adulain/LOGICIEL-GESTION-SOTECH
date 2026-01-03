<?php
/**
 * Script dédié à la génération de codes d'articles
 * Évite de modifier le fichier request.php critique
 */

// Configuration des erreurs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Définir le header JSON dès le début
header('Content-Type: application/json');

session_start();

// Vérifier la session
if (!isset($_SESSION['id_utilisateur'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Session expirée. Veuillez vous reconnecter.'
    ]);
    exit();
}

try {
    // Inclure la connexion à la base de données
    include(__DIR__ . '/../db/connecting.php');
    
    // 🔒 Démarrer une transaction pour garantir l'unicité
    $cnx->beginTransaction();
    
    // 🔒 Récupérer le nombre total d'articles existants avec verrou
    $stmt = $cnx->prepare("SELECT COUNT(*) as total FROM article FOR UPDATE");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $articleCount = $result['total'];
    
    // 🔒 Récupérer le dernier code généré pour déterminer le prochain avec verrou
    $stmt = $cnx->prepare("SELECT CodePersoArticle FROM article ORDER BY IDARTICLE DESC LIMIT 1 FOR UPDATE");
    $stmt->execute();
    $lastArticle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Générer le prochain code
    $nextCode = '';
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    if ($lastArticle && !empty($lastArticle['CodePersoArticle'])) {
        // Analyser le dernier code pour déterminer le suivant
        $lastCode = $lastArticle['CodePersoArticle'];
        
        // Extraire la partie avant le dernier tiret
        $parts = explode('-', $lastCode);
        if (count($parts) >= 2) {
            $prefix = $parts[0]; // A00001
            $count = (int)$parts[1]; // 1
            
            // Extraire les lettres et le numéro
            preg_match('/^([A-Z]*)(\d+)$/', $prefix, $matches);
            if (count($matches) >= 3) {
                $letterPart = $matches[1]; // A
                $numberPart = (int)$matches[2]; // 1
                
                // Incrémenter le numéro
                $numberPart++;
                
                // Si le numéro dépasse 99999, passer à la lettre suivante
                if ($numberPart > 99999) {
                    $numberPart = 1;
                    if (empty($letterPart)) {
                        $letterPart = 'A';
                    } else {
                        // Incrémenter la lettre
                        $lastChar = substr($letterPart, -1);
                        $lastCharIndex = strpos($letters, $lastChar);
                        if ($lastCharIndex !== false && $lastCharIndex < strlen($letters) - 1) {
                            $letterPart = substr($letterPart, 0, -1) . $letters[$lastCharIndex + 1];
                        } else {
                            // Passer à la lettre suivante (AA, AB, etc.)
                            $letterPart = 'A' . $letters[0];
                        }
                    }
                }
                
                $nextCode = $letterPart . str_pad($numberPart, 5, '0', STR_PAD_LEFT) . '-' . ($articleCount + 1);
            } else {
                // Format non reconnu, générer un nouveau code
                $nextCode = 'A00001-' . ($articleCount + 1);
            }
        } else {
            // Format non reconnu, générer un nouveau code
            $nextCode = 'A00001-' . ($articleCount + 1);
        }
    } else {
        // Premier article, commencer par A00001-1
        $nextCode = 'A00001-' . ($articleCount + 1);
    }
    
    // 🔒 Vérifier que le code généré n'existe pas déjà avec verrou
    $stmt = $cnx->prepare("SELECT COUNT(*) as count FROM article WHERE CodePersoArticle = ? FOR UPDATE");
    $stmt->execute([$nextCode]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($exists > 0) {
        // 🔒 Générer un code alternatif avec vérification sécurisée
        $counter = 1;
        do {
            $testCode = 'A' . str_pad($counter, 5, '0', STR_PAD_LEFT) . '-' . ($articleCount + 1);
            $stmt = $cnx->prepare("SELECT COUNT(*) as count FROM article WHERE CodePersoArticle = ? FOR UPDATE");
            $stmt->execute([$testCode]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $counter++;
        } while ($exists > 0 && $counter < 100000);
        
        $nextCode = $testCode;
    }
    
    // Valider la transaction
    $cnx->commit();
    
    // Retourner la réponse JSON
    echo json_encode([
        'success' => true,
        'code' => $nextCode,
        'articleCount' => $articleCount + 1
    ]);
    
} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction
    if (isset($cnx)) {
        $cnx->rollBack();
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la génération du code: ' . $e->getMessage()
    ]);
}
?>
