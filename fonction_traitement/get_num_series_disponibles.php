<?php
session_start();
include('../db/connecting.php');
include('fonction.php');

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['nom_utilisateur'])) {
        throw new Exception("Session non valide");
    }

    $id_article = isset($_GET['id_article']) ? intval($_GET['id_article']) : 0;
    
    if ($id_article <= 0) {
        throw new Exception("ID d'article invalide");
    }

    // Récupérer les numéros de série disponibles pour cet article
    $sql = "SELECT NUMERO_SERIE 
            FROM num_serie 
            WHERE IDARTICLE = ? 
            AND statut = 'disponible'
            AND (ID_VENTE IS NULL OR ID_VENTE = '') 
            AND (IDvente_credit IS NULL OR IDvente_credit = '')
            ORDER BY NUMERO_SERIE";
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$id_article]);
    $numeros_serie = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($numeros_serie);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 