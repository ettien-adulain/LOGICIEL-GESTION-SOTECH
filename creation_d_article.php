<?php 
require_once 'fonction_traitement/fonction.php';
check_access(); // Cette fonction gère déjà la vérification des droits

try {
    include('db/connecting.php');
    
    $categories = verifier_element_tous('categorie_article', ['desactiver'], ['non'], '');
    $article = count(selection_element('article'));
} catch (\Throwable $th) {
    $errorMessage = 'Erreur lors de la récupération des catégories.';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($errorMessage));
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Article</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body id="creation_article">
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/theme_switcher.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <header>
        <h1>Gestion des Articles</h1>
    </header>
    <main class="container">
        <section id="article-form">
            <div class="mt-5">
                
            </div>
            <div>
                <h2 class="mt-3">Création d'article</h2>
            </div>
            <?php
                if (isset($_GET['success'])) {
                    echo '<div id="success-alert" class="alert alert-success" role="alert">' . htmlspecialchars($_GET['success']) . '</div>';
                }
                if (isset($_GET['error'])) {
                    echo '<div id="error-alert" class="alert alert-danger" role="alert">' . htmlspecialchars($_GET['error']) . '</div>';
                }
            ?>
            <div class="form-container mb-4">
                <form id="form" action="fonction_traitement/request.php" enctype="multipart/form-data" method="post">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="CodeArticle">Code de l'article</label>
                            <input type="text" class="form-control" id="CodeArticle" name="CodeArticle" required readonly>
                            <button type="button" onclick="generateNextCode()" class="btn btn-primary mt-2">Générer un code</button>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="Libelle">Libellé</label>
                            <input type="text" class="form-control" id="Libelle" name="Libelle" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="Description">Description</label>
                            <textarea class="form-control" id="Description" name="Description" rows="3"></textarea>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="Marque">Marque</label>
                            <input type="text" class="form-control" id="Marque" name="Marque" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="PrixAchat">Prix d'achat</label>
                            <input type="number" class="form-control" id="PrixAchat" name="PrixAchat" step="0.01" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="PrixVente">Prix de vente TTC</label>
                            <input type="number" class="form-control" id="PrixVente" name="PrixVente" step="0.01" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="Categorie">Catégorie</label>
                            <select class="form-control" id="Categorie" name="Categorie" required>
                                <option value="0">------------</option> 
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['id_categorie']) ?>"><?= htmlspecialchars($cat['nom_categorie']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                       
                        <div class="col-md-4 mb-3">
                            <label for="Photo">Photo</label>
                            <input type="file" class="form-control" id="Photo" name="Photo" accept="image/*">
                        </div>

                        <div class="col-md-4 mb-3">
                            <div id="photo-preview" style="display: none;">
                                <img id="photo-img" src="" alt="Aperçu de la photo" style="max-width: 100%; height: auto;">
                            </div>
                        </div>
                        
                        <div class="col-md-12 mt-3">
                            <?php
                            // Utilisation du système unifié de droits
                            echo bouton_action('Enregistrer', 'Creation_d_article', 'ajouter', 'btn btn-primary', 'name="creer_article" type="submit"');
                            echo bouton_action('Annuler', 'Creation_d_article', 'annuler', 'btn btn-secondary ms-2', 'type="reset"');
                            ?>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(function() {
            const errorAlert = document.getElementById('error-alert');
            const successAlert = document.getElementById('success-alert');
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }
            if (successAlert) {
                successAlert.style.display = 'none';
            }

            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                url.searchParams.delete('success');
                window.history.replaceState(null, null, url);
            }
        }, 2000);

        document.querySelector('#Photo').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.querySelector('#photo-img');
                    img.src = e.target.result;
                    document.querySelector('#photo-preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.querySelector('#photo-preview').style.display = 'none';
            }
        });
        
        // Variables globales  
        let generatedCode = '';
        let codeGenerated = false;

        window.onload = function() {
            // Réactive le bouton si le champ est vide au chargement
            if (!document.getElementById('CodeArticle').value) {
                document.querySelector('button[onclick="generateNextCode()"]')?.removeAttribute('disabled');
                generatedCode = '';
                codeGenerated = false;
            }
        };

        function generateNextCode() {
            if (codeGenerated) {
                alert("Vous devez enregistrer l'article ou vider le champ avant de générer un nouveau code.");
                return;
            }
            
            // Afficher un indicateur de chargement
            const button = document.querySelector('button[onclick="generateNextCode()"]');
            const originalText = button.textContent;
            button.textContent = 'Génération...';
            button.disabled = true;
            
            // Récupérer le prochain code depuis la base de données
            fetch('fonction_traitement/generate_article_code.php', {
                method: 'GET',
                credentials: 'include' // Inclure les cookies de session
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    generatedCode = data.code;
                    document.getElementById('CodeArticle').value = generatedCode;
                    codeGenerated = true;
                    // Désactive le bouton jusqu'à enregistrement ou vidage du champ
                    button.textContent = 'Code généré';
                    button.disabled = true;
                } else {
                    alert('Erreur lors de la génération du code: ' + data.error);
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la génération du code. Veuillez réessayer.');
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        // Réactive le bouton si le champ est vidé manuellement
        const codeInput = document.getElementById('CodeArticle');
        codeInput.addEventListener('input', function() {
            if (!this.value) {
                const button = document.querySelector('button[onclick="generateNextCode()"]');
                button?.removeAttribute('disabled');
                button.textContent = 'Générer un code';
                generatedCode = '';
                codeGenerated = false;
            }
        });

        // Réinitialise le bouton après soumission du formulaire
        const form = document.getElementById('form');
        form.addEventListener('submit', function(e) {
            // Réactive le bouton pour la prochaine création
            setTimeout(() => {
                const button = document.querySelector('button[onclick="generateNextCode()"]');
                button?.removeAttribute('disabled');
                button.textContent = 'Générer un code';
                generatedCode = '';
                codeGenerated = false;
            }, 1000);
        });

    </script>
</body>
</html>
