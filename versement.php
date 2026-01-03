<?php
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();
    include('fonction_traitement/fonction.php');
    $utilisateurs = selection_element('utilisateur');
    $paiments = selection_element('mode_reglement');
    $versements = selection_element('versement');
    
    // Calcul du montant recommandé pour le versement du jour
    $date_aujourdhui = date('Y-m-d');
    
    // 1. Ventes normales (comptant)
    $sql_ventes_comptant = "
        SELECT SUM(MontantTotal) AS total_ventes
        FROM vente 
        WHERE DATE(DateIns) = :date
    ";
    $stmt = $cnx->prepare($sql_ventes_comptant);
    $stmt->execute(['date' => $date_aujourdhui]);
    $total_ventes_comptant = $stmt->fetchColumn() ?: 0;
    
    // 2. Acomptes des ventes à crédit
    $sql_acomptes_credit = "
        SELECT SUM(vcp.AccompteVerse) AS total_acomptes
        FROM ventes_credit_paiement vcp
        JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
        WHERE DATE(vcp.DateIns) = :date 
        AND vc.Statut != 'Transféré'
    ";
    $stmt = $cnx->prepare($sql_acomptes_credit);
    $stmt->execute(['date' => $date_aujourdhui]);
    $total_acomptes_credit = $stmt->fetchColumn() ?: 0;
    
    // 3. Paiements SAV
    $sql_sav_paiements = "
        SELECT SUM(sp.montant) AS total_sav
        FROM sav_paiement sp
        JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE DATE(sp.date_paiement) = :date
    ";
    $stmt = $cnx->prepare($sql_sav_paiements);
    $stmt->execute(['date' => $date_aujourdhui]);
    $total_sav = $stmt->fetchColumn() ?: 0;
    
    // Total recommandé pour le versement
    $montant_versement_recommande = $total_ventes_comptant + $total_acomptes_credit + $total_sav;
    
    // Le contrôle d'accès est déjà géré par check_access() au début du fichier
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . $tableName;
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
    <title>Enregistrement Versement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        /* Header moderne - Couleurs blanc-rouge */
        .main-header {
            background: linear-gradient(90deg, #ff0000 0%, #cc0000 100%);
            color: #fff;
            padding: 20px 0;
            text-align: center;
            border-radius: 0 0 25px 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .main-header h2 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        /* Container principal */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Section de création */
        .creation-section {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .creation-header {
            background: linear-gradient(90deg, #ff0000 0%, #cc0000 100%);
            color: #fff;
            padding: 20px 30px;
            font-size: 1.3em;
            font-weight: 600;
        }
        
        .creation-body {
            padding: 30px;
        }
        
        /* Instructions modernes */
        .instructions-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: none;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .instructions-header {
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            color: #fff;
            padding: 15px 25px;
            border-radius: 15px 15px 0 0;
            font-weight: 600;
        }
        
        .instructions-body {
            padding: 25px;
        }
        
        .instruction-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px;
            background: rgba(255,255,255,0.7);
            border-radius: 10px;
            transition: transform 0.2s;
        }
        
        .instruction-item:hover {
            transform: translateX(5px);
        }
        
        .instruction-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2em;
        }
        
        .instruction-icon.success { background: #4caf50; color: #fff; }
        .instruction-icon.warning { background: #ff9800; color: #fff; }
        .instruction-icon.info { background: #2196f3; color: #fff; }
        
        /* Montant recommandé */
        .recommended-amount {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: #fff;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(76,175,80,0.3);
        }
        
        .recommended-amount h4 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
        }
        
        .recommended-amount .amount {
            font-size: 2em;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        /* Formulaire moderne */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group {
            position: relative;
        }
        
        .btn-recommended {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            border: none;
            color: #fff;
            border-radius: 0 12px 12px 0;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-recommended:hover {
            background: linear-gradient(135deg, #45a049 0%, #4caf50 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76,175,80,0.4);
        }
        
        /* Tableau optimisé pour millions de lignes */
        .table-container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            background: linear-gradient(90deg, #ff0000 0%, #cc0000 100%);
            color: #fff;
            padding: 20px 30px;
            font-size: 1.3em;
            font-weight: 600;
        }
        
        .table-controls {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-input {
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            padding: 10px 20px;
            width: 300px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .filters-container {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
            background: #fff;
            min-width: 120px;
        }
        
        .filter-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Tableau scrollable optimisé pour millions de lignes */
        .table-wrapper {
            max-height: 80vh;
            overflow-y: auto;
            overflow-x: auto;
            position: relative;
            /* Optimisation pour grandes données */
            contain: layout style paint;
            will-change: scroll-position;
        }
        
        .table-wrapper::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #5a6fd8;
        }
        
        .versement-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .versement-table thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(90deg, #ff0000 0%, #cc0000 100%);
            color: #fff;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
            border: none;
        }
        
        .versement-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .versement-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f0ff 100%);
            transform: scale(1.01);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .versement-table tbody td {
            padding: 12px;
            border: none;
            font-size: 0.9em;
            vertical-align: middle;
        }
        
        /* Actions */
        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; color: #fff; }
        
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-secondary:hover { background: #5a6268; color: #fff; }
        
        /* Pagination moderne */
        .pagination-container {
            padding: 20px 30px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination {
            margin: 0;
            display: flex;
            gap: 5px;
        }
        
        .page-item {
            list-style: none;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #667eea;
            text-decoration: none;
            transition: all 0.2s ease;
            background: #fff;
        }
        
        .page-link:hover {
            background: #667eea;
            color: #fff;
            border-color: #667eea;
        }
        
        .page-item.active .page-link {
            background: #667eea;
            color: #fff;
            border-color: #667eea;
        }
        
        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-export {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-success { background: #28a745; color: #fff; }
        .btn-success:hover { background: #218838; color: #fff; }
        
        .btn-primary { background: #007bff; color: #fff; }
        .btn-primary:hover { background: #0056b3; color: #fff; }
        
        .btn-info { background: #17a2b8; color: #fff; }
        .btn-info:hover { background: #138496; color: #fff; }
        
        /* Modal supprimé - styles non nécessaires */
        
        /* Protection bouton supprimer */
        .btn-delete-protected {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .btn-delete-protected:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }
        
        .btn-delete-protected:active {
            transform: scale(0.95);
        }
        
        /* Modal de confirmation de suppression */
        .modal.show {
            display: block !important;
        }
        
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }
        
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .modal-header.bg-danger {
            background: linear-gradient(90deg, #dc3545 0%, #c82333 100%) !important;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-backdrop {
            background-color: rgba(0,0,0,0.5);
        }
        
        .alert-warning {
            border-left: 4px solid #ffc107;
            background-color: #fff3cd;
        }
        
        .card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-container { padding: 0 10px; }
            .table-controls { flex-direction: column; align-items: stretch; }
            .search-input { width: 100%; }
            .filters-container { justify-content: center; }
            .pagination-container { flex-direction: column; }
            .export-buttons { justify-content: center; }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Loading state */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #667eea;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body id="versement">
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    
    <!-- Header principal -->
    <div class="main-header">
        <div class="main-container">
            <h2><i class="fas fa-money-bill-wave"></i> Gestion des Versements</h2>
        </div>
    </div>
    
    <div class="main-container">
        <!-- Messages d'alerte -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success fade-in-up" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger fade-in-up" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>
        
        <!-- Instructions modernes -->
        <div class="instructions-card fade-in-up">
            <div class="instructions-header">
                <i class="fas fa-info-circle me-2"></i> Instructions de Versement
            </div>
            <div class="instructions-body">
                <p class="mb-4"><strong>Le versement doit inclure TOUS les encaissements de la journée :</strong></p>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="instruction-item">
                            <div class="instruction-icon success">
                                <i class="fas fa-cash-register"></i>
                            </div>
                            <div>
                            <strong>Ventes au comptant</strong>
                                <br><small class="text-muted">Montant total des factures payées comptant</small>
                        </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="instruction-item">
                            <div class="instruction-icon warning">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div>
                            <strong>Acomptes crédit</strong>
                                <br><small class="text-muted">Seulement les acomptes versés par les clients</small>
                        </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="instruction-item">
                            <div class="instruction-icon info">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div>
                            <strong>Paiements SAV</strong>
                                <br><small class="text-muted">Montants reçus pour les réparations</small>
                        </div>
                    </div>
                </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <p class="mb-0"><strong>💡 Conseil :</strong> Consultez la page "Vente du Jour" pour voir le détail des montants à verser.</p>
                    </div>
                    <div class="col-md-4">
                        <div class="recommended-amount">
                            <h4>Montant recommandé</h4>
                            <div class="amount"><?= number_format($montant_versement_recommande, 0, ',', ' ') ?> FCFA</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section de création -->
        <div class="creation-section fade-in-up">
            <div class="creation-header">
                <i class="fas fa-plus-circle me-2"></i> Nouveau Versement
        </div>
            <div class="creation-body">
                        <form id="versementForm" method="POST" action="fonction_traitement/request.php">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Sélectionner votre nom</label>
                                <select name="utilisateur" id="utilisateur" class="form-control" required>
                                        <option value="">----------</option>
                                    <?php foreach ($utilisateurs as $utilisateur): ?>
                                        <option value="<?php echo htmlspecialchars($utilisateur['IDUTILISATEUR']); ?>">
                                            <?php echo htmlspecialchars($utilisateur['NomPrenom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Montant du Versement</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="montant" name="montant" placeholder="Entrez le montant" required>
                                    <button type="button" class="btn-recommended" id="btnMontantRecommande" title="Utiliser le montant recommandé">
                                        <i class="fas fa-calculator"></i> Recommandé
                                    </button>
                                </div>
                                <small class="text-muted">Montant recommandé : <?= number_format($montant_versement_recommande, 0, ',', ' ') ?> FCFA</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Date du Versement</label>
                                <input type="date" class="form-control" id="dat" name="dat" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Mode de versement</label>
                                <select id="mode" name="mode" class="form-control" required>
                                    <option value="" disabled selected>------</option>
                                    <?php foreach ($paiments as $paiment): ?>
                                        <option value="<?php echo htmlspecialchars($paiment['IDMODE_REGLEMENT']); ?>">
                                            <?php echo htmlspecialchars($paiment['ModeReglement']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <?php echo bouton_action('Enregistrer le Versement', 'versement', 'ajouter', 'btn btn-primary btn-lg', 'type="submit" name="enregistrer_versement"'); ?>
                    </div>
                        </form>
                    </div>
                </div>
        <!-- Tableau optimisé pour millions de lignes -->
        <div class="table-container fade-in-up">
            <div class="table-header">
                <i class="fas fa-table me-2"></i> Historique des Versements
            </div>
            
            <!-- Contrôles du tableau -->
            <div class="table-controls">
                <div class="search-container">
                    <i class="fas fa-search text-muted"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Rechercher un utilisateur, montant, date ou mode...">
        </div>
                
                <div class="filters-container">
                    <div class="filter-group">
                        <label for="dateFilter" class="form-label mb-0">
                            <i class="fas fa-calendar-alt me-1"></i> Période:
                        </label>
                        <select id="dateFilter" class="filter-select">
                            <option value="">Toutes les dates</option>
                            <option value="today">Aujourd'hui</option>
                            <option value="week">Cette semaine</option>
                            <option value="month">Ce mois</option>
                            <option value="year">Cette année</option>
                        </select>
        </div>
                    
                    <div class="filter-group">
                        <label for="dateRangeStart" class="form-label mb-0">
                            <i class="fas fa-calendar-day me-1"></i> Du:
                        </label>
                        <input type="date" id="dateRangeStart" class="filter-select">
                    </div>
                    
                    <div class="filter-group">
                        <label for="dateRangeEnd" class="form-label mb-0">
                            <i class="fas fa-calendar-day me-1"></i> Au:
                        </label>
                        <input type="date" id="dateRangeEnd" class="filter-select">
                    </div>
                    
                    <div class="filter-group">
                        <label for="rowsPerPageSelect" class="form-label mb-0">
                            <i class="fas fa-list me-1"></i> Lignes:
                        </label>
                        <select id="rowsPerPageSelect" class="filter-select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                            <option value="5000">5000</option>
                            <option value="10000">10000</option>
                            <option value="50000">50000</option>
                            <option value="100000">100000</option>
                            <option value="liste_complete">Tous</option>
                            </select>
                        </div>
                </div>
            </div>
            
            <!-- Tableau scrollable optimisé -->
            <div class="table-wrapper">
                <table class="versement-table">
                                <thead>
                                    <tr>
                            <th><i class="fas fa-user me-1"></i> Utilisateur</th>
                            <th><i class="fas fa-money-bill-wave me-1"></i> Montant</th>
                            <th><i class="fas fa-calendar me-1"></i> Date</th>
                            <th><i class="fas fa-credit-card me-1"></i> Mode</th>
                            <th><i class="fas fa-cogs me-1"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="table-body">
                                    <?php if (!empty($versements)): ?>
                                        <?php foreach ($versements as $versement): ?>
                                            <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <i class="fas fa-user-circle text-primary"></i>
                                            </div>
                                            <div>
                                                <?php
                                                    $users = verifier_element('utilisateur', ['IDUTILISATEUR'], [$versement['IDUTILISATEUR']], '');
                                                echo htmlspecialchars($users['NomPrenom']); 
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="amount-display">
                                            <?php echo number_format($versement['MontantVersement'], 0, ',', ' '); ?> FCFA
                                        </span>
                                    </td>
                                    <td>
                                        <span class="date-display">
                                            <?php echo date('d/m/Y H:i', strtotime($versement['DateIns'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="mode-display">
                                            <?php
                                                    $paiment = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$versement['IDMODE_REGLEMENT']], '');
                                            echo htmlspecialchars($paiment['ModeReglement']); 
                                            ?>
                                        </span>
                                    </td>
                                                <td>
                                        <div class="action-buttons">
                                                    <form method="POST" action="fonction_traitement/request.php" style="display:inline;">
                                                        <input type="hidden" name="idVersement" value="<?php echo htmlspecialchars($versement['IDVERSEMENTS']); ?>">
                                                <?php 
                                                $can_delete = can_user('versement', 'supprimer');
                                                $disabled = $can_delete ? '' : 'disabled title="Accès refusé"';
                                                echo '<button type="submit" name="supprimerVersement" class="btn-action btn-danger btn-delete-protected" title="Supprimer" ' . $disabled . '><i class="fas fa-trash"></i></button>';
                                                ?>
                                                    </form>
                                            <?php 
                                            $can_edit = can_user('versement', 'modifier');
                                            $disabled = $can_edit ? '' : 'disabled title="Accès refusé"';
                                            echo '<button class="btn-action btn-secondary edit-btn" data-id="' . htmlspecialchars($versement['IDUTILISATEUR']) . '" data-montant="' . htmlspecialchars($versement['MontantVersement']) . '" data-date="' . htmlspecialchars($versement['DateIns']) . '" data-mode="' . htmlspecialchars($versement['IDMODE_REGLEMENT']) . '" title="Modifier" ' . $disabled . '><i class="fas fa-edit"></i></button>';
                                            ?>
                                        </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <h5>Aucun versement trouvé</h5>
                                        <p>Commencez par créer votre premier versement</p>
                                    </div>
                                </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
            
            <!-- Pagination et export -->
            <div class="pagination-container">
                <div class="pagination-info">
                    <span id="pagination-info">Affichage des résultats</span>
                </div>
                
                        <nav>
                    <ul id="pagination" class="pagination"></ul>
                        </nav>
                
                <div class="export-buttons">
                    <?php 
                    $can_print = can_user('versement', 'imprimer');
                    $disabled_print = $can_print ? '' : 'disabled title="Accès refusé"';
                    echo '<button id="printButton" class="btn-export btn-success" title="Imprimer" ' . $disabled_print . '><i class="fas fa-print me-1"></i> Imprimer</button>';
                    ?>
                    <?php 
                    $can_export = can_user('versement', 'exporter');
                    $disabled_export = $can_export ? '' : 'disabled title="Accès refusé"';
                    echo '<button id="exportExcel" class="btn-export btn-primary" title="Export Excel" ' . $disabled_export . '><i class="fas fa-file-excel me-1"></i> Excel</button>';
                    echo '<button id="exportWord" class="btn-export btn-info" title="Export Word" ' . $disabled_export . '><i class="fas fa-file-word me-1"></i> Word</button>';
                    echo '<button id="exportTxt" class="btn-export btn-secondary" title="Export TXT" ' . $disabled_export . '><i class="fas fa-file-alt me-1"></i> TXT</button>';
                    ?>
                        </div>
                    </div>
                </div>
        <!-- Modal supprimé - modification directe dans les champs -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        
        <script>
            // ===== SYSTÈME DE VERSEMENT OPTIMISÉ =====
            
            // Variables globales
            let currentPage = 1;
            let rowsPerPage = 10;
            let allRows = [];
            let filteredRows = [];
            
            // Initialisation
            document.addEventListener('DOMContentLoaded', function() {
                initializeSystem();
                setupEventListeners();
                setupPagination();
            });
            
            // Initialisation du système
            function initializeSystem() {
                console.log('Initialisation du système de filtrage...');
                
                // Récupérer toutes les lignes
                allRows = Array.from(document.querySelectorAll('#table-body tr'));
                filteredRows = [...allRows];
                
                console.log('Lignes trouvées:', allRows.length);
                
                // Définir la date d'aujourd'hui
                document.getElementById('dat').value = new Date().toISOString().split('T')[0];
                
                // Masquer les alertes après 5 secondes
                setTimeout(hideAlerts, 5000);
                
                // Mise à jour de l'info pagination
                updatePaginationInfo();
                
                console.log('Système initialisé avec succès');
            }
            
            // Configuration des événements
            function setupEventListeners() {
                // Bouton montant recommandé
            document.getElementById('btnMontantRecommande').addEventListener('click', function() {
                const montantRecommande = <?= $montant_versement_recommande ?>;
                document.getElementById('montant').value = montantRecommande;
                
                // Animation de confirmation
                this.innerHTML = '<i class="fas fa-check"></i> Rempli !';
                    this.classList.remove('btn-recommended');
                this.classList.add('btn-success');
                
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-calculator"></i> Recommandé';
                    this.classList.remove('btn-success');
                        this.classList.add('btn-recommended');
                }, 2000);
            });
                
                // Recherche instantanée
                document.getElementById('searchInput').addEventListener('keyup', function() {
                    performSearch(this.value);
                });
                
                // Filtre par date
                document.getElementById('dateFilter').addEventListener('change', function() {
                    filterByDate(this.value);
                });
                
                // Filtres par plage de dates
                document.getElementById('dateRangeStart').addEventListener('change', function() {
                    filterByDateRange();
                });
                
                document.getElementById('dateRangeEnd').addEventListener('change', function() {
                    filterByDateRange();
                });
                
                // Nombre de lignes par page
                document.getElementById('rowsPerPageSelect').addEventListener('change', function() {
                    rowsPerPage = this.value === 'liste_complete' ? filteredRows.length : parseInt(this.value);
                    currentPage = 1;
                    displayPage();
                    setupPagination();
                    updatePaginationInfo();
                });
                
                // Boutons d'export
                setupExportButtons();
                
                // Boutons de modification
                setupEditButtons();
            }
            
            // Recherche optimisée
            function performSearch(query) {
                const filter = query.toLowerCase();
                filteredRows = allRows.filter(row => {
                    const text = row.textContent.toLowerCase();
                    return text.includes(filter);
                });
                currentPage = 1;
                displayPage();
                setupPagination();
                updatePaginationInfo();
            }
            
            // Filtre par date optimisé
            function filterByDate(dateFilter) {
                console.log('Filtre par date déclenché:', dateFilter);
                
                if (!dateFilter) {
                    filteredRows = [...allRows];
                } else {
                    const today = new Date();
                    filteredRows = allRows.filter(row => {
                        const dateCell = row.querySelector('td:nth-child(3)');
                        if (!dateCell) return false;
                        
                        try {
                            // Parser la date au format dd/mm/yyyy HH:mm
                            const dateText = dateCell.textContent.trim();
                            console.log('Date texte:', dateText);
                            
                            const [datePart, timePart] = dateText.split(' ');
                            const [day, month, year] = datePart.split('/');
                            const rowDate = new Date(year, month - 1, day);
                            
                            console.log('Date parsée:', rowDate);
                            
                            let matches = false;
                            switch(dateFilter) {
                                case 'today':
                                    matches = rowDate.toDateString() === today.toDateString();
                                    break;
                                case 'week':
                                    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                                    matches = rowDate >= weekAgo;
                                    break;
                                case 'month':
                                    matches = rowDate.getMonth() === today.getMonth() && 
                                             rowDate.getFullYear() === today.getFullYear();
                                    break;
                                case 'year':
                                    matches = rowDate.getFullYear() === today.getFullYear();
                                    break;
                                default:
                                    matches = true;
                            }
                            
                            console.log('Match:', matches);
                            return matches;
                        } catch (error) {
                            console.error('Erreur parsing date:', error, dateText);
                            return false;
                        }
                    });
                }
                
                console.log('Lignes filtrées:', filteredRows.length);
                currentPage = 1;
                displayPage();
                setupPagination();
                updatePaginationInfo();
            }
            
            // Filtre par plage de dates
            function filterByDateRange() {
                const startDate = document.getElementById('dateRangeStart').value;
                const endDate = document.getElementById('dateRangeEnd').value;
                
                console.log('Filtre par plage déclenché:', startDate, 'à', endDate);
                
                if (!startDate && !endDate) {
                    filteredRows = [...allRows];
                } else {
                    filteredRows = allRows.filter(row => {
                        const dateCell = row.querySelector('td:nth-child(3)');
                        if (!dateCell) return false;
                        
                        try {
                            // Parser la date au format dd/mm/yyyy HH:mm
                            const dateText = dateCell.textContent.trim();
                            console.log('Date texte plage:', dateText);
                            
                            const [datePart, timePart] = dateText.split(' ');
                            const [day, month, year] = datePart.split('/');
                            const rowDate = new Date(year, month - 1, day);
                            const rowDateStr = rowDate.toISOString().split('T')[0];
                            
                            console.log('Date parsée plage:', rowDateStr);
                            
                            let matches = true;
                            
                            if (startDate) {
                                matches = matches && rowDateStr >= startDate;
                                console.log('Comparaison start:', rowDateStr, '>=', startDate, '=', matches);
                            }
                            
                            if (endDate) {
                                matches = matches && rowDateStr <= endDate;
                                console.log('Comparaison end:', rowDateStr, '<=', endDate, '=', matches);
                            }
                            
                            return matches;
                        } catch (error) {
                            console.error('Erreur parsing date plage:', error, dateText);
                            return false;
                        }
                    });
                }
                
                console.log('Lignes filtrées plage:', filteredRows.length);
                currentPage = 1;
                displayPage();
                setupPagination();
                updatePaginationInfo();
            }
            
            // Affichage des pages
            function displayPage() {
                const start = (currentPage - 1) * rowsPerPage;
                const end = Math.min(start + rowsPerPage, filteredRows.length);
                
                // Masquer toutes les lignes
                allRows.forEach(row => row.style.display = 'none');
                
                // Afficher les lignes de la page courante
                for (let i = start; i < end; i++) {
                    if (filteredRows[i]) {
                        filteredRows[i].style.display = '';
                    }
                }
            }
            
            // Configuration de la pagination
            function setupPagination() {
                const pagination = document.getElementById('pagination');
                pagination.innerHTML = '';
                
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                
                // Bouton précédent
                const prevItem = createPageItem('Précédent', currentPage > 1, () => {
                    if (currentPage > 1) {
                        currentPage--;
                        displayPage();
                        setupPagination();
                        updatePaginationInfo();
                    }
                });
                pagination.appendChild(prevItem);
                
                // Pages numérotées
                for (let i = 1; i <= totalPages; i++) {
                    const pageItem = createPageItem(i.toString(), true, () => {
                        currentPage = i;
                        displayPage();
                        setupPagination();
                        updatePaginationInfo();
                    });
                    
                    if (i === currentPage) {
                        pageItem.classList.add('active');
                    }
                    
                    pagination.appendChild(pageItem);
                }
                
                // Bouton suivant
                const nextItem = createPageItem('Suivant', currentPage < totalPages, () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        displayPage();
                        setupPagination();
                        updatePaginationInfo();
                    }
                });
                pagination.appendChild(nextItem);
            }
            
            // Création d'un élément de pagination
            function createPageItem(text, enabled, onClick) {
                const item = document.createElement('li');
                item.className = `page-item ${enabled ? '' : 'disabled'}`;
                
                const link = document.createElement('a');
                link.href = '#';
                link.textContent = text;
                link.className = 'page-link';
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (enabled) onClick();
                });
                
                item.appendChild(link);
                return item;
            }
            
            // Mise à jour de l'info pagination avec capacités
            function updatePaginationInfo() {
                const start = (currentPage - 1) * rowsPerPage + 1;
                const end = Math.min(currentPage * rowsPerPage, filteredRows.length);
                const total = filteredRows.length;
                
                let capacityInfo = '';
                if (total > 100000) {
                    capacityInfo = ' (Capacité: Millions de lignes supportées)';
                } else if (total > 10000) {
                    capacityInfo = ' (Capacité: Centaines de milliers de lignes)';
                } else if (total > 1000) {
                    capacityInfo = ' (Capacité: Milliers de lignes)';
                }
                
                document.getElementById('pagination-info').textContent = 
                    `Affichage ${start}-${end} sur ${total} résultats${capacityInfo}`;
            }
            
            // Configuration des boutons d'export
            function setupExportButtons() {
                // Impression
                document.getElementById('printButton').addEventListener('click', function() {
                    printTable();
                });
                
                // Export Excel
                document.getElementById('exportExcel').addEventListener('click', function() {
                    exportToCSV();
                });
                
                // Export Word
                document.getElementById('exportWord').addEventListener('click', function() {
                    exportToWord();
                });
                
                // Export TXT
                document.getElementById('exportTxt').addEventListener('click', function() {
                    exportToTxt();
                });
            }
            
            // Impression du tableau
            function printTable() {
                const printWindow = window.open('', '', 'height=600,width=900');
                const printContent = `
                <html>
                <head>
                    <title>Impression Versements</title>
                    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'/>
                    <style>
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; }
                        th { background-color: #f4f4f4; }
                        .text-center { text-align: center; }
                    </style>
</head>
                <body>
                    <h2 class='text-center'>Liste des Versements</h2>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Montant</th>
                                <th>Date</th>
                                <th>Mode</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filteredRows.map(row => {
                                const cells = row.querySelectorAll('td');
                                if (cells.length < 5) return '';
                                return `<tr><td>${cells[0].innerText}</td><td>${cells[1].innerText}</td><td>${cells[2].innerText}</td><td>${cells[3].innerText}</td></tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </body>
                </html>
                `;
                
                printWindow.document.write(printContent);
                printWindow.document.close();
                printWindow.onload = function() {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                };
            }
            
            // Export CSV
            function exportToCSV() {
                const csv = filteredRows.map(row => {
                    const cells = row.querySelectorAll('td');
                    return Array.from(cells).map(cell => `"${cell.innerText.replace(/"/g, '""')}"`).join(',');
                }).join('\n');
                
                downloadFile(csv, 'versements.csv', 'text/csv');
            }
            
            // Export Word
            function exportToWord() {
                const table = document.querySelector('.versement-table').outerHTML;
                const html = `<html><head><meta charset='utf-8'></head><body><h2>Liste des Versements</h2>${table}</body></html>`;
                
                downloadFile('\ufeff' + html, 'versements.doc', 'application/msword');
            }
            
            // Export TXT
            function exportToTxt() {
                const txt = filteredRows.map(row => {
                    const cells = row.querySelectorAll('td');
                    return Array.from(cells).map(cell => cell.innerText).join('\t');
                }).join('\n');
                
                downloadFile(txt, 'versements.txt', 'text/plain');
            }
            
            // Téléchargement de fichier
            function downloadFile(content, filename, type) {
                const blob = new Blob([content], { type: type });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
            
            // Configuration des boutons de modification (modal supprimé)
            function setupEditButtons() {
                // Fonction supprimée - gestion par event listener global
            }
            
            // Masquer les alertes
            function hideAlerts() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
                
                // Nettoyer l'URL
                if (window.history.replaceState) {
                    const url = new URL(window.location);
                    url.searchParams.delete('error');
                    url.searchParams.delete('success');
                    window.history.replaceState(null, null, url);
                }
            }
            
            // Fonction pour fermer le modal (plus utilisée)
            function closeModal() {
                // Modal supprimé - fonction conservée pour compatibilité
            }
            
            // Fonction pour ajouter le bouton "Mettre à jour"
            function addUpdateButton(idVersement) {
                // Supprimer les anciens boutons s'ils existent
                const existingUpdateBtn = document.getElementById('updateBtn');
                const existingCancelBtn = document.getElementById('cancelBtn');
                if (existingUpdateBtn) existingUpdateBtn.remove();
                if (existingCancelBtn) existingCancelBtn.remove();
                
                // Créer le bouton de mise à jour
                const updateBtn = document.createElement('button');
                updateBtn.id = 'updateBtn';
                updateBtn.type = 'button';
                updateBtn.className = 'btn btn-warning btn-lg mt-3 me-2';
                updateBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i> Mettre à jour ce versement';
                updateBtn.style.width = '48%';
                
                // Créer le bouton d'annulation
                const cancelBtn = document.createElement('button');
                cancelBtn.id = 'cancelBtn';
                cancelBtn.type = 'button';
                cancelBtn.className = 'btn btn-secondary btn-lg mt-3';
                cancelBtn.innerHTML = '<i class="fas fa-times me-2"></i> Annuler';
                cancelBtn.style.width = '48%';
                
                // Ajouter les event listeners
                updateBtn.addEventListener('click', function() {
                    updateVersement(idVersement);
                });
                
                cancelBtn.addEventListener('click', function() {
                    cancelUpdate();
                });
                
                // Créer un conteneur pour les boutons
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'd-flex justify-content-between';
                buttonContainer.appendChild(updateBtn);
                buttonContainer.appendChild(cancelBtn);
                
                // Ajouter le conteneur après le formulaire principal
                const form = document.getElementById('versementForm');
                form.appendChild(buttonContainer);
                
                // Scroll vers les boutons
                buttonContainer.scrollIntoView({ behavior: 'smooth' });
            }
            
            // Fonction pour annuler la modification
            function cancelUpdate() {
                // Supprimer les boutons
                const updateBtn = document.getElementById('updateBtn');
                const cancelBtn = document.getElementById('cancelBtn');
                if (updateBtn) updateBtn.remove();
                if (cancelBtn) cancelBtn.remove();
                
                // Vider les champs
                document.getElementById('utilisateur').value = '';
                document.getElementById('montant').value = '';
                document.getElementById('dat').value = new Date().toISOString().split('T')[0];
                document.getElementById('mode').value = '';
                
                // Modal supprimé - pas de fermeture nécessaire
            }
            
            // Fonction pour mettre à jour le versement
            function updateVersement(idVersement) {
                const formData = new FormData();
                formData.append('idVersement', idVersement);
                formData.append('utilisateur', document.getElementById('utilisateur').value);
                formData.append('montant', document.getElementById('montant').value);
                formData.append('dat', document.getElementById('dat').value);
                formData.append('mode', document.getElementById('mode').value);
                formData.append('modifierVersement', '1');
                
                // Afficher un indicateur de chargement
                const updateBtn = document.getElementById('updateBtn');
                const originalText = updateBtn.innerHTML;
                updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Mise à jour en cours...';
                updateBtn.disabled = true;
                
                // Envoyer la requête
                fetch('fonction_traitement/request.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Réinitialiser le bouton
                    updateBtn.innerHTML = originalText;
                    updateBtn.disabled = false;
                    
                    // Supprimer le bouton
                    updateBtn.remove();
                    
                    // Vider les champs
                    document.getElementById('utilisateur').value = '';
                    document.getElementById('montant').value = '';
                    document.getElementById('dat').value = new Date().toISOString().split('T')[0];
                    document.getElementById('mode').value = '';
                    
                    // Recharger la page pour voir les changements
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erreur lors de la mise à jour:', error);
                    updateBtn.innerHTML = originalText;
                    updateBtn.disabled = false;
                    alert('Erreur lors de la mise à jour du versement');
                });
            }
            
            // Event listeners pour les boutons (modal supprimé)
            document.addEventListener('click', function(e) {
                // Gestion des clics sur les boutons de modification
                if (e.target.classList.contains('edit-btn')) {
                    // Remplissage automatique des champs
                    const idUtilisateur = e.target.getAttribute('data-id');
                    const montant = e.target.getAttribute('data-montant');
                    const date = e.target.getAttribute('data-date').split(' ')[0];
                    const mode = e.target.getAttribute('data-mode');
                    const tr = e.target.closest('tr');
                    const idVersement = tr.querySelector('form input[name="idVersement"]').value;
                    
                    // Remplir les champs
                    document.getElementById('utilisateur').value = idUtilisateur;
                    document.getElementById('montant').value = montant;
                    document.getElementById('dat').value = date;
                    document.getElementById('mode').value = mode;
                    
                    // Ajouter les boutons de modification
                    addUpdateButton(idVersement);
                }
                
                // Protection bouton supprimer
                if (e.target.classList.contains('btn-delete-protected') || e.target.closest('.btn-delete-protected')) {
                    e.preventDefault();
                    
                    // Récupérer les informations du versement
                    const tr = e.target.closest('tr');
                    const idVersement = tr.querySelector('input[name="idVersement"]').value;
                    const montant = tr.cells[2].textContent.trim();
                    const date = tr.cells[3].textContent.trim();
                    const utilisateur = tr.cells[1].textContent.trim();
                    
                    // Afficher la confirmation
                    showDeleteConfirmation(idVersement, montant, date, utilisateur);
                }
            });
            
            // Fonction de confirmation de suppression
            function showDeleteConfirmation(idVersement, montant, date, utilisateur) {
                // Créer le modal de confirmation
                const confirmModal = document.createElement('div');
                confirmModal.className = 'modal fade show';
                confirmModal.style.display = 'block';
                confirmModal.style.zIndex = '9999';
                confirmModal.innerHTML = `
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Confirmation de suppression
                                </h5>
                                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteModal()"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-warning me-2"></i>
                                    <strong>Attention !</strong> Cette action est irréversible.
                                </div>
                                
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Détails du versement à supprimer :</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Utilisateur :</strong><br>
                                                <span class="text-primary">${utilisateur}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Montant :</strong><br>
                                                <span class="text-success">${montant} FCFA</span>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <strong>Date :</strong><br>
                                                <span class="text-info">${date}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <p class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Êtes-vous sûr de vouloir supprimer ce versement ?
                                    </p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                                    <i class="fas fa-times me-1"></i> Annuler
                                </button>
                                <button type="button" class="btn btn-danger" onclick="confirmDelete('${idVersement}')">
                                    <i class="fas fa-trash me-1"></i> Supprimer définitivement
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Ajouter le backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.style.zIndex = '9998';
                backdrop.id = 'deleteBackdrop';
                
                // Ajouter au DOM
                document.body.appendChild(confirmModal);
                document.body.appendChild(backdrop);
                
                // Empêcher le scroll
                document.body.style.overflow = 'hidden';
            }
            
            // Fonction pour fermer le modal de confirmation
            function closeDeleteModal() {
                const modal = document.querySelector('.modal.show');
                const backdrop = document.getElementById('deleteBackdrop');
                
                if (modal) modal.remove();
                if (backdrop) backdrop.remove();
                
                // Restaurer le scroll
                document.body.style.overflow = '';
            }
            
            // Fonction pour confirmer la suppression
            function confirmDelete(idVersement) {
                // Afficher un indicateur de chargement
                const deleteBtn = document.querySelector('.btn-danger');
                const originalText = deleteBtn.innerHTML;
                deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Suppression...';
                deleteBtn.disabled = true;
                
                // Créer le formulaire de suppression
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'fonction_traitement/request.php';
                form.style.display = 'none';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'idVersement';
                idInput.value = idVersement;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'supprimerVersement';
                submitInput.value = '1';
                
                form.appendChild(idInput);
                form.appendChild(submitInput);
                document.body.appendChild(form);
                
                // Soumettre le formulaire
                form.submit();
            }
        </script>
    </div>
</body>

</html>