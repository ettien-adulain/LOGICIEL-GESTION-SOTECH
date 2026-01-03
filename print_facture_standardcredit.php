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

    // 1. Vente à crédit
    $vente = verifier_element('ventes_credit', ['NumeroVente'], [$numero], '');
    if (!$vente) throw new Exception("Vente introuvable !");

    // 2. Client
    $client = verifier_element('client', ['IDCLIENT'], [$vente['IDCLIENT']], '');
    $vente['NomPrenomClient'] = $client ? $client['NomPrenomClient'] : 'Client inconnu';
    $vente['Telephone'] = $client ? $client['Telephone'] : '';
    $vente['Adresse_email'] = $client ? $client['Adresse_email'] : '';

    // 3. Articles (panier) - Format corrigé pour récupérer uniquement les numéros de série de cette vente
    $req_articles = $cnx->prepare("
        SELECT DISTINCT a.libelle, a.PrixVenteTTC, l.QuantiteVendue, ns.NUMERO_SERIE
        FROM ventes_credit_ligne l
        JOIN article a ON l.IDARTICLE = a.IDARTICLE
        INNER JOIN num_serie ns 
            ON ns.IDARTICLE = l.IDARTICLE 
            AND ns.NumeroVente = l.NumeroVente 
            AND ns.IDvente_credit = l.IDVenteCredit
            AND ns.statut = 'vendue_credit'
        WHERE l.IDVenteCredit = ?
        ORDER BY a.libelle, ns.NUMERO_SERIE
    ");
    $req_articles->execute([$vente['IDVenteCredit']]);
    $panier = $req_articles->fetchAll(PDO::FETCH_ASSOC);

    // 4. Récupérer les détails des paiements
    $paiements = [];
    $montant_verse_transaction = 0;

    if (isset($_GET['paiements'])) {
        $paiement_ids = explode(',', $_GET['paiements']);
        $paiement_ids_safe = array_map('intval', $paiement_ids);
        $placeholders = implode(',', array_fill(0, count($paiement_ids_safe), '?'));
        $req_paiements = $cnx->prepare("
            SELECT p.AccompteVerse, m.ModeReglement, p.DateIns
            FROM ventes_credit_paiement p
            JOIN mode_reglement m ON p.IDMODE_REGLEMENT = m.IDMODE_REGLEMENT
            WHERE p.IDPaiement IN ($placeholders)
            ORDER BY p.DateIns ASC, p.IDPaiement ASC
        ");
        $req_paiements->execute($paiement_ids_safe);
        $paiements = $req_paiements->fetchAll(PDO::FETCH_ASSOC);
        foreach ($paiements as $p) {
            $montant_verse_transaction += $p['AccompteVerse'];
        }
    } else {
        // Par défaut, récupérer TOUS les paiements de la vente
        $req_paiement = $cnx->prepare("
            SELECT p.AccompteVerse, m.ModeReglement, p.DateIns
            FROM ventes_credit_paiement p
            JOIN mode_reglement m ON p.IDMODE_REGLEMENT = m.IDMODE_REGLEMENT
            WHERE p.IDVenteCredit = ?
            ORDER BY p.DateIns ASC, p.IDPaiement ASC
        ");
        $req_paiement->execute([$vente['IDVenteCredit']]);
        $paiements = $req_paiement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($paiements as $p) {
            $montant_verse_transaction += $p['AccompteVerse'];
        }
    }
    
    // Pour l'affichage simple, on garde le libellé du premier paiement
    $mode_paiement_libelle = '';
    if (!empty($paiements)) {
        $mode_paiement_libelle = $paiements[0]['ModeReglement'];
        if (count($paiements) > 1) {
            $mode_paiement_libelle = 'Multi-paiement';
        }
    }

    // 5. Montants
    $vrai_Montanttotal = number_format($vente['MontantTotal_sansremise'], 0, ',', ' ');
    $remiseMontant = number_format($vente['MontantRemise'], 0, ',', ' ');
    $montant_total = number_format($vente['MontantTotalCredit'], 0, ',', ' ');
    $montant_verse = number_format($vente['MontantVerse'], 0, ',', ' ');
    $acompte = number_format($vente['AccompteVerse'], 0, ',', ' ');
    $reste_a_payer = number_format($vente['RestantAPayer'], 0, ',', ' ');
    $monnaie_rendre = number_format($vente['Monnaie'], 0, ',', ' ');

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
    echo "<p style='color:red;'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
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

        /* Styles par défaut (pour moins de 5 articles) */
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

        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .company-info {
            width: 45%;
        }

        .order-info {
            width: 45%;
            text-align: right;
        }

        .logo {
            width: 120px;
            height: 120px;
            margin-bottom: 15px;
            object-fit: contain;
        }

        .section {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .seller, .buyer {
            width: 48%;
        }

        .seller h3, .buyer h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10px;
        }

        th {
            background-color: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 9px;
        }

        td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        
        /* Pagination */
        .page-break {
            page-break-before: always;
        }
        .page-number {
            text-align: center;
            margin: 10px 0;
            font-size: 9px;
            color: #666;
            font-weight: bold;
        }
        .no-break {
            page-break-inside: avoid;
        }

        .product-table th:nth-child(1) { width: 35%; }
        .product-table th:nth-child(2) { width: 20%; }
        .product-table th:nth-child(3) { width: 10%; }
        .product-table th:nth-child(4) { width: 20%; }
        .product-table th:nth-child(5) { width: 15%; }

        .totals {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .totals p {
            margin: 8px 0;
            font-size: 13px;
        }

        .totals p span {
            float: right;
            font-weight: bold;
        }

        footer {
            position: absolute;
            bottom: 20mm;
            left: 20mm;
            right: 20mm;
            padding: 20px 0;
            border-top: 2px solid #333;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .footer-section {
            width: 48%;
        }

        .footer-section h4 {
            margin-bottom: 10px;
            color: #333;
            font-size: 14px;
        }

        .footer-section p {
            margin: 5px 0;
            font-size: 11px;
        }

        /* Styles pour plus de 5 articles */
        body.compact-mode {
            font-size: 10px;
        }

        body.compact-mode h1 {
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }

        body.compact-mode .header-section {
            margin-bottom: 15px;
            height: 60px;
        }

        body.compact-mode .logo {
            width: 80px;
            height: 80px;
            margin-bottom: 5px;
        }

        body.compact-mode .section {
            margin: 10px 0;
            padding: 10px;
            border-radius: 3px;
        }

        body.compact-mode .seller h3, 
        body.compact-mode .buyer h3 {
            margin-bottom: 5px;
            font-size: 12px;
        }

        body.compact-mode table {
            margin: 10px 0;
            font-size: 9px;
        }

        body.compact-mode th {
            padding: 6px;
            font-size: 10px;
        }

        body.compact-mode td {
            padding: 4px;
            font-size: 9px;
        }

        body.compact-mode .totals {
            margin-top: 10px;
            padding: 10px;
            border-radius: 3px;
            font-size: 10px;
        }

        body.compact-mode .totals p {
            margin: 3px 0;
            font-size: 10px;
        }

        body.compact-mode footer {
            bottom: 15mm;
            left: 15mm;
            right: 15mm;
            padding: 10px 0;
            border-top: 1px solid #333;
            font-size: 9px;
        }

        body.compact-mode .footer-content {
            margin-top: 10px;
        }

        body.compact-mode .footer-section h4 {
            margin-bottom: 5px;
            font-size: 11px;
        }

        body.compact-mode .footer-section p {
            margin: 2px 0;
            font-size: 9px;
        }

        .table-container {
            max-height: none;
            overflow: visible;
        }

        body.compact-mode .table-container {
            max-height: 120mm;
            overflow-y: auto;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .order-form {
                width: 210mm;
                min-height: 297mm;
                padding: 20mm;
                margin: 0;
            }

            body.compact-mode .order-form {
                padding: 15mm;
            }

            footer {
                position: fixed;
                bottom: 20mm;
            }

            body.compact-mode footer {
                bottom: 15mm;
            }

            .product-table {
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
            <p><strong><?= isset($entreprise['nom']) && !empty($entreprise['nom']) ? $entreprise['nom'] : '--------------------' ?></strong></p>
            <p><?= isset($entreprise['telephone']) && !empty($entreprise['telephone']) ? $entreprise['telephone'] : '--------------------' ?></p>
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

    <?php
    // Configuration de la pagination
    $itemsPerPage = 15;
    $articleCount = count($panier);
    $totalPages = ceil($articleCount / $itemsPerPage);
    $currentPage = 0;
    ?>
    
    <div class="articles-container">
        <?php for ($page = 0; $page < $totalPages; $page++): ?>
            <?php if ($page > 0): ?>
                <div class="page-break"></div>
                <div class="page-number">Page <?= $page + 1 ?> - Suite des articles</div>
            <?php endif; ?>
            
            <table class="facture-panier">
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Numéro de Série</th>
                        <th>Prix Unitaire</th>
                        <th>Quantité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $startIndex = $page * $itemsPerPage;
                    $endIndex = min($startIndex + $itemsPerPage, $articleCount);
                    $totalGeneral = 0;
                    
                    for ($i = $startIndex; $i < $endIndex; $i++):
                        $article = $panier[$i];
                        $prixTotalArticle = $article['PrixVenteTTC'] * $article['QuantiteVendue'];
                        $totalGeneral += $prixTotalArticle;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($article['libelle']); ?></td>
                        <td><?= htmlspecialchars($article['NUMERO_SERIE'] ?? 'N/A'); ?></td>
                        <td><?= number_format($article['PrixVenteTTC'], 0, ',', ' '); ?> F</td>
                        <td><?= htmlspecialchars($article['QuantiteVendue']); ?></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php endfor; ?>
    </div>

    <?php if ($articleCount > $itemsPerPage): ?>
        <div class="page-break"></div>
        <div class="page-number">Page <?= $currentPage + 1 ?> - Résumé</div>
    <?php endif; ?>
    
    <div class="totals no-break">
       
        <p>Remise : <span><?=($remiseMontant) ?> F.CFA</span></p>
        <p><strong>Total avec remise : <span><?=($montant_total) ?> F.CFA</span></strong></p>
        <p><strong>Montant Versé (cette transaction) : <span><?= number_format($montant_verse_transaction, 0, ',', ' ') ?> F.CFA</span></strong></p>
        <p><strong>Total Acomptes : <span><?=($acompte) ?> F.CFA</span></strong></p>
        <p><strong>Reste à payer : <span><?=($reste_a_payer) ?> F.CFA</span></strong></p>
        <hr>
        <table class="payment-info">
            <tr>
                <td>
                    <?php if ($vente['RestantAPayer'] <= 0 || (isset($vente['statut']) && in_array($vente['statut'], ['Soldé', 'Transféré']))): ?>
                        <span style="color: #28a745; font-weight: bold; font-size: 1.1em;">SOLDÉ À CRÉDIT</span>
                    <?php elseif (count($paiements) > 0): ?>
                        <ul style="padding-left: 20px; margin-top: 5px;">
                            <?php foreach ($paiements as $p): ?>
                                <li><?= htmlspecialchars($p['ModeReglement']) ?>: <?= number_format($p['AccompteVerse'], 0, ',', ' ') ?> F</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        Aucun versement pour cette transaction.
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

        </p>
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