<?php
include('../db/connecting.php');
include('fonction.php');

header('Content-Type: application/json');

if (!isset($_POST['numero_serie'])) {
    echo json_encode(['error' => 'Numéro de série non fourni']);
    exit;
}

$numero_serie = $_POST['numero_serie'];

try {
    // Vérifier si le numéro de série existe déjà
    $stmt = $cnx->prepare("SELECT COUNT(*) FROM num_serie WHERE NUMERO_SERIE = ?");
    $stmt->execute([$numero_serie]);
    $count = $stmt->fetchColumn();

    echo json_encode([
        'exists' => $count > 0,
        'message' => $count > 0 ? 'Ce numéro de série existe déjà' : 'Numéro de série disponible'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Erreur lors de la vérification du numéro de série',
        'message' => $e->getMessage()
    ]);
} 