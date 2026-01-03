<?php
session_start();
include('db/connecting.php');

// Vérifier si DOMPDF est installé via Composer
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once($autoloadPath);
    
    // Vérifier si les classes DOMPDF sont disponibles
    if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Options')) {
        // Les classes sont disponibles, on peut les utiliser directement
    } else {
        die("Erreur : DOMPDF n'est pas correctement installé. Exécutez : composer require dompdf/dompdf");
    }
} else {
    die("Erreur : Composer autoloader non trouvé. Vérifiez que Composer est installé.");
}

if (!isset($_SESSION['nom_utilisateur'])) {
    header('Location: ../connexion.php');
    exit();
}

$idInventaire = isset($_GET['IDINVENTAIRE']) ? intval($_GET['IDINVENTAIRE']) : 0;
$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$imprimer_ecarts = isset($_GET['ecarts']) ? $_GET['ecarts'] == '1' : false;

if ($idInventaire <= 0) {
    die("ID d'inventaire invalide");
}

// Récupération des informations de l'inventaire
$inventaire = $cnx->query("
    SELECT * FROM inventaire WHERE IDINVENTAIRE = $idInventaire
")->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    die("Inventaire non trouvé");
}

// Récupération des informations de l'entreprise depuis la base de données
$entreprise = $cnx->query("
    SELECT * FROM entreprise LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// Si pas d'entreprise en base, utiliser des valeurs par défaut
if (!$entreprise) {
    $entreprise = [
        'nom' => 'ENTREPRISE',
        'adresse' => 'Adresse de l\'entreprise',
        'telephone' => 'Téléphone',
        'Email' => 'email@entreprise.com'
    ];
}

// Construction de la requête selon les filtres
$where = "il.id_inventaire = $idInventaire";
if (!empty($categorie)) {
    $categorie_escaped = $cnx->quote($categorie);
    $where .= " AND il.categorie = $categorie_escaped";
}
if ($imprimer_ecarts) {
    $where .= " AND (il.ecart != 0 OR il.ecart IS NULL)";
}

// Récupération des lignes d'inventaire
$lignes = $cnx->query("
    SELECT 
        il.*,
        a.PrixAchatHT,
        a.PrixVenteTTC,
        s.StockActuel as stock_actuel
    FROM inventaire_ligne il 
    LEFT JOIN article a ON il.id_article = a.IDARTICLE 
    LEFT JOIN stock s ON il.id_article = s.IDARTICLE
    WHERE $where 
    ORDER BY il.categorie, il.code_article
")->fetchAll(PDO::FETCH_ASSOC);

// Récupération des numéros de série trouvés pour chaque article
foreach ($lignes as &$ligne) {
    $stmt = $cnx->prepare("
        SELECT its.numero_serie 
        FROM inventaire_temp it
        JOIN inventaire_temp_series its ON it.id = its.id_inventaire_temp
        WHERE it.id_inventaire = ? AND it.id_article = ? AND it.id_utilisateur = ?
        ORDER BY its.numero_serie
    ");
    $stmt->execute([$idInventaire, $ligne['id_article'], $_SESSION['id_utilisateur']]);
    $ligne['series_trouves'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
unset($ligne);

// Groupement par catégorie
$categories = [];
foreach ($lignes as $ligne) {
    $cat = $ligne['categorie'];
    if (!isset($categories[$cat])) {
        $categories[$cat] = [];
    }
    $categories[$cat][] = $ligne;
}

// Génération du HTML pour l'impression
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>FICHE D\'INVENTAIRE - ' . htmlspecialchars($inventaire['Commentaires']) . '</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        .page-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .entreprise-info {
            margin-bottom: 15px;
        }
        .entreprise-nom {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .entreprise-details {
            font-size: 9pt;
            color: #666;
        }
        .inventaire-info {
            margin-bottom: 20px;
            text-align: center;
        }
        .inventaire-titre {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .inventaire-details {
            font-size: 10pt;
            color: #333;
        }
        .categorie-header {
            background-color: #f0f0f0;
            padding: 8px;
            margin: 20px 0 10px 0;
            border-left: 4px solid #007bff;
            font-weight: bold;
            font-size: 12pt;
        }
        .table-articles {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table-articles th {
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            padding: 6px;
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
        }
        .table-articles td {
            border: 1px solid #dee2e6;
            padding: 4px 6px;
            font-size: 9pt;
            vertical-align: top;
        }
        .code-article {
            font-weight: bold;
            font-family: monospace;
        }
        .libelle-article {
            text-align: left;
        }
        .prix-vente {
            text-align: right;
            font-family: monospace;
        }
        .qte-comptee {
            text-align: center;
            font-weight: bold;
        }
        .series-cell {
            min-height: 20px;
            font-family: monospace;
            font-size: 8pt;
        }
        .page-break {
            page-break-before: always;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        .ecart-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 5px;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        .ecart-positif {
            color: #28a745;
            font-weight: bold;
        }
        .ecart-negatif {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>';

// En-tête de l'entreprise et de l'inventaire
$html .= '
<div class="page-header">
    <div class="entreprise-info">
        <div class="entreprise-nom">' . htmlspecialchars($entreprise['nom']) . '</div>
        <div class="entreprise-details">
            ' . htmlspecialchars($entreprise['adresse']) . ' | 
            Tél: ' . htmlspecialchars($entreprise['telephone']) . ' | 
            Email: ' . htmlspecialchars($entreprise['Email']) . '
        </div>
    </div>
    <div class="inventaire-info">
        <div class="inventaire-titre">FICHE D\'INVENTAIRE</div>
        <div class="inventaire-details">
            ' . htmlspecialchars($inventaire['Commentaires']) . '<br>
            Date: ' . date('d/m/Y', strtotime($inventaire['DateInventaire'])) . ' | 
            Créé par: ' . htmlspecialchars($inventaire['CreePar']) . '
        </div>';

if ($imprimer_ecarts) {
    $html .= '
        <div class="ecart-info">
            <strong>⚠️ IMPRESSION DES ÉCARTS SEULEMENT</strong><br>
            Cette fiche contient uniquement les articles avec des écarts pour un deuxième comptage de vérification.
        </div>';
}

$html .= '
    </div>
</div>';

// Génération des fiches par catégorie
$pageCount = 0;
foreach ($categories as $nomCategorie => $articles) {
    if ($pageCount > 0) {
        $html .= '<div class="page-break"></div>';
    }
    
    $html .= '
    <div class="categorie-header">
        Catégorie: ' . htmlspecialchars($nomCategorie) . '
    </div>
    
    <table class="table-articles">
        <thead>
            <tr>
                <th style="width: 15%;">Code Article</th>
                <th style="width: 35%;">Libellé Article</th>
                <th style="width: 12%;">Prix Vente</th>
                <th style="width: 10%;">Qté Théorique</th>
                <th style="width: 10%;">Qté Comptée</th>
                <th style="width: 18%;">Numéros de Série Trouvés</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($articles as $article) {
        $ecart = $article['ecart'] ?? 0;
        $ecartClass = $ecart > 0 ? 'ecart-negatif' : ($ecart < 0 ? 'ecart-positif' : '');
        $ecartText = $ecart > 0 ? '+' . $ecart : ($ecart < 0 ? $ecart : '0');
        
        $html .= '
        <tr>
            <td class="code-article">' . htmlspecialchars($article['code_article']) . '</td>
            <td class="libelle-article">' . htmlspecialchars($article['designation']) . '</td>
            <td class="prix-vente">' . number_format($article['PrixVenteTTC'], 0, ',', ' ') . ' F.CFA</td>
            <td class="qte-comptee">' . $article['qte_theorique'] . '</td>
            <td class="qte-comptee ' . $ecartClass . '">' . ($article['qte_physique'] ?? 0) . '</td>
            <td class="series-cell">';
        
        // Affichage des numéros de série trouvés
        if (!empty($article['series_trouves'])) {
            $html .= implode('<br>', array_map('htmlspecialchars', $article['series_trouves']));
        } else {
            $html .= '<em>Aucun numéro saisi</em>';
        }
        
        $html .= '
            </td>
        </tr>';
    }
    
    $html .= '
        </tbody>
    </table>
    
    <div class="footer">
        Page générée le ' . date('d/m/Y H:i') . ' par ' . htmlspecialchars($_SESSION['nom_utilisateur']) . ' | 
        Catégorie: ' . htmlspecialchars($nomCategorie) . ' | 
        Articles: ' . count($articles) . '
    </div>';
    
    $pageCount++;
}

$html .= '
</body>
</html>';

// Configuration de DOMPDF
$options = new \Dompdf\Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Génération du nom de fichier
$filename = 'Inventaire_' . $idInventaire . '_' . date('Y-m-d_H-i-s');
if (!empty($categorie)) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $categorie);
}
if ($imprimer_ecarts) {
    $filename .= '_ECARTS';
}
$filename .= '.pdf';

// Envoi du PDF
$dompdf->stream($filename, array('Attachment' => false));
?> 