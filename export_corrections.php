<?php
include('db/connecting.php');
session_start();
if (!isset($_SESSION['nom_utilisateur'])) {
    header('location: connexion.php');
    exit();
}

// Récupération des filtres
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$motif_id = isset($_GET['motif']) ? $_GET['motif'] : '';
$numero_correction = isset($_GET['numero']) ? $_GET['numero'] : '';
$utilisateur = isset($_GET['utilisateur']) ? trim($_GET['utilisateur']) : '';
$article = isset($_GET['article']) ? trim($_GET['article']) : '';
$montant = isset($_GET['montant']) ? floatval($_GET['montant']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

$sql = "SELECT c.*, 
        a.libelle as article_libelle,
        m.LibelleMotifMouvementStock as motif_libelle,
        u1.NomPrenom as operateur_modifiant,
        u2.NomPrenom as operateur_creant,
        s.StockActuel
        FROM correction c
        LEFT JOIN stock s ON c.IDSTOCK = s.IDSTOCK
        LEFT JOIN article a ON s.IDARTICLE = a.IDARTICLE
        LEFT JOIN motif_correction m ON c.IDMOTIF_MOUVEMENT_STOCK = m.IDMOTIF_MOUVEMENT_STOCK
        LEFT JOIN utilisateur u1 ON c.ID_utilisateurs = u1.IDUTILISATEUR
        LEFT JOIN utilisateur u2 ON c.UtilCrea = u2.IDUTILISATEUR
        WHERE 1=1";
$params = [];
if ($date_debut) {
    $sql .= " AND c.DateMouvementStock >= ?";
    $params[] = $date_debut;
}
if ($date_fin) {
    $sql .= " AND c.DateMouvementStock <= ?";
    $params[] = $date_fin;
}
if ($motif_id) {
    $sql .= " AND c.IDMOTIF_MOUVEMENT_STOCK = ?";
    $params[] = $motif_id;
}
if ($numero_correction) {
    $sql .= " AND c.NumeroCorrection LIKE ?";
    $params[] = "%$numero_correction%";
}
if ($utilisateur) {
    $sql .= " AND (u1.NomPrenom LIKE ? OR u1.IDUTILISATEUR = ?)";
    $params[] = "%$utilisateur%";
    $params[] = $utilisateur;
}
if ($article) {
    $sql .= " AND (a.libelle LIKE ? OR a.CodePersoArticle LIKE ?)";
    $params[] = "%$article%";
    $params[] = "%$article%";
}
if ($montant > 0) {
    $sql .= " AND (c.ValeurCorrection >= ?)";
    $params[] = $montant;
}
$sql .= " ORDER BY c.DateMouvementStock DESC";

$stmt = $cnx->prepare($sql);
$stmt->execute($params);
$corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux
$total_corrections = count($corrections);
$total_valeur = 0;
$total_positives = 0;
$total_negatives = 0;

foreach ($corrections as $correction) {
    $total_valeur += $correction['ValeurCorrection'] ?? 0;
    if (($correction['QuantiteMoved'] ?? 0) > 0) {
        $total_positives++;
    } else {
        $total_negatives++;
    }
}

$filename = "corrections_export_" . date('Ymd_His');

switch ($format) {
    case 'excel':
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.xls');
        
        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="UTF-8">';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo '.header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #fd7e14; padding-bottom: 15px; }';
        echo '.header h1 { color: #fd7e14; font-size: 24px; margin: 0; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
        echo 'th { background: #fd7e14; color: white; padding: 12px 8px; text-align: center; font-weight: bold; }';
        echo 'td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; }';
        echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
        echo '.positive { color: #28a745; font-weight: bold; }';
        echo '.negative { color: #dc3545; font-weight: bold; }';
        echo '</style></head><body>';
        
        echo '<div class="header"><h1>🔧 CORRECTIONS DE STOCK</h1>';
        echo '<p>Date : ' . date('d/m/Y à H:i') . ' | Total : ' . $total_corrections . ' corrections</p></div>';
        
        echo '<table><thead><tr>';
        echo '<th>Date</th><th>N° Correction</th><th>Article</th><th>Motif</th><th>Opérateur</th>';
        echo '<th>Quantité</th><th>Stock Final</th><th>Valeur</th><th>Créé par</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($corrections as $correction) {
            echo '<tr>';
            echo '<td>' . date('d/m/Y H:i', strtotime($correction['DateMouvementStock'])) . '</td>';
            echo '<td>' . htmlspecialchars($correction['NumeroCorrection']) . '</td>';
            echo '<td>' . htmlspecialchars($correction['article_libelle'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($correction['motif_libelle'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($correction['operateur_modifiant'] ?? 'N/A') . '</td>';
            $quantite = $correction['QuantiteMoved'] ?? 0;
            $class = $quantite >= 0 ? 'positive' : 'negative';
            $symbol = $quantite >= 0 ? '+' : '';
            echo '<td class="' . $class . '">' . $symbol . $quantite . '</td>';
            echo '<td>' . ($correction['StockActuel'] ?? 'N/A') . '</td>';
            echo '<td>' . number_format($correction['ValeurCorrection'] ?? 0, 0, ',', ' ') . ' F.CFA</td>';
            echo '<td>' . htmlspecialchars($correction['operateur_creant'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        break;
        
    case 'word':
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename=' . $filename . '.doc');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
        echo '<h1 style="text-align:center; color:#fd7e14;">🔧 CORRECTIONS DE STOCK</h1>';
        echo '<p style="text-align:center;">Date : ' . date('d/m/Y à H:i') . ' | Total : ' . $total_corrections . ' corrections</p>';
        echo '<table border="1" style="width:100%; border-collapse: collapse;">';
        echo '<tr style="background-color:#fd7e14; color:white;">';
        echo '<th>Date</th><th>N° Correction</th><th>Article</th><th>Motif</th><th>Opérateur</th>';
        echo '<th>Quantité</th><th>Stock Final</th><th>Valeur</th><th>Créé par</th></tr>';
        foreach ($corrections as $correction) {
            echo '<tr>';
            echo '<td>' . date('d/m/Y H:i', strtotime($correction['DateMouvementStock'])) . '</td>';
            echo '<td>' . htmlspecialchars($correction['NumeroCorrection']) . '</td>';
            echo '<td>' . htmlspecialchars($correction['article_libelle'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($correction['motif_libelle'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($correction['operateur_modifiant'] ?? 'N/A') . '</td>';
            $quantite = $correction['QuantiteMoved'] ?? 0;
            $symbol = $quantite >= 0 ? '+' : '';
            echo '<td>' . $symbol . $quantite . '</td>';
            echo '<td>' . ($correction['StockActuel'] ?? 'N/A') . '</td>';
            echo '<td>' . number_format($correction['ValeurCorrection'] ?? 0, 0, ',', ' ') . ' F.CFA</td>';
            echo '<td>' . htmlspecialchars($correction['operateur_creant'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        break;
        
    case 'txt':
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.txt');
        echo str_repeat("=", 80) . "\r\n";
        echo "                    🔧 CORRECTIONS DE STOCK                    \r\n";
        echo str_repeat("=", 80) . "\r\n";
        echo "Date : " . date('d/m/Y à H:i') . " | Total : " . $total_corrections . " corrections\r\n";
        echo str_repeat("-", 80) . "\r\n";
        foreach ($corrections as $correction) {
            $quantite = $correction['QuantiteMoved'] ?? 0;
            $symbol = $quantite >= 0 ? '+' : '';
            echo date('d/m/Y', strtotime($correction['DateMouvementStock'])) . " | ";
            echo $correction['NumeroCorrection'] . " | ";
            echo substr($correction['article_libelle'] ?? 'N/A', 0, 20) . " | ";
            echo $symbol . $quantite . " | ";
            echo number_format($correction['ValeurCorrection'] ?? 0, 0, ',', ' ') . " F.CFA\r\n";
        }
        echo str_repeat("=", 80) . "\r\n";
        break;
        
    default: // csv
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.csv');
        echo "\xEF\xBB\xBF"; // BOM UTF-8
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'N° Correction', 'Article', 'Motif', 'Opérateur', 'Quantité', 'Stock Final', 'Valeur correction', 'Créé par'], ';');
        foreach ($corrections as $correction) {
            fputcsv($out, [
                $correction['DateMouvementStock'],
                $correction['NumeroCorrection'],
                $correction['article_libelle'] ?? 'N/A',
                $correction['motif_libelle'] ?? 'N/A',
                $correction['operateur_modifiant'] ?? 'N/A',
                ($correction['QuantiteMoved'] >= 0 ? '+' : '') . $correction['QuantiteMoved'],
                $correction['StockActuel'] ?? 'N/A',
                number_format($correction['ValeurCorrection'] ?? 0, 2, ',', ''),
                $correction['operateur_creant'] ?? 'N/A',
            ], ';');
        }
        fclose($out);
        break;
}
exit; 