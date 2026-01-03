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

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Récupération des paramètres de filtrage
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$motif_id = isset($_GET['motif']) ? $_GET['motif'] : '';
$numero_correction = isset($_GET['numero']) ? $_GET['numero'] : '';
// Ajout des nouveaux filtres avancés
$utilisateur = isset($_GET['utilisateur']) ? trim($_GET['utilisateur']) : '';
$article = isset($_GET['article']) ? trim($_GET['article']) : '';
$montant = isset($_GET['montant']) ? floatval($_GET['montant']) : 0;

// Vérification et création des index (une seule fois)
$indexes_created = $_SESSION['indexes_created'] ?? false;
if (!$indexes_created) {
    try {
        $cnx->exec("CREATE INDEX IF NOT EXISTS idx_correction_date ON correction (DateMouvementStock)");
        $cnx->exec("CREATE INDEX IF NOT EXISTS idx_correction_numero ON correction (NumeroCorrection)");
        $cnx->exec("CREATE INDEX IF NOT EXISTS idx_correction_motif ON correction (IDMOTIF_MOUVEMENT_STOCK)");
        $_SESSION['indexes_created'] = true;
} catch (PDOException $e) {
    // Les index existent peut-être déjà, on continue
    }
}

// Requête principale corrigée
$sql = "SELECT c.*, 
        a.libelle as article_libelle,
        m.LibelleMotifMouvementStock as motif_libelle,
        u1.NomPrenom as operateur_modifiant,
        u2.NomPrenom as operateur_creant,
        s.StockActuel as stock_actuel_aujourdhui,
        c.StockFinal as stock_final_correction,
        (c.StockFinal - c.QuantiteMoved) as stock_avant_correction
        FROM correction c
        LEFT JOIN stock s ON c.IDSTOCK = s.IDSTOCK
        LEFT JOIN article a ON s.IDARTICLE = a.IDARTICLE
        LEFT JOIN motif_correction m ON c.IDMOTIF_MOUVEMENT_STOCK = m.IDMOTIF_MOUVEMENT_STOCK
        LEFT JOIN utilisateur u1 ON c.ID_utilisateurs = u1.IDUTILISATEUR
        LEFT JOIN utilisateur u2 ON c.UtilCrea = u2.IDUTILISATEUR
        WHERE 1=1";

$params = [];

if ($date_debut) {
    $sql .= " AND c.DateIns >= ?";
    $params[] = $date_debut;
}
if ($date_fin) {
    $sql .= " AND c.DateIns <= ?";
    $params[] = $date_fin;
}
if ($motif_id) {
    $sql .= " AND c.IDMOTIF_MOUVEMENT_STOCK = ?";
    $params[] = $motif_id;
}
if ($numero_correction) {
    $sql .= " AND c.NumeroCorrection LIKE ?";
    $params[] = "%$numero_correction%";
}
if ($utilisateur) {
    $sql .= " AND u1.NomPrenom LIKE ?";
    $params[] = "%$utilisateur%";
}
if ($article) {
    $sql .= " AND (a.libelle LIKE ? OR a.CodePersoArticle LIKE ?)";
    $params[] = "%$article%";
    $params[] = "%$article%";
}
if ($montant > 0) {
    $sql .= " AND (c.ValeurCorrection >= ?)";
    $params[] = $montant;
}

// Requête de comptage séparée
$sql_count = "SELECT COUNT(*) as total FROM correction c
              LEFT JOIN stock s ON c.IDSTOCK = s.IDSTOCK
              LEFT JOIN article a ON s.IDARTICLE = a.IDARTICLE
              LEFT JOIN motif_correction m ON c.IDMOTIF_MOUVEMENT_STOCK = m.IDMOTIF_MOUVEMENT_STOCK
              LEFT JOIN utilisateur u1 ON c.ID_utilisateurs = u1.IDUTILISATEUR
              LEFT JOIN utilisateur u2 ON c.UtilCrea = u2.IDUTILISATEUR
              WHERE 1=1";

// Ajouter les mêmes conditions de filtrage pour le comptage
$count_params = [];
if ($date_debut) {
    $sql_count .= " AND c.DateIns >= ?";
    $count_params[] = $date_debut;
}
if ($date_fin) {
    $sql_count .= " AND c.DateIns <= ?";
    $count_params[] = $date_fin;
}
if ($motif_id) {
    $sql_count .= " AND c.IDMOTIF_MOUVEMENT_STOCK = ?";
    $count_params[] = $motif_id;
}
if ($numero_correction) {
    $sql_count .= " AND c.NumeroCorrection LIKE ?";
    $count_params[] = "%$numero_correction%";
}
if ($utilisateur) {
    $sql_count .= " AND u1.NomPrenom LIKE ?";
    $count_params[] = "%$utilisateur%";
}
if ($article) {
    $sql_count .= " AND (a.libelle LIKE ? OR a.CodePersoArticle LIKE ?)";
    $count_params[] = "%$article%";
    $count_params[] = "%$article%";
}
if ($montant > 0) {
    $sql_count .= " AND (c.ValeurCorrection >= ?)";
    $count_params[] = $montant;
}

$sql .= " ORDER BY c.DateIns DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

// Ajout d'un timeout pour les requêtes longues
$cnx->setAttribute(PDO::ATTR_TIMEOUT, 30); // 30 secondes maximum

// Ajout d'un cache pour les motifs
$cache_key = 'motifs_correction';
if (!isset($_SESSION[$cache_key])) {
$motifs = selection_element('motif_correction');
    $_SESSION[$cache_key] = $motifs;
} else {
    $motifs = $_SESSION[$cache_key];
}

// Exécution des requêtes avec gestion d'erreur
try {
    $stmt_count = $cnx->prepare($sql_count);
    $stmt_count->execute($count_params);
    $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    $stmt = $cnx->prepare($sql);
    $stmt->execute($params);
    $correction_stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Afficher l'erreur pour diagnostic
    die("Erreur SQL : " . $e->getMessage() . "<br>Requête : " . $sql);
}

// Ajout d'un message de performance
if ($total_records > 1000) {
    echo '<div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Affichage optimisé pour ' . number_format($total_records, 0, ',', ' ') . ' enregistrements.
            Utilisez les filtres pour affiner votre recherche.
          </div>';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Corrections de Stock</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f0f2f5;
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
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 1rem;
            border-radius: 10px;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }
        .excel-table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            animation: fadeIn 0.7s;
            min-width: 1200px;
            width: 100%;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .excel-table thead th {
            background: #343a40;
            color: #fff;
            border: none;
            position: sticky;
            top: 0;
            z-index: 2;
            font-size: 1em;
            letter-spacing: 0.03em;
        }
        .excel-table tbody tr {
            transition: background 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .excel-table tbody tr:hover {
            background: #e3f0ff !important;
        }
        .excel-table tbody tr.selected {
            background: #b3d7ff !important;
            box-shadow: 0 2px 8px rgba(0,123,255,0.08);
        }
        .excel-table tbody tr {
            border-bottom: 1px solid #e9ecef;
        }
        .excel-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .excel-table td, .excel-table th {
            vertical-align: middle;
            padding: 0.8rem 0.7rem;
            font-size: 0.95em;
            border: none;
            white-space: normal;
            word-wrap: break-word;
            min-width: 120px;
        }
        .excel-table td {
            background: transparent;
        }
        .excel-table td .badge-inventaire {
            background: #007bff;
            color: #fff;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.3em 0.7em;
            font-size: 0.95em;
        }
        .excel-table td .badge-autre {
            background: #6c757d;
            color: #fff;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.3em 0.7em;
            font-size: 0.95em;
        }
        @media (max-width: 1200px) {
            .excel-table td, .excel-table th {
                font-size: 0.9em;
                padding: 0.6rem 0.5rem;
                min-width: 100px;
            }
        }
        @media (max-width: 900px) {
            .excel-table td, .excel-table th {
                font-size: 0.85em;
                padding: 0.5rem 0.4rem;
                min-width: 80px;
            }
        }
        @media (max-width: 600px) {
            .form-container, .container {
                padding: 0.5rem !important;
            }
            .excel-table td, .excel-table th {
                font-size: 0.8em;
                padding: 0.4rem 0.3rem;
                min-width: 60px;
            }
            /* Masquer certaines colonnes sur mobile */
            .excel-table th:nth-child(8),
            .excel-table td:nth-child(8),
            .excel-table th:nth-child(9),
            .excel-table td:nth-child(9),
            .excel-table th:nth-child(10),
            .excel-table td:nth-child(10),
            .excel-table th:nth-child(12),
            .excel-table td:nth-child(12) {
                display: none;
            }
        }
        .search-prompt {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.7em;
        }
        .search-prompt input {
            border-radius: 8px;
            border: 1px solid #b3b3b3;
            padding: 0.4em 1em;
            font-size: 1em;
            width: 260px;
            transition: border 0.2s;
        }
        .search-prompt input:focus {
            border: 1.5px solid #007bff;
            outline: none;
        }
        .toast-copied {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #007bff;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1.1em;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .toast-copied.show {
            opacity: 1;
            pointer-events: auto;
        }
        .export-btn {
            background: #007bff;
            color: #fff;
            border-radius: 8px;
            padding: 0.5em 1.2em;
            font-weight: 600;
            border: none;
            margin-bottom: 1em;
            transition: background 0.2s;
        }
        .export-btn:hover {
            background: #0056b3;
        }
        .loading-indicator {
            text-align: center;
            color: #007bff;
            font-size: 1.2em;
            margin: 1em 0;
        }
        
        /* Styles pour les quantités */
        .quantite-positive {
            color: #28a745;
            font-weight: bold;
        }
        
        .quantite-negative {
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Styles spécifiques pour les cellules */
        .date-cell {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #495057;
        }
        
        .numero-cell {
            font-weight: 600;
            color: #007bff;
            font-family: 'Courier New', monospace;
        }
        
        .article-cell {
            font-weight: 500;
            color: #343a40;
            line-height: 1.3;
        }
        
        .operateur-cell {
            font-weight: 500;
            color: #6c757d;
        }
        
        .quantite-cell {
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .stock-cell {
            font-weight: 600;
            color: #495057;
            font-family: 'Courier New', monospace;
        }
        
        /* Amélioration de la lisibilité */
        .excel-table tbody tr:hover .article-cell {
            color: #007bff;
        }
        
        .excel-table tbody tr:hover .numero-cell {
            color: #0056b3;
        }
        
        /* Indicateur de défilement */
        .scroll-indicator {
            background: #e3f2fd;
            color: #1976d2;
            padding: 8px 16px;
            text-align: center;
            font-size: 0.9em;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
            border: 1px solid #bbdefb;
            border-bottom: none;
        }
        
        .scroll-indicator i {
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1><i class="fas fa-clipboard-list"></i> Liste des Corrections de Stock</h1>
    </header>
    
    <!-- Notice sur le principe de contre-correction -->
    <div class="alert alert-info" role="alert">
        <h5><i class="fas fa-info-circle"></i> Principe de Gestion des Corrections</h5>
        <p class="mb-2">
            <strong>Les corrections de stock ne peuvent jamais être supprimées</strong> pour préserver la traçabilité et l'audit.
        </p>
        
    </div>

    <main class="container">
       

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

            <div class="form-container">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="search-prompt">
                    <i class="fas fa-search"></i>
                    <input type="text" id="excelSearch" placeholder="Recherche rapide dans le tableau... (Ctrl+F pour recherche navigateur)">
                </div>
                <button class="export-btn" onclick="window.location.href='export_corrections.php?'+new URLSearchParams(new FormData(document.querySelector('.filter-form'))).toString()">
                    <i class="fas fa-file-csv"></i> Exporter tout en CSV
                </button>
            </div>
            <form method="GET" class="filter-form">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="date_debut">Date de début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="date_fin">Date de fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="motif">Motif</label>
                            <select class="form-control" id="motif" name="motif">
                                <option value="">Tous les motifs</option>
                                <?php foreach ($motifs as $motif): ?>
                                    <option value="<?= $motif['IDMOTIF_MOUVEMENT_STOCK'] ?>" <?= $motif_id == $motif['IDMOTIF_MOUVEMENT_STOCK'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($motif['LibelleMotifMouvementStock']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="utilisateur">Utilisateur</label>
                            <input type="text" class="form-control" id="utilisateur" name="utilisateur" value="<?= htmlspecialchars(isset($_GET['utilisateur']) ? $_GET['utilisateur'] : '') ?>" placeholder="Nom ou ID">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="article">Article</label>
                            <input type="text" class="form-control" id="article" name="article" value="<?= htmlspecialchars(isset($_GET['article']) ? $_GET['article'] : '') ?>" placeholder="Nom ou code">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="montant">Montant correction &ge;</label>
                            <input type="number" class="form-control" id="montant" name="montant" value="<?= htmlspecialchars(isset($_GET['montant']) ? $_GET['montant'] : '') ?>" placeholder="Montant (F.CFA)">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="limit">Lignes par page</label>
                            <select class="form-control" id="limit" name="limit" onchange="this.form.submit()">
                                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        </div>
                    </div>
                    <div class="col-md-9 text-right">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                        <a href="liste_correction_stock.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Défilez horizontalement pour voir toutes les colonnes
                </div>
                <table class="table excel-table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>N° Correction</th>
                            <th>Article</th>
                            <th>Motif</th>
                            <th>Opérateur</th>
                            <th>Stock Avant</th>
                            <th>Quantité</th>
                            <th>Stock Final</th>
                            <th>PMP utilisé</th>
                            <th>Valeur correction</th>
                            <?php if (user_can_see_purchase_prices()): ?>
                                <th>Prix Achat</th>
                            <?php endif; ?>
                           
                        </tr>
                    </thead>
                    <tbody id="excelTableBody">
                        <?php if (empty($correction_stocks)): ?>
                            <tr>
                                <td colspan="11" class="text-center">Aucune correction trouvée</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($correction_stocks as $correction): ?>
                                <tr tabindex="0">
                                    <td title="<?= htmlspecialchars($correction['DateMouvementStock']) ?>">
                                        <span class="date-cell"><?= htmlspecialchars($correction['DateMouvementStock']) ?></span>
                                    </td>
                                    <td title="<?= htmlspecialchars($correction['NumeroCorrection']) ?>">
                                        <span class="numero-cell"><?= htmlspecialchars($correction['NumeroCorrection']) ?></span>
                                    </td>
                                    <td title="<?= htmlspecialchars($correction['article_libelle'] ?? 'Article a été mal enregistré') ?>">
                                        <span class="article-cell"><?= htmlspecialchars($correction['article_libelle'] ?? 'Article a été mal enregistré') ?></span>
                                    </td>
                                    <td title="<?= htmlspecialchars($correction['motif_libelle'] ?? 'motif correction a été mal enregistré') ?>">
                                        <?php if (isset($correction['motif_libelle']) && stripos($correction['motif_libelle'], 'INVENTAIRE') !== false): ?>
                                            <span class="badge-inventaire">INVENTAIRE</span>
                                        <?php else: ?>
                                            <span class="badge-autre"><?= htmlspecialchars($correction['motif_libelle'] ?? 'motif correction a été mal enregistré') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td title="<?= htmlspecialchars($correction['operateur_modifiant'] ?? 'INVENTAIRE') ?>">
                                        <span class="operateur-cell"><?= htmlspecialchars($correction['operateur_modifiant'] ?? 'INVENTAIRE') ?></span>
                                    </td>
                                    <td title="<?= htmlspecialchars($correction['stock_avant_correction'] ?? '0') ?>">
                                        <span class="stock-cell"><?= htmlspecialchars($correction['stock_avant_correction'] ?? '0') ?></span>
                                    </td>
                                    <td class="<?= $correction['QuantiteMoved'] >= 0 ? 'quantite-positive' : 'quantite-negative' ?>" title="<?= htmlspecialchars($correction['QuantiteMoved']) ?>">
                                        <span class="quantite-cell"><?= $correction['QuantiteMoved'] >= 0 ? '+' : '' ?><?= htmlspecialchars($correction['QuantiteMoved']) ?></span>
                                    </td>
                                    <td title="<?= htmlspecialchars($correction['stock_final_correction'] ?? '0') ?>">
                                        <span class="stock-cell"><?= htmlspecialchars($correction['stock_final_correction'] ?? '0') ?></span>
                                    </td>
                                    <td title="<?= isset($correction['PMP_utilise']) && $correction['PMP_utilise'] !== null ? number_format($correction['PMP_utilise'], 2) : '0' ?>">
                                        <?= isset($correction['PMP_utilise']) && $correction['PMP_utilise'] !== null ? number_format($correction['PMP_utilise'], 2) : '0' ?> F.CFA
                                    </td>
                                    <td title="<?= isset($correction['ValeurCorrection']) && $correction['ValeurCorrection'] !== null ? number_format($correction['ValeurCorrection'], 2) : '0' ?>">
                                        <?= isset($correction['ValeurCorrection']) && $correction['ValeurCorrection'] !== null ? number_format($correction['ValeurCorrection'], 2) : '0' ?> F.CFA
                                    </td>
                                    <?php if (user_can_see_purchase_prices()): ?>
                                        <td title="<?= number_format($correction['PrixAchat'] ?? 0, 2) ?>">
                                            <?= number_format($correction['PrixAchat'] ?? 0, 2) ?> F.CFA
                                        </td>
                                    <?php endif; ?>
                                   
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="loadingMore" class="loading-indicator" style="display:none;">Chargement...</div>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav aria-label="Pagination">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&motif=<?= $motif_id ?>&numero=<?= $numero_correction ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&limit=' . $limit . '&date_debut=' . $date_debut . '&date_fin=' . $date_fin . '&motif=' . $motif_id . '&numero=' . $numero_correction . '">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&motif=<?= $motif_id ?>&numero=<?= $numero_correction ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor;

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&limit=' . $limit . '&date_debut=' . $date_debut . '&date_fin=' . $date_fin . '&motif=' . $motif_id . '&numero=' . $numero_correction . '">' . $total_pages . '</a></li>';
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&motif=<?= $motif_id ?>&numero=<?= $numero_correction ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function creerContreCorrection(id, numeroCorrection) {
            if (confirm(`Voulez-vous créer une contre-correction pour annuler la correction ${numeroCorrection} ?\n\nCette action créera une nouvelle correction qui annulera l'effet de la correction précédente, tout en préservant l'historique.`)) {
                // Rediriger vers la page de correction avec les paramètres pré-remplis
                window.location.href = `correction_stock.php?contre_correction=${id}&numero_reference=${encodeURIComponent(numeroCorrection)}`;
            }
        }

        // Fermeture automatique des alertes après 5 secondes
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);

        // Maintien des filtres lors du changement de page
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);
                window.location.href = url.toString();
            });
        });

        // Recherche rapide dans le tableau (filtrage instantané)
        document.getElementById('excelSearch').addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#excelTableBody tr');
            rows.forEach(row => {
                let show = false;
                row.querySelectorAll('td').forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(value)) show = true;
                });
                row.style.display = show ? '' : 'none';
            });
        });

        // Sélection de ligne façon Excel
        const tableRows = document.querySelectorAll('.excel-table tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('click', function(e) {
                tableRows.forEach(r => r.classList.remove('selected'));
                this.classList.add('selected');
            });
            row.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const next = this.nextElementSibling;
                    if (next) next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = this.previousElementSibling;
                    if (prev) prev.focus();
                }
            });
            // Copier la ligne au clic droit
            row.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                const text = Array.from(this.querySelectorAll('td')).map(td => td.textContent).join('\t');
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Ligne copiée dans le presse-papier !');
                });
            });
        });

        // Toast de confirmation copie
        function showToast(msg) {
            let toast = document.querySelector('.toast-copied');
            if (!toast) {
                toast = document.createElement('div');
                toast.className = 'toast-copied';
                document.body.appendChild(toast);
            }
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2000);
        }

        // Info-bulle sur chaque cellule
        const allCells = document.querySelectorAll('.excel-table td');
        allCells.forEach(cell => {
            cell.addEventListener('mouseenter', function() {
                if (this.offsetWidth < this.scrollWidth) {
                    this.setAttribute('title', this.textContent);
                }
            });
        });

        // Scroll infini (chargement AJAX des pages suivantes)
        let currentPage = <?= (int)$page ?>;
        let totalPages = <?= (int)$total_pages ?>;
        let isLoading = false;
        const tableBody = document.getElementById('excelTableBody');
        const loadingMore = document.getElementById('loadingMore');
        window.addEventListener('scroll', function() {
            if (isLoading) return;
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 200) {
                if (currentPage < totalPages) {
                    isLoading = true;
                    loadingMore.style.display = '';
                    fetchNextPage();
                }
            }
        });
        function fetchNextPage() {
            const params = new URLSearchParams(window.location.search);
            params.set('page', currentPage + 1);
            fetch('liste_correction_stock.php?' + params.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(r => r.text())
                .then(html => {
                    // Extraire uniquement les lignes du tbody
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const newRows = temp.querySelectorAll('#excelTableBody tr');
                    newRows.forEach(row => tableBody.appendChild(row));
                    currentPage++;
                    isLoading = false;
                    loadingMore.style.display = 'none';
                });
        }
    </script>
</body>

</html>