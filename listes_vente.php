<?php
require_once 'fonction_traitement/fonction.php';
check_access(); // Protection automatique selon $DROITS_PAGES

try {
    include('db/connecting.php');
    
    // Configuration de la pagination côté serveur
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(5, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Paramètres de recherche et filtrage
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    
    // Construction de la requête optimisée avec pagination
    $whereConditions = [];
    $params = [];
    
    // Filtre par recherche (nom client ou numéro de vente)
    if (!empty($search)) {
        $whereConditions[] = "(c.NomPrenomClient LIKE :search OR v.NumeroVente LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    // Filtre par date
    if (!empty($startDate)) {
        $whereConditions[] = "DATE(v.DateIns) >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if (!empty($endDate)) {
        $whereConditions[] = "DATE(v.DateIns) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Requête pour compter le total des ventes (pour la pagination)
    $countSql = "SELECT COUNT(*) as total 
                 FROM vente v 
                 LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT 
                 $whereClause";
    $countStmt = $cnx->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalVentes = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalVentes / $limit);
    
    // Requête principale optimisée avec pagination et tri décroissant
    $sql = "SELECT v.*, c.NomPrenomClient, c.Telephone 
            FROM vente v 
            LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT 
            $whereClause 
            ORDER BY v.DateIns DESC, v.IDFactureVente DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $cnx->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque vente, récupérer les articles associés
    foreach ($ventes as &$vente) {
        // Récupérer les informations du client
        $client = verifier_element('client', ['IDCLIENT'], [$vente['IDCLIENT']], '');
        $vente['NomPrenomClient'] = $client ? $client['NomPrenomClient'] : 'Client inconnu';
        $vente['Telephone'] = $client ? $client['Telephone'] : '';
        // Récupérer les modes de paiement multiples si multi-paiement utilisé
// puis on ajoute les modes de paiement si multi-paiement existe
$vente_id = $vente['IDFactureVente'];
$stmt = $cnx->prepare("SELECT MONTANT,IDMODE_REGLEMENT FROM vente_paiement WHERE IDFactureVente = ?");
$stmt->execute([$vente_id]);
$vente['modes_paiement'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
       /* // Récupérer les articles de la vente
        $factures = verifier_element_tous('facture_article', ['NumeroVente'], [$vente['NumeroVente']], '');
        $vente['articles'] = [];
        
        if (is_array($factures)) {
            foreach ($factures as $facture) {
                $article = verifier_element('article', ['IDARTICLE'], [$facture['IDARTICLE']], '');
                if ($article) {
                    $num_serie = verifier_element('num_serie', ['IDARTICLE', 'NumeroVente'], 
                        [$article['IDARTICLE'], $vente['NumeroVente']], '');
                    
                    $vente['articles'][] = [
                        'libelle' => $article['libelle'],
                        'numeroSerie' => $num_serie ? $num_serie['NUMERO_SERIE'] : '',
                        'quantite' => $facture['QuantiteVendue'],
                        'prix' => $article['PrixVenteTTC']
                    ];
                }
            }
        }
    }
    */
   /* $factures = verifier_element_tous('facture_article', ['NumeroVente'], [$vente['NumeroVente']], '');
    $vente['articles'] = [];
    
    if (is_array($factures)) {
        foreach ($factures as $facture) {
            $article = verifier_element('article', ['IDARTICLE'], [$facture['IDARTICLE']], '');
            if ($article) {
                $num_serie = verifier_element('num_serie', ['IDARTICLE', 'NumeroVente'], 
                    [$article['IDARTICLE'], $vente['NumeroVente']], '');
                
                $vente['articles'][] = [
                    'libelle' => $article['libelle'],
                    'numeroSerie' => $num_serie['NUMERO_SERIE'] ?? '',
                    'quantite' => $facture['QuantiteVendue'],
                    'prix' => $article['PrixVenteTTC'] ?? 0
                ];
            }
        }
    }
}*/
foreach ($ventes as &$vente) {
    // Récupérer les articles de la vente avec leurs numéros de série (requête corrigée pour éviter la duplication)
    $sql = "SELECT DISTINCT fa.IDARTICLE, a.libelle, a.PrixVenteTTC, fa.QuantiteVendue, ns.NUMERO_SERIE
            FROM facture_article fa
            JOIN article a ON fa.IDARTICLE = a.IDARTICLE
            INNER JOIN num_serie ns 
                ON ns.IDARTICLE = fa.IDARTICLE 
                AND ns.NumeroVente = fa.NumeroVente 
                AND ns.ID_VENTE = fa.IDFactureVente
                AND ns.statut = 'vendue'
            WHERE fa.NumeroVente = ?
            ORDER BY fa.IDFactureVente, ns.NUMERO_SERIE";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$vente['NumeroVente']]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Regrouper les numéros de série par article
    $articles_groupes = [];
    foreach ($articles as $article) {
        $id = $article['IDARTICLE'];
        if (!isset($articles_groupes[$id])) {
            $articles_groupes[$id] = [
                'libelle' => $article['libelle'],
                'prix' => $article['PrixVenteTTC'],
                'quantite' => $article['QuantiteVendue'],
                'numeros' => []
            ];
        }
        if (!empty($article['NUMERO_SERIE'])) {
            $articles_groupes[$id]['numeros'][] = $article['NUMERO_SERIE'];
        }
    }

    // Convertir le tableau associatif en tableau indexé
    $vente['articles'] = array_values($articles_groupes);
}
    }

        unset($vente); // Détacher la référence

} catch (\Throwable $th) {
    error_log("Erreur dans Listes_vente.php : " . $th->getMessage());
    $erreur = 'Erreur lors de la récupération des ventes';
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
    <title>Espace Liste de Vente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <!-- SweetAlert2 pour les alertes professionnelles -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Système de thème sombre/clair -->
</head>
<body id="liste_vente">
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
        <?php include('includes/theme_switcher.php'); ?>

    <header>
        <h1>Espace Liste de Vente</h1>
    </header>
    <main class="container">
        <div class="m-3">
            
        </div>
        <section class="row p-3">
            <div class="row p-3">
                <h2>Liste des Ventes</h2>
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
                <div>
                    <!-- Informations de pagination -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle"></i>
                                <strong>Total des ventes :</strong> <?php echo number_format($totalVentes, 0, ',', ' '); ?> 
                                | <strong>Page :</strong> <?php echo $page; ?> sur <?php echo $totalPages; ?>
                                | <strong>Lignes :</strong> <?php echo $offset + 1; ?> à <?php echo min($offset + $limit, $totalVentes); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <?php echo bouton_action('Exporter vers Excel', 'Listes_vente', 'exporter', 'btn btn-success', 'id="exportBtn" onclick="exportVentes()"'); ?>
                                <?php echo bouton_action('Exporter vers Word', 'Listes_vente', 'exporter', 'btn btn-primary', 'id="exportWordBtn" onclick="exportVentes(\'word\')"'); ?>
                                <?php echo bouton_action('Exporter en Bloc-notes', 'Listes_vente', 'exporter', 'btn btn-secondary', 'id="exportTxtBtn" onclick="exportVentes(\'txt\')"'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtres et recherche -->
                    <form method="GET" id="filterForm" class="row mb-4">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" id="searchInput" class="form-control" 
                                       placeholder="Rechercher par nom client ou numéro de vente..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input name="start_date" id="startDate" type="date" class="form-control" 
                                       value="<?php echo htmlspecialchars($startDate); ?>">
                                    </div>
                                </div>
                        <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input name="end_date" id="endDate" type="date" class="form-control" 
                                       value="<?php echo htmlspecialchars($endDate); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filtrer
                                    </button>
                                <a href="Listes_vente.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                                </div>
                            </div>
                    </form>
                    
                    <!-- Sélection du nombre de lignes par page -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <label class="input-group-text" for="rowsPerPageSelect">Lignes par page:</label>
                                <select id="rowsPerPageSelect" class="form-select" onchange="changePageSize()">
                                    <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5</option>
                                    <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="d-flex justify-content-end">
                                <button id="printButton" class="btn btn-success">
                                    <i class="fas fa-print"></i> Imprimer
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered p-5">
                            <thead>
                                <tr class="text-center">
                                    <th>#</th>
                                    <th>Client</th>
                                    <th>Numéro Vente</th>
                                    <th>Total avec remise</th>
                                    <th>Montant remise</th>
                                    <th>Montant Versé</th>
                                    <th>Monnaie Rendu</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="table-body">
                                <?php if (!empty($ventes)): ?>
                                <?php
                                    // Calculer le numéro de ligne réel basé sur la position dans l'ensemble des données
                                    $id = $offset + 1; 
                                    foreach ($ventes as $vente): 
                                        $collapseId = "collapseVente" . $id;
                                ?>
                                    <tr>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo $id ?></td>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                                            <?php 
                                                echo htmlspecialchars($vente['NomPrenomClient']);
                                            ?>
                                        </td>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars($vente['NumeroVente']); ?></td>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['MontantTotal']  ?? 0, 0, ',', ' ')); ?> FCFA</td>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['MontantRemise']  ?? 0, 0, ',', ' ')); ?> FCFA</td>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['MontantVerse']  ?? 0, 0, ',', ' ')); ?> FCFA</td>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['Monnaie'] ?? 0, 0, ',', ' ')); ?> FCFA</td>
                                        <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars($vente['DateIns']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <!-- Bouton SMS -->
                                                <button class="btn btn-success btn-sm me-1" 
                                                        onclick="envoyerSMS('<?= htmlspecialchars($vente['NumeroVente']) ?>', '<?= htmlspecialchars($vente['NomPrenomClient']) ?>', '<?= htmlspecialchars($vente['Telephone']) ?>', '<?= $vente['MontantTotal'] ?>', '<?= $vente['DateIns'] ?>')"
                                                        title="Envoyer un SMS pour cette vente">
                                                    <i class="fas fa-sms"></i> SMS
                                                </button>
                                                
                                                <!-- Bouton Email -->
                                                <button class="btn btn-primary btn-sm me-1" 
                                                        onclick="envoyerEmail('<?= htmlspecialchars($vente['NumeroVente']) ?>', '<?= htmlspecialchars($vente['NomPrenomClient']) ?>', '<?= htmlspecialchars($vente['Telephone']) ?>', '<?= $vente['MontantTotal'] ?>', '<?= $vente['DateIns'] ?>')"
                                                        title="Envoyer un email pour cette vente">
                                                    <i class="fas fa-envelope"></i> Email
                                                </button>
                                                
                                                <!-- Bouton Supprimer -->
                                                <?php echo bouton_action('SUPPRIMER', 'Listes_vente', 'supprimer', 'btn btn-danger btn-sm', 'type="button" onclick="confirmerSuppression(\'' . htmlspecialchars($vente['NumeroVente']) . '\', \'' . htmlspecialchars($vente['NomPrenomClient']) . '\')" title="Supprimer cette vente"'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                    <td colspan="10" class="p-0">
                                        <div id="<?php echo $collapseId; ?>" class="collapse" data-bs-parent="#accordionExample">
                                            <div class="p-4">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h5 class="mb-0">Détails de la vente</h5>
                                                    <div class="btn-group">
                                                    <?php
                                                    // Vérifier si c'était une vente à crédit
                                                    $vente_credit = verifier_element('ventes_credit', ['NumeroVente'], [$vente['NumeroVente']], '');
                                                    if ($vente_credit) {
                                                    ?>
                                                        <?php echo bouton_action('Ticket Crédit', 'Listes_vente', 'imprimer', 'btn btn-primary', 'type="button" onclick="imprimerSansNouvelOnglet(\'print_ticket_caisse_credit.php?numero=' . $vente['NumeroVente'] . '\')"'); ?>
                                                        <?php echo bouton_action('Facture Crédit', 'Listes_vente', 'imprimer', 'btn btn-info', 'type="button" onclick="imprimerSansNouvelOnglet(\'print_facture_standardcredit.php?numero=' . $vente['NumeroVente'] . '\')"'); ?>
                                                        <?php echo bouton_action('Facture TVA Crédit', 'Listes_vente', 'imprimer', 'btn btn-success', 'type="button" onclick="imprimerSansNouvelOnglet(\'print_facture_tvacredit.php?numero=' . $vente['NumeroVente'] . '\')"'); ?>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <?php echo bouton_action('Ticket', 'Listes_vente', 'imprimer', 'btn btn-primary', 'type="button" onclick="imprimerSansNouvelOnglet(\'print_ticket_caisse.php?numero=' . $vente['NumeroVente'] . '\')"'); ?>
                                                        <?php echo bouton_action('Facture', 'Listes_vente', 'imprimer', 'btn btn-info', 'type="button" onclick="imprimerSansNouvelOnglet(\'print_facture_standard.php?numero=' . $vente['NumeroVente'] . '\')"'); ?>
                                                        <?php echo bouton_action('Facture TVA', 'Listes_vente', 'imprimer', 'btn btn-success', 'type="button" onclick="imprimerSansNouvelOnglet(\'print_facture_tva.php?numero=' . $vente['NumeroVente'] . '\')"'); ?>
                                                    <?php } ?>
                                                    </div>
                                                </div>
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <h6>Informations client</h6>
                                                        <p><strong>Nom:</strong> <?php echo htmlspecialchars($vente['NomPrenomClient']); ?></p>
                                                        <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($vente['Telephone']); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Détails de la transaction</h6>
                                                        <p><strong>Date:</strong> <?php echo htmlspecialchars($vente['DateIns']); ?></p>
                                                        <p><strong>Mode de paiement:</strong> <?php 
                                                        // Affichage des modes de paiement
                                                        if ($vente_credit) {
                                                            $paiements = verifier_element_tous('ventes_credit_paiement', ['IDVenteCredit'], [$vente_credit['IDVenteCredit']], '');
                                                            if (count($paiements) > 1) {
                                                                foreach ($paiements as $p) {
                                                                    $mode = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$p['IDMODE_REGLEMENT']], '');
                                                                    echo '- ' . htmlspecialchars($mode['ModeReglement'] ?? 'Inconnu') . ' : ' . number_format($p['AccompteVerse'], 0, ',', ' ') . ' FCFA<br>';
                                                                }
                                                            } elseif (count($paiements) == 1) {
                                                                $mode = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$paiements[0]['IDMODE_REGLEMENT']], '');
                                                                echo htmlspecialchars($mode['ModeReglement'] ?? 'Non spécifié') . ' : ' . number_format($paiements[0]['AccompteVerse'], 0, ',', ' ') . ' FCFA';
                                                            } else {
                                                                echo 'Non spécifié';
                                                            }
                                                        } else if (!empty($vente['modes_paiement'])) {
                                                            foreach ($vente['modes_paiement'] as $mp) {
                                                                $mode_info = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$mp['IDMODE_REGLEMENT']], '');
                                                                $montant = isset($mp['MONTANT']) ? floatval($mp['MONTANT']) : 0;
                                                                echo '- ' . htmlspecialchars($mode_info['ModeReglement'] ?? 'Inconnu') . ' : ' . number_format($montant, 0, ',', ' ') . ' FCFA<br>';
                                                            }
                                                        } elseif (!empty($vente['ModePaiement'])) {
                                                            $mode_info = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$vente['ModePaiement']], '');
                                                            echo htmlspecialchars($mode_info['ModeReglement'] ?? 'Non spécifié');
                                                        } else {
                                                            echo 'Non spécifié';
                                                        }
                                                        ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <h6>Articles vendus</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Article</th>
                                                                <th>Numéro de série</th>
                                                                <th>Prix unitaire</th>
                                                                <th>Quantité</th>
                                                                <th>Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                        <?php
                                                        // Récupérer les articles avec la requête corrigée
                                                        $sql_articles = "SELECT DISTINCT fa.IDARTICLE, a.libelle, a.PrixVenteTTC, fa.QuantiteVendue, ns.NUMERO_SERIE
                                                                        FROM facture_article fa
                                                                        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
                                                                        INNER JOIN num_serie ns 
                                                                            ON ns.IDARTICLE = fa.IDARTICLE 
                                                                            AND ns.NumeroVente = fa.NumeroVente 
                                                                            AND ns.ID_VENTE = fa.IDFactureVente
                                                                            AND ns.statut = 'vendue'
                                                                        WHERE fa.NumeroVente = ?
                                                                        ORDER BY fa.IDFactureVente, ns.NUMERO_SERIE";
                                                        $stmt_articles = $cnx->prepare($sql_articles);
                                                        $stmt_articles->execute([$vente['NumeroVente']]);
                                                        $articles_details = $stmt_articles->fetchAll(PDO::FETCH_ASSOC);
                                                        
                                                        if (empty($articles_details)) {
                                                            // Fallback pour les ventes à crédit
                                                            $factures = verifier_element_tous('ventes_credit_ligne', ['NumeroVente'], [$vente['NumeroVente']], '');
                                                            if (is_array($factures) && !empty($factures)) {
                                                                foreach ($factures as $facture) {
                                                                    $article = verifier_element('article', ['IDARTICLE'], [$facture['IDARTICLE']], '');
                                                                    $num_serie = verifier_element('num_serie', ['IDARTICLE', 'NumeroVente'], [$article['IDARTICLE'], $vente['NumeroVente']], '');
                                                                    echo '<tr>';
                                                                    echo '<td>' . htmlspecialchars($article['libelle']) . '</td>';
                                                                    echo '<td>' . htmlspecialchars($num_serie['NUMERO_SERIE'] ?? '') . '</td>';
                                                                    echo '<td class="text-end">' . number_format($article['PrixVenteTTC'], 0, ',', ' ') . ' FCFA</td>';
                                                                    echo '<td class="text-center">' . htmlspecialchars($facture['QuantiteVendue']) . '</td>';
                                                                    echo '<td class="text-end">' . number_format($article['PrixVenteTTC'] * $facture['QuantiteVendue'], 0, ',', ' ') . ' FCFA</td>';
                                                                    echo '</tr>';
                                                                }
                                                            }
                                                        } else {
                                                            foreach ($articles_details as $article_detail) {
                                                                echo '<tr>';
                                                                echo '<td>' . htmlspecialchars($article_detail['libelle']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($article_detail['NUMERO_SERIE'] ?? '') . '</td>';
                                                                echo '<td class="text-end">' . number_format($article_detail['PrixVenteTTC'], 0, ',', ' ') . ' FCFA</td>';
                                                                echo '<td class="text-center">' . htmlspecialchars($article_detail['QuantiteVendue']) . '</td>';
                                                                echo '<td class="text-end">' . number_format($article_detail['PrixVenteTTC'] * $article_detail['QuantiteVendue'], 0, ',', ' ') . ' FCFA</td>';
                                                                echo '</tr>';
                                                            }
                                                        }
                                                        if (empty($articles_details) && (empty($factures) || !is_array($factures))) {
                                                            echo '<tr><td colspan="5"><em>Aucun article trouvé.</em></td></tr>';
                                                        }
                                                        ?>
                                                        </tbody>
                                                        <tfoot class="table-light">
                                                            <tr>
                                                                <td colspan="4" class="text-end"><strong>Total articles:</strong></td>
                                                                <td class="text-end"><strong><?php echo ($vente['MontantTotal'] ?? 00); ?> FCFA</strong></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" class="text-end"><strong>Remise:</strong></td>
                                                                <td class="text-end"><strong><?php echo ($vente['MontantRemise'] ?? 00); ?> FCFA</strong></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" class="text-end"><strong>Total final:</strong></td>
                                                                <td class="text-end"><strong><?php echo ($vente['MontantTotal'] ?? 00); ?> FCFA</strong></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    </tr>
                                <?php 
                                    $id++;
                                    endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10">Aucune vente enregistrée.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <!-- Pagination côté serveur -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <!-- Bouton Précédent -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i> Précédent
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-chevron-left"></i> Précédent</span>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Numéros de page -->
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                // Afficher la première page si nécessaire
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Pages autour de la page courante -->
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <!-- Afficher la dernière page si nécessaire -->
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Bouton Suivant -->
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            Suivant <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Suivant <i class="fas fa-chevron-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
        </nav>
                        <?php endif; ?>
                    </div>
            </div>
        </section>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
    // Masquer les alertes après 3 secondes
    setTimeout(function() {
        const errorAlert = document.getElementById('error-alert');
        const successAlert = document.getElementById('success-alert');
        if (errorAlert) {
                errorAlert.style.display = 'none';
            }
        if (successAlert) {
            successAlert.style.display = 'none';
            }

        // Nettoyer l'URL
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                url.searchParams.delete('success');
                window.history.replaceState(null, null, url);
            }
    }, 3000);

    // Fonction pour changer la taille de page
    function changePageSize() {
        const select = document.getElementById('rowsPerPageSelect');
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('limit', select.value);
        currentUrl.searchParams.delete('page'); // Retourner à la première page
        window.location.href = currentUrl.toString();
    }

    // Fonction pour l'impression avec détection mobile/desktop
    document.getElementById("printButton").addEventListener("click", function() {
        // Détecter si c'est un appareil mobile
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        if (isMobile) {
            printVentesMobile();
        } else {
            printVentesDesktop();
        }
    });

    // Fonction d'impression pour mobile (utilise window.print() directement)
    function printVentesMobile() {
        // Créer un contenu d'impression identique à celui du desktop
        const tableBody = document.querySelector('#table-body');
        if (!tableBody) {
            alert('Aucune donnée à imprimer');
            return;
        }

        // Créer le contenu HTML à imprimer (identique au desktop)
        let printContent = `
            <html>
            <head>
                <title>Liste des Ventes</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
                <style>
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 12px;
                    }
                    th, td {
                        border: 1px solid #ddd;
                        padding: 6px;
                        text-align: left;
                    }
                    th {
                        background-color: #f4f4f4;
                    }
                    .text-center {
                        text-align: center;
                    }
                    .text-end {
                        text-align: right;
                    }
                    @media print {
                        .no-print {
                            display: none !important;
                        }
                        body {
                            font-size: 10px;
                        }
                        /* Masquer la colonne Action */
                        th:last-child, td:last-child {
                            display: none !important;
                        }
                        /* Masquer les détails des ventes */
                        [id^="collapseVente"] {
                            display: none !important;
                        }
                    }
                </style>
            </head>
            <body>
                <h2 class="text-center">Liste des Ventes</h2>
                <p class="text-center"><strong>Page <?php echo $page; ?> sur <?php echo $totalPages; ?> | Total: <?php echo number_format($totalVentes, 0, ',', ' '); ?> ventes</strong></p>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Numéro Vente</th>
                            <th>Total avec remise</th>
                            <th>Montant remise</th>
                            <th>Montant Versé</th>
                            <th>Monnaie Rendu</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableBody.innerHTML}
                    </tbody>
                </table>
            </body>
            </html>
        `;

        // Créer un élément temporaire pour l'impression
        const printDiv = document.createElement('div');
        printDiv.id = 'printContent';
        printDiv.innerHTML = printContent;
        printDiv.style.position = 'absolute';
        printDiv.style.left = '-9999px';
        printDiv.style.top = '-9999px';
        document.body.appendChild(printDiv);

        // Ajouter des styles d'impression pour masquer les éléments non nécessaires
        const printStyles = document.createElement('style');
        printStyles.textContent = `
            @media print {
                /* Masquer tous les éléments de navigation et boutons */
                .user-info, .navigation-buttons, nav, .navbar, .nav, .nav-link, 
                .navbar-nav, .navbar-brand, .fas, .fa, i[class*="fa"], 
                i[class*="fas"], i[class*="far"], .icon, .glyphicon, 
                [class*="icon-"], [class*="fa-"], .btn, button, .form-group, 
                .alert, h1, h2, .pagination, .mb-3, .row, .col-sm-6, 
                .form-label, .form-control, .form-select, header, 
                .alert-info, #printButton, .collapse, .accordion, .card,
                .btn-group, .btn-sm, .btn-danger, .btn-success, .btn-primary, .btn-info,
                .d-flex, .justify-content-end, .gap-2, .text-center, .mt-4,
                .input-group, .input-group-text, .accordion-button { 
                    display: none !important; 
                    visibility: hidden !important; 
                }
                
                /* Masquer la colonne Action */
                th:last-child, td:last-child {
                    display: none !important;
                }
                
                /* Masquer les détails des ventes */
                [id^="collapseVente"] {
                    display: none !important;
                }
                
                /* Afficher seulement le contenu d'impression */
                body > *:not(#printContent) {
                    display: none !important;
                }
                
                #printContent {
                    display: block !important;
                    position: static !important;
                    left: auto !important;
                    top: auto !important;
                }
            }
        `;
        document.head.appendChild(printStyles);

        // Déclencher l'impression
        setTimeout(() => {
            window.print();
        }, 500);

        // Nettoyer après impression
        setTimeout(() => {
            document.getElementById('printContent').remove();
            if (printStyles.parentNode) printStyles.remove();
        }, 1000);
    }

    // Fonction d'impression pour desktop (utilise window.open() avec fallback)
    function printVentesDesktop() {
        try {
            // Créer une nouvelle fenêtre pour l'impression
            let printWindow = window.open('', '', 'height=600,width=800');

            if (!printWindow) {
                // Si window.open() est bloqué, utiliser la méthode mobile
                printVentesMobile();
                return;
            }

            // Créer le contenu HTML à imprimer (sans la colonne Action)
            let printContent = `
                <html>
                <head>
                    <title>Liste des Ventes</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
                    <style>
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            font-size: 12px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 6px;
                            text-align: left;
                        }
                        th {
                            background-color: #f4f4f4;
                        }
                        .text-center {
                            text-align: center;
                        }
                        .text-end {
                            text-align: right;
                        }
                        @media print {
                            .no-print {
                                display: none !important;
                            }
                            body {
                                font-size: 10px;
                            }
                            /* Masquer la colonne Action */
                            th:last-child, td:last-child {
                                display: none !important;
                            }
                            /* Masquer les détails des ventes */
                            [id^="collapseVente"] {
                                display: none !important;
                            }
                        }
                    </style>
                </head>
                <body>
                    <h2 class="text-center">Liste des Ventes</h2>
                    <p class="text-center"><strong>Page <?php echo $page; ?> sur <?php echo $totalPages; ?> | Total: <?php echo number_format($totalVentes, 0, ',', ' '); ?> ventes</strong></p>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Numéro Vente</th>
                                <th>Total avec remise</th>
                                <th>Montant remise</th>
                                <th>Montant Versé</th>
                                <th>Monnaie Rendu</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${document.querySelector('#table-body').innerHTML}
                        </tbody>
                    </table>
                </body>
                </html>
            `;

            // Écrire le contenu HTML dans la nouvelle fenêtre
            printWindow.document.write(printContent);
            printWindow.document.close();

            // Attendre que le document soit complètement chargé, puis imprimer
            printWindow.onload = function() {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            };
        } catch (error) {
            // En cas d'erreur, utiliser la méthode mobile
            printVentesMobile();
        }
    }

// Fonction pour réimprimer les documents
function reimprimer(numeroVente, type) {
    let url;
    switch(type) {
        case 'ticket':
            url = `print_ticket_caisse.php?numero=${numeroVente}`;
            break;
        case 'facture':
            url = `print_facture_standard.php?numero=${numeroVente}`;
            break;
        case 'facture_tva':
            url = `print_facture_tva.php?numero=${numeroVente}`;
            break;
        default:
            console.error('Type de document non reconnu');
            return;
    }

    // Créer un formulaire temporaire pour l'impression
    const tempForm = document.createElement('form');
    tempForm.method = 'POST';
    tempForm.action = url;
    tempForm.target = '_blank';
    document.body.appendChild(tempForm);

    // Ajouter un champ caché pour le numéro de vente
    const numeroInput = document.createElement('input');
    numeroInput.type = 'hidden';
    numeroInput.name = 'numero';
    numeroInput.value = numeroVente;
    tempForm.appendChild(numeroInput);

    // Soumettre le formulaire
    tempForm.submit();

    // Nettoyer le formulaire temporaire
    setTimeout(() => {
        document.body.removeChild(tempForm);
    }, 1000);
}

    // Fonction pour imprimer sans redirection
function imprimerSansNouvelOnglet(url) {
    const largeur = 800;
    const hauteur = 600;
    const left = (screen.width / 2) - (largeur / 2);
    const top = (screen.height / 2) - (hauteur / 2);

    const fenetre = window.open(url, 'Impression', `width=${largeur},height=${hauteur},top=${top},left=${left}`);

    fenetre.focus();

    // Attendre que le contenu soit chargé avant impression
    fenetre.onload = function () {
        fenetre.print();

        // Fermer après un petit délai pour laisser le temps à l'impression
        setTimeout(() => {
            fenetre.close();
            }, 1500);
    };
}

    // Fonction d'exportation des ventes optimisée
function exportVentes(format = 'excel') {
        const currentUrl = new URL(window.location);
        const search = currentUrl.searchParams.get('search') || '';
        const startDate = currentUrl.searchParams.get('start_date') || '';
        const endDate = currentUrl.searchParams.get('end_date') || '';
    
    let url = `export_listes_ventes.php?format=${format}`;
    
        if (search) {
            url += `&search=${encodeURIComponent(search)}`;
        }
        if (startDate) {
            url += `&start_date=${startDate}`;
        }
        if (endDate) {
            url += `&end_date=${endDate}`;
    }
    
    // Créer un lien temporaire pour le téléchargement
    const link = document.createElement('a');
    link.href = url;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Fonction pour formater un montant
function formatMontant(montant) {
    return new Intl.NumberFormat('fr-FR').format(montant);
}

// Fonction pour récupérer les articles d'une vente
async function getArticlesVente(numeroVente) {
    try {
        const response = await fetch(`get_articles_vente.php?numero=${encodeURIComponent(numeroVente)}`);
        const data = await response.json();
        
        if (data.success && data.articles) {
            let articlesText = '';
            data.articles.forEach(article => {
                articlesText += `${article.libelle} - ${formatMontant(article.prix_unitaire)} FCFA - N°: ${article.numero_serie}\n`;
            });
            
            // Ajouter le total
            articlesText += `\nTOTAL: ${formatMontant(data.vente.montant_total)} FCFA`;
            if (data.vente.montant_remise > 0) {
                articlesText += `\nRemise: -${formatMontant(data.vente.montant_remise)} FCFA`;
            }
        
            return {
                articles: articlesText,
                client: data.client,
                vente: data.vente,
                modes_paiement: data.modes_paiement
            };
        }
        return {
            articles: 'Articles non disponibles',
            client: null,
            vente: null,
            modes_paiement: []
        };
    } catch (error) {
        console.error('Erreur lors de la récupération des articles:', error);
        return {
            articles: 'Erreur lors de la récupération des articles',
            client: null,
            vente: null,
            modes_paiement: []
        };
    }
}

// Fonction pour envoyer SMS
async function envoyerSMS(numeroVente, nomClient, telephone, montant, dateVente) {
    try {
        // Afficher un loader
        Swal.fire({
            title: 'Préparation du SMS...',
            text: 'Récupération des détails de la vente',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Récupérer les articles
        const data = await getArticlesVente(numeroVente);
        
        // Construire le message SMS selon les recommandations
        let message = `Bonjour ${data.client.nom},

Vous avez acheté:

${data.articles}

Merci pour votre confiance !

SOTECH`;

        // Fermer le loader
        Swal.close();

        // Rediriger vers la page SMS avec les données pré-remplies (même onglet)
        const url = `envoyer_sms.php?telephone=${encodeURIComponent(telephone)}&message=${encodeURIComponent(message)}&client=${encodeURIComponent(nomClient)}&numero_vente=${encodeURIComponent(numeroVente)}`;
        window.location.href = url;
        
    } catch (error) {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de préparer le SMS. Veuillez réessayer.',
            confirmButtonText: 'OK'
        });
    }
}

// Fonction pour envoyer Email
async function envoyerEmail(numeroVente, nomClient, telephone, montant, dateVente) {
    try {
        // Afficher un loader
        Swal.fire({
            title: 'Préparation de l\'email...',
            text: 'Récupération des détails de la vente',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Récupérer les articles
        const data = await getArticlesVente(numeroVente);
        
        // Vérifier si le client a un email
        const clientEmail = data.client && data.client.email ? data.client.email : null;
        
        if (!clientEmail) {
            Swal.close();
            Swal.fire({
                icon: 'warning',
                title: 'Email non disponible',
                text: `Le client ${nomClient} n'a pas d'adresse email enregistrée. Voulez-vous utiliser le SMS à la place ?`,
                showCancelButton: true,
                confirmButtonText: 'Oui, envoyer un SMS',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Rediriger vers SMS à la place
                    envoyerSMS(numeroVente, nomClient, telephone, montant, dateVente);
                }
            });
            return;
        }
        
        // Construire le message pour l'email selon les recommandations
        let message = `Confirmation d'achat

Bonjour ${data.client.nom},

Vous avez acheté:

${data.articles}

Merci pour votre confiance !

Cordialement,
L'équipe SOTECH`;

        // Fermer le loader
        Swal.close();

        // Rediriger vers la page d'envoi d'email avec les données pré-remplies (même onglet)
        const url = `envoyer_email.php?email=${encodeURIComponent(clientEmail)}&message=${encodeURIComponent(message)}&client=${encodeURIComponent(nomClient)}&numero_vente=${encodeURIComponent(numeroVente)}`;
        window.location.href = url;
        
    } catch (error) {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de préparer l\'email. Veuillez réessayer.',
            confirmButtonText: 'OK'
        });
    }
}

// Fonction de confirmation de suppression avec SweetAlert
function confirmerSuppression(numeroVente, nomClient) {
    Swal.fire({
        title: '⚠️ Confirmation de suppression',
        html: `
            <div class="text-start">
                <p><strong>Vente N°:</strong> ${numeroVente}</p>
                <p><strong>Client:</strong> ${nomClient}</p>
                <hr>
                <p class="text-danger"><strong>⚠️ ATTENTION:</strong></p>
                <ul class="text-danger">
                    <li>Cette action est <strong>IRRÉVERSIBLE</strong></li>
                    <li>La vente sera <strong>définitivement supprimée</strong></li>
                    <li>Tous les détails associés seront perdus</li>
                    <li>Les numéros de série seront libérés</li>
                </ul>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '<i class="fas fa-trash"></i> Oui, supprimer définitivement',
        cancelButtonText: '<i class="fas fa-times"></i> Annuler',
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: 'swal2-popup-custom',
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Afficher un loader pendant la suppression
            Swal.fire({
                title: 'Suppression en cours...',
                text: 'Veuillez patienter pendant la suppression de la vente',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
        // Créer un formulaire temporaire pour la suppression
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'fonction_traitement/request.php';
        
        // Ajouter les champs cachés
        const numeroInput = document.createElement('input');
        numeroInput.type = 'hidden';
        numeroInput.name = 'numero_vente_suppression';
        numeroInput.value = numeroVente;
        form.appendChild(numeroInput);
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'supprimer_vente';
        form.appendChild(actionInput);
        
        // Ajouter le formulaire au DOM et le soumettre
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    });
}

</script>
</body>
</html>
