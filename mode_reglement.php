<?php  
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();
    include('fonction_traitement/fonction.php');
    $mode_reglements = selection_element('mode_reglement');
    
    // Trier les modes de règlement par date de création (du plus ancien au plus récent)
    usort($mode_reglements, function($a, $b) {
        return strtotime($a['DateIns']) - strtotime($b['DateIns']);
    });
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . $tableName;
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Modes de Règlement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body id="fournisseur">
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <header>
        <h1>Gestion des Modes de Règlement</h1>
    </header>
    <main>
        <div class="container-fluid">
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
            <div class="row">
                <div class="col-md-4">
                    <div class="form-container">
                    <h2 id="form-title">Créer un Mode de Règlement</h2>
                        <form method="post" action="fonction_traitement/request.php" id="supplier-form">
                            <input type="hidden" id="idModeReglement" name="idModeReglement">
                            <div class="form-group">
                                <label>Mode de Règlement</label>
                                <input type="text" id="mode_reglement" name="mode_reglement" class="form-control" placeholder="Mode de Règlement" required>
                            </div>
                            <div class="form-group">
                                <label>Numéro</label>
                                <input type="text" id="numero" name="numero" class="form-control" placeholder="Numéro" required>
                            </div>
                            <button type="submit" id="submitButton" name="enregister_paiement" class="btn btn-primary">Ajouter</button>
                            <button type="reset" class="btn btn-secondary">Annuler</button>
                        </form>
                    </div>
                </div>

                <!-- Tableau des fournisseurs -->
                <div class="col-md-8">
                    <div class="table-container">
                        <h2>Mode de Reglement Ajoutés</h2>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Mode de Règlement</th>
                                    <th>Numéro</th>
                                    <th>Date de Création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    if (!empty($mode_reglements)) {
                                        $id = 1;
                                    foreach ($mode_reglements as $mode_reglement): ?>
                                   <tr>
                                   <td><?php echo $id; ?></td>
    <td><?php echo htmlspecialchars($mode_reglement['ModeReglement']); ?></td>
    <td><?php echo isset($mode_reglement['numero']) ? htmlspecialchars($mode_reglement['numero']) : 'Au comptant '; ?></td>
    <td><?php echo htmlspecialchars($mode_reglement['DateIns']); ?></td>
    <td>
        <input type="hidden" name="idModeReglementEdit" value="<?php echo htmlspecialchars($mode_reglement['IDMODE_REGLEMENT']); ?>">
        <button class="btn btn-secondary edit-btn"><i class="fas fa-pen-to-square"></i></button>
        <form method="post" action="fonction_traitement/request.php" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?');">
            <input type="hidden" name="idmode_reglement" value="<?php echo htmlspecialchars($mode_reglement['IDMODE_REGLEMENT']); ?>">
            <button type="submit" name="supprimer_mode_paiement" class="btn btn-danger"><i class="fas fa-trash"></i></button>
        </form>
    </td>
</tr>
                                <?php 
                                    $id = $id+1;
                                    endforeach; 
                                } else {?>
                                        <tr>
                                            <td colspan="6" class="text-center">Aucun fournisseur enregistrée.</td>
                                        </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.edit-btn');
    const modeReglementInput = document.getElementById('mode_reglement');
    const numeroInput = document.getElementById('numero');
    const idInput = document.getElementById('idModeReglement');
    const submitButton = document.getElementById('submitButton');
    const formTitle = document.getElementById('form-title');

    editButtons.forEach(button => {
        button.addEventListener('click', function (event) {
            const row = event.target.closest('tr');
            const id = row.querySelector('input[name="idmode_reglement"]').value;
            const modeReglement = row.cells[1].innerText;
            const numero = row.cells[2].innerText;

            // Remplir les champs du formulaire avec les valeurs actuelles
            modeReglementInput.value = modeReglement;
            numeroInput.value = numero;
            idInput.value = id;

            // Changer le titre et le bouton
            formTitle.innerText = 'Modifier un Mode de Règlement';
            submitButton.innerText = 'Mettre à jour';
            submitButton.name = 'modifier_paiement';
        });
    });
});
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