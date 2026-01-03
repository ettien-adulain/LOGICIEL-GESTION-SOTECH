<?php
session_start();
include('db/connecting.php');
if (!isset($_SESSION['nom_utilisateur'])) {
    die('Accès refusé');
}

// Filtres/recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$id_client = isset($_GET['id_client']) ? intval($_GET['id_client']) : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

$where = [];
$params = [];
if ($search) {
    $where[] = "(sd.numero_sav LIKE ? OR c.NomPrenomClient LIKE ? OR sd.numero_serie LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statut) {
    $where[] = "sd.statut = ?";
    $params[] = $statut;
}
if ($id_client) {
    $where[] = "sd.id_client = ?";
    $params[] = $id_client;
}
if ($date_debut) {
    $where[] = "sd.date_depot >= ?";
    $params[] = $date_debut . ' 00:00:00';
}
if ($date_fin) {
    $where[] = "sd.date_depot <= ?";
    $params[] = $date_fin . ' 23:59:59';
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $cnx->prepare("SELECT sd.numero_sav, sd.date_depot, c.NomPrenomClient, sd.numero_serie, sd.description_panne, sd.cout_estime, sd.statut FROM sav_dossier sd LEFT JOIN client c ON sd.id_client = c.IDCLIENT $where_clause ORDER BY sd.date_depot DESC");
$stmt->execute($params);
$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$columns = ['N° SAV', 'Date dépôt', 'Client', 'N° série', 'Description panne', 'Coût estimatif', 'Statut'];

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="SAV_export_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $columns);
    foreach ($dossiers as $row) {
        fputcsv($output, [
            $row['numero_sav'],
            date('d/m/Y H:i', strtotime($row['date_depot'])),
            $row['NomPrenomClient'],
            $row['numero_serie'],
            $row['description_panne'],
            number_format($row['cout_estime'], 0, ',', ' '),
            $row['statut']
        ]);
    }
    fclose($output);
    exit;
} elseif ($format === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="SAV_export_' . date('Ymd_His') . '.txt"');
    echo implode("\t", $columns) . "\n";
    foreach ($dossiers as $row) {
        echo implode("\t", [
            $row['numero_sav'],
            date('d/m/Y H:i', strtotime($row['date_depot'])),
            $row['NomPrenomClient'],
            $row['numero_serie'],
            $row['description_panne'],
            number_format($row['cout_estime'], 0, ',', ' '),
            $row['statut']
        ]) . "\n";
    }
    exit;
} elseif ($format === 'word') {
    header('Content-Type: application/vnd.ms-word');
    header('Content-Disposition: attachment; filename="SAV_export_' . date('Ymd_His') . '.doc"');
    echo '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse;}th,td{border:1px solid #888;padding:4px;}th{background:#eee;}</style></head><body>';
    echo '<h2>Export dossiers SAV</h2>';
    echo '<table><tr>';
    foreach ($columns as $col) echo '<th>' . htmlspecialchars($col) . '</th>';
    echo '</tr>';
    foreach ($dossiers as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['numero_sav']) . '</td>';
        echo '<td>' . htmlspecialchars(date('d/m/Y H:i', strtotime($row['date_depot']))) . '</td>';
        echo '<td>' . htmlspecialchars($row['NomPrenomClient']) . '</td>';
        echo '<td>' . htmlspecialchars($row['numero_serie']) . '</td>';
        echo '<td>' . htmlspecialchars($row['description_panne']) . '</td>';
        echo '<td>' . htmlspecialchars(number_format($row['cout_estime'], 0, ',', ' ')) . '</td>';
        echo '<td>' . htmlspecialchars($row['statut']) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
} else {
    die('Format non supporté');
} 