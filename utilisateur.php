<?php
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();
    
    // Contrôle d'accès spécial : seul l'administrateur peut accéder
    if ($_SESSION['role'] !== 'admin') {
        access_denied_page();
    }
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
    <title>Gestion des Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body id="utilisateur">
    <!-- Barre de chargement -->
    <div class="loader-wrapper" id="loader">
        <div class="loader">
            <div class="logo"></div>
        </div>
    </div>

    <div class="container">
        <!-- Titre -->
        <div class="title-wrapper">
            <h1 class="title">Gestion des Utilisateurs</h1>
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
                    <a href="creer_compte_utilisateur.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-user-plus"></i> Créer Compte Utilisateur
                        </div>
                        <div class="card-body">
                            <p>Ajouter un nouvel utilisateur</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="liste_utilisateurs.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-users"></i> Liste des Utilisateurs
                        </div>
                        <div class="card-body">
                            <p>Consulter la liste des utilisateurs</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="droit_acces.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-user-shield"></i> Droit d'Accès
                        </div>
                        <div class="card-body">
                            <p>Gérer les droits d'accès</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="modifier_parametre_utilisateur.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-cogs"></i> modifier_parametre_
                        </div>
                        <div class="card-body">
                            <p>Modifier les paramètres des utilisateurs</p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <a href="log.php" class="card-link">
                        <div class="card-header">
                            <i class="fas fa-chart-line"></i> Monitoring Système
                        </div>
                        <div class="card-body">
                            <p>Surveillance avancée des logs et erreurs</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Système d'alerte avec cloche -->
    <div id="alert-bell" class="alert-bell" onclick="toggleAlertPanel()">
        <i class="fas fa-bell"></i>
        <span id="alert-count" class="alert-count">0</span>
    </div>

    <!-- Panneau d'alerte -->
    <div id="alert-panel" class="alert-panel">
        <div class="alert-panel-header">
            <h5><i class="fas fa-exclamation-triangle"></i> Alertes Système</h5>
            <button class="btn-close" onclick="toggleAlertPanel()"></button>
        </div>
        <div class="alert-panel-body">
            <div id="alert-content">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement des alertes...</p>
                </div>
            </div>
        </div>
        <div class="alert-panel-footer">
            <button class="btn btn-sm btn-primary" onclick="refreshAlerts()">
                <i class="fas fa-sync"></i> Actualiser
            </button>
            <button class="btn btn-sm btn-danger" onclick="clearAllAlerts()">
                <i class="fas fa-trash"></i> Vider
            </button>
        </div>
    </div>

    <style>
        /* Système d'alerte avec cloche */
        .alert-bell {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(255, 107, 107, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            animation: pulse 2s infinite;
        }

        .alert-bell:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(255, 107, 107, 0.6);
        }

        .alert-bell i {
            color: white;
            font-size: 24px;
        }

        .alert-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            animation: bounce 1s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 4px 20px rgba(255, 107, 107, 0.4); }
            50% { box-shadow: 0 4px 20px rgba(255, 107, 107, 0.8); }
            100% { box-shadow: 0 4px 20px rgba(255, 107, 107, 0.4); }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .alert-panel {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 400px;
            max-height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 1001;
            display: none;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .alert-panel.show {
            display: block;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-panel-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-panel-header h5 {
            margin: 0;
            font-size: 16px;
        }

        .alert-panel-body {
            max-height: 350px;
            overflow-y: auto;
            padding: 0;
        }

        .alert-panel-footer {
            background: #f8f9fa;
            padding: 10px 15px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .alert-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }

        .alert-item:hover {
            background: #f8f9fa;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-item.critical {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }

        .alert-item.warning {
            border-left: 4px solid #ffc107;
            background: #fffbf0;
        }

        .alert-item.info {
            border-left: 4px solid #17a2b8;
            background: #f0f8ff;
        }

        .alert-item.success {
            border-left: 4px solid #28a745;
            background: #f0fff4;
        }

        .alert-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .alert-message {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .alert-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            justify-content: space-between;
        }

        .alert-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .alert-type.critical {
            background: #dc3545;
            color: white;
        }

        .alert-type.warning {
            background: #ffc107;
            color: #212529;
        }

        .alert-type.info {
            background: #17a2b8;
            color: white;
        }

        .alert-type.success {
            background: #28a745;
            color: white;
        }

        .no-alerts {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .no-alerts i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #28a745;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .alert-panel {
                width: calc(100vw - 40px);
                right: 20px;
                left: 20px;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        let alertPanelOpen = false;
        let alertInterval;

        // Fonction pour basculer le panneau d'alerte
        function toggleAlertPanel() {
            const panel = document.getElementById('alert-panel');
            const bell = document.getElementById('alert-bell');
            
            if (alertPanelOpen) {
                panel.classList.remove('show');
                bell.style.background = 'linear-gradient(135deg, #ff6b6b, #ee5a24)';
                alertPanelOpen = false;
            } else {
                panel.classList.add('show');
                bell.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                alertPanelOpen = true;
                loadAlerts();
            }
        }

        // Fonction pour charger les alertes
        function loadAlerts() {
            fetch('get_system_alerts.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Données reçues de l\'API:', data);
                    console.log('Type de données:', typeof data);
                    console.log('Est un tableau:', Array.isArray(data));
                    
                    // Vérifier si data est un tableau
                    if (Array.isArray(data)) {
                        displayAlerts(data);
                        updateAlertCount(data.length);
                    } else if (data && data.error) {
                        // Gérer les erreurs de l'API
                        console.error('Erreur API:', data.error);
                        document.getElementById('alert-content').innerHTML = 
                            '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i><p>Erreur API: ' + data.error + '</p></div>';
                        updateAlertCount(0);
                    } else {
                        // Données inattendues
                        console.error('Format de données inattendu:', data);
                        document.getElementById('alert-content').innerHTML = 
                            '<div class="text-center text-warning"><i class="fas fa-exclamation-triangle"></i><p>Format de données inattendu</p><small>Vérifiez la console pour plus de détails</small></div>';
                        updateAlertCount(0);
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des alertes:', error);
                    document.getElementById('alert-content').innerHTML = 
                        '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i><p>Erreur de chargement: ' + error.message + '</p></div>';
                    updateAlertCount(0);
                });
        }

        // Fonction pour afficher les alertes
        function displayAlerts(alerts) {
            const content = document.getElementById('alert-content');
            
            if (alerts.length === 0) {
                content.innerHTML = `
                    <div class="no-alerts">
                        <i class="fas fa-check-circle"></i>
                        <h6>Aucune alerte</h6>
                        <p>Le système fonctionne correctement</p>
                    </div>
                `;
                return;
            }

            let html = '';
            alerts.forEach(alert => {
                const typeClass = getAlertTypeClass(alert.type);
                const timeAgo = getTimeAgo(alert.timestamp);
                
                html += `
                    <div class="alert-item ${typeClass}">
                        <div class="alert-title">
                            <span class="alert-type ${typeClass}">${alert.type}</span>
                            ${alert.title}
                        </div>
                        <div class="alert-message">${alert.message}</div>
                        <div class="alert-meta">
                            <span><i class="fas fa-clock"></i> ${timeAgo}</span>
                            <span><i class="fas fa-file"></i> ${alert.file}</span>
                        </div>
                    </div>
                `;
            });
            
            content.innerHTML = html;
        }

        // Fonction pour obtenir la classe CSS du type d'alerte
        function getAlertTypeClass(type) {
            switch(type.toLowerCase()) {
                case 'critical':
                case 'error':
                case 'fatal':
                    return 'critical';
                case 'warning':
                case 'warn':
                    return 'warning';
                case 'info':
                case 'information':
                    return 'info';
                case 'success':
                    return 'success';
                default:
                    return 'info';
            }
        }

        // Fonction pour calculer le temps écoulé
        function getTimeAgo(timestamp) {
            const now = new Date();
            const alertTime = new Date(timestamp);
            const diff = Math.floor((now - alertTime) / 1000);
            
            if (diff < 60) return 'Il y a ' + diff + 's';
            if (diff < 3600) return 'Il y a ' + Math.floor(diff / 60) + 'min';
            if (diff < 86400) return 'Il y a ' + Math.floor(diff / 3600) + 'h';
            return 'Il y a ' + Math.floor(diff / 86400) + 'j';
        }

        // Fonction pour mettre à jour le compteur d'alertes
        function updateAlertCount(count) {
            const countElement = document.getElementById('alert-count');
            countElement.textContent = count;
            
            if (count > 0) {
                countElement.style.display = 'flex';
            } else {
                countElement.style.display = 'none';
            }
        }

        // Fonction pour actualiser les alertes
        function refreshAlerts() {
            loadAlerts();
        }

        // Fonction pour vider toutes les alertes
        function clearAllAlerts() {
            if (confirm('Êtes-vous sûr de vouloir vider toutes les alertes ?')) {
                fetch('clear_system_alerts.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadAlerts();
                        }
                    })
                    .catch(error => console.error('Erreur:', error));
            }
        }

        // Auto-refresh des alertes toutes les 30 secondes
        function startAlertRefresh() {
            alertInterval = setInterval(() => {
                if (alertPanelOpen) {
                    loadAlerts();
                }
            }, 30000);
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Charger les alertes au démarrage
            loadAlerts();
            
            // Démarrer l'auto-refresh
            startAlertRefresh();
            
            // Masquer les alertes après 5 secondes
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
        });

        // Nettoyer l'intervalle à la fermeture de la page
        window.addEventListener('beforeunload', function() {
            if (alertInterval) {
                clearInterval(alertInterval);
            }
        });
    </script>
</body>
</html>
