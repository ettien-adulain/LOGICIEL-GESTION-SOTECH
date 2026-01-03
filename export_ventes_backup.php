<?php
date_default_timezone_set('Africa/Abidjan'); 
session_start();
include('db/connecting.php');
include('fonction_traitement/fonction.php');

if (!isset($_SESSION['nom_utilisateur'])) {
    header('location: connexion.php');
    exit();
}

$format = $_GET['format'] ?? 'txt'; // Default to text
$date_export = $_GET['date'] ?? date('Y-m-d'); // Utiliser la date passée en paramètre ou aujourd'hui

// Récupération des ventes normales
$ventes_query = "SELECT v.*, c.NomPrenomClient, 'normal' as type_vente
                 FROM vente v 
                 JOIN client c ON v.IDCLIENT = c.IDCLIENT 
                 WHERE DATE(v.DateIns) = ?";
$stmt = $cnx->prepare($ventes_query);
$stmt->execute([$date_export]);
$ventes_normales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des ventes à crédit
$ventes_credit_query = "SELECT vc.*, c.NomPrenomClient, 'credit' as type_vente
                        FROM ventes_credit vc 
                        JOIN client c ON vc.IDCLIENT = c.IDCLIENT 
                        WHERE DATE(vc.DateIns) = ?";
$stmt = $cnx->prepare($ventes_credit_query);
$stmt->execute([$date_export]);
$ventes_credit = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fusion des ventes
$ventes = array_merge($ventes_normales, $ventes_credit);

$filename = "ventes_du_" . date('d-m-Y', strtotime($date_export));

// Fonction pour afficher une ligne de vente
function afficherLigneVente($vente, $id) {
    echo '<tr>';
    echo '<td>' . $id . '</td>';
    echo '<td>' . htmlspecialchars($vente['NomPrenomClient']) . '</td>';
    echo '<td><strong>' . htmlspecialchars($vente['NumeroVente']) . '</strong>';
    if ($vente['type_vente'] === 'credit') {
        echo ' <span style="color: #ffc107; font-size: 12px;">(CRÉDIT)</span>';
    }
    echo '</td>';
    echo '<td class="currency">' . number_format($vente['MontantTotal'], 0, ',', ' ') . ' F.CFA</td>';
    echo '<td class="currency">' . number_format($vente['MontantRemise'], 0, ',', ' ') . ' F.CFA</td>';
    if ($vente['type_vente'] === 'credit') {
        // Pour les ventes à crédit, afficher l'acompte au lieu du montant versé
        $acompte = $vente['AccompteVerse'] ?? 0;
        echo '<td class="currency">' . number_format($acompte, 0, ',', ' ') . ' F.CFA <small>(Acompte)</small></td>';
        echo '<td class="currency">-</td>'; // Pas de monnaie pour les ventes à crédit
    } else {
        echo '<td class="currency">' . number_format($vente['MontantVerse'], 0, ',', ' ') . ' F.CFA</td>';
        echo '<td class="currency">' . number_format($vente['Monnaie'], 0, ',', ' ') . ' F.CFA</td>';
    }
    echo '<td>' . date('d/m/Y H:i', strtotime($vente['DateIns'])) . '</td>';
    echo '</tr>';
}

switch ($format) {
    case 'excel':
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.xls');
        
        // Calcul des totaux
        $total_ventes = 0;
        $total_remises = 0;
        $total_verse = 0;
        $total_monnaie = 0;
        
        foreach ($ventes as $vente) {
            $total_ventes += $vente['MontantTotal'];
            $total_remises += $vente['MontantRemise'];
            $total_verse += $vente['MontantVerse'];
            $total_monnaie += $vente['Monnaie'];
        }
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo '.header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #2c5aa0; padding-bottom: 15px; }';
        echo '.header h1 { color: #2c5aa0; font-size: 24px; margin: 0; }';
        echo '.header p { color: #666; font-size: 14px; margin: 5px 0; }';
        echo '.summary { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px; }';
        echo '.summary h3 { color: #2c5aa0; margin-top: 0; }';
        echo '.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }';
        echo '.summary-item { text-align: center; }';
        echo '.summary-value { font-size: 18px; font-weight: bold; color: #2c5aa0; }';
        echo '.summary-label { font-size: 12px; color: #666; margin-top: 5px; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }';
        echo 'th { background: linear-gradient(135deg, #2c5aa0, #1e3a5f); color: white; padding: 12px 8px; text-align: center; font-weight: bold; border: 1px solid #1e3a5f; }';
        echo 'td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; }';
        echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
        echo 'tr:hover { background-color: #e3f2fd; }';
        echo '.total-row { background: linear-gradient(135deg, #28a745, #20c997) !important; color: white; font-weight: bold; }';
        echo '.total-row td { border: 1px solid #1e7e34; }';
        echo '.currency { font-weight: bold; color: #2c5aa0; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // En-tête élégant
        echo '<div class="header">';
        echo '<h1>📊 RAPPORT DES VENTES</h1>';
        echo '<p>Date d\'export : ' . date('d/m/Y à H:i') . '</p>';
        echo '<p>Période : ' . date('d/m/Y', strtotime($date_export)) . '</p>';
        echo '</div>';
        
        // Résumé des totaux
        echo '<div class="summary">';
        echo '<h3>📈 RÉSUMÉ FINANCIER</h3>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . number_format($total_ventes, 0, ',', ' ') . ' F.CFA</div>';
        echo '<div class="summary-label">Total Ventes</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . number_format($total_remises, 0, ',', ' ') . ' F.CFA</div>';
        echo '<div class="summary-label">Total Remises</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . number_format($total_verse, 0, ',', ' ') . ' F.CFA</div>';
        echo '<div class="summary-label">Total Versé</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-value">' . number_format($total_monnaie, 0, ',', ' ') . ' F.CFA</div>';
        echo '<div class="summary-label">Total Monnaie</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Tableau des ventes
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>👤 Client</th>';
        echo '<th>📄 Numéro Vente</th>';
        echo '<th>💰 Total avec Remise</th>';
        echo '<th>🎯 Montant Remise</th>';
        echo '<th>💵 Montant Versé</th>';
        echo '<th>🔄 Monnaie Rendu</th>';
        echo '<th>📅 Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $id = 1;
        foreach ($ventes as $vente) {
            echo '<tr>';
            echo '<td>' . $id++ . '</td>';
            echo '<td>' . htmlspecialchars($vente['NomPrenomClient']) . '</td>';
            echo '<td><strong>' . htmlspecialchars($vente['NumeroVente']) . '</strong></td>';
            echo '<td class="currency">' . number_format($vente['MontantTotal'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['MontantRemise'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['MontantVerse'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['Monnaie'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($vente['DateIns'])) . '</td>';
            echo '</tr>';
        }
        
        // Ligne de totaux
        echo '<tr class="total-row">';
        echo '<td colspan="3"><strong>TOTAL GÉNÉRAL</strong></td>';
        echo '<td><strong>' . number_format($total_ventes, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_remises, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_verse, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_monnaie, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . count($ventes) . ' ventes</strong></td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</body></html>';
        break;

    case 'word':
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename=' . $filename . '.doc');
        
        // Calcul des totaux
        $total_ventes = 0;
        $total_remises = 0;
        $total_verse = 0;
        $total_monnaie = 0;
        
        foreach ($ventes as $vente) {
            $total_ventes += $vente['MontantTotal'];
            $total_remises += $vente['MontantRemise'];
            $total_verse += $vente['MontantVerse'];
            $total_monnaie += $vente['Monnaie'];
        }
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'body { font-family: "Segoe UI", Arial, sans-serif; margin: 20px; line-height: 1.6; }';
        echo '.header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #2c5aa0; padding-bottom: 20px; }';
        echo '.header h1 { color: #2c5aa0; font-size: 28px; margin: 0; font-weight: bold; }';
        echo '.header p { color: #666; font-size: 16px; margin: 8px 0; }';
        echo '.summary { background: #f8f9fa; border: 2px solid #2c5aa0; border-radius: 10px; padding: 20px; margin-bottom: 25px; }';
        echo '.summary h3 { color: #2c5aa0; margin-top: 0; font-size: 20px; text-align: center; }';
        echo '.summary-grid { display: table; width: 100%; }';
        echo '.summary-row { display: table-row; }';
        echo '.summary-item { display: table-cell; text-align: center; padding: 15px; width: 25%; }';
        echo '.summary-value { font-size: 20px; font-weight: bold; color: #2c5aa0; display: block; }';
        echo '.summary-label { font-size: 14px; color: #666; margin-top: 8px; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }';
        echo 'th { background: #2c5aa0; color: white; padding: 15px 10px; text-align: center; font-weight: bold; border: 2px solid #1e3a5f; font-size: 14px; }';
        echo 'td { padding: 12px 10px; border: 1px solid #ddd; text-align: center; font-size: 13px; }';
        echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
        echo 'tr:hover { background-color: #e3f2fd; }';
        echo '.total-row { background: #28a745 !important; color: white; font-weight: bold; }';
        echo '.total-row td { border: 2px solid #1e7e34; font-size: 14px; }';
        echo '.currency { font-weight: bold; color: #2c5aa0; }';
        echo '.footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 15px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // En-tête élégant
        echo '<div class="header">';
        echo '<h1>📊 RAPPORT DES VENTES</h1>';
        echo '<p><strong>Date d\'export :</strong> ' . date('d/m/Y à H:i') . '</p>';
        echo '<p><strong>Période :</strong> ' . date('d/m/Y', strtotime($date_export)) . '</p>';
        echo '<p><strong>Nombre de ventes :</strong> ' . count($ventes) . '</p>';
        echo '</div>';
        
        // Résumé des totaux
        echo '<div class="summary">';
        echo '<h3>📈 RÉSUMÉ FINANCIER</h3>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-row">';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . number_format($total_ventes, 0, ',', ' ') . ' F.CFA</span>';
        echo '<div class="summary-label">Total Ventes</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . number_format($total_remises, 0, ',', ' ') . ' F.CFA</span>';
        echo '<div class="summary-label">Total Remises</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . number_format($total_verse, 0, ',', ' ') . ' F.CFA</span>';
        echo '<div class="summary-label">Total Versé</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span class="summary-value">' . number_format($total_monnaie, 0, ',', ' ') . ' F.CFA</span>';
        echo '<div class="summary-label">Total Monnaie</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Tableau des ventes
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>👤 Client</th>';
        echo '<th>📄 Numéro Vente</th>';
        echo '<th>💰 Total avec Remise</th>';
        echo '<th>🎯 Montant Remise</th>';
        echo '<th>💵 Montant Versé</th>';
        echo '<th>🔄 Monnaie Rendu</th>';
        echo '<th>📅 Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $id = 1;
        foreach ($ventes as $vente) {
            echo '<tr>';
            echo '<td>' . $id++ . '</td>';
            echo '<td>' . htmlspecialchars($vente['NomPrenomClient']) . '</td>';
            echo '<td><strong>' . htmlspecialchars($vente['NumeroVente']) . '</strong></td>';
            echo '<td class="currency">' . number_format($vente['MontantTotal'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['MontantRemise'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['MontantVerse'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['Monnaie'], 0, ',', ' ') . ' F.CFA</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($vente['DateIns'])) . '</td>';
            echo '</tr>';
        }
        
        // Ligne de totaux
        echo '<tr class="total-row">';
        echo '<td colspan="3"><strong>TOTAL GÉNÉRAL</strong></td>';
        echo '<td><strong>' . number_format($total_ventes, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_remises, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_verse, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_monnaie, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . count($ventes) . ' ventes</strong></td>';
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
        header('Content-Disposition: attachment; filename=' . $filename . '.txt');

        // Calcul des totaux
        $total_ventes = 0;
        $total_remises = 0;
        $total_verse = 0;
        $total_monnaie = 0;
        
        foreach ($ventes as $vente) {
            $total_ventes += $vente['MontantTotal'];
            $total_remises += $vente['MontantRemise'];
            $total_verse += $vente['MontantVerse'];
            $total_monnaie += $vente['Monnaie'];
        }

        $data = "";
        $data .= str_repeat("=", 80) . "\r\n";
        $data .= "                    📊 RAPPORT DES VENTES                    \r\n";
        $data .= str_repeat("=", 80) . "\r\n";
        $data .= "Date d'export : " . date('d/m/Y à H:i') . "\r\n";
        $data .= "Période       : " . date('d/m/Y', strtotime($date_export)) . "\r\n";
        $data .= "Nombre ventes : " . count($ventes) . "\r\n";
        $data .= str_repeat("-", 80) . "\r\n";
        
        // Résumé financier
        $data .= "                    📈 RÉSUMÉ FINANCIER                     \r\n";
        $data .= str_repeat("-", 80) . "\r\n";
        $data .= "Total Ventes  : " . str_pad(number_format($total_ventes, 0, ',', ' ') . ' F.CFA', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $data .= "Total Remises : " . str_pad(number_format($total_remises, 0, ',', ' ') . ' F.CFA', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $data .= "Total Versé   : " . str_pad(number_format($total_verse, 0, ',', ' ') . ' F.CFA', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $data .= "Total Monnaie : " . str_pad(number_format($total_monnaie, 0, ',', ' ') . ' F.CFA', 20, ' ', STR_PAD_LEFT) . "\r\n";
        $data .= str_repeat("=", 80) . "\r\n";
        
        // En-têtes du tableau
        $data .= str_pad('#', 4) .
                 str_pad('CLIENT', 25) .
                 str_pad('N° VENTE', 20) .
                 str_pad('TOTAL', 15) .
                 str_pad('REMISE', 12) .
                 str_pad('VERSÉ', 12) .
                 str_pad('MONNAIE', 12) .
                 "DATE\r\n";
        $data .= str_repeat("-", 80) . "\r\n";

        $id = 1;
        foreach ($ventes as $vente) {
            $data .= str_pad($id++, 4) .
                     str_pad(substr($vente['NomPrenomClient'], 0, 24), 25) .
                     str_pad($vente['NumeroVente'], 20) .
                     str_pad(number_format($vente['MontantTotal'], 0, ',', ' ') . ' F', 15) .
                     str_pad(number_format($vente['MontantRemise'], 0, ',', ' ') . ' F', 12) .
                     str_pad(number_format($vente['MontantVerse'], 0, ',', ' ') . ' F', 12) .
                     str_pad(number_format($vente['Monnaie'], 0, ',', ' ') . ' F', 12) .
                     date('d/m/Y H:i', strtotime($vente['DateIns'])) . "\r\n";
        }
        
        // Ligne de totaux
        $data .= str_repeat("-", 80) . "\r\n";
        $data .= str_pad('TOTAL', 4) .
                 str_pad('', 25) .
                 str_pad('', 20) .
                 str_pad(number_format($total_ventes, 0, ',', ' ') . ' F', 15) .
                 str_pad(number_format($total_remises, 0, ',', ' ') . ' F', 12) .
                 str_pad(number_format($total_verse, 0, ',', ' ') . ' F', 12) .
                 str_pad(number_format($total_monnaie, 0, ',', ' ') . ' F', 12) .
                 count($ventes) . ' ventes' . "\r\n";
        $data .= str_repeat("=", 80) . "\r\n";
        
        // Pied de page
        $data .= "Rapport généré automatiquement par le système SOTECH\r\n";
        $data .= "© " . date('Y') . " - Tous droits réservés\r\n";
        $data .= str_repeat("=", 80) . "\r\n";
        
        echo $data;
        break;
}

exit();
?> 