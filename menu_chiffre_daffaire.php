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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Chiffre d'Affaire</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f0f0;
            perspective: 1000px;
            overflow: hidden;
        }
        .top-buttons {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 15px;
            margin: 20px 0 10px 0;
        }
        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(90deg, #ff0000 0%, #cc0000 100%);
            color: #fff !important;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 1.1em;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: background 0.2s, transform 0.2s;
            text-decoration: none;
        }
        .btn-nav:hover {
            background: linear-gradient(90deg, #cc0000 0%, #ff0000 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.04);
            text-decoration: none;
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
        .user-info {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fff;
            padding: 10px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin: 20px 0;
            font-size: 1.2em;
            color: #333;
        }
        .card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            transition: transform 0.5s;
            margin-bottom: 20px;
            text-align: center;
        }
        .card:hover {
            transform: scale(1.05);
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
        .card-link {
            text-decoration: none;
            color: black;
            font-weight: bold;
        }
        .card-link:hover {
            color: #ff0000;
        }
        @media (max-width: 600px) {
            .top-buttons { flex-direction: column; gap: 8px; }
            .title { font-size: 1.2em; padding: 10px 12px; }
        }
    </style>
</head>
<body>
    <div class="loader-wrapper" id="loader">
        <div class="loader">
            <div class="logo"></div>
        </div>
    </div>

    <div class="container">
       
        <div class="title-wrapper">
            <div class="title">Menu Chiffre d'Affaire</div>
        </div>
        
        <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/theme_switcher.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
        
        

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <a href="chiffre_daffaire_horaire.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-clock"></i> Chiffre d'Affaire Horaire
                        </div>
                        <div class="card-body">
                            <p>Consultez le chiffre d'affaire par heure</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <a href="chiffre_daffaire_mensuel.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-calendar-alt"></i> Chiffre d'Affaire Mensuel
                        </div>
                        <div class="card-body">
                            <p>Consultez le chiffre d'affaire mensuel</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <a href="chiffre_daffaire_annuel.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-calendar-year"></i> Chiffre d'Affaire Annuel
                        </div>
                        <div class="card-body">
                            <p>Consultez le chiffre d'affaire annuel</p>
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
        });
    </script>
</body>
</html>
