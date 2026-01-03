<?php
session_start();
include('db/connecting.php');

if (!isset($_SESSION['nom_utilisateur'])) {
    header('Location: ../connexion.php');
    exit();
}

$idInventaire = isset($_GET['IDINVENTAIRE']) ? intval($_GET['IDINVENTAIRE']) : 0;

if ($idInventaire <= 0) {
    die("ID d'inventaire invalide");
}

// Récupération des informations de l'inventaire
$inventaire = $cnx->query("
    SELECT i.*, 
           COUNT(il.id) as nombre_articles,
           SUM(CASE WHEN il.ecart != 0 THEN 1 ELSE 0 END) as articles_avec_ecart,
           SUM(il.ecart) as total_ecart,
           SUM(il.ecart * il.prix_achat) as valeur_ecart
    FROM inventaire i
    LEFT JOIN inventaire_ligne il ON i.IDINVENTAIRE = il.id_inventaire
    WHERE i.IDINVENTAIRE = $idInventaire
    GROUP BY i.IDINVENTAIRE
")->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    die("Inventaire non trouvé");
}

// Récupération des lignes d'inventaire
$lignes = $cnx->query("
    SELECT il.*, 
           a.PrixAchatHT, 
           a.PrixVenteTTC,
           s.StockActuel as stock_actuel
    FROM inventaire_ligne il 
    LEFT JOIN article a ON il.id_article = a.IDARTICLE 
    LEFT JOIN stock s ON il.id_article = s.IDARTICLE
    WHERE il.id_inventaire = $idInventaire
    ORDER BY il.categorie, il.code_article
")->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux
$totalTheorique = 0;
$totalPhysique = 0;
$totalEcart = 0;
$totalValeurTheorique = 0;
$totalValeurPhysique = 0;
$totalValeurEcart = 0;

foreach ($lignes as $ligne) {
    $totalTheorique += $ligne['qte_theorique'];
    $totalPhysique += $ligne['qte_physique'] ?? 0;
    $totalEcart += $ligne['ecart'] ?? 0;
    $totalValeurTheorique += $ligne['qte_theorique'] * $ligne['PrixAchatHT'];
    $totalValeurPhysique += ($ligne['qte_physique'] ?? 0) * $ligne['PrixAchatHT'];
    $totalValeurEcart += ($ligne['ecart'] ?? 0) * $ligne['PrixAchatHT'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport d'Inventaire - <?php echo htmlspecialchars($inventaire['Commentaires']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
            body {
                font-size: 10pt;
            }
            .no-print {
                display: none !important;
            }
            .table {
                font-size: 9pt;
            }
            .table th, .table td {
                padding: 0.3rem !important;
            }
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 100%;
            padding: 2rem;
        }
        .report-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .report-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .report-subtitle {
            color: #6c757d;
            font-size: 1rem;
        }
        .table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: white;
            border-collapse: collapse;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            border: 1px solid #dee2e6;
        }
        .table td {
            border: 1px solid #dee2e6;
            padding: 0.5rem;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .ecart-positif {
            color: #28a745;
            font-weight: bold;
        }
        .ecart-negatif {
            color: #dc3545;
            font-weight: bold;
        }
        .summary-box {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .summary-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .summary-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .category-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
<div class="container">
    <div class="no-print mb-3">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <a href="inventaire_liste.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    <div class="report-header">
        <div class="report-title">RAPPORT D'INVENTAIRE</div>
        <div class="report-subtitle"><?php echo htmlspecialchars($inventaire['Commentaires']); ?></div>
        <div class="report-subtitle">
            Date : <?php echo date('d/m/Y', strtotime($inventaire['DateInventaire'])); ?> |
            Créé par : <?php echo htmlspecialchars($inventaire['CreePar']); ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="summary-box">
                <div class="summary-title">Nombre d'articles</div>
                <div class="summary-value"><?php echo $inventaire['nombre_articles']; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-box">
                <div class="summary-title">Articles avec écart</div>
                <div class="summary-value"><?php echo $inventaire['articles_avec_ecart']; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-box">
                <div class="summary-title">Total écart quantité</div>
                <div class="summary-value <?php echo $inventaire['total_ecart'] > 0 ? 'ecart-positif' : 'ecart-negatif'; ?>">
                    <?php echo $inventaire['total_ecart'] > 0 ? '+' : ''; ?><?php echo $inventaire['total_ecart']; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-box">
                <div class="summary-title">Valeur écart</div>
                <div class="summary-value <?php echo $inventaire['valeur_ecart'] > 0 ? 'ecart-positif' : 'ecart-negatif'; ?>">
                    <?php echo number_format($inventaire['valeur_ecart'], 0, ',', ' '); ?> F.CFA
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Catégorie</th>
                    <th>Code</th>
                    <th>Désignation</th>
                    <th class="text-right">Prix Achat</th>
                    <th class="text-right">Stock Théorique</th>
                    <th class="text-right">Stock Physique</th>
                    <th class="text-right">Écart</th>
                    <th class="text-right">Valeur Écart</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $currentCategory = '';
                foreach ($lignes as $ligne): 
                    if ($currentCategory !== $ligne['categorie']) {
                        $currentCategory = $ligne['categorie'];
                        echo "<tr class='category-header'><td colspan='8'>" . htmlspecialchars($currentCategory) . "</td></tr>";
                    }
                ?>
                <tr>
                    <td></td>
                    <td><?php echo htmlspecialchars($ligne['code_article']); ?></td>
                    <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
                    <td class="text-right"><?php echo number_format($ligne['PrixAchatHT'], 0, ',', ' '); ?> F.CFA</td>
                    <td class="text-right"><?php echo $ligne['qte_theorique']; ?></td>
                    <td class="text-right"><?php echo $ligne['qte_physique'] ?? 0; ?></td>
                    <td class="text-right <?php echo ($ligne['ecart'] ?? 0) > 0 ? 'ecart-positif' : 'ecart-negatif'; ?>">
                        <?php 
                        $ecart = $ligne['ecart'] ?? 0;
                        echo $ecart > 0 ? '+' . $ecart : $ecart;
                        ?>
                    </td>
                    <td class="text-right <?php echo ($ligne['ecart'] ?? 0) > 0 ? 'ecart-positif' : 'ecart-negatif'; ?>">
                        <?php 
                        $valeurEcart = ($ligne['ecart'] ?? 0) * $ligne['PrixAchatHT'];
                        echo number_format($valeurEcart, 0, ',', ' ') . ' F.CFA';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary">
                    <td colspan="4" class="text-right"><strong>TOTAUX :</strong></td>
                    <td class="text-right"><strong><?php echo $totalTheorique; ?></strong></td>
                    <td class="text-right"><strong><?php echo $totalPhysique; ?></strong></td>
                    <td class="text-right <?php echo $totalEcart > 0 ? 'ecart-positif' : 'ecart-negatif'; ?>">
                        <strong><?php echo $totalEcart > 0 ? '+' . $totalEcart : $totalEcart; ?></strong>
                    </td>
                    <td class="text-right <?php echo $totalValeurEcart > 0 ? 'ecart-positif' : 'ecart-negatif'; ?>">
                        <strong><?php echo number_format($totalValeurEcart, 0, ',', ' '); ?> F.CFA</strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        <p>Rapport généré le <?php echo date('d/m/Y H:i'); ?> par <?php echo htmlspecialchars($_SESSION['nom_utilisateur']); ?></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

function checkDroitInventaire($action) {
    $droits = [
        'creation' => ['admin', 'stock_manager'],
        'validation' => ['admin'],
        'consultation' => ['admin', 'stock_manager', 'user']
    ];
    
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $droits[$action])) {
        header('Location: ../inde.php?error=Accès non autorisé');
        exit;
    }
}

function logInventaire($id_inventaire, $id_article, $action, $qte_avant, $qte_apres, $commentaire = '') {
    global $cnx;
    $stmt = $cnx->prepare("
        INSERT INTO inventaire_log 
        (id_inventaire, id_article, utilisateur, date_action, action, qte_avant, qte_apres, commentaire)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
    ");
    $stmt->execute([
        $id_inventaire,
        $id_article,
        $_SESSION['nom_utilisateur'],
        $action,
        $qte_avant,
        $qte_apres,
        $commentaire
    ]);
}