<?php
try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    check_access();
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}

try {
    $fournisseurs = selection_element('fournisseur');
    $error_message = '';
    $success_message = '';

    if (isset($_GET['success'])) {
        $success_message = htmlspecialchars($_GET['success']);
    }
    if (isset($_GET['error'])) {
        $error_message = htmlspecialchars($_GET['error']);
    }
    if (isset($_GET['error_r'])) {
        $error_message = htmlspecialchars($_GET['error_r']);
    }

    if (isset($_POST['recherche'])) { 
        $code = $_POST['Code'];
        $values = [$code];
        $columns1 = ['CodePersoArticle'];
        $columns2 = ['libelle'];
        $tableName = "article";
        $article_resultat = verifier_element($tableName, $columns1, $values, '') ?: verifier_element($tableName, $columns2, $values, '');
        if (!$article_resultat) {
            throw new Exception("Aucun article trouvé avec ce code ou libellé.");
        }
            $id_article_stock = $article_resultat['IDARTICLE'];
            $article_stock = verifier_element('stock', ['IDARTICLE'], [$id_article_stock], '');
        $stockactuel = $article_stock ? $article_stock['StockActuel'] : 0;
    } elseif(isset($_GET['id'])) {
        $code = $_GET['id'];
        $values = [$code];
        $columns1 = ['IDARTICLE'];
        $tableName = "article";
        $article_resultat = verifier_element($tableName, $columns1, $values, '');
        if (!$article_resultat) {
            throw new Exception("Aucun article trouvé avec ce code ou libellé.");
        }
            $article_stock = verifier_element('stock', ['IDARTICLE'], [$code], '');
        $stockactuel = $article_stock ? $article_stock['StockActuel'] : 0;
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de Livraison</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #ff0000;
            color: white;
            padding: 15px;
            text-align: center;
            width: 100%;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .container {
            max-width: 1200px;
            width: 100%;
            margin: 20px auto;
            padding: 0 20px;
        }

        .form-container, .cart-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            margin-bottom: 20px;
        }

        .form-group, .form-control {
            border-radius: 12px;
        }

        .form-control:focus {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .alert-danger {
            background-color: #fff3f3;
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background-color: #f0fff4;
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .btn-navigation {
            background-color: #ff0000;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-navigation:hover {
            background-color: #cc0000;
            text-decoration: none;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .navigation-links {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #545b62;
            transform: translateY(-1px);
        }

        .form-title {
            color: #333;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .form-title i {
            margin-right: 10px;
            color: #ff0000;
        }

        .form-group label {
            font-weight: 500;
            color: #495057;
        }

        .form-group label i {
            margin-right: 5px;
            color: #ff0000;
        }

        .cart-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
        }

        .cart-item-title {
            font-weight: 600;
            color: #495057;
        }

        .cart-item-details {
            color: #6c757d;
            font-size: 0.9em;
        }

        /* Styles pour l'autocomplétion */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: none;
        }

        .search-result-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-code {
            font-weight: bold;
            color: #007bff;
        }

        .search-result-name {
            color: #333;
            margin-top: 2px;
        }

        .search-result-stock {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 2px;
        }

        .form-group {
            position: relative;
        }

        .loading {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #007bff;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <header>
        <h1><i class="fas fa-box"></i> Bon de Livraison</h1>
    </header>

    <main class="container">
        <div class="navigation-links">
            <a href="index.php" class="btn-navigation">
                <i class="fas fa-home"></i> Accueil
            </a>
            <a href="menu_entree_stock.php" class="btn-navigation">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['info_message'])): ?>
    <div class="alert alert-warning">
        <?= $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
    </div>
<?php endif; ?>

        <section id="livraison-form">
            <?php if (!isset($_GET['id'])) {?> 
            <div class="form-container">
                <h2 class="form-title"><i class="fas fa-search"></i> Rechercher un produit</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <form id="searchForm" method="post" onsubmit="return false;">
                            <div class="form-group">
                                <label for="produit"><i class="fas fa-tag"></i> Code ou Nom produit</label>
                                <div id="error-message" class="text-danger"></div>
                                <input type="text" id="searchQuery" name="Code" class="form-control" placeholder="Tapez le code ou nom du produit..." autocomplete="off" required>
                                <div id="searchResults" class="search-results"></div>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="recherche" class="btn btn-primary" onclick="searchProduct()">
                                    <i class="fas fa-search"></i> Rechercher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php }?>

            <form action="fonction_traitement/request.php" method="post" id="entreeStockForm">
                <div class="form-container">
                    <h2 class="form-title"><i class="fas fa-info-circle"></i> Informations du Bon de Livraison</h2>
                    <div id="form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="fournisseur"><i class="fas fa-truck"></i> Fournisseur</label>
                                    <select class="form-control" id="fournisseur" name="fournisseur" required>
                                        <option value="">Sélectionnez un fournisseur</option> 
                                        <?php foreach ($fournisseurs as $fournisseur): ?>
                                            <option value="<?= htmlspecialchars($fournisseur['IDFOURNISSEUR']) ?>"><?= htmlspecialchars($fournisseur['NomFournisseur']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="numeroBon"><i class="fas fa-file-invoice"></i> Numéro du Bon</label>
                                    <input type="text" id="numeroBon" name="numeroBon" class="form-control" placeholder="Numéro du Bon" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="dateLivraison"><i class="fas fa-calendar"></i> Date de Livraison</label>
                                    <input type="date" id="dateLivraison" name="dateLivraison" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="frais_annexes"><i class="fas fa-truck-loading"></i> Frais Annexes (transport, douane, etc.)</label>
                                    <input type="number" step="0.01" id="frais_annexes" name="frais_annexes" class="form-control" placeholder="Frais annexes pour ce bon (optionnel)">
                                    <small class="form-text text-muted">À répartir sur tous les articles du bon.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-container">
                    <h2 class="form-title"><i class="fas fa-plus-circle"></i> Ajouter un Article</h2>
                    <div id="article-form">
                        <div class="row">
                            <input type="hidden" id="id_article" name="id_article[]" value="<?php echo isset($article_resultat['IDARTICLE']) ? htmlspecialchars($article_resultat['IDARTICLE']) : ''; ?>">
                            <input type="hidden" id="id_stock" name="id_stock[]" value="<?php echo isset($article_stock['IDSTOCK']) ? htmlspecialchars($article_stock['IDSTOCK']) : ''; ?>">
                            <input type="hidden" id="prixAchat_article" name="prixAchat_article[]" value="<?php echo isset($article_resultat['PrixAchatHT']) ? htmlspecialchars($article_resultat['PrixAchatHT']) : ''; ?>">
                            <input type="hidden" id="prixVente_article" name="prixVente_article[]" value="<?php echo isset($article_resultat['PrixVenteTTC']) ? htmlspecialchars($article_resultat['PrixVenteTTC']) : ''; ?>">
                            <input type="hidden" name="id_utilisateur" id="id_utilisateur" value="<?php echo $_SESSION['id_utilisateur'];?>">

                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="produit"><i class="fas fa-tag"></i> Produit</label>
                                    <input type="text" id="produit" class="form-control" name="nomproduit" placeholder="Nom du produit" value="<?php echo isset($article_resultat['libelle']) ? htmlspecialchars($article_resultat['libelle']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="identifiant"><i class="fas fa-id-badge"></i> Identifiant</label>
                                    <input type="text" id="identifiant" class="form-control" name="identifiant" placeholder="Identifiant" value="<?php echo isset($article_resultat['CodePersoArticle']) ? htmlspecialchars($article_resultat['CodePersoArticle']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label for="stock"><i class="fas fa-cubes"></i> Stock</label>
                                    <input type="number" id="stock" class="form-control" name="stock" placeholder="Stock" value="<?php echo isset($stockactuel) ? htmlspecialchars($stockactuel) : ''; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label for="quantite"><i class="fas fa-sort-amount-up"></i> Quantité</label>
                                    <input type="number" id="quantite" class="form-control" name="quantite[]" placeholder="Quantité">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <?php if (user_can_see_purchase_prices()): ?>
                                        <label for="prixAchat"><i class="fas fa-money-bill-wave"></i> Prix d'Achat</label>
                                        <input type="number" step="0.01" id="prixAchat" class="form-control" name="prixAchat[]" placeholder="Prix d'Achat" value="<?php echo isset($prixAchat) ? htmlspecialchars($prixAchat) : ''; ?>" required>
                                    <?php else: ?>
                                        <!-- Champ caché avec PMP actuel -->
                                        <input type="hidden" name="prixAchat[]" value="<?php echo isset($article_resultat['PrixAchatHT']) ? htmlspecialchars($article_resultat['PrixAchatHT']) : '0'; ?>">
                                        <label for="prixAchat"><i class="fas fa-money-bill-wave"></i> Prix d'Achat</label>
                                        <input type="text" class="form-control" value="*** CONFIDENTIEL ***" readonly style="background-color: #f8f9fa; color: #6c757d;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label for="prixVente"><i class="fas fa-dollar-sign"></i> Prix de Vente</label>
                                    <input type="number" step="0.01" id="prixVente" class="form-control" name="prixVente[]" placeholder="Prix de Vente" value="<?php echo isset($article_resultat['PrixVenteTTC']) ? htmlspecialchars($article_resultat['PrixVenteTTC']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="mt-5">
                                        <i class="fas fa-barcode"></i> Numéros de série
                                    </label>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Les numéros de série sont obligatoires pour tous les articles.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <button type="button" class="btn btn-danger add-btn mt-4"><i class="fas fa-plus"></i> Ajouter au Bon de Livraison</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            

                <div class="cart-container">
                    <h2><i class="fas fa-list-ul"></i> Articles dans le Bon de Livraison</h2>
                    <table class="table table-bordered" id="product-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Produit</th>
                                <th>Identifiant</th>
                                <th>Stock</th>
                                <th>Quantité</th>
                                <?php if (user_can_see_purchase_prices()): ?>
                                    <th>Prix d'Achat</th>
                                <?php endif; ?>
                                <th>Prix de Vente</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Contenu du bon de livraison généré dynamiquement -->
                        </tbody>
                    </table>
                    <div class="form-group">
                        <label>Total:</label>
                        <input type="text" id="total-lines" class="form-control" disabled>
                    </div>
                    <?php if (user_can_see_purchase_prices()): ?>
                        <div class="form-group">
                            <label>Prix d'Achat Total:</label>
                            <input type="text" id="total-prix-achat" class="form-control" disabled>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Prix d'Achat Total:</label>
                            <input type="text" class="form-control" value="*** CONFIDENTIEL ***" disabled style="background-color: #f8f9fa; color: #6c757d;">
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Prix de Vente Total:</label>
                        <input type="text" id="total-prix-vente" class="form-control" disabled>
                    </div>
                    <div class="form-group">
                        <label>Marge Totale:</label>
                        <input type="text" id="total-marge" class="form-control" disabled>
                    </div>
                    
                    <div class="form-group text-center mt-4">
                        <?php if (can_user('entre_stock', 'enregistrer')): ?>
                        <button type="submit" name="valider_entree_stock" value="1" class="btn btn-primary">
                            <i class="fas fa-check"></i> Valider l'entrée en stock
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-primary" disabled title="Accès refusé">
                            <i class="fas fa-check"></i> Valider l'entrée en stock
                        </button>
                        <?php endif; ?>
                        
                        <?php if (can_user('entre_stock', 'annuler')): ?>
                        <button type="reset" class="btn btn-secondary" id="reset-btn">
                            <i class="fas fa-times"></i> Réinitialiser
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled title="Accès refusé">
                            <i class="fas fa-times"></i> Réinitialiser
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Variable pour les droits utilisateur
        const userCanSeePrices = <?= user_can_see_purchase_prices() ? 'true' : 'false' ?>;
        
        // Validation du formulaire avant soumission
        document.getElementById('entreeStockForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Nettoyer les champs de recherche
            resetAllFields();
            
            // Vérifier que le tableau n'est pas vide
            const tbody = document.querySelector('#product-table tbody');
            if (tbody.children.length === 0) {
                alert('Veuillez ajouter au moins un article au bon de livraison avant de valider.');
                return false;
            }
            
            // Vérifier que tous les champs requis sont remplis
            const requiredFields = ['fournisseur', 'numeroBon', 'dateLivraison'];
            for (const field of requiredFields) {
                const input = document.getElementById(field);
                if (!input.value) {
                    alert(`Le champ ${input.previousElementSibling.textContent} est requis.`);
                    input.focus();
                    return false;
                }
            }

            // Vérifier que tous les articles dans le tableau ont une quantité valide
            const rows = tbody.querySelectorAll('tr');
            let hasInvalidQuantity = false;
            
            rows.forEach((row, index) => {
                const quantiteInput = row.querySelector('input[name="quantite[]"]');
                const quantite = parseInt(quantiteInput.value);
                if (!quantiteInput || isNaN(quantite) || quantite <= 0) {
                    hasInvalidQuantity = true;
                    alert(`La quantité doit être supérieure à 0 pour l'article ${index + 1}`);
                }
            });

            if (hasInvalidQuantity) {
                return false;
            }

            // Ajouter le champ valider_entree_stock
            const submitButton = document.createElement('input');
            submitButton.type = 'hidden';
            submitButton.name = 'valider_entree_stock';
            submitButton.value = '1';
            this.appendChild(submitButton);

            // Si tout est valide, soumettre le formulaire
            this.submit();
        });

        // Fonction pour effacer le tableau
        function clearTable() {
            document.querySelector('#product-table tbody').innerHTML = '';
            totalLines = 0;
            totalPrixAchat = 0;
            totalPrixVente = 0;
            updateSummary();
        }

        // Variables pour l'autocomplétion
        let searchTimeout;
        let selectedIndex = -1;

        // Fonction pour l'autocomplétion
        function setupAutocomplete() {
            const searchInput = document.getElementById('searchQuery');
            const searchResults = document.getElementById('searchResults');

            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Effacer le timeout précédent
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    hideResults();
                    // Réinitialiser les champs si le champ de recherche est vide
                    if (query.length === 0) {
                        resetFields();
                    }
                    return;
                }

                // Délai pour éviter trop de requêtes
                searchTimeout = setTimeout(() => {
                    searchArticles(query);
                }, 300);
            });

            // Gestion des touches
            searchInput.addEventListener('keydown', function(e) {
                const results = searchResults.querySelectorAll('.search-result-item');
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        selectedIndex = Math.min(selectedIndex + 1, results.length - 1);
                        updateSelection(results);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        selectedIndex = Math.max(selectedIndex - 1, -1);
                        updateSelection(results);
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (selectedIndex >= 0 && results[selectedIndex]) {
                            selectArticle(results[selectedIndex]);
                        } else if (this.value.trim()) {
                            // Si on a du texte et aucun résultat sélectionné, faire une recherche manuelle
                            searchProduct();
                        }
                        break;
                    case 'Escape':
                        hideResults();
                        break;
                }
            });

            // Cacher les résultats quand on clique ailleurs
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    hideResults();
                }
            });
        }

        // Fonction pour rechercher les articles
        function searchArticles(query) {
            const searchResults = document.getElementById('searchResults');
            
            // Afficher le loading
            searchResults.innerHTML = '<div class="search-result-item"><i class="fas fa-spinner fa-spin"></i> Recherche...</div>';
            searchResults.style.display = 'block';

            fetch(`fonction_traitement/autocomplete_articles.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    displayResults(data.results);
                })
                .catch(error => {
                    console.error('Erreur de recherche:', error);
                    searchResults.innerHTML = '<div class="search-result-item text-danger">Erreur de recherche</div>';
                });
        }

        // Fonction pour afficher les résultats
        function displayResults(results) {
            const searchResults = document.getElementById('searchResults');
            
            if (results.length === 0) {
                searchResults.innerHTML = '<div class="search-result-item text-muted">Aucun article trouvé</div>';
                searchResults.style.display = 'block';
                return;
            }

            let html = '';
            results.forEach((article, index) => {
                html += `
                    <div class="search-result-item" data-index="${index}" data-article='${JSON.stringify(article)}'>
                        <div class="search-result-code">${article.code}</div>
                        <div class="search-result-name">${article.name}</div>
                        <div class="search-result-stock">Stock: ${article.stock}</div>
                    </div>
                `;
            });

            searchResults.innerHTML = html;
            searchResults.style.display = 'block';
            selectedIndex = -1;

            // Ajouter les événements de clic
            searchResults.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('click', function() {
                    selectArticle(this);
                });
            });
        }

        // Fonction pour mettre à jour la sélection
        function updateSelection(results) {
            results.forEach((item, index) => {
                if (index === selectedIndex) {
                    item.style.backgroundColor = '#e3f2fd';
                } else {
                    item.style.backgroundColor = '';
                }
            });
        }

        // Fonction pour sélectionner un article
        function selectArticle(element) {
            const articleData = JSON.parse(element.dataset.article);
            
            // Mettre à jour les champs - garder seulement le code dans le champ de recherche
            document.getElementById('searchQuery').value = articleData.code;
            document.getElementById('produit').value = articleData.name;
            document.getElementById('identifiant').value = articleData.code;
            
            // Mise à jour conditionnelle du prix d'achat selon les droits
            if (userCanSeePrices) {
                const prixAchatElement = document.getElementById('prixAchat');
                if (prixAchatElement) {
                    prixAchatElement.value = articleData.prix_achat;
                }
            } else {
                // Mettre à jour le champ caché pour les utilisateurs sans droits
                const prixAchatHidden = document.querySelector('input[name="prixAchat[]"]');
                if (prixAchatHidden) {
                    prixAchatHidden.value = articleData.prix_achat;
                }
            }
            
            document.getElementById('prixVente').value = articleData.prix_vente;
            document.getElementById('stock').value = articleData.stock;

            // Mise à jour des champs cachés
            document.getElementById('id_article').value = articleData.id;
            document.getElementById('id_stock').value = articleData.id_stock || '';
            document.getElementById('prixAchat_article').value = articleData.prix_achat;
            document.getElementById('prixVente_article').value = articleData.prix_vente;

            // Cacher les résultats
            hideResults();
            
            // Activer le bouton d'ajout
            document.querySelector('.add-btn').disabled = false;
            
            // Focus sur le champ quantité
            document.getElementById('quantite').focus();
        }

        // Fonction pour cacher les résultats
        function hideResults() {
            document.getElementById('searchResults').style.display = 'none';
            selectedIndex = -1;
        }

        // Fonction pour la recherche manuelle (bouton)
        function searchProduct() {
            const searchCode = document.getElementById('searchQuery').value.trim();
            console.log('Recherche manuelle de l\'article avec le code:', searchCode);

            if (!searchCode) {
                alert('Veuillez saisir un code ou nom d\'article à rechercher.');
                return;
            }

            fetch('fonction_traitement/search_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'Code=' + encodeURIComponent(searchCode)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Données reçues:', data);
                
                if (data.error) {
                    throw new Error(data.error);
                }

                if (!data.IDARTICLE) {
                    throw new Error('Aucun article trouvé avec ce code ou nom.');
                }

                // Mise à jour des champs visibles
                document.getElementById('produit').value = data.libelle || '';
                document.getElementById('identifiant').value = data.CodePersoArticle || '';
                
                // Mise à jour conditionnelle du prix d'achat selon les droits
                if (userCanSeePrices) {
                    const prixAchatElement = document.getElementById('prixAchat');
                    if (prixAchatElement) {
                        prixAchatElement.value = data.PrixAchatHT || '';
                    }
                } else {
                    // Mettre à jour le champ caché pour les utilisateurs sans droits
                    const prixAchatHidden = document.querySelector('input[name="prixAchat[]"]');
                    if (prixAchatHidden) {
                        prixAchatHidden.value = data.PrixAchatHT || '';
                    }
                }
                
                document.getElementById('prixVente').value = data.PrixVenteTTC || '';
                document.getElementById('stock').value = data.StockActuel || '0';

                // Mise à jour des champs cachés
                const hiddenFields = {
                    'id_article': data.IDARTICLE,
                    'id_stock': data.IDSTOCK,
                    'prixAchat_article': data.PrixAchatHT,
                    'prixVente_article': data.PrixVenteTTC
                };

                Object.entries(hiddenFields).forEach(([fieldName, value]) => {
                    const field = document.getElementById(fieldName);
                    if (field) {
                        field.value = value;
                        console.log(`Mise à jour du champ ${fieldName}:`, value);
                    } else {
                        console.error(`Champ ${fieldName} non trouvé`);
                    }
                });

                // Activer le bouton d'ajout
                document.querySelector('.add-btn').disabled = false;
                
                // Cacher les résultats de recherche automatique
                hideResults();
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert(error.message);
                resetAllFields();
            });
        }

        function resetFields() {
            // Ne pas réinitialiser le champ de recherche pour permettre la saisie continue
            document.getElementById('produit').value = '';
            document.getElementById('identifiant').value = '';
            document.getElementById('stock').value = '';
            document.getElementById('quantite').value = '';
            
            // Réinitialisation conditionnelle du prix d'achat selon les droits
            if (userCanSeePrices) {
                const prixAchatElement = document.getElementById('prixAchat');
                if (prixAchatElement) {
                    prixAchatElement.value = '';
                }
            } else {
                // Réinitialiser le champ caché pour les utilisateurs sans droits
                const prixAchatHidden = document.querySelector('input[name="prixAchat[]"]');
                if (prixAchatHidden) {
                    prixAchatHidden.value = '';
                }
            }
            
            document.getElementById('prixVente').value = '';
            document.getElementById('id_article').value = '';
            document.getElementById('id_stock').value = '';
            document.getElementById('prixAchat_article').value = '';
            document.getElementById('prixVente_article').value = '';
            document.querySelector('.add-btn').disabled = true;
            hideResults();
        }
        
        function resetAllFields() {
            document.getElementById('searchQuery').value = '';
            resetFields();
        }

        var today = new Date();
        var date = today.toISOString().split('T')[0];
        document.getElementById('dateLivraison').value = date;

        let totalLines = 0;
        let totalPrixAchat = 0;
        let totalPrixVente = 0;

        document.querySelector('.add-btn').addEventListener('click', function() {
            console.log('Début de l\'ajout d\'article au tableau');
            
            // Récupération et conversion des valeurs numériques
            const produit = document.getElementById('produit').value;
            const identifiant = document.getElementById('identifiant').value;
            const stock = parseInt(document.getElementById('stock').value) || 0;
            const quantite = parseInt(document.getElementById('quantite').value) || 0;
            
            // Récupération conditionnelle du prix d'achat selon les droits
            let prixAchat = 0;
            if (userCanSeePrices) {
                const prixAchatElement = document.getElementById('prixAchat');
                prixAchat = prixAchatElement ? parseFloat(prixAchatElement.value) || 0 : 0;
            } else {
                // Utiliser la valeur cachée si l'utilisateur ne peut pas voir les prix
                const prixAchatHidden = document.querySelector('input[name="prixAchat[]"]');
                prixAchat = prixAchatHidden ? parseFloat(prixAchatHidden.value) || 0 : 0;
            }
            
            const prixVente = parseFloat(document.getElementById('prixVente').value) || 0;
            const idArticle = document.getElementById('id_article').value;
            const idStock = document.getElementById('id_stock').value;
            const prixAchatArticle = parseFloat(document.getElementById('prixAchat_article').value) || 0;
            const prixVenteArticle = parseFloat(document.getElementById('prixVente_article').value) || 0;

            // Validation détaillée avec messages d'erreur spécifiques
            let errorMessage = '';
            if (!produit) errorMessage += "Le nom du produit est requis.\n";
            if (!identifiant) errorMessage += "L'identifiant est requis.\n";
            if (!idArticle) errorMessage += "L'ID de l'article est requis.\n";
            if (quantite <= 0) errorMessage += "La quantité doit être supérieure à 0.\n";
            // Validation conditionnelle du prix d'achat selon les droits
            if (userCanSeePrices) {
                if (prixAchat <= 0) {
                    errorMessage += "Le prix d'achat doit être supérieur à 0.\n";
                }
            }
            if (prixVente <= 0) errorMessage += "Le prix de vente doit être supérieur à 0.\n";

            if (errorMessage) {
                console.log('Erreurs de validation:', errorMessage);
                alert("Veuillez corriger les erreurs suivantes :\n\n" + errorMessage);
                return;
            }

            try {
                // Vérifier si l'article existe déjà dans le tableau
                const existingRow = Array.from(document.querySelectorAll('#product-table tbody tr')).find(row => 
                    row.querySelector('input[name="id_article[]"]').value === idArticle
                );

                if (existingRow) {
                    console.log('Article déjà présent dans le tableau');
                    alert("Cet article est déjà dans le tableau.");
                    return;
                }

                console.log('Création de la nouvelle ligne du tableau');
                const row = document.createElement('tr');
                row.className = 'article-row';
                row.innerHTML = `
                    <td>${totalLines + 1}</td>
                    <td>${produit}</td>
                    <td>${identifiant}</td>
                    <td>${stock}</td>
                    <td>
                        <input type="number" 
                               name="quantite[]" 
                               value="${quantite}" 
                               min="1" 
                               required 
                               class="form-control"
                               style="width: 80px;">
                    </td>
                    ${userCanSeePrices ? `<td>${prixAchat.toFixed(2)}</td>` : ''}
                    <td>${prixVente.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-danger remove-btn"><i class="fas fa-trash-alt"></i></button>
                    </td>
                `;

                // Ajouter les champs cachés pour les données
                console.log('Ajout des champs cachés');
                const hiddenFields = {
                    'id_article[]': idArticle,
                    'id_stock[]': idStock || '',
                    'prixAchat[]': prixAchat.toString(),
                    'prixAchat_article[]': prixAchatArticle.toString(),
                    'prixVente[]': prixVente.toString(),
                    'prixVente_article[]': prixVenteArticle.toString()
                };

                Object.entries(hiddenFields).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    row.appendChild(input);
                });

                // Ajouter un écouteur d'événement pour la mise à jour des totaux lors du changement de quantité
                const quantiteInput = row.querySelector('input[name="quantite[]"]');
                quantiteInput.addEventListener('change', function() {
                    const newQuantite = parseInt(this.value) || 0;
                    if (newQuantite <= 0) {
                        this.value = 1;
                        alert('La quantité doit être supérieure à 0');
                        return;
                    }
                    updateTotals();
                });

                console.log('Ajout de la ligne au tableau');
                document.querySelector('#product-table tbody').appendChild(row);
                totalLines++;
                updateTotals();
                
                // Réinitialiser uniquement les champs de quantité
                document.getElementById('quantite').value = '';
                
                console.log('Article ajouté avec succès');
            } catch (error) {
                console.error('Erreur lors de l\'ajout de l\'article:', error);
                alert('Une erreur est survenue lors de l\'ajout de l\'article. Veuillez réessayer.');
            }
        });

        document.querySelector('#product-table').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-btn')) {
                const row = e.target.closest('tr');
                const quantiteInput = row.querySelector('input[name="quantite[]"]');
                const quantite = quantiteInput ? parseFloat(quantiteInput.value) || 0 : 0;
                
                // Récupération sécurisée des prix depuis les champs cachés
                const prixAchatInput = row.querySelector('input[name="prixAchat[]"]');
                const prixAchat = prixAchatInput ? parseFloat(prixAchatInput.value) || 0 : 0;
                
                const prixVenteInput = row.querySelector('input[name="prixVente[]"]');
                const prixVente = prixVenteInput ? parseFloat(prixVenteInput.value) || 0 : 0;

                totalPrixAchat -= prixAchat * quantite;
                totalPrixVente -= prixVente * quantite;
                totalLines--;

                row.remove();
                updateSummary();
            }
        });

        function updateSummary() {
            document.getElementById('total-lines').value = totalLines;
            if (userCanSeePrices) {
                document.getElementById('total-prix-achat').value = totalPrixAchat.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F.CFA';
            }
            document.getElementById('total-prix-vente').value = totalPrixVente.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F.CFA';
            document.getElementById('total-marge').value = (totalPrixVente - totalPrixAchat).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F.CFA';
        }

        // Fonction pour mettre à jour les totaux
        function updateTotals() {
            totalPrixAchat = 0;
            totalPrixVente = 0;
            
            document.querySelectorAll('#product-table tbody tr').forEach(row => {
                const quantite = parseInt(row.querySelector('input[name="quantite[]"]').value) || 0;
                const prixAchatInput = row.querySelector('input[name="prixAchat[]"]');
                const prixAchat = prixAchatInput ? parseFloat(prixAchatInput.value) || 0 : 0;
                const prixVente = parseFloat(row.querySelector('input[name="prixVente[]"]').value) || 0;
                
                totalPrixAchat += quantite * prixAchat;
                totalPrixVente += quantite * prixVente;
            });
            
            updateSummary();
        }

        document.getElementById('reset-btn').addEventListener('click', function() {
            if (confirm("Êtes-vous sûr de vouloir réinitialiser le formulaire ?")) {
                clearTable();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser l'autocomplétion
            setupAutocomplete();
            
            const form = document.getElementById('entreeStockForm');
            if (!form) {
                console.error('Formulaire non trouvé');
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) {
                console.error('Bouton submit non trouvé');
                return;
            }

            let isSubmitting = false;

            // Fonction pour initialiser les champs cachés
            function initializeHiddenFields() {
                const rows = document.querySelectorAll('.article-row');
                if (!rows.length) {
                    console.warn('Aucune ligne d\'article trouvée');
                    return;
                }

                rows.forEach((row, index) => {
                    const idArticle = row.querySelector('.id-article');
                    const quantite = row.querySelector('.quantite');
                    const prixAchat = row.querySelector('.prix-achat');
                    const prixVente = row.querySelector('.prix-vente');
                    const designation = row.querySelector('.designation');

                    if (!idArticle || !quantite || !prixAchat || !prixVente || !designation) {
                        console.warn(`Éléments manquants dans la ligne ${index + 1}`);
                        return;
                    }

                    // Ajouter les champs cachés pour les autres informations
                    const fields = {
                        'id_article[]': idArticle.value,
                        'quantite[]': quantite.value,
                        'prixAchat[]': prixAchat.value,
                        'prixVente[]': prixVente.value,
                        'designation[]': designation.textContent
                    };

                    // Créer ou mettre à jour les champs cachés
                    Object.entries(fields).forEach(([name, value]) => {
                        let field = row.querySelector(`input[name="${name}"]`);
                        if (!field) {
                            field = document.createElement('input');
                            field.type = 'hidden';
                            field.name = name;
                            row.appendChild(field);
                        }
                        field.value = value;
                    });
                });
            }

            // Fonction pour valider le formulaire
            function validateForm() {
                const rows = document.querySelectorAll('#product-table tbody tr');
                let isValid = true;
                let errorMessage = '';

                // Vérifier qu'il y a au moins un article
                if (rows.length === 0) {
                    errorMessage = '⚠️ Veuillez ajouter au moins un article.';
                    isValid = false;
                }

                // Vérifier chaque ligne
                rows.forEach((row, index) => {
                    const quantite = row.querySelector('input[name="quantite[]"]');
                    const prixVente = row.querySelector('input[name="prixVente[]"]');

                    // Vérification des champs obligatoires
                    if (!quantite || !prixVente) {
                        errorMessage += `\n⚠️ Données manquantes pour l'article ${index + 1}.`;
                        isValid = false;
                        return;
                    }

                    const quantiteValue = parseInt(quantite.value);
                    const prixVenteValue = parseFloat(prixVente.value);

                    if (quantiteValue <= 0) {
                        errorMessage += `\n⚠️ La quantité doit être supérieure à 0 pour l'article ${index + 1}.`;
                        isValid = false;
                    }
                    
                    if (prixVenteValue <= 0) {
                        errorMessage += `\n⚠️ Le prix de vente doit être supérieur à 0 pour l'article ${index + 1}.`;
                        isValid = false;
                    }
                    
                    // Validation du prix d'achat SEULEMENT pour les utilisateurs avec droits
                    if (userCanSeePrices) {
                        const prixAchat = row.querySelector('input[name="prixAchat[]"]');
                        if (!prixAchat) {
                            errorMessage += `\n⚠️ Prix d'achat manquant pour l'article ${index + 1}.`;
                            isValid = false;
                            return;
                        }
                        
                        const prixAchatValue = parseFloat(prixAchat.value);
                        if (prixAchatValue <= 0) {
                            errorMessage += `\n⚠️ Le prix d'achat doit être supérieur à 0 pour l'article ${index + 1}.`;
                            isValid = false;
                        }
                    }
                });

                if (!isValid) {
                    alert(errorMessage);
                }

                return isValid;
            }

            // Gestionnaire de soumission du formulaire
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (isSubmitting) {
                    return;
                }

                if (!validateForm()) {
                    return;
                }

                // Initialiser les champs cachés
                initializeHiddenFields();

                // Désactiver le bouton submit
                isSubmitting = true;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Traitement...';

                // Soumettre le formulaire
                this.submit();
            });
        });
    </script>    
</body>
</html>
