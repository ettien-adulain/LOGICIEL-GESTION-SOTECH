<?php
$telephone = isset($_GET['telephone']) ? $_GET['telephone'] : '';

// envoyer_sms.php
$numbers = isset($_GET['numbers']) ? explode(',', $_GET['numbers']) : [];
if (isset($_GET['sms'])) {
    $telephone = urldecode($_GET['sms']);
}
// Récupérer les informations depuis l'URL
$telephone = isset($_GET['telephone']) ? htmlspecialchars($_GET['telephone']) : '';
$client = isset($_GET['client']) ? htmlspecialchars($_GET['client']) : '';
$articles = isset($_GET['articles']) ? json_decode($_GET['articles'], true) : [];
$total = isset($_GET['total']) ? htmlspecialchars($_GET['total']) : '';
$message_from_url = isset($_GET['message']) ? urldecode($_GET['message']) : '';

// Fonction pour nettoyer le HTML et le convertir en texte simple
function cleanHtmlForSms($html) {
    // Supprimer toutes les balises HTML
    $text = strip_tags($html);
    
    // Remplacer les entités HTML
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Nettoyer les espaces multiples
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Supprimer les espaces en début et fin
    $text = trim($text);
    
    return $text;
}

// Générer le message pré-rempli
if (!empty($message_from_url)) {
    // Message venant d'un modèle personnalisé - nettoyer le HTML
    $message = cleanHtmlForSms($message_from_url);
} else {
    // Message généré automatiquement
    $message = "Cher $client,\n\nVoici les détails de votre commande :\n";
    foreach ($articles as $article) {
        $prix = number_format($article['MontantProduitTTC'], 0, ',', ' ');
        $message .= "- " . htmlspecialchars($article['libelle']) . " (Quantité: " . intval($article['Quantite']) . ", Prix: $prix F.CFA)\n";
    }
    $total_formate = is_numeric(str_replace(' ', '', $total)) ? number_format(str_replace(' ', '', $total), 0, ',', ' ') : $total;
    $message .= "\nTotal à payer : $total_formate F.CFA.\nMerci pour votre commande !";
}


// Traitement de l'envoi SMS via le système unique Infobip
if (isset($_POST['numero']) && isset($_POST['message'])) {
    header('Content-Type: application/json');
    
    try {
        $numero = trim($_POST['numero']);
        $message = trim($_POST['message']);
        
        if (empty($numero) || empty($message)) {
            throw new Exception("Numéro et message requis");
        }
        
        // Redirection vers le système unique Infobip
        $postData = http_build_query([
            'numero' => $numero,
            'message' => $message,
            'bulk' => strpos($numero, ',') !== false ? 'true' : 'false'
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postData
            ]
        ]);
        
        $response = file_get_contents('send_sms_infobip.php', false, $context);
        
        if ($response === false) {
            throw new Exception("Erreur lors de l'envoi du SMS");
        }
        
        echo $response;
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

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

$canVoir = can_user('envoyer_sms', 'voir');
$canEnvoyer = can_user('envoyer_sms', 'envoyer');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer un SMS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        .header-sms {
            background: linear-gradient(90deg, #ff0000 60%, #ff7675 100%);
            color: #fff;
            padding: 25px 0 18px 0;
            text-align: center;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.13);
            margin-bottom: 30px;
        }
        .header-sms h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 1px;
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
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(255,0,0,0.08);
        }
        .btn-navigation:hover {
            background-color: #cc0000;
            color: #fff;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.13);
            border: none;
            overflow: hidden;
        }
        .card-header {
            background: #fff0f0;
            color: #ff0000;
            font-size: 1.3rem;
            font-weight: bold;
            border-bottom: 1px solid #ffeaea;
        }
        .form-group label {
            font-weight: 500;
            color: #222;
        }
        .badge-num {
            background: #ff7675;
            color: #fff;
            font-size: 1rem;
            border-radius: 8px;
            padding: 4px 10px;
            margin: 2px 4px 2px 0;
            display: inline-block;
        }
        .btn-action {
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            margin-right: 8px;
        }
        .btn-outline-primary {
            border: 1.5px solid #007bff;
            color: #007bff;
            background: #fff;
        }
        .btn-outline-primary:hover {
            background: #007bff;
            color: #fff;
        }
        .btn-outline-danger {
            border: 1.5px solid #ff0000;
            color: #ff0000;
            background: #fff;
        }
        .btn-outline-danger:hover {
            background: #ff0000;
            color: #fff;
        }
        .form-control:focus {
            border-color: #ff7675;
            box-shadow: 0 0 0 0.2rem rgba(255,0,0,0.08);
        }
        .loader {
            display: none;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ff0000;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .toast-sms {
            position: fixed;
            top: 30px;
            right: 30px;
            background: #222;
            color: #fff;
            padding: 16px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            opacity: 0;
            pointer-events: none;
            z-index: 9999;
            transition: opacity 0.4s;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
        }
        .toast-sms.show {
            opacity: 1;
            pointer-events: auto;
        }
        @media (max-width: 600px) {
            .header-sms h1 { font-size: 1.3rem; }
            .container { padding: 0 5px; }
            .card-header { font-size: 1rem; }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    <div class="header-sms">
        <h1><i class="fas fa-sms"></i> Envoi de SMS via Infobip</h1>
        <div style="font-size:1.1rem;font-weight:400;opacity:0.85;">Contactez vos clients rapidement et efficacement avec Infobip</div>
    </div>
    <div class="container">
       
        <div class="row">
            <div class="col-md-10 offset-md-1 col-lg-8 offset-lg-2">
                <div class="card mt-3 mb-4">
                    <div class="card-header">
                        <i class="fas fa-sms"></i> Formulaire d'envoi SMS
                    </div>
                    <div class="card-body">
                        <div id="loader" class="loader"></div>
                        <form id="smsForm">
                            <div class="form-group">
                                <label for="recipientNumber">
                                    <i class="fas fa-user"></i> Numéro(s) de téléphone
                                </label>
                                <div id="num-badges">
                                    <?php if (!empty($numbers)) {
                                        foreach ($numbers as $num) {
                                            echo '<span class="badge-num">' . htmlspecialchars(trim($num)) . '</span>';
                                        }
                                    } elseif ($telephone) {
                                        echo '<span class="badge-num">' . htmlspecialchars($telephone) . '</span>';
                                    } ?>
                                </div>
                                <input type="tel" id="recipientNumber" name="phone" class="form-control mt-2" value="<?php echo htmlspecialchars($telephone ?: ''); ?><?php echo htmlspecialchars(implode(', ', $numbers)); ?>" required placeholder="Ex: +2250700000000 ou plusieurs séparés par virgule">
                            </div>
                            <div class="form-group">
                                <label for="smsMessage">
                                    <i class="fas fa-comment"></i> Message
                                </label>
                                <textarea id="message" name="message" class="form-control" rows="8" required maxlength="1600" placeholder="Tapez votre message ici (max 1600 caractères)"><?= $message; ?></textarea>
                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-action" onclick="pasteContent()" title="Coller depuis le presse-papiers">
                                            <i class="fas fa-paste"></i> Coller
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-action" onclick="document.getElementById('message').value=''" title="Effacer le message">
                                            <i class="fas fa-eraser"></i> Effacer
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-action" onclick="insertTemplate()" title="Insérer un modèle de message">
                                            <i class="fas fa-file-text"></i> Modèle
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        <span id="charCount">0</span>/1600 caractères
                                    </small>
                                </div>
                            </div>
                            <div class="form-group text-center mt-4">
<?php

    if ($canEnvoyer) {
        echo '<button type="submit" class="btn-navigation mx-auto" style="min-width:160px;font-size:1.1rem;"><i class="fas fa-paper-plane"></i> Envoyer</button>';
    } else {
        echo '<button type="button" class="btn-navigation mx-auto" style="min-width:160px;font-size:1.1rem;" disabled title="Droit d\'envoyer non autorisé" onclick="alert(\'Accès refusé\')"><i class="fas fa-paper-plane"></i> Envoyer</button>';
    }
?>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <a href="sms_personnalise.php" class="btn btn-outline-danger btn-action" title="Copier un message personnalisé">
                                <i class="fas fa-sms"></i> Copier un message personnalisé
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="toast-sms" id="toastSms"></div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
    <script>
        function showToast(message, success = true) {
            const toast = document.getElementById('toastSms');
            toast.textContent = message;
            toast.style.background = success ? '#27ae60' : '#c0392b';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2200);
        }
        function setLoader(visible) {
            document.getElementById('loader').style.display = visible ? 'block' : 'none';
        }
        document.getElementById('smsForm').addEventListener('submit', function(event) {
            event.preventDefault();
            setLoader(true);

            var input = document.getElementById('recipientNumber');
            var iti = window.intlTelInputGlobals.getInstance(input);
            let numero = iti ? iti.getNumber() : input.value.trim();
            const message = document.getElementById('message').value;

            // Validation du message
            if (message.length > 1600) {
                setLoader(false);
                return showToast('Message trop long (maximum 1600 caractères)', false);
            }

            // Si plusieurs numéros séparés par virgule, on traite chaque numéro
            let numList = numero.split(',').map(num => num.trim()).filter(num => num);
            // Validation : chaque numéro doit commencer par + et contenir uniquement des chiffres
            const isValid = numList.every(num => num.match(/^\+\d{8,15}$/));
            if (!isValid) {
                setLoader(false);
                return showToast('Format du/des numéro(s) incorrect. Exemple : +2250700000000 ou plusieurs séparés par virgule.', false);
            }

            // Déterminer le mode d'envoi
            const bulkMode = numList.length > 1;
            const formData = new URLSearchParams({ 
                numero, 
                message,
                bulk: bulkMode ? 'true' : 'false'
            });

            fetch('send_sms_infobip.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setLoader(false);
                if (data.status === 'success') {
                    showToast(data.message, true);
                    // Optionnel: vider le formulaire après envoi réussi
                    // document.getElementById('message').value = '';
                } else if (data.status === 'partial') {
                    showToast(`${data.message} (${data.success_count} succès, ${data.error_count} erreurs)`, false);
                } else {
                    showToast('Erreur d\'envoi Infobip : ' + (data.message || 'Erreur inconnue'), false);
                }
            })
            .catch(error => {
                setLoader(false);
                console.error('Erreur:', error);
                showToast('Erreur lors de l\'envoi', false);
            });
        });
        function pasteContent() {
            navigator.clipboard.readText()
                .then(text => {
                    const messageField = document.getElementById('message');
                    const currentValue = messageField.value;
                    const newValue = currentValue + text;
                    
                    if (newValue.length > 1600) {
                        showToast('Le texte à coller est trop long', false);
                        return;
                    }
                    
                    messageField.value = newValue;
                    updateCharCount();
                })
                .catch(err => {
                    showToast('Erreur lors du collage', false);
                });
        }
        
        function insertTemplate() {
            const templates = [
                "Cher client,\n\nVotre commande a été confirmée.\n\nMerci pour votre confiance !\n\nSOTECH",
                "Bonjour,\n\nNous vous informons que votre commande est prête.\n\nCordialement,\nSOTECH",
                "Cher client,\n\nVotre facture est disponible.\n\nMerci de régulariser votre situation.\n\nSOTECH"
            ];
            
            const selectedTemplate = templates[Math.floor(Math.random() * templates.length)];
            const messageField = document.getElementById('message');
            
            if (messageField.value.length + selectedTemplate.length > 1600) {
                showToast('Le modèle est trop long pour être ajouté', false);
                return;
            }
            
            messageField.value += (messageField.value ? '\n\n' : '') + selectedTemplate;
            updateCharCount();
        }
        
        function updateCharCount() {
            const messageField = document.getElementById('message');
            const charCount = document.getElementById('charCount');
            const count = messageField.value.length;
            
            charCount.textContent = count;
            
            if (count > 1400) {
                charCount.style.color = '#ff6b6b';
            } else if (count > 1000) {
                charCount.style.color = '#ffa726';
            } else {
                charCount.style.color = '#666';
            }
        }
        // Ajout de l'initialisation intl-tel-input pour le champ numéro
        document.addEventListener('DOMContentLoaded', function() {
            var input = document.querySelector("#recipientNumber");
            if (window.intlTelInput) {
                window.intlTelInput(input, {
                    initialCountry: "ci", // Côte d'Ivoire par défaut
                    preferredCountries: ["ci", "fr", "us"], // Côte d'Ivoire en favori
                    separateDialCode: true, // Affiche +225 séparé
                    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
                });
            }
            
            // Initialiser le compteur de caractères
            updateCharCount();
            
            // Ajouter l'événement de mise à jour du compteur
            document.getElementById('message').addEventListener('input', updateCharCount);
        });
    </script>
</body>

</html>