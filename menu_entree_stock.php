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
    <title>Espace Commande</title>
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
            <h1 class="title">Espace Entree en Stock</h1>
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
            <div class="col-md-4">
                <div class="card">
                    <a href="entre_stock.php" class="card-link">
                        <div class="card-header">
                        <i class="fas fa-receipt"></i> Entree en stock
                        </div>
                        <div class="card-body">
                            <p>Créez un Entree en stock</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <a href="liste_entree_stock.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-list"></i> liste des Entrees_Stock
                        </div>
                        <div class="card-body">
                            <p>Consultez tous les Entrees en Stock</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <a href="correction_stock.php" class="card-link">
                    <div class="card-header">
                            <i class="fas fa-file-invoice"></i> Correction de Stock
                        </div>
                        <div class="card-body">
                            <p>Executer une Correction</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <a href="liste_correction_stock.php" class="card-link">
                    <div class="card-header">
                            <i class="fas fa-file-invoice"></i>Liste de correction
                        </div>
                        <div class="card-body">
                            <p>consultez la liste des correction</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <a href="liste_stock.php" class="card-link">
                    <div class="card-header">
                            <i class="fas fa-file-invoice"></i>Liste de stock
                        </div>
                        <div class="card-body">
                            <p>stock</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <a href="etat_stock.php" class="card-link">
                    <div class="card-header">
                            <i class="fas fa-chart-bar"></i> États Stock
                        </div>
                        <div class="card-body">
                            <p>Générer des rapports et états de stock</p>
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
        }, 2000);
    </script>
</body>
</html>
