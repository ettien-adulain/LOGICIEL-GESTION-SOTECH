<?php
try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    check_access();
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit();
}

$canVoir = can_user('parametre_sms', 'voir');
$canEnregistrer = can_user('parametre_sms', 'enregistrer');

// Traitement de l'enregistrement - Système unique et solide
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEnregistrer) {
    $provider = trim($_POST['provider'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $apiSecret = trim($_POST['api_secret'] ?? '');
    $senderId = trim($_POST['sender_id'] ?? '');
    $baseUrl = trim($_POST['base_url'] ?? '');

    // Validation des données
    if (empty($apiKey) || empty($baseUrl) || empty($provider)) {
        $message = "Veuillez remplir tous les champs obligatoires (API Key, Base URL).";
        $messageType = 'danger';
    } elseif (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
        $message = "L'URL de base doit être une URL HTTPS valide.";
        $messageType = 'danger';
    } elseif (strlen($apiKey) < 10) {
        $message = "L'API Key semble trop courte.";
        $messageType = 'danger';
    } else {
        try {
            // Test de la configuration avant enregistrement
            $testResult = testInfobipConfiguration($apiKey, $baseUrl, $senderId);
            
            if ($testResult['success']) {
                // On désactive les anciennes configs actives
                $stmt = $cnx->prepare("UPDATE parametre_sms SET active = 0 WHERE provider = ?");
                $stmt->execute([$provider]);
                
                // Vérifier la structure de la table et adapter l'insertion
                $stmt = $cnx->query("DESCRIBE parametre_sms");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (in_array('base_url', $columns) && in_array('provider', $columns)) {
                    // Structure complète disponible
                    $sql = "INSERT INTO parametre_sms (provider, api_key, api_secret, sender_id, base_url, active, date_creation) VALUES (?, ?, ?, ?, ?, 1, NOW())";
                    $stmt = $cnx->prepare($sql);
                    $stmt->execute([$provider, $apiKey, $apiSecret, $senderId, $baseUrl]);
                } else {
                    // Structure simplifiée - adapter selon les colonnes disponibles
                    if (in_array('api_key', $columns) && in_array('api_secret', $columns)) {
                        $sql = "INSERT INTO parametre_sms (api_key, api_secret, sender_id) VALUES (?, ?, ?)";
                        $stmt = $cnx->prepare($sql);
                        $stmt->execute([$apiKey, $apiSecret, $senderId]);
                    } else {
                        throw new Exception("Structure de table incompatible. Exécutez fix_sms_database.php pour corriger.");
                    }
                }
                
                $message = "Configuration " . strtoupper($provider) . " enregistrée et testée avec succès !";
                $messageType = 'success';
                
                // Log de l'action
                error_log("Configuration SMS Infobip mise à jour par l'utilisateur: " . ($_SESSION['nom_utilisateur'] ?? 'Inconnu'));
                
            } else {
                $message = "Test de configuration échoué : " . $testResult['message'];
                $messageType = 'warning';
            }
        } catch (Exception $e) {
            $message = "Erreur lors de l'enregistrement : " . $e->getMessage();
            $messageType = 'danger';
            error_log("Erreur configuration SMS: " . $e->getMessage());
        }
    }
}

// Fonction de test de la configuration Infobip
function testInfobipConfiguration($apiKey, $baseUrl, $senderId) {
    try {
        $url = rtrim($baseUrl, '/') . '/account/1/balance';
        
        $headers = [
            'Authorization: App ' . $apiKey,
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return ['success' => false, 'message' => 'Erreur de connexion: ' . $curlError];
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['balance'])) {
                return [
                    'success' => true, 
                    'message' => 'Configuration valide. Solde: ' . $data['balance'] . ' EUR'
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Configuration invalide (HTTP ' . $httpCode . ')'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur de test: ' . $e->getMessage()];
    }
}

// Charger les configs existantes - adapter selon la structure de la table
try {
    // Vérifier la structure de la table
    $stmt = $cnx->query("DESCRIBE parametre_sms");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('base_url', $columns) && in_array('provider', $columns)) {
        // Structure complète
        $sql = "SELECT provider, api_key, api_secret, sender_id, base_url FROM parametre_sms WHERE active = 1 ORDER BY id DESC";
    } else {
        // Structure simplifiée
        $sql = "SELECT api_key, api_secret, sender_id FROM parametre_sms ORDER BY id DESC LIMIT 1";
    }
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les configs
    $config = [];
    if (in_array('base_url', $columns) && in_array('provider', $columns)) {
        foreach ($configs as $conf) {
            $config[$conf['provider']] = $conf;
        }
    } else {
        // Structure simplifiée - utiliser les valeurs par défaut
        if (!empty($configs)) {
            $config['infobip'] = [
                'api_key' => $configs[0]['api_key'] ?? '',
                'api_secret' => $configs[0]['api_secret'] ?? '',
                'sender_id' => $configs[0]['sender_id'] ?? '',
                'base_url' => 'https://api.infobip.com' // Valeur par défaut
            ];
        }
    }
} catch (Exception $e) {
    $config = [];
    error_log("Erreur chargement configs: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètre SMS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .user-info span {
            font-weight: italic;
        }

        .user-info {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fff;
            padding: 10px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            top: 0;
            right: 0;
            margin: 20px;
            font-size: 1.2em;
            color: #333;
            position: absolute;
            top: 10px;
            z-index: 1001;
        }

        .user-info {
            animation: clignote 1s linear infinite;
        }

        @keyframes clignote {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0;
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f0f0;
            perspective: 1000px;
            overflow: hidden;
        }

        .container {
            margin-top: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            transform: rotateX(10deg);
            transition: transform 0.5s;
            margin-bottom: 20px;
            text-align: center;
        }

        .card:hover {
            transform: scale(1.05) rotateX(5deg);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background-color: #ff0000;
            color: white;
            font-size: 1.25em;
            padding: 15px;
        }

        .card-body {
            padding: 20px;
        }

        .card-body i {
            font-size: 3em;
            color: #ff0000;
        }

        .card-body p {
            margin-top: 10px;
            font-size: 1.2em;
        }

        .card-link {
            text-decoration: none;
            color: black;
            font-weight: bold;
        }

        .card-link:hover {
            color: #ff0000;
        }

        .loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            transition: opacity 0.5s ease;
            opacity: 0;
            visibility: hidden;
        }

        .loader {
            position: relative;
            width: 100px;
            height: 100px;
            border: 10px solid #f3f3f3;
            border-radius: 50%;
            border-top: 10px solid #3498db;
            animation: spin 1s linear infinite;
        }

        .logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: url('logo.png') no-repeat center center;
            background-size: contain;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .title-wrapper {
            text-align: center;
            margin: 20px 0;
            perspective: 1000px;
        }

        .title {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 20px 30px;
            border-radius: 15px;
            font-size: 2em;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transform: rotateX(10deg);
            transition: transform 0.5s, box-shadow 0.5s;
        }

        .title:hover {
            transform: scale(1.1) rotateX(5deg);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5);
        }

        .form-group {
            text-align: left;
        }

        .navigation-links {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .btn-navigation {
            background-color: #ff0000;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn-navigation:hover {
            background-color: #cc0000;
            text-decoration: none;
            color: white;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    <div class="container">
        <div class="title-wrapper">
            <div class="title">Paramètre SMS</div>
        </div>
        <div class="container">
            <h2 class="my-4 text-center">Configuration SMS Infobip - Système Unique</h2>
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Configuration Infobip unique -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-sms"></i> Configuration Infobip</h5>
                </div>
                <div class="card-body">
                    <form id="infobipForm" method="post" action="">
                        <input type="hidden" name="provider" value="infobip">
                        <div class="form-group">
                            <label for="infobip_api_key">API Key Infobip *</label>
                            <div class="input-group">
                                <input type="text" id="infobip_api_key" name="api_key" class="form-control" required
                                    value="<?= htmlspecialchars($config['infobip']['api_key'] ?? '') ?>"
                                    placeholder="Ex: 1234567890abcdef1234567890abcdef"
                                    minlength="10">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" onclick="toggleApiKey()">
                                        <i class="fas fa-eye" id="apiKeyIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Trouvez votre API Key dans le dashboard Infobip > Account Settings > API Keys
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="infobip_base_url">Base URL Infobip *</label>
                            <input type="text" id="infobip_base_url" name="base_url" class="form-control" required
                                value="<?= htmlspecialchars($config['infobip']['base_url'] ?? 'https://api.infobip.com') ?>"
                                placeholder="https://api.infobip.com">
                            <small class="form-text text-muted">URL de base de l'API Infobip</small>
                        </div>
                        <div class="form-group">
                            <label for="infobip_sender_id">Sender ID Infobip</label>
                            <input type="text" id="infobip_sender_id" name="sender_id" class="form-control"
                                value="<?= htmlspecialchars($config['infobip']['sender_id'] ?? '') ?>"
                                placeholder="Ex: VotreEntreprise">
                            <small class="form-text text-muted">Nom d'expéditeur (optionnel)</small>
                        </div>
                        <div class="form-group">
                            <label for="infobip_api_secret">API Secret Infobip *</label>
                            <input type="password" id="infobip_api_secret" name="api_secret" class="form-control" required
                                value="<?= htmlspecialchars($config['infobip']['api_secret'] ?? '') ?>"
                                placeholder="Votre secret API">
                            <button type="button" id="toggleInfobipSecret" class="btn btn-outline-secondary mt-2">Afficher/Masquer</button>
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-info btn-lg mr-3" onclick="testConfiguration()">
                                <i class="fas fa-vial"></i> Tester la Configuration
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Enregistrer Configuration Infobip
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Informations Infobip -->
            <div class="mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Informations Infobip</h5>
                    </div>
                    <div class="card-body">
                        <h6>Comment obtenir vos identifiants Infobip :</h6>
                        <ol>
                            <li>Créez un compte sur <a href="https://portal.infobip.com" target="_blank">portal.infobip.com</a></li>
                            <li>Allez dans <strong>Account Settings > API Keys</strong></li>
                            <li>Créez une nouvelle API Key</li>
                            <li>Copiez l'API Key et l'API Secret</li>
                        </ol>
                        
                        <h6>Gestion des fonds :</h6>
                        <ul>
                            <li>Connectez-vous à votre dashboard Infobip</li>
                            <li>Allez dans <strong>Account > Billing</strong></li>
                            <li>Rechargez votre compte via carte bancaire ou virement</li>
                            <li>Le coût est d'environ 0.05-0.15€ par SMS selon le pays</li>
                        </ul>
                        
                        <h6>Avantages Infobip :</h6>
                        <ul>
                            <li>✅ Couverture mondiale (200+ pays)</li>
                            <li>✅ API REST moderne et fiable</li>
                            <li>✅ Support technique 24/7</li>
                            <li>✅ Tarifs compétitifs</li>
                            <li>✅ Délivrabilité élevée</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Diagnostic des droits -->
            <?php
            echo '<div style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;">';
            echo '<strong>Diagnostic des droits pour la page "Paramètre SMS" :</strong><br>';
            echo 'Droit VOIR : ' . ($canVoir ? 'AUTORISÉ' : 'REFUSÉ') . '<br>';
            echo 'Droit ENREGISTRER : ' . ($canEnregistrer ? 'AUTORISÉ' : 'REFUSÉ') . '<br>';
            echo 'ID Utilisateur : ' . (isset($_SESSION['id_utilisateur']) ? $_SESSION['id_utilisateur'] : 'NON DÉFINI') . '<br>';
            echo 'Rôle : ' . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NON DÉFINI');
            echo '</div>';
            ?>
        </div>
    </div>
    <script>
        // Toggle pour Infobip Secret
        document.getElementById('toggleInfobipSecret').addEventListener('click', function () {
            const apiSecretInput = document.getElementById('infobip_api_secret');
            apiSecretInput.type = apiSecretInput.type === 'password' ? 'text' : 'password';
        });
        
        // Toggle pour API Key
        function toggleApiKey() {
            const apiKeyInput = document.getElementById('infobip_api_key');
            const apiKeyIcon = document.getElementById('apiKeyIcon');
            
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                apiKeyIcon.className = 'fas fa-eye-slash';
            } else {
                apiKeyInput.type = 'password';
                apiKeyIcon.className = 'fas fa-eye';
            }
        }
        
        // Test de la configuration
        function testConfiguration() {
            const apiKey = document.getElementById('infobip_api_key').value.trim();
            const baseUrl = document.getElementById('infobip_base_url').value.trim();
            const senderId = document.getElementById('infobip_sender_id').value.trim();
            
            if (!apiKey || !baseUrl) {
                alert('Veuillez remplir l\'API Key et la Base URL pour tester');
                return;
            }
            
            if (!baseUrl.startsWith('https://')) {
                alert('L\'URL de base doit commencer par https://');
                return;
            }
            
            // Afficher un loader
            const testBtn = event.target;
            const originalText = testBtn.innerHTML;
            testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test en cours...';
            testBtn.disabled = true;
            
            // Simuler le test (en réalité, ceci devrait faire un appel AJAX)
            setTimeout(() => {
                testBtn.innerHTML = originalText;
                testBtn.disabled = false;
                alert('Test de configuration simulé. En production, ceci testera réellement la connexion Infobip.');
            }, 2000);
        }
        
        // Validation du formulaire Infobip
        document.getElementById('infobipForm').addEventListener('submit', function(e) {
            const apiKey = document.getElementById('infobip_api_key').value.trim();
            const baseUrl = document.getElementById('infobip_base_url').value.trim();
            const apiSecret = document.getElementById('infobip_api_secret').value.trim();
            
            if (!apiKey || !baseUrl || !apiSecret) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires (marqués avec *)');
                return false;
            }
            
            // Validation de l'URL
            if (!baseUrl.startsWith('https://')) {
                e.preventDefault();
                alert('L\'URL de base doit commencer par https://');
                return false;
            }
            
            // Validation de la longueur de l'API Key
            if (apiKey.length < 10) {
                e.preventDefault();
                alert('L\'API Key semble trop courte');
                return false;
            }
            
            // Confirmation avant enregistrement
            if (!confirm('Êtes-vous sûr de vouloir enregistrer cette configuration ? Elle sera testée automatiquement.')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Masquer l'API Key par défaut
        document.addEventListener('DOMContentLoaded', function() {
            const apiKeyInput = document.getElementById('infobip_api_key');
            if (apiKeyInput.value) {
                apiKeyInput.type = 'password';
            }
        });
    </script>
</body>
</html>