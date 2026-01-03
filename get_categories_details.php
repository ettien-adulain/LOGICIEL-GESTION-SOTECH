<?php
session_start();
include('db/connecting.php');

if (!isset($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

$idInventaire = isset($_GET['IDINVENTAIRE']) ? intval($_GET['IDINVENTAIRE']) : 0;

if ($idInventaire <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID d\'inventaire invalide']);
    exit();
}

try {
    // Récupération des détails par catégorie
    $query = "
        SELECT 
            il.categorie,
            COUNT(il.id) as nombre_articles,
            SUM(il.qte_theorique * COALESCE(a.PrixAchatHT, 0)) as valeur_theorique,
            SUM(COALESCE(il.qte_physique, 0) * COALESCE(a.PrixAchatHT, 0)) as valeur_physique,
            SUM((il.qte_theorique - COALESCE(il.qte_physique, 0)) * COALESCE(a.PrixAchatHT, 0)) as valeur_ecart,
            SUM(CASE WHEN il.ecart != 0 THEN 1 ELSE 0 END) as articles_avec_ecart
        FROM inventaire_ligne il
        LEFT JOIN article a ON il.id_article = a.IDARTICLE
        WHERE il.id_inventaire = ?
        GROUP BY il.categorie
        ORDER BY il.categorie
    ";
    
    $stmt = $cnx->prepare($query);
    $stmt->execute([$idInventaire]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatage des données pour l'affichage
    $formatted_categories = [];
    foreach ($categories as $cat) {
        $formatted_categories[] = [
            'nom' => $cat['categorie'],
            'nombre_articles' => intval($cat['nombre_articles']),
            'valeur_theorique' => floatval($cat['valeur_theorique']),
            'valeur_physique' => floatval($cat['valeur_physique']),
            'valeur_ecart' => floatval($cat['valeur_ecart']),
            'articles_avec_ecart' => intval($cat['articles_avec_ecart'])
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'categories' => $formatted_categories
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
    ]);
}
?> 