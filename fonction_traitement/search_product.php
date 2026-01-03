<?php
try {
    include('../db/connecting.php');
    include('fonction.php');
    session_start();

    if (!isset($_SESSION['nom_utilisateur'])) {
        echo json_encode(['error' => 'Session expirée']);
        exit();
    }

    if (!isset($_POST['Code'])) {
        echo json_encode(['error' => 'Code manquant']);
        exit();
    }

    $code = $_POST['Code'];
    $values = [$code];
    $columns1 = ['CodePersoArticle'];
    $columns2 = ['libelle'];
    $tableName = "article";
    
    $article_resultat = verifier_element($tableName, $columns1, $values, '') ?: verifier_element($tableName, $columns2, $values, '');
    
    if (!$article_resultat) {
        echo json_encode(['error' => 'Aucun article trouvé avec ce code ou libellé.']);
        exit();
    }

    $id_article_stock = $article_resultat['IDARTICLE'];
    $article_stock = verifier_element('stock', ['IDARTICLE'], [$id_article_stock], '');
    
    $response = [
        'IDARTICLE' => $article_resultat['IDARTICLE'],
        'IDSTOCK' => $article_stock ? $article_stock['IDSTOCK'] : null,
        'libelle' => $article_resultat['libelle'],
        'CodePersoArticle' => $article_resultat['CodePersoArticle'],
        'PrixAchatHT' => $article_resultat['PrixAchatHT'],
        'PrixVenteTTC' => $article_resultat['PrixVenteTTC'],
        'StockActuel' => $article_stock ? $article_stock['StockActuel'] : 0
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Une erreur est survenue lors de la recherche.']);
}
?> 