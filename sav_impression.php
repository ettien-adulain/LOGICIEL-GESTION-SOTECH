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

    // 3. Entreprise
    if (isset($_SESSION['entreprise'])) {
        $entreprise = $_SESSION['entreprise'];
    } else {
        $result = $cnx->query("SELECT * FROM entreprise WHERE id = 1");
        $entreprise = $result->fetch(PDO::FETCH_ASSOC);
        $_SESSION['entreprise'] = $entreprise;
    }

    // 4. Date et heure
    $date_aujourdhui = date('d-m-Y');
    $heure_actuelle = date('H:i:s');

} catch (Exception $e) {
    echo "<p style='color:red;'>Erreur : " . $e->getMessage() . "</p>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu SAV</title>
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
        .sav-details {
            background-color: #fff;
            border: 1px dashed #000;
            border-radius: 4px;
            padding: 10px;
            margin: 15px 0;
        }
        .sav-details p {
            margin: 5px 0;
            line-height: 1.4;
        }
        .sav-details p:first-child {
            text-align: center;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .sav-details strong {
            display: inline-block;
            width: 120px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-en_attente { background-color: #ffc107; color: #000; }
        .status-en_cours { background-color: #17a2b8; color: #fff; }
        .status-pret { background-color: #28a745; color: #fff; }
        .status-livre { background-color: #6c757d; color: #fff; }
        .status-annule { background-color: #dc3545; color: #fff; }
        
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
            .sav-details {
                border: 1px solid #000;
                background-color: #fff !important;
            }
            .sav-details p:first-child {
                border-bottom: 1px solid #000;
            }
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
            <h1>Reçu SAV</h1>
            <p><?= htmlspecialchars($dossier['numero_sav']) ?></p>
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
        
        <!-- Détails du dossier SAV -->
        <div class="sav-details">
            <p>DÉTAILS DU DOSSIER SAV</p>
            <p><strong>N° SAV :</strong> <?= htmlspecialchars($dossier['numero_sav']) ?></p>
            <p><strong>Date dépôt :</strong> <?= date('d/m/Y H:i', strtotime($dossier['date_depot'])) ?></p>
            <p><strong>Statut :</strong> 
                <span class="status-badge status-<?= $dossier['statut'] ?>">
                    <?= htmlspecialchars($dossier['statut']) ?>
                </span>
            </p>
        </div>

        <!-- Informations client -->
        <div class="receipt-details">
            <h2>Informations Client</h2>
            <?php if ($client): ?>
                <p><strong>Client:</strong> <?= htmlspecialchars($client['NomPrenomClient']) ?> <?= htmlspecialchars($client['Telephone']) ?></p>
            <?php else: ?>
                <p><strong>Client:</strong> Non renseigné</p>
            <?php endif; ?>
        </div>

        <!-- Informations produit -->
        <div class="receipt-details">
            <h2>Informations Produit</h2>
            <p><strong>N° Série/Réf :</strong> <?= htmlspecialchars($dossier['numero_serie']) ?></p>
            <p><strong>État réception :</strong> <?= htmlspecialchars($dossier['etat_reception'] ?: 'Non précisé') ?></p>
        </div>

        <!-- Description panne -->
        <div class="receipt-details">
            <h2>Description de la Panne</h2>
            <div style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                <p style="margin: 0; font-size: 9px; line-height: 1.3;">
                    <?= nl2br(htmlspecialchars($dossier['description_panne'])) ?>
                </p>
            </div>
        </div>

        <!-- Coût et délai -->
        <div class="receipt-details">
            <h2>Estimation</h2>
            <?php if ($dossier['cout_estime']): ?>
                <p><strong>Coût estimatif :</strong> <?= number_format($dossier['cout_estime'], 0, ',', ' ') ?> F.CFA</p>
            <?php endif; ?>
            <?php if ($dossier['date_previsionnelle']): ?>
                <p><strong>Délai prévisionnel :</strong> <?= date('d/m/Y', strtotime($dossier['date_previsionnelle'])) ?></p>
            <?php endif; ?>
        </div>

        <!-- Séparateur -->
        <div style="border-top: 2px dashed #000; margin: 15px 0;"></div>

        <!-- Section code-barres et informations finales -->
        <div class="footer-section">
            <!-- Code-barres -->
            <div class="barcode-container">
                <svg id="barcode"></svg>
            </div>

            <!-- Message de remerciement -->
            <div class="thank-you">
                <p style="font-size: 11px; margin: 3px 0;">Merci de votre confiance.</p>
                <p style="font-size: 11px; margin: 3px 0;">Nous vous contacterons dès que votre appareil sera prêt.</p>
                <?php if ($dossier['statut'] === 'en_attente'): ?>
                <p style="font-size: 11px; margin: 8px 0; font-weight: bold;">Votre dossier est en cours d'étude</p>
                <?php elseif ($dossier['statut'] === 'en_cours'): ?>
                <p style="font-size: 11px; margin: 8px 0; font-weight: bold;">Réparation en cours</p>
                <?php elseif ($dossier['statut'] === 'pret'): ?>
                <p style="font-size: 11px; margin: 8px 0; font-weight: bold;">Votre appareil est prêt à être récupéré</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            JsBarcode("#barcode", "<?= $dossier['numero_sav'] ?>", {
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
            window.print();
            setTimeout(function () {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html> 