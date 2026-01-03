<?php
session_start();
require_once '../db/connecting.php';
require_once '../fonction_traitement/fonction.php';

// Vérification des droits d'accès (niveau 3+ requis)
if (!isset($_SESSION['id_utilisateur']) || $_SESSION['niveau_acces'] < 3) {
    header('Location: ../connexion.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pages Utilisateur - SOTech</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #d84315, #b71c1c);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2em;
        }
        
        .navigation {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .navigation a {
            color: #d84315;
            text-decoration: none;
            margin-right: 20px;
            font-weight: bold;
        }
        
        .navigation a:hover {
            color: #b71c1c;
        }
        
        .content {
            padding: 30px;
        }
        
        .page-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .page-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .page-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .page-card h3 {
            color: #d84315;
            margin-bottom: 10px;
        }
        
        .page-card p {
            color: #666;
            margin-bottom: 15px;
        }
        
        .page-card a {
            background: #d84315;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .page-card a:hover {
            background: #b71c1c;
        }
        
        .icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navigation">
            <a href="../index.php">🏠 Accueil</a>
            <a href="../journal_systeme.php">📋 Journal Système</a>
            <a href="../utilisateur.php">👤 Gestion Utilisateurs</a>
        </div>
        
        <div class="header">
            <h1>📱 Pages Utilisateur SOTech</h1>
            <p>Interface d'administration et de monitoring</p>
        </div>
        
        <div class="content">
            <h2>🔧 Outils d'Administration</h2>
            <p>Accédez aux différentes pages d'administration et de monitoring du système.</p>
            
            <div class="page-grid">
                <div class="page-card">
                    <div class="icon">🚨</div>
                    <h3>Alertes Système</h3>
                    <p>Monitoring en temps réel des erreurs et performances du système. Consultez les logs d'erreurs, les statistiques et les alertes critiques.</p>
                    <a href="alertes_systeme.php">Accéder aux Alertes</a>
                </div>
                
                <div class="page-card">
                    <div class="icon">📊</div>
                    <h3>Journal Système</h3>
                    <p>Consultez l'historique complet des actions du système avec filtres avancés et recherche.</p>
                    <a href="../journal_systeme.php">Accéder au Journal</a>
                </div>
                
                <div class="page-card">
                    <div class="icon">👥</div>
                    <h3>Gestion Utilisateurs</h3>
                    <p>Administrez les comptes utilisateurs, les droits d'accès et les permissions.</p>
                    <a href="../utilisateur.php">Gérer les Utilisateurs</a>
                </div>
                
                <div class="page-card">
                    <div class="icon">⚙️</div>
                    <h3>Paramètres</h3>
                    <p>Configurez les paramètres généraux du système et les préférences.</p>
                    <a href="../parametre.php">Accéder aux Paramètres</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
