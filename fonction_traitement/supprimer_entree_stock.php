<?php
session_start();
include('../db/connecting.php');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['nom_utilisateur'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier que l'ID est fourni
if (!isset($_POST['id']) || empty($_POST['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de l\'entrée non fourni']);
    exit;
}

$id = intval($_POST['id']);

try {
    $cnx->beginTransaction();
    
    // 1. Récupérer les informations de l'entrée pour les logs
    $stmt = $cnx->prepare("SELECT * FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
    $stmt->execute([$id]);
    $entree = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entree) {
        throw new Exception("Entrée en stock non trouvée");
    }
    
    // 2. Récupérer tous les numéros de série associés à cette entrée
    $stmt = $cnx->prepare("
        SELECT ns.*, e.Numero_bon 
        FROM num_serie ns
        JOIN entree_en_stock e ON ns.ID_ENTRER_STOCK = e.IDENTREE_STOCK
        WHERE e.IDENTREE_STOCK = ?
    ");
    $stmt->execute([$id]);
    $numerosSerie = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Supprimer les numéros de série associés
    if (!empty($numerosSerie)) {
        $stmt = $cnx->prepare("DELETE FROM num_serie WHERE ID_ENTRER_STOCK = ?");
        $stmt->execute([$id]);
        
        // Log de suppression des numéros de série
        foreach ($numerosSerie as $numero) {
            $stmt = $cnx->prepare("
                INSERT INTO log_suppression 
                (table_source, id_source, donnees_supprimees, utilisateur, date_suppression, raison)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                'num_serie',
                $numero['IDNUM_SERIE'],
                json_encode($numero),
                $_SESSION['nom_utilisateur'],
                'Suppression lors de la suppression de l\'entrée en stock N° ' . $entree['Numero_bon']
            ]);
        }
    }
    
    // 4. Supprimer l'entrée en stock
    $stmt = $cnx->prepare("DELETE FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
    $stmt->execute([$id]);
    
    // 5. Log de suppression de l'entrée
    $stmt = $cnx->prepare("
        INSERT INTO log_suppression 
        (table_source, id_source, donnees_supprimees, utilisateur, date_suppression, raison)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([
        'entree_en_stock',
        $id,
        json_encode($entree),
        $_SESSION['nom_utilisateur'],
        'Suppression manuelle par l\'utilisateur'
    ]);
    
    // 6. Mettre à jour les stocks si nécessaire
    // Recalculer le stock pour l'article concerné
    if (!empty($numerosSerie)) {
        $idArticle = $numerosSerie[0]['IDARTICLE'];
        
        // Compter les numéros de série disponibles pour cet article (non vendus et non liés à des entrées supprimées)
        $stmt = $cnx->prepare("
            SELECT COUNT(*) as nb_series
            FROM num_serie 
            WHERE IDARTICLE = ? 
            AND statut = 'disponible'
            AND (ID_VENTE IS NULL OR ID_VENTE = '')
            AND (IDvente_credit IS NULL OR IDvente_credit = '')
            AND ID_ENTRER_STOCK IS NOT NULL
        ");
        $stmt->execute([$idArticle]);
        $nbSeries = $stmt->fetch(PDO::FETCH_ASSOC)['nb_series'];
        
        // Mettre à jour le stock
        $stmt = $cnx->prepare("
            UPDATE stock 
            SET StockActuel = ?, DateMod = NOW()
            WHERE IDARTICLE = ?
        ");
        $stmt->execute([$nbSeries, $idArticle]);
    }
    
    $cnx->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Entrée en stock supprimée avec succès',
        'numerosSupprimes' => count($numerosSerie)
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