<?php
try {
    include('db/connecting.php');

    
    require_once 'fonction_traitement/fonction.php';
    check_access();


    // Récupération de la date depuis la requête ou par défaut la date actuelle
    $dateSelectionnee = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    // CORRECTION : Logique cohérente avec les modules CA
    // 1. Ventes normales du jour
    $sql_ventes_jour = "
        SELECT 
            SUM(v.MontantTotal) AS montant_ventes,
            COUNT(DISTINCT v.IDCLIENT) AS nombre_clients,
            COUNT(*) AS nombre_ventes
        FROM vente v 
        WHERE DATE(v.DateIns) = :date
    ";
    
    // 2. Acomptes crédit du jour
    $sql_acomptes_jour = "
        SELECT 
            SUM(vc.AccompteVerse) AS montant_acomptes,
            COUNT(DISTINCT vc.IDCLIENT) AS nombre_clients_acomptes
        FROM ventes_credit vc
        WHERE DATE(vc.DateMod) = :date AND vc.Statut != 'Transféré'
    ";
    
    // 3. Paiements SAV du jour
    $sql_sav_jour = "
        SELECT 
            SUM(sp.montant) AS montant_sav,
            COUNT(DISTINCT sd.id_client) AS nombre_clients_sav
        FROM sav_paiement sp
        JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE DATE(sp.date_paiement) = :date
    ";

    // 4. Coût d'achat du jour (PMP)
    $sql_cout_jour = "
        SELECT SUM(a.PrixAchatHT * fa.QuantiteVendue) AS cout_total
        FROM facture_article fa
        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
        JOIN vente v ON fa.NumeroVente = v.NumeroVente
        WHERE DATE(v.DateIns) = :date
    ";

    // 5. Versements du jour
    $sql_versements_jour = "
        SELECT SUM(MontantVersement) AS total_versements
        FROM versement 
        WHERE DATE(DateIns) = :date
    ";

    // 6. Remises du jour
    $sql_remises_jour = "
        SELECT SUM(MontantRemise) AS total_remises
        FROM vente 
        WHERE DATE(DateIns) = :date
    ";

    // Exécution des requêtes
    $stmt = $cnx->prepare($sql_ventes_jour);
    $stmt->execute(['date' => $dateSelectionnee]);
    $ventes_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_ventes = $ventes_data['montant_ventes'] ?: 0;
    $nombre_clients_ventes = $ventes_data['nombre_clients'] ?: 0;
    $nombre_ventes = $ventes_data['nombre_ventes'] ?: 0;

    $stmt = $cnx->prepare($sql_acomptes_jour);
    $stmt->execute(['date' => $dateSelectionnee]);
    $acomptes_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_acomptes = $acomptes_data['montant_acomptes'] ?: 0;
    $nombre_clients_acomptes = $acomptes_data['nombre_clients_acomptes'] ?: 0;

    $stmt = $cnx->prepare($sql_sav_jour);
    $stmt->execute(['date' => $dateSelectionnee]);
    $sav_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_sav = $sav_data['montant_sav'] ?: 0;
    $nombre_clients_sav = $sav_data['nombre_clients_sav'] ?: 0;

    $stmt = $cnx->prepare($sql_cout_jour);
    $stmt->execute(['date' => $dateSelectionnee]);
    $cout_total = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_versements_jour);
    $stmt->execute(['date' => $dateSelectionnee]);
    $total_versements = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_remises_jour);
    $stmt->execute(['date' => $dateSelectionnee]);
    $total_remises = $stmt->fetchColumn() ?: 0;

    // Calculs financiers
    $chiffre_affaires_total = $montant_ventes + $montant_acomptes + $montant_sav;
    $total_clients_jour = $nombre_clients_ventes + $nombre_clients_acomptes + $nombre_clients_sav;
    $benefice = $montant_ventes - $cout_total;
    $marge_brute_pourcentage = $montant_ventes > 0 ? ($benefice / $montant_ventes) * 100 : 0;
    $panier_moyen = $total_clients_jour > 0 ? $chiffre_affaires_total / $total_clients_jour : 0;

    // Entrées et sorties du jour
    $entrees_jour = $chiffre_affaires_total;
    $sorties_jour = $cout_total + $total_remises;
    $solde_jour = $entrees_jour - $sorties_jour;

    // Récupération des clients du jour avec détails
    $sql_clients_jour = "
        SELECT 
            c.IDCLIENT, 
            c.NomPrenomClient, 
            c.Telephone,
            COALESCE(SUM(v.MontantTotal), 0) AS montant_ventes,
            COALESCE(SUM(vc.AccompteVerse), 0) AS montant_acomptes,
            COALESCE(SUM(sp.montant), 0) AS montant_sav
        FROM client c
        LEFT JOIN vente v ON c.IDCLIENT = v.IDCLIENT AND DATE(v.DateIns) = :date
        LEFT JOIN ventes_credit vc ON c.IDCLIENT = vc.IDCLIENT AND DATE(vc.DateMod) = :date AND vc.Statut != 'Transféré'
        LEFT JOIN sav_dossier sd ON c.IDCLIENT = sd.id_client
        LEFT JOIN sav_paiement sp ON sd.id_sav = sp.id_sav AND DATE(sp.date_paiement) = :date
        WHERE (v.IDCLIENT IS NOT NULL OR vc.IDCLIENT IS NOT NULL OR sd.id_client IS NOT NULL)
        GROUP BY c.IDCLIENT, c.NomPrenomClient, c.Telephone
        ORDER BY (COALESCE(SUM(v.MontantTotal), 0) + COALESCE(SUM(vc.AccompteVerse), 0) + COALESCE(SUM(sp.montant), 0)) DESC
    ";

    $stmt = $cnx->prepare($sql_clients_jour);
    $stmt->execute(['date' => $dateSelectionnee]);
    $clients_jour = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupération des articles achetés par un client (si demandé)
    $articles = null;
    if (isset($_GET['idClient'])) {
        $idClient = $_GET['idClient'];
        $sql_articles = "
            SELECT 
                a.libelle,
                fa.QuantiteVendue,
                a.PrixVenteTTC,
                COALESCE(ns.NUMERO_SERIE, 'N/A') AS numero_serie,
                'Vente' AS type_transaction
            FROM facture_article fa
            JOIN article a ON fa.IDARTICLE = a.IDARTICLE
            JOIN vente v ON fa.NumeroVente = v.NumeroVente
            LEFT JOIN num_serie ns ON ns.IDARTICLE = a.IDARTICLE AND ns.ID_VENTE = v.IDFactureVente
            WHERE v.IDCLIENT = :idClient AND DATE(v.DateIns) = :date
            UNION ALL
            SELECT 
                'Acompte crédit' AS libelle,
                1 AS QuantiteVendue,
                vc.AccompteVerse  AS PrixVenteTTC,
                'N/A' AS numero_serie,
                'Acompte' AS type_transaction
            FROM ventes_credit vc
            WHERE vc.IDCLIENT = :idClient AND DATE(vc.DateMod) = :date AND vc.Statut != 'Transféré'
            UNION ALL
            SELECT 
                CONCAT('SAV - ', sd.description_panne) AS libelle,
                1 AS QuantiteVendue,
                sp.montant AS PrixVenteTTC,
                'N/A' AS numero_serie,
                'SAV' AS type_transaction
            FROM sav_paiement sp
            JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
            WHERE sd.id_client = :idClient AND DATE(sp.date_paiement) = :date
        ";
        
        $stmt = $cnx->prepare($sql_articles);
        $stmt->execute(['idClient' => $idClient, 'date' => $dateSelectionnee]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Données pour les graphiques
    $sql_evolution_ca = "
        SELECT 
            DATE(v.DateIns) AS date_vente,
            SUM(v.MontantTotal) AS ca_ventes
        FROM vente v
        WHERE v.DateIns >= DATE_SUB(:date, INTERVAL 30 DAY)
        GROUP BY DATE(v.DateIns)
        ORDER BY date_vente
    ";
    
    $stmt = $cnx->prepare($sql_evolution_ca);
    $stmt->execute(['date' => $dateSelectionnee]);
    $evolution_ca = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top clients du mois
    $sql_top_clients = "
        SELECT 
            c.NomPrenomClient,
            COALESCE(SUM(v.MontantTotal), 0) + COALESCE(SUM(vc.AccompteVerse), 0) + COALESCE(SUM(sp.montant), 0) AS total_achats
        FROM client c
        LEFT JOIN vente v ON c.IDCLIENT = v.IDCLIENT AND MONTH(v.DateIns) = MONTH(:date) AND YEAR(v.DateIns) = YEAR(:date)
        LEFT JOIN ventes_credit vc ON c.IDCLIENT = vc.IDCLIENT AND MONTH(vc.DateMod) = MONTH(:date) AND YEAR(vc.DateMod) = YEAR(:date) AND vc.Statut != 'Transféré'
        LEFT JOIN sav_dossier sd ON c.IDCLIENT = sd.id_client
        LEFT JOIN sav_paiement sp ON sd.id_sav = sp.id_sav AND MONTH(sp.date_paiement) = MONTH(:date) AND YEAR(sp.date_paiement) = YEAR(:date)
        GROUP BY c.IDCLIENT, c.NomPrenomClient
        HAVING total_achats > 0
        ORDER BY total_achats DESC
        LIMIT 5
    ";
    
    $stmt = $cnx->prepare($sql_top_clients);
    $stmt->execute(['date' => $dateSelectionnee]);
    $top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $th) {
    // Gestion d'erreur professionnelle
    error_log('Erreur comptabilite.php: ' . $th->getMessage());
    
    // Affichage d'une page d'erreur propre
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur - Comptabilité</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-container {
                background: rgba(255, 255, 255, 0.95);
                padding: 40px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                max-width: 500px;
            }
            .error-icon {
                font-size: 60px;
                color: #dc3545;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <i class="fas fa-exclamation-triangle error-icon"></i>
            <h2>Erreur de chargement</h2>
            <p class="text-muted">Une erreur s'est produite lors du chargement des données comptables.</p>
            <p class="text-muted">Veuillez réessayer ou contacter l'administrateur.</p>
            <a href="javascript:history.back()" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptabilité - Gestion Commerciale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 30px;
        }

        .dashboard-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 600;
        }

        .btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border: none;
            padding: 15px;
        }

        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }

        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%) !important;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .badge {
            border-radius: 20px;
            padding: 8px 12px;
            font-size: 0.8rem;
        }

        .transaction-type {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .transaction-vente { background-color: #d4edda; color: #155724; }
        .transaction-acompte { background-color: #fff3cd; color: #856404; }
        .transaction-sav { background-color: #d1ecf1; color: #0c5460; }

        @media print {
            body { background: white; }
            .main-container { box-shadow: none; margin: 0; }
            .btn, .navbar { display: none; }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-calculator"></i> Comptabilité SO-TECH
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="link-fournisseurs">
                            <i class="fas fa-truck"></i> Fournisseurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#" id="link-clients">
                            <i class="fas fa-users"></i> Clients
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php 
    if (isset($_SESSION['nom_utilisateur'])) {
        include('includes/user_indicator.php'); 
        include('includes/navigation_buttons.php');
    }
    ?>

    <div class="main-container">
        <!-- Section Clients -->
        <div id="section-clients">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h2><i class="fas fa-users"></i> Gestion des Clients</h2>
                </div>
                <div class="col-md-6 text-end">
                    <label for="date" class="form-label">Sélectionner une date :</label>
                    <input type="date" id="date" name="date" class="form-control d-inline-block w-auto" 
                           value="<?= htmlspecialchars($dateSelectionnee) ?>" onchange="changerDate()">
                </div>
            </div>

            <!-- Statistiques du jour -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card bg-primary text-white">
                        <div class="stat-value"><?= number_format($total_clients_jour) ?></div>
                        <div class="stat-label">Total Clients du Jour</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-success text-white">
                        <div class="stat-value"><?= number_format($chiffre_affaires_total, 2, ',', ' ') ?> F.CFA</div>
                        <div class="stat-label">Chiffre d'Affaires Total</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-info text-white">
                        <div class="stat-value"><?= number_format($benefice, 2, ',', ' ') ?> F.CFA</div>
                        <div class="stat-label">Bénéfice Net (Ventes - Coûts)</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning text-white">
                        <div class="stat-value"><?= number_format($marge_brute_pourcentage, 1) ?>%</div>
                        <div class="stat-label">Marge Brute</div>
                    </div>
                </div>
            </div>

            <!-- Détail des revenus -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Détail des Revenus du Jour (Comptabilité)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h4 class="text-primary"><?= number_format($montant_ventes, 2, ',', ' ') ?> F.CFA</h4>
                                        <p class="text-muted">Ventes Normales (<?= $nombre_ventes ?> transactions)</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h4 class="text-warning"><?= number_format($montant_acomptes, 2, ',', ' ') ?> F.CFA</h4>
                                        <p class="text-muted">Acomptes Crédit (<?= $nombre_clients_acomptes ?> clients)</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h4 class="text-info"><?= number_format($montant_sav, 2, ',', ' ') ?> F.CFA</h4>
                                        <p class="text-muted">Paiements SAV (<?= $nombre_clients_sav ?> clients)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des clients -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Liste des Clients du Jour (Revenus)</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <button class="btn btn-success me-2" onclick="exporterTableau('tableauClients')">
                                    <i class="fas fa-file-excel"></i> Exporter Excel
                                </button>
                                <button class="btn btn-danger" onclick="imprimerTableauClients()">
                                    <i class="fas fa-file-pdf"></i> Imprimer PDF
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover" id="tableauClients">
                                    <thead>
                                        <tr>
                                            <th>Nom du Client</th>
                                            <th>Téléphone</th>
                                            <th>Ventes Normales</th>
                                            <th>Acomptes</th>
                                            <th>SAV</th>
                                            <th>Total (F.CFA)</th>
                                            <th class="col-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients_jour as $client) { 
                                            $client['montant_ventes'] = $client['montant_ventes'] ?? 0;
                                            $client['montant_acomptes'] = $client['montant_acomptes'] ?? 0;
                                            $client['montant_sav'] = $client['montant_sav'] ?? 0;
                                            $total_client = $client['montant_ventes'] + $client['montant_acomptes'] + $client['montant_sav'];
                                        ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($client['NomPrenomClient']) ?></strong></td>
                                                <td><?= htmlspecialchars($client['Telephone']) ?></td>
                                                <td>
                                                    <span class="badge bg-success"><?= number_format($client['montant_ventes'], 2, ',', ' ') ?> F.CFA</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning"><?= number_format($client['montant_acomptes'], 2, ',', ' ') ?> F.CFA</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= number_format($client['montant_sav'], 2, ',', ' ') ?> F.CFA</span>
                                                </td>
                                                <td><strong><?= number_format($total_client, 2, ',', ' ') ?> F.CFA</strong></td>
                                                <td class="col-actions">
                                                    <a href="?date=<?= urlencode($dateSelectionnee) ?>&idClient=<?= $client['IDCLIENT'] ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> Détails
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Détails des articles achetés -->
            <?php if (isset($articles) && !empty($articles)) { ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Détails des Transactions</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Numéro de Série</th>
                                                <th>Quantité</th>
                                                <th>Montant TTC (F.CFA)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($articles as $article) { ?>
                                                <tr>
                                                    <td>
                                                        <span class="transaction-type transaction-<?= strtolower($article['type_transaction']) ?>">
                                                            <?= htmlspecialchars($article['type_transaction']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($article['libelle']) ?></td>
                                                    <td><?= htmlspecialchars($article['numero_serie']) ?></td>
                                                    <td><?= $article['QuantiteVendue'] ?></td>
                                                    <td><strong><?= number_format($article['PrixVenteTTC'], 2, ',', ' ') ?> F.CFA</strong></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Section Fournisseurs -->
        <div id="section-fournisseurs" style="display: none;">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h2><i class="fas fa-truck"></i> Gestion des Fournisseurs (Achats & Dettes)</h2>
                </div>
                <div class="col-md-6 text-end">
                    <label for="date-fournisseurs" class="form-label">Sélectionner une date :</label>
                    <input type="date" id="date-fournisseurs" name="date-fournisseurs" class="form-control d-inline-block w-auto" 
                           value="<?= htmlspecialchars($dateSelectionnee) ?>" onchange="changerDateFournisseurs()">
                </div>
            </div>

            <!-- Statistiques fournisseurs -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card bg-primary text-white">
                        <div class="stat-value" id="total-fournisseurs">0</div>
                        <div class="stat-label">Fournisseurs Actifs</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-success text-white">
                        <div class="stat-value" id="achats-jour">0 F.CFA</div>
                        <div class="stat-label">Achats du Jour</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning text-white">
                        <div class="stat-value" id="dettes-fournisseurs">0 F.CFA</div>
                        <div class="stat-label">Dettes Fournisseurs</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-info text-white">
                        <div class="stat-value" id="paiements-jour">0 F.CFA</div>
                        <div class="stat-label">Paiements du Jour</div>
                    </div>
                </div>
            </div>

            <!-- Liste des fournisseurs -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Liste des Fournisseurs (Achats & Paiements)</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <button class="btn btn-success me-2" onclick="exporterTableau('tableauFournisseurs')">
                                    <i class="fas fa-file-excel"></i> Exporter Excel
                                </button>
                                <button class="btn btn-danger me-2" onclick="window.print()">
                                    <i class="fas fa-file-pdf"></i> Imprimer PDF
                                </button>
                                <button class="btn btn-primary" onclick="chargerDonneesFournisseurs()">
                                    <i class="fas fa-sync-alt"></i> Actualiser
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover" id="tableauFournisseurs">
                                    <thead>
                                        <tr>
                                            <th>Fournisseur</th>
                                            <th>Contact</th>
                                            <th>Achats du Jour</th>
                                            <th>Total Achats</th>
                                            <th>Total Payé</th>
                                            <th>Solde</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-fournisseurs">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <i class="fas fa-spinner fa-spin"></i> Chargement des données...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Détails fournisseur -->
            <div id="details-fournisseur" class="row mt-4" style="display: none;">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Détails des Achats (Entrées en Stock)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tableau-achats-fournisseur">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Bon de Commande</th>
                                            <th>Quantité</th>
                                            <th>Prix Unitaire</th>
                                            <th>Total HT</th>
                                            <th>Payé</th>
                                            <th>Reste</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Rempli dynamiquement -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques et Analyses -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Évolution du CA (30 jours)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="caChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy"></i> Top Clients du Mois</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="clientsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports Financiers -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cash-register"></i> Trésorerie du Jour (Flux de Caisse)</h5>
                        </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Entrées :</span>
                            <span class="text-success fw-bold"><?= number_format($entrees_jour, 2, ',', ' ') ?> F.CFA</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Sorties :</span>
                            <span class="text-danger fw-bold"><?= number_format($sorties_jour, 2, ',', ' ') ?> F.CFA</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Solde :</span>
                            <span class="fw-bold <?= $solde_jour >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($solde_jour, 2, ',', ' ') ?> F.CFA
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Performance (Indicateurs Comptables)</h5>
                        </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Panier moyen :</span>
                            <span class="text-primary fw-bold"><?= number_format($panier_moyen, 2, ',', ' ') ?> F.CFA</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Versements :</span>
                            <span class="text-info fw-bold"><?= number_format($total_versements, 2, ',', ' ') ?> F.CFA</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Remises :</span>
                            <span class="text-warning fw-bold"><?= number_format($total_remises, 2, ',', ' ') ?> F.CFA</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation entre sections
        document.addEventListener('DOMContentLoaded', () => {
            const linkFournisseurs = document.getElementById('link-fournisseurs');
            const linkClients = document.getElementById('link-clients');
            const sectionFournisseurs = document.getElementById('section-fournisseurs');
            const sectionClients = document.getElementById('section-clients');

            linkFournisseurs.addEventListener('click', (e) => {
                e.preventDefault();
                sectionFournisseurs.style.display = 'block';
                sectionClients.style.display = 'none';
                linkFournisseurs.classList.add('active');
                linkClients.classList.remove('active');
            });

            linkClients.addEventListener('click', (e) => {
                e.preventDefault();
                sectionClients.style.display = 'block';
                sectionFournisseurs.style.display = 'none';
                linkClients.classList.add('active');
                linkFournisseurs.classList.remove('active');
            });
        });

        // Changement de date
        function changerDate() {
            const date = document.getElementById('date').value;
            window.location.href = `?date=${date}`;
        }

        // Export Excel
        function exporterTableau(idTable) {
            let table = document.getElementById(idTable);
            let html = table.outerHTML.replace(/ /g, '%20');
            let a = document.createElement('a');
            a.href = 'data:application/vnd.ms-excel,' + html;
            a.download = 'comptabilite_clients_' + new Date().toISOString().split('T')[0] + '.xls';
            a.click();
        }

        // Graphique Évolution CA
        <?php if (!empty($evolution_ca)) { ?>
        const ctxCA = document.getElementById('caChart').getContext('2d');
        new Chart(ctxCA, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($evolution_ca, 'date_vente')) ?>,
                datasets: [{
                    label: 'Chiffre d\'Affaires (€)',
                    data: <?= json_encode(array_column($evolution_ca, 'ca_ventes')) ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fr-FR') + ' F.CFA';
                            }
                        }
                    }
                }
            }
        });
        <?php } ?>

        // Graphique Top Clients
        <?php if (!empty($top_clients)) { ?>
        const ctxClients = document.getElementById('clientsChart').getContext('2d');
        new Chart(ctxClients, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($top_clients, 'NomPrenomClient')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($top_clients, 'total_achats')) ?>,
                    backgroundColor: [
                        '#e74c3c',
                        '#3498db',
                        '#2ecc71',
                        '#f39c12',
                        '#9b59b6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php } ?>

        // Fonctions pour les fournisseurs
        function changerDateFournisseurs() {
            const date = document.getElementById('date-fournisseurs').value;
            chargerDonneesFournisseurs(date);
        }

        function chargerDonneesFournisseurs(date = null) {
            if (!date) {
                date = document.getElementById('date-fournisseurs').value;
            }

            // Afficher le loader
            document.getElementById('tbody-fournisseurs').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Chargement des données...
                    </td>
                </tr>
            `;

            // Appel AJAX pour récupérer les données
            fetch(`get_fournisseurs_data.php?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        afficherDonneesFournisseurs(data);
                    } else {
                        document.getElementById('tbody-fournisseurs').innerHTML = `
                            <tr>
                                <td colspan="8" class="text-center text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Erreur: ${data.message}
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('tbody-fournisseurs').innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center text-danger">
                                <i class="fas fa-exclamation-triangle"></i> Erreur de connexion
                            </td>
                        </tr>
                    `;
                });
        }

        function afficherDonneesFournisseurs(data) {
            // Mettre à jour les statistiques
            document.getElementById('total-fournisseurs').textContent = data.stats.total_fournisseurs;
            document.getElementById('achats-jour').textContent = data.stats.achats_jour.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' F.CFA';
            document.getElementById('dettes-fournisseurs').textContent = data.stats.dettes_total.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' F.CFA';
            document.getElementById('paiements-jour').textContent = data.stats.paiements_jour.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' F.CFA';

            // Afficher la liste des fournisseurs
            const tbody = document.getElementById('tbody-fournisseurs');
            tbody.innerHTML = '';

            if (data.fournisseurs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            <i class="fas fa-info-circle"></i> Aucun fournisseur trouvé pour cette date
                        </td>
                    </tr>
                `;
                return;
            }

            data.fournisseurs.forEach(fournisseur => {
                const row = document.createElement('tr');
                const contact = `${fournisseur.telephone || ''} ${fournisseur.email ? '<br><small>' + fournisseur.email + '</small>' : ''}`.trim();
                
                row.innerHTML = `
                    <td><strong>${fournisseur.nom_fournisseur}</strong></td>
                    <td>${contact || '-'}</td>
                    <td>
                        ${fournisseur.achats_jour > 0 ? 
                            `<span class="badge bg-primary">${fournisseur.achats_jour.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</span>` : 
                            '<span class="text-muted">-</span>'
                        }
                    </td>
                    <td>
                        <span class="text-info fw-bold">${fournisseur.total_achats.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</span>
                    </td>
                    <td>
                        <span class="text-success fw-bold">${fournisseur.total_paye.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</span>
                    </td>
                    <td>
                        ${fournisseur.dette_totale > 0 ? 
                            `<span class="badge bg-danger">${fournisseur.dette_totale.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</span>` : 
                            '<span class="badge bg-success">Solde positif</span>'
                        }
                    </td>
                    <td>
                        <span class="badge bg-${fournisseur.statut_class}">${fournisseur.statut}</span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="voirDetailsFournisseur(${fournisseur.id_fournisseur})">
                            <i class="fas fa-eye"></i> Détails
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function voirDetailsFournisseur(idFournisseur) {
            const date = document.getElementById('date-fournisseurs').value;
            
            fetch(`get_fournisseur_details.php?id_fournisseur=${idFournisseur}&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        afficherDetailsFournisseur(data.achats);
                        document.getElementById('details-fournisseur').setAttribute('data-id-fournisseur', idFournisseur);
                        document.getElementById('details-fournisseur').style.display = 'block';
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de connexion');
                });
        }

        function afficherDetailsFournisseur(achats) {
            const tbody = document.getElementById('tableau-achats-fournisseur').querySelector('tbody');
            tbody.innerHTML = '';

            if (achats.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            <i class="fas fa-info-circle"></i> Aucun achat trouvé
                        </td>
                    </tr>
                `;
                return;
            }

            achats.forEach(achat => {
                const row = document.createElement('tr');
                const statutClass = achat.statut_paiement === 'Payé' ? 'bg-success' : 
                                   achat.statut_paiement === 'Paiement partiel' ? 'bg-warning' : 'bg-danger';
                
                row.innerHTML = `
                    <td>${new Date(achat.date_achat).toLocaleDateString('fr-FR')}</td>
                    <td><strong>${achat.libelle}</strong></td>
                    <td>${achat.quantite}</td>
                    <td>${achat.prix_unitaire.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</td>
                    <td><strong>${achat.total_ht.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</strong></td>
                    <td>
                        <span class="text-success fw-bold">${achat.montant_paye.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</span>
                    </td>
                    <td>
                        <span class="text-danger fw-bold">${achat.reste_a_payer.toLocaleString('fr-FR', {minimumFractionDigits: 2})} F.CFA</span>
                    </td>
                    <td>
                        <span class="badge ${statutClass}">${achat.statut_paiement}</span>
                        ${achat.reste_a_payer > 0 ? 
                            `<button class="btn btn-sm btn-success ms-2" onclick="reglerAchatPartiel('${achat.id_entree}','${achat.id_fournisseur}','${achat.reste_a_payer}')">Régler</button>` : ''}
                        <button class="btn btn-sm btn-info ms-2" onclick="voirHistoriquePaiements('${achat.id_entree}')">Historique</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Charger les données fournisseurs au chargement de la page
        document.addEventListener('DOMContentLoaded', () => {
            // Charger les données fournisseurs initiales
            setTimeout(() => {
                if (document.getElementById('section-fournisseurs').style.display !== 'none') {
                    chargerDonneesFournisseurs();
                }
            }, 100);
        });

        // Impression du tableau des clients du jour uniquement
        function imprimerTableauClients() {
            var printContents = document.getElementById('tableauClients').outerHTML;
            var originalContents = document.body.innerHTML;
            var w = window.open('', '', 'height=700,width=900');
            w.document.write('<html><head><title>Liste des Clients du ' + document.getElementById('date').value + '</title>');
            w.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            w.document.write('<style>.col-actions { display: none !important; }</style>');
            w.document.write('</head><body>');
            w.document.write('<h2>Liste des Clients du ' + document.getElementById('date').value + '</h2>');
            w.document.write(printContents);
            w.document.write('</body></html>');
            w.document.close();
            w.focus();
            w.print();
            w.close();
        }

        // Paiement partiel fournisseur
        function reglerAchatPartiel(id_entree, id_fournisseur, montant_restant) {
            // Validation du montant restant
            const montantMax = parseFloat(montant_restant);
            if (montantMax <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Cet achat est déjà entièrement payé !',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Paiement Fournisseur',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Montant à régler</label>
                        <input type="number" id="montant-paiement" class="form-control" 
                               placeholder="Montant en F.CFA" 
                               max="${montantMax}" 
                               min="1" 
                               step="1">
                        <small class="text-muted">Maximum: ${montantMax.toLocaleString('fr-FR')} F.CFA</small>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enregistrer le paiement',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const montant = document.getElementById('montant-paiement').value;
                    
                    // Validations
                    if (!montant || montant === '') {
                        Swal.showValidationMessage('Veuillez saisir un montant');
                        return false;
                    }
                    
                    const montantNum = parseFloat(montant);
                    if (isNaN(montantNum) || montantNum <= 0) {
                        Swal.showValidationMessage('Le montant doit être un nombre positif');
                        return false;
                    }
                    
                    if (montantNum > montantMax) {
                        Swal.showValidationMessage(`Le montant ne peut pas dépasser ${montantMax.toLocaleString('fr-FR')} F.CFA`);
                        return false;
                    }
                    
                    if (montantNum > 100000000) { // 100 millions max
                        Swal.showValidationMessage('Montant trop élevé (maximum 100 000 000 F.CFA)');
                        return false;
                    }
                    
                    return montantNum;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const montant = result.value;
                    const utilisateur = "<?= $_SESSION['nom_utilisateur'] ?? 'Inconnu' ?>";
                    
                    // Afficher le loader
                    Swal.fire({
                        title: 'Enregistrement...',
                        text: 'Paiement en cours d\'enregistrement',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('regler_achat.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id_entree, id_fournisseur, montant, utilisateur})
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Paiement enregistré !',
                                text: `Montant: ${montant.toLocaleString('fr-FR')} F.CFA`,
                                confirmButtonText: 'OK'
                            }).then(() => {
                                voirDetailsFournisseur(id_fournisseur);
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Erreur lors de l\'enregistrement',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de connexion',
                            text: 'Impossible de contacter le serveur',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        }

        // Historique des paiements
        function voirHistoriquePaiements(id_entree) {
            fetch('historique_paiements.php?id_entree=' + id_entree)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-dark"><tr><th>Date</th><th>Montant</th><th>Utilisateur</th></tr></thead><tbody>';
                        
                        if (data.paiements.length === 0) {
                            html += '<tr><td colspan="3" class="text-center text-muted">Aucun paiement enregistré</td></tr>';
                        } else {
                            data.paiements.forEach(p => {
                                const date = new Date(p.DatePaiement).toLocaleDateString('fr-FR');
                                const montant = parseFloat(p.Montant).toLocaleString('fr-FR', {minimumFractionDigits: 2});
                                html += `<tr><td>${date}</td><td class="text-success fw-bold">${montant} F.CFA</td><td>${p.Utilisateur}</td></tr>`;
                            });
                        }
                        
                        html += '</tbody></table></div>';
                        
                        Swal.fire({
                            title: 'Historique des Paiements',
                            html: html,
                            width: '600px',
                            showConfirmButton: true,
                            confirmButtonText: 'Fermer',
                            confirmButtonColor: '#6c757d'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Impossible de charger l\'historique des paiements',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur de connexion',
                        text: 'Impossible de contacter le serveur',
                        confirmButtonText: 'OK'
                    });
                });
        }
    </script>
</body>
</html>