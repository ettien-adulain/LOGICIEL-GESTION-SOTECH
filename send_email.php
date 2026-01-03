<?php
header('Content-Type: application/json');

// Récupération des données
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$to = $data['to'];
$subject = $data['subject'];
$message = $data['message'];

// Configuration des en-têtes
$headers = array(
    'From' => 'noreply@sotech.com',
    'Reply-To' => 'contact@sotech.com',
    'X-Mailer' => 'PHP/' . phpversion(),
    'Content-Type' => 'text/html; charset=UTF-8'
);

// Tentative d'envoi de l'email
try {
    $success = mail($to, $subject, $message, $headers);
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 