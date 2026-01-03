<?php
try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    // PAS de contrôle d'accès ici sur le menu principal Vente
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
    <title>Espace Vente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body id="vente">
    <!-- Barre de chargement -->
    <div class="loader-wrapper" id="loader">
        <div class="loader">
            <div class="logo"></div>
        </div>
    </div>

    <div class="container">
        <!-- Titre -->
        <div class="title-wrapper">
            <h1 class="title">Espace Vente</h1>
        </div>
            
        <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/theme_switcher.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>

            <?php
                if (isset($_GET['success'])) {
                    $successMessage = htmlspecialchars($_GET['success']);
                    echo '<div id="success-alert" class="alert alert-success" role="alert">' . $successMessage . '</div>';
                }
                if (isset($_GET['error'])) {
                    $errorMessage = htmlspecialchars($_GET['error']);
                    echo '<div id="error-alert" class="alert alert-danger" role="alert">' . $errorMessage . '</div>';
                }
            ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <a href="caisse.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-cash-register"></i> Caisse
                        </div>
                        <div class="card-body">
                            <p>Caisse du jour</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="listes_vente.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-list-ul"></i> Liste des ventes
                        </div>
                        <div class="card-body">
                            <p>Liste complète des ventes</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="vente_jour.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-calendar-day"></i> Vente du jour
                        </div>
                        <div class="card-body">
                            <p>Ventes enregistrées aujourd'hui</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="versement.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-money-bill-wave"></i> Versement
                        </div>
                        <div class="card-body">
                            <p>Gestion des versements</p>
                        </div>
                    </a>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <a href="menu_vente_credit.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-cash-register"></i> Vente à crédit
                        </div>
                        <div class="card-body">
                            <p>Vente à crédit</p>
                            <p>Espace suivi</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        setTimeout(function() {
            var errorAlert = document.getElementById('error-alert');
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }

            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState(null, null, url);
            }
        }, 4000);
    </script>
</body>
</html>
