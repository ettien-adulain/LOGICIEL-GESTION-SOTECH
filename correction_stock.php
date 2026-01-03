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

// 1. TRAITEMENT CORRECTION DE STOCK
if (isset($_POST['correction_stock'])) {
    try {
        $cnx->beginTransaction();

        $id_article = $_POST['moid_article'];
        $id_utilisateur = $_POST['moid_utilisateur'];
        $id_stock = $_POST['moid_stock'];
        $quantite_corrigee = $_POST['moquantite'];
        $prix_achat = $_POST['moprixdachat'];
        $motif = $_POST['motif_correction'];
        $numero_correction = $_POST['monnumerocorrection'];
        $date_correction = $_POST['modateCorrection'];
        $id_utilisateur_creer = $_POST['id_utilisateur_creer'];

        // Vérification du stock actuel
        $stmt = $cnx->prepare("SELECT s.*, a.PrixAchatHT, a.libelle FROM stock s JOIN article a ON s.IDARTICLE = a.IDARTICLE WHERE s.IDARTICLE = ?");
        $stmt->execute([$id_article]);
        $stock_actuel = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stock_actuel) {
            throw new Exception("Article non trouvé dans le stock");
        }
        $nouveau_stock = $stock_actuel['StockActuel'] + $quantite_corrigee;
        if ($nouveau_stock < 0) {
            throw new Exception("La correction ne peut pas donner un stock négatif");
        }

        // --- Détermination du PMP utilisé ---
        $pmp_courant = $stock_actuel['PrixAchatHT'] > 0 ? $stock_actuel['PrixAchatHT'] : $prix_achat;
        $pmp_utilise = $pmp_courant;
        // Correction positive : recalcul PMP
        if ($quantite_corrigee > 0) {
            $stock_avant = $stock_actuel['StockActuel'];
            $nouveau_pmp = (($stock_avant * $pmp_courant) + ($quantite_corrigee * $prix_achat)) / ($stock_avant + $quantite_corrigee);
            $nouveau_pmp = round($nouveau_pmp, 2);
            // Mettre à jour le PMP dans la fiche article
            $stmt = $cnx->prepare("UPDATE article SET PrixAchatHT = ? WHERE IDARTICLE = ?");
            $stmt->execute([$nouveau_pmp, $id_article]);
            $pmp_utilise = $nouveau_pmp;
        }
        // Correction négative : on garde le PMP courant

        // Calcul de la valeur de la correction
        $valeur_correction = $quantite_corrigee * $pmp_utilise;

        // 1. Vérification des numéros de série à ajouter
        if ($quantite_corrigee > 0 && !empty($_POST['num_serie_ajout'])) {
            foreach ($_POST['num_serie_ajout'] as $num_serie) {
                if (empty($num_serie)) continue;
                $stmt = $cnx->prepare("SELECT statut FROM num_serie WHERE NUMERO_SERIE = ? AND IDARTICLE = ?");
                $stmt->execute([$num_serie, $id_article]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if ($row['statut'] === 'PERDU' || $row['statut'] === 'INTROUVABLE') {
                        // Réactivation
                        $stmt = $cnx->prepare("UPDATE num_serie SET statut = 'disponible', DateIns = ? WHERE NUMERO_SERIE = ? AND IDARTICLE = ?");
                        $stmt->execute([$date_correction, $num_serie, $id_article]);
                        // Journaliser
                        $stmt = $cnx->prepare("INSERT INTO journal_num_serie (NUMERO_SERIE, id_article, action, ancien_statut, nouveau_statut, date_action, utilisateur, motif) VALUES (?, ?, 'RETOUR', ?, 'DISPONIBLE', ?, ?, ?)");
                        $stmt->execute([$num_serie, $id_article, $row['statut'], $date_correction, $id_utilisateur, $motif]);
                    } else {
                        throw new Exception("Le numéro de série '$num_serie' existe déjà avec le statut '{$row['statut']}'. Aucun ajout n'a été effectué.");
                    }
                } else {
                    // Ajout normal
                    $stmt = $cnx->prepare("INSERT INTO num_serie (IDARTICLE, NUMERO_SERIE, DATE_ENTREE, statut) VALUES (?, ?, ?, 'disponible')");
                    $stmt->execute([$id_article, $num_serie, $date_correction]);
                    // Journaliser
                    $stmt = $cnx->prepare("INSERT INTO journal_num_serie (NUMERO_SERIE, id_article, action, ancien_statut, nouveau_statut, date_action, utilisateur, motif) VALUES (?, ?, 'AJOUT', NULL, 'DISPONIBLE', ?, ?, ?)");
                    $stmt->execute([$num_serie, $id_article, $date_correction, $id_utilisateur, $motif]);
                }
            }
        }

        // 2. Vérification et traitement des numéros de série à retirer
        if ($quantite_corrigee < 0 && !empty($_POST['num_serie_retrait'])) {
            foreach ($_POST['num_serie_retrait'] as $num_serie) {
                if (empty($num_serie)) continue;
                $stmt = $cnx->prepare("SELECT statut FROM num_serie WHERE NUMERO_SERIE = ? AND IDARTICLE = ?");
                $stmt->execute([$num_serie, $id_article]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new Exception("Le numéro de série '$num_serie' n'existe pas pour cet article. Aucun retrait n'a été effectué.");
                }
                if ($row['statut'] !== 'disponible') {
                    throw new Exception("Le numéro de série '$num_serie' n'est pas disponible pour retrait (statut actuel : '{$row['statut']}'). Aucun retrait n'a été effectué.");
                }
                
                // Marquer le numéro de série comme INTROUVABLE lors du retrait
                $stmt = $cnx->prepare("UPDATE num_serie SET statut = 'INTROUVABLE', DateMod = ? WHERE NUMERO_SERIE = ? AND IDARTICLE = ?");
                $stmt->execute([$date_correction, $num_serie, $id_article]);
                
                // Journaliser le retrait
                $stmt = $cnx->prepare("INSERT INTO journal_num_serie (NUMERO_SERIE, id_article, action, ancien_statut, nouveau_statut, date_action, utilisateur, motif) VALUES (?, ?, 'RETRAIT', 'DISPONIBLE', 'INTROUVABLE', ?, ?, ?)");
                $stmt->execute([$num_serie, $id_article, $date_correction, $id_utilisateur, $motif]);
            }
        }

        // Insertion de la correction avec PMP utilisé, valeur et stock final
        $sql = "INSERT INTO correction (NumeroCorrection, DateMouvementStock, QuantiteMoved, PrixAchat, PMP_utilise, ValeurCorrection, StockFinal, IDSTOCK, ID_utilisateurs, IDMOTIF_MOUVEMENT_STOCK, UtilCrea) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([
            $numero_correction,
            $date_correction,
            $quantite_corrigee,
            $prix_achat,
            $pmp_utilise,
            $valeur_correction,
            $nouveau_stock, // Stock final après correction
            $id_stock,
            $id_utilisateur,
            $motif,
            $id_utilisateur_creer
        ]);

        // Mise à jour du stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$nouveau_stock, $id_article]);

        // --- JOURNALISATION : Correction de stock ---
        $description_correction = sprintf(
            "Correction de stock - Article: %s - Stock avant: %d - Stock après: %d - Quantité: %+d - PMP: %.2f FCFA",
            $stock_actuel['libelle'],
            $stock_actuel['StockActuel'],
            $nouveau_stock,
            $quantite_corrigee,
            $pmp_utilise
        );
        
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'CORRECTION_STOCK_MANUELLE',
                'STOCK',
                'correction_stock.php',
                $description_correction,
                [
                    'id_article' => $id_article,
                    'id_stock' => $id_stock,
                    'stock_avant' => $stock_actuel['StockActuel'],
                    'stock_apres' => $nouveau_stock,
                    'quantite_corrigee' => $quantite_corrigee,
                    'prix_achat' => $prix_achat,
                    'pmp_utilise' => $pmp_utilise,
                    'valeur_correction' => $valeur_correction,
                    'numero_correction' => $numero_correction,
                    'motif' => $motif
                ],
                [
                    'action' => 'correction_manuelle',
                    'type_correction' => $quantite_corrigee > 0 ? 'ajout' : 'retrait',
                    'pmp_recalcule' => $quantite_corrigee > 0,
                    'numeros_serie_traites' => !empty($_POST['num_serie_ajout']) || !empty($_POST['num_serie_retrait'])
                ],
                'HIGH',
                'SUCCESS',
                null
            );
        }
        // --- FIN JOURNALISATION ---

        $cnx->commit();
        $_SESSION['success_message'] = "Correction de stock effectuée avec succès. Stock actuel : " . $nouveau_stock;
        
        // Redirection avec fallback JavaScript
        if (!headers_sent()) {
            header('Location: correction_stock.php');
            exit();
        } else {
            // Fallback si les headers sont déjà envoyés
            echo '<script>window.location.href = "correction_stock.php";</script>';
            echo '<meta http-equiv="refresh" content="0;url=correction_stock.php">';
            exit();
        }
    } catch (Exception $e) {
        $cnx->rollBack();
        
        // --- JOURNALISATION : Erreur correction de stock ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_CORRECTION_STOCK',
                'STOCK',
                'correction_stock.php',
                'Erreur lors de la correction de stock : ' . $e->getMessage(),
                [
                    'id_article' => $_POST['moid_article'] ?? null,
                    'quantite_corrigee' => $_POST['moquantite'] ?? null,
                    'numero_correction' => $_POST['monnumerocorrection'] ?? null,
                    'erreur' => $e->getMessage()
                ],
                null,
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        $_SESSION['error_message'] = "Erreur lors de la correction : " . $e->getMessage();
        
        // Redirection avec fallback JavaScript
        if (!headers_sent()) {
            header('Location: correction_stock.php');
            exit();
        } else {
            // Fallback si les headers sont déjà envoyés
            echo '<script>window.location.href = "correction_stock.php";</script>';
            echo '<meta http-equiv="refresh" content="0;url=correction_stock.php">';
            exit();
        }
    }
}

// 2. TRAITEMENT RECHERCHE DE BON DE LIVRAISON ET PRÉPARATION DES DONNÉES POUR LE FORMULAIRE
try {
    $corrections = selection_element('correction');
    $nombre_correction = count($corrections);
} catch (Exception $e) {
    $corrections = [];
    $nombre_correction = 0;
}
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'XX';
$numerodecorrection = date('dmY') . substr($nom_utilisateur, 0, 2) . '0'. $nombre_correction;
        $code_stock=0;
        $motifs=0;
        $createur_stock=0;
$articles = [];
        
        if (isset($_POST['recherche'])) {
            $code = $_POST['Code'];
            $verifieNumero_bon = verifier_element('entree_en_stock', ['Numero_bon'], [$code], '');
            if (!$verifieNumero_bon || !isset($verifieNumero_bon['IDENTREE_STOCK'])) {
                $erreur = 'Numéro de bon invalide'; 
        header('Location: correction_stock.php?error=' . urlencode($erreur));
                exit();
            }
            $code_stock_entrer = $verifieNumero_bon['IDENTREE_STOCK'];
            try {
            $ancienstockEntrer = verifier_element('entree_en_stock', ['IDENTREE_STOCK'], [$code_stock_entrer], '');
                if (!$ancienstockEntrer) {
                    throw new Exception("Erreur lors de la récupération des données du bon d'entrée. IDENTREE_STOCK: " . $code_stock_entrer);
                }
            $createur_stock = $ancienstockEntrer['ID_utilisateurs'];
        $sql = "SELECT esl.*, a.libelle, a.descriptif, a.PrixAchatHT, s.StockActuel, s.IDSTOCK FROM entree_stock_ligne esl JOIN article a ON esl.IDARTICLE = a.IDARTICLE JOIN stock s ON a.IDARTICLE = s.IDARTICLE WHERE esl.IDENTREE_EN_STOCK = ?";
            $stmt = $cnx->prepare($sql);
            $stmt->execute([$code_stock_entrer]);
            $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($articles)) {
                    throw new Exception("Aucun article trouvé pour ce bon d'entrée");
                }
                try {
                    $motifs = selection_element('motif_correction');
                } catch (Exception $e) {
                    $motifs = [];
                }
            } catch (Exception $e) {
        header('Location: correction_stock.php?error=' . urlencode($e->getMessage()));
                exit();
            }
        } elseif (isset($_GET['id'])) {
            $code_stock_entrer = htmlspecialchars($_GET['id']);
            try {
            $ancienstockEntrer = verifier_element('entree_en_stock', ['IDENTREE_STOCK'], [$code_stock_entrer], '');
                if (!$ancienstockEntrer) {
                    throw new Exception("Erreur lors de la récupération des données du bon d'entrée. IDENTREE_STOCK: " . $code_stock_entrer);
                }
            $createur_stock = $ancienstockEntrer['ID_utilisateurs'];
        $sql = "SELECT esl.*, a.libelle, a.descriptif, a.PrixAchatHT, s.StockActuel, s.IDSTOCK FROM entree_stock_ligne esl JOIN article a ON esl.IDARTICLE = a.IDARTICLE JOIN stock s ON a.IDARTICLE = s.IDARTICLE WHERE esl.IDENTREE_EN_STOCK = ?";
                $stmt = $cnx->prepare($sql);
                $stmt->execute([$code_stock_entrer]);
                $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($articles)) {
                    throw new Exception("Aucun article trouvé pour ce bon d'entrée");
                }
                try {
                    $motifs = selection_element('motif_correction');
                } catch (Exception $e) {
                    $motifs = [];
                }
            } catch (Exception $e) {
        header('Location: correction_stock.php?error=' . urlencode($e->getMessage()));
                exit();
            }
        } elseif (isset($_POST['recherche_article'])) {
            // NOUVELLE FONCTIONNALITÉ : Recherche directe par libellé d'article
            $libelle_article = $_POST['libelle_article'];
            try {
                // Recherche des articles par libellé (recherche partielle)
                $sql = "SELECT DISTINCT a.IDARTICLE, a.libelle, a.descriptif, a.PrixAchatHT, s.StockActuel, s.IDSTOCK 
                        FROM article a 
                        JOIN stock s ON a.IDARTICLE = s.IDARTICLE 
                        WHERE a.libelle LIKE ? 
                        ORDER BY a.libelle";
                $stmt = $cnx->prepare($sql);
                $stmt->execute(['%' . $libelle_article . '%']);
                $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($articles)) {
                    throw new Exception("Aucun article trouvé avec le libellé : " . $libelle_article);
                }
                
                // Pour la recherche par article, on met createur_stock à 0 (pas de bon d'entrée spécifique)
                $createur_stock = 0;
                try {
                    $motifs = selection_element('motif_correction');
                } catch (Exception $e) {
                    $motifs = [];
                }
            } catch (Exception $e) {
                header('Location: correction_stock.php?error=' . urlencode($e->getMessage()));
                exit();
            }
        }
// Toujours charger les motifs pour le formulaire
if (!$motifs) {
    try {
        $sql = "SELECT `IDMOTIF_MOUVEMENT_STOCK`, `LibelleMotifMouvementStock` FROM `motif_correction` ORDER BY `LibelleMotifMouvementStock`";
        $stmt = $cnx->prepare($sql);
        $stmt->execute();
        $motifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $motifs = [];
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction de Stock</title>
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

        button {
            margin-right: 0.5rem;
        }

        .modal-header {
            background-color: #ff0000;
            color: white;
        }

        .btn-navigation {
            background-color: #ff0000;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn-navigation:hover {
            background-color: #cc0000;
            text-decoration: none;
            color: white;
        }

        .navigation-links {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        /* Nouveaux styles pour la table des articles */
        .table-responsive {
            margin-top: 1rem;
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #343a40;
            color: white;
            border: none;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .select-article {
            transition: all 0.3s ease;
        }

        .select-article:hover {
            transform: scale(1.05);
        }

        #articleSearch {
            border-radius: 20px;
            padding: 10px 20px;
            border: 2px solid #ddd;
            transition: all 0.3s ease;
        }

        #articleSearch:focus {
            border-color: #ff0000;
            box-shadow: 0 0 0 0.2rem rgba(255, 0, 0, 0.25);
        }

        .article-row {
            transition: all 0.3s ease;
        }

        .article-row:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }

        /* Styles pour les onglets de recherche */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
            color: #495057;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            border-color: #e9ecef #e9ecef #dee2e6;
            background-color: #e9ecef;
        }

        .nav-tabs .nav-link.active {
            color: #495057;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            border-bottom: 2px solid #ff0000;
        }

        .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            padding: 1.5rem;
            background-color: #fff;
        }
    </style>
   
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
    
    <header>
        <h1><i class="fas fa-clipboard-list"></i> Correction de Stock</h1>
    </header>

    <main class="container">
    <br><br>
        

        <?php
        // Affichage des messages d'erreur
        if (isset($_GET['error'])) {
            $errorMessage = htmlspecialchars($_GET['error']);
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> ' . $errorMessage . '
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                  </div>';
        }
        // Affichage des messages de succès
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> ' . nl2br(htmlspecialchars($_SESSION['success_message'])) . '
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                  </div>';
            unset($_SESSION['success_message']);
        }
        // Affichage des messages d'erreur de session
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> ' . nl2br(htmlspecialchars($_SESSION['error_message'])) . '
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                  </div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <section id="correction-form">

            <?php if (!isset($_GET['id'])) {?> 

            <div class="form-container">
                <h2><i class="fas fa-search"></i> Chercher le stock à corriger</h2>
                
                <!-- Onglets pour choisir le mode de recherche -->
                <ul class="nav nav-tabs mb-4" id="searchTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="bon-tab" data-toggle="tab" href="#bon-search" role="tab" aria-controls="bon-search" aria-selected="true">
                            <i class="fas fa-file-invoice"></i> Par Bon de Livraison
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="article-tab" data-toggle="tab" href="#article-search" role="tab" aria-controls="article-search" aria-selected="false">
                            <i class="fas fa-box"></i> Par Libellé d'Article
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="searchTabContent">
                    <!-- Recherche par Bon de Livraison -->
                    <div class="tab-pane fade show active" id="bon-search" role="tabpanel" aria-labelledby="bon-tab">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <form id="searchForm" method="post">
                                    <div class="form-group">
                                        <label for="produit"><i class="fas fa-file-invoice"></i> Numéro de Bon de Livraison</label>
                                        <?php
                                            if (isset($_GET['error_r'])) {
                                                $errorMessage = htmlspecialchars($_GET['error_r']);
                                                echo '<div class="text-danger" role="alert">' . $errorMessage . '</div>';
                                            }
                                        ?>
                                        <input type="text" id="searchQuery" name="Code" class="form-control" placeholder="Entrez le numéro du bon de livraison" required>
                                        <small class="form-text text-muted">Recherche par numéro de bon d'entrée en stock</small>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" name="recherche" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Rechercher par Bon
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Recherche par Libellé d'Article -->
                    <div class="tab-pane fade" id="article-search" role="tabpanel" aria-labelledby="article-tab">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <form id="searchArticleForm" method="post">
                                    <div class="form-group">
                                        <label for="libelle_article"><i class="fas fa-box"></i> Libellé de l'Article</label>
                                        <input type="text" id="libelle_article" name="libelle_article" class="form-control" placeholder="Entrez le nom ou une partie du nom de l'article" required>
                                        <small class="form-text text-muted">Recherche directe par nom d'article (recherche partielle)</small>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" name="recherche_article" class="btn btn-success">
                                            <i class="fas fa-search"></i> Rechercher par Article
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php }?>

            <?php if (isset($articles) && !empty($articles)) { ?>
            <div class="form-container">
                <h2><i class="fas fa-list"></i> Articles du bon</h2>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="articleSearch" class="form-control" placeholder="Rechercher un article...">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Article</th>
                                <th>Référence</th>
                                <th>Stock Actuel</th>
                                <?php if (user_can_see_purchase_prices()): ?>
                                    <th>Prix d'Achat</th>
                                <?php endif; ?>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article): ?>
                            <tr class="article-row">
                                <td><?= htmlspecialchars($article['libelle']) ?></td>
                                <td><?= htmlspecialchars($article['descriptif']) ?></td>
                                <td><?= htmlspecialchars($article['StockActuel']) ?></td>
                                <?php if (user_can_see_purchase_prices()): ?>
                                    <td><?= htmlspecialchars($article['PrixAchatHT']) ?></td>
                                <?php endif; ?>
                                <td>
                                    <button type="button" class="btn btn-primary select-article" 
                                            data-article-id="<?= $article['IDARTICLE'] ?>"
                                            data-article-libelle="<?= htmlspecialchars($article['libelle']) ?>"
                                            data-article-reference="<?= htmlspecialchars($article['descriptif']) ?>"
                                            data-article-stock="<?= htmlspecialchars($article['StockActuel']) ?>"
                                            data-article-prix="<?= htmlspecialchars($article['PrixAchatHT']) ?>"
                                            data-article-idstock="<?= $article['IDSTOCK'] ?>">
                                        <i class="fas fa-check"></i> Sélectionner
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>

            <div class="form-container">
                <h2><i class="fas fa-info-circle"></i> Correction d'un Article</h2>
                <form action="correction_stock.php" method="post" id="correctionForm">
                <input type="hidden" name="correction_stock" value="1">
                <input type="hidden" name="moid_utilisateur" id="id_utilisateur" value="<?php echo $_SESSION['id_utilisateur'] ?? 0;?>">
                <input type="hidden" name="id_utilisateur_creer" id="id_utilisateur_creer" value="<?php echo $createur_stock;?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="produit"><i class="fas fa-tag"></i> Produit</label>
                                <select id="produit" name="moproduit" class="form-control" required>
                                    <option value="">Sélectionner un article</option>
                                    <?php if (isset($articles) && is_array($articles)): ?>
                                    <?php foreach ($articles as $article): ?>
                                    <option value="<?= htmlspecialchars($article['libelle']) ?>" 
                                            data-id="<?= $article['IDARTICLE'] ?>"
                                            data-stock="<?= $article['StockActuel'] ?>"
                                            data-prix="<?= $article['PrixAchatHT'] ?>"
                                            data-idstock="<?= $article['IDSTOCK'] ?>">
                                        <?= htmlspecialchars($article['libelle']) ?> (<?= htmlspecialchars($article['descriptif']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="identifiant"><i class="fas fa-id-badge"></i> Identifiant</label>
                                <input type="text" id="identifiant" name="moidentifiant" class="form-control" placeholder="Identifiant" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group">
                                <label for="stock"><i class="fas fa-cubes"></i> Stock</label>
                                <input type="number" id="stock" class="form-control" placeholder="Stock" name="mostock" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group">
                                <label for="quantiteCorrigee"><i class="fas fa-sort-amount-up"></i> Quantité Corrigée</label>
                                <input type="number" id="moquantite" name="moquantite" class="form-control" placeholder="Quantité Corrigée" required>
                                <p class="alert alert-info mt-3 mb-2">
                                    <strong>Entrez :</strong><br>
                                    Une valeur positive (+) pour ajouter des articles au stock<br>
                                    Une valeur négative (-) pour retirer des articles du stock<br>
                                    <em>Exemple : Si vous avez 10 articles et que vous entrez -3, le nouveau stock sera de 7 articles.</em>
                                </p>
                                <div id="numSerieContainer" class="card shadow-sm mb-3" style="background: #f8fafd; border-radius: 12px; border: 1px solid #e0e0e0; display: none;">
                                    <div class="card-header bg-light" style="border-bottom: 1px solid #e0e0e0;">
                                        <i class="fas fa-barcode text-primary"></i> <span id="numSerieTitle">Numéros de série</span>
                                    </div>
                                    <div class="card-body" id="numSerieFields" style="padding-top: 1rem;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group">
                                <?php if (user_can_see_purchase_prices()): ?>
                                    <label for="prixAchat"><i class="fas fa-money-bill-wave"></i> Prix d'Achat</label>
                                    <input type="number" id="prixAchat" class="form-control" placeholder="Prix d'Achat" name="moprixdachat" required>
                                <?php else: ?>
                                    <!-- Champ caché avec PMP actuel -->
                                    <input type="hidden" name="moprixdachat" id="prixAchatHidden" value="0">
                                    <label for="prixAchat"><i class="fas fa-money-bill-wave"></i> Prix d'Achat</label>
                                    <input type="text" class="form-control" value="*** CONFIDENTIEL ***" readonly style="background-color: #f8f9fa; color: #6c757d;">
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="hidden" name="moid_article" id="id_article">
                        <input type="hidden" name="moid_stock" id="id_stock">
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="motif"><i class="fas fa-clipboard-check"></i> Motif de Correction</label>
                                <select id="motif" name="motif_correction" class="form-control" required>
                                    <option value="">Sélectionner un motif</option>
                                    <?php foreach ($motifs as $motif): ?>
                                        <option value="<?= $motif['IDMOTIF_MOUVEMENT_STOCK']; ?>">
                                            <?= htmlspecialchars($motif['LibelleMotifMouvementStock']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="numeroCorrection"><i class="fas fa-file-invoice"></i> Numéro de la Correction</label>
                                <input type="text" id="numeroCorrection" class="form-control" name="monnumerocorrection" placeholder="Numéro de la Correction" value="<?= htmlspecialchars($numerodecorrection ) ?>" required readonly >
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="dateCorrection"><i class="fas fa-calendar-alt"></i> Date de Correction</label>
                                <input type="date" id="dateCorrection" name="modateCorrection" class="form-control" required>
                    </div>
                </div>
            </div>
                    <div class="form-group text-center mt-4">
                        <?php if (can_user('correction_stock', 'valider')): ?>
                        <button type="submit" class="btn btn-danger btn-lg" id="validate-btn" name="correction_stock">
                            <i class="fas fa-check"></i> Valider la Correction de Stock
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-danger btn-lg" disabled title="Accès refusé">
                            <i class="fas fa-check"></i> Valider la Correction de Stock
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <!-- Modal de confirmation -->
    <div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel"><i class="fas fa-check-circle"></i> Validation réussie</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    La Correction de Stock a été validée avec succès !
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fas fa-times"></i> Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
    // Fonction pour générer les champs de numéros de série
    function updateNumSerieFields() {
        const quantite = parseInt($('#moquantite').val()) || 0;
        const idArticle = $('#id_article').val();
        const container = $('#numSerieFields');
        const box = $('#numSerieContainer');
        const title = $('#numSerieTitle');

        container.empty();
        if (!idArticle || quantite === 0) {
            box.hide();
                return;
            }

        box.show();
        if (quantite > 0) {
            title.text('Numéros de série à ajouter');
            for (let i = 0; i < quantite; i++) {
                container.append(`
                    <div class="form-group mb-2">
                        <label>Numéro de série à ajouter #${i+1}</label>
                        <input type="text" name="num_serie_ajout[]" class="form-control" required>
                    </div>
                `);
            }
        } else if (quantite < 0) {
            title.text('Numéros de série à retirer');
            for (let i = 0; i < Math.abs(quantite); i++) {
                container.append(`
                    <div class="form-group mb-2">
                        <label>Numéro de série à retirer #${i+1}</label>
                        <input type="text" name="num_serie_retrait[]" class="form-control" required>
                    </div>
                `);
            }
        }
    }

    // Mise à jour auto quand on change la quantité ou l'article
    $('#moquantite, #id_article').on('input change', updateNumSerieFields);

    // Pré-remplir la date du jour
    const today = new Date().toISOString().split('T')[0];
    $('#dateCorrection').val(today);

    // Gestion de la sélection d'article depuis le bouton "Sélectionner"
    $('.select-article').on('click', function() {
        const idArticle = $(this).data('article-id');
        const libelle = $(this).data('article-libelle');
        const descriptif = $(this).data('article-reference');
        const stock = $(this).data('article-stock');
        const prix = $(this).data('article-prix');
        const idStock = $(this).data('article-idstock');

        $('#id_article').val(idArticle);
        $('#id_stock').val(idStock);
        $('#produit').val(libelle);
        $('#identifiant').val(descriptif);
        $('#stock').val(stock);
        <?php if (user_can_see_purchase_prices()): ?>
            $('#prixAchat').val(prix);
        <?php else: ?>
            $('#prixAchatHidden').val(prix);
        <?php endif; ?>

        updateNumSerieFields();
    });

    // Gestion de la sélection d'article depuis la liste déroulante
    $('#produit').on('change', function() {
        const selected = $(this).find('option:selected');
        if (selected.val()) {
            const idArticle = selected.data('id');
            const stock = selected.data('stock');
            const prix = selected.data('prix');
            const idStock = selected.data('idstock');
            const descriptif = selected.text().match(/\((.*?)\)/)?.[1] || '';

            $('#id_article').val(idArticle);
            $('#id_stock').val(idStock);
            $('#identifiant').val(descriptif);
            $('#stock').val(stock);
            <?php if (user_can_see_purchase_prices()): ?>
                $('#prixAchat').val(prix);
            <?php else: ?>
                $('#prixAchatHidden').val(prix);
            <?php endif; ?>

            updateNumSerieFields();
        }
    });

    // Gestion de la validation du formulaire
    $('#correctionForm').on('submit', function(e) {
        e.preventDefault();

        const quantite = parseInt($('#moquantite').val());
        const idArticle = $('#id_article').val();
        const idStock = $('#id_stock').val();
        const dateCorrection = $('#dateCorrection').val();
        const motif = $('#motif').val();
        <?php if (user_can_see_purchase_prices()): ?>
            const prixAchat = parseFloat($('#prixAchat').val());
        <?php else: ?>
            const prixAchat = parseFloat($('#prixAchatHidden').val());
        <?php endif; ?>

        if (!idArticle || !idStock) {
            alert('Veuillez sélectionner un article (et son stock).');
            return;
        }

        if (isNaN(quantite) || quantite === 0) {
            alert('Veuillez entrer une quantité valide (différente de 0).');
            return;
        }

        if (!dateCorrection) {
            alert('Veuillez sélectionner une date de correction.');
            return;
        }

        if (!motif) {
            alert('Veuillez sélectionner un motif de correction.');
            return;
        }

        <?php if (user_can_see_purchase_prices()): ?>
            if (isNaN(prixAchat) || prixAchat <= 0) {
                alert('Veuillez entrer un prix d\'achat valide.');
                return;
            }
        <?php endif; ?>

        // Vérification des numéros de série
        let allFilled = true;
        if (quantite > 0) {
            $('input[name="num_serie_ajout[]"]').each(function() {
                if (!$(this).val().trim()) allFilled = false;
            });
            if (!allFilled) {
                alert('Veuillez remplir tous les numéros de série à ajouter.');
                return;
            }
        } else if (quantite < 0) {
            $('input[name="num_serie_retrait[]"]').each(function() {
                if (!$(this).val().trim()) allFilled = false;
            });
            if (!allFilled) {
                alert('Veuillez remplir tous les numéros de série à retirer.');
                return;
            }
        }

        // Soumission réelle
        this.submit();
    });

    // Fermeture automatique des alertes après 5 sec
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);

    // Fermeture manuelle des alertes
    $('.alert .close').on('click', function() {
        $(this).closest('.alert').alert('close');
    });

    // Initialiser une fois au chargement
    updateNumSerieFields();
        });
    </script>
</body>
</html>