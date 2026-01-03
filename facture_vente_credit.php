<?php
session_start();
include('db/connecting.php');
include('fonction_traitement/fonction.php');

if (!isset($_SESSION['nom_utilisateur'])) {
    header('location: connexion.php');
    exit();
}

if (!isset($_GET['id'])) {
    echo "ID de la vente non fourni.";
    exit();
}

$IDVenteCredit = $_GET['id'];

// Récupération des détails de la vente
$query = "
    SELECT vc.*, c.NomPrenomClient
    FROM ventes_credit vc
    JOIN client c ON vc.IDCLIENT = c.IDCLIENT
    WHERE vc.IDVenteCredit = ?
";
$stmt = $cnx->prepare($query);
$stmt->execute([$IDVenteCredit]);
$vente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vente) {
    echo "Détails de la vente introuvables.";
    exit();
}

// Récupération du total des acomptes
$query = $cnx->prepare('SELECT SUM(AccompteVerse) AS total_acompte 
                        FROM ventes_credit_paiement 
                        WHERE IDVenteCredit = :IDVenteCredit');
$query->execute(['IDVenteCredit' => $IDVenteCredit]);
$total_acompte = $query->fetchColumn() ?: 0;

// Récupération de l'acompte du jour
$query = $cnx->prepare('SELECT SUM(AccompteVerse) AS acompte_aujourdhui 
                        FROM ventes_credit_paiement 
                        WHERE IDVenteCredit = :IDVenteCredit 
                          AND DATE(DateIns) = CURDATE()');
$query->execute(['IDVenteCredit' => $IDVenteCredit]);
$acompte_aujourdhui = $query->fetchColumn() ?: 0;

if (isset($_SESSION['entreprise'])) {
    $entreprise = $_SESSION['entreprise'];
} else {
    $result = $cnx->query("SELECT * FROM entreprise WHERE id = 1");
    $entreprise = $result->fetch();
    $_SESSION['entreprise'] = $entreprise;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .receipt-container {
            width: 100%;
            max-width: 57mm;
            margin: auto;
            border: 1px solid #ddd;
            padding: 10mm;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .receipt-header {
            text-align: center;
        }

        .receipt-header h1 {
            font-size: 14px;
        }

        .company-info {
            margin-top: 10px;
        }

        .company-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 5px;
        }

        .receipt-details {
            margin-bottom: 10px;
        }

        .receipt-details h2 {
            margin: 0;
            font-size: 12px;
        }

        .receipt-details p {
            margin: 5px 0;
            font-size: 10px;
        }

        .receipt-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .receipt-items th,
        .receipt-items td {
            border: 1px solid #ddd;
            padding: 2px;
            text-align: left;
            font-size: 10px;
        }

        .receipt-items th {
            background-color: #f4f4f4;
        }

        .receipt-total {
            margin: 5px 0;
            font-size: 10px;
        }

        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 10px;
        }

        .barcode-container {
            text-align: center;
            margin-top: 10px;
        }

        .barcode-container img {
            max-width: 100%;
        }

        @media print {
            body {
                margin: 0;
            }

            .receipt-container {
                width: 57mm;
                height: 50mm;
                padding: 5mm;
                box-shadow: none;
                border: none;
            }

            .receipt-header h1,
            .receipt-details h2,
            .receipt-details p,
            .receipt-items th,
            .receipt-items td,
            .receipt-total,
            .footer {
                font-size: 10px;
            }

            .barcode-container {
                margin-top: 5mm;
            }

            .no-print {
                display: none;
            }
        }
    </style>
    <script>
        window.onload = function() {
            window.print(); // Déclenche l'impression
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>

    <div class="receipt-container">
        <div class="receipt-header">
            <h1>Reçu de Vente à Crédit</h1>
            <p><?= htmlspecialchars($vente['NumeroVente']) ?></p>
            <div class="company-info">
                <img class="logo" src="<?= isset($entreprise['logo1']) && !empty($entreprise['logo1']) ? $entreprise['logo1'] : 'Image_article/sotech.png' ?>" alt="Logo de l'entreprise">
                <p><strong><?= isset($entreprise['nom']) && !empty($entreprise['nom']) ? $entreprise['nom'] : '--------------------' ?></strong></p>
                <p><?= isset($entreprise['telephone']) && !empty($entreprise['telephone']) ? $entreprise['telephone'] : '--------------------' ?></p>
                <p><?= isset($entreprise['Email']) && !empty($entreprise['Email']) ? $entreprise['Email'] : '--------------------' ?></p>
                <p><?= isset($entreprise['adresse_bureau']) && !empty($entreprise['adresse_bureau']) ? $entreprise['adresse_bureau'] : '--------------------' ?></p>
            </div>
            <p><?= htmlspecialchars($_SESSION['nom_complet']) ?></p>
        </div>
        <hr>
        <div class="receipt-details">
            <h2>Détails de la Transaction</h2>
            <p><strong>Nom du Client :</strong> <?= htmlspecialchars($vente['NomPrenomClient']) ?></p>
            <p><strong>Numéro du Client :</strong> <?= htmlspecialchars($vente['IDCLIENT']) ?></p>
            <p><strong>Date :</strong> <?= htmlspecialchars($vente['DateIns']) ?></p>
        </div>

        <table class="receipt-items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Prix Unitaire</th>
                    <th>Numéro de Série</th>
                    <th>Quantite</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $factures = verifier_element_tous('ventes_credit_ligne', ['NumeroVente'], [$vente['NumeroVente']], '');

                if (is_array($factures)) {
                    foreach ($factures as $facture) {
                        $article = verifier_element('article', ['IDARTICLE'], [$facture['IDARTICLE']], '');
                        $num_serie = verifier_element('num_serie', ['IDARTICLE'], [$article['IDARTICLE']], '');

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($article['libelle']) . '</td>';
                        echo '<td>' . htmlspecialchars($article['PrixVenteTTC']) . ' F CFA</td>';
                        echo '<td>' . htmlspecialchars($num_serie['NUMERO_SERIE']) . '</td>';
                        echo '<td>' . htmlspecialchars($facture['QuantiteVendue']) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">Aucune facture trouvée.</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <div class="receipt-total">
            <p><strong>Montant Remise :</strong> <?= ($vente['MontantRemise']) ?> FCFA</p>
            <p><strong>Montant Total :</strong> <?= ($vente['MontantTotalCredit']) ?> FCFA</p>
            <p><strong>Total des Acomptes : </strong> <?= ($total_acompte) ?> F CFA</p>
            <p><strong>Acompte du jour : </strong> <?= ($acompte_aujourdhui) ?> F CFA</p>
            <p><strong>Reste à Payer :</strong> <?= ($vente['RestantAPayer']) ?> FCFA</p>
        </div>

        <div class="footer">
            <p>Merci pour votre achat !</p>
            <p>Paiement :
                <strong><span class="<?= $vente['RestantAPayer'] <= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $vente['RestantAPayer'] <= 0 ? 'Soldé' : 'Non Soldé' ?>
                    </span></strong>.
            </p>
        </div>

        <div class="barcode-container">
            <svg id="barcode"></svg>
        </div>
    </div>

    <script>
        // Code-barres avec JsBarcode
        JsBarcode("#barcode", "<?= $vente['NumeroVente'] ?>", {
            format: "CODE128",
            width: 1,
            height: 50,
        });
    </script>
</body>

</html>