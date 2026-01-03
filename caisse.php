<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'fonction_traitement/fonction.php';
check_access(); // Protection automatique selon $DROITS_PAGES

try {
    include('db/connecting.php');
    include('fonction_traitement/fonction.php');

    $article = null;
    $erreur = null;
    $mode_paiement = selection_element('mode_reglement');
    
    // Trier les modes de règlement par date de création (du plus ancien au plus récent)
    usort($mode_paiement, function($a, $b) {
        return strtotime($a['DateIns']) - strtotime($b['DateIns']);
    });

    if (isset($_POST['recherche'])) {
        $code = $_POST['CodePersoArticle'];
        $values = [$code];
        $columns1 = ['CodePersoArticle'];
        $columns2 = ['libelle'];
        $tableName = "article";

        $article = verifier_element($tableName, $columns1, $values, '') ?: verifier_element($tableName, $columns2, $values, '');

        if ($article) {
            // Vérification du statut désactivé
            if (isset($article['desactiver']) && $article['desactiver'] === 'oui') {
                $_SESSION['error'] = "Cet article est inactif et ne peut pas être vendu.";
                $article = null;
            } else {
                // Récupérer tous les numéros de série disponibles pour cet article
                $stmt = $cnx->prepare("
                    SELECT NUMERO_SERIE 
                    FROM num_serie 
                    WHERE IDARTICLE = ? 
                    AND statut = 'disponible'
                    AND (ID_VENTE IS NULL OR ID_VENTE = '') 
                    AND (IDvente_credit IS NULL OR IDvente_credit = '')
                ");
                $stmt->execute([$article['IDARTICLE']]);
                $numerosSerie = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $article['NUMEROS_SERIE'] = $numerosSerie;
            }
        } else {
            $_SESSION['error'] = "Aucun article trouvé avec ce code ou libellé.";
        }
    }
    if (isset($_POST['ajouter_panier'])) {
        if (!isset($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
        }
        $numeroSerie = $_POST['numeroSerie'];
        $id_article = $_POST['id_article'];
        $libelle = $_POST['libelle'];
        $prixVenteUnitaire = $_POST['prixVenteUnitaire'];
        $quantite = 1;

        // Vérifier si le numéro de série existe déjà dans le panier
        $numeroSerieExiste = false;
        foreach ($_SESSION['panier'] as $articles) {
            foreach ($articles as $numSerie => $details) {
                if ($numSerie === $numeroSerie) {
                    $numeroSerieExiste = true;
                    break 2;
                }
            }
        }

        if ($numeroSerieExiste) {
            $_SESSION['error'] = "Ce numéro de série est déjà dans le panier.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }

        $num_serie = verifier_element('num_serie', ['NUMERO_SERIE'], [$numeroSerie], '');
        $article_num_serie = verifier_element('num_serie', ['IDARTICLE'], [$numeroSerie], '');

        if ($num_serie) {
            if (is_null($num_serie['NumeroVente']) && is_null($num_serie['ID_VENTE']) && $num_serie['IDARTICLE'] == $id_article) {
                $_SESSION['panier'][$id_article][$numeroSerie] = [
                    'id_article' => $id_article,
                    'libelle' => $libelle,
                    'prixVenteUnitaire' => $prixVenteUnitaire,
                    'quantite' => $quantite
                ];
            } else {
                $_SESSION['error'] = "Le numéro de série existe déjà ou n'appartient pas à cet appareil.";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $_SESSION['error'] = "Le numéro de série n'existe pas.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    if (isset($_POST['supprimer_panier'])) {
        $id_article = $_POST['id_article'];
        $numeroSerie = $_POST['numeroSerie'];
        if (isset($_SESSION['panier'][$id_article][$numeroSerie])) {
            unset($_SESSION['panier'][$id_article][$numeroSerie]);
            if (empty($_SESSION['panier'][$id_article])) {
                unset($_SESSION['panier'][$id_article]);
            }
        }
    }

    if (isset($_POST['vider_panier'])) {
        unset($_SESSION['panier']);
    }
} catch (\Throwable $th) {
    // Gestion des erreurs et redirection
    $_SESSION['error'] = 'Erreur lors de la récupération des articles : ' . htmlspecialchars($th->getMessage(), ENT_QUOTES, 'UTF-8');
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer);
    exit();
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    <title>Interface de Caisse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <style>
        /* ===== INTERFACE CAISSE MODERNE PLEIN ÉCRAN ===== */
        
        /* Reset et base */
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        /* Layout principal - Tablette centrée */
        .caisse-container {
            width: 100%;
            max-width: 1400px;
            height: 90vh;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 0;
            overflow: hidden;
            position: relative;
        }
        
        /* Header */
        .caisse-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .caisse-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .caisse-header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Contenu principal */
        .caisse-main {
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 10px;
            padding: 15px 15px 0px 15px;
            overflow-y: auto;
            overflow-x: hidden;
            height: calc(100% - 80px);
            -webkit-overflow-scrolling: touch;
        }
        
        /* Zone de recherche en haut */
        .search-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e9ecef;
        }
        
        .search-section h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .search-input-container {
            position: relative;
        }
        
        .search-input {
            padding: 10px 15px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
        }
        
        .search-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background: #c82333;
        }
        
        /* Détails produit */
        .product-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .product-field {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            background: #f8f9fa;
            font-size: 0.95rem;
            font-weight: 500;
            color: #495057;
        }
        
        /* Formulaire d'ajout */
        .add-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 8px;
            align-items: end;
        }
        
        .quantity-field {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            background: #f8f9fa;
            color: #495057;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .serial-select {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .serial-select:focus {
            outline: none;
            border-color: #dc3545;
        }
        
        .add-btn {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-btn:hover {
            background: #218838;
        }
        
        /* Zone centrale - Tableau panier */
        .cart-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e9ecef;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
            height: 100%;
        }
        
        .cart-section h3 {
            margin: 0 0 15px 0;
            color: #dc3545;
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        /* Tableau panier avec scroll indépendant */
        .cart-table-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
            background: white;
            position: relative;
            min-height: 0;
            max-height: 500px;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin: 0;
            background: white;
            table-layout: fixed;
        }
        
        .cart-table th {
            background: #dc3545;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid #fff;
            height: 40px;
        }
        
        .cart-table td {
            padding: 8px 8px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            font-size: 0.9rem;
            font-weight: 500;
            height: 36px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .cart-table tbody tr {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s ease;
        }
        
        .cart-table tbody tr:hover {
            background: #f0f8ff;
            border-bottom: 1px solid #b3d9ff;
        }
        
        .cart-table tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        .cart-table tbody tr:nth-child(odd) {
            background: white;
        }
        
        .cart-table tbody tr:nth-child(even):hover {
            background: #f0f8ff;
        }
        
        .cart-table tbody tr:nth-child(odd):hover {
            background: #f0f8ff;
        }
        
        /* Colonnes avec largeurs fixes */
        .cart-table th:nth-child(1),
        .cart-table td:nth-child(1) {
            width: 35%;
        }
        
        .cart-table th:nth-child(2),
        .cart-table td:nth-child(2) {
            width: 10%;
            text-align: center;
        }
        
        .cart-table th:nth-child(3),
        .cart-table td:nth-child(3) {
            width: 20%;
            text-align: right;
        }
        
        .cart-table th:nth-child(4),
        .cart-table td:nth-child(4) {
            width: 25%;
        }
        
        .cart-table th:nth-child(5),
        .cart-table td:nth-child(5) {
            width: 10%;
            text-align: center;
        }
        
        /* Message panier vide */
        .cart-table .empty-cart {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 40px 20px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            margin: 10px;
        }
        
        /* Indicateur panier vide mobile */
        @media (max-width: 768px) {
            .cart-table .empty-cart {
                padding: 30px 15px;
                font-size: 0.9rem;
                border: 2px dashed #dc3545;
                background: #fff5f5;
                color: #dc3545;
                font-weight: 600;
            }
        }
        
        .delete-btn {
            padding: 4px 8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .clear-cart-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .clear-cart-btn:hover {
            background: #5a6268;
        }
        
        /* Zone basse - 3 sections horizontales */
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2px;
            margin-top: auto;
            align-self: end;
        }
        
        /* Section 1: Totaux */
        .totaux-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 6px;
            padding: 8px;
            border: 1px solid #2196f3;
            box-shadow: 0 1px 4px rgba(33, 150, 243, 0.15);
        }
        
        .totaux-section h3 {
            margin: 0 0 8px 0;
            color: #1976d2;
            font-size: 1rem;
            font-weight: 700;
            text-align: center;
        }
        
        /* Section 2: Encaissement */
        .encaissement-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border-radius: 6px;
            padding: 8px;
            border: 1px solid #4caf50;
            box-shadow: 0 1px 4px rgba(76, 175, 80, 0.15);
        }
        
        .encaissement-section h3 {
            margin: 0 0 8px 0;
            color: #388e3c;
            font-size: 1rem;
            font-weight: 700;
            text-align: center;
        }
        
        /* Section 3: Info Client */
        .client-section {
            background: linear-gradient(135deg, #fff3e0 0%, #ffcc80 100%);
            border-radius: 6px;
            padding: 8px;
            border: 1px solid #ff9800;
            box-shadow: 0 1px 4px rgba(255, 152, 0, 0.15);
            overflow: visible;
        }
        
        .client-section h3 {
            margin: 0 0 8px 0;
            color: #f57c00;
            font-size: 1rem;
            font-weight: 700;
            text-align: center;
        }
        
        /* Styles communs pour les sections */
        .section-summary {
            display: grid;
            gap: 6px;
            margin-bottom: 8px;
        }
        
        .section-row {
            display: grid;
            grid-template-columns: 1fr auto;
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            font-size: 0.85rem;
            align-items: center;
        }
        
        .section-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .section-label {
            color: #333;
            font-weight: 600;
        }
        
        .section-value {
            font-weight: 700;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        
        /* Carrés colorés pour les totaux */
        .total-box {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            text-align: center;
            min-width: 80px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 2px solid;
        }
        
        .total-brut {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
            border-color: #ff3742;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .total-remise {
            background: linear-gradient(135deg, #2ed573 0%, #1dd65a 100%);
            color: white;
            border-color: #1dd65a;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .monnaie {
            background: linear-gradient(135deg, #ffa502 0%, #ff9500 100%);
            color: white;
            border-color: #ff9500;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .section-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
        }
        
        /* Styles pour les lignes horizontales */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            align-items: end;
        }
        
        .form-row-single {
            display: grid;
            gap: 2px;
        }
        
        .pay-btn {
            padding: 6px 10px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pay-btn:hover {
            background: #45a049;
            transform: translateY(-1px);
        }
        
        .pay-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .cancel-btn {
            padding: 6px 10px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .cancel-btn:hover {
            background: #5a6268;
        }
        
        
        /* Styles des formulaires pour les sections */
        .section-form {
            display: grid;
            gap: 3px;
        }
        
        .form-group {
            display: grid;
            gap: 1px;
            position: relative;
        }
        
        .form-group label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #333;
        }
        
        .form-control {
            padding: 4px 6px;
            border: 1px solid rgba(0,0,0,0.2);
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.9);
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2196f3;
            box-shadow: 0 0 0 1px rgba(33, 150, 243, 0.2);
        }
        
        .form-control:disabled {
            background: rgba(255,255,255,0.6);
            color: #6c757d;
        }
        
        .mode-payment-group {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2px;
            align-items: end;
        }
        
        .multi-payment-btn {
            padding: 4px 6px;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .multi-payment-btn:hover {
            background: #f57c00;
        }
        
        /* Alerts et messages */
        .alert {
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 0.8rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stock-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 0.8rem;
        }
        
        .stock-warning h4 {
            margin: 0 0 8px 0;
            color: #856404;
            font-size: 0.9rem;
        }
        
        /* Liste des produits (autocomplete) */
        .produit-container {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 150px;
            overflow-y: auto;
            margin-top: 2px;
        }
        
        /* Suggestions de recherche d'articles */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 1001;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 2px;
            display: none;
        }
        
        .search-suggestions.show {
            display: block;
        }
        
        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .suggestion-item:hover {
            background: #f8f9fa;
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        
        .suggestion-item.active {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        
        .suggestion-info {
            flex: 1;
        }
        
        .suggestion-code {
            font-weight: 600;
            color: #dc3545;
            font-size: 0.9rem;
        }
        
        .suggestion-libelle {
            color: #333;
            font-size: 0.85rem;
            margin-top: 2px;
        }
        
        .suggestion-price {
            color: #28a745;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .suggestion-stock {
            color: #6c757d;
            font-size: 0.8rem;
            margin-top: 2px;
        }
        
        .suggestion-stock.low {
            color: #dc3545;
            font-weight: 600;
        }
        
        /* Indicateur de scanner de code-barres */
        .barcode-indicator {
            position: absolute;
            top: -25px;
            right: 0;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        
        .produit-container .list-group-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s ease;
            font-size: 0.8rem;
        }
        
        .produit-container .list-group-item:hover {
            background: #f8f9fa;
        }
        
        .produit-container .list-group-item:last-child {
            border-bottom: none;
        }
        
        /* Scrollbar personnalisée */
        .produit-container::-webkit-scrollbar,
        .cart-table-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .produit-container::-webkit-scrollbar-track,
        .cart-table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .produit-container::-webkit-scrollbar-thumb,
        .cart-table-container::-webkit-scrollbar-thumb {
            background: #dc3545;
            border-radius: 4px;
        }
        
        .produit-container::-webkit-scrollbar-thumb:hover,
        .cart-table-container::-webkit-scrollbar-thumb:hover {
            background: #c82333;
        }
        
        /* SweetAlert2 styles */
        .swal2-popup {
            font-size: 1.2rem !important;
        }

        .swal2-title {
            font-size: 1.5rem !important;
        }

        .swal2-html-container {
            font-size: 1.1rem !important;
        }

        .swal2-confirm, .swal2-cancel, .swal2-deny {
            font-size: 1.1rem !important;
            padding: 0.8rem 2rem !important;
        }

        /* Styles pour le multi-paiement */
        .payment-summary {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }

        .payment-summary .badge {
            font-size: 0.9rem;
            padding: 0.5rem 0.8rem;
        }

        .table th {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .caisse-container {
                max-width: 95%;
                height: 95vh;
            }
            
            .bottom-section {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 3px;
            }
        }
        
        @media (max-width: 1200px) {
            .bottom-section {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto auto;
                gap: 3px;
            }
            
            .client-section {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 992px) {
            .caisse-container {
                max-width: 98%;
                height: 98vh;
            }
            
            .bottom-section {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                gap: 3px;
            }
            
            .caisse-header h1 {
                font-size: 1.4rem;
            }
            
            /* Amélioration pour tablettes */
            .search-form {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .product-details {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            
            .add-form {
                grid-template-columns: 1fr 1fr auto;
                gap: 8px;
            }
            
            .cart-table th,
            .cart-table td {
                font-size: 0.8rem;
                padding: 6px 4px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 5px;
                overflow-x: hidden;
            }
            
            .caisse-container {
                max-width: 100%;
                height: 100vh;
                border-radius: 0;
                overflow: hidden;
            }
            
            .caisse-main {
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
                padding: 10px;
                gap: 15px;
                display: flex;
                flex-direction: column;
            }
            
            .caisse-header {
                padding: 10px 15px;
                border-radius: 0;
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .caisse-header h1 {
                font-size: 1.2rem;
            }
            
            .caisse-main {
                padding: 10px;
                gap: 10px;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }
            
            .search-section {
                padding: 15px;
                flex-shrink: 0;
            }
            
            .cart-section {
                padding: 15px;
                flex: 1;
                min-height: 300px;
                display: flex;
                flex-direction: column;
            }
            
            .search-section h3,
            .cart-section h3 {
                font-size: 1.1rem;
                margin-bottom: 10px;
            }
            
            /* Formulaire de recherche mobile */
            .search-form {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .search-input {
                font-size: 16px; /* Évite le zoom sur iOS */
                padding: 12px 15px;
            }
            
            .search-btn {
                padding: 12px 20px;
                font-size: 1rem;
            }
            
            /* Détails produit mobile */
            .product-details {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .product-field {
                font-size: 0.9rem;
                padding: 10px 12px;
            }
            
            /* Formulaire d'ajout mobile */
            .add-form {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .serial-select {
                font-size: 16px; /* Évite le zoom sur iOS */
                padding: 10px 12px;
            }
            
            .add-btn {
                padding: 10px 16px;
                font-size: 1rem;
            }
            
            /* Tableau panier mobile */
            .cart-section {
                min-height: 250px;
                margin-bottom: 15px;
            }
            
            .cart-table-container {
                max-height: 250px;
                min-height: 150px;
                overflow-x: auto;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                border: 2px solid #dc3545;
                background: #fff;
                position: relative;
            }
            
            /* Indicateur de scroll pour mobile */
            .cart-table-container::after {
                content: "👆 Faites défiler pour voir plus";
                position: absolute;
                bottom: 5px;
                right: 5px;
                background: rgba(220, 53, 69, 0.9);
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 0.7rem;
                opacity: 0.8;
                pointer-events: none;
            }
            
            .cart-table {
                min-width: 600px; /* Force la largeur minimale */
                font-size: 0.8rem;
            }
            
            .cart-table th,
            .cart-table td {
                padding: 8px 6px;
                white-space: nowrap;
            }
            
            /* Colonnes du tableau mobile */
            .cart-table th:nth-child(1),
            .cart-table td:nth-child(1) {
                width: 40%;
                min-width: 120px;
            }
            
            .cart-table th:nth-child(2),
            .cart-table td:nth-child(2) {
                width: 8%;
                min-width: 40px;
            }
            
            .cart-table th:nth-child(3),
            .cart-table td:nth-child(3) {
                width: 20%;
                min-width: 80px;
            }
            
            .cart-table th:nth-child(4),
            .cart-table td:nth-child(4) {
                width: 20%;
                min-width: 100px;
            }
            
            .cart-table th:nth-child(5),
            .cart-table td:nth-child(5) {
                width: 12%;
                min-width: 60px;
            }
            
            .delete-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .clear-cart-btn {
                padding: 10px 16px;
                font-size: 0.9rem;
                width: 100%;
                margin-top: 10px;
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 4px;
                font-weight: 600;
            }
            
            /* Sections du bas mobile */
            .bottom-section {
                grid-template-columns: 1fr;
                gap: 8px;
                margin-top: 15px;
                flex-shrink: 0;
            }
            
            .totaux-section,
            .encaissement-section,
            .client-section {
                padding: 12px;
            }
            
            .totaux-section h3,
            .encaissement-section h3,
            .client-section h3 {
                font-size: 1rem;
                margin-bottom: 8px;
            }
            
            /* Formulaires mobile */
            .form-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .form-control {
                font-size: 16px; /* Évite le zoom sur iOS */
                padding: 8px 10px;
            }
            
            .form-group label {
                font-size: 0.85rem;
            }
            
            .section-actions {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            
            .pay-btn,
            .cancel-btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
            
            /* Mode paiement mobile */
            .mode-payment-group {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .multi-payment-btn {
                padding: 8px 12px;
                font-size: 0.8rem;
                width: 100%;
            }
        }
        
        /* Très petits écrans (moins de 480px) */
        @media (max-width: 480px) {
            body {
                padding: 2px;
                overflow-x: hidden;
            }
            
            .caisse-container {
                height: 100vh;
                overflow: hidden;
            }
            
            .caisse-main {
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
                padding: 8px;
                gap: 8px;
            }
            
            .caisse-header {
                padding: 8px 10px;
            }
            
            .caisse-header h1 {
                font-size: 1rem;
            }
            
            .caisse-main {
                padding: 8px;
                gap: 8px;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }
            
            .search-section {
                padding: 12px;
                flex-shrink: 0;
            }
            
            .cart-section {
                padding: 12px;
                flex: 1;
                min-height: 250px;
                display: flex;
                flex-direction: column;
            }
            
            .search-section h3,
            .cart-section h3 {
                font-size: 1rem;
            }
            
            .search-input,
            .serial-select,
            .form-control {
                font-size: 16px;
                padding: 10px;
            }
            
            .search-btn,
            .add-btn,
            .pay-btn,
            .cancel-btn {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .cart-table {
                font-size: 0.75rem;
            }
            
            .cart-table th,
            .cart-table td {
                padding: 6px 4px;
            }
            
            .totaux-section,
            .encaissement-section,
            .client-section {
                padding: 10px;
            }
            
            .section-row {
                font-size: 0.8rem;
            }
            
            .total-box {
                font-size: 0.8rem;
                padding: 6px 10px;
                min-width: 70px;
            }
        }
        
        /* Orientation paysage sur mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .caisse-container {
                height: 100vh;
                overflow: hidden;
            }
            
            .caisse-main {
                grid-template-rows: auto 1fr auto;
                gap: 8px;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }
            
            .search-section {
                padding: 10px;
            }
            
            .cart-table-container {
                max-height: 200px;
            }
            
            .bottom-section {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 5px;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .search-section, .cart-section, .client-section, .payment-section {
            animation: fadeIn 0.3s ease-out;
        }
        
        /* Focus visible pour l'accessibilité */
        .form-control:focus,
        .search-btn:focus,
        .add-btn:focus,
        .pay-btn:focus {
            outline: 2px solid #dc3545;
            outline-offset: 1px;
        }
        
        /* Améliorations tactiles pour mobile */
        @media (hover: none) and (pointer: coarse) {
            .search-btn,
            .add-btn,
            .pay-btn,
            .cancel-btn,
            .delete-btn,
            .clear-cart-btn,
            .multi-payment-btn {
                min-height: 44px; /* Taille minimale recommandée pour le tactile */
                min-width: 44px;
                touch-action: manipulation;
            }
            
            .form-control,
            .search-input,
            .serial-select {
                min-height: 44px;
                touch-action: manipulation;
            }
            
            .cart-table tbody tr {
                min-height: 44px;
            }
            
            .suggestion-item {
                min-height: 44px;
                padding: 12px 15px;
            }
        }
        
        /* Amélioration du scroll tactile */
        .cart-table-container,
        .search-suggestions,
        .produit-container {
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }
        
        /* Prévention du zoom sur les inputs */
        input[type="text"],
        input[type="number"],
        input[type="email"],
        select,
        textarea {
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            input[type="text"],
            input[type="number"],
            input[type="email"],
            select,
            textarea {
                font-size: 16px !important;
            }
        }
        
        /* Messages d'erreur et validation */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 8px 12px;
            border-radius: 4px;
            margin: 5px 0;
            font-size: 0.85rem;
            font-weight: 600;
            display: none;
        }
        
        .error-message.show {
            display: block;
            animation: shake 0.5s ease-in-out;
        }
        
        .field-error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
        }
        
        .field-success {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2) !important;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
            font-weight: bold;
        }
        
        .validation-summary {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: none;
        }
        
        .validation-summary.show {
            display: block;
        }
        
        .validation-summary h4 {
            margin: 0 0 10px 0;
            color: #856404;
            font-size: 1rem;
            font-weight: 700;
        }
        
        .validation-summary ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .validation-summary li {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        /* Animation de secousse pour les erreurs */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Bouton désactivé après clic */
        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Indicateur de chargement */
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 10px;
            color: #007bff;
            font-weight: 600;
        }
        
        .loading-indicator.show {
            display: block;
        }
        
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body id="caisse">
<?php include('includes/user_indicator.php'); ?>
<?php include('includes/navigation_buttons.php'); ?>
    <div class="caisse-container">
        <!-- Header -->
        <header class="caisse-header">
            <h1><i class="fas fa-cash-register"></i> Interface de Caisse</h1>
            <div class="">
   
            </div>
    </header>

        <!-- Contenu principal -->
        <main class="caisse-main">
            <!-- Zone de recherche en haut -->
            <div class="search-section">
                <h3><i class="fas fa-search"></i> Recherche d'Article</h3>
                
        <?php if (isset($_SESSION['stock_insuffisant'])): ?>
        <div class="stock-warning">
            <h4>⚠️ ATTENTION - Stock Insuffisant</h4>
            <ul>
                <?php foreach ($_SESSION['stock_insuffisant'] as $article): ?>
                <li>
                    <strong><?= htmlspecialchars($article['libelle']) ?></strong><br>
                    Stock actuel: <?= $article['stock_actuel'] ?><br>
                    Quantité demandée: <?= $article['quantite_demandee'] ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <p style="color: #d32f2f; margin-top: 10px;">
                <strong>Note:</strong> La vente sera enregistrée mais le stock deviendra négatif.
            </p>
        </div>
        <?php unset($_SESSION['stock_insuffisant']); ?>
        <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger" style="white-space: pre-line;">
                        <?php 
                        echo html_entity_decode($_SESSION['error']);
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form id="searchForm" enctype="multipart/form-data" method="post" class="search-form">
                    <div class="search-input-container">
                        <input type="text" id="searchQuery" name="CodePersoArticle" class="search-input" placeholder="🔍 Code ou Libellé de l'article... (Scanner de code-barres actif)" required autocomplete="off">
                        <div id="searchSuggestions" class="search-suggestions"></div>
                        <div id="barcodeIndicator" class="barcode-indicator" style="display: none;">
                            <i class="fas fa-barcode"></i> Scanner actif
                        </div>
                    </div>
                    <button type="submit" name="recherche" class="search-btn">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                </form>
                
                <!-- Message d'erreur pour la recherche -->
                <div id="searchError" class="error-message"></div>

                <div id="searchResult">
                    <div class="product-details">
                        <input type="text" id="libelle" class="product-field" placeholder="🏷️ Libellé de l'article" value="<?php echo isset($article['libelle']) ? htmlspecialchars($article['libelle'], ENT_QUOTES, 'UTF-8') : ''; ?>" disabled>
                        <input type="text" id="description" class="product-field" placeholder="📝 Description" value="<?php echo isset($article['Descriptif']) ? htmlspecialchars($article['Descriptif'], ENT_QUOTES, 'UTF-8') : ''; ?>" disabled>
                        <input type="text" id="prixVenteUnitaire" class="product-field" placeholder="💰 Prix Vente TTC" value="<?php echo isset($article['PrixVenteTTC']) ? number_format((float)$article['PrixVenteTTC'], 0, ',', ' ') : ''; ?> F CFA" disabled>
                </div>

                <!-- Formulaire d'Ajout au Panier -->
                    <form id="form-ajouter-article" method="post" class="add-form">
                    <input type="hidden" id="id_article" name="id_article" value="<?php echo isset($article['IDARTICLE']) ? htmlspecialchars($article['IDARTICLE'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    <input type="hidden" id="libelle" name="libelle" value="<?php echo isset($article['libelle']) ? htmlspecialchars($article['libelle'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    <input type="hidden" id="prixVenteUnitaire" name="prixVenteUnitaire" value="<?php echo isset($article['PrixVenteTTC']) ? (float)$article['PrixVenteTTC'] : ''; ?>">

                        <input type="number" id="quantite" class="quantity-field" placeholder="📦 Quantité" value="1" disabled>
                        <select id="numeroSerie" name="numeroSerie" class="serial-select" required>
                                    <option value="">🔢 Sélectionnez un numéro de série</option>
                                        <?php if (isset($article['NUMEROS_SERIE'])): ?>
                                            <?php foreach ($article['NUMEROS_SERIE'] as $numero): ?>
                                                <option value="<?= htmlspecialchars($numero['NUMERO_SERIE']) ?>">
                                                    <?= htmlspecialchars($numero['NUMERO_SERIE']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                        <button type="submit" class="add-btn" id="addToCartBtn" name="ajouter_panier">
                            <i class="fas fa-cart-plus"></i> Ajouter
                            </button>
                            
                        <!-- Messages d'erreur pour l'ajout au panier -->
                        <div id="addToCartError" class="error-message"></div>
                </form>
                </div>
            </div>

            <!-- Zone centrale - Tableau panier -->
            <div class="cart-section">
                <h3><i class="fas fa-shopping-cart"></i> Panier</h3>
                <div class="cart-table-container">
                    <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                                <th>Qté</th>
                                <th>Prix</th>
                                <th>N° Série</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cartTable">
                        <?php
                        $totalPanier = 0;
                        if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
                            // Parcourt chaque produit dans le panier
                            foreach ($_SESSION['panier'] as $id_article => $produits) {
                                foreach ($produits as $numeroSerie => $details) {
                                    $libelle = htmlspecialchars($details['libelle']);
                                    $quantite = (int) $details['quantite'];
                                    $prixUnitaire = (float) $details['prixVenteUnitaire'];
                                    $prixGlobal = $prixUnitaire * $quantite;
                        ?>
                                    <tr>
                                        <td><?= $libelle ?></td>
                                        <td><?= $quantite ?></td>
                                            <td><?= number_format($prixGlobal, 0, ',', ' ') ?> F</td>
                                        <td><?= htmlspecialchars($numeroSerie) ?></td>
                                        <td>
                                                <form method="post" style="display: inline;">
                                                <input type="hidden" name="id_article" value="<?= htmlspecialchars($id_article) ?>">
                                                <input type="hidden" name="numeroSerie" value="<?= htmlspecialchars($numeroSerie) ?>">
                                                    <button type="submit" class="delete-btn" name="supprimer_panier">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                            </form>
                                        </td>
                                    </tr>
                            <?php
                                    $totalPanier += $prixGlobal;
                                }
                            }
                            ?>
                        <?php
                        } else {
                                echo "<tr><td colspan='5' class='empty-cart'>Votre panier est vide.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
                <div style="text-align: right;">
                    <form method="post" action="" style="display: inline;">
                        <button type="submit" name="vider_panier" class="clear-cart-btn">
                            <i class="fas fa-trash-alt"></i> Vider le panier
                        </button>
                    </form>
                </div>
            </div>

            <!-- Zone basse - 3 sections horizontales -->
            <div class="bottom-section">
                <!-- SECTION 1: TOTAUX -->
                <div class="totaux-section">
                    <h3><i class="fas fa-calculator"></i> Totaux</h3>
                    
                    <div class="section-summary">
                        <div class="section-row">
                            <span class="section-label">💰 Total brut</span>
                            <span class="total-box total-brut"><?= number_format($totalPanier, 0, ',', ' ') ?> F</span>
                        </div>
                        <div class="section-row">
                            <span class="section-label">💳 Total avec remise</span>
                            <span class="total-box total-remise" id="montantTotal"><?= number_format($totalPanier, 0, ',', ' ') ?> F</span>
                        </div>
                        <div class="section-row">
                            <span class="section-label">🔄 Monnaie à rendre</span>
                            <span class="total-box monnaie" id="monnaie">0 F</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: ENCAISSEMENT -->
                <div class="encaissement-section">
                    <h3><i class="fas fa-credit-card"></i> Encaissement</h3>

                <form id="form-paiement" action="fonction_traitement/request.php" enctype="multipart/form-data" method="post">
                        <!-- Champs cachés pour les données de paiement -->
                        <input type="hidden" id="remiseMontantHidden" name="remiseMontant" value="0">
                        <input type="hidden" id="montantTotalHidden" name="montantTotal" value="<?= $totalPanier ?>">
                        <input type="hidden" id="monnaieHidden" name="monnaie" value="0">
                        <input type="hidden" id="vraiMontantTotalHidden" name="vrai_Montanttotal" value="<?= $totalPanier ?>">
                        
                        <!-- Champs client (doivent être dans le même formulaire) -->
                        <input type="hidden" id="nomprenomHidden" name="nomprenom" value="">
                        <input type="hidden" id="numeroClientHidden" name="numero_client" value="">
                        <input type="hidden" id="emailHidden" name="Adresse_email" value="">
                        <input type="hidden" id="modePaiementHidden" name="mode_paiement" value="">
                        <input type="hidden" id="montantVerseHidden" name="montantVerse" value="0">
                        
                        <div class="section-form">
                    <div class="form-group">
                                <label for="mode" class="required-field">💳 Mode de versement</label>
                                <div class="mode-payment-group">
                                    <select id="mode" name="mode_paiement" class="form-control" required>
                                        <option value="" disabled>💳 Mode</option>
                                        <?php foreach ($mode_paiement as $index => $modes): ?>
                                            <option value="<?php echo htmlspecialchars($modes['IDMODE_REGLEMENT']); ?>" <?php echo $index === 0 ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($modes['ModeReglement']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php echo bouton_action('Multi-P', 'caisse', 'multi_paiement', 'multi-payment-btn', 'type="button" id="btnMultiPaiement"'); ?>
                        </div>
                                <div id="modeError" class="error-message"></div>
                        </div>
                        
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="remiseMontant">💰 Remise</label>
                                    <input type="number" id="remiseMontant" class="form-control" value="0" step="0.01" min="0">
                                    <div id="remiseError" class="error-message"></div>
                            </div>
                                <div class="form-group">
                                    <label for="montantVerse" class="required-field">💵 Montant Versé</label>
                                    <input type="number" id="montantVerse" class="form-control montantVerse" name="montantVerse" placeholder="Montant versé" step="0.01" min="0" required>
                                    <div id="montantVerseError" class="error-message"></div>
                            </div>
                    </div>

                            <!-- Résumé des erreurs de validation -->
                            <div id="validationSummary" class="validation-summary">
                                <h4><i class="fas fa-exclamation-triangle"></i> Erreurs de validation</h4>
                                <ul id="validationErrorsList"></ul>
            </div>

                            <!-- Indicateur de chargement -->
                            <div id="loadingIndicator" class="loading-indicator">
                                <div class="spinner"></div>
                                Enregistrement en cours...
                            </div>
                            
                            <div class="section-actions">
                                <?php echo bouton_action('Encaisser', 'caisse', 'enregistrer', 'pay-btn', 'type="submit" name="enregistrer_caise" id="encaisserBtn"'); ?>
                                <?php echo bouton_action('Annuler', 'caisse', 'annuler', 'cancel-btn', 'type="reset" id="annulerBtn"'); ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- SECTION 3: INFO CLIENT -->
                <div class="client-section">
                    <h3><i class="fas fa-user"></i> Info Client</h3>
                    
                    <div class="section-form">
                        <div class="form-row">
                        <div class="form-group">
                                <label for="nomprenom" class="required-field">👤 Nom Client</label>
                                <input type="text" id="nomprenom" name="nomprenom" class="form-control" placeholder="Nom..." onkeyup="filterProducts()" required />
                            <input type="hidden" id="IDCLIENT" name="IDCLIENT" value="<?= isset($_POST['IDCLIENT']) ? htmlspecialchars($_POST['IDCLIENT']) : '' ?>">
                            <div id="produitContainer" class="produit-container" style="display: none;">
                                <ul id="produitList" class="list-group"></ul>
                        </div>
                                <div id="nomprenomError" class="error-message"></div>
                    </div>
                        <div class="form-group">
                                <label for="clientNumber" class="required-field">📞 Téléphone</label>
                                <input type="text" id="clientNumber" class="form-control" name="numero_client" placeholder="+225..." value="<?= isset($_POST['Telephone']) ? htmlspecialchars($_POST['Telephone']) : '' ?>" required>
                                <div id="clientNumberError" class="error-message"></div>
                            </div>
                    </div>

                        <div class="form-row-single">
                        <div class="form-group">
                                <label for="Adresse_email">📧 Email du Client (optionnel)</label>
                        <input type="email" id="Adresse_email" class="form-control" name="Adresse_email" placeholder="📧 Adresse email (optionnel)" value="<?= isset($_POST['Adresse_email']) ? htmlspecialchars($_POST['Adresse_email']) : '' ?>">
                                <div id="emailError" class="error-message"></div>
                    </div>
    </div>
</div>
                    </div>
                        </div>
        </main>
                </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
       window.onload = function () {
    const urlParams = new URLSearchParams(window.location.search);
    const numeroVente = urlParams.get('numero');
    const success = urlParams.get('success');

    if (success === 'vente_enregistree' && numeroVente) {
        // Afficher les options d'impression dans une SweetAlert centrée
        Swal.fire({
            title: '<i class="fas fa-check-circle text-success"></i> Vente enregistrée',
            html: `
                <div class="text-center">
                    <p class="mb-4">La vente a été enregistrée avec succès !</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" onclick="imprimerSansNouvelOnglet('print_ticket_caisse.php?numero=${numeroVente}')">
                            <i class="fas fa-print"></i> Imprimer Ticket de Caisse
                        </button>
                        <button class="btn btn-info btn-lg" onclick="imprimerSansNouvelOnglet('print_facture_standard.php?numero=${numeroVente}')">
                            <i class="fas fa-file-invoice"></i> Imprimer Facture A4
                        </button>
                        <button class="btn btn-success btn-lg" onclick="imprimerSansNouvelOnglet('print_facture_tva.php?numero=${numeroVente}')">
                            <i class="fas fa-file-invoice-dollar"></i> Imprimer Facture TVA
                        </button>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-times"></i> Fermer',
            showDenyButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            stopKeydownPropagation: true,
            width: '600px',
            customClass: {
                popup: 'swal2-popup',
                title: 'swal2-title',
                htmlContainer: 'swal2-html-container',
                cancelButton: 'swal2-cancel'
            },
            didOpen: () => {
                // Ajouter les gestionnaires d'événements pour les boutons d'impression
                document.querySelectorAll('.btn').forEach(button => {
                    button.addEventListener('click', () => {
                        // Attendre que l'impression soit terminée avant de recharger
                    setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    });
                });
            }
        });
        
        // Nettoyer URL après affichage de la SweetAlert
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                url.searchParams.delete('numero');
                window.history.replaceState(null, null, url);
            }
    }
};

        // ===== SYSTÈME DE VALIDATION COMPLET =====
        
        // Configuration des validations
        const VALIDATION_CONFIG = {
            REMISE_MAX_POURCENTAGE: 30, // Maximum 30% de remise
            TELEPHONE_PATTERN: /^(\+)?[0-9]{8,15}$/, // International (8-15 chiffres)
            EMAIL_PATTERN: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, // Tous les formats email valides
            MONTANT_MIN: 0,
            MONTANT_MAX: 999999999999 // Maximum 999,999,999,999 FCFA (plus professionnel)
        };
        
        // Fonction pour afficher une erreur
        function showError(fieldId, message) {
            const errorElement = document.getElementById(fieldId + 'Error');
            const fieldElement = document.getElementById(fieldId);
            
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }
            
            if (fieldElement) {
                fieldElement.classList.add('field-error');
                fieldElement.classList.remove('field-success');
            }
        }
        
        // Fonction pour masquer une erreur
        function hideError(fieldId) {
            const errorElement = document.getElementById(fieldId + 'Error');
            const fieldElement = document.getElementById(fieldId);
            
            if (errorElement) {
                errorElement.classList.remove('show');
            }
            
            if (fieldElement) {
                fieldElement.classList.remove('field-error');
                fieldElement.classList.add('field-success');
            }
        }
        
        // Fonction pour valider le format de téléphone (international)
        function validatePhone(phone) {
            if (!phone) return false;
            return VALIDATION_CONFIG.TELEPHONE_PATTERN.test(phone.replace(/\s/g, ''));
        }
        
        // Fonction pour valider le format d'email
        function validateEmail(email) {
            if (!email) return false;
            return VALIDATION_CONFIG.EMAIL_PATTERN.test(email);
        }
        
        // Fonction pour valider la remise
        function validateRemise(remise, totalBrut) {
            if (remise < 0) {
                return { valid: false, message: "La remise ne peut pas être négative" };
            }
            
            if (remise > totalBrut) {
                return { valid: false, message: "La remise ne peut pas dépasser le montant total" };
            }
            
            if (remise > VALIDATION_CONFIG.MONTANT_MAX) {
                return { 
                    valid: false, 
                    message: `La remise ne peut pas dépasser ${formatMontant(VALIDATION_CONFIG.MONTANT_MAX)}` 
                };
            }
            
            const pourcentageRemise = (remise / totalBrut) * 100;
            if (pourcentageRemise > VALIDATION_CONFIG.REMISE_MAX_POURCENTAGE) {
                return { 
                    valid: false, 
                    message: `La remise ne peut pas dépasser ${VALIDATION_CONFIG.REMISE_MAX_POURCENTAGE}% du montant total (${pourcentageRemise.toFixed(1)}%)` 
                };
            }
            
            return { valid: true };
        }
        
        // Fonction pour valider le montant versé
        function validateMontantVerse(montantVerse, montantTotal) {
            if (montantVerse < 0) {
                return { valid: false, message: "Le montant versé ne peut pas être négatif" };
            }
            
            if (montantVerse > VALIDATION_CONFIG.MONTANT_MAX) {
                return { 
                    valid: false, 
                    message: `Le montant versé ne peut pas dépasser ${formatMontant(VALIDATION_CONFIG.MONTANT_MAX)}` 
                };
            }
            
            if (montantVerse < montantTotal) {
                return { 
                    valid: false, 
                    message: `Le montant versé (${formatMontant(montantVerse)}) est inférieur au total à payer (${formatMontant(montantTotal)}). Ajoutez un mode de paiement complémentaire ou sélectionnez Vente à crédit.` 
                };
            }
            
            return { valid: true };
        }
        
        // Fonction pour formater les montants
        function formatMontant(montant) {
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(montant) + ' F';
        }

        // Fonction pour calculer le montant total avec remise
        function calculerMontantAvecRemise() {
            var montantTotalSansRemise = parseFloat(<?= $totalPanier ?>); // Montant total sans remise
            var remiseMontant = parseFloat(document.getElementById('remiseMontant').value) || 0; // Remise en montant

            // Validation de la remise
            const validationRemise = validateRemise(remiseMontant, montantTotalSansRemise);
            if (!validationRemise.valid) {
                showError('remise', validationRemise.message);
                return 0;
            } else {
                hideError('remise');
            }
            
            // Calculer le montant total avec remise
            var montantTotalAvecRemise = montantTotalSansRemise - remiseMontant;
            if (montantTotalAvecRemise < 0) montantTotalAvecRemise = 0; // S'assurer que le montant ne soit pas négatif

            // Afficher le montant total avec remise dans l'input correspondant
            document.getElementById('montantTotal').textContent = formatMontant(montantTotalAvecRemise);

            // Retourner le montant total avec remise pour d'autres calculs
            return montantTotalAvecRemise;
        }
        
// Appeler la fonction au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    calculerMontantAvecRemise();
});

        // Fonction pour calculer la monnaie à rendre
        function calculerMonnaieRendre() {
            var montantTotalSansRemise = parseFloat(<?= $totalPanier ?>); // Montant total sans remise
            var montantTotalAvecRemise = calculerMontantAvecRemise(); // Calculer le montant total avec remise
            var montantVerse = parseFloat(document.getElementById('montantVerse').value) || 0; // Montant versé par le client
            
            // Validation du montant versé
            const validationMontant = validateMontantVerse(montantVerse, montantTotalAvecRemise);
            if (!validationMontant.valid) {
                showError('montantVerse', validationMontant.message);
            } else {
                hideError('montantVerse');
            }

            // Calculer la monnaie à rendre (ne peut pas être négative)
            var monnaieRendre = Math.max(0, montantVerse - montantTotalAvecRemise);

            // Afficher la monnaie à rendre dans l'input correspondant
            document.getElementById('monnaie').textContent = formatMontant(monnaieRendre);
        }
        
        // Fonction de validation complète du formulaire
        function validateForm() {
            let isValid = true;
            const errors = [];
            
            // Note: La validation du panier est gérée côté serveur
            
            // Vérifier les champs obligatoires
            const requiredFields = [
                { id: 'nomprenom', name: 'Nom du client' },
                { id: 'clientNumber', name: 'Téléphone' },
                { id: 'mode', name: 'Mode de paiement' },
                { id: 'montantVerse', name: 'Montant versé' }
            ];
            
            requiredFields.forEach(field => {
                const element = document.getElementById(field.id);
                if (!element || !element.value.trim()) {
                    showError(field.id, `${field.name} est obligatoire`);
                    errors.push(`${field.name} est obligatoire`);
                    isValid = false;
                } else {
                    hideError(field.id);
                }
            });
            
            // Validation spécifique du téléphone (international)
            const phone = document.getElementById('clientNumber').value;
            if (phone && !validatePhone(phone)) {
                showError('clientNumber', 'Format de téléphone invalide. Utilisez un numéro de 8 à 15 chiffres avec ou sans indicatif pays (+).');
                errors.push('Format de téléphone invalide');
                isValid = false;
            }
            
            // Validation spécifique de l'email (optionnel)
            const email = document.getElementById('Adresse_email').value;
            if (email && !validateEmail(email)) {
                showError('Adresse_email', 'Format d\'email invalide (ex: client@icloud.com, client@yahoo.fr)');
                errors.push('Format d\'email invalide');
                isValid = false;
            }
            
            // Validation des montants
            const montantTotalAvecRemise = calculerMontantAvecRemise();
            const montantVerse = parseFloat(document.getElementById('montantVerse').value) || 0;
            
            const validationMontant = validateMontantVerse(montantVerse, montantTotalAvecRemise);
            if (!validationMontant.valid) {
                showError('montantVerse', validationMontant.message);
                errors.push(validationMontant.message);
                isValid = false;
            }
            
            // Afficher le résumé des erreurs
            const validationSummary = document.getElementById('validationSummary');
            const errorsList = document.getElementById('validationErrorsList');
            
            if (!isValid) {
                errorsList.innerHTML = errors.map(error => `<li>${error}</li>`).join('');
                validationSummary.classList.add('show');
            } else {
                validationSummary.classList.remove('show');
            }
            
            return isValid;
        }

        // Ajouter des événements pour recalculer lorsque les valeurs changent
        document.getElementById('remiseMontant').addEventListener('input', function() {
            calculerMonnaieRendre();
        });
        document.getElementById('montantVerse').addEventListener('input', function() {
            calculerMonnaieRendre();
        });
        
        // Validation en temps réel pour les montants
        document.getElementById('remiseMontant').addEventListener('input', function() {
            const remise = parseFloat(this.value) || 0;
            const totalBrut = parseFloat(<?= $totalPanier ?>);
            const validation = validateRemise(remise, totalBrut);
            if (!validation.valid) {
                showError('remise', validation.message);
            } else {
                hideError('remise');
            }
        });
        
        document.getElementById('montantVerse').addEventListener('input', function() {
            const montantVerse = parseFloat(this.value) || 0;
            const montantTotal = calculerMontantAvecRemise();
            const validation = validateMontantVerse(montantVerse, montantTotal);
            if (!validation.valid) {
                showError('montantVerse', validation.message);
            } else {
                hideError('montantVerse');
            }
        });
        
        // Validation en temps réel
        document.getElementById('clientNumber').addEventListener('input', function() {
            const phone = this.value;
            if (phone && !validatePhone(phone)) {
                showError('clientNumber', 'Format de téléphone invalide. Utilisez un numéro de 8 à 15 chiffres avec ou sans indicatif pays (+).');
            } else {
                hideError('clientNumber');
            }
        });
        
        document.getElementById('Adresse_email').addEventListener('input', function() {
            const email = this.value;
            if (email && !validateEmail(email)) {
                showError('Adresse_email', 'Format d\'email invalide (ex: client@icloud.com, client@yahoo.fr)');
            } else {
                hideError('Adresse_email');
            }
        });
        
        document.getElementById('nomprenom').addEventListener('input', function() {
            if (this.value.trim()) {
                hideError('nomprenom');
            }
        });
        
        document.getElementById('mode').addEventListener('change', function() {
            if (this.value) {
                hideError('mode');
            }
        });

        // Fonction pour mettre à jour les champs cachés avant soumission
        function updateHiddenFields() {
            var remiseMontant = parseFloat(document.getElementById('remiseMontant').value) || 0;
            var montantTotalAvecRemise = calculerMontantAvecRemise();
            var montantVerse = parseFloat(document.getElementById('montantVerse').value) || 0;
            // Calcul de la monnaie à rendre (ne peut pas être négative)
            var monnaie = Math.max(0, montantVerse - montantTotalAvecRemise);

            // Mettre à jour les champs cachés de paiement
            document.getElementById('remiseMontantHidden').value = remiseMontant;
            document.getElementById('montantTotalHidden').value = montantTotalAvecRemise;
            document.getElementById('monnaieHidden').value = monnaie;
            document.getElementById('vraiMontantTotalHidden').value = <?= $totalPanier ?>;
            document.getElementById('montantVerseHidden').value = montantVerse;

            // Mettre à jour les champs cachés client
            document.getElementById('nomprenomHidden').value = document.getElementById('nomprenom').value;
            document.getElementById('numeroClientHidden').value = document.getElementById('clientNumber').value;
            document.getElementById('emailHidden').value = document.getElementById('Adresse_email').value;
            document.getElementById('modePaiementHidden').value = document.getElementById('mode').value;
        }

        // Ajouter un événement pour mettre à jour les champs cachés avant soumission
        document.getElementById('form-paiement').addEventListener('submit', function(e) {
            // Validation du formulaire
            if (!validateForm()) {
                e.preventDefault();
                // Faire défiler vers le premier champ en erreur
                const firstError = document.querySelector('.field-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                return false;
            }
            
            // Désactiver le bouton pour éviter les doubles clics
            const encaisserBtn = document.getElementById('encaisserBtn');
            const loadingIndicator = document.getElementById('loadingIndicator');
            
            encaisserBtn.classList.add('btn-disabled');
            loadingIndicator.classList.add('show');
            
            // Mettre à jour les champs cachés
            updateHiddenFields();
            
            // Laisser le formulaire se soumettre normalement
        });
        
        // Fonction pour réinitialiser la page
        function reinitialiserPage() {
            // Vider le panier
            fetch('fonction_traitement/request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=vider_panier'
            })
            .then(() => {
                // Recharger la page
                window.location.reload();
            })
            .catch(() => {
                // En cas d'erreur, recharger quand même
                window.location.reload();
            });
        }
        
        // Ajouter l'événement au bouton Annuler
        document.getElementById('annulerBtn').addEventListener('click', function(e) {
            e.preventDefault();
            reinitialiserPage();
        });

        // Stockez les produits dans une variable pour le filtrage
        const products = <?php
                            $query = "SELECT IDCLIENT, NomPrenomClient, Telephone, Adresse_email FROM client";
                            $stmt = $cnx->prepare($query);
                            $stmt->execute();

                            $produits = [];
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $produits[] = $row;
                            }
                            echo json_encode($produits);
                            ?>;

        // Stockez les articles dans une variable pour la recherche dynamique
        const articles = <?php
                            $query = "SELECT a.IDARTICLE, a.CodePersoArticle, a.libelle, a.PrixVenteTTC, a.desactiver, 
                                            COALESCE(s.StockActuel, 0) as StockActuel
                                      FROM article a 
                                      LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE 
                                      WHERE a.desactiver != 'oui' OR a.desactiver IS NULL
                                      ORDER BY a.libelle";
                            $stmt = $cnx->prepare($query);
                            $stmt->execute();

                            $articles = [];
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $articles[] = $row;
                            }
                            echo json_encode($articles);
                            ?>;

        // ===== RECHERCHE DYNAMIQUE D'ARTICLES =====
        let currentSuggestionIndex = -1;
        let filteredArticles = [];

        function searchArticles() {
            const input = document.getElementById('searchQuery');
            const suggestionsContainer = document.getElementById('searchSuggestions');
            const filter = input.value.toLowerCase().trim();
            
            // Réinitialiser l'index de suggestion
            currentSuggestionIndex = -1;
            
            if (filter.length < 2) {
                suggestionsContainer.classList.remove('show');
                return;
            }
            
            // Filtrer les articles
            filteredArticles = articles.filter(article => 
                article.CodePersoArticle.toLowerCase().includes(filter) || 
                article.libelle.toLowerCase().includes(filter)
            );
            
            if (filteredArticles.length === 0) {
                suggestionsContainer.innerHTML = '<div class="suggestion-item"><div class="suggestion-info"><div class="suggestion-libelle">Aucun article trouvé</div></div></div>';
                suggestionsContainer.classList.add('show');
                return;
            }
            
            // Limiter à 10 suggestions maximum
            const limitedArticles = filteredArticles.slice(0, 10);
            
            // Générer le HTML des suggestions
            suggestionsContainer.innerHTML = limitedArticles.map(article => {
                const stockClass = article.StockActuel <= 5 ? 'low' : '';
                const stockText = article.StockActuel <= 5 ? `⚠️ Stock faible: ${article.StockActuel}` : `Stock: ${article.StockActuel}`;
                
                return `
                    <div class="suggestion-item" data-article='${JSON.stringify(article)}'>
                        <div class="suggestion-info">
                            <div class="suggestion-code">${article.CodePersoArticle}</div>
                            <div class="suggestion-libelle">${article.libelle}</div>
                            <div class="suggestion-stock ${stockClass}">${stockText}</div>
                        </div>
                        <div class="suggestion-price">${formatMontant(article.PrixVenteTTC)}</div>
                    </div>
                `;
            }).join('');
            
            suggestionsContainer.classList.add('show');
        }
        
        function selectSuggestion(article) {
            const input = document.getElementById('searchQuery');
            const suggestionsContainer = document.getElementById('searchSuggestions');
            
            // Remplir le champ de recherche
            input.value = article.CodePersoArticle;
            
            // Masquer les suggestions
            suggestionsContainer.classList.remove('show');
            
            // Déclencher la recherche automatiquement
            document.querySelector('button[name="recherche"]').click();
        }
        
        function highlightSuggestion(index) {
            const suggestions = document.querySelectorAll('.suggestion-item');
            suggestions.forEach((item, i) => {
                if (i === index) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }
        
        function showBarcodeError(barcode) {
            // Afficher un message d'erreur temporaire
            const searchError = document.getElementById('searchError');
            searchError.textContent = `❌ Code-barres non trouvé: ${barcode}`;
            searchError.classList.add('show');
            
            // Masquer le message après 3 secondes
            setTimeout(() => {
                searchError.classList.remove('show');
            }, 3000);
            
            // Vider le champ de recherche
            document.getElementById('searchQuery').value = '';
        }
        
        function handleBarcodeScan(barcode) {
            // Masquer l'indicateur de scanner
            document.getElementById('barcodeIndicator').style.display = 'none';
            
            // Rechercher l'article avec ce code
            const foundArticle = articles.find(article => 
                article.CodePersoArticle === barcode
            );
            
            if (foundArticle) {
                // Vérifier si l'article est désactivé
                if (foundArticle.desactiver === 'oui') {
                    showBarcodeError(`${barcode} (Article désactivé)`);
                    return;
                }
                
                // Vérifier le stock
                if (foundArticle.StockActuel <= 0) {
                    showBarcodeError(`${barcode} (Stock épuisé)`);
                    return;
                }
                
                // Sélectionner automatiquement l'article
                selectSuggestion(foundArticle);
                
                // Afficher un message de succès
                const searchError = document.getElementById('searchError');
                searchError.textContent = `✅ Article trouvé: ${foundArticle.libelle}`;
                searchError.classList.add('show');
                searchError.style.color = '#28a745';
                
                setTimeout(() => {
                    searchError.classList.remove('show');
                }, 2000);
                
            } else {
                showBarcodeError(barcode);
            }
        }
        
        function showBarcodeIndicator() {
            const indicator = document.getElementById('barcodeIndicator');
            indicator.style.display = 'block';
            
            // Masquer après 2 secondes
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 2000);
        }

        function filterProducts() {
            const input = document.getElementById('nomprenom');
            const idclient = document.getElementById('IDCLIENT');
            const telephoneInput = document.getElementById('clientNumber');
            const adresseInput = document.getElementById('Adresse_email');
            const filter = input.value.toLowerCase();
            const produitContainer = document.getElementById('produitContainer');
            const produitList = document.getElementById('produitList');

            produitList.innerHTML = '';
            const filtered = products.filter(product => 
                product.NomPrenomClient.toLowerCase().includes(filter) || 
                product.Telephone.includes(filter)
            );

            if (filter === '' || filtered.length === 0) {
                produitContainer.style.display = 'none';
                idclient.value = '';
                telephoneInput.value = '';
                adresseInput.value = '';
                return;
            }

            filtered.forEach(product => {
                const listItem = document.createElement('li');
                listItem.classList.add('list-group-item');
                listItem.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${product.NomPrenomClient}</strong><br>
                            <small>Tél: ${product.Telephone}</small>
                        </div>
                        <button class="btn btn-sm btn-primary select-client" 
                            data-id="${product.IDCLIENT}"
                            data-name="${product.NomPrenomClient}"
                            data-phone="${product.Telephone}"
                            data-email="${product.Adresse_email || ''}">
                            Sélectionner
                        </button>
                    </div>
                `;
                produitList.appendChild(listItem);
            });
            produitContainer.style.display = 'block';

            // Ajouter les écouteurs d'événements pour les boutons de sélection
            document.querySelectorAll('.select-client').forEach(button => {
                button.addEventListener('click', () => {
                    input.value = button.dataset.name;
                    idclient.value = button.dataset.id;
                    telephoneInput.value = button.dataset.phone;
                    adresseInput.value = button.dataset.email;
                    produitContainer.style.display = 'none';
                });
            });
        }

        // Ajouter un écouteur pour fermer la liste quand on clique en dehors
        document.addEventListener('click', function(event) {
            const produitContainer = document.getElementById('produitContainer');
            const input = document.getElementById('nomprenom');
            if (!produitContainer.contains(event.target) && event.target !== input) {
                produitContainer.style.display = 'none';
            }
        });

        // Navigation par touche Entrée pour une utilisation rapide
        document.addEventListener('DOMContentLoaded', function() {
            const searchQuery = document.getElementById('searchQuery');
            const numeroSerie = document.getElementById('numeroSerie');
            const nomprenom = document.getElementById('nomprenom');
            const clientNumber = document.getElementById('clientNumber');
            const adresseEmail = document.getElementById('Adresse_email');
            const mode = document.getElementById('mode');
            const montantVerse = document.getElementById('montantVerse');
            const remiseMontant = document.getElementById('remiseMontant');
            
            // ===== ÉVÉNEMENTS POUR LA RECHERCHE DYNAMIQUE D'ARTICLES =====
            
            // Variables pour la gestion des codes-barres
            let barcodeBuffer = '';
            let barcodeTimeout;
            const BARCODE_DELAY = 100; // Délai entre les caractères (ms)
            let isTyping = false; // Indicateur de saisie manuelle
            let typingTimeout;
            
            // Recherche en temps réel
            searchQuery.addEventListener('input', function() {
                // Marquer comme saisie manuelle
                isTyping = true;
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    isTyping = false;
                }, 1000); // Considérer comme saisie manuelle pendant 1 seconde après le dernier input
                
                searchArticles();
            });
            
            // Gestion des codes-barres (scanner)
            searchQuery.addEventListener('keydown', function(e) {
                // Si c'est Entrée et qu'on a du contenu dans le buffer
                if (e.key === 'Enter') {
                    e.preventDefault();
                    
                    if (barcodeBuffer.length > 0 && !isTyping) {
                        // Traiter le code-barres scanné seulement si ce n'est pas une saisie manuelle
                        handleBarcodeScan(barcodeBuffer);
                        barcodeBuffer = '';
                        clearTimeout(barcodeTimeout);
                    } else {
                        // Recherche normale si pas de buffer ou si c'est une saisie manuelle
                        document.querySelector('button[name="recherche"]').click();
                    }
                    return;
                }
                
                // Réinitialiser le buffer si c'est une touche spéciale
                if (e.key === 'Tab' || e.key === 'Escape') {
                    barcodeBuffer = '';
                    clearTimeout(barcodeTimeout);
                    return;
                }
                
                // Ignorer la gestion des codes-barres si l'utilisateur tape manuellement
                if (isTyping) {
                    return;
                }
                
                // Ajouter le caractère au buffer seulement si ce n'est pas une saisie manuelle
                barcodeBuffer += e.key;
                
                // Afficher l'indicateur de scanner si c'est le premier caractère
                if (barcodeBuffer.length === 1) {
                    showBarcodeIndicator();
                }
                
                // Effacer le timeout précédent
                clearTimeout(barcodeTimeout);
                
                // Définir un nouveau timeout pour détecter la fin du scan
                barcodeTimeout = setTimeout(() => {
                    // Si le buffer contient plus de 5 caractères et que ce n'est pas une saisie manuelle, c'est probablement un code-barres
                    if (barcodeBuffer.length > 5 && !isTyping) {
                        handleBarcodeScan(barcodeBuffer);
                    }
                    
                    // Réinitialiser le buffer
                    barcodeBuffer = '';
                }, BARCODE_DELAY);
            });
            
            // Gestion des clics sur les suggestions
            document.addEventListener('click', function(e) {
                if (e.target.closest('.suggestion-item')) {
                    const suggestionItem = e.target.closest('.suggestion-item');
                    const articleData = JSON.parse(suggestionItem.dataset.article);
                    selectSuggestion(articleData);
                }
            });
            
            // Gestion des touches clavier
            searchQuery.addEventListener('keydown', function(e) {
                const suggestionsContainer = document.getElementById('searchSuggestions');
                
                if (!suggestionsContainer.classList.contains('show')) {
                    return;
                }
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        currentSuggestionIndex = Math.min(currentSuggestionIndex + 1, filteredArticles.length - 1);
                        highlightSuggestion(currentSuggestionIndex);
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        currentSuggestionIndex = Math.max(currentSuggestionIndex - 1, -1);
                        if (currentSuggestionIndex === -1) {
                            document.querySelectorAll('.suggestion-item').forEach(item => {
                                item.classList.remove('active');
                            });
                        } else {
                            highlightSuggestion(currentSuggestionIndex);
                        }
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (currentSuggestionIndex >= 0 && filteredArticles[currentSuggestionIndex]) {
                            selectSuggestion(filteredArticles[currentSuggestionIndex]);
                        } else {
                            // Recherche normale si aucune suggestion sélectionnée
                            document.querySelector('button[name="recherche"]').click();
                        }
                        break;
                        
                    case 'Escape':
                        suggestionsContainer.classList.remove('show');
                        currentSuggestionIndex = -1;
                        break;
                }
            });
            
            // Masquer les suggestions quand on clique ailleurs
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-input-container')) {
                    document.getElementById('searchSuggestions').classList.remove('show');
                    currentSuggestionIndex = -1;
                }
            });

            // Fonction pour passer au champ suivant
            function nextField(currentField, nextField) {
                if (nextField) {
                    nextField.focus();
                    if (nextField.tagName === 'SELECT') {
                        nextField.click();
                    }
                }
            }

            // Gestionnaires d'événements pour la touche Entrée (géré dans la section recherche dynamique)

            numeroSerie.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('addToCartBtn').click();
                }
            });

            nomprenom.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextField(nomprenom, clientNumber);
                }
            });

            clientNumber.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextField(clientNumber, adresseEmail);
                }
            });

            adresseEmail.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextField(adresseEmail, mode);
                }
            });

            mode.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextField(mode, montantVerse);
                }
            });

            montantVerse.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextField(montantVerse, remiseMontant);
                }
            });

            remiseMontant.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.querySelector('button[name="enregistrer_caise"]').click();
                }
            });

            // Focus automatique sur le champ de recherche au chargement
            searchQuery.focus();
            
            // ===== PRÉVENTION DES DOUBLONS AVEC JAVASCRIPT =====
            
            // Fonction pour vérifier si un numéro de série existe déjà dans le panier
            function checkDuplicateSerial(serialNumber) {
                const cartTable = document.getElementById('cartTable');
                const rows = cartTable.querySelectorAll('tr');
                
                for (let row of rows) {
                    const serialCell = row.cells[3]; // Colonne du numéro de série
                    if (serialCell && serialCell.textContent.trim() === serialNumber) {
                        return true;
                    }
                }
                return false;
            }
            
            // Fonction pour afficher une erreur JavaScript
            function showJSError(message) {
                const errorDiv = document.getElementById('addToCartError');
                if (errorDiv) {
                    errorDiv.textContent = message;
                    errorDiv.classList.add('show');
                    
                    // Masquer l'erreur après 5 secondes
                    setTimeout(() => {
                        errorDiv.classList.remove('show');
                    }, 5000);
                }
            }
            
            // Fonction pour masquer l'erreur JavaScript
            function hideJSError() {
                const errorDiv = document.getElementById('addToCartError');
                if (errorDiv) {
                    errorDiv.classList.remove('show');
                }
            }
            
            // Intercepter la soumission du formulaire d'ajout au panier
            const addToCartForm = document.getElementById('form-ajouter-article');
            if (addToCartForm) {
                addToCartForm.addEventListener('submit', function(e) {
                    const serialSelect = document.getElementById('numeroSerie');
                    const serialNumber = serialSelect.value;
                    
                    // Vérifier si un numéro de série est sélectionné
                    if (!serialNumber) {
                        e.preventDefault(); // Empêcher la soumission
                        showJSError('Veuillez sélectionner un numéro de série.');
                        return false;
                    }
                    
                    // Vérifier si le numéro de série existe déjà dans le panier
                    if (checkDuplicateSerial(serialNumber)) {
                        e.preventDefault(); // Empêcher la soumission
                        showJSError('Ce numéro de série est déjà dans le panier.');
                        return false;
                    }
                    
                    // Si tout est OK, masquer les erreurs et laisser le formulaire se soumettre normalement
                    hideJSError();
                    // Ne pas empêcher la soumission - laisser le formulaire se soumettre
                });
            }
        });

//SECTION BOUTON MULTI-PAIEMENT 
    document.addEventListener('DOMContentLoaded', function() {
    // Récupération des montants depuis les champs du formulaire
    const totalBrut = parseFloat(<?= $totalPanier ?>) || 0;
    let paiements = []; // Tableau pour stocker les paiements effectués
    
    // Fonction pour calculer le montant restant à payer
    function updateMontantRestant() {
        const remiseActuelle = parseFloat(document.getElementById('remiseMontant').value) || 0;
        const montantTotalActuel = totalBrut - remiseActuelle;
        const totalPaye = paiements.reduce((sum, p) => sum + p.montant, 0);
        const restant = montantTotalActuel - totalPaye;
        return restant;
    }
    
    // Fonction pour formater les montants avec le format monétaire
    function formatMontant(montant) {
        return montant.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F';
    }
    
    // Fonction pour générer le tableau HTML des paiements
    function generatePaymentTable() {
        const montantRestant = updateMontantRestant();
        const remiseActuelle = parseFloat(document.getElementById('remiseMontant').value) || 0;
        const montantTotalActuel = totalBrut - remiseActuelle;
        
        let html = `
            <div class="payment-summary mb-3">
                <div class="row">
                    <div class="col-6">
                        <strong><i class="fas fa-calculator"></i> Total à payer:</strong>
                    </div>
                    <div class="col-6 text-end">
                        <span class="badge bg-primary fs-6">${formatMontant(montantTotalActuel)}</span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong><i class="fas fa-wallet"></i> Reste à payer:</strong>
                    </div>
                    <div class="col-6 text-end">
                        <span class="badge ${montantRestant > 0 ? 'bg-warning' : 'bg-success'} fs-6">${formatMontant(montantRestant)}</span>
                    </div>
                </div>
            </div>
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th><i class="fas fa-credit-card"></i> Mode de paiement</th>
                        <th><i class="fas fa-money-bill"></i> Montant</th>
                        <th><i class="fas fa-cog"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        if (paiements.length === 0) {
            html += `
                <tr>
                    <td colspan="3" class="text-center text-muted">
                        <i class="fas fa-info-circle"></i> Aucun paiement ajouté
                    </td>
                </tr>
            `;
        } else {
            paiements.forEach((p, index) => {
                html += `
                    <tr>
                        <td><i class="fas fa-credit-card text-primary"></i> ${p.modeLibelle}</td>
                        <td><strong>${formatMontant(p.montant)}</strong></td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="supprimerPaiement(${index})" title="Supprimer ce paiement">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        
        html += '</tbody></table>';
        return html;
    }
    
    // Gestionnaire d'événement pour le bouton de multi-paiement
    document.getElementById('btnMultiPaiement').addEventListener('click', function() {
        // Recalculer les montants au moment du clic
        const remiseActuelle = parseFloat(document.getElementById('remiseMontant').value) || 0;
        const montantTotalActuel = totalBrut - remiseActuelle;
        
        // Réinitialiser les paiements
        paiements = [];
        
        // Configuration et affichage du SweetAlert
        Swal.fire({
            title: '<i class="fas fa-money-bill-wave text-primary"></i> Multi-paiement',
            html: `
                <div class="container-fluid">
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Total à payer:</strong> ${formatMontant(montantTotalActuel)}
                            </div>
                        </div>
                    </div>
                    
                    <form id="formMultiPaiement" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-credit-card text-primary"></i> Mode de paiement
                                </label>
                                <select class="form-select" id="modePaiement" required>
                                    <option value="">Sélectionnez un mode de paiement</option>
                                    ${Array.from(document.getElementById('mode').options)
                                        .map(opt => `<option value="${opt.value}">${opt.text}</option>`)
                                        .join('')}
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-money-bill text-success"></i> Montant
                                </label>
                                <input type="number" class="form-control" id="montantPaiement" step="0.01" min="0" required>
                                <small class="form-text text-muted" id="montantFeedback"></small>
                            </div>
                        </div>
                    </form>
                    
                    <div id="paymentList">
                        ${generatePaymentTable()}
                    </div>
                </div>
            `,
            // Configuration des boutons du SweetAlert
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-plus"></i> Ajouter',
            cancelButtonText: '<i class="fas fa-times"></i> Annuler',
            showDenyButton: true,
            denyButtonText: '<i class="fas fa-check"></i> Valider',
            width: '900px',
            // Classes CSS personnalisées
            customClass: {
                popup: 'swal2-popup',
                title: 'swal2-title',
                htmlContainer: 'swal2-html-container',
                confirmButton: 'swal2-confirm',
                cancelButton: 'swal2-cancel',
                denyButton: 'swal2-deny'
            },
            // Empêche la fermeture automatique du SweetAlert
            preConfirm: () => false,
            
            // Fonction exécutée après l'ouverture du SweetAlert
            didOpen: () => {
                // Récupération des éléments du DOM
                const confirmButton = Swal.getConfirmButton();
                const denyButton = Swal.getDenyButton();
                const montantInput = document.getElementById('montantPaiement');
                const montantFeedback = document.getElementById('montantFeedback');
                
                // Gestionnaire d'événement pour la saisie du montant
                montantInput.addEventListener('input', function() {
                    const montantSaisi = parseFloat(this.value) || 0;
                    const restant = updateMontantRestant();
                    
                    // Validation du montant saisi
                    if (montantSaisi <= 0) {
                        montantFeedback.textContent = '⚠️ Le montant doit être supérieur à 0';
                        montantFeedback.className = 'text-danger';
                        confirmButton.disabled = true;
                    } else if (montantSaisi > restant) {
                        montantFeedback.textContent = `⚠️ Le montant saisi (${formatMontant(montantSaisi)}) dépasse le montant restant (${formatMontant(restant)})`;
                        montantFeedback.className = 'text-danger';
                        confirmButton.disabled = true;
                    } else {
                        const nouveauRestant = restant - montantSaisi;
                        montantFeedback.textContent = `✅ Montant valide. Reste à payer: ${formatMontant(nouveauRestant)}`;
                        montantFeedback.className = 'text-success';
                        confirmButton.disabled = false;
                    }
                });
                
                // Gestionnaire d'événement pour le bouton "Ajouter"
                confirmButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    const mode = document.getElementById('modePaiement');
                    const montant = parseFloat(montantInput.value);
                    
                    // Validation des champs
                    if (!mode.value || isNaN(montant) || montant <= 0) {
                        Swal.showValidationMessage('Veuillez sélectionner un mode de paiement et entrer un montant valide');
                        return false;
                    }
                    
                    // Vérification du montant par rapport au reste à payer
                    const restant = updateMontantRestant();
                    if (montant > restant) {
                        Swal.showValidationMessage('Le montant ne peut pas dépasser le montant restant à payer');
                        return false;
                    }
                    
                    // Ajout du paiement au tableau
                    paiements.push({
                        mode: mode.value,
                        modeLibelle: mode.options[mode.selectedIndex].text,
                        montant: montant
                    });
                    
                    // Mise à jour de l'interface
                    document.getElementById('paymentList').innerHTML = generatePaymentTable();
                    document.getElementById('formMultiPaiement').reset();
                    montantFeedback.textContent = '';
                    
                    // Désactiver le bouton ajouter jusqu'à la prochaine saisie
                    confirmButton.disabled = true;
                    
                    return false;
                });
                
                // Gestionnaire d'événement pour le bouton "Valider"
                denyButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    const restant = updateMontantRestant();
                    
                    // Vérification qu'au moins un paiement a été ajouté
                    if (paiements.length === 0) {
                        Swal.showValidationMessage('Veuillez ajouter au moins un paiement');
                        return false;
                    }
                    
                    // Vérification que le montant total des paiements correspond au montant à payer
                    if (Math.abs(restant) > 0.01) {
                        Swal.showValidationMessage(`Le montant total des paiements doit être égal au montant à payer. Reste à payer: ${formatMontant(restant)}`);
                        return false;
                    }

                // Envoi des données au serveur
                fetch('fonction_traitement/request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'multi_paiement',
                        'paiements': JSON.stringify(paiements),
                        'montant_total': montantTotalActuel,
                        'remiseMontant': remiseActuelle,
                        'nomprenom': document.getElementById('nomprenom').value,
                        'numero_client': document.getElementById('clientNumber').value,
                        'Adresse_email': document.getElementById('Adresse_email').value
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau: ' + response.status);
                    }
                    
                    // Vérifier si la réponse est du JSON valide
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Réponse non-JSON reçue du serveur');
                    }
                    
                    return response.json();
                })
                    .then(data => {
                        if (data.success) {
                            // Fermer le SweetAlert actuel et en ouvrir un nouveau pour les options d'impression
                            Swal.fire({
                                title: '<i class="fas fa-check-circle text-success"></i> Multi-paiement enregistré',
                                html: `
                                    <div class="text-center">
                                        <p class="mb-4">La vente multi-paiement a été enregistrée avec succès !</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-primary btn-lg" onclick="imprimerSansNouvelOnglet('print_ticket_caisse.php?numero=${data.numero_vente}')">
                                                <i class="fas fa-print"></i> Imprimer Ticket de Caisse
                                            </button>
                                            <button class="btn btn-info btn-lg" onclick="imprimerSansNouvelOnglet('print_facture_standard.php?numero=${data.numero_vente}')">
                                                <i class="fas fa-file-invoice"></i> Imprimer Facture A4
                                            </button>
                                            <button class="btn btn-success btn-lg" onclick="imprimerSansNouvelOnglet('print_facture_tva.php?numero=${data.numero_vente}')">
                                                <i class="fas fa-file-invoice-dollar"></i> Imprimer Facture TVA
                                            </button>
                                        </div>
                                    </div>
                                `,
                                showConfirmButton: false,
                                showCancelButton: true,
                                cancelButtonText: '<i class="fas fa-times"></i> Fermer',
                                showDenyButton: false,
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                allowEnterKey: false,
                                stopKeydownPropagation: true,
                                width: '600px',
                                customClass: {
                                    popup: 'swal2-popup',
                                    title: 'swal2-title',
                                    htmlContainer: 'swal2-html-container',
                                    cancelButton: 'swal2-cancel'
                                },
                                didOpen: () => {
                                    // Ajouter les gestionnaires d'événements pour les boutons d'impression
                                    document.querySelectorAll('.btn').forEach(button => {
                                        button.addEventListener('click', () => {
                                            // Attendre que l'impression soit terminée avant de recharger
                                            setTimeout(() => {
                                                window.location.reload();
                                            }, 2000);
                                        });
                                    });
                                }
                            });
                        } else {
                            // Afficher le message d'erreur dans un nouveau SweetAlert
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de l\'enregistrement du paiement',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        
                        let errorMessage = 'Une erreur est survenue lors de la communication avec le serveur';
                        
                        if (error.message.includes('JSON')) {
                            errorMessage = 'Erreur de format de données reçues du serveur. Veuillez réessayer.';
                        } else if (error.message.includes('réseau')) {
                            errorMessage = 'Erreur de connexion au serveur. Vérifiez votre connexion.';
                        }
                        
                        // Afficher l'erreur dans un nouveau SweetAlert
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: errorMessage,
                            confirmButtonText: 'OK'
                        });
                    });
                    
                    return false;
                });
            },
            // Options de sécurité pour empêcher la fermeture accidentelle
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            stopKeydownPropagation: true
        });
    });
    
    // Fonction globale pour supprimer un paiement
    window.supprimerPaiement = function(index) {
        paiements.splice(index, 1);
        document.getElementById('paymentList').innerHTML = generatePaymentTable();
        
        // Réactiver le bouton ajouter si nécessaire
        const confirmButton = Swal.getConfirmButton();
        if (confirmButton) {
            confirmButton.disabled = false;
        }
    };
});

//PERMET DIMPRIMER SANS REDIRECTION
        function imprimerSansNouvelOnglet(url) {
    const largeur = 800;
    const hauteur = 600;
    const left = (screen.width / 2) - (largeur / 2);
    const top = (screen.height / 2) - (hauteur / 2);

    const fenetre = window.open(url, '_blank', `width=${largeur},height=${hauteur},top=${top},left=${left}`);

    if (!fenetre) {
        alert("Le pop-up a été bloqué. Veuillez autoriser les fenêtres pop-up.");
        return;
    }

    const timer = setInterval(() => {
        if (fenetre.document.readyState === 'complete') {
            clearInterval(timer);
            fenetre.focus();
            fenetre.print();
            setTimeout(() => {
                fenetre.close();
            }, 1500);
        }
    }, 500);
}
    </script>
   
</body>

</html>