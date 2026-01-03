<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['id_utilisateur']) || empty($_SESSION['id_utilisateur'])) {
        header('Location: connexion.php?error=' . urlencode('Veuillez vous connecter'));
        exit();
    }
    
    // Vérifier les droits d'accès pour les états de stock
    if (!can_user('etat_stock', 'voir')) {
        header('Location: index.php?error=' . urlencode('Accès refusé aux états de stock'));
        exit();
    }
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données: ' . $th->getMessage();
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}

// Traitement de la génération d'état
if (isset($_POST['generer_etat'])) {
    // Vérifier les droits pour voir les états
    if (!can_user('etat_stock', 'voir')) {
        header('Location: etat_stock.php?error=' . urlencode('Accès refusé pour voir les états'));
        exit();
    }
    $type_etat = $_POST['type_etat'];
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $heure_debut = $_POST['heure_debut'] ?? '00:00';
    $heure_fin = $_POST['heure_fin'] ?? '23:59';
    $filtre_stock = $_POST['filtre_stock'] ?? '';
    $valeur_stock = $_POST['valeur_stock'] ?? 0;
    $regroupement = $_POST['regroupement'] ?? 'aucun';
    $critere_regroupement = $_POST['critere_regroupement'] ?? '';
    $autoprint = isset($_POST['autoprint']);
    
    // Redirection vers la page de génération avec les paramètres
    $params = http_build_query([
        'type' => $type_etat,
        'date_debut' => $date_debut,
        'date_fin' => $date_fin,
        'heure_debut' => $heure_debut,
        'heure_fin' => $heure_fin,
        'filtre_stock' => $filtre_stock,
        'valeur_stock' => $valeur_stock,
        'regroupement' => $regroupement,
        'critere_regroupement' => $critere_regroupement,
        'categorie' => $_POST['categorie'] ?? '',
        'client' => $_POST['client'] ?? '',
        'statut' => $_POST['statut'] ?? '',
        'limite_resultats' => $_POST['limite_resultats'] ?? 1000,
        'seuil_stock_faible' => $_POST['seuil_stock_faible'] ?? 10,
        'masquer_prix_achat' => $_POST['masquer_prix_achat'] ?? '0',
        'masquer_description' => $_POST['masquer_description'] ?? '0',
        'masquer_statut' => $_POST['masquer_statut'] ?? '0',
        'masquer_categorie' => $_POST['masquer_categorie'] ?? '0',
        'autoprint' => $autoprint ? '1' : '0'
    ]);
    
    header('Location: generation_etat_stock.php?' . $params);
    exit();
}

// Récupération des données pour les listes déroulantes
try {
    // Catégories d'articles
    $categories = $cnx->query("SELECT id_categorie, nom_categorie FROM categorie_article ORDER BY nom_categorie")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fournisseurs supprimés
    
    // Clients
    $clients = $cnx->query("SELECT IDCLIENT, NomPrenomClient FROM client ORDER BY NomPrenomClient LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    
    
} catch (Exception $e) {
    $erreur = "Erreur lors du chargement des données : " . $e->getMessage();
}

// Définition des types d'états basés sur votre vraie base de données
$types_etats = [
    'listing_produits' => 'LISTING DES PRODUITS',
    'listing_produits_desactives' => 'LISTING DES PRODUITS DÉSACTIVÉS',
    'listing_stock' => 'LISTING DU STOCK',
    'listing_produits_vendus' => 'LISTING DES PRODUITS VENDUS',
    'listing_produits_vendus_credit' => 'LISTING DES PRODUITS VENDUS À CRÉDIT',
    'listing_produits_non_vendus' => 'LISTING DES PRODUITS NON-VENDUS',
    'listing_produits_par_categorie' => 'LISTING DES PRODUITS PAR CATÉGORIE',
    'listing_clients_produits' => 'LISTING DES CLIENTS AVEC LEURS PRODUITS',
    'listing_ventes_periode' => 'LISTING DES VENTES PAR PÉRIODE',
    'listing_ventes_credit_periode' => 'LISTING DES VENTES CRÉDIT PAR PÉRIODE',
    'listing_stock_faible' => 'LISTING DU STOCK FAIBLE',
    'listing_stock_zero' => 'LISTING DU STOCK ZÉRO',
    'listing_num_serie_disponibles' => 'LISTING DES NUMÉROS DE SÉRIE DISPONIBLES',
    'listing_num_serie_vendus' => 'LISTING DES NUMÉROS DE SÉRIE VENDUS',
    'listing_num_serie_vendus_credit' => 'LISTING DES NUMÉROS DE SÉRIE VENDUS À CRÉDIT',
    'listing_num_serie_vendus_client' => 'LISTING DES NUMÉROS DE SÉRIE VENDUS PAR CLIENT',
    'valeur_stock' => 'VALEUR DU STOCK',
    'valeur_stock_resume' => 'VALEUR DU STOCK RÉSUMÉE',
    'statistiques_ventes' => 'STATISTIQUES DES VENTES',
    'top_produits_vendus' => 'TOP PRODUITS VENDUS'
];

// Valeurs par défaut
$date_aujourdhui = date('d/m/Y');
$heure_debut_defaut = '00:00';
$heure_fin_defaut = '23:59';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>États de Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            margin: 0;
            padding: 0;
            color: #000000;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .container {
            max-width: 1200px;
        }

        .card {
            border: 1px solid #dc3545;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
            margin-bottom: 1.5rem;
            background: #ffffff;
        }

        .card-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .etats-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dc3545;
            border-radius: 8px;
            background: #ffffff;
        }

        .etat-item {
            padding: 10px 12px;
            border-bottom: 1px solid #dc3545;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            color: #000000;
        }

        .etat-item:hover {
            background-color: #dc3545;
            color: white;
            transform: translateX(3px);
        }

        .etat-item.selected {
            background-color: #dc3545;
            color: white;
        }

        .etat-item:last-child {
            border-bottom: none;
        }

        .etat-number {
            background-color: #dc3545;
            color: #ffffff;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 12px;
            font-size: 0.8em;
        }

        .etat-item.selected .etat-number {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
            background: #ffffff;
            color: #333333;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            background: #ffffff;
        }

        .form-control::placeholder {
            color: #6c757d;
        }

        .btn-primary {
            background: #dc3545;
            border: 1px solid #dc3545;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(220, 53, 69, 0.4);
            background: #c82333;
        }

        .criteria-section {
            background: #ffffff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            border: 1px solid #dc3545;
        }

        .criteria-title {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 0.8rem;
            padding-bottom: 0.3rem;
            border-bottom: 2px solid #dc3545;
            font-size: 0.95rem;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        .form-check {
            margin-bottom: 0;
        }

        .form-check-input:checked {
            background-color: #007bff;
            border-color: #007bff;
        }

        .form-check-label {
            color: #333333;
            font-size: 0.9rem;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .form-label {
            color: #333333;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .etat-label {
            font-size: 0.9rem;
            line-height: 1.3;
        }

        /* Réduction de la hauteur pour éviter le scroll */
        .card-body {
            padding: 1rem;
        }

        .row {
            margin-bottom: 0.5rem;
        }

        .col-md-3, .col-md-4, .col-md-6 {
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .etats-list {
                max-height: 300px;
            }
            
            .checkbox-group {
                flex-direction: column;
            }
            
            .header {
                padding: 1rem 0;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    <?php include('includes/theme_switcher.php'); ?>

    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-bar"></i> États de Stock</h1>
                    <p class="mb-0">Générez des rapports détaillés sur votre stock et vos ventes</p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-file-alt fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($erreur)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($erreur) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="etatForm">
            <div class="row">
                <!-- Liste des états -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list"></i> États à imprimer
                        </div>
                        <div class="card-body p-0">
                            <div class="etats-list">
                                <?php $counter = 1; ?>
                                <?php foreach ($types_etats as $key => $label): ?>
                                    <div class="etat-item" data-type="<?= $key ?>">
                                        <div class="etat-number"><?= $counter ?></div>
                                        <div class="etat-label"><?= htmlspecialchars($label) ?></div>
                                    </div>
                                    <?php $counter++; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critères de sélection -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-filter"></i> Détermination des critères
                        </div>
                        <div class="card-body">
                            <!-- Filtres principaux -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Période du :</label>
                                    <input type="date" name="date_debut" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">au :</label>
                                    <input type="date" name="date_fin" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            
                            <!-- Filtres secondaires -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Catégorie :</label>
                                    <select name="categorie" class="form-select">
                                        <option value="">TOUTES</option>
                                        <?php foreach ($categories as $categorie): ?>
                                            <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['nom_categorie']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Statut :</label>
                                    <select name="statut" class="form-select">
                                        <option value="">TOUS</option>
                                        <option value="actif">ACTIF</option>
                                        <option value="desactive">DÉSACTIVÉ</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Options avancées -->
                            <div class="criteria-section">
                                <h6 class="criteria-title">Options avancées</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Stock :</label>
                                        <select name="filtre_stock" class="form-select">
                                            <option value="">Peu importe</option>
                                            <option value=">">Stock > à</option>
                                            <option value="<">Stock < à</option>
                                            <option value="=">Stock = à</option>
                                            <option value=">=">Stock >= à</option>
                                            <option value="<=">Stock <= à</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Valeur :</label>
                                        <input type="number" name="valeur_stock" class="form-control" value="0" step="0.01">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Seuil stock faible :</label>
                                        <input type="number" name="seuil_stock_faible" class="form-control" value="10" min="1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Limite résultats :</label>
                                        <input type="number" name="limite_resultats" class="form-control" value="1000" min="1" max="10000">
                                    </div>
                                </div>
                            </div>

                            <!-- Options d'affichage -->
                            <div class="criteria-section">
                                <h6 class="criteria-title">Options d'affichage</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="masquer_prix_achat" id="masquer_prix_achat" value="1">
                                            <label class="form-check-label" for="masquer_prix_achat">
                                                Masquer prix d'achat
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="masquer_description" id="masquer_description" value="1">
                                            <label class="form-check-label" for="masquer_description">
                                                Masquer description
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="masquer_statut" id="masquer_statut" value="1">
                                            <label class="form-check-label" for="masquer_statut">
                                                Masquer statut article
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="masquer_categorie" id="masquer_categorie" value="1">
                                            <label class="form-check-label" for="masquer_categorie">
                                                Masquer catégorie
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bouton de génération -->
                            <div class="text-center mt-4">
                                <input type="hidden" name="type_etat" id="type_etat_selected" value="">
                                <div class="mb-3">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="autoprint" id="autoprint" value="1">
                                        <label class="form-check-label" for="autoprint">
                                            <i class="fas fa-print"></i> Impression automatique
                                        </label>
                                    </div>
                                </div>
                                <?php if (can_user('etat_stock', 'voir')): ?>
                                    <button type="submit" name="generer_etat" class="btn btn-primary btn-lg">
                                        <i class="fas fa-chart-bar"></i> Générer l'État sélectionné
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary btn-lg" disabled title="Accès refusé">
                                        <i class="fas fa-chart-bar"></i> Générer l'État sélectionné
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sélection d'état
            const etatItems = document.querySelectorAll('.etat-item');
            const typeEtatInput = document.getElementById('type_etat_selected');
            
            etatItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Retirer la sélection précédente
                    etatItems.forEach(i => i.classList.remove('selected'));
                    
                    // Ajouter la sélection à l'élément cliqué
                    this.classList.add('selected');
                    
                    // Mettre à jour le champ caché
                    typeEtatInput.value = this.dataset.type;
                });
            });

            // Sélectionner le premier état par défaut
            if (etatItems.length > 0) {
                etatItems[0].click();
            }

            // Validation du formulaire
            document.getElementById('etatForm').addEventListener('submit', function(e) {
                if (!typeEtatInput.value) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un état à générer.');
                    return false;
                }
            });

            // Animation des éléments au survol
            document.querySelectorAll('.etat-item').forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.transform = 'translateX(0)';
                    }
                });
            });
        });
    </script>
</body>
</html>
