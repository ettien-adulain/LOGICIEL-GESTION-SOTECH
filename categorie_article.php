<?php
try {
    include('db/connecting.php');
    
    require_once 'fonction_traitement/fonction.php';
    check_access();


    if (!can_user_page('Categorie_article', 'voir')) {
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Accès refusé</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: #f8d7da; display: flex; align-items: center; justify-content: center; height: 100vh; }
                .denied-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(220,38,38,0.15); padding: 2rem 2.5rem; text-align: center; }
                .denied-box h1 { color: #dc3545; font-size: 2.2rem; margin-bottom: 1rem; }
                .denied-box p { color: #333; font-size: 1.1rem; }
                .btn-retour { margin-top: 1.5rem; }
            </style>
        </head>
        <body>
            <div class="denied-box">
                <h1><i class="fas fa-ban"></i> Accès refusé</h1>
                <p>Vous n\'avez pas l\'autorisation d\'accéder à la page de Categorie.</p>
                <p><strong>Droit requis :</strong> Voir sur la page "Categorie"</p>
                <a href="index.php" class="btn btn-danger btn-retour"><i class="fas fa-arrow-left"></i> Retour au menu</a>
            </div>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </body>
        </html>';
        exit();
    }
    
    $categories = selection_element('categorie_article');
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . $tableName;
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}
$currentPage = 'Categorie_article';
$canView = can_user_page($currentPage, 'voir');
$canAdd= can_user_page($currentPage, 'ajouter');
$canDelete = can_user_page($currentPage, 'supprimer');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body id="categorie_article">
<?php include('includes/user_indicator.php'); ?>
<?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1>Gestion des Catégories</h1>
    </header>
    <main class="container">
            <div class="m-3">
            </div>
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
            <form action="fonction_traitement/request.php" enctype="multipart/form-data" method="post">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="categoryName" class="form-label">Ajouter une nouvelle catégorie</label>
                            <div class="input-group">
                                <input type="text" id="categoryName" name="nom_categorie" class="form-control" placeholder="Nom de la catégorie" required>
                                <span class="input-group-text"><i class="fas fa-plus"></i></span>
                            </div>
                        </div>
                        <?php if ($canAdd): ?>
                        <button type="submit" name="creer_categorie" class="btn btn-primary">Ajouter Catégorie</button>
                        <?php endif ?>
                        <button type="reset" class="btn btn-secondary">Annuler</button>
                    </div>
                </div>
            </form>

            <h3 class="text-center mb-4 text-decoration-underline">Liste des catégories</h3>
            <table class="table table-bordered table-striped mb-5">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Supprimer</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($categories) > 0) { 
                    $id = 1;
                        foreach ($categories as $category)  : 
                           
                            ?>
                        <tr>
                                <td><?php echo $id ?></td>
                                <td><?php echo htmlspecialchars($category['nom_categorie']); ?></td>
                                <td>
                                    <form action="fonction_traitement/request.php" enctype="multipart/form-data" method="post" class="d-inline">
                                        <input type="hidden" name="id_categorie" value="<?php echo htmlspecialchars($category['id_categorie']); ?>">
                                        <?php if ($canDelete): ?>
                                        <button type="submit" name="supprimer_categorie" class="btn btn-danger"><i class="fas fa-trash-alt"></i></button>
                                        <?php endif ?>
                                    </form>
                                </td>
                            </tr>

                        <?php 
                            $id = $id+1;
                            endforeach; 
                        }
                        else {?>
                            <tr>
                                <td colspan="4" class="empty-row">Le tableau est vide</td>
                            </tr>
                        <?php }; ?>
                </tbody>
            </table>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <script>
        setTimeout(function() {
            var errorAlert = document.getElementById('error-alert');
            var successAlert = document.getElementById('error-alert');
            if (errorAlert & successAlert) {
                errorAlert.style.display = 'none';
            }

            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                url.searchParams.delete('success');
                window.history.replaceState(null, null, url);
            }
        }, 2000);
    </script>
   
</body>
</html>
