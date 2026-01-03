<?php
session_start();
include('../db/connecting.php');
include('fonction.php');

// Vérifier la session
if (!isset($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer le terme de recherche
$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    // Requête pour rechercher les articles par code ou nom
    $sql = "SELECT a.IDARTICLE, a.libelle, a.CodePersoArticle, a.PrixAchatHT, a.PrixVenteTTC, 
                   COALESCE(s.StockActuel, 0) as StockActuel, s.IDSTOCK
            FROM article a 
            LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE 
            WHERE (a.CodePersoArticle LIKE ? OR a.libelle LIKE ?) 
            ORDER BY 
                CASE 
                    WHEN a.CodePersoArticle = ? THEN 1
                    WHEN a.CodePersoArticle LIKE ? THEN 2
                    WHEN a.libelle LIKE ? THEN 3
                    ELSE 4
                END,
                a.libelle
            LIMIT 10";
    
    $stmt = $cnx->prepare($sql);
    $searchTerm = '%' . $query . '%';
    $exactTerm = $query;
    $stmt->execute([$searchTerm, $searchTerm, $exactTerm, $exactTerm . '%', $exactTerm . '%']);
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['IDARTICLE'],
            'code' => $row['CodePersoArticle'],
            'name' => $row['libelle'],
            'stock' => $row['StockActuel'],
            'prix_achat' => $row['PrixAchatHT'],
            'prix_vente' => $row['PrixVenteTTC'],
            'id_stock' => $row['IDSTOCK']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de recherche: ' . $e->getMessage()]);
}
?>
