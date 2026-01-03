<?php
try {
    session_start();
    include('db/connecting.php');
    include('fonction_traitement/fonction.php');

    if (!isset($_SESSION['nom_utilisateur'])) {
        header('location: connexion.php');
        exit();
    }

    if (!isset($_GET['numero'])) {
        throw new Exception("Numéro de vente non fourni !");
    }

    $numero = $_GET['numero'];

    // 1. Vente
    $vente = verifier_element('vente', ['NumeroVente'], [$numero], '');
    if (!$vente) throw new Exception("Vente introuvable !");

    // 2. Client
    $client = verifier_element('client', ['IDCLIENT'], [$vente['IDCLIENT']], '');
    $vente['NomPrenomClient'] = $client ? $client['NomPrenomClient'] : 'Client inconnu';
    $vente['Telephone'] = $client ? $client['Telephone'] : '';
    $vente['Adresse_email'] = $client ? $client['Adresse_email'] : '';

    // 3. Articles - Format corrigé pour récupérer uniquement les numéros de série de cette vente
    $req_articles = $cnx->prepare("
       SELECT DISTINCT a.libelle, a.PrixVenteTTC, f.QuantiteVendue, n.NUMERO_SERIE
FROM facture_article f
JOIN article a ON f.IDARTICLE = a.IDARTICLE
INNER JOIN num_serie n 
    ON n.IDARTICLE = f.IDARTICLE 
    AND n.NumeroVente = f.NumeroVente 
    AND n.ID_VENTE = f.IDFactureVente
    AND n.statut = 'vendue'
WHERE f.NumeroVente = ?
ORDER BY f.IDFactureVente, n.NUMERO_SERIE
    ");
    $req_articles->execute([$numero]);
    $panier = $req_articles->fetchAll();

    // 4. Paiement
    $paiements = [];
    $req_multi = $cnx->prepare("SELECT m.MONTANT, r.ModeReglement 
        FROM vente_paiement m 
        LEFT JOIN mode_reglement r ON m.IDMODE_REGLEMENT = r.IDMODE_REGLEMENT
        LEFT JOIN vente v ON v.IDFactureVente = m.IDFactureVente
        WHERE v.NumeroVente = ?
    ");
    $req_multi->execute([$numero]);
    $paiements = $req_multi->fetchAll();

    if (count($paiements) == 0) {
        $mode = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$vente['ModePaiement']], '');
        $paiements[] = [
            'ModeReglement' => $mode ? $mode['ModeReglement'] : 'Inconnu',
            'MONTANT' => $vente['MontantVerse']
        ];
    }

    // 5. Calculs
    $vrai_Montanttotal = number_format($vente['MontantTotal_sansremise'], 2, '.', '');
    $remiseMontant = number_format($vente['MontantRemise'], 2, '.', '');
    $montant_total = number_format($vente['MontantTotal'], 2, '.', '');
    $montant_verse = number_format($vente['MontantVerse'], 2, '.', '');
    $monnaie_rendre = number_format($vente['Monnaie'], 2, '.', '');

    // 6. Entreprise
    if (isset($_SESSION['entreprise'])) {
        $entreprise = $_SESSION['entreprise'];
    } else {
        $result = $cnx->query("SELECT * FROM entreprise WHERE id = 1");
        $entreprise = $result->fetch(PDO::FETCH_ASSOC);
        $_SESSION['entreprise'] = $entreprise;
    }

// Nombre de clients du jour (pour le N° client)
$date_aujourdhui = date('Y-m-d');
$date_aujourdhui2 = date('d-m-Y');
$clients = selection_element('client');
$nombre_clients = 0;
foreach ($clients as $client) {
    $dateIns = date('Y-m-d', strtotime($client['DateIns']));
    if ($dateIns == $date_aujourdhui) {
        $nombre_clients++;
    }
}

} catch (Exception $e) {
    echo "<p style='color:red;'>Erreur : " . $e->getMessage() . "</p>";
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.4;
        }

        .order-form {
            width: 100%;
            min-height: 100vh;
            margin: 0 auto;
            padding: 0;
            position: relative;
        }

        /* Styles par défaut */
        body {
            font-size: 12px;
        }

        h1 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 30px;
            color: #333;
            text-transform: uppercase;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        /* HEADER - Toujours visible en haut de page */
        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px 0;
            border-bottom: 2px solid #333;
        }

        .company-info {
            width: 45%;
        }

        .order-info {
            width: 45%;
            text-align: right;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin-bottom: 10px;
            object-fit: contain;
        }

        /* SECTION CLIENT - Compacte */
        .section {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .seller, .buyer {
            width: 48%;
        }

        .seller h3, .buyer h3 {
            margin-bottom: 10px;
            color: #333;
            font-size: 14px;
        }

        /* TABLEAU DES ARTICLES - Optimisé */
        .articles-container {
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        th {
            background-color: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }

        td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 11px;
        }

        .product-table th:nth-child(1) { width: 40%; }
        .product-table th:nth-child(2) { width: 20%; }
        .product-table th:nth-child(3) { width: 15%; }
        .product-table th:nth-child(4) { width: 15%; }
        .product-table th:nth-child(5) { width: 10%; }

        /* TOTAUX - Toujours en bas de page */
        .totals {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            page-break-inside: avoid;
            page-break-before: avoid;
        }

        .totals p {
            margin: 5px 0;
            font-size: 12px;
        }

        .totals p span {
            float: right;
            font-weight: bold;
        }

        /* FOOTER - Fixe en bas de chaque page */
        footer {
            position: fixed;
            bottom: 15mm;
            left: 15mm;
            right: 15mm;
            padding: 10px 0;
            border-top: 1px solid #333;
            background: white;
            page-break-inside: avoid;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .footer-section {
            width: 48%;
        }

        .footer-section h4 {
            margin-bottom: 5px;
            color: #333;
            font-size: 12px;
        }

        .footer-section p {
            margin: 2px 0;
            font-size: 10px;
        }

        /* PAGINATION */
        .page-number {
            text-align: center;
            margin: 10px 0;
            font-size: 10px;
            color: #666;
        }

        /* SAUTS DE PAGE */
        .page-break {
            page-break-before: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* RESPONSIVE ET IMPRESSION */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .order-form {
                width: 100%;
                min-height: 100vh;
                padding: 0;
                margin: 0;
            }

            footer {
                position: fixed;
                bottom: 15mm;
            }

            .product-table {
                page-break-inside: avoid;
            }

            .no-break {
                page-break-inside: avoid;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <!-- Système de thème sombre/clair -->
</head>
<body class="order-form" id="orderForm">
    <div class="header-section">
        <div class="company-info">
            <img class="logo" src="<?= isset($entreprise['logo1']) && !empty($entreprise['logo1']) ? $entreprise['logo1'] : 'Image_article/sotech.png' ?>" alt="Logo de l'entreprise">
            <p><strong><?= isset($entreprise['nom']) && !empty($entreprise['nom']) ? $entreprise['nom'] : '' ?></strong></p>
            <p><?= isset($entreprise['telephone']) && !empty($entreprise['telephone']) ? $entreprise['telephone'] : '' ?></p>
        </div>
        <div class="order-info">
            <h1>Facture</h1>
            <?PHP
            setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra');
            date_default_timezone_set('Africa/Abidjan');
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
            echo "<p><strong>Date : </strong>" . $fmt->format(new DateTime()) . "</p>";
            ?>
            <p><strong>Facture N° : </strong> <?= htmlspecialchars($vente['NumeroVente'])?></p>
        </div>
    </div>

    <div class="section">
        <div class="seller">
            <h3>Vendeur</h3>
            <p><strong>Nom :</strong> <?= isset($_SESSION['nom_complet']) && !empty($_SESSION['nom_complet']) ? $_SESSION['nom_complet'] : '--------------------' ?></p>
        </div>
        <div class="buyer">
            <h3>Client</h3>
            <p><strong>Nom :</strong> <?= htmlspecialchars($vente['NomPrenomClient'])?></p>
            <p><strong>Téléphone :</strong> <?= htmlspecialchars($vente['Telephone'])?></p>
            <p><strong>E-mail :</strong> <?= htmlspecialchars($vente['Adresse_email']) ?></p>
            <p><strong>Date et Heure:</strong> <?= $date_aujourdhui2 ?> <?= date("H:i:s") ?></p>
        </div>
    </div>

    <div class="articles-container">
        <table class="facture-panier">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>N° Série</th>
                    <th>Prix Unitaire</th>
                    <th>Quantité</th>
                </tr>
            </thead>
            <tbody>
            <?php 
                $total = 0;
                $articleCount = count($panier);
                $itemsPerPage = 12; // Maximum 12 articles par page pour laisser de l'espace aux totaux
                $currentPage = 1;
                $itemIndex = 0;
                
                foreach ($panier as $item):
                    $subtotal = $item['PrixVenteTTC'] * $item['QuantiteVendue'];
                    $total += $subtotal;
                    $itemIndex++;
                    
                    // Saut de page après 12 articles (sauf pour le premier)
                    if ($itemIndex > $itemsPerPage && ($itemIndex - 1) % $itemsPerPage == 0) {
                        $currentPage++;
                        echo '</tbody></table>';
                        echo '<div class="page-break"></div>';
                        echo '<div class="page-number">Page ' . $currentPage . '</div>';
                        echo '<table class="facture-panier">';
                        echo '<thead><tr><th>Description</th><th>N° Série</th><th>Prix Unitaire</th><th>Quantité</th></tr></thead>';
                        echo '<tbody>';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['libelle']) ?></td>
                    <td><?= htmlspecialchars($item['NUMERO_SERIE']) ?></td>
                    <td><?= number_format($item['PrixVenteTTC'], 2) ?> FCFA</td>
                    <td><?= $item['QuantiteVendue'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="totals no-break">
        <p>Remise : <span><?= $remiseMontant ?> F.CFA</span></p>
        <p><strong>Total avec remise : <span><?= $montant_total ?> F.CFA</span></strong></p>
        <p><strong>Montant versé : <span><?= $montant_verse ?> F.CFA</span></strong></p>
        <p><strong>Monnaie à rendre : <span><?= $monnaie_rendre ?> F.CFA</span></strong></p>   
        <hr>
        <h4>Mode(s) de paiement :</h4>
        <ul>
            <?php foreach ($paiements as $p): ?>
                <li><?= htmlspecialchars($p['ModeReglement']) ?> : <?= number_format($p['MONTANT'], 2) ?> FCFA</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <p><strong><?= isset($entreprise['NCC']) && !empty($entreprise['NCC']) ? $entreprise['NCC'] : '' ?></strong></p>
                <p><?= isset($entreprise['RCCM']) && !empty($entreprise['RCCM']) ? $entreprise['RCCM'] : '' ?></p>
                <p><?= isset($entreprise['NUMERO']) && !empty($entreprise['NUMERO']) ? $entreprise['NUMERO'] : '' ?></p>
            </div>

            <div class="footer-section">
                <p><strong><?= isset($entreprise['NomBanque']) && !empty($entreprise['NomBanque']) ? $entreprise['NomBanque'] : '' ?></strong></p>
                <p><?= isset($entreprise['NumeroCompte']) && !empty($entreprise['NumeroCompte']) ? $entreprise['NumeroCompte'] : '' ?></p>
                <p><?= isset($entreprise['IBAN']) && !empty($entreprise['IBAN']) ? $entreprise['IBAN'] : '' ?></p>
            </div>
        </div>
    </footer>

    <script>
        window.onload = function () {
            // Impression automatique
            window.print();

            // Fermer automatiquement après impression
            setTimeout(function () {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html> 