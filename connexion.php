<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion SO-TECH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Effet de particules en arrière-plan */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(220, 38, 38, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(220, 38, 38, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(220, 38, 38, 0.05) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 10;
            animation: slideInUp 0.8s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 30px rgba(220, 38, 38, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .login-title {
            color: #1a1a1a;
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .login-subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            font-weight: 300;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            color: #1a1a1a;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.2);
            transform: translateY(-2px);
        }

        .input-group-text {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border: none;
            color: white;
            padding: 0.75rem 1rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .form-control {
            border: none;
            padding: 1rem 1rem 1rem 0;
            font-size: 1rem;
            background: white;
            color: #1a1a1a;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: none;
            background: #f8f9fa;
        }

        .form-control::placeholder {
            color: #999;
            font-weight: 300;
        }

        .login-btn {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(220, 38, 38, 0.4);
        }

        .login-btn:active {
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 12px;
            border: none;
            font-weight: 500;
            animation: slideInDown 0.5s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
        }

        .alert-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
            }
            
            .logo-icon i {
                font-size: 2rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }

        /* Animation des icônes */
        .icon-animate {
            animation: iconFloat 3s ease-in-out infinite;
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        /* Effet de focus amélioré */
        .form-control:focus + .input-group-text {
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
        }

        /* Bouton d'affichage du mot de passe */
        .password-toggle {
            background: linear-gradient(135deg, #dc2626, #991b1b) !important;
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 0 12px 12px 0 !important;
        }

        .password-toggle:hover {
            background: linear-gradient(135deg, #991b1b, #7f1d1d) !important;
            transform: scale(1.05);
        }

        .password-toggle:active {
            transform: scale(0.95);
        }

        .password-toggle i {
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        /* Animation de l'icône */
        .password-toggle:hover i {
            transform: scale(1.1);
        }

        /* Style pour l'état "visible" */
        .password-toggle.showing {
            background: linear-gradient(135deg, #059669, #047857) !important;
        }

        .password-toggle.showing:hover {
            background: linear-gradient(135deg, #047857, #065f46) !important;
        }
    </style>
</head>
<body>
    <?php
    // Ajoute ce bloc PHP en haut du fichier (après <head> ou tout début du <body>)
    $showAdminForm = false;
    $adminFormError = '';
    $adminFormSuccess = '';
    $CODE_SECRET_ADMIN = '#root@administrateur.sotech_2025'; // <-- À personnaliser par l'admin
    if (isset($_POST['emergency_code_submit'])) {
        $code = trim($_POST['emergency_code'] ?? '');
        if ($code === $CODE_SECRET_ADMIN) {
            $showAdminForm = true;
        } else {
            $adminFormError = "Code d'accès incorrect.";
        }
    }
    if (isset($_POST['emergency_admin_create'])) {
        include_once 'db/connecting.php';
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $fonction = trim($_POST['fonction'] ?? '');
        $identifiant = trim($_POST['identifiant'] ?? '');
        $mdp = $_POST['mdp'] ?? '';
        $mdp2 = $_POST['mdp2'] ?? '';
        if ($mdp !== $mdp2) {
            $adminFormError = 'Les mots de passe ne correspondent pas.';
            $showAdminForm = true;
        } elseif (strlen($mdp) < 9) {
            $adminFormError = 'Le mot de passe doit comporter au moins 9 caractères.';
            $showAdminForm = true;
        } elseif (empty($prenom) || empty($nom) || empty($fonction) || empty($identifiant)) {
            $adminFormError = 'Tous les champs sont obligatoires.';
            $showAdminForm = true;
        } else {
            // Vérifie si l'identifiant existe déjà
            $stmt = $cnx->prepare('SELECT * FROM utilisateur WHERE Identifiant = ?');
            $stmt->execute([$identifiant]);
            if ($stmt->fetch()) {
                $adminFormError = "Cet identifiant existe déjà.";
                $showAdminForm = true;
            } else {
                $nomPrenom = $prenom . ' ' . $nom;
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt = $cnx->prepare('INSERT INTO utilisateur (NomPrenom, Identifiant, MotDePasse, fonction, role, actif) VALUES (?, ?, ?, ?, "admin", "oui")');
                $stmt->execute([$nomPrenom, $identifiant, $hash, $fonction]);
                $adminFormSuccess = 'Compte administrateur créé avec succès. Connectez-vous.';
            }
        }
    }
    ?>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">SO-TECH</h1>
            <p class="login-subtitle">Système de Gestion</p>
        </div>

        <form id="form" action="fonction_traitement/request.php" method="post">
                <?php
                    if (isset($_GET['error'])) {
                        $errorMessage = htmlspecialchars($_GET['error']);
                    echo '<div id="error-alert" class="alert alert-danger" role="alert">';
                    echo '<i class="fas fa-exclamation-triangle me-2"></i>' . $errorMessage;
                    echo '</div>';
                    }
                ?>
            
                    <div class="form-group">
                <label for="identifiant" class="form-label">Identifiant</label>
                        <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user icon-animate"></i>
                    </span>
                    <input type="text" 
                           id="identifiant"
                           name="Identifiant" 
                           class="form-control" 
                           placeholder="Entrez votre identifiant" 
                           required 
                           autocomplete="username">
                        </div>
                    </div>

                    <div class="form-group">
                <label for="password" class="form-label">Mot de Passe</label>
                        <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock icon-animate"></i>
                    </span>
                    <input type="password" 
                           id="password"
                           name="mdp" 
                           class="form-control" 
                           placeholder="Entrez votre mot de passe" 
                           required 
                           autocomplete="current-password">
                    <button type="button" class="input-group-text password-toggle" id="password-toggle">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </button>
                        </div>
                    </div>

            <button type="submit" class="login-btn" name="connexion_admin">
                <i class="fas fa-sign-in-alt me-2"></i>
                Se Connecter
            </button>

                    <?php if (!empty($message)): ?>
                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

    <?php if ($adminFormSuccess): ?>
        <div class="alert alert-success text-center mb-3">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $adminFormSuccess; ?><br>
            <span class="fw-bold">Vous pouvez maintenant vous connecter en tant qu'administrateur.</span>
        </div>
    <?php endif; ?>
    <!-- Modal d'urgence admin -->
    <div id="emergencyModal" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-shield me-2"></i> Création d'un compte administrateur</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body">
            <?php if (!$showAdminForm): ?>
              <form method="post" autocomplete="off">
                <div class="mb-3">
                  <label for="emergency_code" class="form-label">Saisissez le code d'accès&nbsp;:</label>
                  <input type="password" class="form-control" id="emergency_code" name="emergency_code" required autofocus>
                </div>
                <?php if ($adminFormError): ?>
                  <div class="alert alert-danger py-2 mb-2">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $adminFormError; ?>
                  </div>
                <?php elseif (isset($_POST['emergency_code_submit']) && !$adminFormError): ?>
                  <div class="alert alert-success py-2 mb-2">
                    <i class="fas fa-check-circle me-2"></i>
                    Code d'accès autorisé. Veuillez remplir le formulaire ci-dessous.
                  </div>
                <?php endif; ?>
                <button type="submit" name="emergency_code_submit" class="btn btn-primary w-100">Valider</button>
              </form>
            <?php else: ?>
              <form method="post" autocomplete="off">
                <div class="row g-2">
                  <div class="col-md-6 mb-2"><input type="text" name="prenom" class="form-control" placeholder="Prénom" required></div>
                  <div class="col-md-6 mb-2"><input type="text" name="nom" class="form-control" placeholder="Nom" required></div>
                  <div class="col-md-6 mb-2"><input type="text" name="fonction" class="form-control" placeholder="Fonction" required></div>
                  <div class="col-md-6 mb-2"><input type="text" name="identifiant" class="form-control" placeholder="Identifiant" required></div>
                  <div class="col-md-6 mb-2"><input type="password" name="mdp" class="form-control" placeholder="Mot de passe" required></div>
                  <div class="col-md-6 mb-2"><input type="password" name="mdp2" class="form-control" placeholder="Confirmer mot de passe" required></div>
                </div>
                <?php if ($adminFormError): ?><div class="alert alert-danger py-2 mb-2"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $adminFormError; ?></div><?php endif; ?>
                <button type="submit" name="emergency_admin_create" class="btn btn-success w-100 mt-2">Créer le compte administrateur</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if ($showAdminForm || (isset($_POST['emergency_code_submit']) && !$adminFormError)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('emergencyModal'));
            modal.show();
            setTimeout(function(){
                var codeInput = document.getElementById('emergency_code');
                if(codeInput) codeInput.focus();
            }, 300);
        });
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Animation des alertes
        setTimeout(function() {
            var errorAlert = document.getElementById('error-alert');
            if (errorAlert) {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                errorAlert.style.display = 'none';
                }, 500);
            }

            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState(null, null, url);
            }
        }, 5000);
        
        // Effet de focus amélioré
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Animation du bouton au survol
        document.querySelector('.login-btn').addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        document.querySelector('.login-btn').addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });

        // Fonctionnalité d'affichage/masquage du mot de passe
        const passwordToggle = document.getElementById('password-toggle');
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('password-icon');

        passwordToggle.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                // Afficher le mot de passe
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
                passwordToggle.classList.add('showing');
                passwordToggle.title = 'Masquer le mot de passe';
                
                // Animation de l'icône
                passwordIcon.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    passwordIcon.style.transform = 'scale(1)';
                }, 200);
            } else {
                // Masquer le mot de passe
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
                passwordToggle.classList.remove('showing');
                passwordToggle.title = 'Afficher le mot de passe';
                
                // Animation de l'icône
                passwordIcon.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    passwordIcon.style.transform = 'scale(1)';
                }, 200);
            }
        });

        // Focus sur le champ mot de passe
        passwordInput.addEventListener('focus', function() {
            passwordToggle.style.opacity = '1';
        });

        // Tooltip par défaut
        passwordToggle.title = 'Afficher le mot de passe';

        // Raccourci clavier Ctrl+I pour ouvrir le modal
        window.addEventListener('keydown', function(e) {
            if (e.ctrlKey && (e.key === 'i' || e.key === 'I')) {
                e.preventDefault();
                var modal = new bootstrap.Modal(document.getElementById('emergencyModal'));
                modal.show();
                setTimeout(function(){
                    var codeInput = document.getElementById('emergency_code');
                    if(codeInput) codeInput.focus();
                }, 300);
            }
        });
    </script>
</body>
</html>