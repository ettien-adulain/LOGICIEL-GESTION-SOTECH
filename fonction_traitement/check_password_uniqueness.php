<?php
/**
 * Vérification de l'unicité du mot de passe
 * Ce fichier vérifie si un mot de passe est déjà utilisé par un autre utilisateur
 */

header('Content-Type: application/json');

try {
    include('db/connecting.php');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    $password = $_POST['password'] ?? '';
    
    if (empty($password)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    // Vérifier si le mot de passe existe déjà dans la base de données
    // Récupérer tous les mots de passe hashés et vérifier avec password_verify
    $sql = "SELECT MotDePasse FROM utilisateur";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $hashedPasswords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $exists = false;
    foreach ($hashedPasswords as $hashedPassword) {
        if (password_verify($password, $hashedPassword)) {
            $exists = true;
            break;
        }
    }
    
    echo json_encode(['exists' => $exists]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
