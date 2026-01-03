<?php 
require_once 'fonction_traitement/fonction.php';
check_access(); // Cette fonction gère déjà la vérification des droits

try {
    include('db/connecting.php');
    $categorie_article = verifier_element_tous('categorie_article', ['desactiver'], ['non'], '');
    $articles = verifier_element_tous('article', [], [], '');
    $totalArticles = 0;
    if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $idarticles) {
            $totalArticles += count($idarticles);
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
        
        // Vérifier si le numéro de série existe
        $num_serie = verifier_element('num_serie', ['NUMERO_SERIE'], [$numeroSerie], '');
        if ($num_serie) {
            // Vérifier si le numéro de série appartient à l'article
            if ($num_serie['IDARTICLE'] != $id_article) {
                $erreur = "Le numéro de série n'appartient pas à cet article.";
                if (!headers_sent()) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur));
                    exit();
                } else {
                    echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '";</script>';
                    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '">';
                    exit();
                }
            }
            
            // Vérifier si le numéro de série est déjà vendu (vente normale)
            if (!is_null($num_serie['NumeroVente']) && $num_serie['NumeroVente'] != '') {
                $erreur = "Le numéro de série a déjà été vendu.";
                if (!headers_sent()) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur));
                    exit();
                } else {
                    echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '";</script>';
                    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '">';
                    exit();
                }
            }
            
            // Vérifier si le numéro de série est déjà vendu à crédit
            if (!is_null($num_serie['IDvente_credit']) && $num_serie['IDvente_credit'] != '') {
                $erreur = "Le numéro de série a déjà été vendu à crédit.";
                if (!headers_sent()) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur));
                    exit();
                } else {
                    echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '";</script>';
                    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '">';
                    exit();
                }
            }
            
            // Vérifier si le numéro de série est déjà dans le panier
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
                $erreur = "Ce numéro de série est déjà dans le panier.";
                if (!headers_sent()) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur));
                    exit();
                } else {
                    echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '";</script>';
                    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '">';
                    exit();
                }
            }
            
            // Si toutes les vérifications passent, ajouter au panier
            $_SESSION['panier'][$id_article][$numeroSerie] = [
                'id_article' => $id_article,
                'libelle' => $libelle,
                'prixVenteUnitaire' => $prixVenteUnitaire,
                'quantite' => $quantite
            ];
            
            // Redirection avec message de succès et fallback JavaScript
            if (!headers_sent()) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?success=' . urlencode('Article ajouté au panier avec succès'));
                exit();
            } else {
                // Fallback si les headers sont déjà envoyés
                echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?success=' . urlencode('Article ajouté au panier avec succès') . '";</script>';
                echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?success=' . urlencode('Article ajouté au panier avec succès') . '">';
                exit();
            }
        }
        else {
            $erreur = "Le numéro de série n'existe pas.";
            if (!headers_sent()) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur));
                exit();
            } else {
                echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '";</script>';
                echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($erreur) . '">';
                exit();
            }
        }
    } 
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    if (!headers_sent()) {
        header('Location: ' . $referer . '?error=' . urlencode($erreur));
        exit();
    } else {
        echo '<script>window.location.href = "' . $referer . '?error=' . urlencode($erreur) . '";</script>';
        echo '<meta http-equiv="refresh" content="0;url=' . $referer . '?error=' . urlencode($erreur) . '">';
        exit();
    }
}

// Variables pour les droits (utilisées dans les boutons)
$currentPage = 'liste_article';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Articles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Styles pour le formulaire d'ajout au panier */
        .form-panier-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .form-panier-container .form-label {
            color: #495057;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-panier-container .input-group {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-panier-container .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-panier-container .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .form-panier-container .form-control.is-valid {
            border-color: #28a745;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.94-.94 1.44 1.44 2.3-2.3.94.94-3.24 3.24z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-panier-container .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 1.4 1.4 1.4-1.4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .btn-verification {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-verification:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        
        .btn-ajouter-panier {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-ajouter-panier:hover:not(:disabled) {
            background: linear-gradient(135deg, #1e7e34 0%, #155724 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(40,167,69,0.3);
        }
        
        .btn-ajouter-panier:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .alert-validation {
            border-radius: 8px;
            border: none;
            padding: 12px 16px;
            margin-top: 8px;
            font-weight: 500;
        }
        
        .alert-validation .fas {
            margin-right: 8px;
        }
        
        .info-prix-stock {
            background: rgba(255,255,255,0.8);
            border-radius: 6px;
            padding: 8px 12px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        /* Animation pour les messages de validation */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-validation {
            animation: slideInDown 0.3s ease-out;
        }
        
        /* Responsive pour mobile */
        @media (max-width: 768px) {
            .form-panier-container {
                padding: 15px !important;
            }
            
            .form-panier-container .form-control {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .btn-ajouter-panier {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body id="liste_article">
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <header class="p-5">
        <h1>Liste des Articles</h1>
        <a href="caisse.php">
            <div class="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <span id="cart-count"><?= isset($totalArticles) ? htmlspecialchars($totalArticles) : 0; ?></span>
            </div>
        </a>
    </header>
    
    
    <main class="container">
        <div class="m-3">
            
        </div>

        <?php
                if (isset($_GET['success'])) {
                    $successMessage = htmlspecialchars($_GET['success']);
                    echo '<div id="success-alert" class="alert alert-success" role="alert">' . $successMessage . '</div>';
                }
                if (isset($_GET['error'])) {
                    $errorMessage = htmlspecialchars($_GET['error']);
                    echo '<div id="error-alert" class="alert alert-danger" role="alert">' . $errorMessage . '</div>';
                }
            ?>
        <form method="post" id="searchForm">
            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher par nom ou code" name="search" value="">
            <select class="form-select mt-3" name="category" id="categorySelect">
                <option value="0">------------</option>
                <?php foreach ($categorie_article as $category): ?>
                <option value="<?= htmlspecialchars($category['id_categorie']) ?>">
                    <?= htmlspecialchars($category['nom_categorie']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-search mt-3 mx-3">Rechercher</button>
        </form>
        
        <div class="row" id="articleList">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-message">
                    <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-message">
                <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <?php if (empty($articles)): ?>
                <div class="col-12 text-center mt-5 mb-5">
                    <div class="" role="alert">
                        Aucun article trouvé.
                    </div>
                </div>
            <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <?php $isActive = ($article['desactiver'] !== 'oui'); ?>
                        <div class="col-md-4 mt-4 article-item" data-category="<?= htmlspecialchars($article['id_categorie']) ?>">
                            <div class="product-card position-relative <?= $isActive ? '' : 'bg-light text-muted' ?>">
                                <img src="<?= htmlspecialchars(str_replace('../', '', $article['photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Image de l'article" style="opacity:<?= $isActive ? '1' : '0.5' ?>;">
                                <div class="p-3">
                                    <div class="info-overlay">
                                        <h5 class="product-title"><?= htmlspecialchars($article['libelle'], ENT_QUOTES, 'UTF-8') ?></h5>
                                        <p class="product-price">
                                            <?= number_format((float)$article['PrixVenteTTC'], 0, ',', ' ') ?> F CFA
                                        </p>
                                        <p><strong>Code :</strong> <?= htmlspecialchars($article['CodePersoArticle'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <p><strong>Marque :</strong> <?= htmlspecialchars($article['marque'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <p><strong>Description :</strong> <?= htmlspecialchars($article['Descriptif'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <p><strong>Stock :</strong> <?php
                                            $stock = verifier_element('stock',['IDARTICLE'],[$article['IDARTICLE']],'');
                                            if (!$stock) {
                                                echo '<style>@keyframes blink { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } } </style><span style="color: red; animation: blink 1s infinite;">Rupture de stock</span>';
                                            }else {
                                                echo htmlspecialchars($stock['StockActuel'], ENT_QUOTES, 'UTF-8');   
                                            }
                                        ?></p>
                                        <p><strong>Statut :</strong> <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"><?= $isActive ? 'Actif' : 'Inactif' ?></span></p>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <?php
                                    // Utilisation du système unifié de droits pour tous les boutons
                                    if ($isActive) {
                                        echo bouton_action('Ajouter au Panier', 'Liste_article', 'ajouter_au_panier', 'btn btn-warning', 'type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse' . htmlspecialchars($article['IDARTICLE']) . '" aria-expanded="false" aria-controls="panelsStayOpen-collapse"');
                                    } else {
                                        echo '<button class="btn btn-warning" type="button" disabled><i class="fas fa-shopping-cart"></i> Ajouter au Panier</button>';
                                    }
                                    
                                    echo bouton_action('Modifier', 'Liste_article', 'modifier', 'btn btn-secondary', 'type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapsemofifier' . htmlspecialchars($article['IDARTICLE']) . '" aria-expanded="false" aria-controls="panelsStayOpen-collapse"');
                                    ?>
                                    
                                    <form action="fonction_traitement/request.php" method="post" style="display:inline;">
                                        <input type="hidden" name="id_article" value="<?= htmlspecialchars($article['IDARTICLE'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($isActive): ?>
                                            <?php echo bouton_action('Désactiver', 'Liste_article', 'supprimer', 'btn btn-danger', 'type="submit" name="supprimer_article"'); ?>
                                        <?php else: ?>
                                            <?php echo bouton_action('Réactiver', 'Liste_article', 'supprimer', 'btn btn-success', 'type="submit" name="reactiver_article"'); ?>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <div>
                                <div class="accordion-collapse collapse" id="panelsStayOpen-collapse<?= htmlspecialchars($article['IDARTICLE']) ?>">
                                    <div class="form-panier-container p-3">
                                        <form method="post" id="form-panier-<?= htmlspecialchars($article['IDARTICLE']) ?>">
                                            <input type="hidden" name="id_article" value="<?= htmlspecialchars($article['IDARTICLE'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="libelle" value="<?= htmlspecialchars($article['libelle'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="prixVenteUnitaire" value="<?= htmlspecialchars($article['PrixVenteTTC'], ENT_QUOTES, 'UTF-8') ?>">
                                            
                                            <div class="mb-3">
                                                <label for="numeroSerie-<?= htmlspecialchars($article['IDARTICLE']) ?>" class="form-label fw-bold">
                                                    <i class="fas fa-barcode text-primary"></i> Numéro de série
                                                </label>
                                                <div class="input-group">
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="numeroSerie-<?= htmlspecialchars($article['IDARTICLE']) ?>" 
                                                           name="numeroSerie" 
                                                           required 
                                                           placeholder="Entrez le numéro de série..."
                                                           autocomplete="off">
                                                    <button type="button" 
                                                            class="btn btn-outline-secondary" 
                                                            title="Le numéro de série sera vérifié lors de l'ajout">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                </div>
                                                <div id="validation-<?= htmlspecialchars($article['IDARTICLE']) ?>" class="mt-2 alert-validation"></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="info-prix-stock">
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle text-primary"></i> 
                                                                Prix: <strong><?= number_format((float)$article['PrixVenteTTC'], 0, ',', ' ') ?> F CFA</strong>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 text-end">
                                                        <div class="info-prix-stock">
                                                            <small class="text-muted">
                                                                <i class="fas fa-box text-info"></i> 
                                                                Stock: <strong><?php
                                                                    $stock = verifier_element('stock',['IDARTICLE'],[$article['IDARTICLE']],'');
                                                                    if (!$stock) {
                                                                        echo '<span class="text-danger">Rupture</span>';
                                                                    } else {
                                                                        echo htmlspecialchars($stock['StockActuel']);
                                                                    }
                                                                ?></strong>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="submit" 
                                                        name="ajouter_panier" 
                                                        class="btn btn-ajouter-panier" 
                                                        id="btn-ajouter-<?= htmlspecialchars($article['IDARTICLE']) ?>">
                                                    <i class="fas fa-cart-plus"></i> Ajouter au Panier
                                                </button>
                                                <button type="reset" 
                                                        class="btn btn-secondary" 
                                                        onclick="fermerFormulaire(<?= htmlspecialchars($article['IDARTICLE']) ?>)">
                                                    <i class="fas fa-times"></i> Annuler
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <div class="accordion-collapse collapse" id="panelsStayOpen-collapsemofifier<?= htmlspecialchars($article['IDARTICLE']) ?>">
                                    <div>
                                        <form id="form" action="fonction_traitement/request.php" enctype="multipart/form-data" method="post">
                                            <div class="row">
                                                <input type="hidden" name="id_article" value="<?= htmlspecialchars($article['IDARTICLE'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="col-md-12">
                                                    <label for="Libelle">Libellé</label>
                                                    <input type="text" class="form-control" id="Libelle" name="moLibelle" value="<?= htmlspecialchars($article['libelle']) ?>" required>
                                                </div>

                                                <div class="col-md-12">
                                                    <label for="Description">Description</label>
                                                    <textarea class="form-control" id="Description" name="moDescription" rows="3"><?= htmlspecialchars($article['Descriptif']) ?></textarea>
                                                </div>

                                                <div class="col-md-12">
                                                    <label for="Marque">Marque</label>
                                                    <input type="text" class="form-control" id="Marque" name="moMarque" value="<?= htmlspecialchars($article['marque']) ?>" required>
                                                </div>

                                                <div class="col-md-12">
                                                    <label for="PrixAchat">Prix d'achat (PMP)</label>
                                                    <input type="number" class="form-control" id="PrixAchat<?= $article['IDARTICLE'] ?>" name="moPrixAchat" step="0.01" value="<?= htmlspecialchars($article['PrixAchatHT']) ?>" readonly>
                                                </div>

                                                <div class="col-md-12">
                                                    <label for="PrixVente">Prix de vente TTC</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="PrixVente<?= $article['IDARTICLE'] ?>" name="moPrixVente" step="0.01" value="<?= htmlspecialchars($article['PrixVenteTTC']) ?>" required>
                                                        <button type="button" class="btn btn-outline-secondary" onclick="proposerPrixVente(<?= $article['IDARTICLE'] ?>)">Prix conseillé</button>
                                                    </div>
                                                    <small id="margeInfo<?= $article['IDARTICLE'] ?>" class="form-text text-muted"></small>
                                                </div>
                                                    
                                                <div class="col-md-12 mb-3">
                                                    <label for="Photo">Photo</label>
                                                    <input type="file" class="form-control" id="Photo" name="moPhoto">
                                                </div>
                                                        
                                                <div class="col-md-12 mt-3 mb-3">
                                                    <button type="submit" name="mocreer_article" class="btn btn-primary">Enregistrer</button>
                                                    <button type="reset" class="btn btn-secondary">Annuler</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div id="messageContainer"></div>
    </main>
    <span class="total-articles">Total Articles: <?= count($articles) ?></span>
    <div class="fixed-bottom">
        <p>© 2024 SOTech | Partenaire Apple</p>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        setTimeout(function() {
            var errorAlert = document.getElementById('error-alert');
            var successAlert = document.getElementById('error-alert');
            if (errorAlert & successAlert) {
                errorAlert.style.display = 'none';
            }

            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                url.searchParams.delete('success');
                window.history.replaceState(null, null, url);
            }
        }, 2000);
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("searchInput");
            const categorySelect = document.getElementById("categorySelect");
            const articleList = document.getElementById("articleList");
            const articles = articleList.getElementsByClassName("article-item");
            const messageContainer = document.getElementById("messageContainer");

            function filterArticles() {
                const searchText = searchInput.value.toLowerCase();
                const selectedCategory = categorySelect.value;
                let articlesFound = false;

                for (let i = 0; i < articles.length; i++) {
                    const article = articles[i];
                    const articleText = article.textContent.toLowerCase();
                    const articleCategory = article.getAttribute("data-category");

                    const matchesSearch = articleText.includes(searchText);
                    const matchesCategory = selectedCategory === "0" || articleCategory === selectedCategory;

                    if (matchesSearch && matchesCategory) {
                        article.style.display = "";
                        articlesFound = true;
                    } else {
                        article.style.display = "none";
                    }
                }
                messageContainer.innerHTML = articlesFound ? "" : "<div class='alert alert-info m-5'>Aucun article trouvé.</div>";
            }

            searchInput.addEventListener("input", filterArticles);
            categorySelect.addEventListener("change", filterArticles);

            // Initial filter when page loads
            filterArticles();
        });

        // Correction JS pour proposerPrixVente et affichage marge
        function proposerPrixVente(id) {
            var pmp = parseFloat(document.getElementById('PrixAchat'+id).value);
            var marge = 30; // marge cible en %
            if (!isNaN(pmp)) {
                var prixConseil = Math.round(pmp * (1 + marge/100));
                document.getElementById('PrixVente'+id).value = prixConseil;
                document.getElementById('margeInfo'+id).innerText = 'Prix conseillé : ' + prixConseil.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F CFA (marge ' + marge + '%)';
            }
        }

        document.getElementById('PrixVente<?= $article['IDARTICLE'] ?>').addEventListener('input', function() {
            var pmp = parseFloat(document.getElementById('PrixAchat<?= $article['IDARTICLE'] ?>').value);
            var prixVente = parseFloat(this.value);
            var marge = prixVente > 0 ? ((prixVente - pmp) / prixVente) * 100 : 0;
            var info = '';
            if (marge < 30) {
                info = '⚠️ Marge faible : ' + marge.toFixed(0) + '%. Prix conseillé : ' + Math.round(pmp * 1.3).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F CFA';
            } else {
                info = 'Marge : ' + marge.toFixed(0) + '%. ';
            }
            document.getElementById('margeInfo<?= $article['IDARTICLE'] ?>').innerText = info;
        });

        // ===== FONCTIONNALITÉS SIMPLIFIÉES POUR LE PANIER =====
        
        // Fonction pour fermer le formulaire
        function fermerFormulaire(idArticle) {
            try {
                const collapse = document.getElementById('panelsStayOpen-collapse' + idArticle);
                if (collapse) {
                    const bsCollapse = new bootstrap.Collapse(collapse, {toggle: true});
                }
                
                // Réinitialiser le formulaire
                const form = document.getElementById('form-panier-' + idArticle);
                if (form) {
                    form.reset();
                }
                
                // Réinitialiser la validation
                const validationDiv = document.getElementById('validation-' + idArticle);
                if (validationDiv) {
                    validationDiv.innerHTML = '';
                }
                
                const input = document.getElementById('numeroSerie-' + idArticle);
                if (input) {
                    input.classList.remove('is-valid', 'is-invalid');
                }
                
                const btnAjouter = document.getElementById('btn-ajouter-' + idArticle);
                if (btnAjouter) {
                    btnAjouter.disabled = false;
                }
            } catch (error) {
                console.error('Erreur dans fermerFormulaire:', error);
            }
        }
        
        // Auto-focus sur le champ de numéro de série quand le formulaire s'ouvre
        document.addEventListener('shown.bs.collapse', function(e) {
            try {
                if (e.target.id && e.target.id.startsWith('panelsStayOpen-collapse')) {
                    const idArticle = e.target.id.replace('panelsStayOpen-collapse', '');
                    const input = document.getElementById('numeroSerie-' + idArticle);
                    if (input) {
                        input.focus();
                    }
                }
            } catch (error) {
                console.error('Erreur dans l\'auto-focus:', error);
            }
        });

    </script>
</body>
</html>