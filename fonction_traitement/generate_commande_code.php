<?php
/**
 * Script dédié à la génération de codes de commande
 * Basé sur le système de génération des articles
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
    
    // Démarrer une transaction pour garantir l'unicité
    $cnx->beginTransaction();
    
    // Récupérer le nombre total de commandes existantes
    $stmt = $cnx->prepare("SELECT COUNT(*) as total FROM commande");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $commandeCount = $result['total'];
    
    // Récupérer le dernier code généré pour déterminer le prochain
    $stmt = $cnx->prepare("SELECT numero_commande FROM commande ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastCommande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Générer le prochain code
    $nextCode = '';
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    if ($lastCommande && !empty($lastCommande['numero_commande'])) {
        $lastCode = $lastCommande['numero_commande'];
        
        // Vérifier si le format est correct (lettres + chiffres)
        if (preg_match('/^([A-Z]+)(\d+)$/', $lastCode, $matches)) {
            $letterPart = $matches[1];
            $numberPart = intval($matches[2]);
            
            // Incrémenter le numéro
            $numberPart++;
            
            // Si le numéro dépasse 99999, passer à la lettre suivante
            if ($numberPart > 99999) {
                $letterPart = incrementLetterSequence($letterPart, $letters);
                $numberPart = 1;
            }
            
            $nextCode = $letterPart . str_pad($numberPart, 5, '0', STR_PAD_LEFT);
        } else {
            // Format non reconnu, générer un nouveau code
            $nextCode = 'BON00001';
        }
    } else {
        // Première commande, commencer par BON00001
        $nextCode = 'BON00001';
    }
    
    // Vérifier que le code généré n'existe pas déjà
    $stmt = $cnx->prepare("SELECT COUNT(*) as count FROM commande WHERE numero_commande = ?");
    $stmt->execute([$nextCode]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($exists > 0) {
        // Générer un code alternatif
        $counter = 1;
        do {
            $testCode = 'BON' . str_pad($counter, 5, '0', STR_PAD_LEFT);
            $stmt = $cnx->prepare("SELECT COUNT(*) as count FROM commande WHERE numero_commande = ?");
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
        'commandeCount' => $commandeCount + 1
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

/**
 * Fonction pour incrémenter une séquence de lettres
 * Exemple: A -> B, Z -> AA, AZ -> BA, etc.
 */
function incrementLetterSequence($letterSequence, $letters) {
    $letterArray = str_split($letterSequence);
    $carry = true;
    $index = count($letterArray) - 1;
    
    while ($carry && $index >= 0) {
        $currentPos = strpos($letters, $letterArray[$index]);
        if ($currentPos === false) {
            $currentPos = 0;
        }
        
        $nextPos = ($currentPos + 1) % strlen($letters);
        $letterArray[$index] = $letters[$nextPos];
        
        if ($nextPos == 0) {
            $carry = true;
        } else {
            $carry = false;
        }
        
        $index--;
    }
    
    // Si on a une retenue sur le premier caractère, ajouter une nouvelle lettre
    if ($carry) {
        array_unshift($letterArray, $letters[0]);
    }
    
    return implode('', $letterArray);
}
?>
