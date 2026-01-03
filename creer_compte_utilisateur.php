<?php
require_once 'fonction_traitement/fonction.php';
check_access(['admin']); // Seuls les admins peuvent accéder à la création de compte

try {
    include('db/connecting.php');
    // session_start(); // Plus besoin ici, déjà fait dans check_access
    
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
    <title>Créer un Compte Utilisateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body id="creation_utilisateur">
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <header>
        <h1>Gestion des Utilisateurs</h1>
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
        <section>
            <h2>Création de Compte Utilisateur</h2>
            <div class="form-container">
                <form id="form" action="fonction_traitement/request.php" method="post" onsubmit="return validateForm()">
                    <div class="row">
                        <!-- Prénom -->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user icon-animate"></i></span>
                                    </div>
                                    <input type="text" name="prenom" class="form-control" placeholder="Prénom" required>
                                </div>
                            </div>
                        </div>

                        <!-- Nom -->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user icon-animate"></i></span>
                                    </div>
                                    <input type="text" name="nom" class="form-control" placeholder="Nom" required>
                                </div>
                            </div>
                        </div>

                        <!-- Fonction -->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-briefcase icon-animate"></i></span>
                                    </div>
                                    <input type="text" name="fonction" class="form-control" placeholder="Fonction" required>
                                </div>
                            </div>
                        </div>

                        <!-- Identifiant -->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user-tag icon-animate"></i></span>
                                    </div>
                                    <input type="text" name="identifiant" class="form-control" placeholder="Identifiant" required>
                                </div>
                            </div>
                        </div>

                        <!-- Rôle (visible uniquement pour les admins) -->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user-shield icon-animate"></i></span>
                                    </div>
                                    <select name="role" class="form-control" required>
                                        <option value="user">Utilisateur simple</option>
                                        <option value="admin">Administrateur</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Message d'erreur -->
                        <div class="col-md-12 mb-3">
                            <small id="passwordError" class="text-danger" style="display:none;">
                                <i class="fas fa-exclamation"></i> Ce mot de passe est déjà utilisé par un autre utilisateur.</small>
                            <small id="confirmError" class="text-danger" style="display:none;"><i class="fas fa-exclamation"></i> Les mots de passe ne correspondent pas.</small>
                            <small id="successMessage" class="text-success" style="display:none;"><i class="fas fa-check"></i> Mot de passe disponible</small>
                            <small id="loadingMessage" class="text-info" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Vérification en cours...</small>
                        </div>
                        <!-- Mot de passe-->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-lock icon-animate"></i></span>
                                    </div>
                                    <input type="password" id="mdp" name="mdp" class="form-control" placeholder="Mot de Passe" required>
                                </div>
                            </div>
                        </div>
                        <!-- Confirme Mot de passe-->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-lock icon-animate"></i></span>
                                    </div>
                                    <input type="password" id="Confirme_mdp" name="Confirme_mdp" class="form-control" placeholder="Confirmer Mot de Passe" required>
                                </div>
                                <small id="confirmError" class="text-danger" style="display:none;">Les mots de passe ne correspondent pas.</small>
                            </div>
                        </div>

                        <!-- Boutons -->
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" name="enregistrer_utilisateur" id="submitButton">Enregistrer</button>
                            <button type="reset" class="btn btn-secondary">Annuler</button>
                        </div>
                    </div>
                </form>
            </div>

            
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
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

        document.getElementById('mdp').addEventListener('input', validatePassword);
        document.getElementById('Confirme_mdp').addEventListener('input', validatePassword);

        function validatePassword() {
            var password = document.getElementById('mdp').value;
            var confirmPassword = document.getElementById('Confirme_mdp').value;
            var passwordError = document.getElementById('passwordError');
            var confirmError = document.getElementById('confirmError');
            var successMessage = document.getElementById('successMessage');

            // Masquer tous les messages d'erreur
            passwordError.style.display = 'none';
            confirmError.style.display = 'none';
            successMessage.style.display = 'none';
            document.getElementById('loadingMessage').style.display = 'none';

            // Vérifier si les mots de passe correspondent
            if (password !== confirmPassword && confirmPassword !== '') {
                confirmError.style.display = 'block';
                return;
            }

            // Vérifier l'unicité du mot de passe (vérification côté serveur)
            if (password.length > 0) {
                checkPasswordUniqueness(password);
            }
        }

        // Variable pour gérer le délai de vérification
        let passwordCheckTimeout;

        function checkPasswordUniqueness(password) {
            // Annuler la vérification précédente si elle existe
            if (passwordCheckTimeout) {
                clearTimeout(passwordCheckTimeout);
            }
            
            // Attendre 500ms avant de faire la vérification pour éviter trop de requêtes
            passwordCheckTimeout = setTimeout(() => {
                // Afficher l'indicateur de chargement
                document.getElementById('loadingMessage').style.display = 'block';
                
                // Vérification AJAX de l'unicité du mot de passe
                fetch('fonction_traitement/check_password_uniqueness.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'password=' + encodeURIComponent(password)
                })
                .then(response => response.json())
                .then(data => {
                    var passwordError = document.getElementById('passwordError');
                    var successMessage = document.getElementById('successMessage');
                    var loadingMessage = document.getElementById('loadingMessage');
                    
                    // Masquer l'indicateur de chargement
                    loadingMessage.style.display = 'none';
                    
                    if (data.exists) {
                        passwordError.style.display = 'block';
                        successMessage.style.display = 'none';
                    } else {
                        passwordError.style.display = 'none';
                        if (document.getElementById('Confirme_mdp').value === password && password.length > 0) {
                            successMessage.style.display = 'block';
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la vérification:', error);
                });
            }, 500);
        }
    </script>
</body>
</html>