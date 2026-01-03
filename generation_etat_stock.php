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
    
    // Vérifier les droits d'accès pour voir les états
    if (!can_user('etat_stock', 'voir')) {
        header('Location: etat_stock.php?error=' . urlencode('Accès refusé pour voir les états'));
        exit();
    }
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données: ' . $th->getMessage();
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}

// Récupération des paramètres
$type_etat = $_GET['type'] ?? '';
$date_debut = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$heure_debut = $_GET['heure_debut'] ?? '00:00';
$heure_fin = $_GET['heure_fin'] ?? '23:59';
$filtre_stock = $_GET['filtre_stock'] ?? '';
$valeur_stock = $_GET['valeur_stock'] ?? 0;
$regroupement = $_GET['regroupement'] ?? 'aucun';
$critere_regroupement = $_GET['critere_regroupement'] ?? '';
$categorie = $_GET['categorie'] ?? '';
$client = $_GET['client'] ?? '';
$statut = $_GET['statut'] ?? '';
$limite_resultats = $_GET['limite_resultats'] ?? 1000;
$seuil_stock_faible = $_GET['seuil_stock_faible'] ?? 10;
$masquer_prix_achat = $_GET['masquer_prix_achat'] ?? '0';
$masquer_description = $_GET['masquer_description'] ?? '0';
$masquer_statut = $_GET['masquer_statut'] ?? '0';
$masquer_categorie = $_GET['masquer_categorie'] ?? '0';

// Conversion des dates
$datetime_debut = $date_debut . ' ' . $heure_debut . ':00';
$datetime_fin = $date_fin . ' ' . $heure_fin . ':59';

// Construction des conditions WHERE
$conditions = [];
$params = [];

// Condition de base
$conditions[] = "1=1";

// Condition de catégorie
if ($categorie) {
    $conditions[] = "a.id_categorie = ?";
    $params[] = $categorie;
}

// Fournisseur supprimé

// Condition de statut
if ($statut) {
    if ($statut == 'actif') {
        $conditions[] = "a.desactiver != 'oui'";
    } else {
        $conditions[] = "a.desactiver = 'oui'";
    }
}

// Condition de stock
if ($filtre_stock && $valeur_stock > 0) {
    $conditions[] = "COALESCE(s.StockActuel, 0) " . $filtre_stock . " ?";
    $params[] = $valeur_stock;
}

// Produit et catégorie de rotation supprimés

$where_clause = implode(' AND ', $conditions);

// Fonction pour construire les requêtes SQL optimisées basées sur votre vraie base de données
function getEtatQuery($type_etat, $where_clause, $params, $datetime_debut, $datetime_fin, $regroupement, $limite_resultats, $seuil_stock_faible, $date_debut, $date_fin) {
    $base_joins = "FROM article a 
                   LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE 
                   LEFT JOIN categorie_article c ON a.id_categorie = c.id_categorie";
    
    $base_select = "a.libelle, a.CodePersoArticle as Code, a.descriptif as Description, 
                   a.PrixAchatHT, a.PrixVenteTTC, 
                   COALESCE(s.StockActuel, 0) as StockActuel, 
                   c.nom_categorie, a.desactiver as StatutArticle";
    
    switch ($type_etat) {
        case 'listing_produits':
            return [
                'sql' => "SELECT $base_select 
                         $base_joins 
                         WHERE $where_clause 
                         ORDER BY a.libelle 
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'LISTING DES PRODUITS'
            ];
            
        case 'listing_produits_desactives':
            return [
                'sql' => "SELECT $base_select 
                         $base_joins 
                         WHERE $where_clause AND a.desactiver = 'oui' 
                         ORDER BY a.libelle 
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'LISTING DES PRODUITS DÉSACTIVÉS'
            ];
            
        case 'listing_stock':
            return [
                'sql' => "SELECT $base_select, 
                         (COALESCE(s.StockActuel, 0) * a.PrixAchatHT) as valeur_stock_achat,
                         (COALESCE(s.StockActuel, 0) * a.PrixVenteTTC) as valeur_stock_vente
                         $base_joins 
                         WHERE $where_clause 
                         ORDER BY COALESCE(s.StockActuel, 0) DESC 
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'LISTING DU STOCK'
            ];
            
        case 'listing_produits_vendus':
            return [
                'sql' => "SELECT $base_select, 
                         SUM(fa.QuantiteVendue) as quantite_vendue,
                         SUM(fa.QuantiteVendue * a.PrixVenteTTC) as montant_vendu
                         $base_joins 
                         LEFT JOIN facture_article fa ON a.IDARTICLE = fa.IDARTICLE 
                         LEFT JOIN vente v ON fa.NumeroVente = v.NumeroVente 
                         WHERE $where_clause 
                         AND DATE(v.DateIns) BETWEEN ? AND ?
                         GROUP BY a.libelle, a.CodePersoArticle, a.descriptif, a.PrixAchatHT, a.PrixVenteTTC, COALESCE(s.StockActuel, 0), c.nom_categorie, a.desactiver 
                         HAVING quantite_vendue > 0
                         ORDER BY quantite_vendue DESC 
                         LIMIT " . intval($limite_resultats),
                'params' => array_merge($params, [$date_debut, $date_fin]),
                'title' => 'LISTING DES PRODUITS VENDUS'
            ];
            
        case 'listing_produits_vendus_credit':
            return [
                'sql' => "SELECT $base_select, 
                         COUNT(*) as nombre_ventes_credit,
                         SUM(vc.AccompteVerse) as total_acomptes
                         $base_joins 
                         LEFT JOIN ventes_credit_ligne vcl ON a.IDARTICLE = vcl.IDARTICLE 
                         LEFT JOIN ventes_credit vc ON vcl.IDVenteCredit = vc.IDVenteCredit
                         WHERE $where_clause 
                         AND DATE(vc.DateMod) BETWEEN ? AND ?
                         AND vc.statut != 'Transféré'
                         GROUP BY a.libelle, a.CodePersoArticle, a.descriptif, a.PrixAchatHT, a.PrixVenteTTC, COALESCE(s.StockActuel, 0), c.nom_categorie, a.desactiver 
                         HAVING nombre_ventes_credit > 0
                         ORDER BY total_acomptes DESC 
                         LIMIT " . intval($limite_resultats),
                'params' => array_merge($params, [$date_debut, $date_fin]),
                'title' => 'LISTING DES PRODUITS VENDUS À CRÉDIT'
            ];
            
        case 'listing_produits_non_vendus':
            return [
                'sql' => "SELECT $base_select 
                         $base_joins 
                         LEFT JOIN facture_article fa ON a.IDARTICLE = fa.IDARTICLE 
                         LEFT JOIN vente v ON fa.NumeroVente = v.NumeroVente 
                         AND DATE(v.DateIns) BETWEEN ? AND ?
                         WHERE $where_clause 
                         AND fa.IDARTICLE IS NULL
                         ORDER BY a.libelle 
                         LIMIT " . intval($limite_resultats),
                'params' => array_merge($params, [$date_debut, $date_fin]),
                'title' => 'LISTING DES PRODUITS NON-VENDUS'
            ];
            
        case 'listing_produits_par_categorie':
            return [
                'sql' => "SELECT $base_select, 
                         COUNT(*) as nombre_articles_categorie
                         $base_joins 
                         WHERE $where_clause 
                         GROUP BY a.id_categorie, c.nom_categorie
                         ORDER BY c.nom_categorie 
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'LISTING DES PRODUITS PAR CATÉGORIE'
            ];
            
            
        case 'listing_clients_produits':
            return [
                'sql' => "SELECT c.NomPrenomClient, c.Telephone,
                         a.libelle, a.CodePersoArticle,
                         SUM(fa.QuantiteVendue) as quantite_achetee,
                         SUM(fa.QuantiteVendue * a.PrixVenteTTC) as montant_total
                         FROM client c
                         JOIN vente v ON c.IDCLIENT = v.IDCLIENT
                         JOIN facture_article fa ON v.NumeroVente = fa.NumeroVente
                         JOIN article a ON fa.IDARTICLE = a.IDARTICLE
                         WHERE DATE(v.DateIns) BETWEEN ? AND ?
                         GROUP BY c.IDCLIENT, a.IDARTICLE
                         ORDER BY c.NomPrenomClient, montant_total DESC
                         LIMIT " . intval($limite_resultats),
                'params' => [$date_debut, $date_fin],
                'title' => 'LISTING DES CLIENTS AVEC LEURS PRODUITS'
            ];
            
        case 'listing_ventes_periode':
            return [
                'sql' => "SELECT v.NumeroVente, v.DateIns, c.NomPrenomClient,
                         COUNT(fa.IDARTICLE) as nombre_articles,
                         SUM(fa.QuantiteVendue) as quantite_totale,
                         SUM(fa.QuantiteVendue * a.PrixVenteTTC) as montant_total
                         FROM vente v
                         LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT
                         JOIN facture_article fa ON v.NumeroVente = fa.NumeroVente
                         JOIN article a ON fa.IDARTICLE = a.IDARTICLE
                         WHERE DATE(v.DateIns) BETWEEN ? AND ?
                         GROUP BY v.IDFactureVente
                         ORDER BY v.DateIns DESC
                         LIMIT " . intval($limite_resultats),
                'params' => [$date_debut, $date_fin],
                'title' => 'LISTING DES VENTES PAR PÉRIODE'
            ];
            
        case 'listing_ventes_credit_periode':
            return [
                'sql' => "SELECT vc.IDVenteCredit, vc.DateMod, c.NomPrenomClient,
                         vc.AccompteVerse, vc.Statut
                         FROM ventes_credit vc
                         LEFT JOIN client c ON vc.IDCLIENT = c.IDCLIENT
                         WHERE DATE(vc.DateMod) BETWEEN ? AND ?
                         AND vc.Statut != 'Transféré'
                         ORDER BY vc.DateMod DESC
                         LIMIT " . intval($limite_resultats),
                'params' => [$date_debut, $date_fin],
                'title' => 'LISTING DES VENTES CRÉDIT PAR PÉRIODE'
            ];
            
        case 'listing_stock_faible':
            return [
                'sql' => "SELECT $base_select 
                         $base_joins 
                         WHERE $where_clause 
                         AND COALESCE(s.StockActuel, 0) <= ?
                         ORDER BY COALESCE(s.StockActuel, 0) ASC 
                         LIMIT " . intval($limite_resultats),
                'params' => array_merge($params, [$seuil_stock_faible]),
                'title' => 'LISTING DU STOCK FAIBLE'
            ];
            
        case 'listing_stock_zero':
            return [
                'sql' => "SELECT $base_select 
                         $base_joins 
                         WHERE $where_clause 
                         AND (s.StockActuel IS NULL OR s.StockActuel = 0)
                         ORDER BY a.libelle 
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'LISTING DU STOCK ZÉRO'
            ];
            
        case 'listing_num_serie_disponibles':
            return [
                'sql' => "SELECT a.IDARTICLE, a.libelle, a.CodePersoArticle,
                         ns.NUMERO_SERIE, ns.DateIns, ns.statut
                         FROM article a
                         JOIN num_serie ns ON a.IDARTICLE = ns.IDARTICLE
                         WHERE ns.statut = 'disponible'
                         AND $where_clause
                         ORDER BY a.libelle, ns.NUMERO_SERIE
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'LISTING DES NUMÉROS DE SÉRIE DISPONIBLES'
            ];
            
            
        case 'valeur_stock':
            return [
                'sql' => "SELECT $base_select, 
                         (COALESCE(s.StockActuel, 0) * a.PrixAchatHT) as valeur_achat,
                         (COALESCE(s.StockActuel, 0) * a.PrixVenteTTC) as valeur_vente
                         $base_joins 
                         WHERE $where_clause 
                         ORDER BY valeur_achat DESC 
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'VALEUR DU STOCK'
            ];
            
        case 'valeur_stock_resume':
            return [
                'sql' => "SELECT 
                         COUNT(*) as nombre_produits,
                         SUM(COALESCE(s.StockActuel, 0)) as stock_total,
                         SUM(COALESCE(s.StockActuel, 0) * a.PrixAchatHT) as valeur_achat_totale,
                         SUM(COALESCE(s.StockActuel, 0) * a.PrixVenteTTC) as valeur_vente_totale
                         $base_joins 
                         WHERE $where_clause",
                'params' => $params,
                'title' => 'VALEUR DU STOCK RÉSUMÉE'
            ];
            
        case 'statistiques_ventes':
            return [
                'sql' => "SELECT 
                         COUNT(DISTINCT v.IDFactureVente) as nombre_ventes,
                         COUNT(DISTINCT v.IDCLIENT) as nombre_clients,
                         SUM(fa.QuantiteVendue) as quantite_totale_vendue,
                         SUM(fa.QuantiteVendue * a.PrixVenteTTC) as chiffre_affaires
                         FROM vente v
                         JOIN facture_article fa ON v.NumeroVente = fa.NumeroVente
                         JOIN article a ON fa.IDARTICLE = a.IDARTICLE
                         WHERE DATE(v.DateIns) BETWEEN ? AND ?",
                'params' => [$date_debut, $date_fin],
                'title' => 'STATISTIQUES DES VENTES'
            ];
            
        case 'top_produits_vendus':
            return [
                'sql' => "SELECT $base_select, 
                         SUM(fa.QuantiteVendue) as quantite_vendue,
                         SUM(fa.QuantiteVendue * a.PrixVenteTTC) as chiffre_affaires
                         $base_joins 
                         LEFT JOIN facture_article fa ON a.IDARTICLE = fa.IDARTICLE 
                         LEFT JOIN vente v ON fa.NumeroVente = v.NumeroVente 
                         WHERE $where_clause 
                         AND DATE(v.DateIns) BETWEEN ? AND ?
                         GROUP BY a.libelle, a.CodePersoArticle, a.descriptif, a.PrixAchatHT, a.PrixVenteTTC, COALESCE(s.StockActuel, 0), c.nom_categorie, a.desactiver 
                         HAVING quantite_vendue > 0
                         ORDER BY chiffre_affaires DESC 
                         LIMIT " . intval($limite_resultats),
                'params' => array_merge($params, [$date_debut, $date_fin]),
                'title' => 'TOP PRODUITS VENDUS'
            ];
            
        case 'listing_num_serie_vendus':
            return [
                'sql' => "SELECT ns.NUMERO_SERIE, a.libelle, a.CodePersoArticle, 
                         v.DateIns as date_vente, v.NumeroVente,
                         c.NomPrenomClient, v.MontantTotal
                         FROM num_serie ns
                         LEFT JOIN article a ON ns.IDARTICLE = a.IDARTICLE
                         LEFT JOIN vente v ON ns.NumeroVente = v.NumeroVente
                         LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT
                         WHERE ns.NumeroVente IS NOT NULL
                         AND DATE(v.DateIns) BETWEEN ? AND ?
                         ORDER BY v.DateIns DESC
                         LIMIT " . intval($limite_resultats),
                'params' => [$date_debut, $date_fin],
                'title' => 'LISTING DES NUMÉROS DE SÉRIE VENDUS'
            ];
            
        case 'listing_num_serie_vendus_credit':
            return [
                'sql' => "SELECT ns.NUMERO_SERIE, a.libelle, a.CodePersoArticle, 
                         vc.DateMod as date_vente_credit, vc.NumeroVente,
                         c.NomPrenomClient, vc.AccompteVerse, vc.RestantAPayer, vc.Statut
                         FROM num_serie ns
                         LEFT JOIN article a ON ns.IDARTICLE = a.IDARTICLE
                         LEFT JOIN ventes_credit vc ON ns.NumeroVente = vc.NumeroVente AND ns.IDvente_credit = vc.IDVenteCredit
                         LEFT JOIN client c ON vc.IDCLIENT = c.IDCLIENT
                         WHERE ns.IDvente_credit IS NOT NULL
                         AND DATE(vc.DateMod) BETWEEN ? AND ?
                         ORDER BY vc.DateMod DESC
                         LIMIT " . intval($limite_resultats),
                'params' => [$date_debut, $date_fin],
                'title' => 'LISTING DES NUMÉROS DE SÉRIE VENDUS À CRÉDIT'
            ];
            
        case 'listing_num_serie_vendus_client':
            return [
                'sql' => "SELECT c.NomPrenomClient, c.Telephone,
                         COUNT(ns.NUMERO_SERIE) as nombre_series_vendues,
                         GROUP_CONCAT(DISTINCT a.libelle SEPARATOR ', ') as produits_achetes,
                         SUM(v.MontantTotal) as montant_total_ventes
                         FROM num_serie ns
                         LEFT JOIN article a ON ns.IDARTICLE = a.IDARTICLE
                         LEFT JOIN vente v ON ns.NumeroVente = v.NumeroVente
                         LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT
                         WHERE ns.NumeroVente IS NOT NULL
                         AND DATE(v.DateIns) BETWEEN ? AND ?
                         GROUP BY c.IDCLIENT, c.NomPrenomClient, c.Telephone
                         ORDER BY nombre_series_vendues DESC
                         LIMIT " . intval($limite_resultats),
                'params' => [$date_debut, $date_fin],
                'title' => 'LISTING DES NUMÉROS DE SÉRIE VENDUS PAR CLIENT'
            ];
            
        default:
            return [
                'sql' => "SELECT $base_select 
                         $base_joins 
                         WHERE $where_clause 
                         ORDER BY a.libelle 
                         LIMIT " . intval($limite_resultats),
                'params' => $params,
                'title' => 'LISTING GÉNÉRAL'
            ];
    }
}

// Exécution de la requête
try {
    $query_data = getEtatQuery($type_etat, $where_clause, $params, $datetime_debut, $datetime_fin, $regroupement, $limite_resultats, $seuil_stock_faible, $date_debut, $date_fin);
    
    $stmt = $cnx->prepare($query_data['sql']);
    $stmt->execute($query_data['params']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $titre_etat = $query_data['title'];
    
} catch (Exception $e) {
    $erreur = "Erreur lors de l'exécution de la requête : " . $e->getMessage();
    $results = [];
    $titre_etat = "ERREUR";
}

// Fonction pour formater les nombres
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, ',', ' ');
}

// Fonction pour formater les dates
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titre_etat) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .container { max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .table { font-size: 11px; }
            body { 
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 10px !important;
            }
            .header {
                background: #dc3545 !important;
                color: white !important;
                margin-bottom: 15px !important;
                padding: 10px !important;
                page-break-inside: avoid;
            }
            .card { 
                background: white !important;
                border: 1px solid #ccc !important;
                box-shadow: none !important;
                margin-bottom: 10px !important;
            }
            .table {
                background: white !important;
                border-collapse: collapse !important;
            }
            th, td {
                color: black !important;
                border: 1px solid #ccc !important;
                padding: 4px !important;
            }
            th {
                background: #f5f5f5 !important;
                color: black !important;
                font-weight: bold !important;
            }
            .alert {
                background: white !important;
                border: 1px solid #ccc !important;
                color: black !important;
            }
            .badge {
                background: #f0f0f0 !important;
                color: black !important;
                border: 1px solid #ccc !important;
            }
            .summary-card {
                background: #dc3545 !important;
                color: white !important;
                page-break-inside: avoid;
            }
            .table-responsive {
                overflow: visible !important;
            }
            .row {
                margin: 0 !important;
            }
            .col-md-3, .col-md-4, .col-md-6, .col-md-8 {
                width: auto !important;
                float: none !important;
            }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            color: #000000;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .card {
            border: 1px solid #dc3545;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
            background: #ffffff;
        }
        
        .table {
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            color: #000000;
        }

        .table th {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
        }

        .table td {
            border: 1px solid #dc3545;
            color: #000000;
        }
        
        .table tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .alert {
            background: #ffffff;
            border: 1px solid #dc3545;
            color: #000000;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .table thead th {
            background: #007bff;
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #dc3545;
            border: 1px solid #dc3545;
        }
        
        .summary-card {
            background: #dc3545;
            color: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-item {
            text-align: center;
            padding: 1rem;
        }
        
        .summary-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .summary-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-bar"></i> <?= htmlspecialchars($titre_etat) ?></h1>
                    <p class="mb-0">Période : <?= formatDate($datetime_debut) ?> - <?= formatDate($datetime_fin) ?></p>
                    <div class="mt-2">
                        <small class="text-white-50">
                            <?php if ($categorie): ?>
                                <span class="badge bg-light text-dark me-1">Catégorie: <?= htmlspecialchars($categorie) ?></span>
                            <?php endif; ?>
                            <?php if ($statut): ?>
                                <span class="badge bg-light text-dark me-1">Statut: <?= htmlspecialchars($statut) ?></span>
                            <?php endif; ?>
                            <?php if ($filtre_stock && $valeur_stock > 0): ?>
                                <span class="badge bg-light text-dark me-1">Stock <?= htmlspecialchars($filtre_stock) ?> <?= $valeur_stock ?></span>
                            <?php endif; ?>
                            <?php if ($regroupement && $regroupement !== 'aucun'): ?>
                                <span class="badge bg-light text-dark me-1">Regroupé par: <?= htmlspecialchars($regroupement) ?></span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button onclick="window.print()" class="btn btn-primary no-print">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <button onclick="imprimerRapport()" class="btn btn-success no-print">
                        <i class="fas fa-print"></i> Imprimer (Avancé)
                    </button>
                    <a href="etat_stock.php" class="btn btn-outline-light no-print">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
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

        <?php if (!empty($results)): ?>
            <!-- Résumé pour les états de valeur -->
            <?php if (in_array($type_etat, ['valeur_stock_resume'])): ?>
                <div class="summary-card">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-number"><?= formatNumber($results[0]['nombre_produits'] ?? 0, 0) ?></div>
                                <div class="summary-label">Produits</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-number"><?= formatNumber($results[0]['stock_total'] ?? 0, 0) ?></div>
                                <div class="summary-label">Stock Total</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-number"><?= formatNumber($results[0]['valeur_achat_totale'] ?? 0) ?> FCFA</div>
                                <div class="summary-label">Valeur Achat</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-number"><?= formatNumber($results[0]['valeur_vente_totale'] ?? 0) ?> FCFA</div>
                                <div class="summary-label">Valeur Vente</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tableau des résultats -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php if (!empty($results)): ?>
                                        <?php foreach (array_keys($results[0]) as $column): ?>
                                            <?php 
                                            // Appliquer les options de masquage
                                            $should_hide = false;
                                            if ($masquer_prix_achat == '1' && $column == 'PrixAchatHT') $should_hide = true;
                                            if ($masquer_description == '1' && $column == 'Description') $should_hide = true;
                                            if ($masquer_statut == '1' && $column == 'StatutArticle') $should_hide = true;
                                            if ($masquer_categorie == '1' && $column == 'nom_categorie') $should_hide = true;
                                            
                                            if (!$should_hide):
                                                // Changer les libellés
                                                $display_name = $column;
                                                if ($column == 'CodePersoArticle') $display_name = 'Code';
                                                if ($column == 'descriptif') $display_name = 'Description';
                                                if ($column == 'desactiver') $display_name = 'Statut Article';
                                                if ($column == 'nom_categorie') $display_name = 'Catégorie';
                                            ?>
                                                <th><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $display_name))) ?></th>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $value): ?>
                                            <?php 
                                            // Appliquer les options de masquage
                                            $should_hide = false;
                                            if ($masquer_prix_achat == '1' && $key == 'PrixAchatHT') $should_hide = true;
                                            if ($masquer_description == '1' && $key == 'Description') $should_hide = true;
                                            if ($masquer_statut == '1' && $key == 'StatutArticle') $should_hide = true;
                                            if ($masquer_categorie == '1' && $key == 'nom_categorie') $should_hide = true;
                                            
                                            if (!$should_hide):
                                            ?>
                                            <td>
                                                <?php if (in_array($key, ['PrixAchatHT', 'PrixVenteTTC', 'valeur_stock_achat', 'valeur_stock_vente', 'valeur_achat', 'valeur_vente', 'montant_vendu', 'montant_total', 'chiffre_affaires', 'total_acomptes', 'valeur_achat_totale', 'valeur_vente_totale'])): ?>
                                                    <?= formatNumber($value) ?> FCFA
                                                <?php elseif (in_array($key, ['StockActuel', 'quantite_vendue', 'quantite_achetee', 'stock_total', 'nombre_articles', 'quantite_totale', 'nombre_ventes_credit', 'nombre_articles_categorie', 'nombre_articles_fournisseur', 'nombre_produits', 'nombre_ventes', 'nombre_clients', 'quantite_totale_vendue'])): ?>
                                                    <?= formatNumber($value, 0) ?>
                                                <?php elseif (in_array($key, ['DateIns', 'DateMod', 'date_vente', 'DATE_ENTREE'])): ?>
                                                    <?= formatDate($value) ?>
                                                <?php elseif ($key == 'desactiver'): ?>
                                                    <?= $value == 'oui' ? 'Désactivé' : 'Actif' ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($value ?? '') ?>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Informations sur les résultats -->
            <div class="row mt-3 no-print">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong><?= count($results) ?></strong> résultat(s) trouvé(s)
                        <?php if (count($results) >= $limite_resultats): ?>
                            <br><small>Limite de <?= $limite_resultats ?> résultats atteinte</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        Généré le <?= date('d/m/Y à H:i') ?> par <?= $_SESSION['nom_utilisateur'] ?? 'Utilisateur' ?>
                    </small>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle"></i> Aucun résultat trouvé pour les critères sélectionnés.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction d'impression améliorée
        function imprimerRapport() {
            try {
                // Sauvegarder l'état actuel des éléments
                const noPrintElements = document.querySelectorAll('.no-print');
                const originalDisplay = [];
                
                // Sauvegarder les styles actuels
                noPrintElements.forEach((el, index) => {
                    originalDisplay[index] = el.style.display || '';
                });
                
                // Masquer temporairement les éléments non imprimables
                noPrintElements.forEach(el => {
                    el.style.display = 'none';
                });
                
                // Attendre un peu puis imprimer
                setTimeout(() => {
                    window.print();
                    
                    // Remettre les éléments après impression
                    setTimeout(() => {
                        noPrintElements.forEach((el, index) => {
                            el.style.display = originalDisplay[index];
                        });
                    }, 1000);
                }, 300);
                
            } catch (error) {
                console.error('Erreur lors de l\'impression:', error);
                // Fallback vers l'impression simple
                window.print();
            }
        }
        
        // Auto-print si demandé
        if (window.location.search.includes('autoprint=1')) {
            setTimeout(() => {
                imprimerRapport();
            }, 500);
        }
        
        // Raccourci clavier pour impression
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                imprimerRapport();
            }
        });
        
        // Impression directe au chargement si paramètre autoprint
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.search.includes('autoprint=1')) {
                setTimeout(() => {
                    imprimerRapport();
                }, 1000);
            }
        });
    </script>

    <!-- Pied de page avec informations de génération -->
    <div class="footer-info" style="position: fixed; bottom: 0; left: 0; right: 0; background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 10px; text-align: center; font-size: 12px; color: #6c757d; z-index: 1000;">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <strong>Généré par :</strong> <?= htmlspecialchars($_SESSION['nom_utilisateur'] ?? 'Utilisateur') ?> | 
                    <strong>Date :</strong> <?= date('d/m/Y') ?> | 
                    <strong>Heure :</strong> <?= date('H:i:s') ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .footer-info {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                background: #f8f9fa !important;
                border-top: 1px solid #ccc !important;
                padding: 8px !important;
                text-align: center !important;
                font-size: 10px !important;
                color: #333 !important;
                page-break-inside: avoid !important;
            }
        }
        
        /* Espace pour le pied de page en mode écran */
        body {
            padding-bottom: 60px;
        }
    </style>
</body>
</html>
