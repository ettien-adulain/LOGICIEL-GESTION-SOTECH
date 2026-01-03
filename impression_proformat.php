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

// Récupération des données avec gestion d'erreurs
try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id || $id <= 0) {
        throw new Exception("ID proforma manquant ou invalide");
    }

    $proformat = verifier_element('proforma', ['IDPROFORMA'], [$id], '');
    if (!$proformat) {
        throw new Exception("Proforma introuvable avec l'ID : " . $id);
    }
    
    $dateproformat = $proformat['DateIns'];
    $dateproformat_valide = $proformat['date_validite'];
    $liste_article = verifier_element_tous('proformaligne', ['IDPROFORMA'], [$id], '');
    
    if (empty($liste_article)) {
        throw new Exception("Aucun article trouvé pour ce proforma");
    }
    
    $entreprise = $cnx->query("SELECT * FROM entreprise WHERE id = 1")->fetch();
    if (!$entreprise) {
        throw new Exception("Informations de l'entreprise introuvables");
    }
    
} catch (Exception $e) {
    error_log("Erreur impression_proformat: " . $e->getMessage());
    http_response_code(500);
    echo "Erreur : " . $e->getMessage();
    exit;
}

    $action = isset($_GET['action']) ? $_GET['action'] : '';
$is_pdf = ($action === 'download');

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

function render_facture_html($id, $proformat, $dateproformat, $dateproformat_valide, $liste_article, $entreprise,$logo_path) {
    ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
        <title>Facture Proforma</title>
    <style>
            @page {
                margin: 40px 30px 90px 30px;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #333;
                background: #f7f7f7;
            }
            .container-facture {
                background: #fff;
                margin: 30px auto 0 auto;
                padding: 30px 30px 0 30px;
                max-width: 900px;
            }
            .header-facture {
                display: table;
                width: 100%;
                margin-bottom: 20px;
            }
            .header-facture .logo {
                display: table-cell;
                vertical-align: top;
                width: 120px;
                max-width: 110px;
                max-height: 110px;
            }
            .header-facture .infos {
                display: table-cell;
                text-align: right;
                vertical-align: top;
                padding-left: 30px;
            }
            .header-facture h1 {
                color: #b71c1c;
                font-size: 2.2rem;
                margin-bottom: 10px;
            }
            .section {
                display: table;
                width: 100%;
                margin-bottom: 20px;
            }
            .seller, .buyer {
                display: table-cell;
                background: #f7f7f7;
                padding: 10px 18px;
                width: 48%;
                vertical-align: top;
            }
            .seller h3, .buyer h3 {
                color: #b71c1c;
                font-size: 1.1rem;
                margin-bottom: 6px;
            }
            .product-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                background: #fff;
            }
            .product-table th, .product-table td {
                border: 1px solid #ccc;
                padding: 4px 2px;
                font-size: 10px;
                text-align: center;
            }
            .product-table th {
                background: #b71c1c;
                color: #fff;
                font-size: 10.5px;
            }
            .totals {
                margin-top: 20px;
                background: #f7f7f7;
                padding: 15px 25px;
                max-width: 400px;
                float: right;
                font-size: 1.1em;
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
                height: 70px;
                background: #fff;
                color: #333;
                border-top: 2px solid #b71c1c;
                font-size: 6px;
                line-height: 1.3;
                padding: 10px 0 5px 0;
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
                font-size: 7px;
                margin-top: 6px;
            }
            @media print {
        body {
                    background: #fff;
        }
                .container-facture {
            margin: 0;
                }
        }
    </style>
    <!-- Système de thème sombre/clair -->
</head>
    <body>
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
                    <h1>Facture Proforma</h1>
                    <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($dateproformat)) ?></p>
                    <p><strong>Proforma N° :</strong> <?= $id ?></p>
                    <p><strong>Valable jusqu'au :</strong> <?= $dateproformat_valide ?></p>
                </div>
            </div>
            <div class="section">
                <div class="seller">
                    <h3>Vendeur :</h3>
                    <p><strong><?= isset($_SESSION['nom_complet']) && !empty($_SESSION['nom_complet']) ? $_SESSION['nom_complet'] : '' ?></strong></p>

                </div>
                <div class="buyer">
                    <h3>À l'attention de :</h3>
                    <p><strong><?= htmlspecialchars($proformat['ClientProforma']) ?></strong></p>
                    <p>Téléphone : <?= htmlspecialchars($proformat['ContactClientProforma']) ?></p>
                    <p>E-mail : <?= htmlspecialchars($proformat['email']) ?></p>
                </div>
            </div>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Nom du Produit/Description</th>
                        <th>Prix Unitaire</th>
                        <th>Qté</th>
                        <th>Total Sans Remise</th>
                        <th>Remise</th>
                        <th>Total Avec Remise</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalPanieravec = 0;
                    $totalPaniersans = 0;
                    $remises = 0;
                            foreach ($liste_article as $article) {
                                $id_article = $article['IDARTICLE'];
                                $nom = verifier_element('article', ['IDARTICLE'], [$id_article], '');
                                $libelle = $nom['libelle'];
                                $prix = floatval($article['MontantProduitTTC']);
                                $quantite = $article['Quantite'];
                                $remise = floatval($article['MontantRemise']);
                                $totalavec = ($prix * $quantite) * (1 - $remise / 100);
                                $totalsans = ($prix * $quantite);
                                $totalPanieravec += $totalavec;
                                $totalPaniersans += $totalsans;
                                $remises += $remise;
                    ?>
                    <tr>
                        <td style="text-align:left;"><?= htmlspecialchars($libelle) ?></td>
                        <td><?= number_format($prix, 0, ',', ' ') ?> F.CFA</td>
                        <td><?= $quantite ?></td>
                        <td><?= number_format($totalsans, 0, ',', ' ') ?> F.CFA</td>
                        <td style="color:#b71c1c; font-weight:bold;"><?= number_format($remise) ?>%</td>
                        <td style="font-weight:bold;"><?= number_format($totalavec, 0, ',', ' ') ?> F.CFA</td>
                                </tr>
                    <?php } ?>
                </tbody>
            </table>
            <div class="totals">
                <p>Total sans remise : <span id="Totalsans" style="font-weight:bold; color:#333;"><?= number_format($totalPaniersans, 0, ',', ' ') ?> F.CFA</span></p>
                <p>Remise totale : <span id="Remise" style="font-weight:bold; color:#b71c1c;"><?= number_format($remises) ?>%</span></p>
                <p><strong>Total avec remise : <span><?= number_format($totalPanieravec, 0, ',', ' ') ?> F.CFA</span></strong></p>
                <p><strong>Mode de règlement :
                        <span style="color:#333;">
                            <?php
                            $ModeReglement = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$proformat['ConditionReglement']], '');
                            echo htmlspecialchars($ModeReglement['ModeReglement']);
                            ?>
                        </span>
                </strong></p>
            </div>
            <div class="clear"></div>
                    </div>
        <footer>
            <div class="footer-flex">
                <div>
                    <p><strong><?= $entreprise['NCC'] ?></strong></p>
                    <p><?= $entreprise['RCCM'] ?></p>
                    <p><?= $entreprise['NUMERO'] ?></p>
                    <p><?= $entreprise['adresse'] ?></p>
                </div>
                <div>
                    <p><strong><?= $entreprise['NomBanque'] ?></strong></p>
                    <p><?= $entreprise['NumeroCompte'] ?></p>
                    <p><?= $entreprise['IBAN'] ?></p>
                    <p><?= $entreprise['Code_SWIFT'] ?></p>
                </div>
          </div>
            <div class="footer-note">
                <em>Merci pour votre confiance - Document généré le <?= date('d/m/Y H:i') ?></em>
            </div>
        </footer>
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
        
        $html = render_facture_html($id, $proformat, $dateproformat, $dateproformat_valide, $liste_article, $entreprise, $logo_path);
        
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
        $dompdf->stream('proforma_'.$id.'.pdf', [
            'Attachment' => true,
            'compress' => true
        ]);
        
    } catch (Exception $e) {
        // Log de l'erreur pour débogage
        error_log("Erreur PDF: " . $e->getMessage());
        
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
<?= render_facture_html($id, $proformat, $dateproformat, $dateproformat_valide, $liste_article, $entreprise,$logo_path); ?>
<?php if (!$is_pdf): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    if (window.location.search.indexOf('action=print') !== -1) {
        window.print();
        window.onafterprint = function() {
            window.location.href = 'liste_proforma.php';
        };
        // Pour compatibilité certains navigateurs : fallback timeout
        setTimeout(function() {
            window.location.href = 'liste_proforma.php';
        }, 2000);
    }
});
</script>
<?php endif; ?>