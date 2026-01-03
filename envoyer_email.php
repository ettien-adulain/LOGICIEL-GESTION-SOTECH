<?php
try {
    include('db/connecting.php');
    session_start();
    require_once 'fonction_traitement/fonction.php';
    check_access();
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit();
}
$id = $_GET['id'] ?? null;
$email = $_GET['email'] ?? null;

// Utilisez $email pour envoyer l'email...
$emails = isset($_GET['emails']) ? explode(',', $_GET['emails']) : [];


// Récupérer les informations depuis l'URL ou POST
$email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '');
$client = isset($_POST['client']) ? htmlspecialchars($_POST['client']) : (isset($_GET['client']) ? htmlspecialchars($_GET['client']) : '');
$articles = isset($_GET['articles']) ? json_decode($_GET['articles'], true) : [];
$total = isset($_GET['total']) ? htmlspecialchars($_GET['total']) : '';
$message_from_url = isset($_POST['message']) ? $_POST['message'] : (isset($_GET['message']) ? urldecode($_GET['message']) : '');
$numero_vente = isset($_POST['numero_vente']) ? htmlspecialchars($_POST['numero_vente']) : (isset($_GET['numero_vente']) ? htmlspecialchars($_GET['numero_vente']) : '');

// Debug initial (commenté pour la production)
// echo "<div style='background: #e8f4fd; padding: 10px; margin: 10px 0; border: 1px solid #007bff;'>";
// echo "<h4>Debug Initial - Données récupérées :</h4>";
// echo "<p><strong>Email initial:</strong> " . htmlspecialchars($email) . "</p>";
// echo "<p><strong>Client initial:</strong> " . htmlspecialchars($client) . "</p>";
// echo "<p><strong>Message initial:</strong> " . (strlen($message_from_url) > 100 ? substr($message_from_url, 0, 100) . '...' : $message_from_url) . "</p>";
// echo "<p><strong>Numéro vente initial:</strong> " . htmlspecialchars($numero_vente) . "</p>";
// echo "</div>";

// Fonction pour nettoyer et formater le texte pour email
function cleanTextForEmail($text) {
    // Si c'est du HTML, le convertir en texte simple
    if (strpos($text, '<') !== false) {
        // Supprimer toutes les balises HTML
        $text = strip_tags($text);
        
        // Décoder les entités HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Remplacer les sauts de ligne multiples par des simples
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
    }
    
    // Nettoyer les espaces multiples
    $text = preg_replace('/[ \t]+/', ' ', $text);
    
    return trim($text);
}

// Générer le message pré-rempli
if (!empty($message_from_url)) {
    // Message venant d'un modèle personnalisé ou de la liste des ventes
    if (isset($_POST['message'])) {
        // Message venant de POST (liste des ventes) - nettoyer le texte
        $message = cleanTextForEmail($message_from_url);
    } else {
        // Message venant d'URL (modèles personnalisés) - nettoyer le texte
        $message = cleanTextForEmail($message_from_url);
    }
} else {
    // Message généré automatiquement
    $message = "Cher $client,\n\nVoici les détails de votre commande :\n";
    foreach ($articles as $article) {
        $message .= "- " . htmlspecialchars($article['libelle']) . " (Quantité: " . intval($article['Quantite']) . ", Prix: " . number_format($article['MontantProduitTTC'], 2) . " F.CFA)\n";
    }
    $message .= "\nTotal à payer : $total F.CFA.\nMerci pour votre commande !";
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Charger PHPMailer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données depuis le formulaire
    $email = $_POST['recipientEmail'] ?? $email; // Utiliser l'email déjà récupéré si pas de recipientEmail
    $subject = $_POST['emailSubject'] ?? 'Sans Sujet'; // Sujet par défaut
    $message = $_POST['message'] ?? $message; // Utiliser le message déjà récupéré si pas de message dans le formulaire
    $attachment = $_POST['emailAttachment'] ?? 'Message vide'; // Message par défaut
    // Valider et diviser les e-mails
    // Debug : Afficher les données reçues (commenté pour la production)
    // echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
    // echo "<h4>Debug - Données reçues :</h4>";
    // echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
    // echo "<p><strong>Client:</strong> " . htmlspecialchars($client) . "</p>";
    // echo "<p><strong>Numéro vente:</strong> " . htmlspecialchars($numero_vente) . "</p>";
    // echo "<p><strong>POST data:</strong> " . print_r($_POST, true) . "</p>";
    // echo "</div>";
    
    // Diviser les adresses e-mails
    if (empty($email)) {
        die("<p style='color:red;'>Erreur : Aucun e-mail valide fourni.</p>");
    }
    // Diviser les adresses e-mails par des virgules
    $emailArray = array_map('trim', explode(',', $email)); // Nettoyer les espaces
    if (empty($emailArray)) {
        die("<p style='color:red;'>Erreur : Aucun e-mail valide fourni.</p>");
    }
    print_r($email);

    // Validation des champs
    if (empty($email) || empty($subject) || empty($message)) {
        die("<p style='color:red;'>Erreur : Veuillez remplir tous les champs requis.</p>");
    }


    // Récupérer les paramètres SMTP depuis la base de données
    $query = $cnx->prepare("SELECT smtp_server, smtp_port, email_address, email_password FROM email_configuration ORDER BY created_at DESC LIMIT 1");
    $query->execute();
    $config = $query->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo "<p style='color:red;'>Erreur : Impossible de charger la configuration e-mail. <a href='parametre_email.php'>Configurez vos paramètres SMTP ici</a>.</p>";
    }

    // Initialisation de PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = $config['smtp_server'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['email_address'];
        $mail->Password = $config['email_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp_port'];

        // Configuration de l'expéditeur et du destinataire
        $mail->setFrom($config['email_address'], 'SOTech');
        // Ajouter chaque adresse en BCC (copie cachée) pour la confidentialité
        foreach ($emailArray as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($email); // BCC = Chacun ne voit que son email
            } else {
                echo "<div class='alert alert-warning'>Adresse e-mail invalide ignorée : $email</div>";
            }
        }



        if (isset($_FILES['emailAttachment']) && $_FILES['emailAttachment']['error'] === UPLOAD_ERR_OK) {
            $attachmentTmpPath = $_FILES['emailAttachment']['tmp_name'];
            $attachmentName = $_FILES['emailAttachment']['name'];
            $destinationDir = __DIR__ . '/Image_article/';
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0777, true);
            }
            $destinationPath = $destinationDir . basename($attachmentName);
            // Si un fichier du même nom existe déjà, on ajoute un suffixe unique
            $baseName = pathinfo($attachmentName, PATHINFO_FILENAME);
            $extension = pathinfo($attachmentName, PATHINFO_EXTENSION);
            $i = 1;
            while (file_exists($destinationPath)) {
                $attachmentName = $baseName . '_' . $i . ($extension ? ('.' . $extension) : '');
                $destinationPath = $destinationDir . $attachmentName;
                $i++;
            }
            move_uploaded_file($attachmentTmpPath, $destinationPath);
            $attachmentToSave = $attachmentName;
            $mail->addAttachment($destinationPath, $attachmentName);
        } else {
            $attachmentToSave = null;
        }
        $mail->isHTML(true);
        $mail->Subject = htmlspecialchars($subject);
        $mail->Body = nl2br(htmlspecialchars($message));
        $mail->AltBody = htmlspecialchars($message);

        // Envoi de l'e-mail
        $mail->send();
        echo "<div class='alert alert-success'>E-mails envoyés avec succès !</div>";
        $stmt = $cnx->prepare("INSERT INTO emails (adresse_email, Objet, messag, attachment, date_envoi) VALUES (:adresse_email, :Objet, :messag, :attachment, NOW())");
        foreach ($emailArray as $email) {
            $stmt->execute([
                ':adresse_email' => $email,
                ':Objet' => $subject,
                ':messag' => $message,
                ':attachment' => $attachmentToSave,
            ]);
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Erreur lors de l'envoi de l'e-mail : {$mail->ErrorInfo}</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer un E-mail</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        .header-email {
            background: linear-gradient(90deg, #ff0000 60%, #ff7675 100%);
            color: #fff;
            padding: 25px 0 18px 0;
            text-align: center;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.13);
            margin-bottom: 30px;
        }
        .header-email h1 {
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
        .badge-email {
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
        .toast-email {
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
        .toast-email.show {
            opacity: 1;
            pointer-events: auto;
        }
        @media (max-width: 600px) {
            .header-email h1 { font-size: 1.3rem; }
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

    <div class="header-email">
        <h1><i class="fas fa-envelope"></i> Envoi d'E-mail Professionnel</h1>
        <div style="font-size:1.1rem;font-weight:400;opacity:0.85;">Contactez vos clients rapidement et efficacement</div>
    </div>
    <div class="container">
       
        <div class="row">
            <div class="col-md-10 offset-md-1 col-lg-8 offset-lg-2">
                <div class="card mt-3 mb-4">
                    <div class="card-header">
                        <i class="fas fa-envelope"></i> Formulaire d'envoi E-mail
                    </div>
                    <div class="card-body">
                        <div id="loader" class="loader"></div>
                        <form id="emailForm" enctype="multipart/form-data" method="post">
                            <div class="form-group">
                                <label for="recipientEmail">
                                    <i class="fas fa-user"></i> Adresse(s) E-mail
                                </label>
                                <div id="email-badges">
                                    <?php if (!empty($emails)) {
                                        foreach ($emails as $em) {
                                            echo '<span class="badge-email">' . htmlspecialchars(trim($em)) . '</span>';
                                        }
                                    } elseif ($email) {
                                        echo '<span class="badge-email">' . htmlspecialchars($email) . '</span>';
                                    } ?>
                                </div>
                                <input type="text" class="form-control mt-2" id="recipientEmail" name="recipientEmail" placeholder="email@example.com ou plusieurs séparés par virgule" value="<?php echo htmlspecialchars($email ?: ''); ?><?php echo htmlspecialchars(implode(', ', $emails)); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="emailSubject">
                                    <i class="fas fa-tag"></i> Sujet
                                </label>
                                <input type="text" class="form-control" id="emailSubject" name="emailSubject" placeholder="Sujet de l'e-mail" required>
                            </div>
                            <div class="form-group">
                                <label for="emailMessage">
                                    <i class="fas fa-comment"></i> Message
                                </label>
                                <textarea id="message" name="message" class="form-control" required rows="8" placeholder="Tapez votre message ici..."><?= htmlspecialchars($message); ?></textarea>
                                <div class="mt-2 d-flex flex-wrap gap-2">
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
                            </div>
                            <div class="form-group">
                                <label for="emailAttachment" class="d-flex align-items-center">
                                    <i class="fas fa-paperclip mr-2"></i> Pièce jointe
                                </label>
                                <input type="file" class="form-control-file" id="emailAttachment" name="emailAttachment">
                                <div id="fileName">Aucun fichier choisi</div>
                                <div id="filePreview"></div>
                            </div>
                            <div class="form-group text-center mt-4">
                                <button type="submit" class="btn-navigation mx-auto" style="min-width:160px;font-size:1.1rem;">
                                    <i class="fas fa-paper-plane"></i> Envoyer
                                </button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <a href="e_mail_personalise.php" class="btn btn-outline-danger btn-action" title="Copier un e-mail personnalisé">
                                <i class="fas fa-envelope"></i> Copier un e-mail personnalisé
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="toast-email" id="toastEmail"></div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function showToast(message, success = true) {
            const toast = document.getElementById('toastEmail');
            toast.textContent = message;
            toast.style.background = success ? '#27ae60' : '#c0392b';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2200);
        }
        function setLoader(visible) {
            document.getElementById('loader').style.display = visible ? 'block' : 'none';
        }
        document.getElementById('emailForm').addEventListener('submit', function(event) {
            setLoader(true);
            // Laisse le submit normal PHP, mais affiche le loader
        });
        document.getElementById('emailAttachment').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const fileName = file ? file.name : 'Aucun fichier choisi';
            document.getElementById('fileName').textContent = fileName;
            const filePreview = document.getElementById('filePreview');
            filePreview.innerHTML = '';
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        filePreview.appendChild(img);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
        function pasteContent() {
            navigator.clipboard.readText()
                .then(text => {
                    document.getElementById('message').value += text;
                })
                .catch(err => {
                    showToast('Erreur lors du collage', false);
                });
        }
        
        function insertTemplate() {
            const templates = [
                "Confirmation de commande\n\nBonjour [NOM_CLIENT],\n\nNous vous confirmons la réception de votre commande.\n\nMerci pour votre confiance !\n\nCordialement,\nL'équipe SOTECH",
                "Facture disponible\n\nBonjour [NOM_CLIENT],\n\nVotre facture est disponible.\n\nMerci de régulariser votre situation.\n\nCordialement,\nL'équipe SOTECH",
                "Promotion spéciale\n\nBonjour [NOM_CLIENT],\n\nDécouvrez notre nouvelle offre !\n\nProfitez de cette promotion limitée.\n\nL'équipe SOTECH"
            ];
            
            const selectedTemplate = templates[Math.floor(Math.random() * templates.length)];
            const messageField = document.getElementById('message');
            messageField.value += (messageField.value ? '\n\n' : '') + selectedTemplate;
        }
    </script>
</body>

</html>