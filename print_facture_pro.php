<?php
session_start();
include('db/connecting.php');

// Récupération des informations de l'entreprise
$stmt = $cnx->prepare("SELECT * FROM entreprise WHERE id = 1");
$stmt->execute();
$entreprise = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture TVA Content</title>
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
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 0;
            position: relative;
        }
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 20mm 15mm;
            border-bottom: 1px solid #ddd;
            background: #fff;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .logo-section {
            text-align: center;
            width: 30%;
        }
        .logo-section img {
            max-width: 100px;
            height: auto;
        }
        .company-info {
            width: 65%;
        }
        .company-info h1 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }
        .company-info p {
            margin: 3px 0;
            font-size: 11px;
        }
        .content-area {
            min-height: 200mm;
            padding: 25mm 15mm;
            margin-top: 40mm;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10mm 15mm;
            border-top: 1px solid #ddd;
            background: #fff;
            font-size: 10px;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
        }
        .bank-info, .legal-info {
            width: 45%;
        }
        .bank-info h3, .legal-info h3 {
            font-size: 12px;
            margin-bottom: 5px;
            color: #333;
        }
        .bank-info p, .legal-info p {
            margin: 2px 0;
            font-size: 10px;
        }
        @media print {
            body {
                padding: 0;
                width: 210mm;
                height: 297mm;
            }
            .header, .footer {
                position: fixed;
            }
            .content-area {
                margin-top: 40mm;
                min-height: 200mm;
            }
            .no-print {
                display: none;
            }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <div class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="<?= isset($entreprise['logo1']) && !empty($entreprise['logo1']) ? $entreprise['logo1'] : 'Image_article/sotech.png' ?>" alt="Logo de l'entreprise">
            </div>
            <div class="company-info">
                <h1><?= htmlspecialchars($entreprise['nom'] ?? '') ?></h1>
                <p><strong>Adresse:</strong> <?= htmlspecialchars($entreprise['adresse'] ?? '') ?></p>
                <p><strong>Téléphone:</strong> <?= htmlspecialchars($entreprise['telephone'] ?? '') ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($entreprise['Email'] ?? '') ?></p>
                <p><strong>Bureau:</strong> <?= htmlspecialchars($entreprise['adresse_bureau'] ?? '') ?></p>
            </div>
        </div>
    </div>

    <div class="content-area">
        <!-- Le contenu de la facture sera inséré ici -->
    </div>

    <div class="footer">
        <div class="footer-content">
            <div class="bank-info">
                <h3>Informations Bancaires</h3>
                <p><strong>Banque:</strong> <?= htmlspecialchars($entreprise['NomBanque'] ?? '') ?></p>
                <p><strong>Compte:</strong> <?= htmlspecialchars($entreprise['NumeroCompte'] ?? '') ?></p>
                <p><strong>IBAN:</strong> <?= htmlspecialchars($entreprise['IBAN'] ?? '') ?></p>
                <p><strong>SWIFT:</strong> <?= htmlspecialchars($entreprise['Code_SWIFT'] ?? '') ?></p>
            </div>
            <div class="legal-info">
                <h3>Informations Légales</h3>
                <p><strong>NCC:</strong> <?= htmlspecialchars($entreprise['NCC'] ?? '') ?></p>
                <p><strong>RCCM:</strong> <?= htmlspecialchars($entreprise['RCCM'] ?? '') ?></p>
                <p><strong>Numéro:</strong> <?= htmlspecialchars($entreprise['NUMERO'] ?? '') ?></p>
                <p><strong>Site Web:</strong> <?= htmlspecialchars($entreprise['adresse_site'] ?? '') ?></p>

            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            window.onafterprint = function() {
                window.location.href = "caisse.php";
            };
        };
    </script>
</body>
</html>