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
            margin-bottom: 20px;
            padding-bottom: 10px;
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
        }
        .totals {
            margin-top: 25px;
            border-top: 1px solid #000;
            padding-top: 15px;
            font-size: 11px;
            page-break-inside: avoid;
            page-break-before: avoid;
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
        .totals p {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }
        .totals .amount {
            font-weight: bold;
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
                $total = 0;
                $articleCount = count($panier);
                $itemsPerPage = 15; // Maximum 15 articles par page pour un meilleur centrage
                $currentPage = 1;
                $itemIndex = 0;
                
                foreach ($panier as $item):
                    $subtotal = $item['PrixVenteTTC'] * $item['QuantiteVendue'];
                    $total += $subtotal;
                    $itemIndex++;
                    
                    // Saut de page après 15 articles (sauf pour le premier)
                    if ($itemIndex > $itemsPerPage && ($itemIndex - 1) % $itemsPerPage == 0) {
                        $currentPage++;
                        echo '</tbody></table>';
                        echo '<div class="page-break"></div>';
                        echo '<div class="page-number">Page ' . $currentPage . '</div>';
                        echo '<table>';
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

        <div class="totals">
            <p>Remise: <span class="amount"><?= $remiseMontant ?> F.CFA</span></p>
            <p>Net à payer: <span class="amount"><?= $montant_total ?> F.CFA</span></p>
            <p>Montant versé: <span class="amount"><?= $montant_verse ?> F.CFA</span></p>
            <p>Monnaie rendue: <span class="amount"><?= $monnaie_rendre ?> F.CFA</span></p>
            <hr>
            <h4>Mode(s) de paiement :</h4>
            <ul>
                <?php foreach ($paiements as $p): ?>
                    <li><?= htmlspecialchars($p['ModeReglement']) ?> : <?= number_format($p['MONTANT'], 2) ?> FCFA</li>
                <?php endforeach; ?>
            </ul>
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
