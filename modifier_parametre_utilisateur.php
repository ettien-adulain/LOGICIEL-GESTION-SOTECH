<?php     
// Démarrer la session

require_once 'fonction_traitement/fonction.php';
check_access();

// Initialisation des valeurs
// Récupérer l'ID de l'utilisateur à modifier depuis l'URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['id_utilisateur'];
$prenom = '';
$nom = '';
$fonction = '';
$identifiant = '';
$mdp = '';

// Vérification de sécurité : seul l'admin peut modifier d'autres utilisateurs
if ($userId != $_SESSION['id_utilisateur'] && $_SESSION['role'] !== 'admin') {
    header('Location: liste_utilisateurs.php?error=Vous n\'avez pas l\'autorisation de modifier cet utilisateur');
    exit();
}

// Connexion à la base de données
try {
    include('db/connecting.php');

    // Récupérer les informations de l'utilisateur
    $query = $cnx->prepare("SELECT NomPrenom, Fonction, Identifiant FROM utilisateur WHERE IDUTILISATEUR = :id");
    $query->bindParam(':id', $userId);
    $query->execute();
    $userData = $query->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        $fullName = $userData['NomPrenom'];
        $nameParts = explode(' ', $fullName, 2);
        $nom = isset($nameParts[0]) ? $nameParts[0] : '';
        $prenom = isset($nameParts[1]) ? $nameParts[1] : '';
        $fonction = $userData['Fonction'];
        $identifiant = $userData['Identifiant'];
    }

    if (isset($_POST['modifier_utilisateur'])) {
        $nom_simple = htmlspecialchars(trim($_POST['nom']));
        $prenom_post = htmlspecialchars(trim($_POST['prenom']));
        $nom_complet = $nom_simple . ' ' . $prenom_post;
        $fonction = htmlspecialchars(trim($_POST['fonction']));
        $identifiant = htmlspecialchars(trim($_POST['identifiant']));

        // Vérification des mots de passe
        $mdp = $_POST['mdp'];
        $confirme_mdp = $_POST['Confirme_mdp'];
        
        // Si un nouveau mot de passe est fourni, vérifier l'unicité
        if (!empty($mdp)) {
            if ($mdp !== $confirme_mdp) {
                $errorMessage = "Les mots de passe ne correspondent pas. Veuillez vérifier votre saisie.";
            } else {
                // Vérification de l'unicité du mot de passe (vérification sur les hashs)
                $sql = "SELECT MotDePasse, IDUTILISATEUR FROM utilisateur WHERE IDUTILISATEUR != ?";
                $stmt = $cnx->prepare($sql);
                $stmt->execute([$userId]);
                $otherUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $passwordExists = false;
                foreach ($otherUsers as $user) {
                    if (password_verify($mdp, $user['MotDePasse'])) {
                        $passwordExists = true;
                        break;
                    }
                }
                
                if ($passwordExists) {
                    $errorMessage = "Ce mot de passe est déjà utilisé par un autre utilisateur. Veuillez en choisir un autre.";
                } else {
                    $hashedPassword = password_hash($mdp, PASSWORD_DEFAULT);
                }
            }
        }
        
        // Exécution de la mise à jour seulement s'il n'y a pas d'erreur
        if (!isset($errorMessage)) {
            try {
                // Préparation de la requête de mise à jour
                $queryStr = "UPDATE utilisateur SET NomPrenom = :nom, Fonction = :fonction, Identifiant = :identifiant";
                if (!empty($mdp) && isset($hashedPassword)) {
                    $queryStr .= ", MotDePasse = :motdepasse";
                }
                $queryStr .= " WHERE IDUTILISATEUR = :id";

                $query = $cnx->prepare($queryStr);
                $query->bindParam(':nom', $nom_complet);
                $query->bindParam(':fonction', $fonction);
                $query->bindParam(':identifiant', $identifiant);
                if (!empty($mdp) && isset($hashedPassword)) {
                    $query->bindParam(':motdepasse', $hashedPassword);
                }
                $query->bindParam(':id', $userId);

                // Exécution de la requête
                if ($query->execute()) {
                    // Mettre à jour les variables pour l'affichage
                    $nom = $nom_simple;
                    $prenom = $prenom_post;
                    if (!empty($mdp) && isset($hashedPassword)) {
                        $successMessage = 'Utilisateur modifié avec succès. Le mot de passe a été mis à jour.';
                    } else {
                        $successMessage = 'Utilisateur modifié avec succès.';
                    }
                } else {
                    $errorMessage = 'Erreur lors de la mise à jour des informations utilisateur.';
                }
            } catch (Exception $e) {
                $errorMessage = 'Erreur lors de la mise à jour: ' . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $errorMessage = 'Erreur lors de la récupération des données: ' . $e->getMessage();
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
    <title>Modifier un Compte Utilisateur</title>
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
        
        <?php if (isset($errorMessage)): ?>
            <div id="error-alert" class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php elseif (isset($successMessage)): ?>
            <div id="success-alert" class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php elseif (isset($_GET['success'])): ?>
            <div id="success-alert" class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <section>
        <div class="container mt-4">
    <h2 class="text-center text-uppercase mb-4">Informations de l'utilisateur</h2>
    <div id="user-info" class="border border-primary rounded p-4 shadow-lg bg-light">
        <h4 class="text-primary mb-3">
            <i class="fas fa-user"></i> 
            <?php echo htmlspecialchars($nom); ?> <?php echo htmlspecialchars($prenom); ?>
        </h4>
        <p><strong><i class="fas fa-briefcase"></i> Fonction :</strong> 
            <span id="user-function" class="text-secondary"><?php echo htmlspecialchars($fonction); ?></span>
        </p>
        <p><strong><i class="fas fa-id-badge"></i> Identifiant :</strong> 
            <span id="user-identifier" class="text-secondary"><?php echo htmlspecialchars($identifiant); ?></span>
        </p>
        <p><strong><i class="fas fa-key"></i> Mot de passe :</strong> 
            <span id="user-password" class="text-secondary">
                <?php if (isset($_POST['modifier_utilisateur']) && !empty($mdp) && isset($hashedPassword)): ?>
                    <span class="text-success"><i class="fas fa-check-circle"></i> Mis à jour avec succès</span>
                <?php else: ?>
                    Non affiché pour des raisons de sécurité
                <?php endif; ?>
            </span>
        </p>
        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="showPasswordFieldsBtn">
            <i class="fas fa-edit"></i> Modifier le mot de passe
        </button>
    </div>
    <style>
        #user-info {
    border: 2px solid #007bff;
    background-color: #f8f9fa;
    border-radius: 10px;
}

h2 {
    font-weight: bold;
    color: #343a40;
}

h4 {
    font-weight: bold;
    color: #007bff;
}

p {
    font-size: 1.1rem;
}

.text-secondary {
    color: #6c757d;
}

.shadow-lg {
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
}

i {
    margin-right: 8px;
}


    </style>
</div>


            <h2>Modifier Compte Utilisateur</h2>
            <div class="form-container">
                <form id="form" action="" method="post" onsubmit="return validateForm()">
                    <div class="row">
                        <!-- Prénom -->
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user icon-animate"></i></span>
                                    </div>
                                    <input type="text" name="prenom" class="form-control" placeholder="Prénom" value="<?php echo htmlspecialchars($prenom); ?>" required>
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
                                    <input type="text" name="nom" class="form-control" placeholder="Nom" value="<?php echo htmlspecialchars($nom); ?>" required>
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
                                    <input type="text" name="fonction" class="form-control" placeholder="Fonction" value="<?php echo htmlspecialchars($fonction); ?>" required>
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
                                    <input type="text" name="identifiant" class="form-control" placeholder="Identifiant" value="<?php echo htmlspecialchars($identifiant); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Mot de passe (caché par défaut) -->
                        <div id="passwordFields" style="display:none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-lock icon-animate"></i></span>
                                            </div>
                                            <input type="password" id="mdp" name="mdp" class="form-control" placeholder="Nouveau mot de passe">
                                            <div class="input-group-append">
                                                <span class="input-group-text" onclick="togglePassword('mdp', this)"><i class="fas fa-eye" aria-hidden="true"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-lock icon-animate"></i></span>
                                            </div>
                                            <input type="password" id="Confirme_mdp" name="Confirme_mdp" class="form-control" placeholder="Confirmer le nouveau mot de passe">
                                            <div class="input-group-append">
                                                <span class="input-group-text" onclick="togglePassword('Confirme_mdp', this)"><i class="fas fa-eye" aria-hidden="true"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <small id="passwordError" class="text-danger" style="display:none;">
                                    <i class="fas fa-exclamation"></i> Ce mot de passe est déjà utilisé par un autre utilisateur.
                                </small>
                                <small id="confirmError" class="text-danger" style="display:none;">
                                    <i class="fas fa-exclamation"></i> Les mots de passe ne correspondent pas.
                                </small>
                                <small id="successMessage" class="text-success" style="display:none;">
                                    <i class="fas fa-check"></i> Mot de passe disponible
                                </small>
                                <small id="loadingMessage" class="text-info" style="display:none;">
                                    <i class="fas fa-spinner fa-spin"></i> Vérification en cours...
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <button type="submit" name="modifier_utilisateur" class="btn btn-primary btn-lg">Modifier</button>
                    </div>
                </form>
            </div>
        </section>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></script>
        <script>
            function togglePassword(id, element) {
                const passwordInput = document.getElementById(id);
                const icon = element.querySelector("i");
                if (passwordInput.type === "password") {
                    passwordInput.type = "text";
                    icon.classList.remove("fa-eye");
                    icon.classList.add("fa-eye-slash");
                } else {
                    passwordInput.type = "password";
                    icon.classList.remove("fa-eye-slash");
                    icon.classList.add("fa-eye");
                }
            }

            document.getElementById('showPasswordFieldsBtn').addEventListener('click', function() {
                var fields = document.getElementById('passwordFields');
                fields.style.display = (fields.style.display === 'none') ? 'block' : 'none';
            });

            // Ajouter les événements de validation pour les mots de passe
            document.getElementById('mdp').addEventListener('input', validatePassword);
            document.getElementById('Confirme_mdp').addEventListener('input', validatePassword);

            function validatePassword() {
                const password = document.getElementById('mdp').value;
                const confirmPassword = document.getElementById('Confirme_mdp').value;
                const passwordError = document.getElementById('passwordError');
                const confirmError = document.getElementById('confirmError');
                const successMessage = document.getElementById('successMessage');

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

            function validateForm() {
                var passwordFields = document.getElementById('passwordFields');
                if (passwordFields.style.display === 'block') {
                    const password = document.getElementById("mdp").value;
                    const confirmPassword = document.getElementById("Confirme_mdp").value;
                    
                    // Vérifier si les mots de passe correspondent
                    if (password !== confirmPassword) {
                        document.getElementById("confirmError").style.display = "block";
                        return false;
                    } else {
                        document.getElementById("confirmError").style.display = "none";
                    }
                    
                    // Vérifier l'unicité (si un mot de passe est saisi)
                    if (password.length > 0) {
                        const passwordError = document.getElementById("passwordError");
                        if (passwordError.style.display === 'block') {
                            return false;
                        }
                    }
                }
                return true;
            }

            <?php if (isset($_POST['modifier_utilisateur'])): ?>
                document.getElementById('user-function').textContent = "<?php echo htmlspecialchars($fonction); ?>";
                document.getElementById('user-identifier').textContent = "<?php echo htmlspecialchars($identifiant); ?>";
                // Le mot de passe reste caché pour des raisons de sécurité
                <?php if (!empty($mdp) && isset($hashedPassword)): ?>
                    document.getElementById('user-password').innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Mis à jour avec succès</span>';
                <?php else: ?>
                    document.getElementById('user-password').textContent = "Non affiché pour des raisons de sécurité";
                <?php endif; ?>
            <?php endif; ?>
        </script>
    </main>
</body>
</html>
