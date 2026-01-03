<?php 
try {
    include('db/connecting.php');
   
    require_once 'fonction_traitement/fonction.php';
    check_access();
    include('fonction_traitement/fonction.php');
    $motif = selection_element('motif_correction');
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . $tableName;
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
    <title>Motif Correction Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body id="categorie_article">
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1> Motif Correction Stock
        </h1>
    </header>
    <main class="container">
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
                            <label for="categoryName" class="form-label">Ajouter une nouveau Motif</label>
                            <div class="input-group">
                                <input type="text" id="categoryName" name="LibelleMotifMouvementStock" class="form-control" placeholder="Nom du motif" required>
                                <span class="input-group-text"><i class="fas fa-plus"></i></span>
                            </div>
                        </div>
                        <button type="submit" name="creer_motif" class="btn btn-primary">Ajouter Motif</button>
                        <button type="reset" class="btn btn-secondary">Annuler</button>
                    </div>
                </div>
            </form>

            <h3 class="text-center mb-4 text-decoration-underline">Liste des Motifs</h3>
            <table class="table table-bordered table-striped mb-5">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Supprimer</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($motif) > 0) { 
                    $id = 1;
                        foreach ($motif as $category)  : 
                           
                            ?>
                        <tr>
                                <td><?php echo $id ?></td>
                                <td><?php echo htmlspecialchars($category['LibelleMotifMouvementStock']); ?></td>
                                <td>
                                    <form action="fonction_traitement/request.php" enctype="multipart/form-data" method="post" class="d-inline">
                                        <input type="hidden" name="id_categorie" value="<?php echo htmlspecialchars($category['IDMOTIF_MOUVEMENT_STOCK']?? ''); ?>">
                                        <button type="submit" name="supprimer_motif" class="btn btn-danger"><i class="fas fa-trash-alt"></i></button>
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
