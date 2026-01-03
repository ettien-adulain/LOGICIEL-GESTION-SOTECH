<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Stock</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        .swal2-popup {
            font-size: 1.6rem !important;
        }
        .swal2-title {
            font-size: 1.8rem !important;
        }
        .swal2-html-container {
            font-size: 1.4rem !important;
        }
    </style>
</head>
<body>
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
    <?php
    // Inclure les fonctions d'alerte
    require_once 'fonction_traitement/alert_functions.php';
    
    // Afficher les alertes
    displayAlert();
    ?>
<!-- Compteur de session (affiché en bas à droite) -->
<div id="session-timer" style="position:fixed;bottom:10px;right:10px;background:#222;color:#fff;padding:8px 16px;border-radius:6px;z-index:9999;font-size:16px;">
    Temps avant déconnexion : <span id="timer"></span>
</div>
<script>
(function() {
    var timeLeft = window.sessionTimeLeft || 0;
    var timerSpan = document.getElementById('timer');
    function updateTimer() {
        var min = Math.floor(timeLeft / 60);
        var sec = timeLeft % 60;
        timerSpan.textContent = min + ' min ' + (sec < 10 ? '0' : '') + sec + ' s';
        if (timeLeft <= 0) {
            timerSpan.textContent = 'Session expirée';
            // Optionnel : rediriger automatiquement
            // window.location.href = 'Connexion.php?error=Session expirée, veuillez vous reconnecter';
        } else {
            timeLeft--;
            setTimeout(updateTimer, 1000);
        }
    }
    updateTimer();
})();
</script>
</body>
</html> 