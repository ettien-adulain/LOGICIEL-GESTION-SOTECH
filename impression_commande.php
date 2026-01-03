<?php
// Configuration pour Hostinger
error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs en production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Gestion des erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        http_response_code(500);
        echo "Une erreur s'est produite. Veuillez réessayer plus tard.";
        exit;
    }
});

try {
    // Chargement de l'autoloader avec plusieurs chemins possibles
    $autoload_paths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        realpath(__DIR__ . '/../vendor/autoload.php')
    ];
    
    $autoload_loaded = false;
    foreach ($autoload_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $autoload_loaded = true;
            break;
        }
    }
    
    if (!$autoload_loaded) {
        throw new Exception("Autoloader Composer introuvable");
    }
    
    // Chargement des autres fichiers
    if (!file_exists('fonction_traitement/fonction.php')) {
        throw new Exception("Fichier fonction.php introuvable");
    }
    require_once('fonction_traitement/fonction.php');
    
    if (!file_exists('db/connecting.php')) {
        throw new Exception("Fichier connecting.php introuvable");
    }
    require_once('db/connecting.php');
    
    session_start();
    
    // Vérification de la connexion à la base de données
    if (!isset($cnx) || $cnx === null) {
        throw new Exception("Connexion à la base de données échouée");
    }
    
} catch (Exception $e) {
    error_log("Erreur de chargement: " . $e->getMessage());
    http_response_code(500);
    echo "Erreur de chargement: " . $e->getMessage();
    exit;
}

// Import des classes Dompdf après le try-catch
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['nom_utilisateur'])) {
    header('location: connexion.php');
    exit();
}

// Récupération des données avec gestion d'erreurs
try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $is_pdf = ($action === 'download');

    if (!$id || $id <= 0) {
        throw new Exception("ID commande manquant ou invalide");
    }

    // Récupération des données de la commande depuis la base de données
    $commande = verifier_element('commande', ['id'], [$id], '');
    if (!$commande) {
        throw new Exception("Commande non trouvée avec l'ID : " . $id);
    }

    $idcommande = $commande['numero_commande'];
    $idfournisseur = $commande['IDFOURNISSEUR'];
    $datecommande = $commande['DateIns'];

    // Récupération des lignes de commande depuis la base de données
    $liste_article = verifier_element_tous('commande_ligne', ['id'], [$id], '');
    
    if (empty($liste_article)) {
        throw new Exception("Aucun article trouvé pour cette commande");
    }

    // Récupération des informations du fournisseur
    $fournisseur = verifier_element('fournisseur', ['IDFOURNISSEUR'], [$idfournisseur], '');
    if (!$fournisseur) {
        throw new Exception("Fournisseur non trouvé avec l'ID : " . $idfournisseur);
    }

    // Récupération des informations de l'entreprise
    $entreprise = $cnx->query("SELECT * FROM entreprise WHERE id = 1")->fetch();
    if (!$entreprise) {
        throw new Exception("Informations de l'entreprise introuvables");
    }
    
} catch (Exception $e) {
    error_log("Erreur impression_commande: " . $e->getMessage());
    http_response_code(500);
    echo "Erreur : " . $e->getMessage();
    exit;
}

// Gestion robuste du chemin du logo pour Hostinger
function getLogoPath($entreprise) {
    $logo_path = (isset($entreprise['logo1']) && !empty($entreprise['logo1']))
        ? $entreprise['logo1']
        : 'Image_article/sotech.png';
    
    // Si c'est déjà une URL complète, on la retourne
    if (strpos($logo_path, 'http') === 0) {
        return $logo_path;
    }
    
    // Pour Hostinger et autres hébergeurs, construire l'URL complète
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Nettoyer les chemins
    $script_dir = rtrim($script_dir, '/');
    $logo_path = ltrim($logo_path, '/');
    
    $full_path = $protocol . '://' . $host . $script_dir . '/' . $logo_path;
    
    // Nettoyer les doubles slashes
    $full_path = preg_replace('#(?<!:)//+#', '/', $full_path);
    
    return $full_path;
}

$logo_path = getLogoPath($entreprise);

function render_commande_html($idcommande, $fournisseur, $datecommande, $liste_article, $entreprise, $logo_path) {
    ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de Commande</title>
    <style>
        @page {
            margin: 30px 25px 80px 25px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            background: #f7f7f7;
        }
        .container-facture {
            background: #fff;
            margin: 20px auto 0 auto;
            padding: 20px 20px 0 20px;
            max-width: 900px;
            margin-bottom: 15px;
        }
        .header-facture {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .header-facture .logo {
            display: table-cell;
            vertical-align: top;
            width: 100px;
            max-width: 90px;
            max-height: 90px;
        }
        .header-facture .infos {
            display: table-cell;
            text-align: right;
            vertical-align: top;
            padding-left: 20px;
        }
        .header-facture h1 {
            color: #b71c1c;
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        .section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .seller, .buyer {
            display: table-cell;
            background: #f7f7f7;
            padding: 8px 15px;
            width: 48%;
            vertical-align: top;
        }
        .seller h3, .buyer h3 {
            color: #b71c1c;
            font-size: 1rem;
            margin-bottom: 5px;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            background: #fff;
        }
        .product-table th, .product-table td {
            border: 1px solid #ccc;
            padding: 3px 2px;
            font-size: 9px;
            text-align: center;
        }
        .product-table th {
            background: #b71c1c;
            color: #fff;
            font-size: 9.5px;
        }
        .envoie {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            background: #fff;
        }
        .envoie th, .envoie td {
            border: 1px solid #ccc;
            padding: 6px 3px;
            font-size: 9px;
            text-align: center;
        }
        .envoie th {
            background: #b71c1c;
            color: #fff;
            font-size: 9.5px;
        }
        .envoie tbody tr td {
            height: 60px;
            vertical-align: top;
        }
        .display {
            margin-bottom: 15px;
            display: table;
            width: 100%;
        }
        .signature-section {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            text-align: left;
        }
        .signature-title {
            font-size: 11px;
            margin-bottom: 8px;
            text-decoration: underline;
        }
        .totals {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            text-align: right;
            background: #f7f7f7;
            padding: 12px 20px;
            font-size: 1em;
        }
        .totals strong {
            color: #388e3c;
        }
        .clear { clear: both; }
        /* Footer unifié pour PDF et impression navigateur, sans ombre ni fond gris */
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #fff;
            color: #333;
            border-top: 2px solid #b71c1c;
            font-size: 9px;
            line-height: 1.2;
            padding: 8px 0 4px 0;
            text-align: center;
        }
        /* Footer alternatif pour impression navigateur */
        .footer-print {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #fff;
            color: #333;
            border-top: 0px solid #b71c1c;
            font-size: 7px;
            line-height: 1.2;
            padding: 8px 0 4px 0;
            text-align: center;
        }
        .footer-flex {
            width: 100%;
            display: table;
            table-layout: fixed;
        }
        .footer-flex > div {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            min-width: 180px;
            text-align: center;
        }
        .footer-flex p {
            margin-bottom: 2px;
        }
        .footer-note {
            color: #b71c1c;
            font-size: 5px;
            margin-top: 0px;
        }
        @media print {
            body {
                background: #fff;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }
            .container-facture {
                margin: 0;
                flex: 1;
                padding-bottom: 30px;
            }
            footer {
                display: none !important;
            }
            .footer-print {
                display: block !important;
                position: fixed !important;
                bottom: 10px !important;
                left: 0 !important;
                right: 0 !important;
                height: 60px !important;
                background: #fff !important;
                color: #333 !important;
                border-top: 2px solid #b71c1c !important;
                font-size: 7px !important;
                line-height: 1.2 !important;
                padding: 8px 0 4px 0 !important;
                text-align: center !important;
                page-break-inside: avoid !important;
                margin-top: 0 !important;
            }
        }
    </style>
</head>
<body class="order-form" id="orderForm">
    <div class="container-facture">
        <div class="header-facture">
            <img class="logo" src="<?= $logo_path ?>" alt="Logo de l'entreprise">
            <div class="company-info">
                <p><strong><?= isset($entreprise['nom']) && !empty($entreprise['nom']) ? $entreprise['nom'] : '' ?></strong></p>
                <p><?= isset($entreprise['telephone']) && !empty($entreprise['telephone']) ? $entreprise['telephone'] : '' ?></p>
                <p><?= isset($entreprise['Email']) && !empty($entreprise['Email']) ? $entreprise['Email'] : '' ?></p>
                <p><?= isset($entreprise['adresse_bureau']) && !empty($entreprise['adresse_bureau']) ? $entreprise['adresse_bureau'] : '' ?></p>
            </div>
            <div class="infos">
                <h1>Bon de Commande</h1>
                <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($datecommande)) ?></p>
                <p><strong>Commande N° :</strong> <?= isset($idcommande) && !empty($idcommande) ? $idcommande : '--------------------' ?></p>
            </div>
        </div>
        <div class="section">
            <div class="seller">
                <h3>Vendeur :</h3>
                <p><strong><?= isset($_SESSION['nom_complet']) && !empty($_SESSION['nom_complet']) ? $_SESSION['nom_complet'] : '' ?></strong></p>
            </div>
            <div class="buyer">
                <h3>Adressé à :</h3>
                <p>Nom : <?= isset($fournisseur['NomFournisseur']) && !empty($fournisseur['NomFournisseur']) ? $fournisseur['NomFournisseur'] : (isset($fournisseur['societeFournisseur']) ? $fournisseur['societeFournisseur'] : '--------------------') ?></p>
                <p>Téléphone : <?= isset($fournisseur['TelephoneFournisseur']) && !empty($fournisseur['TelephoneFournisseur']) ? $fournisseur['TelephoneFournisseur'] : '--------------------' ?></p>
                <p>E-mail : <?= isset($fournisseur['eMailFournisseur']) && !empty($fournisseur['eMailFournisseur']) ? $fournisseur['eMailFournisseur'] : '--------------------' ?></p>
            </div>
        </div>

        <table class="envoie">
            <thead>
                <tr>
                    <th>Envoi par</th>
                    <th>Mode de Livraison</th>
                    <th>Conditions de Livraison</th>
                    <th>Date de Livraison</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="4"></td>
                </tr>
            </tbody>
        </table>

        <table class="product-table">
            <thead>
                <tr>
                    <th>Nom du Produit/Description</th>
                    <th>Prix Unitaire</th>
                    <th>Qté</th>
                    <th>Prix Total</th>
                    <th>Date de Livraison</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalPanier = 0;
                
                if (!empty($liste_article)) {
                    foreach ($liste_article as $article) {
                        $id_article = $article['IDARTICLE'];
                        $nom = verifier_element('article', ['IDARTICLE'], [$id_article], '');
                        
                        // Vérifier que l'article existe
                        if (!$nom || !isset($nom['libelle'])) {
                            $produit = 'Article introuvable';
                        } else {
                            $produit = htmlspecialchars($nom['libelle']);
                        }
                        
                        $prixAchat = floatval($article['prixAchat'] ?? 0);
                        $quantite = intval($article['quantite'] ?? 0);
                        $totalPanier += $prixAchat * $quantite;
                ?>
                        <tr>
                            <td style="text-align:left;"><?= $produit ?></td>
                            <td><?= number_format($prixAchat, 0, ',', ' ') ?> F.CFA</td>
                            <td><?= $quantite ?></td>
                            <td><?= number_format($prixAchat * $quantite, 0, ',', ' ') ?> F.CFA</td>
                            <td><?= date('Y-m-d H:i:s'); ?></td>
                        </tr>
                <?php
                    }
                } else {
                    echo "<tr><td colspan='5'>Aucun produit dans la commande</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <div class="display">
            <div class="signature-section">
                <p class="signature-title"><strong>RESPONSABLE :</strong></p>
            </div>
            <div class="totals">                
                <p><strong>Total: <span id="total"><?= number_format($totalPanier, 0, ',', ' ') ?> F.CFA</span></strong></p>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="footer-flex">
            <div>
                <p><strong><?= isset($entreprise['NCC']) && !empty($entreprise['NCC']) ? $entreprise['NCC'] : '' ?></strong></p>
                <p><?= isset($entreprise['RCCM']) && !empty($entreprise['RCCM']) ? $entreprise['RCCM'] : '' ?></p>
                <p> <?= isset($entreprise['NUMERO']) && !empty($entreprise['NUMERO']) ? $entreprise['NUMERO'] : '' ?></p>
                <p> <?= isset($entreprise['adresse']) && !empty($entreprise['adresse']) ? $entreprise['adresse'] : '' ?></p>
            </div>
            <div>
                <p> <strong><?= isset($entreprise['NomBanque']) && !empty($entreprise['NomBanque']) ? $entreprise['NomBanque'] : '' ?></strong></p>
                <p><?= isset($entreprise['NumeroCompte']) && !empty($entreprise['NumeroCompte']) ? $entreprise['NumeroCompte'] : '' ?></p>
                <p>  <?= isset($entreprise['IBAN']) && !empty($entreprise['IBAN']) ? $entreprise['IBAN'] : '' ?></p>
                <p> <?= isset($entreprise['Code_SWIFT']) && !empty($entreprise['Code_SWIFT']) ? $entreprise['Code_SWIFT'] : '' ?></p>
            </div>
        </div>
        <div class="footer-note">
            <em>Merci pour votre confiance - Document généré le <?= date('d/m/Y H:i') ?></em>
        </div>
    </footer>
    
    <!-- Footer alternatif pour impression navigateur -->
    <div class="footer-print">
        <div class="footer-flex">
            <div>
                <p><strong><?= isset($entreprise['NCC']) && !empty($entreprise['NCC']) ? $entreprise['NCC'] : '' ?></strong></p>
                <p><?= isset($entreprise['RCCM']) && !empty($entreprise['RCCM']) ? $entreprise['RCCM'] : '' ?></p>
                <p> <?= isset($entreprise['NUMERO']) && !empty($entreprise['NUMERO']) ? $entreprise['NUMERO'] : '' ?></p>
                <p><?= isset($entreprise['adresse']) && !empty($entreprise['adresse']) ? $entreprise['adresse'] : '' ?></p>
            </div>
            <div>
                <p><strong><?= isset($entreprise['NomBanque']) && !empty($entreprise['NomBanque']) ? $entreprise['NomBanque'] : '' ?></strong></p>
                <p><?= isset($entreprise['NumeroCompte']) && !empty($entreprise['NumeroCompte']) ? $entreprise['NumeroCompte'] : '' ?></p>
                <p><?= isset($entreprise['IBAN']) && !empty($entreprise['IBAN']) ? $entreprise['IBAN'] : '' ?></p>
                <p><?= isset($entreprise['Code_SWIFT']) && !empty($entreprise['Code_SWIFT']) ? $entreprise['Code_SWIFT'] : '' ?></p>
            </div>
        </div>
        <div class="footer-note">
            <em>Merci pour votre confiance - Document généré le <?= date('d/m/Y H:i') ?></em>
        </div>
    </div>
</body>
</html>
<?php
    return ob_get_clean();
}

if ($is_pdf) {
    try {
        // Configuration optimisée pour Hostinger
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('debugPng', false);
        $options->set('debugKeepTemp', false);
        $options->set('debugCss', false);
        $options->set('debugLayout', false);
        $options->set('debugLayoutLines', false);
        $options->set('debugLayoutBlocks', false);
        $options->set('debugLayoutInline', false);
        $options->set('debugLayoutPaddingBox', false);
        $options->set('defaultMediaType', 'screen');
        $options->set('isJavascriptEnabled', false);
        
        $dompdf = new Dompdf($options);
        
        // Augmenter la mémoire et le temps d'exécution pour Hostinger
        ini_set('memory_limit', '256M');
        set_time_limit(60);
        
        $html = render_commande_html($idcommande, $fournisseur, $datecommande, $liste_article, $entreprise, $logo_path);
        
        if (empty($html)) {
            throw new Exception("Le contenu HTML est vide");
        }
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        
        // Rendu avec gestion d'erreurs
        $dompdf->render();
        
        // Vérifier si le rendu a réussi
        if ($dompdf->getCanvas() === null) {
            throw new Exception("Erreur lors du rendu PDF");
        }
        
        // Envoyer le PDF
        $dompdf->stream('bon_commande_'.$idcommande.'.pdf', [
            'Attachment' => true,
            'compress' => true
        ]);
        
    } catch (Exception $e) {
        // Log de l'erreur pour débogage
        error_log("Erreur PDF commande: " . $e->getMessage());
        
        // Affichage d'erreur convivial
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Erreur</title></head><body>";
        echo "<h2>Erreur lors de la génération du PDF</h2>";
        echo "<p>Une erreur s'est produite lors de la génération du document PDF.</p>";
        echo "<p>Veuillez réessayer ou contacter l'administrateur.</p>";
        echo "<p><a href='javascript:history.back()'>Retour</a></p>";
        echo "</body></html>";
    }
    exit;
}

// Affichage normal ou impression navigateur
?>
<?= render_commande_html($idcommande, $fournisseur, $datecommande, $liste_article, $entreprise, $logo_path); ?>
<?php if (!$is_pdf): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    if (window.location.search.indexOf('action=print') !== -1) {
        window.print();
        window.onafterprint = function() {
            window.location.href = 'liste_commande.php';
        };
        // Pour compatibilité certains navigateurs : fallback timeout
        setTimeout(function() {
            window.location.href = 'liste_commande.php';
        }, 2000);
    }
});
</script>
<?php endif; ?>