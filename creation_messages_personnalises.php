<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modèles SMS et Email Personnalisés - SOTECH</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            padding: 30px;
            backdrop-filter: blur(10px);
        }

        .header-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            border-radius: 15px;
            color: white;
        }

        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .feature-card.sms {
            border-left-color: #28a745;
        }

        .feature-card.email {
            border-left-color: #007bff;
        }

        .feature-card.general {
            border-left-color: #ffc107;
        }

        .features-list {
            list-style: none;
            padding: 0;
        }

        .features-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .features-list i {
            color: #28a745;
            margin-right: 10px;
        }

        .btn-custom {
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: white;
            text-decoration: none;
        }

        .btn-sms {
            background: linear-gradient(45deg, #28a745, #20c997);
        }

        .btn-email {
            background: linear-gradient(45deg, #007bff, #6610f2);
        }

        .btn-demo {
            background: linear-gradient(45deg, #ffc107, #fd7e14);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .color-palette {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }

        .color-option {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #ddd;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.9em;
            color: #777;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
<?php include('includes/user_indicator.php'); ?>
<?php include('includes/navigation_buttons.php'); ?>  
    
    <div class="container-fluid">
        <div class="main-container">
            <!-- Header Section -->
            <div class="header-section">
                <h1><i class="fas fa-magic"></i> Création de Messages Personnalisés</h1>
                <p>Créez facilement vos propres modèles de SMS et emails pour communiquer avec vos clients</p>
            </div>

            <!-- Fonctionnalités SMS -->
            <div class="feature-card sms">
                <div class="row">
                    <div class="col-md-8">
                        <h3><i class="fas fa-sms"></i> Messages SMS Personnalisés</h3>
                        <p>Créez vos propres modèles de SMS pour vos clients :</p>
        <ul class="features-list">
                            <li><i class="fas fa-check"></i> Écrivez vos messages une seule fois</li>
                            <li><i class="fas fa-check"></i> Sauvegardez-les pour les réutiliser</li>
                            <li><i class="fas fa-check"></i> Organisez par catégories (Promotion, Rappel, etc.)</li>
                            <li><i class="fas fa-check"></i> Insérez automatiquement le nom du client</li>
                            <li><i class="fas fa-check"></i> Voir l'aperçu avant d'envoyer</li>
                            <li><i class="fas fa-check"></i> Envoyer directement depuis vos modèles</li>
        </ul>
                    </div>
                    <div class="col-md-4 text-center">
                        <a href="sms_personnalise.php" class="btn-custom btn-sms">
                            <i class="fas fa-sms"></i> Créer des SMS
                        </a>
                    </div>
                </div>
            </div>

            <!-- Fonctionnalités Email -->
            <div class="feature-card email">
                <div class="row">
                    <div class="col-md-8">
                        <h3><i class="fas fa-envelope"></i> Emails Personnalisés</h3>
                        <p>Créez des emails professionnels comme avec Word :</p>
                        <ul class="features-list">
                            <li><i class="fas fa-check"></i> Éditeur simple comme Word</li>
                            <li><i class="fas fa-check"></i> Modèles prêts à utiliser (Confirmation, Facture, etc.)</li>
                            <li><i class="fas fa-check"></i> Texte en gras, italique, couleurs</li>
                            <li><i class="fas fa-check"></i> Ajouter des images et liens</li>
                            <li><i class="fas fa-check"></i> Insérer automatiquement les infos client</li>
                            <li><i class="fas fa-check"></i> Voir l'aperçu avant d'envoyer</li>
                            <li><i class="fas fa-check"></i> Envoyer directement depuis vos modèles</li>
                        </ul>
                    </div>
                    <div class="col-md-4 text-center">
                        <a href="e_mail_personalise.php" class="btn-custom btn-email">
                            <i class="fas fa-envelope"></i> Créer des Emails
                        </a>
                    </div>
                </div>
            </div>

            <!-- Avantages -->
            <div class="feature-card general">
                <h3><i class="fas fa-star"></i> Pourquoi utiliser ces outils ?</h3>
                <div class="row">
                    <div class="col-md-6">
                        <h5>💡 Gagnez du temps</h5>
                        <ul class="features-list">
                            <li><i class="fas fa-check"></i> Écrivez une fois, utilisez plusieurs fois</li>
                            <li><i class="fas fa-check"></i> Plus besoin de retaper les mêmes messages</li>
                            <li><i class="fas fa-check"></i> Envoi rapide à vos clients</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>🎯 Messages professionnels</h5>
        <ul class="features-list">
                            <li><i class="fas fa-check"></i> Messages bien organisés</li>
                            <li><i class="fas fa-check"></i> Personnalisés avec le nom du client</li>
                            <li><i class="fas fa-check"></i> Couleurs pour différencier vos messages</li>
        </ul>
                    </div>
                </div>
            </div>

            <!-- Comment ça marche -->
            <div class="feature-card">
                <h3><i class="fas fa-lightbulb"></i> Comment ça marche ?</h3>
                <div class="row">
                    <div class="col-md-6">
                        <h5>📝 Créer un modèle :</h5>
                        <ul class="features-list">
                            <li><i class="fas fa-check"></i> Donnez un titre à votre message</li>
                            <li><i class="fas fa-check"></i> Choisissez une catégorie (Promotion, Rappel, etc.)</li>
                            <li><i class="fas fa-check"></i> Écrivez votre message</li>
                            <li><i class="fas fa-check"></i> Cliquez sur "Enregistrer"</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>🚀 Utiliser un modèle :</h5>
                        <ul class="features-list">
                            <li><i class="fas fa-check"></i> Trouvez votre modèle dans la liste</li>
                            <li><i class="fas fa-check"></i> Cliquez sur "Utiliser"</li>
                            <li><i class="fas fa-check"></i> Le message s'ouvre prêt à envoyer</li>
                            <li><i class="fas fa-check"></i> Ajoutez le numéro du client et envoyez</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Actions Rapides -->
            <div class="text-center">
                <h3>🚀 Commencez Maintenant</h3>
                <p>Choisissez le type de message que vous voulez créer :</p>
                <div class="btn-group" role="group">
                    <a href="sms_personnalise.php" class="btn-custom btn-sms">
                        <i class="fas fa-sms"></i> Créer des SMS
                    </a>
                    <a href="e_mail_personalise.php" class="btn-custom btn-email">
                        <i class="fas fa-envelope"></i> Créer des Emails
                    </a>
                </div>
            </div>

            <!-- Exemples -->
            <div class="feature-card">
                <h3><i class="fas fa-comment"></i> Exemples de messages</h3>
                <div class="row">
                    <div class="col-md-6">
                        <h5>📱 SMS :</h5>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 10px 0;">
                            <strong>Promotion :</strong><br>
                            "Bonjour [NOM_CLIENT], profitez de -20% sur tous nos produits jusqu'au [DATE] !"
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 10px 0;">
                            <strong>Rappel :</strong><br>
                            "Bonjour [NOM_CLIENT], votre commande n°[REFERENCE] est prête à être récupérée."
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>📧 Email :</h5>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 10px 0;">
                            <strong>Confirmation :</strong><br>
                            "Bonjour [NOM_CLIENT],<br>
                            Nous confirmons votre commande d'un montant de [MONTANT] FCFA.<br>
                            Merci pour votre confiance !"
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 10px 0;">
                            <strong>Newsletter :</strong><br>
                            "Découvrez nos nouvelles offres et actualités dans cette newsletter."
                        </div>
                    </div>
                </div>
            </div>

        <div class="footer">
                <p><strong>Besoin d'aide ?</strong> Contactez l'équipe technique pour toute question.</p>
                <p>&copy; 2024 SOTECH - Tous droits réservés.</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>

</html>
