<?php
include('db/connecting.php');
$stmt = $cnx->query('SHOW TABLES');
echo "=== TABLES DISPONIBLES ===\n";
while($row = $stmt->fetch()) {
    echo "- " . $row[0] . "\n";
}
?>
