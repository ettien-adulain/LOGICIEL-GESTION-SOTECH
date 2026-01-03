<?php
header('Content-Type: application/json');

// Connexion à la base de données
require_once 'db/connecting.php';

// Récupérer la configuration Africa's Talking
$sql = "SELECT username, api_key, sender_id FROM parametre_sms WHERE provider = 'africastalking' ORDER BY id DESC LIMIT 1";
$stmt = $cnx->prepare($sql);
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune configuration trouvée']);
    exit();
}

$username = $config['username'];
$apiKey = $config['api_key'];

echo json_encode([
    'username' => $username,
    'api_key_length' => strlen($apiKey),
    'api_key_preview' => substr($apiKey, 0, 10) . '...' . substr($apiKey, -10),
    'api_key_has_spaces' => strpos($apiKey, ' ') !== false,
    'username_is_sandbox' => $username === 'sandbox',
    'config_complete' => !empty($username) && !empty($apiKey)
], JSON_PRETTY_PRINT); 