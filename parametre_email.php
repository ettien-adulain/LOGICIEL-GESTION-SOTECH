<?php

require_once 'fonction_traitement/fonction.php';
check_access();
include('db/connecting.php'); // Connexion à la base de données


$message = ''; // Variable pour stocker les messages

// Vérifie si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtpServer = $_POST['smtpServer'];
    $smtpPort = $_POST['smtpPort'];
    $emailAddress = $_POST['emailAddress'];
    $emailPassword = $_POST['emailPassword'];

    if (!$smtpServer || !$smtpPort || !$emailAddress || !$emailPassword) {
        $message = "Erreur : Veuillez remplir correctement tous les champs.";
    } else {
        try {
            $stmt = $cnx->prepare("
                INSERT INTO email_configuration (smtp_server, smtp_port, email_address, email_password, created_at) 
                VALUES (:smtpServer, :smtpPort, :emailAddress, :emailPassword, NOW())
            ");

            $stmt->bindValue(':smtpServer', $smtpServer, PDO::PARAM_STR);
            $stmt->bindValue(':smtpPort', $smtpPort, PDO::PARAM_INT);
            $stmt->bindValue(':emailAddress', $emailAddress, PDO::PARAM_STR);
            $stmt->bindValue(':emailPassword', $emailPassword, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $message = "Paramètres enregistrés avec succès !";
            } else {
                $message = "Erreur lors de l'enregistrement des paramètres.";
            }
        } catch (PDOException $e) {
            $message = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètre E-mail</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .user-info i {
            margin-right: 10px;
            color: #ff0000;
        }

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

        .modal-content {
            border-radius: 15px;
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

        .success-message {
            color: green;
            font-weight: bold;
            text-align: center;
            margin-top: 10px;
        }

        .error-message {
            color: red;
            font-weight: bold;
            text-align: center;
            margin-top: 10px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
        }

        .form-group {
            position: relative;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <div class="container">
        <br><br>
        
        <!-- Barre de chargement -->
        <div class="loader-wrapper" id="loader">
            <div class="loader">
                <div class="logo"></div>
            </div>
        </div>
        <div class="user-info">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars($_SESSION['nom_utilisateur']); ?> est connecté</span>
        </div>
        <div class="container">
            <!-- Titre -->
            <div class="title-wrapper">
                <div class="title">Paramètre E-mail</div>
            </div>

            <div class="row">
                <?php if (!empty($message)) : ?>
                    <div class="<?= strpos($message, 'succès') !== false ? 'success-message' : 'error-message'; ?>">
                        <?= htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-cogs"></i> Configuration des E-mails
                        </div>
                        <div class="card-body">
                            <form id="emailSettingsForm" method="post">
                                <div class="form-group">
                                    <label for="smtpServer">Serveur SMTP</label>
                                    <input type="text" class="form-control" id="smtpServer" name="smtpServer" placeholder="smtp.exemple.com">
                                </div>
                                <div class="form-group">
                                    <label for="smtpPort">Port SMTP</label>
                                    <input type="text" class="form-control" id="smtpPort" name="smtpPort" placeholder="465">
                                </div>
                                <div class="form-group">
                                    <label for="emailAddress">Adresse E-mail</label>
                                    <input type="email" class="form-control" id="emailAddress" name="emailAddress" placeholder="votre-email@exemple.com">
                                </div>
                                <div class="form-group">
                                    <label for="emailPassword">Mot de passe</label>
                                    <input type="password" class="form-control" id="emailPassword" name="emailPassword" placeholder="Mot de passe" required>
                                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                                </div>
                                <button type="submit" name="Enregistrer" class="btn btn-primary">Enregistrer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        <script>
            // Afficher/Masquer le mot de passe
            const togglePassword = document.querySelector("#togglePassword");
            const passwordField = document.querySelector("#emailPassword");

            togglePassword.addEventListener("click", () => {
                const type = passwordField.type === "password" ? "text" : "password";
                passwordField.type = type;
                togglePassword.classList.toggle("fa-eye-slash");
            });

            // Sauvegarder les données dans le localStorage
            const form = document.querySelector("#emailSettingsForm");

            // Charger les données sauvegardées
            window.onload = function() {
                if (localStorage.getItem("smtpServer")) {
                    document.querySelector("#smtpServer").value = localStorage.getItem("smtpServer");
                    document.querySelector("#smtpPort").value = localStorage.getItem("smtpPort");
                    document.querySelector("#emailAddress").value = localStorage.getItem("emailAddress");
                    document.querySelector("#emailPassword").value = localStorage.getItem("emailPassword");
                }
            };

            form.addEventListener("input", () => {
                localStorage.setItem("smtpServer", document.querySelector("#smtpServer").value);
                localStorage.setItem("smtpPort", document.querySelector("#smtpPort").value);
                localStorage.setItem("emailAddress", document.querySelector("#emailAddress").value);
                localStorage.setItem("emailPassword", document.querySelector("#emailPassword").value);
            });
            // JavaScript pour déplacer le curseur en fonction des touches
            function moveFocus(currentElement, direction) {
                let formElements = Array.from(document.querySelectorAll('input, button'));
                let currentIndex = formElements.indexOf(currentElement);

                if (direction === 'next' && currentIndex < formElements.length - 1) {
                    formElements[currentIndex + 1].focus();
                } else if (direction === 'previous' && currentIndex > 0) {
                    formElements[currentIndex - 1].focus();
                }
            }

            document.querySelectorAll('input').forEach(function(inputElement) {
                inputElement.addEventListener('keydown', function(event) {
                    if (event.key === 'ArrowDown' || event.key === 'Enter') {
                        event.preventDefault();
                        moveFocus(inputElement, 'next');
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        moveFocus(inputElement, 'previous');
                    } else if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        moveFocus(inputElement, 'next');
                    } else if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        moveFocus(inputElement, 'previous');
                    }
                });
            });
        </script>
</body>

</html>