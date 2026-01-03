<?php
// send_sms_infobip.php - Système unique et solide d'envoi SMS via Infobip
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Gestion des erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error send_sms_infobip: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erreur interne du serveur']);
        exit;
    }
});

try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    
    // Vérification de la connexion à la base de données
    if (!isset($cnx) || $cnx === null) {
        throw new Exception("Connexion à la base de données échouée");
    }
  
    
} catch (Exception $e) {
    error_log("Erreur de connexion send_sms_infobip: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur de connexion']);
    exit;
}

// Fonction principale pour envoyer SMS via Infobip - Système unique et solide
function sendSmsInfobip($apiKey, $baseUrl, $senderId, $numero, $message) {
    // Validation des paramètres
    if (empty($apiKey) || empty($baseUrl) || empty($numero) || empty($message)) {
        throw new Exception("Paramètres manquants pour l'envoi SMS");
    }
    
    // Nettoyage et validation du numéro
    $numero = preg_replace('/[^0-9+]/', '', $numero);
    if (!preg_match('/^\+[1-9]\d{7,14}$/', $numero)) {
        throw new Exception("Format de numéro invalide: " . $numero);
    }
    
    // Limitation de la longueur du message
    if (strlen($message) > 1600) {
        throw new Exception("Message trop long (max 1600 caractères)");
    }
    
    $url = rtrim($baseUrl, '/') . '/sms/2/text/advanced';
    
    // Préparer les données pour l'API Infobip
    $data = [
        'messages' => [
            [
                'from' => $senderId ?: 'SOTECH',
                'destinations' => [
                    ['to' => $numero]
                ],
                'text' => $message,
                'notifyUrl' => '', // Optionnel pour les webhooks
                'notifyContentType' => 'application/json'
            ]
        ]
    ];
    
    $headers = [
        'Authorization: App ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: SOTECH-SMS-System/1.0'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    // Log de la requête pour debugging
    error_log("Infobip SMS Request - URL: $url, HTTP Code: $httpCode, Response: " . substr($response, 0, 500));
    
    if ($curlError) {
        throw new Exception("Erreur cURL: " . $curlError);
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        // Vérifier la réponse Infobip spécifique
        if (isset($responseData['messages'][0]['status']['groupName'])) {
            $statusGroup = $responseData['messages'][0]['status']['groupName'];
            $statusDescription = $responseData['messages'][0]['status']['description'] ?? '';
            
            // Gérer les différents statuts Infobip
            if ($statusGroup === 'ACCEPTED') {
                return [
                    'success' => true,
                    'message' => 'SMS envoyé avec succès via Infobip',
                    'response' => $responseData,
                    'http_code' => $httpCode,
                    'message_id' => $responseData['messages'][0]['messageId'] ?? null
                ];
            } elseif ($statusGroup === 'PENDING_ACCEPTED') {
                return [
                    'success' => true,
                    'message' => 'SMS accepté et en cours de traitement',
                    'response' => $responseData,
                    'http_code' => $httpCode,
                    'message_id' => $responseData['messages'][0]['messageId'] ?? null
                ];
            } elseif ($statusGroup === 'PENDING_WAITING_DELIVERY') {
        return [
            'success' => true,
                    'message' => 'SMS en attente de livraison',
                    'response' => $responseData,
                    'http_code' => $httpCode,
                    'message_id' => $responseData['messages'][0]['messageId'] ?? null
                ];
            } elseif ($statusGroup === 'PENDING') {
                // Statut PENDING peut indiquer un problème de crédits ou de configuration
                $messageId = $responseData['messages'][0]['messageId'] ?? null;
                if ($messageId) {
                    return [
                        'success' => false,
                        'message' => 'SMS accepté mais non livré - Vérifiez votre solde Infobip et la configuration',
                        'response' => $responseData,
                        'http_code' => $httpCode,
                        'message_id' => $messageId
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Erreur Infobip: ' . $statusDescription,
            'response' => $responseData,
            'http_code' => $httpCode
        ];
                }
            } else {
                // Pour les autres statuts, considérer comme succès si le message a un ID
                if (isset($responseData['messages'][0]['messageId']) && !empty($responseData['messages'][0]['messageId'])) {
                    return [
                        'success' => true,
                        'message' => "SMS traité (Statut: $statusDescription)",
                        'response' => $responseData,
                        'http_code' => $httpCode,
                        'message_id' => $responseData['messages'][0]['messageId']
                    ];
    } else {
        return [
            'success' => false,
                        'message' => "Erreur Infobip: $statusDescription",
            'response' => $responseData,
            'http_code' => $httpCode
        ];
    }
}
        } else {
            // Si pas de statut détaillé, vérifier si on a un messageId
            if (isset($responseData['messages'][0]['messageId']) && !empty($responseData['messages'][0]['messageId'])) {
        return [
            'success' => true,
                    'message' => 'SMS envoyé avec succès via Infobip',
                    'response' => $responseData,
                    'http_code' => $httpCode,
                    'message_id' => $responseData['messages'][0]['messageId']
                ];
            } else {
                $errorMsg = $responseData['messages'][0]['status']['description'] ?? 'Erreur inconnue';
                return [
                    'success' => false,
                    'message' => $errorMsg,
            'response' => $responseData,
            'http_code' => $httpCode
        ];
            }
        }
    } else {
        $errorMessage = 'Erreur API Infobip (HTTP ' . $httpCode . ')';
        if (isset($responseData['requestError']['serviceException']['text'])) {
            $errorMessage = $responseData['requestError']['serviceException']['text'];
        } elseif (isset($responseData['requestError']['serviceException']['messageId'])) {
            $errorMessage = $responseData['requestError']['serviceException']['messageId'];
        } elseif (isset($responseData['error'])) {
            $errorMessage = $responseData['error'];
        }
        
        return [
            'success' => false,
            'message' => $errorMessage,
            'response' => $responseData,
            'http_code' => $httpCode
        ];
    }
}

// Fonction principale pour envoyer SMS unique via Infobip - Système unique et solide
function sendSmsUnique($numero, $message) {
    global $cnx;
    
    try {
        // Récupérer la configuration Infobip active - adapter selon la structure de la table
        $stmt = $cnx->query("DESCRIBE parametre_sms");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('base_url', $columns) && in_array('provider', $columns)) {
            // Structure complète
        $stmt = $cnx->prepare("SELECT api_key, base_url, sender_id FROM parametre_sms WHERE provider = 'infobip' AND active = 1 ORDER BY id DESC LIMIT 1");
        } else {
            // Structure simplifiée
            $stmt = $cnx->prepare("SELECT api_key, api_secret, sender_id FROM parametre_sms ORDER BY id DESC LIMIT 1");
        }
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Adapter la configuration selon la structure
        if ($config) {
            if (!isset($config['base_url'])) {
                $config['base_url'] = 'https://api.infobip.com'; // Valeur par défaut
            }
        }
        
        if (!$config) {
            throw new Exception("Configuration Infobip non trouvée. Veuillez configurer Infobip dans les paramètres SMS.");
        }
        
        // Validation de la configuration
        if (empty($config['api_key']) || empty($config['base_url'])) {
            throw new Exception("Configuration Infobip incomplète. API Key et Base URL requis.");
        }
        
        // Envoi du SMS via Infobip
        $result = sendSmsInfobip($config['api_key'], $config['base_url'], $config['sender_id'], $numero, $message);
        
        // Log de l'envoi pour audit
        error_log("SMS Infobip - Numéro: $numero, Succès: " . ($result['success'] ? 'OUI' : 'NON') . ", Message: " . $result['message']);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erreur sendSmsUnique: " . $e->getMessage());
        throw new Exception("Erreur configuration Infobip: " . $e->getMessage());
    }
}

// Fonction pour envoyer SMS en lot (plusieurs numéros)
function sendSmsBulk($numeros, $message) {
    global $cnx;
    $results = [];
    $successCount = 0;
    $errorCount = 0;
    
    try {
        // Récupérer la configuration Infobip active - adapter selon la structure de la table
        $stmt = $cnx->query("DESCRIBE parametre_sms");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('base_url', $columns) && in_array('provider', $columns)) {
            // Structure complète
            $stmt = $cnx->prepare("SELECT api_key, base_url, sender_id FROM parametre_sms WHERE provider = 'infobip' AND active = 1 ORDER BY id DESC LIMIT 1");
        } else {
            // Structure simplifiée
            $stmt = $cnx->prepare("SELECT api_key, api_secret, sender_id FROM parametre_sms ORDER BY id DESC LIMIT 1");
        }
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Adapter la configuration selon la structure
        if ($config) {
            if (!isset($config['base_url'])) {
                $config['base_url'] = 'https://api.infobip.com'; // Valeur par défaut
            }
        }
        
        if (!$config) {
            throw new Exception("Configuration Infobip non trouvée.");
        }
        
        foreach ($numeros as $numero) {
            try {
                $result = sendSmsInfobip($config['api_key'], $config['base_url'], $config['sender_id'], $numero, $message);
                $results[] = [
                    'numero' => $numero,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
                
                // Petite pause entre les envois pour éviter le rate limiting
                usleep(100000); // 100ms
                
            } catch (Exception $e) {
                $results[] = [
                    'numero' => $numero,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }
        
        return [
            'success' => $errorCount === 0,
            'message' => "Envoi terminé: $successCount succès, $errorCount erreurs",
            'results' => $results,
            'success_count' => $successCount,
            'error_count' => $errorCount
        ];
        
    } catch (Exception $e) {
        throw new Exception("Erreur envoi en lot: " . $e->getMessage());
    }
}

// Traitement de la requête - Système unique et solide
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $numero = trim($_POST['numero'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $bulkMode = isset($_POST['bulk']) && $_POST['bulk'] === 'true';
        
        if (empty($numero) || empty($message)) {
            throw new Exception("Numéro et message requis");
        }
        
        // Traitement en mode lot ou unique
        if ($bulkMode && strpos($numero, ',') !== false) {
            // Mode envoi en lot
            $numeros = array_map('trim', explode(',', $numero));
            $numeros = array_filter($numeros, function($num) {
                return !empty($num) && preg_match('/^\+[1-9]\d{7,14}$/', $num);
            });
            
            if (empty($numeros)) {
                throw new Exception("Aucun numéro valide trouvé");
            }
            
            $result = sendSmsBulk($numeros, $message);
            
            // Enregistrer les résultats en base
            foreach ($result['results'] as $smsResult) {
                $statut = $smsResult['success'] ? 'envoye' : 'echec';
                $erreur = $smsResult['success'] ? null : $smsResult['message'];
                
                $stmt = $cnx->prepare("INSERT INTO messages_envoyes (numero_telephone, messag, provider, date_envoi, statut, erreur) VALUES (?, ?, 'Infobip', NOW(), ?, ?)");
                $stmt->execute([$smsResult['numero'], $message, $statut, $erreur]);
            }
            
            echo json_encode([
                'status' => $result['success'] ? 'success' : 'partial',
                'message' => $result['message'],
                'provider' => 'Infobip',
                'success_count' => $result['success_count'],
                'error_count' => $result['error_count']
            ]);
            
        } else {
            // Mode envoi unique
            $result = sendSmsUnique($numero, $message);
            
            if ($result['success']) {
                // Enregistrer le SMS envoyé dans la base de données
                $stmt = $cnx->prepare("INSERT INTO messages_envoyes (numero_telephone, messag, provider, date_envoi, statut, message_id) VALUES (?, ?, 'Infobip', NOW(), 'envoye', ?)");
                $stmt->execute([$numero, $message, $result['message_id'] ?? null]);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => "SMS envoyé avec succès via Infobip",
                    'provider' => 'Infobip',
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                // Enregistrer l'échec dans la base de données
                $stmt = $cnx->prepare("INSERT INTO messages_envoyes (numero_telephone, messag, provider, date_envoi, statut, erreur) VALUES (?, ?, 'Infobip', NOW(), 'echec', ?)");
                $stmt->execute([$numero, $message, $result['message']]);
                
                echo json_encode([
                    'status' => 'error',
                    'message' => "Échec de l'envoi Infobip: " . $result['message']
                ]);
            }
            }
            
        } catch (Exception $e) {
        error_log("Erreur send_sms_infobip: " . $e->getMessage());
        
        // Enregistrer l'erreur dans la base de données si possible
        if (isset($numero) && isset($message)) {
            try {
            $stmt = $cnx->prepare("INSERT INTO messages_envoyes (numero_telephone, messag, provider, date_envoi, statut, erreur) VALUES (?, ?, 'Infobip', NOW(), 'echec', ?)");
            $stmt->execute([$numero, $message, $e->getMessage()]);
            } catch (Exception $dbError) {
                error_log("Erreur enregistrement DB: " . $dbError->getMessage());
            }
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
}
?>
