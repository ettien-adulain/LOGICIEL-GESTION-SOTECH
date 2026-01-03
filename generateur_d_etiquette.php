<?php
require_once 'db/connecting.php';
require_once 'fonction_traitement/fonction.php';
check_access(); // Protection automatique selon $DROITS_PAGES

$articles = $cnx->query("SELECT IDARTICLE, libelle, CodePersoArticle, PrixVenteTTC FROM article ORDER BY libelle")->fetchAll(PDO::FETCH_ASSOC);
$serials = [];
$selectedArticle = null;
if (isset($_GET['id_article'])) {
    $idArticle = intval($_GET['id_article']);
    $selectedArticle = $cnx->prepare("SELECT * FROM article WHERE IDARTICLE = ?");
    $selectedArticle->execute([$idArticle]);
    $selectedArticle = $selectedArticle->fetch(PDO::FETCH_ASSOC);
    $stmt = $cnx->prepare("SELECT NUMERO_SERIE FROM num_serie WHERE IDARTICLE = ? AND statut = 'disponible' ORDER BY NUMERO_SERIE");
    $stmt->execute([$idArticle]);
    $serials = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur d'étiquettes SOTech</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; padding: 0; }
        .container { max-width: 900px; margin: 30px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #0001; padding: 30px 30px 20px 30px; }
        h1 { text-align: center; color: #d84315; margin-bottom: 20px; font-weight: bold; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; color: #333; }
        select, button { padding: 8px 12px; border-radius: 5px; border: 1px solid #bbb; font-size: 1em; }
        select { width: 100%; margin-top: 5px; }
        
        /* === CHAMP DE RECHERCHE MODERNE === */
        .search-container {
            position: relative;
            margin-top: 5px;
        }
        .search-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fff;
            box-sizing: border-box;
        }
        .search-input:focus {
            outline: none;
            border-color: #d84315;
            box-shadow: 0 0 0 3px rgba(216, 67, 21, 0.1);
        }
        .search-input::placeholder {
            color: #999;
            font-style: italic;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 2px solid #d84315;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
        }
        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .search-result-item:hover {
            background: #f8f9fa;
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item.selected {
            background: #d84315;
            color: #fff;
        }
        .article-name {
            font-weight: 500;
            flex: 1;
        }
        .article-code {
            color: #666;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .search-result-item.selected .article-code {
            color: #fff;
        }
        .no-results {
            padding: 15px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        /* === STYLES POUR LE SYSTÈME DE POSITION === */
        .position-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .position-input {
            max-width: 200px;
            font-weight: 500;
            border: 2px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .position-input:focus {
            border-color: #d84315;
            box-shadow: 0 0 0 3px rgba(216, 67, 21, 0.1);
        }
        
        .position-input:valid {
            border-color: #28a745;
        }
        
        .position-input:invalid {
            border-color: #dc3545;
        }
        
        .position-info {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .position-info i {
            color: #d84315;
        }
        
        .position-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            display: none;
        }
        
        .position-preview.show {
            display: block;
        }
        
        .position-preview h6 {
            color: #d84315;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .position-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 2px;
            max-width: 300px;
        }
        
        .position-cell {
            width: 50px;
            height: 30px;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: 500;
            background: #fff;
            transition: all 0.2s ease;
        }
        
        .position-cell.used {
            background: #d84315;
            color: #fff;
            border-color: #b71c1c;
        }
        
        .position-cell.start {
            background: #28a745;
            color: #fff;
            border-color: #1e7e34;
            font-weight: bold;
        }
        
        .position-cell.available {
            background: #e9ecef;
            color: #495057;
        }
        
        .serials-list { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .serial-checkbox { background: #f1f1f1; border-radius: 5px; padding: 6px 12px; display: flex; align-items: center; font-size: 0.98em; }
        .serial-checkbox input { margin-right: 6px; }
        .actions-etiquettes { display: flex; gap: 10px; margin-bottom: 10px; justify-content: flex-end; }
        .actions-etiquettes button { background: #d84315; color: #fff; border: none; cursor: pointer; transition: background 0.2s; font-size: 1em; }
        .actions-etiquettes button:hover { background: #b71c1c; }
        .actions-etiquettes .danger { background: #000; }
        .actions-etiquettes .danger:hover { background: #333; }
        #qr-loading { display: none; text-align: center; color: #d84315; font-weight: bold; margin-bottom: 10px; }

        /* === DIMENSIONS ÉTIQUETTES STANDARD === */
        /* Format standard : 38mm x 21.2mm (3.8cm x 2.12cm) */
        /* Grille : 5 étiquettes par ligne sur A4 */
        /* Si vous avez d'autres dimensions, modifiez width/height ci-dessous */
        #labels {
            display: grid;
            grid-template-columns: repeat(5, 1fr); /* 5 étiquettes par ligne */
            grid-template-rows: repeat(13, 1fr); /* 13 lignes */
            gap: 2px;
            margin-top: 10px;
            width: 100%;
            min-height: 29.7cm; /* Hauteur A4 */
        }
        
        /* === GESTION DU POSITIONNEMENT DYNAMIQUE === */
        .label-wrapper {
            grid-column: auto;
            grid-row: auto;
        }
        
        /* Classes pour le positionnement spécifique */
        .label-wrapper.position-1 { grid-column: 1; grid-row: 1; }
        .label-wrapper.position-2 { grid-column: 2; grid-row: 1; }
        .label-wrapper.position-3 { grid-column: 3; grid-row: 1; }
        .label-wrapper.position-4 { grid-column: 4; grid-row: 1; }
        .label-wrapper.position-5 { grid-column: 5; grid-row: 1; }
        .label-wrapper.position-6 { grid-column: 1; grid-row: 2; }
        .label-wrapper.position-7 { grid-column: 2; grid-row: 2; }
        .label-wrapper.position-8 { grid-column: 3; grid-row: 2; }
        .label-wrapper.position-9 { grid-column: 4; grid-row: 2; }
        .label-wrapper.position-10 { grid-column: 5; grid-row: 2; }
        
        /* Espaces vides pour les positions non utilisées */
        .empty-position {
            grid-column: auto;
            grid-row: auto;
            width: 100%;
            height: 100%;
            background: transparent;
            border: none;
            display: block;
        }
        .label-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .remove-label {
            margin-bottom: 2px;
            color: #d84315;
            background: #fff;
            border: none;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            border-radius: 2px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .remove-label:hover {
            opacity: 1;
            background: #ffeaea;
        }
        .label {
            width: 3.8cm; /* 38mm - DIMENSION STANDARD */
            height: 2.12cm; /* 21.2mm - DIMENSION STANDARD */
            background: #fff;
            border: 1px solid #000;
            border-radius: 2px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
            padding: 1px;
        }

        /* === DESIGN ROUGE/NOIR/BLANC === */
        .label .serial {
            font-size: 0.55em;
            font-weight: bold;
            color: #000;
            margin: 1px 0 1px 0;
            text-align: center;
            width: 98%;
            white-space: normal;
            word-break: break-all;
            overflow-wrap: break-word;
            line-height: 1.1;
            padding: 1px 2px;
            border-radius: 2px;
            z-index: 3;
            position: relative;
            border: none;
            background: none;
            box-shadow: none;
        }
        .barcode-row {
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: flex-end;
            justify-content: flex-start;
            margin-bottom: 0px;
            margin-top: 1px;
            position: relative;
        }
        .barcode-container {
            width: 75%;
            min-width: 60px;
            max-width: 90px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin: 0;
            position: relative;
        }
        .barcode {
            width: 100%;
            height: 28px;
            margin: 0;
            display: block;
        }
        .logo-vertical {
            position: absolute;
            right: 1px;
            bottom: 1px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #000;
            border-radius: 2px;
            padding: 1px;
            box-shadow: 0 1px 2px #0002;
            z-index: 10;
            min-width: 10px;
            max-width: 14px;
        }
        .logo-vertical span {
            font-size: 0.45em;
            font-family: Arial, sans-serif;
            font-weight: bold;
            letter-spacing: 0.1em;
            line-height: 1.1em;
            display: block;
            text-align: center;
        }
        .logo-vertical .red { color: #d84315; }
        .logo-vertical .white { color: #fff; }
        .art-code-barre {
            font-size: 0.45em;
            color: #d84315;
            text-align: center;
            width: 100%;
            margin: 1px 0 1px 0;
            letter-spacing: 0.04em;
            word-break: break-all;
            font-weight: bold;
        }
        .art-header {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 0px;
        }
        .art-name {
            font-size: 0.6em;
            font-weight: bold;
            color: #000;
            text-align: center;
            width: 100%;
            white-space: normal;
            word-break: break-word;
            line-height: 1.1;
            max-height: 1.2em;
            overflow: hidden;
        }
        .art-price {
            font-size: 0.55em;
            color: #d84315;
            font-weight: bold;
            text-align: center;
            width: 100%;
            margin-top: 0.5px;
        }

        /* === IMPRESSION OPTIMISÉE === */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            body { 
                background: #fff !important; 
                margin: 0 !important; 
                padding: 0 !important;
                font-family: Arial, sans-serif !important;
            }
            
            .container { 
                background: #fff !important; 
                box-shadow: none !important; 
                margin: 0 !important;
                padding: 0 !important;
                border-radius: 0 !important;
                max-width: none !important;
            }
            
            /* Masquer tous les éléments non nécessaires à l'impression */
            .actions, 
            .form-group, 
            .serials-list, 
            .actions-etiquettes, 
            .remove-label, 
            .position-preview,
            .search-container,
            .search-results,
            h1,
            #qr-loading,
            .user-info,
            .navigation-buttons,
            .btn,
            button:not(.remove-label),
            nav,
            .navbar,
            .nav,
            .nav-link,
            .navbar-nav,
            .navbar-brand,
            .fas,
            .fa,
            i[class*="fa"],
            i[class*="fas"],
            i[class*="far"],
            .icon,
            .glyphicon,
            [class*="icon-"],
            [class*="fa-"],
            header { 
                display: none !important; 
            }
            
        /* Styles pour la grille d'étiquettes */
        #labels { 
            margin: 0 !important; 
            padding: 0 !important;
            gap: 0 !important; 
            display: grid !important;
            grid-template-columns: repeat(5, 1fr) !important;
            grid-template-rows: repeat(13, 1fr) !important;
            width: 100% !important;
            height: 100vh !important;
            min-height: 29.7cm !important;
        }
            
            /* Styles pour les étiquettes */
            .label-wrapper { 
                margin: 0 !important; 
                padding: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                height: 100% !important;
                box-sizing: border-box !important;
                grid-column: auto !important;
                grid-row: auto !important;
            }
            
            .label { 
                page-break-inside: avoid !important; 
                box-shadow: none !important;
                border: 1px solid #000 !important;
                width: 100% !important;
                height: 100% !important;
                background: #fff !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: flex-start !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                position: relative !important;
                padding: 1px !important;
            }
            
            /* Masquer les espaces vides */
            .empty-position {
                display: none !important;
            }
            
            /* Styles pour le contenu des étiquettes */
            .label .serial {
                font-size: 0.55em !important;
                font-weight: bold !important;
                color: #000 !important;
                margin: 1px 0 !important;
                text-align: center !important;
                width: 98% !important;
                line-height: 1.1 !important;
                padding: 1px 2px !important;
            }
            
            .label .barcode-row {
                width: 100% !important;
                display: flex !important;
                flex-direction: row !important;
                align-items: flex-end !important;
                justify-content: flex-start !important;
                margin: 1px 0 0 0 !important;
            }
            
            .label .barcode-container {
                width: 75% !important;
                height: 28px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
            }
            
            .label .barcode {
                width: 100% !important;
                height: 28px !important;
                margin: 0 !important;
                display: block !important;
            }
            
            .label .art-code-barre {
                font-size: 0.45em !important;
                color: #d84315 !important;
                text-align: center !important;
                width: 100% !important;
                margin: 1px 0 !important;
                font-weight: bold !important;
            }
            
            .label .art-header {
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                margin-bottom: 0 !important;
            }
            
            .label .art-name {
                font-size: 0.6em !important;
                font-weight: bold !important;
                color: #000 !important;
                text-align: center !important;
                width: 100% !important;
                line-height: 1.1 !important;
                max-height: 1.2em !important;
                overflow: hidden !important;
            }
            
            .label .art-price {
                font-size: 0.55em !important;
                color: #d84315 !important;
                font-weight: bold !important;
                text-align: center !important;
                width: 100% !important;
                margin-top: 0.5px !important;
            }
            
            .label .logo-vertical {
                position: absolute !important;
                right: 1px !important;
                bottom: 1px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                background: #000 !important;
                border-radius: 2px !important;
                padding: 1px !important;
                min-width: 10px !important;
                max-width: 14px !important;
            }
            
            .label .logo-vertical span {
                font-size: 0.45em !important;
                font-family: Arial, sans-serif !important;
                font-weight: bold !important;
                letter-spacing: 0.1em !important;
                line-height: 1.1em !important;
                display: block !important;
                text-align: center !important;
            }
            
            .label .logo-vertical .red { 
                color: #d84315 !important; 
            }
            
            .label .logo-vertical .white { 
                color: #fff !important; 
            }
            
            /* Masquer l'indicateur de position à l'impression */
            .position-indicator {
                display: none !important;
            }
        }
        
        
        /* === MASQUAGE DES BOUTONS DE NAVIGATION === */
        @media print {
            .user-info,
            .navigation-buttons,
            .btn,
            button,
            .form-group,
            .actions,
            .serials-list,
            .actions-etiquettes,
            .position-preview,
            .search-container,
            .search-results,
            h1,
            #qr-loading,
            nav,
            .navbar,
            .nav,
            .nav-link,
            .navbar-nav,
            .navbar-brand,
            .fas,
            .fa,
            i[class*="fa"],
            i[class*="fas"],
            i[class*="far"],
            .icon,
            .glyphicon,
            [class*="icon-"],
            [class*="fa-"],
            header {
                display: none !important;
                visibility: hidden !important;
            }
        }
        
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
</head>
<body>
<div class="container">
    <h1>Générateur d'étiquettes SOTech</h1>
    
    <!-- Champ de recherche moderne -->
                            <div class="form-group">
        <label for="article-search">Rechercher un article :</label>
        <div class="search-container">
            <input type="text" 
                   id="article-search" 
                   class="search-input" 
                   placeholder="Tapez le nom de l'article (minimum 2 caractères)..."
                   autocomplete="off">
            <div id="search-results" class="search-results"></div>
                            </div>
                        </div>

    <?php if ($selectedArticle): ?>
        <form id="serialForm">
            <!-- Configuration de position d'impression -->
            <div class="form-group">
                <label for="start-position">Position de départ d'impression :</label>
                <div class="position-controls">
                    <input type="number" 
                           id="start-position" 
                           name="start_position" 
                           min="1" 
                           max="65" 
                           value="1" 
                           class="form-control position-input"
                           placeholder="Position de départ (1-65)">
                    <div class="position-info">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Grille A4 : 5 colonnes × 13 lignes = 65 positions
                        </small>
                    </div>
                </div>
                
                <!-- Aperçu visuel de la position -->
                <div id="position-preview" class="position-preview">
                    <h6><i class="fas fa-eye"></i> Aperçu de la position de départ</h6>
                    <div id="position-grid" class="position-grid"></div>
                    <small class="text-muted mt-2 d-block">
                        <span class="text-success">●</span> Position de départ | 
                        <span class="text-danger">●</span> Positions utilisées | 
                        <span class="text-secondary">●</span> Positions disponibles
                    </small>
                </div>
            </div>
            
            <div class="form-group">
                <label>Numéros de série disponibles :</label>
                <div class="serials-list">
                    <?php if (count($serials) > 0): ?>
                        <?php foreach ($serials as $num): ?>
                            <label class="serial-checkbox">
                                <input type="checkbox" name="serials[]" value="<?= htmlspecialchars($num) ?>">
                                <?= htmlspecialchars($num) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#d84315;">Aucun numéro de série disponible.</span>
                    <?php endif; ?>
                                </div>
                            </div>
            <div class="actions">
                <?php echo bouton_action('Générer les étiquettes', 'generateur_d_etiquette', 'voir', 'btn btn-primary', 'type="button" onclick="generateLabels()"'); ?>
                        </div>
        </form>
    <?php endif; ?>
    
    <div class="actions-etiquettes">
        <?php echo bouton_action('Imprimer', 'generateur_d_etiquette', 'voir', 'btn btn-primary', 'type="button" onclick="printLabels()"'); ?>
        <?php echo bouton_action('Vider toutes les étiquettes', 'generateur_d_etiquette', 'voir', 'btn btn-danger', 'type="button" onclick="clearAllLabels()"'); ?>
                                    </div>
    <div id="qr-loading">Génération des QR Codes, veuillez patienter…</div>
    <div id="labels"></div>
                    </div>

                    <script>
// Données des articles pour la recherche
const articles = <?php echo json_encode($articles); ?>;
let selectedArticle = <?php echo json_encode($selectedArticle); ?>;

// === SYSTÈME DE POSITIONNEMENT ===
let currentStartPosition = 1;
let usedPositions = new Set(); // Positions déjà utilisées

// Éléments DOM
const searchInput = document.getElementById('article-search');
const searchResults = document.getElementById('search-results');
let selectedIndex = -1;
let filteredArticles = [];

// Fonction de recherche
function searchArticles(query) {
    if (query.length < 2) {
        searchResults.style.display = 'none';
        return;
    }
    
    const searchTerm = query.toLowerCase();
    filteredArticles = articles.filter(article => 
        article.libelle.toLowerCase().includes(searchTerm) ||
        article.CodePersoArticle.toLowerCase().includes(searchTerm)
    );
    
    displayResults();
}

// Affichage des résultats
function displayResults() {
    if (filteredArticles.length === 0) {
        searchResults.innerHTML = '<div class="no-results">Aucun article trouvé</div>';
        searchResults.style.display = 'block';
        return;
    }
    
    searchResults.innerHTML = filteredArticles.map((article, index) => `
        <div class="search-result-item" data-id="${article.IDARTICLE}" data-index="${index}">
            <div class="article-name">${article.libelle}</div>
            <div class="article-code">${article.CodePersoArticle}</div>
        </div>
    `).join('');
    
    searchResults.style.display = 'block';
    selectedIndex = -1;
}

// Sélection d'un article
function selectArticle(articleId) {
    const article = articles.find(a => a.IDARTICLE == articleId);
    if (article) {
        selectedArticle = article;
        searchInput.value = `${article.libelle} (${article.CodePersoArticle})`;
        searchResults.style.display = 'none';
        
        // Rediriger vers la page avec l'article sélectionné
        window.location.href = `?id_article=${article.IDARTICLE}`;
    }
}

// Gestion des événements clavier
searchInput.addEventListener('keydown', function(e) {
    const items = searchResults.querySelectorAll('.search-result-item');
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
        updateSelection(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, -1);
        updateSelection(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedIndex >= 0 && items[selectedIndex]) {
            const articleId = items[selectedIndex].getAttribute('data-id');
            selectArticle(articleId);
        }
    } else if (e.key === 'Escape') {
        searchResults.style.display = 'none';
        selectedIndex = -1;
    }
});

// Mise à jour de la sélection visuelle
function updateSelection(items) {
    items.forEach((item, index) => {
        item.classList.toggle('selected', index === selectedIndex);
    });
}

// Gestion des clics sur les résultats
searchResults.addEventListener('click', function(e) {
    const item = e.target.closest('.search-result-item');
    if (item) {
        const articleId = item.getAttribute('data-id');
        selectArticle(articleId);
    }
});

// Recherche en temps réel
searchInput.addEventListener('input', function() {
    searchArticles(this.value);
});

// Fermer les résultats en cliquant ailleurs
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
        selectedIndex = -1;
    }
});

// Pré-remplir le champ si un article est déjà sélectionné
if (selectedArticle) {
    searchInput.value = `${selectedArticle.libelle} (${selectedArticle.CodePersoArticle})`;
}

// === FONCTIONS DE GESTION DE POSITION ===

// Calculer la position dans la grille (ligne, colonne) à partir du numéro de position
function getGridPosition(positionNumber) {
    const row = Math.ceil(positionNumber / 5);
    const col = ((positionNumber - 1) % 5) + 1;
    return { row, col };
}

// Calculer le numéro de position à partir de la ligne et colonne
function getPositionNumber(row, col) {
    return (row - 1) * 5 + col;
}

// Générer l'aperçu visuel de la grille de positions
function generatePositionPreview(startPosition) {
    const preview = document.getElementById('position-preview');
    const grid = document.getElementById('position-grid');
    
    if (!preview || !grid) return;
    
    grid.innerHTML = '';
    
    // Créer 65 cellules (5 colonnes × 13 lignes)
    for (let i = 1; i <= 65; i++) {
        const cell = document.createElement('div');
        cell.className = 'position-cell';
        cell.textContent = i;
        
        if (i < startPosition) {
            cell.classList.add('used');
        } else if (i === startPosition) {
            cell.classList.add('start');
        } else {
            cell.classList.add('available');
        }
        
        grid.appendChild(cell);
    }
    
    preview.classList.add('show');
}

// Valider la position de départ
function validateStartPosition(position) {
    const pos = parseInt(position);
    if (isNaN(pos) || pos < 1 || pos > 65) {
        return false;
    }
    return true;
}

// Gestionnaire d'événement pour le champ de position
document.addEventListener('DOMContentLoaded', function() {
    const startPositionInput = document.getElementById('start-position');
    
    if (startPositionInput) {
        // Générer l'aperçu initial
        generatePositionPreview(parseInt(startPositionInput.value));
        
        // Écouter les changements
        startPositionInput.addEventListener('input', function() {
            const position = parseInt(this.value);
            
            if (validateStartPosition(position)) {
                currentStartPosition = position;
                generatePositionPreview(position);
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
        
        // Écouter les changements de focus
        startPositionInput.addEventListener('blur', function() {
            if (!validateStartPosition(this.value)) {
                this.value = currentStartPosition;
                generatePositionPreview(currentStartPosition);
            }
        });
    }
});

function formatPrixIvoirien(val) {
    return parseInt(val).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F CFA';
}
// Gestion centralisée des étiquettes
function getAllLabels() {
    return Array.from(document.querySelectorAll('.label-wrapper'));
}
function saveLabels() {
    localStorage.setItem('sotech_labels', document.getElementById('labels').innerHTML);
}
function attachRemoveEvents() {
    getAllLabels().forEach(wrapper => {
        const btn = wrapper.querySelector('.remove-label');
        if (btn) {
            btn.onclick = function(e) {
                e.stopPropagation();
                wrapper.remove();
                saveLabels();
            };
        }
    });
}
function generateLabels() {
    let serials = Array.from(document.querySelectorAll('input[name="serials[]"]:checked')).map(e => e.value);
    serials = [...new Set(serials)];
    const labelsDiv = document.getElementById('labels');
    
    // Récupérer la position de départ
    const startPositionInput = document.getElementById('start-position');
    const startPosition = startPositionInput ? parseInt(startPositionInput.value) : 1;
    
    if (!validateStartPosition(startPosition)) {
        Swal.fire({
            title: 'Position invalide',
            text: 'Veuillez entrer un nombre entre 1 et 65.',
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d84315'
        });
        return;
    }
    
    // On conserve les anciennes étiquettes déjà présentes
    let tempDiv = document.createElement('div');
    tempDiv.innerHTML = labelsDiv.innerHTML;
    let existingSerials = Array.from(tempDiv.querySelectorAll('.serial')).map(e => e.textContent);
    let art = selectedArticle;
    let newSerials = serials.filter(num => !existingSerials.includes(num));
    
    if (newSerials.length === 0) {
        Swal.fire({
            title: 'Aucune étiquette',
            text: 'Aucune nouvelle étiquette à ajouter (déjà présentes ou rien de sélectionné).',
            icon: 'info',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d84315'
        });
        return;
    }
    
    // Vérifier si on a assez de positions disponibles
    const totalPositionsNeeded = newSerials.length;
    const availablePositions = 65 - startPosition + 1;
    
    if (totalPositionsNeeded > availablePositions) {
        Swal.fire({
            title: 'Positions insuffisantes',
            text: `Pas assez de positions disponibles. Vous avez ${availablePositions} positions libres à partir de la position ${startPosition}, mais vous voulez imprimer ${totalPositionsNeeded} étiquettes.`,
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d84315'
        });
        return;
    }
    
    // Créer d'abord toutes les positions vides (1 à 65)
    for (let i = 1; i <= 65; i++) {
        const emptyDiv = document.createElement('div');
        const gridPos = getGridPosition(i);
        emptyDiv.className = 'empty-position';
        emptyDiv.style.gridColumn = gridPos.col;
        emptyDiv.style.gridRow = gridPos.row;
        emptyDiv.style.width = '100%';
        emptyDiv.style.height = '100%';
        tempDiv.appendChild(emptyDiv);
    }
    
    // Générer les étiquettes avec positionnement exact
    newSerials.forEach((num, index) => {
        console.log("Numéro de série généré:", num);
        const wrapper = document.createElement('div');
        
        // Calculer la position de cette étiquette
        const currentPosition = startPosition + index;
        const gridPos = getGridPosition(currentPosition);
        
        // Appliquer les classes de positionnement CSS
        wrapper.className = `label-wrapper position-${currentPosition}`;
        wrapper.style.gridColumn = gridPos.col;
        wrapper.style.gridRow = gridPos.row;
        wrapper.style.width = '100%';
        wrapper.style.height = '100%';
        
        // On stocke le code perso article dans une variable pour éviter toute ambiguïté
        const codePerso = art.CodePersoArticle;
        const serialText = num && num.trim() ? num : '(num vide)';
        wrapper.innerHTML = `
            <button type="button" class="remove-label" title="Supprimer cette étiquette">✖</button>
            <div class="label" data-position="${currentPosition}">
                <div class="position-indicator" style="position: absolute; top: 1px; left: 1px; font-size: 0.3em; color: #666; background: rgba(255,255,255,0.8); padding: 1px 2px; border-radius: 2px;">P${currentPosition}</div>
                <div class="serial">${serialText}</div>
                <div class="barcode-row">
                    <div class="barcode-container"><svg class="barcode" data-codeperso="${codePerso}"></svg></div>
                </div>
                <div class="art-code-barre">${codePerso}</div>
                <div class="art-header">
                    <div class="art-name">${art.libelle}</div>
                    <div class="art-price">${formatPrixIvoirien(art.PrixVenteTTC)}</div>
                </div>
                <div class="logo-vertical">
                        <span class="red">S</span>
                        <span class="red">O</span>
                        <span class="white">T</span>
                        <span class="white">e</span>
                        <span class="white">c</span>
                        <span class="white">h</span>
                    </div>
            </div>
        `;
        
        // Remplacer la position vide par l'étiquette
        const emptyPosition = tempDiv.querySelector(`.empty-position:nth-child(${currentPosition})`);
        if (emptyPosition) {
            tempDiv.replaceChild(wrapper, emptyPosition);
        } else {
            tempDiv.appendChild(wrapper);
        }
    });
    labelsDiv.innerHTML = tempDiv.innerHTML;
    // Générer les codes-barres pour les nouvelles étiquettes
    getAllLabels().forEach(wrapper => {
        let label = wrapper.querySelector('.label');
        let barcodeSvg = label.querySelector('.barcode');
        // On récupère le code perso article depuis l'attribut data-codeperso pour être sûr
        const codePerso = barcodeSvg?.getAttribute('data-codeperso') || '';
        console.log("CodePerso utilisé pour le code-barres:", codePerso);
        if (barcodeSvg && barcodeSvg.childElementCount === 0) {
            JsBarcode(barcodeSvg, codePerso, {
                format: "CODE128",
                lineColor: "#000",
                width: 1,
                height: 42,
                displayValue: false,
                margin: 0
            });
        }
    });
    attachRemoveEvents();
    saveLabels();
}
function clearAllLabels() {
    Swal.fire({
        title: 'Supprimer toutes les étiquettes ?',
        text: 'Cette action est irréversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('labels').innerHTML = '';
            localStorage.removeItem('sotech_labels');
            Swal.fire({
                title: 'Supprimé !',
                text: 'Toutes les étiquettes ont été supprimées.',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#d84315'
            });
        }
    });
}

function printLabels() {
    if (getAllLabels().length === 0) {
        Swal.fire({
            title: 'Aucune étiquette',
            text: "Générez d'abord les étiquettes.",
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d84315'
        });
        return;
    }
    
    // Détection mobile
    const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (isMobile) {
        // Méthode mobile : impression directe
        printLabelsMobile();
    } else {
        // Méthode PC : fenêtre popup
        printLabelsDesktop();
    }
}

function printLabelsMobile() {
    const labelsContainer = document.getElementById('labels');
    
    // Cloner le contenu en préservant les positions exactes
    const clonedContainer = labelsContainer.cloneNode(true);
    
    // Masquer les boutons de suppression dans le clone
    const removeButtons = clonedContainer.querySelectorAll('.remove-label');
    removeButtons.forEach(btn => btn.style.display = 'none');
    
    // Masquer les espaces vides
    const emptyPositions = clonedContainer.querySelectorAll('.empty-position');
    emptyPositions.forEach(pos => pos.style.display = 'none');
    
    // Masquer les indicateurs de position
    const positionIndicators = clonedContainer.querySelectorAll('.position-indicator');
    positionIndicators.forEach(indicator => indicator.style.display = 'none');
    
    // PRÉSERVER LES POSITIONS EXACTES - Copier les styles inline
    const originalWrappers = labelsContainer.querySelectorAll('.label-wrapper');
    const clonedWrappers = clonedContainer.querySelectorAll('.label-wrapper');
    
    originalWrappers.forEach((original, index) => {
        if (clonedWrappers[index]) {
            // Copier les styles inline de positionnement
            const computedStyle = window.getComputedStyle(original);
            clonedWrappers[index].style.gridColumn = computedStyle.gridColumn;
            clonedWrappers[index].style.gridRow = computedStyle.gridRow;
            clonedWrappers[index].style.width = computedStyle.width;
            clonedWrappers[index].style.height = computedStyle.height;
            
            // Copier les classes de position
            clonedWrappers[index].className = original.className;
            
            // Copier les attributs de position
            if (original.dataset.position) {
                clonedWrappers[index].dataset.position = original.dataset.position;
            }
        }
    });
    
    // Créer un élément temporaire pour l'impression avec positions préservées
    const printContent = document.createElement('div');
    printContent.innerHTML = `
        <div style="display: none;" id="printContent">
            <style>
                @media print {
                    body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                    #print-labels { 
                        display: grid !important;
                        grid-template-columns: repeat(5, 1fr) !important;
                        grid-template-rows: repeat(13, 1fr) !important;
                        gap: 0 !important;
                        width: 100% !important;
                        height: 100vh !important;
                        min-height: 29.7cm !important;
                    }
                    .label-wrapper { 
                        width: 100% !important; 
                        height: 100% !important;
                        display: flex !important; 
                        flex-direction: column !important;
                        align-items: center !important; 
                        justify-content: center !important;
                        /* PRÉSERVER LES POSITIONS EXACTES - Styles inline prioritaires */
                        grid-column: inherit !important;
                        grid-row: inherit !important;
                    }
                    
                    /* Classes de position spécifiques pour mobile */
                    .label-wrapper.position-1 { grid-column: 1 !important; grid-row: 1 !important; }
                    .label-wrapper.position-2 { grid-column: 2 !important; grid-row: 1 !important; }
                    .label-wrapper.position-3 { grid-column: 3 !important; grid-row: 1 !important; }
                    .label-wrapper.position-4 { grid-column: 4 !important; grid-row: 1 !important; }
                    .label-wrapper.position-5 { grid-column: 5 !important; grid-row: 1 !important; }
                    .label-wrapper.position-6 { grid-column: 1 !important; grid-row: 2 !important; }
                    .label-wrapper.position-7 { grid-column: 2 !important; grid-row: 2 !important; }
                    .label-wrapper.position-8 { grid-column: 3 !important; grid-row: 2 !important; }
                    .label-wrapper.position-9 { grid-column: 4 !important; grid-row: 2 !important; }
                    .label-wrapper.position-10 { grid-column: 5 !important; grid-row: 2 !important; }
                    .label-wrapper.position-11 { grid-column: 1 !important; grid-row: 3 !important; }
                    .label-wrapper.position-12 { grid-column: 2 !important; grid-row: 3 !important; }
                    .label-wrapper.position-13 { grid-column: 3 !important; grid-row: 3 !important; }
                    .label-wrapper.position-14 { grid-column: 4 !important; grid-row: 3 !important; }
                    .label-wrapper.position-15 { grid-column: 5 !important; grid-row: 3 !important; }
                    .label-wrapper.position-16 { grid-column: 1 !important; grid-row: 4 !important; }
                    .label-wrapper.position-17 { grid-column: 2 !important; grid-row: 4 !important; }
                    .label-wrapper.position-18 { grid-column: 3 !important; grid-row: 4 !important; }
                    .label-wrapper.position-19 { grid-column: 4 !important; grid-row: 4 !important; }
                    .label-wrapper.position-20 { grid-column: 5 !important; grid-row: 4 !important; }
                    .label-wrapper.position-21 { grid-column: 1 !important; grid-row: 5 !important; }
                    .label-wrapper.position-22 { grid-column: 2 !important; grid-row: 5 !important; }
                    .label-wrapper.position-23 { grid-column: 3 !important; grid-row: 5 !important; }
                    .label-wrapper.position-24 { grid-column: 4 !important; grid-row: 5 !important; }
                    .label-wrapper.position-25 { grid-column: 5 !important; grid-row: 5 !important; }
                    .label-wrapper.position-26 { grid-column: 1 !important; grid-row: 6 !important; }
                    .label-wrapper.position-27 { grid-column: 2 !important; grid-row: 6 !important; }
                    .label-wrapper.position-28 { grid-column: 3 !important; grid-row: 6 !important; }
                    .label-wrapper.position-29 { grid-column: 4 !important; grid-row: 6 !important; }
                    .label-wrapper.position-30 { grid-column: 5 !important; grid-row: 6 !important; }
                    .label-wrapper.position-31 { grid-column: 1 !important; grid-row: 7 !important; }
                    .label-wrapper.position-32 { grid-column: 2 !important; grid-row: 7 !important; }
                    .label-wrapper.position-33 { grid-column: 3 !important; grid-row: 7 !important; }
                    .label-wrapper.position-34 { grid-column: 4 !important; grid-row: 7 !important; }
                    .label-wrapper.position-35 { grid-column: 5 !important; grid-row: 7 !important; }
                    .label-wrapper.position-36 { grid-column: 1 !important; grid-row: 8 !important; }
                    .label-wrapper.position-37 { grid-column: 2 !important; grid-row: 8 !important; }
                    .label-wrapper.position-38 { grid-column: 3 !important; grid-row: 8 !important; }
                    .label-wrapper.position-39 { grid-column: 4 !important; grid-row: 8 !important; }
                    .label-wrapper.position-40 { grid-column: 5 !important; grid-row: 8 !important; }
                    .label-wrapper.position-41 { grid-column: 1 !important; grid-row: 9 !important; }
                    .label-wrapper.position-42 { grid-column: 2 !important; grid-row: 9 !important; }
                    .label-wrapper.position-43 { grid-column: 3 !important; grid-row: 9 !important; }
                    .label-wrapper.position-44 { grid-column: 4 !important; grid-row: 9 !important; }
                    .label-wrapper.position-45 { grid-column: 5 !important; grid-row: 9 !important; }
                    .label-wrapper.position-46 { grid-column: 1 !important; grid-row: 10 !important; }
                    .label-wrapper.position-47 { grid-column: 2 !important; grid-row: 10 !important; }
                    .label-wrapper.position-48 { grid-column: 3 !important; grid-row: 10 !important; }
                    .label-wrapper.position-49 { grid-column: 4 !important; grid-row: 10 !important; }
                    .label-wrapper.position-50 { grid-column: 5 !important; grid-row: 10 !important; }
                    .label-wrapper.position-51 { grid-column: 1 !important; grid-row: 11 !important; }
                    .label-wrapper.position-52 { grid-column: 2 !important; grid-row: 11 !important; }
                    .label-wrapper.position-53 { grid-column: 3 !important; grid-row: 11 !important; }
                    .label-wrapper.position-54 { grid-column: 4 !important; grid-row: 11 !important; }
                    .label-wrapper.position-55 { grid-column: 5 !important; grid-row: 11 !important; }
                    .label-wrapper.position-56 { grid-column: 1 !important; grid-row: 12 !important; }
                    .label-wrapper.position-57 { grid-column: 2 !important; grid-row: 12 !important; }
                    .label-wrapper.position-58 { grid-column: 3 !important; grid-row: 12 !important; }
                    .label-wrapper.position-59 { grid-column: 4 !important; grid-row: 12 !important; }
                    .label-wrapper.position-60 { grid-column: 5 !important; grid-row: 12 !important; }
                    .label-wrapper.position-61 { grid-column: 1 !important; grid-row: 13 !important; }
                    .label-wrapper.position-62 { grid-column: 2 !important; grid-row: 13 !important; }
                    .label-wrapper.position-63 { grid-column: 3 !important; grid-row: 13 !important; }
                    .label-wrapper.position-64 { grid-column: 4 !important; grid-row: 13 !important; }
                    .label-wrapper.position-65 { grid-column: 5 !important; grid-row: 13 !important; }
                    .label { 
                        width: 100% !important; 
                        height: 100% !important;
                        border: 1px solid #000 !important; 
                        background: white !important;
                        display: flex !important; 
                        flex-direction: column !important;
                        align-items: center !important; 
                        justify-content: flex-start !important;
                        box-sizing: border-box !important; 
                        overflow: hidden !important;
                        position: relative !important; 
                        padding: 1px !important;
                    }
                    .label .serial { font-size: 0.55em !important; font-weight: bold !important; color: #000 !important; margin: 1px 0 !important; text-align: center !important; width: 98% !important; line-height: 1.1 !important; padding: 1px 2px !important; }
                    .label .barcode-row { width: 100% !important; display: flex !important; flex-direction: row !important; align-items: flex-end !important; justify-content: flex-start !important; margin: 1px 0 0 0 !important; }
                    .label .barcode-container { width: 75% !important; height: 28px !important; display: flex !important; align-items: center !important; justify-content: flex-start !important; }
                    .label .barcode { width: 100% !important; height: 28px !important; margin: 0 !important; display: block !important; }
                    .label .art-code-barre { font-size: 0.45em !important; color: #d84315 !important; text-align: center !important; width: 100% !important; margin: 1px 0 !important; font-weight: bold !important; }
                    .label .art-header { width: 100% !important; display: flex !important; flex-direction: column !important; align-items: center !important; margin-bottom: 0 !important; }
                    .label .art-name { font-size: 0.6em !important; font-weight: bold !important; color: #000 !important; text-align: center !important; width: 100% !important; line-height: 1.1 !important; max-height: 1.2em !important; overflow: hidden !important; }
                    .label .art-price { font-size: 0.55em !important; color: #d84315 !important; font-weight: bold !important; text-align: center !important; width: 100% !important; margin-top: 0.5px !important; }
                    .label .logo-vertical { position: absolute !important; right: 1px !important; bottom: 1px !important; display: flex !important; flex-direction: column !important; align-items: center !important; background: #000 !important; border-radius: 2px !important; padding: 1px !important; min-width: 10px !important; max-width: 14px !important; }
                    .label .logo-vertical span { font-size: 0.45em !important; font-family: Arial, sans-serif !important; font-weight: bold !important; letter-spacing: 0.1em !important; line-height: 1.1em !important; display: block !important; text-align: center !important; }
                    .label .logo-vertical .red { color: #d84315 !important; }
                    .label .logo-vertical .white { color: #fff !important; }
                    .position-indicator, .empty-position, .remove-label { display: none !important; }
                    
                    /* Masquer tous les éléments de navigation et icônes */
                    .user-info, .navigation-buttons, nav, .navbar, .nav, .nav-link, 
                    .navbar-nav, .navbar-brand, .fas, .fa, i[class*="fa"], 
                    i[class*="fas"], i[class*="far"], .icon, .glyphicon, 
                    [class*="icon-"], [class*="fa-"], .btn, button, .form-group, 
                    .alert, h1, h2, .pagination, .mb-3, .row, .col-sm-6, 
                    .form-label, .form-control, .form-select, header, 
                    .alert-info, #printButton { 
                        display: none !important; 
                        visibility: hidden !important; 
                    }
                    
                    @page { margin: 0; size: A4; }
                }
                @media screen { #printContent { display: block !important; } }
            </style>
            <div id="print-labels">
                ${clonedContainer.innerHTML}
            </div>
        </div>
    `;
    
    document.body.appendChild(printContent);
    
    // Déclencher l'impression
    setTimeout(() => {
        window.print();
        // Nettoyer après impression et restaurer la page
        setTimeout(() => {
            document.getElementById('printContent').remove();
            
            // Restaurer tous les éléments masqués
            const allHiddenElements = document.querySelectorAll('*');
            allHiddenElements.forEach(el => {
                if (el.style.display === 'none') {
                    el.style.display = '';
                }
            });
            
            // S'assurer que le conteneur d'étiquettes est visible
            const labelsContainer = document.getElementById('labels');
            if (labelsContainer) {
                labelsContainer.style.display = 'grid';
            }
            
            // Restaurer les boutons d'action
            const actionButtons = document.querySelectorAll('.actions-etiquettes, .btn, button');
            actionButtons.forEach(btn => {
                btn.style.display = '';
            });
            
            // Restaurer les formulaires
            const forms = document.querySelectorAll('.form-group, .form-control, .form-select');
            forms.forEach(form => {
                form.style.display = '';
            });
            
            // Restaurer les éléments de navigation
            const navElements = document.querySelectorAll('.user-info, .navigation-buttons, nav, .navbar');
            navElements.forEach(nav => {
                nav.style.display = '';
            });
            
            // Restaurer le header
            const header = document.querySelector('header');
            if (header) {
                header.style.display = '';
            }
            
            // Restaurer les titres
            const titles = document.querySelectorAll('h1, h2');
            titles.forEach(title => {
                title.style.display = '';
            });
            
            // Restaurer les lignes et colonnes
            const rows = document.querySelectorAll('.row, .col-sm-6, .mb-3');
            rows.forEach(row => {
                row.style.display = '';
            });
        }, 1000);
    }, 100);
}

function printLabelsDesktop() {
    // Créer une nouvelle fenêtre pour l'impression avec décalages respectés
    const printWindow = window.open('', '_blank');
    const labelsContainer = document.getElementById('labels');
    
    if (!printWindow || printWindow.closed) {
        // Fallback si popup bloqué
        alert('Pop-up bloqué. Utilisation de l\'impression directe...');
        printLabelsMobile();
        return;
    }
    
    // Cloner le contenu des étiquettes
    const clonedLabels = labelsContainer.cloneNode(true);
    
    // Masquer les boutons de suppression dans le clone
    const removeButtons = clonedLabels.querySelectorAll('.remove-label');
    removeButtons.forEach(btn => btn.style.display = 'none');
    
    // Masquer les espaces vides
    const emptyPositions = clonedLabels.querySelectorAll('.empty-position');
    emptyPositions.forEach(pos => pos.style.display = 'none');
    
    // Masquer les indicateurs de position
    const positionIndicators = clonedLabels.querySelectorAll('.position-indicator');
    positionIndicators.forEach(indicator => indicator.style.display = 'none');
    
    // HTML pour la fenêtre d'impression
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Impression Étiquettes SOTech</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    background: white;
                    width: 21cm;
                    height: 29.7cm;
                }
                
                #print-labels {
                    display: grid;
                    grid-template-columns: repeat(5, 1fr);
                    grid-template-rows: repeat(13, 1fr);
                    gap: 0;
                    width: 100%;
                    height: 100vh;
                    min-height: 29.7cm;
                }
                
                .label-wrapper {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                }
                
                .label {
                    width: 100%;
                    height: 100%;
                    border: 1px solid #000;
                    background: white;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    box-sizing: border-box;
                    overflow: hidden;
                    position: relative;
                    padding: 1px;
                }
                
                .label .serial {
                    font-size: 0.55em;
                    font-weight: bold;
                    color: #000;
                    margin: 1px 0;
                    text-align: center;
                    width: 98%;
                    line-height: 1.1;
                    padding: 1px 2px;
                }
                
                .label .barcode-row {
                    width: 100%;
                    display: flex;
                    flex-direction: row;
                    align-items: flex-end;
                    justify-content: flex-start;
                    margin: 1px 0 0 0;
                }
                
                .label .barcode-container {
                    width: 75%;
                    height: 28px;
                    display: flex;
                    align-items: center;
                    justify-content: flex-start;
                }
                
                .label .barcode {
                    width: 100%;
                    height: 28px;
                    margin: 0;
                    display: block;
                }
                
                .label .art-code-barre {
                    font-size: 0.45em;
                    color: #d84315;
                    text-align: center;
                    width: 100%;
                    margin: 1px 0;
                    font-weight: bold;
                }
                
                .label .art-header {
                    width: 100%;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    margin-bottom: 0;
                }
                
                .label .art-name {
                    font-size: 0.6em;
                    font-weight: bold;
                    color: #000;
                    text-align: center;
                    width: 100%;
                    line-height: 1.1;
                    max-height: 1.2em;
                    overflow: hidden;
                }
                
                .label .art-price {
                    font-size: 0.55em;
                    color: #d84315;
                    font-weight: bold;
                    text-align: center;
                    width: 100%;
                    margin-top: 0.5px;
                }
                
                .label .logo-vertical {
                    position: absolute;
                    right: 1px;
                    bottom: 1px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    background: #000;
                    border-radius: 2px;
                    padding: 1px;
                    min-width: 10px;
                    max-width: 14px;
                }
                
                .label .logo-vertical span {
                    font-size: 0.45em;
                    font-family: Arial, sans-serif;
                    font-weight: bold;
                    letter-spacing: 0.1em;
                    line-height: 1.1em;
                    display: block;
                    text-align: center;
                }
                
                .label .logo-vertical .red { 
                    color: #d84315; 
                }
                
                .label .logo-vertical .white { 
                    color: #fff; 
                }
                
                .position-indicator {
                    display: none;
                }
                
                .empty-position {
                    display: none;
                }
                
                @media print {
                    body {
                        margin: 0;
                        padding: 0;
                    }
                    
                    #print-labels {
                        margin: 0;
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div id="print-labels">
                ${clonedLabels.innerHTML}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Attendre que le contenu soit chargé puis imprimer
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
}
window.addEventListener('DOMContentLoaded', function() {
    const labelsDiv = document.getElementById('labels');
    const saved = localStorage.getItem('sotech_labels');
    const loadingMsg = document.getElementById('qr-loading');
    if (labelsDiv && saved) {
        if (loadingMsg) loadingMsg.style.display = 'block';
        labelsDiv.innerHTML = saved;
        const wrappers = getAllLabels();
        let done = 0;
        wrappers.forEach(wrapper => {
            let label = wrapper.querySelector('.label');
            let barcodeSvg = label.querySelector('.barcode');
            // On récupère le code perso article depuis l'attribut data-codeperso pour être sûr
            const codePerso = barcodeSvg?.getAttribute('data-codeperso') || '';
            console.log("CodePerso utilisé pour le code-barres:", codePerso);
            if (barcodeSvg) barcodeSvg.innerHTML = '';
            JsBarcode(barcodeSvg, codePerso, {
                format: "CODE128",
                lineColor: "#000",
                        width: 2,
                height: 40,
                displayValue: false,
                margin: 0
            });
            done++;
            if (done === wrappers.length && loadingMsg) {
                loadingMsg.style.display = 'none';
            }
        });
        if (wrappers.length === 0 && loadingMsg) loadingMsg.style.display = 'none';
        attachRemoveEvents();
    }
        });
    </script>
</body>
</html>