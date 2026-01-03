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
    
    // Pour la compatibilité, si un seul paiement, on le met dans une variable simple
    $mode_paiement_libelle = $vente['ModePaiement'];
    if (!empty($paiements)) {
        if (count($paiements) === 1) {
            $mode_paiement_libelle = $paiements[0]['ModeReglement'];
        } elseif (count($paiements) > 1) {
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
    <title>Facture TVA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            font-size: 12px;
            line-height: 1.4;
            background: #fff;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .content-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        /* Classes pour le centrage dynamique */
        .center-small {
            margin-top: 150px !important;
        }
        .center-medium {
            margin-top: 100px !important;
        }
        .center-large {
            margin-top: 50px !important;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        .company-info, .client-info {
            width: 48%;
        }
        .company-info h3, .client-info h3 {
            font-size: 14px;
            margin-bottom: 12px;
        }
        .company-info p, .client-info p {
            margin: 4px 0;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-size: 9px;
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
        .totals {
            margin-top: 25px;
            border-top: 1px solid #000;
            padding-top: 15px;
            font-size: 11px;
            page-break-inside: avoid;
            page-break-before: avoid;
        }
        .totals p {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }
        .totals .amount {
            font-weight: bold;
        }
        /* Centrage pour impression */
        @media print {
            body {
                display: block;
                min-height: auto;
                justify-content: normal;
                align-items: normal;
            }
            .content-container {
                max-width: none;
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .center-small {
                margin-top: 200px !important;
            }
            .center-medium {
                margin-top: 150px !important;
            }
            .center-large {
                margin-top: 100px !important;
            }
        }
        
        /* Styles pour l'affichage à l'écran */
        @media screen {
            .content-container {
                margin: 20px auto;
            }
        }
    </style>
    <!-- Système de thème sombre/clair -->
</head>
<body>
    <div class="content-container">
        
        <?php 
        // Centrage dynamique basé sur le nombre d'articles
        $articleCount = count($panier);
        $centerClass = '';
        if ($articleCount <= 3) {
            $centerClass = 'center-small';
        } elseif ($articleCount <= 8) {
            $centerClass = 'center-medium';
        } elseif ($articleCount <= 15) {
            $centerClass = 'center-large';
        }
        ?>
        
        <div class="info-section <?= $centerClass ?>">
            <div class="company-info">
                <h3>Facture N°: <?= htmlspecialchars ($vente['NumeroVente'])?></h3>
                <p>Date: <?= date('d/m/Y H:i') ?></p>
                <p>Vendeur: <?= htmlspecialchars($_SESSION['nom_complet'] ?? '') ?></p>
            </div>
            <div class="client-info">
                <h3>Client</h3>
                <p>Nom: <?= htmlspecialchars($vente['NomPrenomClient']) ?></p>
                <p>Téléphone: <?= htmlspecialchars ($vente['Telephone'])?></p>
                <p>Email: <?= htmlspecialchars($vente['Adresse_email']) ?></p>
            </div>
        </div>

        <?php
        // Configuration de la pagination
        $itemsPerPage = 15; // Maximum 15 articles par page pour un meilleur centrage
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
                
                <table>
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
                        $startIndex = $page * $itemsPerPage;
                        $endIndex = min($startIndex + $itemsPerPage, $articleCount);
                        $total_ht = 0;
                        $tva_rate = 18; // 18%
                        
                        for ($i = $startIndex; $i < $endIndex; $i++):
                            $article = $panier[$i];
                            $prix_unitaire_ht = $article['PrixVenteTTC'] / (1 + ($tva_rate / 100));
                            $montant_total_ht = $prix_unitaire_ht * $article['QuantiteVendue'];
                            $total_ht += $montant_total_ht;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($article['libelle']); ?></td>
                            <td><?= htmlspecialchars($article['NUMERO_SERIE'] ?? 'N/A'); ?></td>
                            <td><?= number_format($prix_unitaire_ht, 0, ',', ' '); ?> F</td>
                            <td><?= htmlspecialchars($article['QuantiteVendue']); ?></td>
                        </tr>
                        <?php endfor; 
                        $montant_tva = $total_ht * ($tva_rate / 100);
                        $total_ttc = $total_ht + $montant_tva;
                        ?>
                    </tbody>
                </table>
            <?php endfor; ?>
        </div>

        <?php if ($articleCount > $itemsPerPage): ?>
            <div class="page-break"></div>
            <div class="page-number">Page <?= $currentPage + 1 ?> - Résumé</div>
        <?php endif; ?>
        
        <div class="totals no-break">
            <p><strong>Total TTC : <span class="amount"><?= number_format($total_ttc, 0, ',', ' ') ?> F</span></strong></p>
            <?php if ($vente['MontantRemise'] > 0): ?>
                <p>Remise : <span class="amount"><?= $remiseMontant ?> F</span></p>
            <?php endif; ?>
            <p class="net-due"><strong>Net à Payer : <span class="amount"><?= number_format($total_ttc - $vente['MontantRemise'], 0, ',', ' ') ?> F</span></strong></p>
            <hr>
            <p><strong>Montant Versé (cette transaction): <span><?= number_format($montant_verse_transaction, 0, ',', ' ') ?> F</span></strong></p>
            <p><strong>Total Acomptes : <span><?= number_format($vente['AccompteVerse'], 0, ',', ' ') ?> F</span></strong></p>
            <p><strong>Reste à Payer : <span><?= number_format($vente['RestantAPayer'], 0, ',', ' ') ?> F</span></strong></p>
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
            <hr>
            
        </div>
    </div>

    <script>
window.onload = function () {
    window.print();

    // Fermer automatiquement après impression (protection supplémentaire)
    setTimeout(function () {
        window.close();
    }, 1000);
};
</script>

</body>
</html>
