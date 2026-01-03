<?php
date_default_timezone_set('Africa/Abidjan'); 
session_start();
include('db/connecting.php');
include('fonction_traitement/fonction.php');

if (!isset($_SESSION['nom_utilisateur'])) {
    header('location: connexion.php');
    exit();
}

$format = $_GET['format'] ?? 'excel';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Construction de la requête optimisée avec filtres
$whereConditions = [];
$params = [];

// Filtre par recherche (nom client ou numéro de vente)
if (!empty($search)) {
    $whereConditions[] = "(c.NomPrenomClient LIKE ? OR v.NumeroVente LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// Filtre par date
if (!empty($start_date)) {
    $whereConditions[] = "DATE(v.DateIns) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $whereConditions[] = "DATE(v.DateIns) <= ?";
    $params[] = $end_date;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Requête pour compter le total des ventes
$countSql = "SELECT COUNT(*) as total 
             FROM vente v 
             LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT 
             $whereClause";
$countStmt = $cnx->prepare($countSql);
$countStmt->execute($params);
$totalVentes = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Construction du nom de fichier
if ($start_date && $end_date) {
    $filename = "ventes_du_" . date('d-m-Y', strtotime($start_date)) . "_au_" . date('d-m-Y', strtotime($end_date));
} elseif (!empty($search)) {
    $filename = "ventes_recherche_" . date('d-m-Y');
} else {
    $filename = "toutes_les_ventes_" . date('d-m-Y');
}

// Requête principale optimisée
$ventes_query = "SELECT v.*, c.NomPrenomClient, c.Telephone 
                 FROM vente v 
                 LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT 
                 $whereClause 
                 ORDER BY v.DateIns DESC, v.IDFactureVente DESC";

// Pour les gros volumes, on utilise un curseur pour éviter de charger tout en mémoire
$stmt = $cnx->prepare($ventes_query);
$stmt->execute($params);

switch ($format) {
    case 'excel':
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.xls');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Calculer les totaux
        $total_ventes = 0;
        $total_remises = 0;
        $total_verse = 0;
        $total_monnaie = 0;
        
        // Première passe pour calculer les totaux
        $stmt->execute($params);
        while ($vente = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total_ventes += $vente['MontantTotal'] ?? 0;
            $total_remises += $vente['MontantRemise'] ?? 0;
            $total_verse += $vente['MontantVerse'] ?? 0;
            $total_monnaie += $vente['Monnaie'] ?? 0;
        }
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo '.header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #6f42c1; padding-bottom: 15px; }';
        echo '.header h1 { color: #6f42c1; font-size: 24px; margin: 0; }';
        echo '.header p { color: #666; font-size: 14px; margin: 5px 0; }';
        echo '.summary { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px; }';
        echo '.summary h3 { color: #6f42c1; margin-top: 0; }';
        echo '.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }';
        echo '.summary-item { text-align: center; }';
        echo '.summary-value { font-size: 18px; font-weight: bold; color: #6f42c1; }';
        echo '.summary-label { font-size: 12px; color: #666; margin-top: 5px; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }';
        echo 'th { background: linear-gradient(135deg, #6f42c1, #5a32a3); color: white; padding: 12px 8px; text-align: center; font-weight: bold; border: 1px solid #5a32a3; }';
        echo 'td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; }';
        echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
        echo 'tr:hover { background-color: #f3e8ff; }';
        echo '.total-row { background: linear-gradient(135deg, #dc3545, #c82333) !important; color: white; font-weight: bold; }';
        echo '.total-row td { border: 1px solid #bd2130; }';
        echo '.currency { font-weight: bold; color: #6f42c1; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // En-tête élégant
        echo '<div class="header">';
        echo '<h1>📊 LISTE COMPLÈTE DES VENTES</h1>';
        $date_range = '';
        if ($start_date && $end_date) {
            $date_range = " du " . date('d/m/Y', strtotime($start_date)) . " au " . date('d/m/Y', strtotime($end_date));
        } elseif (!empty($search)) {
            $date_range = " - Recherche: " . htmlspecialchars($search);
        }
        echo '<p>Date d\'export : ' . date('d/m/Y à H:i') . $date_range . '</p>';
        echo '<p>Total des ventes : ' . number_format($totalVentes, 0, ',', ' ') . '</p>';
        echo '</div>';
        
        // Résumé des totaux
        echo '<div class="summary">';
        echo '<h3>💰 RÉSUMÉ FINANCIER</h3>';
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
        echo '<th>📞 Téléphone</th>';
        echo '<th>📄 Numéro Vente</th>';
        echo '<th>💰 Total avec Remise</th>';
        echo '<th>🎯 Montant Remise</th>';
        echo '<th>💵 Montant Versé</th>';
        echo '<th>🔄 Monnaie Rendu</th>';
        echo '<th>📅 Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        // Deuxième passe pour afficher les données
        $stmt->execute($params);
        $id = 1;
        $batchSize = 500; // Traiter par lots pour éviter les timeouts
        $processed = 0;
        
        while ($vente = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td>' . $id++ . '</td>';
            echo '<td>' . htmlspecialchars($vente['NomPrenomClient'] ?? 'Client inconnu') . '</td>';
            echo '<td>' . htmlspecialchars($vente['Telephone'] ?? '') . '</td>';
            echo '<td><strong>' . htmlspecialchars($vente['NumeroVente']) . '</strong></td>';
            echo '<td class="currency">' . number_format($vente['MontantTotal'] ?? 0, 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['MontantRemise'] ?? 0, 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['MontantVerse'] ?? 0, 0, ',', ' ') . ' F.CFA</td>';
            echo '<td class="currency">' . number_format($vente['Monnaie'] ?? 0, 0, ',', ' ') . ' F.CFA</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($vente['DateIns'])) . '</td>';
            echo '</tr>';
            
            $processed++;
            
            // Flush périodique pour éviter les timeouts
            if ($processed % $batchSize === 0) {
                flush();
                set_time_limit(300);
            }
        }
        
        // Ligne de totaux
        echo '<tr class="total-row">';
        echo '<td colspan="4"><strong>TOTAL GÉNÉRAL</strong></td>';
        echo '<td><strong>' . number_format($total_ventes, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_remises, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_verse, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . number_format($total_monnaie, 0, ',', ' ') . ' F.CFA</strong></td>';
        echo '<td><strong>' . $totalVentes . ' ventes</strong></td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</body></html>';
        break;

    case 'word':
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename=' . $filename . '.doc');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        $date_range = '';
        if ($start_date && $end_date) {
            $date_range = " du " . date('d/m/Y', strtotime($start_date)) . " au " . date('d/m/Y', strtotime($end_date));
        } elseif (!empty($search)) {
            $date_range = " - Recherche: " . htmlspecialchars($search);
        }
        
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
        echo '<h1>Liste des Ventes' . $date_range . '</h1>';
        echo '<p><strong>Total des ventes :</strong> ' . number_format($totalVentes, 0, ',', ' ') . '</p>';
        echo '<table border="1" style="width:100%; border-collapse: collapse; font-size: 10px;">';
        echo '<thead><tr><th>#</th><th>Client</th><th>Téléphone</th><th>Numéro Vente</th><th>Total avec remise</th><th>Montant remise</th><th>Montant Versé</th><th>Monnaie Rendu</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        
        $id = 1;
        $batchSize = 500; // Traitement par lots pour Word
        $processed = 0;
        
        // Réinitialiser le curseur pour Word
        $stmt->execute($params);
        
        while ($vente = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td>' . $id++ . '</td>';
            echo '<td>' . htmlspecialchars($vente['NomPrenomClient'] ?? 'Client inconnu') . '</td>';
            echo '<td>' . htmlspecialchars($vente['Telephone'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($vente['NumeroVente']) . '</td>';
            echo '<td>' . htmlspecialchars(number_format($vente['MontantTotal'] ?? 0, 0, ',', ' ')) . ' F</td>';
            echo '<td>' . htmlspecialchars(number_format($vente['MontantRemise'] ?? 0, 0, ',', ' ')) . ' F</td>';
            echo '<td>' . htmlspecialchars(number_format($vente['MontantVerse'] ?? 0, 0, ',', ' ')) . ' F</td>';
            echo '<td>' . htmlspecialchars(number_format($vente['Monnaie'] ?? 0, 0, ',', ' ')) . ' F</td>';
            echo '<td>' . htmlspecialchars($vente['DateIns']) . '</td>';
            echo '</tr>';
            
            $processed++;
            
            // Flush périodique pour éviter les timeouts
            if ($processed % $batchSize === 0) {
                flush();
                set_time_limit(300);
            }
        }
        
        echo '</tbody></table>';
        echo '</body></html>';
        break;

    case 'txt':
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.txt');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        $date_range = '';
        if ($start_date && $end_date) {
            $date_range = " du " . date('d/m/Y', strtotime($start_date)) . " au " . date('d/m/Y', strtotime($end_date));
        } elseif (!empty($search)) {
            $date_range = " - Recherche: " . $search;
        }

        echo "Liste des Ventes" . $date_range . "\r\n";
        echo "Total des ventes : " . number_format($totalVentes, 0, ',', ' ') . "\r\n";
        echo str_repeat("=", 200) . "\r\n";
        
        // En-têtes avec alignement
        echo str_pad('#', 5) .
             str_pad('Client', 25) .
             str_pad('Téléphone', 15) .
             str_pad('Numero Vente', 20) .
             str_pad('Total avec remise', 18) .
             str_pad('Remise', 12) .
             str_pad('Verse', 12) .
             str_pad('Monnaie', 12) .
             "Date\r\n";
        echo str_repeat("-", 200) . "\r\n";

        $id = 1;
        $batchSize = 1000; // Traitement par lots pour TXT
        $processed = 0;
        
        // Réinitialiser le curseur pour TXT
        $stmt->execute($params);
        
        while ($vente = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo str_pad($id++, 5) .
                 str_pad(substr($vente['NomPrenomClient'] ?? 'Client inconnu', 0, 24), 25) .
                 str_pad(substr($vente['Telephone'] ?? '', 0, 14), 15) .
                 str_pad($vente['NumeroVente'], 20) .
                 str_pad(number_format($vente['MontantTotal'] ?? 0, 0, ',', ' ') . ' F', 18) .
                 str_pad(number_format($vente['MontantRemise'] ?? 0, 0, ',', ' ') . ' F', 12) .
                 str_pad(number_format($vente['MontantVerse'] ?? 0, 0, ',', ' ') . ' F', 12) .
                 str_pad(number_format($vente['Monnaie'] ?? 0, 0, ',', ' ') . ' F', 12) .
                 $vente['DateIns'] . "\r\n";
            
            $processed++;
            
            // Flush périodique pour éviter les timeouts
            if ($processed % $batchSize === 0) {
                flush();
                set_time_limit(300);
            }
        }
        break;
}

exit();
?> 