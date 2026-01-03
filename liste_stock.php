<?php
$start_time = microtime(true);

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

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

// Récupération des paramètres de filtrage
$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';
$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$stock_min = isset($_GET['stock_min']) ? (int)$_GET['stock_min'] : '';
$stock_max = isset($_GET['stock_max']) ? (int)$_GET['stock_max'] : '';
$tri = isset($_GET['tri']) ? $_GET['tri'] : 'stock_desc';
$seuil_alerte = isset($_GET['seuil_alerte']) ? (int)$_GET['seuil_alerte'] : 10;

// Récupération des catégories
$sql_categories = "SELECT * FROM categorie_article ORDER BY nom_categorie";
$stmt_categories = $cnx->prepare($sql_categories);
$stmt_categories->execute();
$categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

// Construction de la requête SQL
$sql = "SELECT s.*, a.libelle as article_libelle, a.descriptif, a.PrixAchatHT, a.PrixVenteTTC, 
               c.nom_categorie, c.id_categorie
        FROM stock s 
        JOIN article a ON s.IDARTICLE = a.IDARTICLE 
        LEFT JOIN categorie_article c ON a.id_categorie = c.id_categorie 
        WHERE 1=1";

$params = [];

// Filtre de recherche
if (!empty($recherche)) {
    $sql .= " AND (a.libelle LIKE ? OR a.descriptif LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

// Filtre par catégorie
if (!empty($categorie)) {
    $sql .= " AND a.id_categorie = ?";
    $params[] = $categorie;
}

// Filtre par stock minimum
if (!empty($stock_min)) {
    $sql .= " AND s.StockActuel >= ?";
    $params[] = $stock_min;
}

// Filtre par stock maximum
if (!empty($stock_max)) {
    $sql .= " AND s.StockActuel <= ?";
    $params[] = $stock_max;
}

// Tri
switch ($tri) {
    case 'stock_asc':
        $sql .= " ORDER BY s.StockActuel ASC";
        break;
    case 'nom_asc':
        $sql .= " ORDER BY a.libelle ASC";
        break;
    case 'nom_desc':
        $sql .= " ORDER BY a.libelle DESC";
        break;
    case 'prix_asc':
        $sql .= " ORDER BY a.PrixAchatHT ASC";
        break;
    case 'prix_desc':
        $sql .= " ORDER BY a.PrixAchatHT DESC";
        break;
    default: // stock_desc
        $sql .= " ORDER BY s.StockActuel DESC";
}

// Pagination simplifiée
$limit = (int)$limit;
$offset = (int)$offset;
$sql .= " LIMIT $limit OFFSET $offset";

// Debug pour voir la requête et les paramètres
if (isset($_GET['debug'])) {
    echo "<!-- Requête SQL: $sql -->";
    echo "<!-- Paramètres: " . print_r($params, true) . " -->";
}

// Exécution de la requête
$stmt = $cnx->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Requête pour le comptage total
$countSql = "SELECT COUNT(*) as total FROM stock s 
             JOIN article a ON s.IDARTICLE = a.IDARTICLE 
             LEFT JOIN categorie_article c ON a.id_categorie = c.id_categorie 
             WHERE 1=1";

$countParams = [];

if (!empty($recherche)) {
    $countSql .= " AND (a.libelle LIKE ? OR a.descriptif LIKE ?)";
    $countParams[] = "%$recherche%";
    $countParams[] = "%$recherche%";
}

if (!empty($categorie)) {
    $countSql .= " AND a.id_categorie = ?";
    $countParams[] = $categorie;
}

if (!empty($stock_min)) {
    $countSql .= " AND s.StockActuel >= ?";
    $countParams[] = $stock_min;
}

if (!empty($stock_max)) {
    $countSql .= " AND s.StockActuel <= ?";
    $countParams[] = $stock_max;
}

$countStmt = $cnx->prepare($countSql);
$countStmt->execute($countParams);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

$total_pages = ceil($total / $limit);

$end_time = microtime(true);
$execution_time = round(($end_time - $start_time) * 1000, 2); // en millisecondes

// Affichage du temps d'exécution en mode debug
if (isset($_GET['debug'])) {
    echo "<!-- Temps d'exécution: {$execution_time}ms -->";
    echo "<!-- Nombre de résultats: " . count($stocks) . " -->";
}

// Calcul des statistiques
$stats = [
    'total_articles' => 0,
    'total_stock' => 0,
    'moyenne_stock' => 0,
    'min_stock' => 0,
    'valeur_stock_achat' => 0,
    'valeur_stock_vente' => 0
];

if (!empty($stocks)) {
    $stats['total_articles'] = count($stocks);
    $stats['total_stock'] = array_sum(array_column($stocks, 'StockActuel'));
    $stats['moyenne_stock'] = $stats['total_articles'] > 0 ? round($stats['total_stock'] / $stats['total_articles']) : 0;
    $stats['min_stock'] = min(array_column($stocks, 'StockActuel'));
    
    // Calcul des valeurs du stock
    foreach ($stocks as $stock) {
        $stats['valeur_stock_achat'] += $stock['StockActuel'] * $stock['PrixAchatHT'];
        $stats['valeur_stock_vente'] += $stock['StockActuel'] * $stock['PrixVenteTTC'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Stocks</title>
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
            max-width: 1400px;
            width: 100%;
            margin: 20px auto;
            padding: 0 20px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card i {
            font-size: 2rem;
            color: #ff0000;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }

        .table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background-color: #343a40;
        }

        .stock-alerte {
            background-color: #fff3cd;
        }

        .stock-critique {
            background-color: #f8d7da;
        }

        .filter-form {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            position: sticky;
            top: 0;
            z-index: 2;
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

        .pagination {
            margin-top: 1rem;
            justify-content: center;
        }

        .page-link {
            color: #ff0000;
            padding: 0.5rem 1rem;
        }

        .page-item.active .page-link {
            background-color: #ff0000;
            border-color: #ff0000;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1><i class="fas fa-boxes"></i> Liste des Stocks</h1>
    </header>

    <main class="container">
       

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Articles</h5>
                        <p class="card-text h3"><?= number_format($stats['total_articles'] ?? 0, 0, ',', ' ') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Stock Total</h5>
                        <p class="card-text h3"><?= number_format($stats['total_stock'] ?? 0, 0, ',', ' ') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Stock Moyen</h5>
                        <p class="card-text h3"><?= number_format($stats['moyenne_stock'] ?? 0, 0, ',', ' ') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Stock Minimum</h5>
                        <p class="card-text h3"><?= number_format($stats['min_stock'] ?? 0, 0, ',', ' ') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Valeurs du stock -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Valeur Stock (Achat)</h5>
                        <p class="card-text h3"><?= number_format($stats['valeur_stock_achat'] ?? 0, 0, ',', ' ') ?> F.CFA</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Valeur Stock (Vente)</h5>
                        <p class="card-text h3"><?= number_format($stats['valeur_stock_vente'] ?? 0, 0, ',', ' ') ?> F.CFA</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message si aucun résultat -->
        <?php if (empty($stocks)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Aucun article ne correspond à vos critères de recherche.
        </div>
        <?php endif; ?>

        <!-- Filtres -->
            <div class="form-container">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="recherche">Recherche</label>
                            <input type="text" class="form-control" id="recherche" name="recherche" 
                                   value="<?= htmlspecialchars($recherche) ?>" 
                                   placeholder="Nom ou référence"
                                   title="Rechercher par nom d'article ou référence"
                                   onkeyup="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="categorie">Catégorie</label>
                            <select class="form-control" id="categorie" name="categorie" onchange="this.form.submit()">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id_categorie'] ?>" 
                                            <?= $categorie == $cat['id_categorie'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nom_categorie']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="stock_min">Stock Min</label>
                            <input type="number" class="form-control" id="stock_min" name="stock_min" 
                                   value="<?= htmlspecialchars($stock_min) ?>"
                                   title="Afficher les articles avec un stock supérieur ou égal à cette valeur"
                                   onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="stock_max">Stock Max</label>
                            <input type="number" class="form-control" id="stock_max" name="stock_max" 
                                   value="<?= htmlspecialchars($stock_max) ?>"
                                   title="Afficher les articles avec un stock inférieur ou égal à cette valeur"
                                   onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="tri">Trier par</label>
                            <select class="form-control" id="tri" name="tri" onchange="this.form.submit()">
                                <option value="stock_desc" <?= $tri == 'stock_desc' ? 'selected' : '' ?>>Stock (Du plus élevé au plus bas)</option>
                                <option value="stock_asc" <?= $tri == 'stock_asc' ? 'selected' : '' ?>>Stock (Du plus bas au plus élevé)</option>
                                <option value="nom_asc" <?= $tri == 'nom_asc' ? 'selected' : '' ?>>Nom (A à Z)</option>
                                <option value="nom_desc" <?= $tri == 'nom_desc' ? 'selected' : '' ?>>Nom (Z à A)</option>
                                <option value="prix_asc" <?= $tri == 'prix_asc' ? 'selected' : '' ?>>Prix d'achat (Du moins cher au plus cher)</option>
                                <option value="prix_desc" <?= $tri == 'prix_desc' ? 'selected' : '' ?>>Prix d'achat (Du plus cher au moins cher)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="limit">Lignes par page</label>
                            <select class="form-control" id="limit" name="limit" onchange="this.form.submit()">
                                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="form-group text-right">
                            <a href="verification_stock_consistency.php" class="btn btn-warning" title="Vérifier la cohérence entre le stock et les numéros de série">
                                <i class="fas fa-balance-scale"></i> Vérifier Cohérence
                            </a>
                            <a href="liste_stock.php" class="btn btn-secondary" title="Réinitialiser tous les filtres">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </a>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-success dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Exporter les données dans différents formats">
                                    <i class="fas fa-file-export"></i> Exporter
                                </button>
                                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                    <a class="dropdown-item" href="export_stock.php?format=csv<?= !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>" title="Exporter au format CSV">
                                        <i class="fas fa-file-csv"></i> CSV
                                    </a>
                                    <a class="dropdown-item" href="export_stock.php?format=excel<?= !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>" title="Exporter au format Excel">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </a>
                                    <a class="dropdown-item" href="export_stock.php?format=word<?= !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>" title="Exporter au format Word">
                                        <i class="fas fa-file-word"></i> Word
                                    </a>
                                    <a class="dropdown-item" href="export_stock.php?format=txt<?= !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>" title="Exporter au format texte">
                                        <i class="fas fa-file-alt"></i> TXT
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
                    </div>

        <!-- Tableau des stocks -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Article</th>
                        <th>Référence</th>
                        <th>Catégorie</th>
                        <th>Stock</th>
                        <th>Prix Achat HT</th>
                        <th>Prix Vente TTC</th>
                        <th>
                            Marge brute
                            <span data-toggle="tooltip" title="Marge brute = Prix de vente TTC - Prix d'achat HT (PMP). C'est le gain théorique par unité vendue, hors frais et charges.">
                                <i class="fas fa-info-circle text-info"></i>
                            </span>
                        </th>
                        <th>Valeur Stock (Achat)</th>
                        <th>Valeur Stock (Vente)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stocks)): ?>
                        <tr>
                            <td colspan="10" class="text-center">Aucun stock trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stocks as $stock): ?>
                            <tr class="<?= $stock['StockActuel'] <= $seuil_alerte ? 'stock-alerte' : '' ?>">
                                <td><?= htmlspecialchars($stock['article_libelle']) ?></td>
                                <td><?= htmlspecialchars($stock['descriptif']) ?></td>
                                <td><?= htmlspecialchars($stock['nom_categorie']) ?></td>
                                <td><?= htmlspecialchars($stock['StockActuel']) ?></td>
                                <td><?= number_format($stock['PrixAchatHT'], 0, ',', ' ') ?> F.CFA</td>
                                <td><?= number_format($stock['PrixVenteTTC'], 0, ',', ' ') ?> F.CFA</td>
                                <td>
                                    <?php
                                    $marge = $stock['PrixVenteTTC'] - $stock['PrixAchatHT'];
                                    $marge_percent = $stock['PrixVenteTTC'] > 0 ? ($marge / $stock['PrixVenteTTC']) * 100 : 0;
                                    echo number_format($marge, 0, ',', ' ') . ' F.CFA (' . round($marge_percent, 1) . '%)';
                                    ?>
                                </td>
                                <td><?= number_format($stock['StockActuel'] * $stock['PrixAchatHT'], 0, ',', ' ') ?> F.CFA</td>
                                <td><?= number_format($stock['StockActuel'] * $stock['PrixVenteTTC'], 0, ',', ' ') ?> F.CFA</td>
                               
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Pagination">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?>&recherche=<?= $recherche ?>&categorie=<?= $categorie ?>&stock_min=<?= $stock_min ?>&stock_max=<?= $stock_max ?>&tri=<?= $tri ?>&seuil_alerte=<?= $seuil_alerte ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&limit=' . $limit . '&recherche=' . $recherche . '&categorie=' . $categorie . '&stock_min=' . $stock_min . '&stock_max=' . $stock_max . '&tri=' . $tri . '&seuil_alerte=' . $seuil_alerte . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&recherche=<?= $recherche ?>&categorie=<?= $categorie ?>&stock_min=<?= $stock_min ?>&stock_max=<?= $stock_max ?>&tri=<?= $tri ?>&seuil_alerte=<?= $seuil_alerte ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor;

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&limit=' . $limit . '&recherche=' . $recherche . '&categorie=' . $categorie . '&stock_min=' . $stock_min . '&stock_max=' . $stock_max . '&tri=' . $tri . '&seuil_alerte=' . $seuil_alerte . '">' . $total_pages . '</a></li>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?>&recherche=<?= $recherche ?>&categorie=<?= $categorie ?>&stock_min=<?= $stock_min ?>&stock_max=<?= $stock_max ?>&tri=<?= $tri ?>&seuil_alerte=<?= $seuil_alerte ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </main>

    <!-- jQuery, Popper.js et Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    // Vérification que jQuery est chargé
    if (typeof jQuery != 'undefined') {
        console.log('jQuery est chargé');
        
        $(document).ready(function() {
            console.log('Document prêt');
            
            // Initialisation des menus déroulants Bootstrap
            $('.dropdown-toggle').dropdown();
            
            // Test du dropdown
            $('.dropdown-toggle').on('click', function() {
                console.log('Dropdown cliqué');
            });
            
            // Fermeture automatique des alertes après 5 secondes
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
                        } else {
        console.error('jQuery n\'est pas chargé');
    }

    // Initialisation des tooltips Bootstrap
    $(document).ready(function() {
        $('[title]').tooltip();
        });

    // Ajouter un délai pour la recherche en temps réel
    let searchTimeout;
    document.getElementById('recherche').addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 300); // Attendre 300ms après la dernière frappe
    });

    // Fonction de confirmation sécurisée pour la suppression de stock
    function confirmerSuppressionStock(nomArticle, stockActuel, idStock) {
        const message = `⚠️ ATTENTION - SUPPRESSION DÉFINITIVE ⚠️

📦 Article : ${nomArticle}
📊 Stock actuel : ${stockActuel} unité(s)
🆔 ID Stock : ${idStock}

🚨 ACTIONS QUI VONT ÊTRE EFFECTUÉES :
• Suppression définitive du stock
• Suppression de tous les numéros de série associés
• Suppression de l'historique des mouvements
• Cette action est IRRÉVERSIBLE

❓ Êtes-vous ABSOLUMENT sûr de vouloir continuer ?

⚠️ Cette opération ne peut pas être annulée !`;

        return confirm(message);
    }
    </script>
</body>

</html>