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
    $vrai_Montanttotal = number_format($vente['MontantTotal_sansremise'], 2, '.', '');
    $remiseMontant = number_format($vente['MontantRemise'], 2, '.', '');
    $montant_total = number_format($vente['MontantTotalCredit'], 2, '.', '');
    $montant_verse = number_format($vente['MontantVerse'], 2, '.', '');
    $acompte = number_format($vente['AccompteVerse'], 2, '.', '');
    $reste_a_payer = number_format($vente['RestantAPayer'], 2, '.', '');
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
    <title>Ticket de Caisse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        .receipt-container {
            width: 100%;
            max-width: 80mm;
            margin: auto;
            border: 1px solid #ddd;
            padding: 10mm;
            background-color: #f9f9f9;
        }
        .receipt-header {
            text-align: center;
        }
        .receipt-header h1 {
            font-size: 14px;
        }
        .company-info img.logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
        }
        .receipt-details,
        .receipt-total,
        .footer {
            font-size: 10px;
            margin: 5px 0;
        }
        .receipt-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9px;
        }
        .receipt-items th,
        .receipt-items td {
            border: 1px solid #ddd;
            padding: 1px;
            text-align: left;
        }
        .receipt-items th {
            background-color: #f4f4f4;
            font-size: 8px;
        }
        
        /* Mode compact pour beaucoup d'articles */
        .compact-receipt {
            font-size: 8px;
        }
        .compact-receipt .receipt-items {
            font-size: 7px;
        }
        .compact-receipt .receipt-items th,
        .compact-receipt .receipt-items td {
            padding: 1px;
        }
        
        /* Pagination pour ticket */
        .page-break {
            page-break-before: always;
        }
        .page-number {
            text-align: center;
            margin: 5px 0;
            font-size: 8px;
            color: #666;
        }
        .barcode-container {
            text-align: center;
            margin-top: 10px;
        }
        /* Styles pour les paiements multiples */
        .payment-details {
            background-color: #f9f9f9;
            border-radius: 4px;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
        }
        .payment-details p {
            margin: 5px 0;
            line-height: 1.4;
        }
        .payment-details strong {
            display: inline-block;
            width: 140px;
        }
        .credit-summary {
            background-color: #fff;
            border: 1px dashed #000;
            border-radius: 4px;
            padding: 10px;
            margin: 15px 0;
        }
        .credit-summary p {
            margin: 5px 0;
            line-height: 1.4;
        }
        .credit-summary p:first-child {
            text-align: center;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .credit-summary strong {
            display: inline-block;
            width: 120px;
        }
        .single-payment {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
        }
        @media print {
            .no-print {
                display: none;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                width: 80mm;
                height: auto;
                padding: 5mm;
            }
            .payment-details,
            .credit-summary {
                border: 1px solid #000;
                background-color: #fff !important;
            }
            .credit-summary p:first-child {
                border-bottom: 1px solid #000;
            }
        }
        .footer-section {
            margin-top: 20px;
            padding-top: 10px;
        }
        .barcode-container {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background-color: #fff;
        }
        .barcode-container svg {
            max-width: 100%;
            height: auto;
        }
        .client-info {
            text-align: center;
            margin: 10px 0;
            padding: 5px;
        }
        .client-info strong {
            font-size: 12px;
            font-weight: bold;
        }
        .thank-you {
            text-align: center;
            margin-top: 10px;
            padding: 10px 0;
            border-top: 1px dashed #000;
        }
        .thank-you p {
            margin: 3px 0;
            font-size: 11px;
        }
        .thank-you p:last-child {
            font-weight: bold;
            margin-top: 8px;
        }
        @media print {
            .footer-section {
                position: relative;
                bottom: 0;
                width: 100%;
                margin-top: 20px;
            }
            .barcode-container {
                background-color: #fff !important;
            }
            .thank-you {
                border-top: 1px dashed #000 !important;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <!-- Système de thème sombre/clair -->
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>Ticket de Caisse</h1>
            <p><?= htmlspecialchars($vente['NumeroVente'])?></p>
            <div class="company-info">
                <img class="logo" 
                     src="<?= isset($entreprise['logo1']) && !empty($entreprise['logo1']) ? $entreprise['logo1'] : 'Image_article/sotech.png' ?>" 
                     alt="Logo de l'entreprise">
                <p><strong><?= $entreprise['nom'] ?? '--------------------' ?></strong></p>
                <p><?= $entreprise['telephone'] ?? '--------------------' ?></p>
                <p><?= $entreprise['Email'] ?? '--------------------' ?></p>
                <p><?= $entreprise['adresse_bureau'] ?? '--------------------' ?></p>
            </div>
            <p><strong><?= $_SESSION['nom_complet'] ?? '' ?></strong></p>
            </div>
        <hr>
        <div class="receipt-details">
            <h2>Détails de la Transaction</h2>
            <p><strong>Nom du Client :</strong> <?= htmlspecialchars($vente['NomPrenomClient']) ?></p>
            <p><strong>Numéro du Client :</strong> <?=htmlspecialchars($vente['Telephone'])  ?></p>
            <p><strong>Numéro de Vente :</strong> <?= htmlspecialchars($vente['NumeroVente']) ?></p>
            <p><strong>Date et Heure:</strong> <?= $date_aujourdhui2 ?> <?= date("H:i:s") ?></p>
        </div>
        <?php
        // Configuration de la pagination pour ticket
        $itemsPerPage = 12;
        $articleCount = count($panier);
        $totalPages = ceil($articleCount / $itemsPerPage);
        ?>
        
        <div class="articles-container">
            <?php for ($page = 0; $page < $totalPages; $page++): ?>
                <?php if ($page > 0): ?>
                    <div class="page-break"></div>
                    <div class="page-number">--- Suite ---</div>
                <?php endif; ?>
                
                <table class="receipt-items">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>N° Série</th>
                            <th>PU</th>
                            <th>Qté</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $startIndex = $page * $itemsPerPage;
                        $endIndex = min($startIndex + $itemsPerPage, $articleCount);
                        $total = 0;
                        
                        for ($i = $startIndex; $i < $endIndex; $i++):
                            $item = $panier[$i];
                            $subtotal = $item['PrixVenteTTC'] * $item['QuantiteVendue'];
                            $total += $subtotal;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['libelle']) ?></td>
                            <td><?= htmlspecialchars($item['NUMERO_SERIE']) ?></td>
                            <td><?= number_format($item['PrixVenteTTC'], 2) ?> FCFA</td>
                            <td><?= $item['QuantiteVendue'] ?></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            <?php endfor; ?>
        </div>
        <hr>
        <?php if ($articleCount > $itemsPerPage): ?>
            <div style="page-break-before: always; text-align: center; font-size: 8px; margin: 5px 0;">--- Résumé ---</div>
        <?php endif; ?>
        
        <?php if ($vente['ModePaiement'] === 'multi_paiement_credit' || count($paiements) > 1): ?>
            <!-- Cas Multi-paiements -->
            <div class="payment-details" style="margin: 10px 0; padding: 0;">
                <?php if ($vente['RestantAPayer'] <= 0 || (isset($vente['statut']) && in_array($vente['statut'], ['Soldé', 'Transféré']))): ?>
                    <span style="color: #28a745; font-weight: bold; font-size: 1.1em;">SOLDÉ À CRÉDIT</span>
                <?php else: ?>
                  
                        <?php foreach ($paiements as $p): ?>
                            <li">
                                <?= htmlspecialchars($p['ModeReglement']) ?> : <?= number_format($p['AccompteVerse'], 0, ',', ' ') ?> F
                            </li>
                        <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Cas Un seul paiement -->
            <div class="payment-details">
                <?php if ($vente['RestantAPayer'] <= 0 || (isset($vente['statut']) && in_array($vente['statut'], ['Soldé', 'Transféré']))): ?>
                    <span style="color: #28a745; font-weight: bold; font-size: 1.1em;">SOLDÉ À CRÉDIT</span>
                <?php else: ?>
                    <p><strong>Mode de paiement :</strong> <?= htmlspecialchars($mode_paiement_libelle) ?></p>
                    <p><strong>Montant versé :</strong> <?= number_format($montant_verse_transaction, 0, ',', ' ') ?> F</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($remiseMontant > 0): ?>
            <p><strong>Montant de Remise :</strong> <?= number_format($remiseMontant, 0, ',', ' ') ?> F.CFA</p>
        <?php endif; ?>
        <p><strong>Total avec Remise :</strong> <?= number_format($montant_total, 0, ',', ' ') ?> F.CFA</p>
        <p><strong>Acompte versé :</strong> <?= number_format($vente['AccompteVerse'], 0, ',', ' ') ?> F CFA</p>
        <p><strong>Reste à payer :</strong> <?= number_format($vente['RestantAPayer'], 0, ',', ' ') ?> F CFA</p>
    
        <!-- Séparateur -->
        <div style="border-top: 2px dashed #000; margin: 15px 0;"></div>

        <!-- Section code-barres et informations finales -->
        <div class="footer-section">
            <!-- Code-barres -->
            <div class="barcode-container" style="text-align: center; margin-bottom: 15px;">
                <svg id="barcode"></svg>
            </div>

            <!-- Informations client -->
            <div class="client-info" style="text-align: center; margin-bottom: 10px;">
                <p style="font-size: 11px; margin: 5px 0;">Client <strong>N°0<?= date('dmY') . '00' . $nombre_clients ?></strong></p>
            </div>

            <!-- Message de remerciement -->
            <div class="thank-you" style="text-align: center; border-top: 1px dashed #000; padding-top: 10px;">
                <p style="font-size: 11px; margin: 3px 0;">Merci ,nous espérons vous revoir bientôt.</p>
                <?php if ($vente['RestantAPayer'] > 0): ?>
                <p style="font-size: 11px; margin: 8px 0; font-weight: bold;">N'oubliez pas de régler le reste de votre crédit</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            JsBarcode("#barcode", "<?= $vente['NumeroVente'] ?>", {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 40,
                displayValue: true,
                fontSize: 12,
                margin: 10
            });
        });

        window.onload = function () {
            // Activer le mode compact si plus de 8 articles
            const articleCount = <?= $articleCount ?>;
            if (articleCount > 8) {
                document.body.classList.add('compact-receipt');
            }
            
            window.print();
            setTimeout(function () {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>
