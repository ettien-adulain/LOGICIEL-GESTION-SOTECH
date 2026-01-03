<?php
session_start();
include('db/connecting.php');
include('fonction_traitement/fonction.php');

if (!isset($_GET['id'])) {
    die("ID d'entrée en stock non spécifié.");
}

$id_entree = $_GET['id'];

// Récupération des informations de l'entrée en stock
$stmt = $cnx->prepare("
    SELECT e.*, f.NomFournisseur, f.TelephoneFournisseur as tel_fournisseur, f.eMailFournisseur as email_fournisseur,
           u.NomPrenom as operateur
    FROM entree_en_stock e
    LEFT JOIN fournisseur f ON e.IDFOURNISSEUR = f.IDFOURNISSEUR
    LEFT JOIN utilisateur u ON e.ID_utilisateurs = u.IDUTILISATEUR
    WHERE e.IDENTREE_STOCK = ?
");
$stmt->execute([$id_entree]);
$entree = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entree) {
    die("Entrée en stock non trouvée.");
}

// Récupération des lignes d'entrée en stock
$stmt = $cnx->prepare("
    SELECT esl.*, a.libelle, a.descriptif, a.descriptif
    FROM entree_stock_ligne esl
    JOIN article a ON esl.IDARTICLE = a.IDARTICLE
    WHERE esl.IDENTREE_EN_STOCK = ?
");
$stmt->execute([$id_entree]);
$lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux
$total_general = 0;
foreach ($lignes as $ligne) {
    $total_general += $ligne['Quantite'] * $ligne['PrixAchat'];
}

// Récupération des informations de l'entreprise
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
    <title>Bon d'Entrée en Stock</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.25;
            font-size: 11px;
            background: #fff;
        }
        .order-form {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 10mm 10mm 30mm 10mm;
            position: relative;
        }
        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 18px;
            color: #333;
            text-transform: uppercase;
            border-bottom: 1px solid #333;
            padding-bottom: 6px;
            letter-spacing: 1px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .company-info {
            width: 45%;
        }
        .order-info {
            width: 45%;
            text-align: right;
        }
        .logo {
            width: 90px;
            height: 90px;
            margin-bottom: 10px;
            object-fit: contain;
        }
        .section {
            display: flex;
            justify-content: space-between;
            margin: 18px 0 12px 0;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        .seller, .buyer {
            width: 48%;
        }
        .seller h3, .buyer h3 {
            margin-bottom: 8px;
            color: #333;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 0 0;
        }
        th, td {
            border: 1px solid #bbb;
            padding: 3px 6px;
            font-size: 10.5px;
            line-height: 1.15;
        }
        th {
            background-color: #333;
            color: white;
            font-size: 11px;
            padding: 4px 6px;
            font-weight: 600;
        }
        .product-table th:nth-child(1) { width: 32%; }
        .product-table th:nth-child(2) { width: 16%; }
        .product-table th:nth-child(3) { width: 12%; }
        .product-table th:nth-child(4) { width: 16%; }
        .product-table th:nth-child(5) { width: 18%; }
        .totals {
            margin-top: 10px;
            padding: 10px 12px;
            background-color: #f7f7f7;
            border-radius: 4px;
            font-size: 11px;
        }
        .totals p {
            margin: 4px 0;
            font-size: 11px;
        }
        .totals p span {
            float: right;
            font-weight: bold;
        }
        footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0;
            border-top: 1.5px solid #333;
            background: #fafbfc;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 0;
            padding: 10px 20px 6px 20px;
        }
        .footer-section {
            width: 48%;
        }
        .footer-section h4 {
            margin-bottom: 5px;
            color: #222;
            font-size: 12px;
            font-weight: 600;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 2px;
            letter-spacing: 0.5px;
        }
        .footer-section p {
            margin: 2px 0;
            font-size: 10px;
            color: #444;
        }
        .footer-section strong {
            color: #007bff;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .order-form {
                width: 210mm;
                min-height: 297mm;
                padding: 10mm 10mm 30mm 10mm;
                margin: 0;
            }
            footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
            }
            .product-table {
                page-break-inside: avoid;
            }
        }
    </style>
    <!-- Système de thème sombre/clair -->
</head>
<body class="order-form" id="orderForm">
    <div class="header-section">
        <div class="company-info">
            <img class="logo" src="<?= isset($entreprise['logo1']) && !empty($entreprise['logo1']) ? $entreprise['logo1'] : 'Image_article/sotech.png' ?>" alt="Logo de l'entreprise">
            <p><strong><?= isset($entreprise['nom']) && !empty($entreprise['nom']) ? $entreprise['nom'] : '--------------------' ?></strong></p>
            <p><?= isset($entreprise['telephone']) && !empty($entreprise['telephone']) ? $entreprise['telephone'] : '--------------------' ?></p>
        </div>
        <div class="order-info">
            <h1>Bon d'Entrée en Stock</h1>
            <?PHP
            setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra');
            date_default_timezone_set('Africa/Abidjan');
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
            echo "<p><strong>Date : </strong>" . $fmt->format(new DateTime()) . "</p>";
            ?>
            <p><strong>N° Entrée : </strong> <?= htmlspecialchars($entree['IDENTREE_STOCK']) ?></p>
            <p><strong>N° Bon : </strong> <?= htmlspecialchars($entree['Numero_bon']) ?></p>
        </div>
    </div>

    <div class="section">
        <div class="seller">
            <h3>Entreprise</h3>
            <p><strong>Nom :</strong> <?= isset($entreprise['nom']) ? htmlspecialchars($entreprise['nom']) : '--------------------' ?></p>
            <p><strong>Adresse :</strong> <?= isset($entreprise['adresse']) ? htmlspecialchars($entreprise['adresse']) : '--------------------' ?></p>
            <p><strong>Téléphone :</strong> <?= isset($entreprise['telephone']) ? htmlspecialchars($entreprise['telephone']) : '--------------------' ?></p>
        </div>
        <div class="buyer">
            <h3>Fournisseur</h3>
            <p><strong>Nom :</strong> <?= htmlspecialchars($entree['NomFournisseur']) ?></p>
            <p><strong>Téléphone :</strong> <?= htmlspecialchars($entree['tel_fournisseur'] ?? 'Non spécifié') ?></p>
            <p><strong>E-mail :</strong> <?= htmlspecialchars($entree['email_fournisseur'] ?? 'Non spécifié') ?></p>
        </div>
    </div>

    <div class="table-container">
        <table class="product-table">
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Référence</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne): 
                    $total_ligne = $ligne['Quantite'] * $ligne['PrixAchat'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($ligne['libelle']) ?></td>
                        <td><?= htmlspecialchars($ligne['descriptif']) ?></td>
                        <td><?= $ligne['Quantite'] ?></td>
                        <td><?= number_format($ligne['PrixAchat'], 0, ',', ' ') ?> F CFA</td>
                        <td><?= number_format($total_ligne, 0, ',', ' ') ?> F CFA</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="totals">
        <p><strong>Total général : <span><?= number_format($total_general, 0, ',', ' ') ?> F CFA</span></strong></p>
        <p><strong>Opérateur :</strong> <?= htmlspecialchars($entree['operateur']) ?></p>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <p><strong><?= isset($entreprise['NCC']) && !empty($entreprise['NCC']) ? $entreprise['NCC'] : '--------------------' ?></strong></p>
                <p> <?= isset($entreprise['RCCM']) && !empty($entreprise['RCCM']) ? $entreprise['RCCM'] : '--------------------' ?></p>
                <p><?= isset($entreprise['NUMERO']) && !empty($entreprise['NUMERO']) ? $entreprise['NUMERO'] : '--------------------' ?></p>
            </div>

            <div class="footer-section">
                <p><strong><?= isset($entreprise['NomBanque']) && !empty($entreprise['NomBanque']) ? $entreprise['NomBanque'] : '--------------------' ?></strong></p>
                <p><?= isset($entreprise['NumeroCompte']) && !empty($entreprise['NumeroCompte']) ? $entreprise['NumeroCompte'] : '--------------------' ?></p>
                <p><?= isset($entreprise['IBAN']) && !empty($entreprise['IBAN']) ? $entreprise['IBAN'] : '--------------------' ?></p>
            </div>
        </div>
    </footer>

    <script>
        window.onload = function () {
            window.print();
            setTimeout(function() {
                window.location.href = 'liste_entree_stock.php';
            }, 1000); // 1 seconde pour laisser le temps à l'impression
        };
    </script>
</body>
</html> 