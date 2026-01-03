<?php
session_start();
require_once 'db/connecting.php';

// Vérification de la connexion
if (!isset($_SESSION['nom_utilisateur'])) {
    header('Location: connexion.php');
    exit();
}

// Récupération des paramètres de filtrage
$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';
$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$stock_min = isset($_GET['stock_min']) ? $_GET['stock_min'] : '';
$stock_max = isset($_GET['stock_max']) ? $_GET['stock_max'] : '';
$tri = isset($_GET['tri']) ? $_GET['tri'] : 'stock_desc';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Construction de la requête SQL
$sql = "SELECT a.libelle, 
        a.descriptif, 
        c.nom_categorie, 
        s.StockActuel, 
        a.PrixAchatHT, 
        a.PrixVenteTTC,
        (s.StockActuel * a.PrixAchatHT) as valeur_stock_achat,
        (s.StockActuel * a.PrixVenteTTC) as valeur_stock_vente
        FROM stock s 
        JOIN article a ON s.IDARTICLE = a.IDARTICLE 
        JOIN categorie_article c ON a.id_categorie = c.id_categorie 
        WHERE 1=1";

$params = [];

if (!empty($recherche)) {
    $sql .= " AND (a.libelle LIKE ? OR a.descriptif LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

if (!empty($categorie)) {
    $sql .= " AND a.IDCATEGORIE = ?";
    $params[] = $categorie;
}

if (!empty($stock_min)) {
    $sql .= " AND s.StockActuel >= ?";
    $params[] = $stock_min;
}

if (!empty($stock_max)) {
    $sql .= " AND s.StockActuel <= ?";
    $params[] = $stock_max;
}

// Tri
switch ($tri) {
    case 'stock_asc':
        $sql .= " ORDER BY s.StockActuel ASC";
        break;
    case 'nom_asc':
        $sql .= " ORDER BY a.libelle ASC";
        break;
    case 'nom_desc':
        $sql .= " ORDER BY a.libelle DESC";
        break;
    case 'prix_asc':
        $sql .= " ORDER BY a.PrixVenteTTC ASC";
        break;
    case 'prix_desc':
        $sql .= " ORDER BY a.PrixVenteTTC DESC";
        break;
    default:
        $sql .= " ORDER BY s.StockActuel DESC";
}

// Préparation des données
$stmt = $cnx->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux
$total_stock = 0;
$total_valeur_achat = 0;
$total_valeur_vente = 0;

foreach ($data as $row) {
    $total_stock += $row['StockActuel'];
    $total_valeur_achat += $row['valeur_stock_achat'];
    $total_valeur_vente += $row['valeur_stock_vente'];
}

// Configuration des en-têtes selon le format
switch ($format) {
    case 'excel':
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="Inventaire_Stock_' . date('Y-m-d_H-i') . '.xls"');
        break;
    case 'word':
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        header('Content-Disposition: attachment; filename="Inventaire_Stock_' . date('Y-m-d_H-i') . '.doc"');
        break;
    case 'txt':
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="Inventaire_Stock_' . date('Y-m-d_H-i') . '.txt"');
        break;
    default: // csv
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="Inventaire_Stock_' . date('Y-m-d_H-i') . '.csv"');
}

// Création du contenu selon le format
switch ($format) {
    case 'excel':
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Inventaire_Stock_' . date('Y-m-d_H-i') . '.xls"');
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo '.header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #28a745; padding-bottom: 15px; }';
        echo '.header h1 { color: #28a745; font-size: 24px; margin: 0; }';
        echo '.header p { color: #666; font-size: 14px; margin: 5px 0; }';
        echo '.summary { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px; }';
        echo '.summary h3 { color: #28a745; margin-top: 0; }';
        echo '.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }';
        echo '.summary-item { text-align: center; }';
        echo '.summary-value { font-size: 18px; font-weight: bold; color: #28a745; }';
        echo '.summary-label { font-size: 12px; color: #666; margin-top: 5px; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }';
        echo 'th { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 12px 8px; text-align: center; font-weight: bold; border: 1px solid #1e7e34; }';
        echo 'td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; }';
        echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
        echo 'tr:hover { background-color: #e8f5e8; }';
        echo '.total-row { background: linear-gradient(135deg, #dc3545, #c82333) !important; color: white; font-weight: bold; }';
        echo '.total-row td { border: 1px solid #bd2130; }';
        echo '.currency { font-weight: bold; color: #28a745; }';
        echo '.stock-low { background-color: #fff3cd; }';
        echo '.stock-zero { background-color: #f8d7da; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // En-tête élégant
        echo '<div class="header">';
        echo '<h1>📦 INVENTAIRE DU STOCK</h1>';
        echo '<p>Date d\'export : ' . date('d/m/Y à H:i') . '</p>';
        echo '<p>Nombre d\'articles : ' . count($data) . '</p>';
        echo '</div>';
        
        // Résumé des totaux
        echo '<div class="summary">';
        echo '<h3>📊 RÉSUMÉ INVENTAIRE</h3>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . $total_stock . ' unités</div>';
        echo '<div class="summary-label">Total Stock</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . number_format($total_valeur_achat, 0, ',', ' ') . ' F.CFA</div>';
        echo '<div class="summary-label">Valeur Achat</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . number_format($total_valeur_vente, 0, ',', ' ') . ' F.CFA</div>';
        echo '<div class="summary-label">Valeur Vente</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . number_format($total_valeur_vente - $total_valeur_achat, 0, ',', ' ') . ' F.CFA</div>';
        echo '<div class="summary-label">Marge Potentielle</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Tableau des articles
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>📦 Article</th>';
        echo '<th>📝 Référence</th>';
        echo '<th>🏷️ Catégorie</th>';
        echo '<th>📊 Stock</th>';
        echo '<th>💰 Prix Achat HT</th>';
        echo '<th>💵 Prix Vente TTC</th>';
        echo '<th>📈 Valeur Stock (Achat)</th>';
        echo '<th>📈 Valeur Stock (Vente)</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($data as $row) {
            $stock_class = '';
            if ($row['StockActuel'] == 0) {
                $stock_class = 'stock-zero';
            } elseif ($row['StockActuel'] <= 5) {
                $stock_class = 'stock-low';
            }
            
            echo '<tr class="' . $stock_class . '">';
            echo '<td>' . htmlspecialchars($row['libelle']) . '</td>';
            echo '<td>' . htmlspecialchars($row['descriptif']) . '</td>';
            echo '<td>' . htmlspecialchars($row['nom_categorie']) . '</td>';
            echo '<td><strong>' . $row['StockActuel'] . '</strong></td>';
            echo '<td class="currency">' . number_format($row['PrixAchatHT'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($row['PrixVenteTTC'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($row['valeur_stock_achat'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($row['valeur_stock_vente'], 0, ',', ' ') . ' F.CFA</td>';
            echo '</tr>';
        }
        
        // Ligne de totaux
        echo '<tr class="total-row">';
        echo '<td colspan="3"><strong>TOTAL GÉNÉRAL</strong></td>';
        echo '<td><strong>' . $total_stock . ' unités</strong></td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td><strong>' . number_format($total_valeur_achat, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_valeur_vente, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</body></html>';
        break;
        
    case 'word':
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename="Inventaire_Stock_' . date('Y-m-d_H-i') . '.doc"');
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'body { font-family: "Segoe UI", Arial, sans-serif; margin: 20px; line-height: 1.6; }';
        echo '.header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #28a745; padding-bottom: 20px; }';
        echo '.header h1 { color: #28a745; font-size: 28px; margin: 0; font-weight: bold; }';
        echo '.header p { color: #666; font-size: 16px; margin: 8px 0; }';
        echo '.summary { background: #f8f9fa; border: 2px solid #28a745; border-radius: 10px; padding: 20px; margin-bottom: 25px; }';
        echo '.summary h3 { color: #28a745; margin-top: 0; font-size: 20px; text-align: center; }';
        echo '.summary-grid { display: table; width: 100%; }';
        echo '.summary-row { display: table-row; }';
        echo '.summary-item { display: table-cell; text-align: center; padding: 15px; width: 25%; }';
        echo '.summary-value { font-size: 20px; font-weight: bold; color: #28a745; display: block; }';
        echo '.summary-label { font-size: 14px; color: #666; margin-top: 8px; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }';
        echo 'th { background: #28a745; color: white; padding: 15px 10px; text-align: center; font-weight: bold; border: 2px solid #1e7e34; font-size: 14px; }';
        echo 'td { padding: 12px 10px; border: 1px solid #ddd; text-align: center; font-size: 13px; }';
        echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
        echo 'tr:hover { background-color: #e8f5e8; }';
        echo '.total-row { background: #dc3545 !important; color: white; font-weight: bold; }';
        echo '.total-row td { border: 2px solid #bd2130; font-size: 14px; }';
        echo '.currency { font-weight: bold; color: #28a745; }';
        echo '.stock-low { background-color: #fff3cd; }';
        echo '.stock-zero { background-color: #f8d7da; }';
        echo '.footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 15px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // En-tête élégant
        echo '<div class="header">';
        echo '<h1>📦 INVENTAIRE DU STOCK</h1>';
        echo '<p><strong>Date d\'export :</strong> ' . date('d/m/Y à H:i') . '</p>';
        echo '<p><strong>Nombre d\'articles :</strong> ' . count($data) . '</p>';
        echo '</div>';
        
        // Résumé des totaux
        echo '<div class="summary">';
        echo '<h3>📊 RÉSUMÉ INVENTAIRE</h3>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-row">';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . $total_stock . ' unités</span>';
        echo '<div class="summary-label">Total Stock</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . number_format($total_valeur_achat, 0, ',', ' ') . ' F.CFA</span>';
        echo '<div class="summary-label">Valeur Achat</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . number_format($total_valeur_vente, 0, ',', ' ') . ' F.CFA</span>';
        echo '<div class="summary-label">Valeur Vente</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . number_format($total_valeur_vente - $total_valeur_achat, 0, ',', ' ') . ' F.CFA</span>';
        echo '<div class="summary-label">Marge Potentielle</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Tableau des articles
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>📦 Article</th>';
        echo '<th>📝 Référence</th>';
        echo '<th>🏷️ Catégorie</th>';
        echo '<th>📊 Stock</th>';
        echo '<th>💰 Prix Achat HT</th>';
        echo '<th>💵 Prix Vente TTC</th>';
        echo '<th>📈 Valeur Stock (Achat)</th>';
        echo '<th>📈 Valeur Stock (Vente)</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($data as $row) {
            $stock_class = '';
            if ($row['StockActuel'] == 0) {
                $stock_class = 'stock-zero';
            } elseif ($row['StockActuel'] <= 5) {
                $stock_class = 'stock-low';
            }
            
            echo '<tr class="' . $stock_class . '">';
            echo '<td>' . htmlspecialchars($row['libelle']) . '</td>';
            echo '<td>' . htmlspecialchars($row['descriptif']) . '</td>';
            echo '<td>' . htmlspecialchars($row['nom_categorie']) . '</td>';
            echo '<td><strong>' . $row['StockActuel'] . '</strong></td>';
            echo '<td class="currency">' . number_format($row['PrixAchatHT'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($row['PrixVenteTTC'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($row['valeur_stock_achat'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($row['valeur_stock_vente'], 0, ',', ' ') . ' F.CFA</td>';
            echo '</tr>';
        }
        
        // Ligne de totaux
        echo '<tr class="total-row">';
        echo '<td colspan="3"><strong>TOTAL GÉNÉRAL</strong></td>';
        echo '<td><strong>' . $total_stock . ' unités</strong></td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td><strong>' . number_format($total_valeur_achat, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_valeur_vente, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        
        // Pied de page
        echo '<div class="footer">';
        echo '<p>Rapport généré automatiquement par le système SOTECH le ' . date('d/m/Y à H:i') . '</p>';
        echo '<p>© ' . date('Y') . ' - Tous droits réservés</p>';
        echo '</div>';
        echo '</body></html>';
        break;
        
    case 'txt':
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="Inventaire_Stock_' . date('Y-m-d_H-i') . '.txt"');
        
        $txt_content = "";
        $txt_content .= str_repeat("=", 80) . "\r\n";
        $txt_content .= "                    📦 INVENTAIRE DU STOCK                    \r\n";
        $txt_content .= str_repeat("=", 80) . "\r\n";
        $txt_content .= "Date d'export : " . date('d/m/Y à H:i') . "\r\n";
        $txt_content .= "Nombre d'articles : " . count($data) . "\r\n";
        $txt_content .= str_repeat("-", 80) . "\r\n";
        
        // Résumé financier
        $txt_content .= "                    📊 RÉSUMÉ INVENTAIRE                     \r\n";
        $txt_content .= str_repeat("-", 80) . "\r\n";
        $txt_content .= "Total Stock    : " . str_pad($total_stock . ' unités', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $txt_content .= "Valeur Achat   : " . str_pad(number_format($total_valeur_achat, 0, ',', ' ') . ' F.CFA', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $txt_content .= "Valeur Vente   : " . str_pad(number_format($total_valeur_vente, 0, ',', ' ') . ' F.CFA', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $txt_content .= "Marge Potentielle : " . str_pad(number_format($total_valeur_vente - $total_valeur_achat, 0, ',', ' ') . ' F.CFA', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $txt_content .= str_repeat("=", 80) . "\r\n";
        
        // En-têtes du tableau
        $txt_content .= str_pad('ARTICLE', 25) .
                        str_pad('RÉFÉRENCE', 20) .
                        str_pad('CATÉGORIE', 15) .
                        str_pad('STOCK', 8) .
                        str_pad('PRIX ACHAT', 12) .
                        str_pad('PRIX VENTE', 12) .
                        str_pad('VALEUR ACHAT', 15) .
                        "VALEUR VENTE\r\n";
        $txt_content .= str_repeat("-", 80) . "\r\n";
        
        foreach ($data as $row) {
            $txt_content .= str_pad(substr($row['libelle'], 0, 24), 25) .
                            str_pad(substr($row['descriptif'], 0, 19), 20) .
                            str_pad(substr($row['nom_categorie'], 0, 14), 15) .
                            str_pad($row['StockActuel'], 8, ' ', STR_PAD_LEFT) .
                            str_pad(number_format($row['PrixAchatHT'], 0, ',', ' ') . ' F', 12, ' ', STR_PAD_LEFT) .
                            str_pad(number_format($row['PrixVenteTTC'], 0, ',', ' ') . ' F', 12, ' ', STR_PAD_LEFT) .
                            str_pad(number_format($row['valeur_stock_achat'], 0, ',', ' ') . ' F', 15, ' ', STR_PAD_LEFT) .
                            number_format($row['valeur_stock_vente'], 0, ',', ' ') . ' F' . "\r\n";
        }
        
        // Ligne de totaux
        $txt_content .= str_repeat("-", 80) . "\r\n";
        $txt_content .= str_pad('TOTAL', 25) .
                        str_pad('', 20) .
                        str_pad('', 15) .
                        str_pad($total_stock, 8, ' ', STR_PAD_LEFT) .
                        str_pad('', 12) .
                        str_pad('', 12) .
                        str_pad(number_format($total_valeur_achat, 0, ',', ' ') . ' F', 15, ' ', STR_PAD_LEFT) .
                        number_format($total_valeur_vente, 0, ',', ' ') . ' F' . "\r\n";
        $txt_content .= str_repeat("=", 80) . "\r\n";
        
        // Pied de page
        $txt_content .= "Rapport généré automatiquement par le système SOTECH\r\n";
        $txt_content .= "© " . date('Y') . " - Tous droits réservés\r\n";
        $txt_content .= str_repeat("=", 80) . "\r\n";
        
        echo $txt_content;
        break;
        
    default: // csv
        // Format CSV standard avec en-tête
        $output = fopen('php://output', 'w');
        
        // En-tête du document
        fputcsv($output, ['INVENTAIRE DU STOCK']);
        fputcsv($output, ['']);
        fputcsv($output, ['Date d\'export', date('d/m/Y à H:i')]);
        fputcsv($output, ['Nombre d\'articles', count($data)]);
        fputcsv($output, ['Total stock', $total_stock . ' unités']);
        fputcsv($output, ['Valeur totale (achat)', number_format($total_valeur_achat, 2, ',', ' ') . ' F.CFA']);
        fputcsv($output, ['Valeur totale (vente)', number_format($total_valeur_vente, 2, ',', ' ') . ' F.CFA']);
        fputcsv($output, ['']);
        
        // En-têtes des colonnes
        fputcsv($output, ['Article', 'Référence', 'Catégorie', 'Stock', 'Prix Achat HT', 'Prix Vente TTC', 'Valeur Stock (Achat)', 'Valeur Stock (Vente)']);
        
        // Données
        foreach ($data as $row) {
            fputcsv($output, [
                $row['libelle'],
                $row['descriptif'],
                $row['nom_categorie'],
                $row['StockActuel'],
                number_format($row['PrixAchatHT'], 2, ',', ' '),
                number_format($row['PrixVenteTTC'], 2, ',', ' '),
                number_format($row['valeur_stock_achat'], 2, ',', ' '),
                number_format($row['valeur_stock_vente'], 2, ',', ' ')
            ]);
        }
        
        // Ligne vide
        fputcsv($output, ['']);
        
        // Totaux
        fputcsv($output, ['TOTAL', '', '', $total_stock, '', '', number_format($total_valeur_achat, 2, ',', ' '), number_format($total_valeur_vente, 2, ',', ' ')]);
        
        fclose($output);
        break;
}
?> 