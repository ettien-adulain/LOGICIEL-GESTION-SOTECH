<?php
session_start();
include('../db/connecting.php');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['nom_utilisateur'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

try {
    $cnx->beginTransaction();
    
    // 1. Compter les entrées et numéros de série avant suppression
    $stmt = $cnx->query("SELECT COUNT(*) as nb_entrees FROM entree_en_stock");
    $nbEntrees = $stmt->fetch(PDO::FETCH_ASSOC)['nb_entrees'];
    
    $stmt = $cnx->query("SELECT COUNT(*) as nb_numeros FROM num_serie");
    $nbNumeros = $stmt->fetch(PDO::FETCH_ASSOC)['nb_numeros'];
    
    // 2. Récupérer toutes les entrées pour les logs
    $stmt = $cnx->query("SELECT * FROM entree_en_stock");
    $entrees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Récupérer tous les numéros de série pour les logs
    $stmt = $cnx->query("SELECT * FROM num_serie");
    $numerosSerie = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Supprimer tous les numéros de série liés aux entrées en stock
    $cnx->query("DELETE FROM num_serie WHERE ID_ENTRER_STOCK IS NOT NULL");
    
    // 5. Supprimer toutes les entrées en stock
    $cnx->query("DELETE FROM entree_en_stock");
    
    // 6. Remettre tous les stocks à zéro (puisque toutes les entrées sont supprimées)
    $cnx->query("UPDATE stock SET StockActuel = 0, DateMod = NOW()");
    
    // 7. Log de suppression massive
    $stmt = $cnx->prepare("
        INSERT INTO log_suppression 
        (table_source, id_source, donnees_supprimees, utilisateur, date_suppression, raison)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    
    // Log pour les entrées
    $stmt->execute([
        'entree_en_stock',
        0,
        json_encode($entrees),
        $_SESSION['nom_utilisateur'],
        'Suppression massive de toutes les entrées en stock (' . $nbEntrees . ' entrées)'
    ]);
    
    // Log pour les numéros de série supprimés
    $numerosSupprimes = array_filter($numerosSerie, function($ns) {
        return !is_null($ns['ID_ENTRER_STOCK']);
    });
    
    $stmt->execute([
        'num_serie',
        0,
        json_encode($numerosSupprimes),
        $_SESSION['nom_utilisateur'],
        'Suppression massive des numéros de série liés aux entrées en stock (' . count($numerosSupprimes) . ' numéros)'
    ]);
    
    $cnx->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Toutes les entrées en stock ont été supprimées avec succès',
        'entreesSupprimees' => $nbEntrees,
        'numerosSupprimes' => count($numerosSupprimes)
    ]);
    
} catch (Exception $e) {
    $cnx->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
    ]);
}
?> 