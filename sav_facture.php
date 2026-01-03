<?php
try {
    session_start();
    include('db/connecting.php');
    include('fonction_traitement/fonction.php');

    if (!isset($_SESSION['nom_utilisateur'])) {
        header('location: connexion.php');
        exit();
    }

    if (!isset($_GET['id_sav'])) {
        throw new Exception("ID SAV non fourni !");
    }

    $id_sav = intval($_GET['id_sav']);

    // 1. Dossier SAV
    $stmt = $cnx->prepare("SELECT * FROM sav_dossier WHERE id_sav = ?");
    $stmt->execute([$id_sav]);
    $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dossier) {
        throw new Exception("Dossier SAV introuvable !");
    }

    // 2. Client
    $client = null;
    if ($dossier['id_client']) {
        $stmt = $cnx->prepare("SELECT NomPrenomClient, Telephone FROM client WHERE IDCLIENT = ?");
        $stmt->execute([$dossier['id_client']]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Paiements
    $stmt = $cnx->prepare("SELECT * FROM sav_paiement WHERE id_sav = ? ORDER BY date_paiement");
    $stmt->execute([$id_sav]);
    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Calculs financiers (matériaux cachés du client)
    $cout_estime = $dossier['cout_estime'] ?? 0;
    $cout_total = $cout_estime; // Seulement le coût estimatif pour le client
    
    // Paiements
    $total_payements = 0;
    foreach ($paiements as $p) {
        $total_payements += $p['montant'];
    }
    $reste_a_payer = $cout_total - $total_payements;

    // 6. Entreprise
    if (isset($_SESSION['entreprise'])) {
        $entreprise = $_SESSION['entreprise'];
    } else {
        $result = $cnx->query("SELECT * FROM entreprise WHERE id = 1");
        $entreprise = $result->fetch(PDO::FETCH_ASSOC);
        $_SESSION['entreprise'] = $entreprise;
    }

    // 7. Numéro de facture
    $numero_facture = 'FACT-SAV-' . date('Y') . '-' . str_pad($id_sav, 6, '0', STR_PAD_LEFT);

} catch (Exception $e) {
    echo "<p style='color:red;'>Erreur : " . $e->getMessage() . "</p>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture SAV</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            size: A4;
            margin: 15mm 10mm 15mm 10mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.2;
        }
        
        .facture-container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px solid #007bff;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        
        .header h1 {
            color: #007bff;
            margin: 0 0 4px 0;
            font-size: 18px;
        }
        
        .header .numero-facture {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .entreprise-info, .client-info {
            flex: 1;
            font-size: 9px;
        }
        
        .entreprise-info h4, .client-info h4 {
            margin: 0 0 4px 0;
            font-size: 11px;
        }
        
        .entreprise-info p, .client-info p {
            margin: 2px 0;
        }
        
        .facture-details {
            margin-bottom: 12px;
        }
        
        .facture-details h3 {
            color: #007bff;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin: 0 0 8px 0;
            font-size: 12px;
        }
        
        .table-facture {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 9px;
        }
        
        .table-facture th, .table-facture td {
            border: 1px solid #ddd;
            padding: 4px 6px;
            text-align: left;
        }
        
        .table-facture th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 9px;
        }
        
        .table-facture .text-right {
            text-align: right;
        }
        
        .table-facture .text-center {
            text-align: center;
        }
        
        .totaux {
            margin-top: 8px;
            border-top: 1px solid #007bff;
            padding-top: 6px;
        }
        
        .totaux table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totaux td {
            padding: 2px 4px;
            border: none;
            font-size: 10px;
        }
        
        .totaux .label {
            font-weight: bold;
        }
        
        .totaux .montant {
            text-align: right;
            font-weight: bold;
        }
        
        .totaux .total-final {
            font-size: 12px;
            color: #007bff;
            border-top: 1px solid #007bff;
        }
        
        .paiements {
            margin-top: 12px;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 3px;
        }
        
        .paiements h3 {
            color: #007bff;
            margin: 0 0 6px 0;
            font-size: 11px;
        }
        
        .paiements table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        
        .paiements th, .paiements td {
            border: 1px solid #ddd;
            padding: 3px 4px;
            text-align: left;
        }
        
        .paiements th {
            background-color: #e9ecef;
            font-size: 8px;
        }
        
        .reste-a-payer {
            margin-top: 8px;
            padding: 6px;
            border-radius: 3px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
        }
        
        .reste-a-payer.paye {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .reste-a-payer.en-attente {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .footer {
            margin-top: 12px;
            text-align: center;
            color: #666;
            font-size: 8px;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
        
        .footer p {
            margin: 2px 0;
        }
        
        @media print {
            body { 
                background: white; 
                margin: 0;
                padding: 0;
            }
            .facture-container { 
                box-shadow: none; 
                margin: 0;
                padding: 0;
            }
            .no-print { 
                display: none; 
            }
        }
    </style>
</head>
<body>
    <div class="facture-container">
        <!-- En-tête -->
        <div class="header">
            <h1>FACTURE SAV</h1>
            <div class="numero-facture"><?= $numero_facture ?></div>
            <div>Date : <?= date('d/m/Y') ?></div>
        </div>

        <!-- Informations entreprise et client -->
        <div class="info-section">
            <div class="entreprise-info">
                <h4><?= htmlspecialchars($entreprise['nom'] ?? 'Entreprise') ?></h4>
                <p><?= htmlspecialchars($entreprise['adresse_bureau'] ?? '') ?></p>
                <p>Tél : <?= htmlspecialchars($entreprise['telephone'] ?? '') ?></p>
                <p>Email : <?= htmlspecialchars($entreprise['Email'] ?? '') ?></p>
            </div>
            <div class="client-info">
                <h4>Client</h4>
                <?php if ($client): ?>
                    <p><strong><?= htmlspecialchars($client['NomPrenomClient']) ?></strong></p>
                    <p>Tél : <?= htmlspecialchars($client['Telephone']) ?></p>
                <?php else: ?>
                    <p>Client non renseigné</p>
                <?php endif; ?>
                <p><strong>Dossier SAV :</strong> <?= htmlspecialchars($dossier['numero_sav']) ?></p>
                <p><strong>Date dépôt :</strong> <?= date('d/m/Y', strtotime($dossier['date_depot'])) ?></p>
            </div>
        </div>

        <!-- Détails de la prestation -->
        <div class="facture-details">
            <h3>Détails de la prestation</h3>
            <table class="table-facture">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Qté</th>
                        <th class="text-right">Prix unit.</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Coût estimatif -->
                    <tr>
                        <td><strong>Réparation - <?= htmlspecialchars($dossier['numero_serie']) ?></strong><br>
                            <small><?= htmlspecialchars($dossier['description_panne']) ?></small>
                        </td>
                        <td class="text-center">1</td>
                        <td class="text-right"><?= number_format($cout_estime, 0) ?> F.CFA</td>
                        <td class="text-right"><?= number_format($cout_estime, 0) ?> F.CFA</td>
                    </tr>
                </tbody>
            </table>

            <!-- Totaux -->
            <div class="totaux">
                <table>
                    <tr class="total-final">
                        <td class="label">Total :</td>
                        <td class="montant total-final"><?= number_format($cout_total, 0) ?> F.CFA</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Paiements -->
        <div class="paiements">
            <h3>Paiements effectués</h3>
            <?php if (!empty($paiements)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Montant</th>
                        <th>Utilisateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements as $p): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                        <td><?= ucfirst(htmlspecialchars($p['type_paiement'])) ?></td>
                        <td class="text-right"><?= number_format($p['montant'], 0) ?> F.CFA</td>
                        <td><?= htmlspecialchars($p['utilisateur']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2"><strong>Total payé :</strong></td>
                        <td class="text-right"><strong><?= number_format($total_payements, 0) ?> F.CFA</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <p>Aucun paiement enregistré</p>
            <?php endif; ?>

            <!-- Reste à payer -->
            <div class="reste-a-payer <?= $reste_a_payer <= 0 ? 'paye' : 'en-attente' ?>">
                <?php if ($reste_a_payer <= 0): ?>
                    ✓ Dossier entièrement payé
                <?php else: ?>
                    ⚠ Reste à payer : <?= number_format($reste_a_payer, 0) ?> F.CFA
                <?php endif; ?>
            </div>
        </div>

        <!-- Pied de page -->
        <div class="footer">
            <p>Merci de votre confiance</p>
            <p>Cette facture est générée automatiquement par le système de gestion SAV</p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() {
                window.close();
            }, 1000);
        };

    </script>
</body>
</html> 