<?php

require_once 'fonction_traitement/fonction.php';
check_access();

include('db/connecting.php');


// Initialisation des variables pour les dates et fournisseur
$date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : '';
$date_fin = isset($_POST['date_fin']) ? $_POST['date_fin'] : '';
$fournisseur = isset($_POST['fournisseur']) ? $_POST['fournisseur'] : '';

// Récupérer la liste des fournisseurs
try {
    $fournisseur_query = "SELECT * FROM fournisseur";
$fournisseur_stmt = $cnx->query($fournisseur_query);
$fournisseurs = $fournisseur_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des fournisseurs: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Récupérer les informations de l'entreprise
try {
    $entreprise_query = "SELECT * FROM entreprise WHERE id = 1";
    $entreprise_stmt = $cnx->query($entreprise_query);
    $entreprise_info = $entreprise_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des informations de l'entreprise: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Récupérer les commandes avec pagination serveur
$numero_commande = isset($_POST['numero_commande']) ? $_POST['numero_commande'] : '';

// Paramètres de pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(10, min(1000, intval($_GET['limit']))) : 50; // Limite entre 10 et 1000
$offset = ($page - 1) * $limit;

try {
    // Construction des conditions WHERE
    $whereConditions = [];
    $params = [];
    
    if ($fournisseur) {
        $whereConditions[] = "commande.IDFOURNISSEUR = ?";
        $params[] = $fournisseur;
    }
    if ($date_debut) {
        $whereConditions[] = "commande.date_commande >= ?";
        $params[] = $date_debut;
    }
    if ($date_fin) {
        $whereConditions[] = "commande.date_commande <= ?";
        $params[] = $date_fin;
    }
    if ($numero_commande) {
        $whereConditions[] = "commande.numero_commande LIKE ?";
        $params[] = '%' . $numero_commande . '%';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Requête pour compter le total
    $countQuery = "SELECT COUNT(*) as total FROM commande INNER JOIN fournisseur ON commande.IDFOURNISSEUR = fournisseur.IDFOURNISSEUR $whereClause";
    $countStmt = $cnx->prepare($countQuery);
    $countStmt->execute($params);
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRows / $limit);
    
    // Requête paginée pour les commandes
    $query = "SELECT commande.id, commande.numero_commande, commande.totalprixAchat, commande.date_commande, fournisseur.NomFournisseur, fournisseur.Telephonefournisseur, fournisseur.emailFournisseur  
FROM commande 
INNER JOIN fournisseur ON commande.IDFOURNISSEUR = fournisseur.IDFOURNISSEUR 
$whereClause
ORDER BY commande.date_commande DESC 
LIMIT $limit OFFSET $offset";

$stmt = $cnx->prepare($query);
$stmt->execute($params);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des commandes: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Suppression d'une commande si demandé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_commande_id'])) {
    try {
    $delete_id = intval($_POST['delete_commande_id']);
    
    // Récupérer les données complètes avant suppression
    $stmt = $cnx->prepare("
        SELECT c.*, f.NomFournisseur, f.Telephonefournisseur, f.emailFournisseur 
        FROM commande c 
        LEFT JOIN fournisseur f ON c.IDFOURNISSEUR = f.IDFOURNISSEUR 
        WHERE c.id = ?
    ");
    $stmt->execute([$delete_id]);
    $commandeAvant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commandeAvant) {
        throw new Exception("Commande introuvable");
    }
    
    // Récupérer les articles de la commande avant suppression
    $stmt_articles = $cnx->prepare("
        SELECT cl.*, a.libelle 
        FROM commande_ligne cl 
        LEFT JOIN article a ON cl.IDARTICLE = a.IDARTICLE 
        WHERE cl.id = ?
    ");
    $stmt_articles->execute([$delete_id]);
    $articles_commande = $stmt_articles->fetchAll(PDO::FETCH_ASSOC);
    
    // Supprimer d'abord les lignes de commande associées
    $stmt = $cnx->prepare("DELETE FROM commande_ligne WHERE id = ?");
    $stmt->execute([$delete_id]);
    // Puis la commande elle-même
    $stmt = $cnx->prepare("DELETE FROM commande WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    // Préparer les données détaillées pour la journalisation
    $donnees_commande_supprime = [
        'commande_supprime' => [
            'id' => $delete_id,
            'numero_commande' => $commandeAvant['numero_commande'],
            'date_commande' => $commandeAvant['date_commande'],
            'total_prix_achat' => $commandeAvant['totalprixAchat'],
            'nombre_articles' => count($articles_commande)
        ],
        'fournisseur' => [
            'id' => $commandeAvant['IDFOURNISSEUR'],
            'nom' => $commandeAvant['NomFournisseur'],
            'telephone' => $commandeAvant['Telephonefournisseur'],
            'email' => $commandeAvant['emailFournisseur']
        ],
        'articles_supprimes' => $articles_commande,
        'operateur' => [
            'id' => $_SESSION['id_utilisateur'] ?? 0,
            'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
            'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
        ]
    ];
    
    // Journalisation suppression commande (système unifié)
    require_once 'fonction_traitement/fonction.php';
    $startTime = log_action_start();
    $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
    $description_detaille = 'Suppression commande: N°' . $commandeAvant['numero_commande'] . ' - Fournisseur: ' . $commandeAvant['NomFournisseur'] . ' (Opérateur: ' . $operateur_nom . ') - Total: ' . number_format($commandeAvant['totalprixAchat'], 0, ',', ' ') . ' FCFA';
    
    logSystemAction($cnx, 'SUPPRESSION_COMMANDE', 'COMMANDES', 'liste_commande.php', 
        $description_detaille, 
        $commandeAvant, $donnees_commande_supprime, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
    
    // Redirection pour éviter le repost avec fallback JavaScript
    if (!headers_sent()) {
        header('Location: liste_commande.php?success=' . urlencode('Commande supprimée avec succès'));
        exit();
    } else {
        echo '<script>window.location.href = "liste_commande.php?success=' . urlencode('Commande supprimée avec succès') . '";</script>';
        echo '<meta http-equiv="refresh" content="0;url=liste_commande.php?success=' . urlencode('Commande supprimée avec succès') . '">';
        exit();
    }
    } catch (PDOException $e) {
        if (!headers_sent()) {
            header('Location: liste_commande.php?error=' . urlencode('Erreur lors de la suppression: ' . $e->getMessage()));
            exit();
        } else {
            echo '<script>window.location.href = "liste_commande.php?error=' . urlencode('Erreur lors de la suppression: ' . $e->getMessage()) . '";</script>';
            echo '<meta http-equiv="refresh" content="0;url=liste_commande.php?error=' . urlencode('Erreur lors de la suppression: ' . $e->getMessage()) . '">';
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    <title>Liste des Commandes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .container {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 30px;
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        header {
            background: #ffffff;
            color: #333333;
            padding: 20px;
            text-align: center;
            width: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease-out;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e9ecef;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: #333333;
        }

        .table-responsive {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-top: 20px;
            animation: fadeIn 0.8s ease-out;
            border: 1px solid #e9ecef;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8f9fa;
            color: #333333;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 15px 12px;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #dee2e6;
        }

        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .table tbody tr:nth-child(even) {
            background: #fafbfc;
        }

        .table tbody tr:nth-child(even):hover {
            background: #f8f9fa;
        }

        .table td {
            padding: 15px 12px;
            vertical-align: middle;
            border: none;
            font-size: 0.95rem;
        }

        .btn {
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 20px;
            margin: 2px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }

        .btn-success {
            background: #28a745;
            border-color: #28a745;
        }

        .btn-warning {
            background: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }

        .btn-info {
            background: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
        }

        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.8rem;
            border-radius: 20px;
            margin: 2px;
            min-width: 80px;
        }

        .pagination {
            margin-top: 30px;
        }

        .page-link {
            border-radius: 10px;
            margin: 0 3px;
            border: none;
            padding: 12px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .page-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .page-item.active .page-link {
            background: #007bff;
            border-color: #007bff;
        }

        .page-link {
            color: #007bff;
            border-color: #dee2e6;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
            transform: translateY(-2px);
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e6ed;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            margin: 10px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: slideInRight 0.5s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .fas {
            margin-right: 8px;
        }

        /* Responsive design amélioré */
        @media (max-width: 1400px) {
            .container {
                max-width: 95%;
                margin: 15px auto;
            }
        }
        
        @media (max-width: 1200px) {
            .container {
                max-width: 98%;
                margin: 10px auto;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
            }
            
            .d-flex.align-items-center.gap-3 {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }
        }
        
        @media (max-width: 992px) {
            .container {
                margin: 5px;
                padding: 15px;
            }
            
            header h1 {
                font-size: 2.2rem;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .btn-sm {
                padding: 8px 12px;
                font-size: 0.8rem;
                min-width: 80px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }
            
            .container {
                margin: 5px;
                padding: 15px;
                border-radius: 15px;
            }
            
            header h1 {
                font-size: 1.8rem;
                margin-bottom: 15px;
            }
            
            /* Contrôles de pagination mobile */
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
                align-items: stretch !important;
            }
            
            .d-flex.align-items-center.gap-3 {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }
            
            .position-relative {
                width: 100%;
            }
            
            /* Sélecteur de lignes par page mobile */
            .d-flex.align-items-center {
                justify-content: space-between;
                width: 100%;
            }
            
            .form-select {
                font-size: 16px; /* Évite le zoom sur iOS */
                min-width: 100px;
            }
            
            /* Informations de pagination mobile */
            .fw-bold {
                text-align: center;
                font-size: 0.9rem;
            }
            
            /* Tableau responsive mobile */
            .table-responsive {
                font-size: 0.8rem;
                border-radius: 10px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table {
                min-width: 800px; /* Force la largeur minimale pour le scroll horizontal */
            }
            
            .table thead th {
                padding: 10px 8px;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            
            .table td {
                padding: 10px 8px;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            
            /* Boutons d'action mobile */
            .btn-sm {
                padding: 6px 10px;
                font-size: 0.7rem;
                min-width: 70px;
                margin: 1px 0;
            }
            
            /* Pagination mobile */
            .pagination {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .page-link {
                padding: 8px 12px;
                font-size: 0.9rem;
                margin: 2px;
            }
            
            .pagination .d-flex {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .text-muted {
                font-size: 0.8rem;
            }
        }
        
        /* Très petits écrans (moins de 480px) */
        @media (max-width: 480px) {
            .container {
                margin: 2px;
                padding: 10px;
            }
            
            header h1 {
                font-size: 1.5rem;
            }
            
            .table-responsive {
                font-size: 0.75rem;
            }
            
            .table thead th,
            .table td {
                padding: 8px 6px;
                font-size: 0.75rem;
            }
            
            .btn-sm {
                padding: 5px 8px;
                font-size: 0.65rem;
                min-width: 60px;
            }
            
            .page-link {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
        }
        
        /* Orientation paysage sur mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .container {
                margin: 5px;
                padding: 10px;
            }
            
            header h1 {
                font-size: 1.6rem;
                margin-bottom: 10px;
            }
            
            .table-responsive {
                max-height: 60vh;
                overflow-y: auto;
            }
        }
        
        /* Améliorations tactiles pour mobile */
        @media (hover: none) and (pointer: coarse) {
            .btn,
            .btn-sm,
            .page-link,
            .form-control,
            .form-select {
                min-height: 44px; /* Taille minimale recommandée pour le tactile */
                touch-action: manipulation;
            }
            
            .table tbody tr {
                min-height: 44px;
            }
        }
        
        /* Amélioration du scroll tactile */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }
        
        /* Prévention du zoom sur les inputs */
        input[type="text"],
        input[type="number"],
        input[type="email"],
        select,
        textarea {
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            input[type="text"],
            input[type="number"],
            input[type="email"],
            select,
            textarea {
                font-size: 16px !important;
            }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <header>
        <h1><i class="fas fa-box"></i> Liste des Commandes</h1>
    </header>

    <div class="container mt-4">
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
        <!-- Formulaire de filtres -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filtres de Recherche</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label for="date_debut" class="form-label">Date de début</label>
                        <input type="date" name="date_debut" id="date_debut" class="form-control" value="<?php echo htmlspecialchars($date_debut); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_fin" class="form-label">Date de fin</label>
                        <input type="date" name="date_fin" id="date_fin" class="form-control" value="<?php echo htmlspecialchars($date_fin); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fournisseur" class="form-label">Fournisseur</label>
                        <select name="fournisseur" id="fournisseur" class="form-select">
                            <option value="">Tous les fournisseurs</option>
                            <?php foreach ($fournisseurs as $f) : ?>
                                <option value="<?php echo htmlspecialchars($f['IDFOURNISSEUR']); ?>" <?php echo ($fournisseur == $f['IDFOURNISSEUR']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($f['NomFournisseur']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="numero_commande" class="form-label">Numéro de commande</label>
                        <input type="text" name="numero_commande" id="numero_commande" class="form-control" value="<?php echo htmlspecialchars($numero_commande); ?>" placeholder="Numéro de commande">
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Rechercher
                            </button>
                            <a href="liste_commande.php" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Contrôles de pagination et export -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                    <!-- Sélecteur de lignes par page -->
                    <div class="d-flex align-items-center">
                        <label class="me-2 fw-bold">Lignes/page:</label>
                        <select id="limitSelect" class="form-select form-select-sm" style="width: auto; min-width: 80px;" onchange="changeLimit()">
                            <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                            <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                            <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                            <option value="1000" <?= $limit == 1000 ? 'selected' : '' ?>>1000</option>
                        </select>
                    </div>
                    
                    <!-- Informations de pagination -->
                    <div class="fw-bold text-muted">
                        <span id="paginationInfo">
                            Page <?= $page ?> sur <?= $totalPages ?> 
                            (<?= number_format($totalRows) ?> lignes total)
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="d-flex justify-content-end gap-2">
                    <?php if (can_user('liste_commande', 'exporter')) : ?>
                        <a href="export_commande.php?type=excel" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="export_commande.php?type=word" class="btn btn-primary btn-sm">
                            <i class="fas fa-file-word"></i> Word
                        </a>
                        <a href="export_commande.php?type=txt" class="btn btn-secondary btn-sm">
                            <i class="fas fa-file-alt"></i> TXT
                        </a>
                    <?php else : ?>
                        <button class="btn btn-success btn-sm" disabled title="Accès refusé">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-primary btn-sm" disabled title="Accès refusé">
                            <i class="fas fa-file-word"></i> Word
                        </button>
                        <button class="btn btn-secondary btn-sm" disabled title="Accès refusé">
                            <i class="fas fa-file-alt"></i> TXT
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Tableau des commandes -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Liste des Commandes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="table-commandes">
                        <thead class="table-dark">
                            <tr>
                                <th>Date Commande</th>
                                <th>Numéro de Commande</th>
                                <th>Fournisseur</th>
                                <th>Total Prix</th>
                                <th>Action</th>
                            </tr>
                        </thead>
            <tbody id="commandes">
                <?php foreach ($commandes as $commande) : ?>
                    <tr class="command-row">
                        <td data-target="#details-<?php echo $commande['id']; ?>" aria-expanded="false" aria-controls="details-<?php echo $commande['id']; ?>"><?php echo htmlspecialchars($commande['date_commande']); ?></td>
                        <td data-target="#details-<?php echo $commande['id']; ?>" aria-expanded="false" aria-controls="details-<?php echo $commande['id']; ?>"><?php echo htmlspecialchars($commande['numero_commande']); ?></td>
                        <td data-target="#details-<?php echo $commande['id']; ?>" aria-expanded="false" aria-controls="details-<?php echo $commande['id']; ?>"><?php echo htmlspecialchars($commande['NomFournisseur']); ?></td>
                        <td data-target="#details-<?php echo $commande['id']; ?>" aria-expanded="false" aria-controls="details-<?php echo $commande['id']; ?>"><?php echo number_format($commande['totalprixAchat'], 0, ',', ' '); ?> F.CFA</td>
                        <td>
                            <?php if (can_user('liste_commande', 'imprimer')) : ?>
                                <a href="impression_commande.php?id=<?php echo $commande['id']; ?>&action=download" class="btn btn-success">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>
                                <a href="impression_commande.php?id=<?php echo $commande['id']; ?>&action=print" class="btn btn-primary">
                                    <i class="fas fa-print"></i> Imprimer
                                </a>
                            <?php else : ?>
                                <button class="btn btn-success" disabled title="Accès refusé">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn btn-primary" disabled title="Accès refusé">
                                    <i class="fas fa-print"></i> Imprimer
                                </button>
                            <?php endif; ?>
                            <?php if (can_user('liste_commande', 'supprimer')) : ?>
                                <button type="button" class="btn btn-danger btn-sm btn-delete-commande" 
                                        data-id="<?php echo $commande['id']; ?>" 
                                        data-numero="<?php echo htmlspecialchars($commande['numero_commande']); ?>" 
                                        data-fournisseur="<?php echo htmlspecialchars($commande['NomFournisseur']); ?>"
                                        data-montant="<?php echo number_format($commande['totalprixAchat'], 0, ',', ' '); ?>">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            <?php else : ?>
                                <button class="btn btn-danger btn-sm" disabled title="Accès refusé"><i class="fas fa-trash"></i> Supprimer</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="detail-row">
                        <td colspan="5" style="padding:0;">
                            <div id="details-<?php echo $commande['id']; ?>" class="collapse">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Libellé</th>
                                            <th>ID Article</th>
                                            <th>Prix Achat</th>
                                            <th>Quantité</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                        // Récupérer les détails de la commande
                                        $commande_id = $commande['id'];
                                            $query = "SELECT article.libelle, article.Descriptif, commande_ligne.prixAchat, commande_ligne.quantite
                                          FROM commande_ligne
                                          INNER JOIN article ON commande_ligne.IDARTICLE = article.IDARTICLE
                                              WHERE commande_ligne.id = :commande_id";
                                        $stmt = $cnx->prepare($query);
                                        $stmt->bindParam(':commande_id', $commande_id);
                                        $stmt->execute();
                                        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($lignes as $ligne) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($ligne['libelle'], ENT_QUOTES, 'UTF-8') . "</td>";
                                            echo "<td>" . htmlspecialchars($ligne['Descriptif'], ENT_QUOTES, 'UTF-8') . "</td>";
                                            echo "<td>" . number_format($ligne['prixAchat'], 0, ',', ' ') . " F.CFA</td>";
                                            echo "<td>" . htmlspecialchars($ligne['quantite'], ENT_QUOTES, 'UTF-8') . "</td>";
                                            echo "</tr>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<tr><td colspan='4'>Erreur lors de la récupération des détails: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination robuste -->
        <nav class="mt-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="text-muted">
                        Affichage de <?= number_format($offset + 1) ?> à <?= number_format(min($offset + $limit, $totalRows)) ?> 
                        sur <?= number_format($totalRows) ?> résultats
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <ul class="pagination pagination-lg mb-0">
                    <!-- Bouton Première page -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&limit=<?= $limit ?><?= !empty($fournisseur) ? '&fournisseur=' . urlencode($fournisseur) : '' ?><?= !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '' ?><?= !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '' ?><?= !empty($numero_commande) ? '&numero_commande=' . urlencode($numero_commande) : '' ?>" title="Première page">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-double-left"></i></span>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Bouton Précédent -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?><?= !empty($fournisseur) ? '&fournisseur=' . urlencode($fournisseur) : '' ?><?= !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '' ?><?= !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '' ?><?= !empty($numero_commande) ? '&numero_commande=' . urlencode($numero_commande) : '' ?>" title="Page précédente">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-left"></i></span>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Numéros de pages -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&limit=<?= $limit ?><?= !empty($fournisseur) ? '&fournisseur=' . urlencode($fournisseur) : '' ?><?= !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '' ?><?= !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '' ?><?= !empty($numero_commande) ? '&numero_commande=' . urlencode($numero_commande) : '' ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?><?= !empty($fournisseur) ? '&fournisseur=' . urlencode($fournisseur) : '' ?><?= !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '' ?><?= !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '' ?><?= !empty($numero_commande) ? '&numero_commande=' . urlencode($numero_commande) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $totalPages ?>&limit=<?= $limit ?><?= !empty($fournisseur) ? '&fournisseur=' . urlencode($fournisseur) : '' ?><?= !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '' ?><?= !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '' ?><?= !empty($numero_commande) ? '&numero_commande=' . urlencode($numero_commande) : '' ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Bouton Suivant -->
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?><?= !empty($fournisseur) ? '&fournisseur=' . urlencode($fournisseur) : '' ?><?= !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '' ?><?= !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '' ?><?= !empty($numero_commande) ? '&numero_commande=' . urlencode($numero_commande) : '' ?>" title="Page suivante">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-right"></i></span>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Bouton Dernière page -->
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $totalPages ?>&limit=<?= $limit ?><?= !empty($fournisseur) ? '&fournisseur=' . urlencode($fournisseur) : '' ?><?= !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '' ?><?= !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '' ?><?= !empty($numero_commande) ? '&numero_commande=' . urlencode($numero_commande) : '' ?>" title="Dernière page">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-double-right"></i></span>
                        </li>
                    <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

        <script>
        // --- Fonction pour changer le nombre de lignes par page ---
        function changeLimit() {
            const limit = document.getElementById('limitSelect').value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('limit', limit);
            currentUrl.searchParams.set('page', '1'); // Retour à la première page
            window.location.href = currentUrl.toString();
        }

        // --- Gestion des clics pour développer/réduire les détails ---
        document.addEventListener("DOMContentLoaded", function() {
            // Gestion des clics pour développer/réduire les détails
            document.querySelectorAll('tr.command-row td:not(:last-child)').forEach(function(td) {
                td.style.cursor = 'pointer';
                td.addEventListener('click', function() {
                    var tr = td.parentElement;
                    var detailRow = tr.nextElementSibling;
                    if (detailRow && detailRow.classList.contains('detail-row')) {
                        var detailDiv = detailRow.querySelector('.collapse');
                        if (detailDiv) {
                            if (detailDiv.classList.contains('show')) {
                                detailDiv.classList.remove('show');
                                detailRow.style.display = 'none';
                            } else {
                                detailDiv.classList.add('show');
                                detailRow.style.display = '';
                            }
                        }
                    }
                });
            });

            // Gestion des boutons de suppression avec SweetAlert2
            document.querySelectorAll('.btn-delete-commande').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const numero = this.getAttribute('data-numero');
                    const fournisseur = this.getAttribute('data-fournisseur');
                    const montant = this.getAttribute('data-montant');
                    
                    Swal.fire({
                        title: 'Confirmer la suppression',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                                <p class="mt-3"><strong>Êtes-vous sûr de vouloir supprimer cette commande ?</strong></p>
                                <div class="alert alert-danger">
                                    <strong>Numéro :</strong> ${numero}<br>
                                    <strong>Fournisseur :</strong> ${fournisseur}<br>
                                    <strong>Montant :</strong> ${montant} FCFA<br>
                                    <strong>Attention :</strong> Cette action est irréversible !
                                </div>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Oui, supprimer !',
                        cancelButtonText: 'Annuler',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Afficher le loading
                            Swal.fire({
                                title: 'Suppression en cours...',
                                text: 'Veuillez patienter...',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            // Créer et soumettre le formulaire de suppression
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '';
                            
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'delete_commande_id';
                            input.value = id;
                            
                            form.appendChild(input);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });

            // Message d'information pour les grandes listes
            const totalRows = <?= $totalRows ?>;
            if (totalRows > 10000) {
                Swal.fire({
                    icon: 'info',
                    title: 'Liste volumineuse',
                    text: `${totalRows.toLocaleString('fr-FR')} commandes trouvées. Utilisez les filtres pour optimiser les performances.`,
                    timer: 5000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        });

        // --- Fonction d'impression ---
        function imprimerFacture(commandeId) {
            const row = document.querySelector(`.command-row[data-target="#details-${commandeId}"]`);
            const details = document.getElementById(`details-${commandeId}`);

            const printContent = document.createElement('div');
            printContent.appendChild(row.cloneNode(true));
            printContent.appendChild(details.cloneNode(true));

            const printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>Impression</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }

        // --- Gestion des messages de succès/erreur ---
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Succès !',
                text: '<?= addslashes($_GET['success']) ?>',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Erreur !',
                text: '<?= addslashes($_GET['error']) ?>',
                timer: 5000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        <?php endif; ?>
        </script>