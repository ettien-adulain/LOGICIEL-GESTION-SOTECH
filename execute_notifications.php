<?php
$queueFile = __DIR__ . '/queue/notifications.json';
if (!file_exists($queueFile)) {
    echo "Aucune notification à traiter.\n";
    exit;
}
$lines = file($queueFile);
file_put_contents($queueFile, ''); // Vide la file
foreach ($lines as $line) {
    $job = json_decode($line, true);
    if (!$job) continue;
    if (in_array('email', $job['mode']) && filter_var($job['email'], FILTER_VALIDATE_EMAIL)) {
        mail($job['email'], $job['sujet'], $job['message_email']);
    }
    if (in_array('sms', $job['mode']) && strlen($job['telephone']) >= 8) {
        if (function_exists('envoyer_sms')) {
            envoyer_sms($job['telephone'], $job['message_sms']);
        }
    }
}
echo "Notifications traitées : " . count($lines) . "\n"; 