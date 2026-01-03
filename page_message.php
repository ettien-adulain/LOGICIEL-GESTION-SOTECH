<?php

require_once 'fonction_traitement/fonction.php';
check_access();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Messages</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    right:0;
    margin: 20px;
    font-size: 1.2em;
    color: #333;
    position: absolute;
    top: 10px;
    z-index: 1001;
}
.user-info{
    animation:clignote 1s linear infinite;
}
@keyframes clignote{
    0%,100%{ opacity:1;}
    50%{opacity:0;}
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    
<?php include('includes/user_indicator.php'); ?>
<?php include('includes/navigation_buttons.php'); ?>  
    <!-- Barre de chargement -->
    <div class="loader-wrapper" id="loader">
        <div class="loader">
            <div class="logo"></div>
        </div>
    </div>
    <div class="user-info">
    <i class="fas fa-user"></i>
    <span><?php echo htmlspecialchars($_SESSION['nom_utilisateur']);?> est connecté</span>
</div>

    <div class="container">
        <!-- Titre -->
        <div class="title-wrapper">
            <div class="title">Espace Message</div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <a href="sms_personnalise.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-envelope"></i> 
                        </div>
                        <div class="card-body">
                            <p>PERSONNALISE SMS</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="e_mail_personalise.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="card-body">
                            <p>PERSONNALISE EMAIL</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hide the loader after a delay
            setTimeout(() => {
                const loader = document.getElementById('loader');
                loader.style.opacity = '0';
                loader.style.visibility = 'hidden';
            }, 1000); // 1 second

            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('mousemove', function(e) {
                    let x = (e.clientX / window.innerWidth) * 20 - 10;
                    let y = (e.clientY / window.innerHeight) * 20 - 10;
                    card.style.transform = `rotateX(${y}deg) rotateY(${x}deg)`;
                });
                card.addEventListener('mouseleave', function() {
                    card.style.transform = `rotateX(10deg)`;
                });
            });
        });
    </script>
</body>
</html>
