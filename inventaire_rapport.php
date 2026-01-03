<?php
require_once 'vendor/autoload.php';
include('db/connecting.php');

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['nom_utilisateur'])) {
    die('Accès refusé');
}

$idInventaire = isset($_GET['IDINVENTAIRE']) ? intval($_GET['IDINVENTAIRE']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'achat_vente';
$categorieFiltre = isset($_GET['categorie']) ? $_GET['categorie'] : '';
if ($idInventaire <= 0) {
    die('ID inventaire invalide');
}

// Récupération des infos inventaire
$inventaire = $cnx->query("SELECT * FROM inventaire WHERE IDINVENTAIRE = $idInventaire")->fetch(PDO::FETCH_ASSOC);
if (!$inventaire) die('Inventaire non trouvé');

// Récupération des lignes
$whereCat = '';
$params = [$idInventaire];
if ($type === 'categorie' && $categorieFiltre) {
    $whereCat = ' AND il.categorie = ?';
    $params[] = $categorieFiltre;
}
$stmt = $cnx->prepare("
    SELECT il.*, a.CodePersoArticle, a.libelle, a.PrixAchatHT, a.PrixVenteTTC
    FROM inventaire_ligne il
    LEFT JOIN article a ON il.id_article = a.IDARTICLE
    WHERE il.id_inventaire = ? $whereCat
    ORDER BY il.categorie, il.code_article
");
$stmt->execute($params);
$lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculs globaux
$nb_articles = count($lignes);
$nb_ecarts = 0;
$valeurs = [
    'achat_theorique' => 0,
    'achat_physique' => 0,
    'achat_ecart' => 0,
    'vente_theorique' => 0,
    'vente_physique' => 0,
    'vente_ecart' => 0,
];
foreach ($lignes as $l) {
    $nb_ecarts += ($l['ecart'] ?? 0) != 0 ? 1 : 0;
    $valeurs['achat_theorique'] += $l['qte_theorique'] * $l['PrixAchatHT'];
    $valeurs['achat_physique'] += ($l['qte_physique'] ?? 0) * $l['PrixAchatHT'];
    $valeurs['achat_ecart'] += (($l['ecart'] ?? 0) * $l['PrixAchatHT']);
    $valeurs['vente_theorique'] += $l['qte_theorique'] * $l['PrixVenteTTC'];
    $valeurs['vente_physique'] += ($l['qte_physique'] ?? 0) * $l['PrixVenteTTC'];
    $valeurs['vente_ecart'] += (($l['ecart'] ?? 0) * $l['PrixVenteTTC']);
}

// Détail par catégorie
$categories = [];
if ($type !== 'categorie') {
    $catStmt = $cnx->prepare("
        SELECT il.categorie,
            SUM(il.qte_theorique * COALESCE(a.PrixAchatHT,0)) as achat_theorique,
            SUM(COALESCE(il.qte_physique,0) * COALESCE(a.PrixAchatHT,0)) as achat_physique,
            SUM((il.qte_theorique-COALESCE(il.qte_physique,0)) * COALESCE(a.PrixAchatHT,0)) as achat_ecart,
            SUM(il.qte_theorique * COALESCE(a.PrixVenteTTC,0)) as vente_theorique,
            SUM(COALESCE(il.qte_physique,0) * COALESCE(a.PrixVenteTTC,0)) as vente_physique,
            SUM((il.qte_theorique-COALESCE(il.qte_physique,0)) * COALESCE(a.PrixVenteTTC,0)) as vente_ecart
        FROM inventaire_ligne il
        LEFT JOIN article a ON il.id_article = a.IDARTICLE
        WHERE il.id_inventaire = ?
        GROUP BY il.categorie
        ORDER BY il.categorie
    ");
    $catStmt->execute([$idInventaire]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} else if ($type === 'categorie' && $categorieFiltre) {
    $catStmt = $cnx->prepare("
        SELECT il.categorie,
            SUM(il.qte_theorique * COALESCE(a.PrixAchatHT,0)) as achat_theorique,
            SUM(COALESCE(il.qte_physique,0) * COALESCE(a.PrixAchatHT,0)) as achat_physique,
            SUM((il.qte_theorique-COALESCE(il.qte_physique,0)) * COALESCE(a.PrixAchatHT,0)) as achat_ecart
        FROM inventaire_ligne il
        LEFT JOIN article a ON il.id_article = a.IDARTICLE
        WHERE il.id_inventaire = ? AND il.categorie = ?
        GROUP BY il.categorie
    ");
    $catStmt->execute([$idInventaire, $categorieFiltre]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statut
$statut = ($inventaire['StatutInventaire'] == 'valide') ? 'Validé' : 'Brouillon';
$date = date('d/m/Y H:i', strtotime($inventaire['DateInventaire']));

// HTML du rapport
$html = '<html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; background: #f8f9fa; }
.header { text-align: center; margin-bottom: 18px; }
.title { font-size: 22px; font-weight: bold; color: #222; letter-spacing: 1px; }
.subtitle { font-size: 14px; color: #555; margin-bottom: 2px; }
.table { width: 100%; border-collapse: collapse; margin-top: 18px; margin-bottom: 18px; }
.table th, .table td { border: 1px solid #aaa; padding: 7px 8px; font-size: 12px; }
.table th { background: #e9ecef; color: #222; }
.stats-table { margin: 0 auto 25px auto; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px #eee; background: #fff; }
.stats-table th { background: #212529; color: #fff; font-size: 13px; }
.stats-table td { background: #fff; }
.valid { color: #28a745; font-weight: bold; }
.draft { color: #dc3545; font-weight: bold; }
.signature { margin-top: 40px; }
.section-title { font-size: 16px; color: #212529; font-weight: bold; margin: 30px 0 10px 0; border-bottom: 2px solid #dc3545; display: inline-block; padding-bottom: 2px; }
</style></head><body>';
$html .= '<div class="header">
    <div class="title">RAPPORT D\'INVENTAIRE</div>
    <div class="subtitle">' . htmlspecialchars($inventaire['Commentaires']) . '</div>
    <div class="subtitle">Date : ' . $date . ' | Utilisateur : ' . htmlspecialchars($inventaire['CreePar']) . '</div>
    <div class="subtitle">Statut : <span class="' . ($statut=='Validé'?'valid':'draft') . '">' . $statut . '</span></div>
</div>';
// Tableau de synthèse global
$html .= '<table class="table stats-table">';
$html .= '<tr>';
$html .= '<th>Nombre d\'articles</th><th>Articles avec écart</th>';
if ($type === 'achat_vente') {
    $html .= '<th>Achat logiciel</th><th>Achat physique</th><th>Écart achat</th><th>Vente logiciel</th><th>Vente physique</th><th>Écart vente</th>';
} else {
    $html .= '<th>Achat logiciel</th><th>Achat physique</th><th>Écart achat</th>';
}
$html .= '</tr>';
$html .= '<tr>';
$html .= '<td align="center">' . $nb_articles . '</td>';
$html .= '<td align="center">' . $nb_ecarts . '</td>';
if ($type === 'achat_vente') {
    $html .= '<td align="right">' . number_format($valeurs['achat_theorique'], 0, ',', ' ') . ' F.CFA</td>';
    $html .= '<td align="right">' . number_format($valeurs['achat_physique'], 0, ',', ' ') . ' F.CFA</td>';
    $html .= '<td align="right">' . number_format($valeurs['achat_ecart'], 0, ',', ' ') . ' F.CFA</td>';
    $html .= '<td align="right">' . number_format($valeurs['vente_theorique'], 0, ',', ' ') . ' F.CFA</td>';
    $html .= '<td align="right">' . number_format($valeurs['vente_physique'], 0, ',', ' ') . ' F.CFA</td>';
    $html .= '<td align="right">' . number_format($valeurs['vente_ecart'], 0, ',', ' ') . ' F.CFA</td>';
} else {
    $html .= '<td align="right">' . number_format($valeurs['achat_theorique'], 0, ',', ' ') . ' F.CFA</td>';
    $html .= '<td align="right">' . number_format($valeurs['achat_physique'], 0, ',', ' ') . ' F.CFA</td>';
    $html .= '<td align="right">' . number_format($valeurs['achat_ecart'], 0, ',', ' ') . ' F.CFA</td>';
}
$html .= '</tr>';
$html .= '</table>';

// Section synthèse par catégorie
if (!empty($categories)) {
    $html .= '<div class="section-title">Synthèse par catégorie</div>';
    $html .= '<table class="table">';
    $html .= '<tr>';
    $html .= '<th>Catégorie</th><th>Achat logiciel</th><th>Achat physique</th><th>Écart achat</th>';
    if ($type === 'achat_vente') {
        $html .= '<th>Vente logiciel</th><th>Vente physique</th><th>Écart vente</th>';
    }
    $html .= '</tr>';
    foreach ($categories as $cat) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($cat['categorie']) . '</td>';
        $html .= '<td align="right">' . number_format($cat['achat_theorique'], 0, ',', ' ') . ' F.CFA</td>';
        $html .= '<td align="right">' . number_format($cat['achat_physique'], 0, ',', ' ') . ' F.CFA</td>';
        $html .= '<td align="right">' . number_format($cat['achat_ecart'], 0, ',', ' ') . ' F.CFA</td>';
        if ($type === 'achat_vente') {
            $html .= '<td align="right">' . number_format($cat['vente_theorique'], 0, ',', ' ') . ' F.CFA</td>';
            $html .= '<td align="right">' . number_format($cat['vente_physique'], 0, ',', ' ') . ' F.CFA</td>';
            $html .= '<td align="right">' . number_format($cat['vente_ecart'], 0, ',', ' ') . ' F.CFA</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
}

$html .= '<div class="signature"><br><br>Signature responsable : ____________________________</div>';
$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$filename = 'Rapport_Inventaire_' . $idInventaire . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit; 