<?php
try {
    session_start();
    include('db/connecting.php');
    include('fonction_traitement/fonction.php');


    require_once 'fonction_traitement/fonction.php';
    check_access();


    $articles = selection_element('article');

    // Récupérer les proformas (avec filtrage si nécessaire)
    $dateFilter = isset($_POST['dateFilter']) ? htmlspecialchars(trim($_POST['dateFilter'])) : '';
    $searchQuery = isset($_POST['searchQuery']) ? htmlspecialchars(trim($_POST['searchQuery'])) : '';

    $filterSql = "SELECT * FROM proforma WHERE 1=1"; // "1=1" pour simplifier l'ajout de conditions
    if ($dateFilter) {
        $filterSql .= " AND DateProforma = :dateFilter";
    }
    if ($searchQuery) {
        $filterSql .= " AND (ClientProforma LIKE :searchQuery OR ContactClientProforma LIKE :searchQuery)";
    }
    $filterSql .= " ORDER BY DateProforma DESC, IDPROFORMA DESC"; // Tri par date décroissante, puis par ID décroissant

    $stmt = $cnx->prepare($filterSql);
    if ($dateFilter) {
        $stmt->bindParam(':dateFilter', $dateFilter);
    }
    if ($searchQuery) {
        $searchQuery = "$searchQuery";
        $stmt->bindParam(':searchQuery', $searchQuery);
    }
    $stmt->execute();
    $proformas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les articles liés à chaque proforma
    $proformaLignes = [];
    foreach ($proformas as $proforma) {
        $query = "SELECT pl.IDARTICLE, a.libelle, pl.Quantite, pl.MontantProduitTTC 
                  FROM proformaligne pl 
                  JOIN article a ON pl.IDARTICLE = a.IDARTICLE 
                  WHERE pl.IDPROFORMA = :idProforma";
        $stmt = $cnx->prepare($query);
        $stmt->bindParam(':idProforma', $proforma['IDPROFORMA']);
        $stmt->execute();
        $proformaLignes[$proforma['IDPROFORMA']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (isset($_POST['ajouter_panier'])) {
        // Debug: Afficher les données reçues
        error_log("=== DEBUG AJOUT PANIER ===");
        error_log("POST data: " . print_r($_POST, true));
        
        if (!isset($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
        }

        $articlesAjoutes = 0;
        $erreurs = [];

        // Vérifier si les données nécessaires sont présentes
        if (!isset($_POST['id_article']) || !isset($_POST['libelle']) || !isset($_POST['MontantProduitTTC']) || !isset($_POST['numeroSerie'])) {
            $erreurs[] = "Données du formulaire manquantes.";
            error_log("Données manquantes: " . print_r($_POST, true));
        } else {
            // Parcours des articles soumis dans le formulaire
            foreach ($_POST['id_article'] as $index => $id_article) {
                $libelle = $_POST['libelle'][$index];
                $prixVenteUnitaire = $_POST['MontantProduitTTC'][$index];
                
                // Vérifier si les numéros de série existent pour cet article
                if (!isset($_POST['numeroSerie'][$id_article])) {
                    $erreurs[] = "Aucun numéro de série fourni pour l'article '$libelle'.";
                    continue;
                }
                
                $numeroSeries = $_POST['numeroSerie'][$id_article]; // Tableau des numéros de série pour cet article
                error_log("Article: $libelle, ID: $id_article, Numéros: " . print_r($numeroSeries, true));

                // Parcourir chaque numéro de série soumis pour cet article
                foreach ($numeroSeries as $numeroSerie) {
                    if (empty(trim($numeroSerie))) {
                        continue; // Ignorer les numéros de série vides
                    }

                    error_log("Vérification numéro de série: $numeroSerie pour article $id_article");

                    // Vérifiez si le numéro de série existe et qu'il est disponible
                    $stmt = $cnx->prepare("
                        SELECT * FROM num_serie 
                        WHERE NUMERO_SERIE = ? 
                        AND statut = 'disponible'
                        AND (ID_VENTE IS NULL OR ID_VENTE = '') 
                        AND (IDvente_credit IS NULL OR IDvente_credit = '')
                    ");
                    $stmt->execute([$numeroSerie]);
                    $num_serie = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($num_serie && $num_serie['IDARTICLE'] == $id_article) {
                        // Ajouter l'article au panier
                        $_SESSION['panier'][$id_article][$numeroSerie] = [
                            'id_article' => $id_article,
                            'libelle' => $libelle,
                            'prixVenteUnitaire' => $prixVenteUnitaire,
                            'quantite' => 1 // Chaque numéro de série correspond à un article unique
                        ];
                        $articlesAjoutes++;
                        error_log("Article ajouté au panier: $libelle avec numéro $numeroSerie");
                    } else {
                        $erreurs[] = "Le numéro de série '$numeroSerie' pour l'article '$libelle' n'est pas disponible ou n'appartient pas à cet article.";
                        error_log("Numéro de série invalide: $numeroSerie pour article $id_article");
                    }
                }
            }
        }

        error_log("Articles ajoutés: $articlesAjoutes, Erreurs: " . count($erreurs));

        // Vérifier si des articles ont été ajoutés
        if ($articlesAjoutes > 0) {
            // Debug: Vérifier l'état de la session
            error_log("Session panier après ajout: " . print_r($_SESSION['panier'], true));
            
            // Vérifier si les headers ont déjà été envoyés
            if (headers_sent($file, $line)) {
                error_log("Headers déjà envoyés depuis $file ligne $line");
                echo '<script>window.location.href = "caisse.php?success=' . urlencode("$articlesAjoutes article(s) ajouté(s) au panier avec succès.") . '";</script>';
                exit();
            } else {
                // Redirection vers la caisse après ajout au panier
                header("Location: caisse.php?success=" . urlencode("$articlesAjoutes article(s) ajouté(s) au panier avec succès."));
                exit();
            }
        } else {
            // Afficher les erreurs et rester sur la page
            $messageErreur = "Aucun article n'a pu être ajouté au panier. Erreurs : " . implode(" ", $erreurs);
            if (headers_sent($file, $line)) {
                error_log("Headers déjà envoyés depuis $file ligne $line");
                echo '<script>alert("' . addslashes($messageErreur) . '"); window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
                exit();
            } else {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($messageErreur));
                exit();
            }
        }
    }
    if (isset($_POST['ajouter_credit'])) {
        // Debug: Afficher les données reçues
        error_log("=== DEBUG AJOUT CREDIT ===");
        error_log("POST data: " . print_r($_POST, true));
        
        if (!isset($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
        }

        $articlesAjoutes = 0;
        $erreurs = [];

        // Vérifier si les données nécessaires sont présentes
        if (!isset($_POST['id_article']) || !isset($_POST['libelle']) || !isset($_POST['MontantProduitTTC']) || !isset($_POST['numeroSerie'])) {
            $erreurs[] = "Données du formulaire manquantes.";
            error_log("Données manquantes: " . print_r($_POST, true));
        } else {
            // Parcours des articles soumis dans le formulaire
            foreach ($_POST['id_article'] as $index => $id_article) {
                $libelle = $_POST['libelle'][$index];
                $prixVenteUnitaire = $_POST['MontantProduitTTC'][$index];
                
                // Vérifier si les numéros de série existent pour cet article
                if (!isset($_POST['numeroSerie'][$id_article])) {
                    $erreurs[] = "Aucun numéro de série fourni pour l'article '$libelle'.";
                    continue;
                }
                
                $numeroSeries = $_POST['numeroSerie'][$id_article]; // Tableau des numéros de série pour cet article
                error_log("Article: $libelle, ID: $id_article, Numéros: " . print_r($numeroSeries, true));

                // Parcourir chaque numéro de série soumis pour cet article
                foreach ($numeroSeries as $numeroSerie) {
                    if (empty(trim($numeroSerie))) {
                        continue; // Ignorer les numéros de série vides
                    }

                    error_log("Vérification numéro de série: $numeroSerie pour article $id_article");

                    // Vérifiez si le numéro de série existe et qu'il est disponible
                    $stmt = $cnx->prepare("
                        SELECT * FROM num_serie 
                        WHERE NUMERO_SERIE = ? 
                        AND statut = 'disponible'
                        AND (ID_VENTE IS NULL OR ID_VENTE = '') 
                        AND (IDvente_credit IS NULL OR IDvente_credit = '')
                    ");
                    $stmt->execute([$numeroSerie]);
                    $num_serie = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($num_serie && $num_serie['IDARTICLE'] == $id_article) {
                        // Ajouter l'article à la session vente_credit
                        $_SESSION['panier'][$id_article][$numeroSerie] = [
                            'id_article' => $id_article,
                            'libelle' => $libelle,
                            'prixVenteUnitaire' => $prixVenteUnitaire,
                            'quantite' => 1 // Chaque numéro de série correspond à un article unique
                        ];
                        $articlesAjoutes++;
                        error_log("Article ajouté au panier: $libelle avec numéro $numeroSerie");
                    } else {
                        $erreurs[] = "Le numéro de série '$numeroSerie' pour l'article '$libelle' n'est pas disponible ou n'appartient pas à cet article.";
                        error_log("Numéro de série invalide: $numeroSerie pour article $id_article");
                    }
                }
            }
        }

        error_log("Articles ajoutés: $articlesAjoutes, Erreurs: " . count($erreurs));

        // Vérifier si des articles ont été ajoutés
        if ($articlesAjoutes > 0) {
            // Debug: Vérifier l'état de la session
            error_log("Session panier après ajout crédit: " . print_r($_SESSION['panier'], true));
            
            // Vérifier si les headers ont déjà été envoyés
            if (headers_sent($file, $line)) {
                error_log("Headers déjà envoyés depuis $file ligne $line");
                echo '<script>window.location.href = "vente_credit.php?success=' . urlencode("$articlesAjoutes article(s) ajouté(s) au panier avec succès.") . '";</script>';
                exit();
            } else {
                // Redirection vers la caisse vente_credit après ajout
                header("Location: vente_credit.php?success=" . urlencode("$articlesAjoutes article(s) ajouté(s) au panier avec succès."));
                exit();
            }
        } else {
            // Afficher les erreurs et rester sur la page
            $messageErreur = "Aucun article n'a pu être ajouté au panier. Erreurs : " . implode(" ", $erreurs);
            if (headers_sent($file, $line)) {
                error_log("Headers déjà envoyés depuis $file ligne $line");
                echo '<script>alert("' . addslashes($messageErreur) . '"); window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
                exit();
            } else {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($messageErreur));
                exit();
            }
        }
    }

    // Récupérer la liste des fournisseurs
    $fournisseur_query = "SELECT*FROM fournisseur";
    $fournisseur_stmt = $cnx->query($fournisseur_query);
    $fournisseurs = $fournisseur_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les informations de l'entreprise
    try {
        $entreprise_query = "SELECT * FROM entreprise WHERE id = 1"; // Vous pouvez adapter l'ID selon votre base de données
        $entreprise_stmt = $cnx->query($entreprise_query);
        $entreprise_info = $entreprise_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur lors de la récupération des informations de l'entreprise: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    if (isset($_POST['deleteLine'])) {
        $idProforma = $_POST['idProforma'];

        try {
            // Récupérer les informations de la proforma AVANT suppression pour la journalisation
            $stmt_info = $cnx->prepare("SELECT * FROM proforma WHERE IDPROFORMA = ?");
            $stmt_info->execute([$idProforma]);
            $proforma_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            if (!$proforma_info) {
                throw new Exception("Proforma introuvable");
            }
            
            // Démarrer une transaction pour garantir l'intégrité
            $cnx->beginTransaction();

            // Supprimer d'abord les lignes de la proforma
            $deleteLignesSql = "DELETE FROM proformaligne WHERE IDPROFORMA = :idProforma";
            $stmtLignes = $cnx->prepare($deleteLignesSql);
            $stmtLignes->bindParam(':idProforma', $idProforma);
            $stmtLignes->execute();

            // Ensuite supprimer la proforma
            $deleteSql = "DELETE FROM proforma WHERE IDPROFORMA = :idProforma";
            $stmt = $cnx->prepare($deleteSql);
            $stmt->bindParam(':idProforma', $idProforma);

            if ($stmt->execute()) {
                // Valider la transaction
                $cnx->commit();
                
                // Préparer les données détaillées pour la journalisation
                $donnees_proforma_supprime = [
                    'proforma_supprime' => [
                        'id' => $idProforma,
                        'client' => $proforma_info['ClientProforma'],
                        'telephone' => $proforma_info['ContactClientProforma'],
                        'email' => $proforma_info['email'],
                        'date_proforma' => $proforma_info['DateProforma'],
                        'total_net_payer' => $proforma_info['TotalNetPayer']
                    ],
                    'operateur' => [
                        'id' => $_SESSION['id_utilisateur'] ?? 0,
                        'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                        'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
                    ]
                ];
                
                // Journalisation suppression proforma (système unifié)
                require_once 'fonction_traitement/fonction.php';
                $startTime = log_action_start();
                $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
                $description_detaille = 'Suppression proforma: Client ' . $proforma_info['ClientProforma'] . ' (Opérateur: ' . $operateur_nom . ') - Total: ' . number_format($proforma_info['TotalNetPayer'], 0, ',', ' ') . ' FCFA';
                
                logSystemAction($cnx, 'SUPPRESSION_PROFORMA', 'PROFORMA', 'liste_proforma.php', 
                    $description_detaille, 
                    null, $donnees_proforma_supprime, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
                
                // Redirection avec fallback JavaScript
                if (!headers_sent()) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?success=' . urlencode('Proforma supprimée avec succès'));
                    exit();
                } else {
                    echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?success=' . urlencode('Proforma supprimée avec succès') . '";</script>';
                    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?success=' . urlencode('Proforma supprimée avec succès') . '">';
                    exit();
                }
            } else {
                // Annuler la transaction en cas d'erreur
                $cnx->rollBack();
                throw new Exception("Erreur lors de la suppression de la proforma");
            }
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($cnx->inTransaction()) {
                $cnx->rollBack();
            }
            
            // Gérer l'erreur avec fallback JavaScript
            if (!headers_sent()) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode('Erreur lors de la suppression : ' . $e->getMessage()));
                exit();
            } else {
                echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '?error=' . urlencode('Erreur lors de la suppression : ' . $e->getMessage()) . '";</script>';
                echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?error=' . urlencode('Erreur lors de la suppression : ' . $e->getMessage()) . '">';
                exit();
            }
        }
    }
} catch (Exception $e) {
    echo '<script>alert("Erreur : ' . htmlspecialchars($e->getMessage()) . '");</script>';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture Proforma</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #ff0000;
            color: white;
            padding: 15px;
            text-align: center;
            width: 100%;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .form-container {
            background-color: #ffffff;
            /* Fond blanc */
            border: 1px solid #dee2e6;
            /* Bordure gris clair */
        }

        input[type="date"] {
            background-color: #f8f9fa;
            /* Fond gris clair pour le champ date */
        }

        .btn-primary {
            background-color: #007bff;
            /* Couleur du bouton */
            border-color: #007bff;
            /* Bordure du bouton */
        }

        .btn-primary:hover {
            background-color: #0056b3;
            /* Couleur du bouton au survol */
            border-color: #0056b3;
            /* Bordure du bouton au survol */
        }

        .cart-container {
            background-color: #f9f9f9;
            /* Couleur d'arrière-plan douce */
            border: 1px solid #ddd;
            border-radius: 10px;
            /* Bordures arrondies */
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            /* Ombre douce */
        }

        .cart-container h2 {
            color: #333;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            font-size: 24px;
        }

        .cart-container table {
            width: 100%;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
        }

        .cart-container th,
        .cart-container td {
            text-align: center;
            padding: 15px;
            font-size: 16px;
        }

        .cart-container th {
            background-color: #343a40;
            color: #fff;
            font-weight: 600;
        }

        .btn-navigation {
            background-color: #ff0000;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn-navigation:hover {
            background-color: #cc0000;
            text-decoration: none;
            color: white;
        }

        .navigation-links {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .cart-container tbody tr:nth-child(odd) {
            background-color: #f1f1f1;
            /* Couleur pour les lignes impaires */
        }

        .cart-container tbody tr:hover {
            background-color: #e9ecef;
            /* Couleur au survol */
            transition: background-color 0.3s ease;
        }

        .cart-container .btn {
            font-size: 14px;
            padding: 10px 15px;
            margin: 5px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .cart-container .btn-danger {
            background-color: #dc3545;
            color: #fff;
            border: none;
        }

        .cart-container .btn-danger:hover {
            background-color: #c82333;
        }

        .cart-container .btn-success {
            background-color: #28a745;
            color: #fff;
            border: none;
        }

        .cart-container .btn-success:hover {
            background-color: #218838;
        }

        .fas {
            margin-right: 8px;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>

    <header>
        <h1 class=" p-4"><i class="fas fa-file-invoice"></i> Facture Proforma</h1>
    </header>
    <form method="POST" class="form-container mb-4 p-4 border rounded shadow">
        <br><br>
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
        <h2 class="text-center mb-4">Filtrer les Proformas</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="dateFilter" class="font-weight-bold">Date de Proforma:</label>
                    <input type="date" id="dateFilter" name="dateFilter" class="form-control" value="<?= $dateFilter ?>">
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group">
                    <label for="searchQuery" class="font-weight-bold">Recherche par Client:</label>
                    <div class="input-group">
                        <input type="text" id="searchQuery" name="searchQuery" class="form-control" placeholder="Nom ou Contact" value="<?= $searchQuery ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-block">Filtrer</button>
            </div>
        </div>
    </form>

    <main class="container">
        <div>
            <div class="mb-3">
                <label for="rowsPerPageSelect" class="form-label">Lignes par page:</label>
                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="15">15</option>
                    <option value="20">20</option>
                    <option value="liste_complete">liste complète</option>
                </select>
            </div>
            <section id="proforma-list">
                <div class="cart-container">
                    <h2><i class="fas fa-list"></i> Liste des Proformas</h2>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th>Adresse Email</th>
                                <th>Date</th>
                                <th>Conditions</th>
                                <th>Total Proforma</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <?php foreach ($proformas as $proforma): ?>
                                <tr class="clickable-row" data-id="<?= $proforma['IDPROFORMA']; ?>" data-toggle="collapse" data-target="#details-<?= $proforma['IDPROFORMA']; ?>">
                                    <td><?= htmlspecialchars($proforma['ClientProforma']); ?></td>
                                    <td><?= htmlspecialchars($proforma['ContactClientProforma']); ?></td>
                                    <td><?= htmlspecialchars($proforma['email']); ?></td>
                                    <td><?= htmlspecialchars($proforma['DateProforma']); ?></td>
                                    <td>
                                        <?php
                                        $ModeReglement = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$proforma['ConditionReglement']], '');
                                        echo htmlspecialchars($ModeReglement['ModeReglement']); ?>
                                    </td>
                                    <td><?= number_format($proforma['TotalNetPayer'], 0, ',', ' '); ?> F.CFA</td>
                                    <td>
                                        <?php if (can_user('liste_proforma', 'imprimer')) : ?>
                                            <a href="impression_proformat.php?id=<?php echo $proforma['IDPROFORMA']; ?>&action=print" class="btn btn-primary">
                                                <i class="fas fa-print"></i> 
                                            </a>
                                            <a href="impression_proformat.php?id=<?php echo $proforma['IDPROFORMA']; ?>&action=download" class="btn btn-success">
                                                <i class="fas fa-file-pdf"></i> 
                                            </a>
                                        <?php else : ?>
                                            <button class="btn btn-primary" disabled title="Accès refusé">
                                                <i class="fas fa-print"></i> 
                                            </button>
                                            <button class="btn btn-success" disabled title="Accès refusé">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (can_user('liste_proforma', 'supprimer')) : ?>
                                            <button type="button" class="btn btn-danger btn-sm btn-delete-proforma" 
                                                    data-id="<?= $proforma['IDPROFORMA']; ?>" 
                                                    data-client="<?= htmlspecialchars($proforma['ClientProforma']); ?>" 
                                                    data-date="<?= htmlspecialchars($proforma['DateProforma']); ?>"
                                                    data-total="<?= number_format($proforma['TotalNetPayer'], 0, ',', ' '); ?>">
                                                <i class="fas fa-trash"></i> 
                                            </button>
                                        <?php else : ?>
                                            <button class="btn btn-danger btn-sm" disabled title="Accès refusé">
                                                <i class="fas fa-trash"></i> 
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr id="details-<?= $proforma['IDPROFORMA']; ?>" class="collapse">
                                    <td colspan="6">
                                        <table class="table details-table">
                                            <thead>
                                                <tr>
                                                    <th>Article</th>
                                                    <th>Quantité</th>
                                                    <th>Prix</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($proformaLignes[$proforma['IDPROFORMA']] as $ligne): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($ligne['libelle']); ?></td>
                                                        <td><?= intval($ligne['Quantite']); ?></td>
                                                        <td><?= number_format($ligne['MontantProduitTTC'], 0, ',', ' '); ?> F.CFA</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <div style="text-align:center;">
                                            <?php if (can_user('liste_proforma', 'envoyer')) : ?>
                                                <a href="envoyer_sms.php?telephone=<?= urlencode($proforma['ContactClientProforma']) ?>&client=<?= urlencode($proforma['ClientProforma']) ?>&articles=<?= urlencode(json_encode($proformaLignes[$proforma['IDPROFORMA']])) ?>&total=<?= urlencode($proforma['TotalNetPayer']) ?>" class="btn btn-warning rounded-pill px-3">
                                                    <i class="fas fa-sms"></i> SMS
                                                </a>
                                                <a href="envoyer_email.php?email=<?= urlencode($proforma['email']) ?>&client=<?= urlencode($proforma['ClientProforma']) ?>&articles=<?= urlencode(json_encode($proformaLignes[$proforma['IDPROFORMA']])) ?>&total=<?= urlencode($proforma['TotalNetPayer']) ?>" class="btn btn-info rounded-pill px-3">
                                                    <i class="fas fa-envelope"></i> Envoyer Email
                                                </a>
                                            <?php else : ?>
                                                <button class="btn btn-warning rounded-pill px-3" disabled title="Accès refusé">
                                                    <i class="fas fa-sms"></i> SMS
                                                </button>
                                                <button class="btn btn-info rounded-pill px-3" disabled title="Accès refusé">
                                                    <i class="fas fa-envelope"></i> Envoyer Email
                                                </button>
                                            <?php endif; ?>
                                            <?php if (can_user('liste_proforma', 'transformer')) : ?>
                                                <button class="btn btn-success rounded-pill px-3" data-toggle="modal" data-target="#serialModal-<?= $proforma['IDPROFORMA'] ?>">
                                                    <i class="fas fa-exchange-alt"></i> Transformer En Vente
                                                </button>
                                                <button class="btn btn-danger rounded-pill px-3" data-toggle="modal" data-target="#creditModal-<?= $proforma['IDPROFORMA'] ?>">
                                                    <i class="fas fa-credit-card"></i> Transformer En Vente À Crédit
                                                </button>
                                            <?php else : ?>
                                                <button class="btn btn-success rounded-pill px-3" disabled title="Accès refusé">
                                                    <i class="fas fa-exchange-alt"></i> Transformer En Vente
                                                </button>
                                                <button class="btn btn-danger rounded-pill px-3" disabled title="Accès refusé">
                                                    <i class="fas fa-credit-card"></i> Transformer En Vente À Crédit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php foreach ($proformas as $proforma): ?>
                            <!-- Définition de la modale spécifique pour chaque proforma -->
                            <div class="modal fade" id="serialModal-<?= $proforma['IDPROFORMA']; ?>" role="dialog" aria-labelledby="serialModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content shadow-lg border-0">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title" id="serialModalLabel">Ajout des Numéros de Série</h5>
                                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Formulaire pour saisir les numéros de série -->
                                            <form method="POST">
                                                <input type="hidden" name="proforma_id" value="<?= $proforma['IDPROFORMA']; ?>">
                                                <?php foreach ($proformaLignes[$proforma['IDPROFORMA']] as $ligne): ?>
                                                    <input type="hidden" name="libelle[]" value="<?= htmlspecialchars($ligne['libelle']); ?>">
                                                    <input type="hidden" name="id_article[]" value="<?= $ligne['IDARTICLE']; ?>">
                                                    <input type="hidden" name="MontantProduitTTC[]" value="<?= $ligne['MontantProduitTTC']; ?>">

                                                    <h6 class="font-weight-bold my-3"><?= htmlspecialchars($ligne['libelle']); ?> (Quantité: <?= $ligne['Quantite']; ?>)</h6>
                                                    <?php for ($i = 1; $i <= $ligne['Quantite']; $i++): ?>
                                                        <div class="form-group">
                                                            <label for="numeroSerie-<?= $ligne['IDARTICLE'] . '-' . $i; ?>" class="font-weight-semibold">Numéro de Série <?= $i; ?></label>
                                                            <input type="text" class="form-control" id="numeroSerie-<?= $ligne['IDARTICLE'] . '-' . $i; ?>" name="numeroSerie[<?= $ligne['IDARTICLE']; ?>][]" required>
                                                        </div>
                                                    <?php endfor; ?>
                                                <?php endforeach; ?>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-primary" name="ajouter_panier">Ajouter au Panier</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>



                        <?php foreach ($proformas as $proforma): ?>
                            <!-- Modal pour Vente à Crédit -->
                            <div class="modal fade" id="creditModal-<?= $proforma['IDPROFORMA']; ?>" role="dialog" aria-labelledby="creditModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content shadow-lg border-0">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title" id="creditModalLabel">Ajout des Numéros de Série - Vente à Crédit</h5>
                                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Formulaire pour saisir les numéros de série -->
                                            <form method="POST">
                                                <input type="hidden" name="proforma_id" value="<?= $proforma['IDPROFORMA']; ?>">
                                                <?php foreach ($proformaLignes[$proforma['IDPROFORMA']] as $ligne): ?>
                                                    <input type="hidden" name="libelle[]" value="<?= htmlspecialchars($ligne['libelle']); ?>">
                                                    <input type="hidden" name="id_article[]" value="<?= $ligne['IDARTICLE']; ?>">
                                                    <input type="hidden" name="MontantProduitTTC[]" value="<?= $ligne['MontantProduitTTC']; ?>">

                                                    <h6 class="font-weight-bold my-3"><?= htmlspecialchars($ligne['libelle']); ?> (Quantité: <?= $ligne['Quantite']; ?>)</h6>
                                                    <?php for ($i = 1; $i <= $ligne['Quantite']; $i++): ?>
                                                        <div class="form-group">
                                                            <label for="numeroSerie-<?= $ligne['IDARTICLE'] . '-' . $i; ?>" class="font-weight-semibold">Numéro de Série <?= $i; ?></label>
                                                            <input type="text" class="form-control" id="numeroSerie-<?= $ligne['IDARTICLE'] . '-' . $i; ?>" name="numeroSerie[<?= $ligne['IDARTICLE']; ?>][]" required>
                                                        </div>
                                                    <?php endfor; ?>
                                                <?php endforeach; ?>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-danger" name="ajouter_credit">Ajouter en Vente À Crédit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </table>
                    <ul id="pagination" class="pagination justify-content-center"></ul>
                </div>
            </section>
    </main>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const rowsPerPageSelect = document.getElementById("rowsPerPageSelect");
            const tableBody = document.getElementById("table-body");
            const rows = Array.from(tableBody.getElementsByTagName("tr")); // Récupère toutes les lignes du tableau
            let rowsPerPage = parseInt(rowsPerPageSelect.value, 10); // Nombre de lignes à afficher par page
            let currentPage = 1; // Page actuelle

            // Fonction pour afficher une page spécifique en fonction du nombre de lignes par page
            function displayPage(page) {
                // Si l'option "liste complète" est sélectionnée, afficher toutes les lignes sans pagination
                if (rowsPerPageSelect.value === "liste_complete") {
                    rows.forEach((row) => {
                        row.style.display = ""; // Afficher toutes les lignes
                    });
                } else {
                    // Sinon, afficher uniquement les lignes correspondant à la page actuelle
                    const start = (page - 1) * rowsPerPage; // Index de la première ligne à afficher
                    const end = Math.min(start + rowsPerPage, rows.length); // Index de la dernière ligne à afficher
                    rows.forEach((row, index) => {
                        row.style.display = (index >= start && index < end) ? "" : "none"; // Afficher ou masquer la ligne
                    });
                }
            }

            // Fonction pour configurer la pagination (affichage des boutons de navigation)
            function setupPagination() {
                const totalPages = Math.ceil(rows.length / rowsPerPage); // Calcul du nombre total de pages
                const pagination = document.getElementById("pagination");
                pagination.innerHTML = ""; // Réinitialiser la pagination

                // Création du bouton "Précédent"
                let prevItem = document.createElement("li");
                prevItem.className = "page-item";
                let prevLink = document.createElement("a");
                prevLink.href = "#";
                prevLink.textContent = "Précédent";
                prevLink.className = "page-link";
                prevLink.addEventListener("click", function(event) {
                    event.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        displayPage(currentPage); // Afficher la page précédente
                    }
                });
                prevItem.appendChild(prevLink);
                pagination.appendChild(prevItem);

                // Création des boutons pour chaque page
                for (let i = 1; i <= totalPages; i++) {
                    let pageItem = document.createElement("li");
                    pageItem.className = "page-item";
                    if (i === currentPage) pageItem.classList.add("active"); // Marquer la page active
                    let pageLink = document.createElement("a");
                    pageLink.href = "#";
                    pageLink.textContent = i;
                    pageLink.className = "page-link";
                    pageLink.addEventListener("click", function(event) {
                        event.preventDefault();
                        currentPage = i;
                        displayPage(currentPage); // Afficher la page correspondante
                    });
                    pageItem.appendChild(pageLink);
                    pagination.appendChild(pageItem);
                }

                // Création du bouton "Suivant"
                let nextItem = document.createElement("li");
                nextItem.className = "page-item";
                let nextLink = document.createElement("a");
                nextLink.href = "#";
                nextLink.textContent = "Suivant";
                nextLink.className = "page-link";
                nextLink.addEventListener("click", function(event) {
                    event.preventDefault();
                    if (currentPage < totalPages) {
                        currentPage++;
                        displayPage(currentPage); // Afficher la page suivante
                    }
                });
                nextItem.appendChild(nextLink);
                pagination.appendChild(nextItem);
            }

            // Événement lors du changement de valeur dans le sélecteur de lignes par page
            rowsPerPageSelect.addEventListener("change", function() {
                if (rowsPerPageSelect.value === "liste_complete") {
                    // Si "liste complète" est sélectionné, afficher toutes les lignes et masquer la pagination
                    rows.forEach((row) => {
                        row.style.display = ""; // Afficher toutes les lignes
                    });
                    document.getElementById("pagination").innerHTML = ""; // Masquer la pagination
                } else {
                    // Sinon, afficher la pagination et afficher la première page
                    rowsPerPage = parseInt(rowsPerPageSelect.value, 10);
                    displayPage(1); // Afficher la première page
                    setupPagination(); // Configurer la pagination
                }
            });

            // Affichage initial de la page avec la pagination
            displayPage(currentPage);
            setupPagination();
        });

        
//PERMET DIMPRIMER SANS REDIRECTION
function imprimerSansNouvelOnglet(url) {
    const largeur = 800;
    const hauteur = 600;
    const left = (screen.width / 2) - (largeur / 2);
    const top = (screen.height / 2) - (hauteur / 2);

    const fenetre = window.open(url, '_blank', `width=${largeur},height=${hauteur},top=${top},left=${left}`);

    if (!fenetre) {
        alert("Le pop-up a été bloqué. Veuillez autoriser les fenêtres pop-up.");
        return;
    }

    const timer = setInterval(() => {
        if (fenetre.document.readyState === 'complete') {
            clearInterval(timer);
            fenetre.focus();
            fenetre.print();
            setTimeout(() => {
                fenetre.close();
            }, 1500);
        }
    }, 500);
}

    function telechargerProforma(id) {
        var url = 'impression_proformat.php?id=' + encodeURIComponent(id) + '&action=download';
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);
        setTimeout(function() {
            document.body.removeChild(iframe);
        }, 5000);
    }

    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <script>
        // --- Gestion des boutons de suppression avec SweetAlert2 ---
        document.addEventListener("DOMContentLoaded", function() {
            // Gestion des boutons de suppression avec SweetAlert2
            document.querySelectorAll('.btn-delete-proforma').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const client = this.getAttribute('data-client');
                    const date = this.getAttribute('data-date');
                    const total = this.getAttribute('data-total');
                    
                    Swal.fire({
                        title: 'Confirmer la suppression',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                                <p class="mt-3"><strong>Êtes-vous sûr de vouloir supprimer cette proforma ?</strong></p>
                                <div class="alert alert-danger">
                                    <strong>Client :</strong> ${client}<br>
                                    <strong>Date :</strong> ${date}<br>
                                    <strong>Total :</strong> ${total} FCFA<br>
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
                            
                            const inputId = document.createElement('input');
                            inputId.type = 'hidden';
                            inputId.name = 'idProforma';
                            inputId.value = id;
                            
                            const inputDelete = document.createElement('input');
                            inputDelete.type = 'hidden';
                            inputDelete.name = 'deleteLine';
                            inputDelete.value = '1';
                            
                            form.appendChild(inputId);
                            form.appendChild(inputDelete);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });

            // Gestion des messages de succès/erreur
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
        });
    </script>
</body>

</html>