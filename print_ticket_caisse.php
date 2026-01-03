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
        .compact-receipt .receipt-items {
            font-size: 8px;
        }
        .compact-receipt .receipt-items th,
        .compact-receipt .receipt-items td {
            padding: 1px;
            font-size: 7px;
        }
        .compact-receipt .receipt-header h1 {
            font-size: 12px;
        }
        .compact-receipt .receipt-details,
        .compact-receipt .receipt-total,
        .compact-receipt .footer {
            font-size: 8px;
        }
        .barcode-container {
            text-align: center;
            margin-top: 10px;
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
        }
        .footer {
            font-size: 10px;
            margin: 20px 0 0 0;
            text-align: center;
            line-height: 1.5;
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
        <table class="receipt-items">
            <thead>
                <tr>
                    <th>Libellé</th>
                    <th>N° Série</th>
                    <th>PU</th>
                    <th>Qté</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            <?php
                $total = 0;
                $articleCount = count($panier);
                $itemsPerPage = 12; // Maximum 12 articles par page sur ticket 80mm
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
                        echo '<div style="page-break-before: always; text-align: center; font-size: 8px; margin: 5px 0;">--- Suite ---</div>';
                        echo '<table class="receipt-items">';
                        echo '<thead><tr><th>Libellé</th><th>N° Série</th><th>PU</th><th>Qté</th><th>Total</th></tr></thead>';
                        echo '<tbody>';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['libelle']) ?></td>
                    <td><?= htmlspecialchars($item['NUMERO_SERIE']) ?></td>
                    <td><?= number_format($item['PrixVenteTTC'], 2) ?> FCFA</td>
                    <td><?= $item['QuantiteVendue'] ?></td>
                    <td><?= number_format($subtotal, 2) ?> FCFA</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <hr>
        <p><strong>Mode(s) de paiement :</strong></p>
        <ul>
            <?php foreach ($paiements as $p): ?>
                <li><?= htmlspecialchars($p['ModeReglement']) ?> : <?= number_format($p['MONTANT'], 2) ?> FCFA</li>
            <?php endforeach; ?>
        </ul>
        <p><strong>Articles :</strong> <?= $articleCount ?></p>
        <p><strong>Montant de Remise :</strong> <?= $remiseMontant ?> F.CFA</p>
        <p><strong>Total avec Remise :</strong> <?= $montant_total ?> F.CFA</p>
        <p><strong>Montant Versé :</strong> <?= $montant_verse ?> F.CFA</p>
        <p><strong>Monnaie à Rendre :</strong> <?= $monnaie_rendre ?> F.CFA</p>
            <hr>
            <div class="footer">
                <p style="margin: 0 0 4px 0;"><strong>Client N°0<?= date('dmY') . '00' . $nombre_clients ?></strong></p>
                <p style="margin: 0 0 4px 0;">Merci pour votre achat&nbsp;!</p>
                <p style="margin: 0;">Nous espérons vous revoir bientôt.</p>
            </div>
        </div>
       
      
           
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            JsBarcode("#barcode", "<?= $numero ?>", {
                format: "CODE128",
                lineColor: "#000",
                width: 1,
                height: 50,
                displayValue: true
            });
        });

        window.onload = function () {
            // Activer le mode compact si plus de 8 articles
            const articleCount = <?= $articleCount ?>;
            if (articleCount > 8) {
                document.body.classList.add('compact-receipt');
            }
            
            window.print();
        };
        
        
    </script>
</body>
</html>
