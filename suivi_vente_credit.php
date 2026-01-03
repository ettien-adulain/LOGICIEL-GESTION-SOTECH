<?php
// --- Gestion AJAX pour l'historique (DOIT ÊTRE EN PREMIER) ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'get_historique'
) {
    // Nettoyer tout le buffer de sortie
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    include_once('db/connecting.php');
    try {
        if (empty($_POST['IDVenteCredit']) || !is_numeric($_POST['IDVenteCredit'])) {
            throw new Exception('ID de vente invalide');
        }
        $IDVenteCredit = $_POST['IDVenteCredit'];
        $query = "
            SELECT p.*, m.ModeReglement 
            FROM ventes_credit_paiement p 
            LEFT JOIN mode_reglement m ON p.IDMODE_REGLEMENT = m.IDMODE_REGLEMENT 
            WHERE p.IDVenteCredit = ? 
            ORDER BY p.DateIns ASC
        ";
        $stmt = $cnx->prepare($query);
        $stmt->execute([$IDVenteCredit]);
        $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'paiements' => $paiements], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// --- Logique serveur (déplacée après les gestionnaires AJAX) ---

// --- Debug pour voir les données reçues ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['received' => $_POST, 'files' => $_FILES], JSON_UNESCAPED_UNICODE);
    exit();
}

// --- Paiement simple ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['MontantVerse']) && !isset($_POST['action'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    include_once('db/connecting.php');
    
    // Validation des données reçues
    if (!isset($_POST['IDVenteCredit']) || !isset($_POST['MontantVerse'])) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes: IDVenteCredit ou MontantVerse'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $IDVenteCredit = $_POST['IDVenteCredit'];
    $AccompteVerse = $_POST['MontantVerse'];
    $nouveauRestant = $_POST['restant'] ?? 0;
    // Gérer la date et heure de paiement
    if (isset($_POST['DatePaiement']) && !empty($_POST['DatePaiement'])) {
        // Le format datetime-local envoie "YYYY-MM-DDTHH:MM"
        $datePaiement = str_replace('T', ' ', $_POST['DatePaiement']) . ':00';
    } else {
        $datePaiement = date('Y-m-d H:i:s');
    }
    $modePaiement = $_POST['mode_paiement'] ?? 1;
    $venteQuery = "
        SELECT v.NumeroVente, v.MontantTotalCredit, v.RestantAPayer
              FROM ventes_credit v
              WHERE v.IDVenteCredit = ?";
    $stmt = $cnx->prepare($venteQuery);
    $stmt->execute([$IDVenteCredit]);
    $vente = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($vente) {
        // Empêcher un paiement qui dépasse le restant à payer
        if ($AccompteVerse > $vente['RestantAPayer']) {
            echo json_encode(['success' => false, 'message' => 'Le montant versé dépasse le restant à payer.'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $insertPaiement = "
            INSERT INTO `ventes_credit_paiement` (`IDVenteCredit`, `AccompteVerse`, `restant`, `DateIns`, `IDMODE_REGLEMENT`) 
        VALUES (?, ?, ?, ?, ?)";
        $stmt = $cnx->prepare($insertPaiement);
        $stmt->execute([$IDVenteCredit, $AccompteVerse, $nouveauRestant, $datePaiement, $modePaiement]);
        $paiement_id = $cnx->lastInsertId();
        $restantAPayer = $vente['RestantAPayer'] - $AccompteVerse;
        $statut = ($restantAPayer <= 0) ? 'Soldé' : 'En cours';
        $updateSolde = "
            UPDATE `ventes_credit` SET `RestantAPayer` = ?, `AccompteVerse` = `AccompteVerse` + ?, `statut` = ?
        WHERE `IDVenteCredit` = ?";
        $stmt = $cnx->prepare($updateSolde);
        $stmt->execute([$restantAPayer, $AccompteVerse, $statut, $IDVenteCredit]);
        if ($statut === 'Soldé') {
            try {
                $stmt = $cnx->prepare("
                INSERT INTO `vente` 
                (`NumeroVente`, `IDCLIENT`, `MontantTotal`, `MontantRemise`, `MontantVerse`, `Monnaie`, `DateIns`, `Statut`) 
                SELECT `NumeroVente`, `IDCLIENT`, `MontantTotalCredit`, `MontantRemise`, `AccompteVerse`, 
                       GREATEST(0, `AccompteVerse` - `MontantTotalCredit`), `DateMod`, 'Soldé' 
                FROM `ventes_credit` 
                WHERE `IDVenteCredit` = ?");
                $stmt->execute([$IDVenteCredit]);
                $stmt = $cnx->prepare("UPDATE `ventes_credit` SET `statut` = 'Transféré' WHERE `IDVenteCredit` = ?");
                $stmt->execute([$IDVenteCredit]);
            } catch (PDOException $e) {
                error_log("Erreur lors du transfert vers vente : " . $e->getMessage());
            }
        }
        echo json_encode(['success' => true, 'numero_vente' => $vente['NumeroVente'], 'paiement_ids' => [$paiement_id]], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vente introuvable.'], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// --- Multi-paiement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'multi_paiement_suivi') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    include_once('db/connecting.php');
    $cnx->beginTransaction();
    try {
        $IDVenteCredit = $_POST['IDVenteCredit'];
        $paiements = json_decode($_POST['paiements'], true);
        $totalVerse = 0;
        $paiement_ids = [];
        foreach ($paiements as $p) {
            $totalVerse += (float)$p['montant'];
        }
        $venteQuery = "SELECT NumeroVente, RestantAPayer FROM ventes_credit WHERE IDVenteCredit = ?";
        $stmt = $cnx->prepare($venteQuery);
        $stmt->execute([$IDVenteCredit]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$vente) {
            throw new Exception("Vente introuvable.");
        }
        if ($totalVerse > $vente['RestantAPayer']) {
            throw new Exception("Le montant total versé dépasse le restant à payer.");
        }
        $nouveauRestant = $vente['RestantAPayer'] - $totalVerse;
        // Utiliser la date et heure actuelle pour le multi-paiement
        $datePaiement = date('Y-m-d H:i:s');
        foreach ($paiements as $p) {
            $insertPaiement = "INSERT INTO `ventes_credit_paiement` (`IDVenteCredit`, `AccompteVerse`, `restant`, `DateIns`, `IDMODE_REGLEMENT`) VALUES (?, ?, ?, ?, ?)";
            $stmt = $cnx->prepare($insertPaiement);
            $stmt->execute([$IDVenteCredit, (float)$p['montant'], $nouveauRestant, $datePaiement, (int)$p['mode']]);
            $paiement_ids[] = $cnx->lastInsertId();
        }
        $statut = ($nouveauRestant <= 0) ? 'Soldé' : 'En cours';
        $updateSolde = "UPDATE `ventes_credit` SET `RestantAPayer` = ?, `AccompteVerse` = `AccompteVerse` + ?, `statut` = ? WHERE `IDVenteCredit` = ?";
        $stmt = $cnx->prepare($updateSolde);
        $stmt->execute([$nouveauRestant, $totalVerse, $statut, $IDVenteCredit]);
        if ($statut === 'Soldé') {
            try {
                $stmt = $cnx->prepare("
                INSERT INTO `vente` 
                (`NumeroVente`, `IDCLIENT`, `MontantTotal`, `MontantRemise`, `MontantVerse`, `Monnaie`, `DateIns`, `Statut`) 
                SELECT `NumeroVente`, `IDCLIENT`, `MontantTotalCredit`, `MontantRemise`, `AccompteVerse`, 
                       GREATEST(0, `AccompteVerse` - `MontantTotalCredit`), `DateMod`, 'Soldé' 
                FROM `ventes_credit` 
                WHERE `IDVenteCredit` = ?");
                $stmt->execute([$IDVenteCredit]);
                $stmt = $cnx->prepare("UPDATE `ventes_credit` SET `statut` = 'Transféré' WHERE `IDVenteCredit` = ?");
                $stmt->execute([$IDVenteCredit]);
            } catch (PDOException $e) {
                error_log("Erreur lors du transfert vers vente : " . $e->getMessage());
            }
        }
        $cnx->commit();
        echo json_encode(['success' => true, 'numero_vente' => $vente['NumeroVente'], 'paiement_ids' => $paiement_ids], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// --- Suppression d'un paiement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer_paiement') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    include_once('db/connecting.php');
    $IDPaiement = $_POST['IDPaiement'];
    $MontantPaiement = $_POST['MontantPaiement'];
    try {
        $query = "SELECT IDVenteCredit FROM ventes_credit_paiement WHERE IDPaiement = ?";
        $stmt = $cnx->prepare($query);
        $stmt->execute([$IDPaiement]);
        $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($paiement) {
            $IDVenteCredit = $paiement['IDVenteCredit'];
            $venteQuery = "SELECT RestantAPayer, AccompteVerse FROM ventes_credit WHERE IDVenteCredit = ?";
            $stmt = $cnx->prepare($venteQuery);
            $stmt->execute([$IDVenteCredit]);
            $vente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($vente) {
                $deletePaiement = "DELETE FROM ventes_credit_paiement WHERE IDPaiement = ?";
                $stmt = $cnx->prepare($deletePaiement);
                $stmt->execute([$IDPaiement]);
                $nouveauAccompte = $vente['AccompteVerse'] - $MontantPaiement;
                $nouveauRestant = $vente['RestantAPayer'] + $MontantPaiement;
                $statut = $nouveauRestant > 0 ? 'En cours' : 'Soldé';
                $updateVente = "
                    UPDATE ventes_credit 
                    SET RestantAPayer = ?, AccompteVerse = ?, statut = ? 
                    WHERE IDVenteCredit = ?
                ";
                $stmt = $cnx->prepare($updateVente);
                $stmt->execute([$nouveauRestant, $nouveauAccompte, $statut, $IDVenteCredit]);
                echo json_encode(['success' => true, 'message' => 'Paiement supprimé avec succès'], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Vente introuvable'], JSON_UNESCAPED_UNICODE);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Paiement introuvable'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// --- Suppression d'une vente à crédit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer_vente_credit') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    include_once('db/connecting.php');
    $IDVenteCredit = $_POST['IDVenteCredit'];
    if (!is_numeric($IDVenteCredit) || $IDVenteCredit <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de vente invalide'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    try {
        $cnx->beginTransaction();
        
        // 1. Récupérer les informations de la vente
        $venteQuery = "SELECT NumeroVente, IDCLIENT, MontantTotalCredit, statut FROM ventes_credit WHERE IDVenteCredit = ?";
        $stmt = $cnx->prepare($venteQuery);
        $stmt->execute([$IDVenteCredit]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$vente) {
            throw new Exception('Vente à crédit introuvable');
        }
        if ($vente['statut'] === 'Transféré') {
            throw new Exception('Impossible de supprimer une vente déjà transférée vers les ventes normales');
        }

        // 2. Récupérer les articles vendus en comptant directement les numéros de série vendus à crédit
        $articlesQuery = "
            SELECT 
                ns.IDARTICLE,
                COUNT(ns.NUMERO_SERIE) as quantite_reelle_vendue,
                a.libelle,
                s.StockActuel as stock_actuel
            FROM num_serie ns
            JOIN article a ON ns.IDARTICLE = a.IDARTICLE
            JOIN stock s ON a.IDARTICLE = s.IDARTICLE
            WHERE ns.IDvente_credit = ? 
            AND ns.statut = 'vendue_credit'
            GROUP BY ns.IDARTICLE, a.libelle, s.StockActuel
        ";
        $stmt = $cnx->prepare($articlesQuery);
        $stmt->execute([$IDVenteCredit]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Restaurer le stock et libérer les numéros de série
        foreach ($articles as $article) {
            $stock_avant = $article['stock_actuel'];
            $quantite_a_restaurer = $article['quantite_reelle_vendue']; // Utiliser le nombre réel de numéros de série vendus à crédit
            $stock_apres = $stock_avant + $quantite_a_restaurer;
            
            // Vérifier que la quantité à restaurer est cohérente
            if ($quantite_a_restaurer <= 0) {
                throw new Exception("Quantité invalide pour l'article: " . $article['libelle']);
            }
            
            // Restaurer le stock avec les valeurs exactes
            $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?");
            $result = $stmt->execute([$stock_apres, $article['IDARTICLE']]);
            
            if (!$result) {
                throw new Exception("Erreur lors de la restauration du stock pour l'article: " . $article['libelle']);
            }

            // Journaliser la suppression avec les vraies valeurs
            $description = "SUPPRESSION VENTE CRÉDIT - Article: " . $article['libelle'] . 
                          " - Numéros de série restaurés: " . $quantite_a_restaurer . 
                          " - Stock avant: " . $stock_avant . 
                          " - Stock après: " . $stock_apres . 
                          " - Vente: " . $vente['NumeroVente'];
            
            // --- JOURNALISATION : Suppression vente à crédit ---
            if (function_exists('logSystemAction')) {
                logSystemAction(
                    $cnx,
                    'SUPPRESSION_VENTE_CREDIT',
                    'VENTES',
                    'suivi_vente_credit.php',
                    $description,
                    [
                        'id_vente_credit' => $IDVenteCredit,
                        'numero_vente' => $vente['NumeroVente'],
                        'id_article' => $article['IDARTICLE'],
                        'libelle_article' => $article['libelle'],
                        'quantite_restauree' => $quantite_a_restaurer,
                        'stock_avant' => $stock_avant,
                        'stock_apres' => $stock_apres,
                        'numeros_serie_liberes' => $quantite_a_restaurer
                    ],
                    [
                        'action' => 'suppression_vente_credit',
                        'stock_restaure' => true,
                        'numeros_serie_liberes' => true,
                        'articles_concernes' => 1
                    ],
                    'HIGH',
                    'SUCCESS',
                    null
                );
            }
            // --- FIN JOURNALISATION ---
            
            // Log détaillé pour debug
            error_log(sprintf(
                "RESTAURATION STOCK CRÉDIT - Article: %s (ID: %s), Numéros de série: %d, Stock avant: %d, Stock après: %d, Vente: %s",
                $article['libelle'],
                $article['IDARTICLE'],
                $quantite_a_restaurer,
                $stock_avant,
                $stock_apres,
                $vente['NumeroVente']
            ));
        }

        // 4. Libérer les numéros de série vendus à crédit (au lieu de les supprimer)
        $updateNumerosSerie = "UPDATE num_serie SET IDvente_credit = NULL, NumeroVente = NULL, statut = 'disponible' WHERE IDvente_credit = ? AND statut = 'vendue_credit'";
        $stmt = $cnx->prepare($updateNumerosSerie);
        $result = $stmt->execute([$IDVenteCredit]);
        
        // Compter le nombre de numéros de série libérés
        $numeros_liberes = $stmt->rowCount();
        
        if (!$result) {
            throw new Exception("Erreur lors de la libération des numéros de série");
        }

        // 5. Supprimer les données liées
        $deletePaiements = "DELETE FROM ventes_credit_paiement WHERE IDVenteCredit = ?";
        $stmt = $cnx->prepare($deletePaiements);
        $stmt->execute([$IDVenteCredit]);
        
        $deleteLignes = "DELETE FROM ventes_credit_ligne WHERE NumeroVente = ?";
        $stmt = $cnx->prepare($deleteLignes);
        $stmt->execute([$vente['NumeroVente']]);
        
        // NE PAS supprimer les numéros de série - ils ont été libérés ci-dessus
        // $deleteSeries = "DELETE FROM num_serie WHERE NumeroVente = ?"; // SUPPRIMÉ
        
        $deleteVente = "DELETE FROM ventes_credit WHERE IDVenteCredit = ?";
        $stmt = $cnx->prepare($deleteVente);
        $stmt->execute([$IDVenteCredit]);
        
        $cnx->commit();
        
        // --- JOURNALISATION : Suppression complète vente à crédit ---
        $total_articles_restaures = count($articles);
        $description_suppression_complete = sprintf(
            "Suppression complète vente à crédit N°%s - %d articles restaurés - %d numéros de série libérés - Stock et numéros de série restaurés",
            $vente['NumeroVente'],
            $total_articles_restaures,
            $numeros_liberes
        );
        
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'SUPPRESSION_COMPLETE_VENTE_CREDIT',
                'VENTES',
                'suivi_vente_credit.php',
                $description_suppression_complete,
                [
                    'id_vente_credit' => $IDVenteCredit,
                    'numero_vente' => $vente['NumeroVente'],
                    'montant_total' => $vente['MontantTotalCredit'],
                    'statut_vente' => $vente['statut'],
                    'articles_restaures' => $total_articles_restaures,
                    'numeros_serie_liberes' => $numeros_liberes,
                    'paiements_supprimes' => true,
                    'lignes_supprimees' => true
                ],
                [
                    'action' => 'suppression_complete_vente_credit',
                    'stock_restaure' => true,
                    'numeros_serie_liberes' => true,
                    'donnees_vente_supprimees' => true,
                    'paiements_supprimes' => true
                ],
                'CRITICAL',
                'SUCCESS',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        // Message de succès détaillé avec nombre de numéros de série libérés
        $success_message = "✅ Vente à crédit N°" . $vente['NumeroVente'] . " supprimée avec succès. " . $total_articles_restaures . " article(s) restauré(s) et " . $numeros_liberes . " numéro(s) de série marqué(s) comme 'disponible'.";
        
        echo json_encode([
            'success' => true, 
            'message' => $success_message,
            'numero_vente' => $vente['NumeroVente'],
            'articles_restaures' => $total_articles_restaures,
            'numeros_liberes' => $numeros_liberes
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        
        // --- JOURNALISATION : Erreur suppression vente à crédit ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_SUPPRESSION_VENTE_CREDIT',
                'VENTES',
                'suivi_vente_credit.php',
                'Erreur lors de la suppression de la vente à crédit N°' . ($IDVenteCredit ?? 'N/A') . ' : ' . $e->getMessage(),
                [
                    'id_vente_credit' => $IDVenteCredit ?? null,
                    'erreur' => $e->getMessage(),
                    'transaction_rollback' => true
                ],
                null,
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// --- Export ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    include_once('db/connecting.php');
    $format = $_POST['format'] ?? 'excel';
    $query = "
        SELECT 
            vc.IDVenteCredit,
            vc.NumeroVente,
            c.NomPrenomClient,
            vc.MontantTotalCredit,
            vc.AccompteVerse,
            vc.RestantAPayer,
            vc.MontantRemise,
            vc.Monnaie,
            vc.DateIns,
            vc.statut
        FROM ventes_credit vc
        LEFT JOIN client c ON vc.IDCLIENT = c.IDCLIENT
        ORDER BY vc.DateIns DESC
    ";
    $stmt = $cnx->prepare($query);
    $stmt->execute();
    $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    switch ($format) {
        case 'excel':
            exportToExcel($ventes);
            break;
        case 'word':
            exportToWord($ventes);
            break;
        case 'txt':
            exportToTxt($ventes);
            break;
        default:
            exportToExcel($ventes);
    }
    exit();
}

// --- Logique serveur principale (après les gestionnaires AJAX) ---
try {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    include('db/connecting.php');
    include('fonction_traitement/fonction.php');
    require_once 'fonction_traitement/fonction.php';
    check_access();

    $entreprise = verifier_element('entreprise', ['id'], [1], '');
    $ventes = selection_element('ventes_credit');
    $mode_paiement = selection_element('mode_reglement');

    // Vérification que les modes de paiement sont bien chargés
    if (!$mode_paiement || !is_array($mode_paiement)) {
        error_log("Erreur: Impossible de charger les modes de paiement");
        $mode_paiement = [];
    }

    ob_start(); // Démarre la mise en tampon de sortie
    // Gestion des messages de notification
    $message = '';
    $typeMessage = ''; // success, info, error
    $success = '';
    $redirect = false;


    // Pagination et recherche
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // 50 lignes par page par défaut
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $offset = ($page - 1) * $limit;
    
    // Construction des conditions de recherche
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(c.NomPrenomClient LIKE ? OR vc.NumeroVente LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Requête pour compter le total avec recherche
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM ventes_credit vc 
        LEFT JOIN client c ON vc.IDCLIENT = c.IDCLIENT 
        $whereClause
    ";
    $stmt = $cnx->prepare($countQuery);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $totalRows = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRows / $limit);
    
    // Requête paginée pour les ventes avec recherche
    $ventesQuery = "
        SELECT vc.*, c.NomPrenomClient 
        FROM ventes_credit vc 
        LEFT JOIN client c ON vc.IDCLIENT = c.IDCLIENT 
        $whereClause
        ORDER BY vc.DateIns DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $cnx->prepare($ventesQuery);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $th) {
    // Gestion des erreurs
    $erreur = 'Erreur lors de la récupération des données : ' . $th->getMessage();
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    echo json_encode(['success' => false, 'error' => $error]);
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    ob_end_clean(); // Annule la sortie tamponnée en cas d'erreur

    exit();
}
?>

<?php
// Fonctions d'export
function exportToExcel($ventes) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="ventes_credit_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    echo '<tr style="background-color: #4CAF50; color: white; font-weight: bold;">';
    echo '<th>#</th>';
    echo '<th>Client</th>';
    echo '<th>Numéro Vente</th>';
    echo '<th>Montant Total</th>';
    echo '<th>Accompte</th>';
    echo '<th>Reste à Payer</th>';
    echo '<th>Remise</th>';
    echo '<th>Monnaie</th>';
    echo '<th>Date</th>';
    echo '<th>Statut</th>';
    echo '</tr>';
    
    $i = 1;
    foreach ($ventes as $vente) {
        echo '<tr>';
        echo '<td>' . $i . '</td>';
        echo '<td>' . htmlspecialchars($vente['NomPrenomClient']) . '</td>';
        echo '<td>' . htmlspecialchars($vente['NumeroVente']) . '</td>';
        echo '<td>' . number_format($vente['MontantTotalCredit'], 0, ',', ' ') . ' FCFA</td>';
        echo '<td>' . number_format($vente['AccompteVerse'], 0, ',', ' ') . ' FCFA</td>';
        echo '<td>' . number_format($vente['RestantAPayer'], 0, ',', ' ') . ' FCFA</td>';
        echo '<td>' . number_format($vente['MontantRemise'], 2) . ' FCFA</td>';
        echo '<td>' . number_format($vente['Monnaie'], 2) . ' FCFA</td>';
        echo '<td>' . htmlspecialchars($vente['DateIns']) . '</td>';
        echo '<td>' . htmlspecialchars($vente['statut']) . '</td>';
        echo '</tr>';
        $i++;
    }
    echo '</table>';
}

function exportToWord($ventes) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="ventes_credit_' . date('Y-m-d_H-i-s') . '.docx"');
    header('Cache-Control: max-age=0');
    
    $content = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    $content .= '<head><meta charset="utf-8"></head>';
    $content .= '<body>';
    $content .= '<h1 style="text-align: center; color: #2c3e50; font-family: Arial, sans-serif;">Rapport des Ventes à Crédit</h1>';
    $content .= '<p style="text-align: center; color: #7f8c8d; font-family: Arial, sans-serif;">Généré le ' . date('d/m/Y à H:i:s') . '</p>';
    $content .= '<table border="1" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px;">';
    
    // En-têtes
    $content .= '<tr style="background-color: #3498db; color: white; font-weight: bold;">';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">#</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Client</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Numéro Vente</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Montant Total</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Accompte</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Reste à Payer</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Remise</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Monnaie</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Date</th>';
    $content .= '<th style="padding: 8px; border: 1px solid #ddd;">Statut</th>';
    $content .= '</tr>';
    
    $i = 1;
    foreach ($ventes as $vente) {
        $bgColor = ($i % 2 == 0) ? '#f8f9fa' : '#ffffff';
        $content .= '<tr style="background-color: ' . $bgColor . ';">';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $i . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($vente['NomPrenomClient']) . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($vente['NumeroVente']) . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . number_format($vente['MontantTotalCredit'], 0, ',', ' ') . ' FCFA</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . number_format($vente['AccompteVerse'], 0, ',', ' ') . ' FCFA</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . number_format($vente['RestantAPayer'], 0, ',', ' ') . ' FCFA</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . number_format($vente['MontantRemise'], 2) . ' FCFA</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . number_format($vente['Monnaie'], 2) . ' FCFA</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($vente['DateIns']) . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($vente['statut']) . '</td>';
        $content .= '</tr>';
        $i++;
    }
    
    $content .= '</table>';
    
    // Résumé
    $totalVentes = count($ventes);
    $totalMontant = array_sum(array_column($ventes, 'MontantTotalCredit'));
    $totalAccompte = array_sum(array_column($ventes, 'AccompteVerse'));
    $totalRestant = array_sum(array_column($ventes, 'RestantAPayer'));
    
    $content .= '<div style="margin-top: 20px; padding: 15px; background-color: #ecf0f1; border-radius: 5px;">';
    $content .= '<h3 style="color: #2c3e50; margin-bottom: 10px;">Résumé</h3>';
    $content .= '<p><strong>Nombre total de ventes :</strong> ' . $totalVentes . '</p>';
    $content .= '<p><strong>Montant total des ventes :</strong> ' . number_format($totalMontant, 0, ',', ' ') . ' FCFA</p>';
    $content .= '<p><strong>Total des acomptes versés :</strong> ' . number_format($totalAccompte, 0, ',', ' ') . ' FCFA</p>';
    $content .= '<p><strong>Total restant à payer :</strong> ' . number_format($totalRestant, 0, ',', ' ') . ' FCFA</p>';
    $content .= '</div>';
    
    $content .= '</body></html>';
    
    echo $content;
}

function exportToTxt($ventes) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="ventes_credit_' . date('Y-m-d_H-i-s') . '.txt"');
    header('Cache-Control: max-age=0');
    
    $content = "RAPPORT DES VENTES À CRÉDIT\n";
    $content .= "=============================\n";
    $content .= "Généré le " . date('d/m/Y à H:i:s') . "\n\n";
    
    $content .= str_pad("#", 3) . " | ";
    $content .= str_pad("Client", 25) . " | ";
    $content .= str_pad("N° Vente", 12) . " | ";
    $content .= str_pad("Montant Total", 15) . " | ";
    $content .= str_pad("Accompte", 12) . " | ";
    $content .= str_pad("Reste", 12) . " | ";
    $content .= str_pad("Date", 19) . " | ";
    $content .= "Statut\n";
    
    $content .= str_repeat("-", 120) . "\n";
    
    $i = 1;
    foreach ($ventes as $vente) {
        $content .= str_pad($i, 3) . " | ";
        $content .= str_pad(substr($vente['NomPrenomClient'], 0, 23), 25) . " | ";
        $content .= str_pad($vente['NumeroVente'], 12) . " | ";
        $content .= str_pad(number_format($vente['MontantTotalCredit'], 0, ',', ' ') . " F", 15) . " | ";
        $content .= str_pad(number_format($vente['AccompteVerse'], 0, ',', ' ') . " F", 12) . " | ";
        $content .= str_pad(number_format($vente['RestantAPayer'], 0, ',', ' ') . " F", 12) . " | ";
        $content .= str_pad($vente['DateIns'], 19) . " | ";
        $content .= $vente['statut'] . "\n";
        $i++;
    }
    
    $content .= "\n" . str_repeat("=", 120) . "\n";
    $content .= "RÉSUMÉ\n";
    $content .= "=======\n";
    
    $totalVentes = count($ventes);
    $totalMontant = array_sum(array_column($ventes, 'MontantTotalCredit'));
    $totalAccompte = array_sum(array_column($ventes, 'AccompteVerse'));
    $totalRestant = array_sum(array_column($ventes, 'RestantAPayer'));
    
    $content .= "Nombre total de ventes : " . $totalVentes . "\n";
    $content .= "Montant total des ventes : " . number_format($totalMontant, 0, ',', ' ') . " FCFA\n";
    $content .= "Total des acomptes versés : " . number_format($totalAccompte, 0, ',', ' ') . " FCFA\n";
    $content .= "Total restant à payer : " . number_format($totalRestant, 0, ',', ' ') . " FCFA\n";
    
    echo $content;
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
    <title>Suivi des Ventes à Crédit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
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
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        header h2 {
            background: linear-gradient(45deg, #dc3545, #8b0000);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .search-bar {
            max-width: 400px;
            margin: 0 auto;
            border-radius: 25px;
            border: 2px solid #e0e6ed;
            padding: 12px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .search-bar:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
            transform: translateY(-2px);
        }
        
        .table-responsive {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-top: 20px;
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 15px 12px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.1);
        }
        
        .table tbody tr:nth-child(even) {
            background: #fafbfc;
        }
        
        .table tbody tr:nth-child(even):hover {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
        }
        
        .table td {
            padding: 15px 12px;
            vertical-align: middle;
            border: none;
            font-size: 0.95rem;
        }
        
        .badge-status {
            font-size: 0.8rem;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .badge-status:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
            background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        
        .dropdown-menu {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border: none;
            padding: 10px 0;
            animation: slideInDown 0.3s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-item {
            padding: 12px 20px;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 2px 10px;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%);
            color: white;
            transform: translateX(5px);
        }
        
        .modal-content {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%);
            color: white;
            border-bottom: none;
            padding: 20px 30px;
        }
        
        .modal-title {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e0e6ed;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
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
            background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%);
            border: none;
        }
        
        .page-link {
            color: #dc3545;
            border-color: #dee2e6;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%);
            color: white;
            border-color: #dc3545;
            transform: translateY(-2px);
        }
        
        .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #dee2e6;
        }
        
        /* Animations pour les boutons d'action */
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.8rem;
            border-radius: 20px;
            margin: 2px;
            min-width: 80px;
        }
        
        /* Effet de pulsation pour les boutons importants */
        .btn-primary {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4); }
            50% { box-shadow: 0 4px 25px rgba(220, 53, 69, 0.6); }
            100% { box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4); }
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
            
            header h2 {
                font-size: 2.2rem;
            }
            
            .search-bar {
                max-width: 100%;
                font-size: 16px; /* Évite le zoom sur iOS */
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .btn-sm {
                padding: 8px 12px;
                font-size: 0.8rem;
                min-width: 80px;
            }
            
            /* Statistiques en 2x2 sur tablette */
            .row.mb-4 .col-md-3 {
                flex: 0 0 50%;
                max-width: 50%;
                margin-bottom: 15px;
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
            
            header h2 {
                font-size: 1.8rem;
                margin-bottom: 15px;
            }
            
            header p {
                font-size: 0.9rem;
            }
            
            /* Barre de recherche mobile */
            .search-bar {
                max-width: 100%;
                font-size: 16px; /* Évite le zoom sur iOS */
                padding: 12px 15px;
                border-radius: 20px;
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
            .text-white.fw-bold {
                text-align: center;
                font-size: 0.9rem;
            }
            
            /* Statistiques en une colonne sur mobile */
            .row.mb-4 .col-md-3 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 10px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .card-body h5 {
                font-size: 0.9rem;
            }
            
            .card-body h3 {
                font-size: 1.5rem;
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
            
            /* Colonnes du tableau mobile */
            .table th:nth-child(1),
            .table td:nth-child(1) {
                width: 5%;
                min-width: 40px;
            }
            
            .table th:nth-child(2),
            .table td:nth-child(2) {
                width: 20%;
                min-width: 120px;
            }
            
            .table th:nth-child(3),
            .table td:nth-child(3) {
                width: 15%;
                min-width: 100px;
            }
            
            .table th:nth-child(4),
            .table td:nth-child(4) {
                width: 15%;
                min-width: 100px;
            }
            
            .table th:nth-child(5),
            .table td:nth-child(5) {
                width: 15%;
                min-width: 100px;
            }
            
            .table th:nth-child(6),
            .table td:nth-child(6) {
                width: 15%;
                min-width: 100px;
            }
            
            .table th:nth-child(7),
            .table td:nth-child(7) {
                width: 15%;
                min-width: 100px;
            }
            
            .table th:nth-child(8),
            .table td:nth-child(8) {
                width: 10%;
                min-width: 80px;
            }
            
            .table th:nth-child(9),
            .table td:nth-child(9) {
                width: 20%;
                min-width: 150px;
            }
            
            /* Boutons d'action mobile */
            .btn-group-vertical {
                width: 100%;
            }
            
            .btn-sm {
                padding: 6px 10px;
                font-size: 0.7rem;
                min-width: 70px;
                margin: 1px 0;
            }
            
            /* Badges de statut mobile */
            .badge-status {
                font-size: 0.7rem;
                padding: 6px 10px;
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
            
            /* Modals mobile */
            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .form-control,
            .form-select {
                font-size: 16px; /* Évite le zoom sur iOS */
                padding: 10px 12px;
            }
            
            .form-label {
                font-size: 0.9rem;
            }
        }
        
        /* Très petits écrans (moins de 480px) */
        @media (max-width: 480px) {
            .container {
                margin: 2px;
                padding: 10px;
            }
            
            header h2 {
                font-size: 1.5rem;
            }
            
            header p {
                font-size: 0.8rem;
            }
            
            .search-bar {
                padding: 10px 12px;
                font-size: 16px;
            }
            
            .card-body {
                padding: 12px;
            }
            
            .card-body h5 {
                font-size: 0.8rem;
            }
            
            .card-body h3 {
                font-size: 1.3rem;
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
            
            .badge-status {
                font-size: 0.65rem;
                padding: 4px 8px;
            }
            
            .page-link {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .form-control,
            .form-select {
                font-size: 16px;
                padding: 8px 10px;
            }
        }
        
        /* Orientation paysage sur mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .container {
                margin: 5px;
                padding: 10px;
            }
            
            header h2 {
                font-size: 1.6rem;
                margin-bottom: 10px;
            }
            
            .row.mb-4 .col-md-3 {
                flex: 0 0 25%;
                max-width: 25%;
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
            .form-select,
            .search-bar {
                min-height: 44px; /* Taille minimale recommandée pour le tactile */
                touch-action: manipulation;
            }
            
            .table tbody tr {
                min-height: 44px;
            }
            
            .dropdown-item {
                min-height: 44px;
                padding: 12px 20px;
            }
        }
        
        /* Amélioration du scroll tactile */
        .table-responsive,
        .modal-body {
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
        
        /* Effet de loading personnalisé */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Amélioration des notifications */
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
    </style>
</head>
<body>
    <!-- Particules en arrière-plan -->
    <div id="particles-js" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; opacity: 0.3;"></div>
<?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
<div class="container py-4">
    <header class="mb-4">
        <h2 class="text-center">
            <i class="fas fa-chart-line me-3" style="color: #667eea;"></i>
            Suivi des Ventes à Crédit
            <i class="fas fa-chart-line ms-3" style="color: #764ba2;"></i>
        </h2>
        <p class="text-center text-muted mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Gestion complète des ventes à crédit et suivi des paiements
        </p>
    </header>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="position-relative">
            <input type="text" id="searchInput" class="form-control search-bar" placeholder="🔍 Recherche client ou n° vente..." value="<?= htmlspecialchars($search) ?>">
            <div class="position-absolute top-50 end-0 translate-middle-y pe-3">
                <i class="fas fa-search text-muted"></i>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <!-- Sélecteur de lignes par page -->
            <div class="d-flex align-items-center">
                <label class="me-2 text-white fw-bold">Lignes/page:</label>
                <select id="limitSelect" class="form-select form-select-sm" style="width: auto; min-width: 80px;" onchange="changeLimit()">
                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                    <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                </select>
            </div>
            
            <!-- Informations de pagination -->
            <div class="text-white fw-bold">
                <span id="paginationInfo">
                    Page <?= $page ?> sur <?= $totalPages ?> 
                    (<?= number_format($totalRows) ?> lignes total)
                </span>
        </div>
            
            <div class="dropdown d-inline-block">
                <?php echo bouton_action('Exporter', 'suivi_vente_credit', 'exporter', 'btn btn-info dropdown-toggle', 'type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false"'); ?>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" onclick="exportData('excel')"><i class="fas fa-file-excel text-success"></i> Excel (.xls)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportData('word')"><i class="fas fa-file-word text-primary"></i> Word (.docx)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportData('txt')"><i class="fas fa-file-alt text-secondary"></i> Bloc-notes (.txt)</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm" style="background: linear-gradient(135deg, #dc3545 0%, #8b0000 100%); color: white;">
                <div class="card-body">
                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                    <h5 class="card-title">Total Ventes</h5>
                    <h3 class="mb-0"><?= count($ventes) ?></h3>
                    </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                    <div class="card-body">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h5 class="card-title">Soldées</h5>
                    <h3 class="mb-0"><?= count(array_filter($ventes, function($v) { return $v['RestantAPayer'] <= 0; })) ?></h3>
                        </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white;">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h5 class="card-title">En Cours</h5>
                    <h3 class="mb-0"><?= count(array_filter($ventes, function($v) { return $v['RestantAPayer'] > 0; })) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white;">
                <div class="card-body">
                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                    <h5 class="card-title">Total Restant</h5>
                    <h3 class="mb-0"><?= number_format(array_sum(array_column($ventes, 'RestantAPayer')), 0, ',', ' ') ?> F</h3>
                </div>
            </div>
        </div>
                        </div>

    <div class="table-responsive shadow-sm rounded bg-white">
        <div id="loadingIndicator" class="text-center py-4" style="display: none;">
            <div class="spinner-border text-danger" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2 text-muted">Chargement des données...</p>
        </div>
        <table class="table table-hover align-middle mb-0" id="venteTable">
            <thead class="sticky-header">
                                    <tr class="text-center">
                                        <th>#</th>
                                        <th>Client</th>
                                        <th>Numéro Vente</th>
                                        <th>Montant Total</th>
                                        <th>Accompte</th>
                    <th>Reste à Payer</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                    <th>Actions</th>
                                    </tr>
                                </thead>
            <tbody id="venteTableBody">
                                    <?php if (!empty($ventes)): ?>
                    <?php $i = 1; foreach ($ventes as $vente): ?>
                        <?php $client = verifier_element('client', ['IDCLIENT'], [$vente['IDCLIENT']], ''); ?>
                        <tr data-client="<?= htmlspecialchars($client['NomPrenomClient']); ?>" data-numero="<?= htmlspecialchars($vente['NumeroVente']); ?>">
                            <td><?= $i ?></td>
                            <td><?= htmlspecialchars($client['NomPrenomClient']); ?></td>
                            <td><?= htmlspecialchars($vente['NumeroVente']); ?></td>
                            <td><?= number_format($vente['MontantTotalCredit'], 0, ',', ' ') ?> FCFA</td>
                            <td><?= number_format($vente['AccompteVerse'], 0, ',', ' ') ?> FCFA</td>
                            <td><?= number_format($vente['RestantAPayer'], 0, ',', ' ') ?> FCFA</td>
                            <td><?= htmlspecialchars($vente['DateIns']); ?></td>
                            <td>
                                <span class="badge badge-status <?= $vente['RestantAPayer'] <= 0 ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= $vente['RestantAPayer'] <= 0 ? 'Soldé' : 'En cours' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <?php echo bouton_action('Paiement', 'suivi_vente_credit', 'enregistrer', 'btn btn-success btn-sm btn-paiement mb-1', 'data-id="' . $vente['IDVenteCredit'] . '" data-client="' . htmlspecialchars($client['NomPrenomClient']) . '" data-vente="' . htmlspecialchars($vente['NumeroVente']) . '" data-montant="' . htmlspecialchars($vente['MontantTotalCredit']) . '" data-accompte="' . htmlspecialchars($vente['AccompteVerse']) . '" data-reste="' . htmlspecialchars($vente['RestantAPayer']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Effectuer un paiement simple"' . (($vente['RestantAPayer'] <= 0) ? ' disabled' : '')); ?>
                                    <?php echo bouton_action('Multi-Paiement', 'suivi_vente_credit', 'multi_paiement', 'btn btn-warning btn-sm btn-multi-paiement mb-1', 'data-id-vente="' . $vente['IDVenteCredit'] . '" data-client="' . htmlspecialchars($client['NomPrenomClient']) . '" data-vente="' . htmlspecialchars($vente['NumeroVente']) . '" data-montant="' . htmlspecialchars($vente['MontantTotalCredit']) . '" data-accompte="' . htmlspecialchars($vente['AccompteVerse']) . '" data-reste="' . htmlspecialchars($vente['RestantAPayer']) . '" data-montant-restant="' . htmlspecialchars($vente['RestantAPayer']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Effectuer un paiement multiple"' . (($vente['RestantAPayer'] <= 0) ? ' disabled' : '')); ?>
                                    <?php echo bouton_action('Historique', 'suivi_vente_credit', 'voir', 'btn btn-info btn-sm btn-historique mb-1', 'data-id="' . $vente['IDVenteCredit'] . '" data-client="' . htmlspecialchars($client['NomPrenomClient']) . '" data-vente="' . htmlspecialchars($vente['NumeroVente']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Voir l\'historique des paiements"'); ?>
                                    <?php echo bouton_action('Supprimer', 'suivi_vente_credit', 'supprimer', 'btn btn-danger btn-sm btn-supprimer-vente', 'data-id="' . $vente['IDVenteCredit'] . '" data-client="' . htmlspecialchars($client['NomPrenomClient']) . '" data-vente="' . htmlspecialchars($vente['NumeroVente']) . '" data-statut="' . htmlspecialchars($vente['statut']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Supprimer cette vente à crédit"'); ?>
                                </div>
                                                </td>
                                            </tr>
                    <?php $i++; endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center">Aucune vente à crédit trouvée.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
                                                        </div>
    <!-- Pagination robuste -->
    <nav class="mt-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">
                Affichage de <?= number_format($offset + 1) ?> à <?= number_format(min($offset + $limit, $totalRows)) ?> 
                sur <?= number_format($totalRows) ?> résultats
                                                    </div>
            
            <ul class="pagination pagination-lg mb-0">
                <!-- Bouton Première page -->
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1&limit=<?= $limit ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" title="Première page">
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" title="Page précédente">
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
                        <a class="page-link" href="?page=1&limit=<?= $limit ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $totalPages ?>&limit=<?= $limit ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- Bouton Suivant -->
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" title="Page suivante">
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
                        <a class="page-link" href="?page=<?= $totalPages ?>&limit=<?= $limit ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" title="Dernière page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link"><i class="fas fa-angle-double-right"></i></span>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="text-muted">
                <?= $totalPages ?> page<?= $totalPages > 1 ? 's' : '' ?> au total
                                                                </div>
                                                                </div>
    </nav>
                                                                </div>

<!-- Modal Paiement (à remplir dynamiquement en JS) -->
<div class="modal fade" id="paiementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enregistrer un Paiement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
      <div class="modal-body" id="paiementModalBody">
        <!-- Formulaire injecté par JS -->
                                                                </div>
                                                                </div>
                                                            </div>
                                                    </div>

<!-- Modal Historique Paiements -->
<div class="modal fade" id="historiqueModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Historique des Paiements</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
      <div class="modal-body" id="historiqueModalBody">
        <!-- Historique injecté par JS -->
                                                        </div>
                                                    </div>
            </div>
        </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
        <script>
// --- Initialisation des particules ---
particlesJS('particles-js', {
    particles: {
        number: {
            value: 80,
            density: {
                enable: true,
                value_area: 800
            }
        },
        color: {
            value: '#dc3545'
        },
        shape: {
            type: 'circle'
        },
        opacity: {
            value: 0.5,
            random: false
        },
        size: {
            value: 3,
            random: true
        },
        line_linked: {
            enable: true,
            distance: 150,
            color: '#dc3545',
            opacity: 0.4,
            width: 1
        },
        move: {
            enable: true,
            speed: 2,
            direction: 'none',
            random: false,
            straight: false,
            out_mode: 'out',
            bounce: false
        }
    },
    interactivity: {
        detect_on: 'canvas',
        events: {
            onhover: {
                enable: true,
                mode: 'repulse'
            },
            onclick: {
                enable: true,
                mode: 'push'
            },
            resize: true
        }
    },
    retina_detect: true
});

// --- Variables de contrôle d'accès ---
const canView = <?= user_has_access('suivi_vente_credit', 'voir', false) ? 'true' : 'false' ?>;
const canSupprimer = <?= user_has_access('suivi_vente_credit', 'supprimer', false) ? 'true' : 'false' ?>;
const canImprimer = <?= user_has_access('suivi_vente_credit', 'imprimer', false) ? 'true' : 'false' ?>;

// --- Initialisation des tooltips ---
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser tous les tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Animation d'entrée pour les cartes de statistiques
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// --- Fonction pour changer le nombre de lignes par page ---
function changeLimit() {
    // Afficher l'indicateur de chargement
    document.getElementById('loadingIndicator').style.display = 'block';
    document.getElementById('venteTable').style.display = 'none';
    
    const limit = document.getElementById('limitSelect').value;
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('limit', limit);
    currentUrl.searchParams.set('page', '1'); // Retour à la première page
    window.location.href = currentUrl.toString();
}

// --- Recherche avec pagination ---
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const searchTerm = this.value.trim();
    
    // Attendre 500ms après la fin de la frappe pour éviter trop de requêtes
    searchTimeout = setTimeout(() => {
        if (searchTerm.length >= 2 || searchTerm.length === 0) {
            performSearch(searchTerm);
        }
    }, 500);
});

function performSearch(searchTerm) {
    // Afficher l'indicateur de chargement
    document.getElementById('loadingIndicator').style.display = 'block';
    document.getElementById('venteTable').style.display = 'none';
    
    const currentUrl = new URL(window.location);
    if (searchTerm) {
        currentUrl.searchParams.set('search', searchTerm);
    } else {
        currentUrl.searchParams.delete('search');
    }
    currentUrl.searchParams.set('page', '1'); // Retour à la première page lors d'une recherche
    window.location.href = currentUrl.toString();
}

// --- Recherche instantanée locale (pour les petites listes) ---
document.getElementById('searchInput').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#venteTableBody tr');
    
    // Si moins de 100 lignes, faire la recherche locale
    if (rows.length < 100) {
        rows.forEach(row => {
            const client = row.getAttribute('data-client').toLowerCase();
            const numero = row.getAttribute('data-numero').toLowerCase();
            row.style.display = (client.includes(filter) || numero.includes(filter)) ? '' : 'none';
        });
    }
});

// --- Fonction d'export ---
function exportData(format) {
    Swal.fire({
        title: 'Export en cours...',
        text: 'Préparation du fichier ' + format.toUpperCase(),
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('action', 'export');
    formData.append('format', format);
    
    fetch('suivi_vente_credit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Erreur lors de l\'export');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ventes_credit_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.${format === 'excel' ? 'xls' : format === 'word' ? 'docx' : 'txt'}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        Swal.fire({
            icon: 'success',
            title: 'Export réussi !',
            text: 'Le fichier a été téléchargé avec succès.',
            timer: 2000,
            showConfirmButton: false
        });
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur d\'export',
            text: 'Une erreur est survenue lors de l\'export du fichier.'
        });
    });
}

// --- Suppression de vente à crédit ---
document.querySelectorAll('.btn-supprimer-vente').forEach(btn => {
    btn.addEventListener('click', function() {
        const idVente = this.getAttribute('data-id');
        const clientName = this.getAttribute('data-client');
        const venteNumero = this.getAttribute('data-vente');
        const statut = this.getAttribute('data-statut');
        
        // Vérifier si la vente peut être supprimée
        if (statut === 'Transféré') {
            Swal.fire({
                icon: 'warning',
                title: 'Suppression impossible',
                text: 'Cette vente a déjà été transférée vers les ventes normales et ne peut pas être supprimée.'
            });
            return;
        }
        
        Swal.fire({
            title: 'Confirmer la suppression',
            html: `
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                    <p class="mt-3"><strong>Êtes-vous sûr de vouloir supprimer cette vente à crédit ?</strong></p>
                    <div class="alert alert-danger">
                        <strong>Client :</strong> ${clientName}<br>
                        <strong>Vente N° :</strong> ${venteNumero}<br>
                        <strong>Attention :</strong> Cette action supprimera définitivement la vente et tous ses paiements associés !
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
                
                // Envoyer la requête de suppression
                const formData = new FormData();
                formData.append('action', 'supprimer_vente_credit');
                formData.append('IDVenteCredit', idVente);
                
                fetch('suivi_vente_credit.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                            title: 'Suppression réussie !',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                                });
                            } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de suppression',
                            text: data.message
                        });
                            }
                        })
                        .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur réseau',
                        text: 'Une erreur est survenue lors de la communication avec le serveur.'
                    });
                });
            }
                        });
                    });
                });

// --- Paiement Modal ---
document.querySelectorAll('.btn-paiement').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('tr');
        // Récupérer dynamiquement les valeurs du tableau principal (comme dans l'original)
        const clientName = row.querySelector('td:nth-child(2)').textContent.trim();
        const venteNumero = row.querySelector('td:nth-child(3)').textContent.trim();
        const montantTotal = row.querySelector('td:nth-child(4)').textContent.trim();
        const acompte = row.querySelector('td:nth-child(5)').textContent.trim();
        const resteAPayer = row.querySelector('td:nth-child(6)').textContent.trim();
        const idVente = this.getAttribute('data-id');
        
        // Créer le formulaire de paiement
        const formHTML = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Client:</strong> ${clientName}<br>
                    <strong>Vente N°:</strong> ${venteNumero}<br>
                    <strong>Montant Total:</strong> ${montantTotal}
                                            </div>
                <div class="col-md-6">
                    <strong>Accompte Versé:</strong> ${acompte}<br>
                    <strong>Reste à Payer:</strong> <span class="text-danger fw-bold">${resteAPayer}</span>
                                        </div>
                                            </div>
            <form id="paiementForm" method="post">
                <input type="hidden" name="IDVenteCredit" value="${idVente}">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Montant versé *</label>
                        <input type="number" name="MontantVerse" class="form-control" step="0.01" required>
                                            </div>
                    <div class="col-md-6">
                        <label class="form-label">Date de paiement *</label>
                        <input type="datetime-local" name="DatePaiement" class="form-control" value="${new Date().toISOString().slice(0, 16)}" required>
                                        </div>
                                        </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Mode de paiement *</label>
                        <select name="mode_paiement" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <?php if ($mode_paiement && is_array($mode_paiement)): ?>
                                <?php foreach ($mode_paiement as $mode): ?>
                                    <option value="<?= $mode['IDMODE_REGLEMENT'] ?>"><?= htmlspecialchars($mode['ModeReglement']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">Aucun mode de paiement disponible</option>
                            <?php endif; ?>
                        </select>
                                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nouveau solde restant</label>
                        <input type="text" id="nouveauSolde" class="form-control" readonly>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check"></i> Valider le Paiement
                        </button>
                    </div>
                </div>
            </form>
        `;
        
        document.getElementById('paiementModalBody').innerHTML = formHTML;
        
        // Calcul automatique du nouveau solde
        const montantInput = document.querySelector('input[name="MontantVerse"]');
        const resteAPayerValue = parseFloat(resteAPayer.replace(/[^\d.-]/g, ''));
        
        montantInput.addEventListener('input', function() {
            const montantVerse = parseFloat(this.value) || 0;
            const nouveauSolde = resteAPayerValue - montantVerse;
            document.getElementById('nouveauSolde').value = nouveauSolde.toFixed(2) + ' FCFA';
        });
        
        // Gestion du formulaire
        document.getElementById('paiementForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading-spinner me-2"></div>Validation...';
            
            // Ajouter le champ restant au formData
            const montantVerse = parseFloat(formData.get('MontantVerse')) || 0;
            const nouveauRestant = resteAPayerValue - montantVerse;
            formData.append('restant', nouveauRestant.toFixed(2));
            
            // Debug: afficher les données envoyées
            console.log('Données envoyées:', {
                IDVenteCredit: formData.get('IDVenteCredit'),
                MontantVerse: formData.get('MontantVerse'),
                DatePaiement: formData.get('DatePaiement'),
                mode_paiement: formData.get('mode_paiement'),
                restant: formData.get('restant')
            });
             
             fetch(window.location.href, {
                 method: 'POST',
                 body: formData
             })
             .then(response => {
                 console.log('Response status:', response.status);
                 console.log('Response headers:', response.headers);
                 
                 if (!response.ok) {
                     throw new Error(`HTTP error! status: ${response.status}`);
                 }
                 
                 return response.text().then(text => {
                     console.log('Response text:', text);
                     try {
                         return JSON.parse(text);
                     } catch (e) {
                         console.error('JSON parse error:', e);
                         console.error('Response was:', text);
                         throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                     }
                 });
             })
             .then(data => {
                 console.log('Parsed data:', data);
                 if (data.success) {
                                                 Swal.fire({
                                icon: 'success',
                                title: 'Paiement Enregistré !',
                                html: `
                                    <p>Le paiement a été validé.</p>
                                    <p>Options d'impression :</p>
                                    <div class="d-grid gap-2 mt-3">
                                        <button class="btn btn-primary" onclick="imprimerSansNouvelOnglet('print_ticket_caissecredit.php?numero=${data.numero_vente}&paiements=${data.paiement_ids.join(',')}')"><i class="fas fa-receipt"></i> Ticket</button>
                                        <button class="btn btn-secondary" onclick="imprimerSansNouvelOnglet('print_facture_standardcredit.php?numero=${data.numero_vente}&paiements=${data.paiement_ids.join(',')}')"><i class="fas fa-file-invoice"></i> Facture A4</button>
                                        <button class="btn btn-info" onclick="imprimerSansNouvelOnglet('print_facture_tvacredit.php?numero=${data.numero_vente}&paiements=${data.paiement_ids.join(',')}')"><i class="fas fa-file-invoice-dollar"></i> Facture TVA</button>
                                    </div>`,
                                showConfirmButton: false,
                                showCancelButton: true,
                                cancelButtonText: 'Fermer',
                                willClose: () => location.reload()
                            });
                 } else {
                     Swal.fire('Erreur', data.message || 'Un problème est survenu.', 'error');
                 }
             })
             .catch(error => {
                 console.error('Complete error:', error);
                 Swal.fire('Erreur Réseau', 'Erreur: ' + error.message, 'error');
             })
             .finally(() => {
                 submitBtn.disabled = false;
                 submitBtn.innerHTML = '<i class="fas fa-check me-1"></i>Valider le Paiement';
                    });
                });

        new bootstrap.Modal(document.getElementById('paiementModal')).show();
                });
            });

// --- Multi-Paiement Modal ---
document.querySelectorAll('.btn-multi-paiement').forEach(btn => {
    btn.addEventListener('click', function() {
                        const row = this.closest('tr');
        // Récupérer dynamiquement les valeurs du tableau principal (comme dans l'original)
                        const clientName = row.querySelector('td:nth-child(2)').textContent.trim();
                        const venteNumero = row.querySelector('td:nth-child(3)').textContent.trim();
                        const montantTotal = row.querySelector('td:nth-child(4)').textContent.trim();
                        const acompte = row.querySelector('td:nth-child(5)').textContent.trim();
                        const montantRestantInitial = parseFloat(this.getAttribute('data-montant-restant')) || 0;
                        const idVente = this.getAttribute('data-id-vente');

                        let paiements = [];

                        Swal.fire({
                            title: 'Multi-Paiement',
                            html: `
                                <div class="mb-2">
                                    <strong>Client :</strong> ${clientName}<br>
                                    <strong>Vente N° :</strong> ${venteNumero}<br>
                                    <strong>Montant total :</strong> ${montantTotal}<br>
                                    <strong>Acompte déjà versé :</strong> ${acompte}<br>
                                </div>
                                <div class="border p-3">
                                    <h5 id="multi-total-restant" class="text-danger">Restant à payer : ...</h5>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label>Mode de paiement</label>
                                            <select id="multi-mode" class="form-select">
                                                <option value="" disabled selected>Choisir...</option>
                                                <?php foreach ($mode_paiement as $modes): ?>
                                                    <option value="<?= $modes['IDMODE_REGLEMENT'] ?>"><?= $modes['ModeReglement'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Montant</label>
                                            <input type="number" id="multi-montant" class="form-control">
                                        </div>
                                    </div>
                                    <h5 id="multi-total-verse" class="mt-3">Total versé : 0 F</h5>
                                </div>
                                <div class="mt-3" style="max-height: 150px; overflow-y: auto;">
                                    <table id="multi-liste-paiements" class="table table-sm">
                                        <thead><tr><th>Mode</th><th>Montant</th><th>Action</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            `,
            width: '700px',
                            showCancelButton: true,
                            confirmButtonText: 'Ajouter Paiement',
                            cancelButtonText: 'Annuler',
                            showDenyButton: true,
                            denyButtonText: 'Valider les Paiements',
                            denyButtonColor: '#28a745',
                            didOpen: (popup) => {
                function updateModalState(popup) {
                    const totalVerse = paiements.reduce((sum, p) => sum + p.montant, 0);
                    const restantAPayer = montantRestantInitial - totalVerse;

                    popup.querySelector('#multi-total-restant').innerText = `Restant à payer : ${restantAPayer.toLocaleString('fr-FR')} F`;
                    popup.querySelector('#multi-total-verse').innerText = `Total versé : ${totalVerse.toLocaleString('fr-FR')} F`;

                    let listeHtml = '';
                    paiements.forEach((p, index) => {
                        listeHtml += `
                            <tr>
                                <td>${p.modeLibelle}</td>
                                <td>${p.montant.toLocaleString('fr-FR')} F</td>
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="supprimerPaiementModal(${index})">X</button></td>
                            </tr>
                        `;
                    });
                    popup.querySelector('#multi-liste-paiements tbody').innerHTML = listeHtml;
                    
                    const validerBtn = Swal.getDenyButton();
                    if (validerBtn) {
                        validerBtn.disabled = totalVerse <= 0 || totalVerse > montantRestantInitial;
                    }
                }
                
                window.supprimerPaiementModal = (index) => {
                    paiements.splice(index, 1);
                    updateModalState(Swal.getPopup());
                };

                                updateModalState(popup);

                                Swal.getConfirmButton().addEventListener('click', () => {
                                    const modeSelect = popup.querySelector('#multi-mode');
                                    const montantInput = popup.querySelector('#multi-montant');
                                    const mode = modeSelect.value;
                                    const montant = parseFloat(montantInput.value);
                                    
                                    const totalVerse = paiements.reduce((sum, p) => sum + p.montant, 0);

                                    if (!mode || isNaN(montant) || montant <= 0) {
                                        Swal.showValidationMessage('Veuillez sélectionner un mode et entrer un montant valide.');
                                        return;
                                    }
                                    if (montant + totalVerse > montantRestantInitial) {
                                        Swal.showValidationMessage('Le montant total versé ne peut pas dépasser le reste à payer.');
                        return;
                    }

                                    paiements.push({
                                        mode: mode,
                                        modeLibelle: modeSelect.options[modeSelect.selectedIndex].text,
                                        montant: montant
                                    });

                                    montantInput.value = '';
                                    updateModalState(popup);
                                });

                                Swal.getDenyButton().addEventListener('click', () => {
                                    Swal.showLoading();
                    fetch('suivi_vente_credit.php', {
                            method: 'POST',
                                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                        body: new URLSearchParams({
                                            action: 'multi_paiement_suivi',
                                            IDVenteCredit: idVente,
                                            paiements: JSON.stringify(paiements)
                                        })
                        })
                        .then(response => response.json())
                        .then(data => {
                                        if (data.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Paiements Enregistrés !',
                                                html: `
                                                    <p>Les paiements ont été validés.</p>
                                                    <p>Options d'impression :</p>
                                                    <div class="d-grid gap-2 mt-3">
                                                        <button class="btn btn-primary" onclick="imprimerSansNouvelOnglet('print_ticket_caissecredit.php?numero=${data.numero_vente}&paiements=${data.paiement_ids.join(',')}')"><i class="fas fa-receipt"></i> Ticket</button>
                                                        <button class="btn btn-secondary" onclick="imprimerSansNouvelOnglet('print_facture_standardcredit.php?numero=${data.numero_vente}&paiements=${data.paiement_ids.join(',')}')"><i class="fas fa-file-invoice"></i> Facture</button>
                                                        <button class="btn btn-info" onclick="imprimerSansNouvelOnglet('print_facture_tvacredit.php?numero=${data.numero_vente}&paiements=${data.paiement_ids.join(',')}')"><i class="fas fa-file-invoice-dollar"></i> Facture TVA</button>
                                                    </div>`,
                                                showConfirmButton: false,
                                                showCancelButton: true,
                                                cancelButtonText: 'Fermer',
                                                willClose: () => location.reload()
                                            });
                            } else {
                                            Swal.fire('Erreur', data.message || 'Un problème est survenu.', 'error');
                                        }
                                    })
                                    .catch(error => Swal.fire('Erreur Réseau', 'Impossible de communiquer avec le serveur.', 'error'));
                                });
                            },
            preConfirm: () => false
        });
    });
});

// --- Historique Modal ---
document.querySelectorAll('.btn-historique').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!canView) {
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'avez pas l\'autorisation pour voir l\'historique des paiements.'
            });
            return;
        }
        
        const idVente = this.getAttribute('data-id');
        const clientName = this.getAttribute('data-client');
        const venteNumero = this.getAttribute('data-vente');
        
        // Charger l'historique via AJAX
        fetch('suivi_vente_credit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'get_historique',
                IDVenteCredit: idVente
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                                 const historiqueHTML = `
                     <div class="mb-3">
                         <strong>Client:</strong> ${clientName}<br>
                         <strong>Vente N°:</strong> ${venteNumero}
                     </div>
                     <div class="table-responsive">
                         <table class="table table-striped">
                             <thead>
                                 <tr>
                                     <th>Date</th>
                                     <th>Montant Versé</th>
                                     <th>Reste à Payer</th>
                                     <th>Mode de Paiement</th>
                                     <th>Action</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 ${data.paiements.map(p => `
                                     <tr>
                                         <td>${new Date(p.DateIns).toLocaleString('fr-FR', { 
                                             year: 'numeric', 
                                             month: '2-digit', 
                                             day: '2-digit',
                                             hour: '2-digit',
                                             minute: '2-digit',
                                             second: '2-digit'
                                         })}</td>
                                         <td>${parseFloat(p.AccompteVerse).toFixed(2)} FCFA</td>
                                         <td>${parseFloat(p.restant).toFixed(2)} FCFA</td>
                                         <td>${p.ModeReglement}</td>
                                         <td>
                                             ${canSupprimer ? `<button class="btn btn-danger btn-sm" onclick="supprimerPaiementHistorique(${p.IDPaiement}, ${p.AccompteVerse})">
                                                 <i class="fas fa-trash"></i>
                                             </button>` : ''}
                                         </td>
                                     </tr>
                                 `).join('')}
                             </tbody>
                         </table>
                     </div>
                     <div class="text-center mt-3">
                         ${canImprimer ? `<button class="btn btn-success" onclick="imprimerBulletinPaiement('${clientName}', '${venteNumero}', ${JSON.stringify(data.paiements).replace(/"/g, '&quot;')})">
                             <i class="fas fa-print"></i> Imprimer Bulletin de Paiement
                         </button>` : ''}
                     </div>
                 `;
                document.getElementById('historiqueModalBody').innerHTML = historiqueHTML;
            } else {
                document.getElementById('historiqueModalBody').innerHTML = '<div class="text-center text-muted">Aucun historique trouvé.</div>';
            }
            new bootstrap.Modal(document.getElementById('historiqueModal')).show();
        })
        .catch(error => {
            document.getElementById('historiqueModalBody').innerHTML = '<div class="text-center text-danger">Erreur lors du chargement de l\'historique.</div>';
            console.error('Erreur:', error);
        });
    });
});

// --- Fonctions utilitaires ---
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
            setTimeout(() => { fenetre.close(); }, 1500);
        }
    }, 500);
}

function imprimerBulletinPaiement(clientName, venteNumero, paiements) {
    if (!canImprimer) {
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas l\'autorisation pour imprimer des bulletins de paiement.'
        });
        return;
    }
    
    const enterpriseInfo = <?= json_encode($entreprise ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    
    // Calculer les totaux
    const totalVerse = paiements.reduce((sum, p) => sum + parseFloat(p.AccompteVerse), 0);
    const dernierPaiement = paiements[paiements.length - 1];
    const resteAPayer = parseFloat(dernierPaiement.restant);
    
    const printContent = `
        <html>
        <head>
            <title>Bulletin de Paiement - ${venteNumero}</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    font-size: 12px; 
                    background: white;
                }
                .container { 
                    width: 100%; 
                    max-width: 800px; 
                    margin: 0 auto; 
                    border: 2px solid #333; 
                    padding: 25px; 
                    box-shadow: 0 0 10px rgba(0,0,0,0.1); 
                }
                .header { 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: flex-start; 
                    border-bottom: 3px solid #333; 
                    padding-bottom: 15px; 
                    margin-bottom: 25px;
                }
                .header .logo { 
                    font-size: 28px; 
                    font-weight: bold; 
                    color: #333; 
                }
                .header .enterprise-details { 
                    text-align: right; 
                }
                .header .enterprise-details p { 
                    margin: 0; 
                    font-size: 11px;
                }
                .title { 
                    text-align: center; 
                    font-size: 24px; 
                    font-weight: bold; 
                    margin-bottom: 30px; 
                    text-decoration: underline; 
                    text-transform: uppercase; 
                    color: #333;
                }
                .info-section { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 25px; 
                    padding: 15px; 
                    background-color: #f8f9fa; 
                    border-radius: 8px; 
                    border: 1px solid #dee2e6;
                }
                .summary-section { 
                    margin-bottom: 25px; 
                    padding: 15px;
                    background-color: #e9ecef;
                    border-radius: 8px;
                }
                .summary-section table { 
                    width: 100%; 
                    border-collapse: collapse; 
                }
                .summary-section td { 
                    padding: 10px; 
                    border: 1px solid #dee2e6; 
                }
                .summary-section .label { 
                    font-weight: bold; 
                    background-color: #f8f9fa; 
                    width: 40%;
                }
                .history-title { 
                    margin-top: 30px; 
                    font-size: 18px; 
                    border-bottom: 2px solid #333; 
                    padding-bottom: 10px; 
                    color: #333;
                    font-weight: bold;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 15px; 
                }
                th, td { 
                    border: 1px solid #333; 
                    padding: 12px; 
                    text-align: left; 
                }
                th { 
                    background-color: #f4f4f4; 
                    font-weight: bold;
                    text-align: center;
                }
                .total-row {
                    background-color: #e9ecef;
                    font-weight: bold;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px solid #ccc;
                    padding-top: 15px;
                }
                @media print {
                    body { margin: 0; }
                    .container { border: none; box-shadow: none; margin: 0; width: 100%; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">${enterpriseInfo.nom_entreprise || 'Sotech'}</div>
                    <div class="enterprise-details">
                        <p>${enterpriseInfo.adresse || ''}</p>
                        <p>Tél: ${enterpriseInfo.telephone || ''}</p>
                        <p>Email: ${enterpriseInfo.email || ''}</p>
                        <p>${enterpriseInfo.site_web || ''}</p>
                    </div>
                </div>
                
                <div class="title">Bulletin de Paiement</div>
                
                <div class="info-section">
                    <div>
                        <strong>Client:</strong> ${clientName}<br>
                        <strong>Vente N°:</strong> ${venteNumero}<br>
                        <strong>Date d'impression:</strong> ${new Date().toLocaleDateString('fr-FR')}
                    </div>
                    <div>
                        <strong>Statut:</strong> ${resteAPayer <= 0 ? 'Soldé' : 'En cours'}<br>
                        <strong>Nombre de paiements:</strong> ${paiements.length}
                    </div>
                </div>
                
                <div class="summary-section">
                    <h4 style="margin-top: 0; color: #333;">Résumé des Paiements</h4>
                    <table>
                        <tr>
                            <td class="label">Total versé:</td>
                            <td style="font-weight: bold; color: #28a745;">${totalVerse.toLocaleString('fr-FR')} FCFA</td>
                        </tr>
                        <tr>
                            <td class="label">Reste à payer:</td>
                            <td style="font-weight: bold; color: ${resteAPayer <= 0 ? '#28a745' : '#dc3545'};">${resteAPayer.toLocaleString('fr-FR')} FCFA</td>
                        </tr>
                    </table>
                </div>

                <h4 class="history-title">Détail des Versements</h4>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%;">Date</th>
                            <th style="width: 25%;">Montant Versé</th>
                            <th style="width: 25%;">Reste à Payer</th>
                            <th style="width: 30%;">Mode de Paiement</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${paiements.map((p, index) => `
                            <tr>
                                <td style="text-align: center;">${new Date(p.DateIns).toLocaleString('fr-FR', { 
                                    year: 'numeric', 
                                    month: '2-digit', 
                                    day: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    second: '2-digit'
                                })}</td>
                                <td style="text-align: right; font-weight: bold;">${parseFloat(p.AccompteVerse).toLocaleString('fr-FR')} FCFA</td>
                                <td style="text-align: right;">${parseFloat(p.restant).toLocaleString('fr-FR')} FCFA</td>
                                <td style="text-align: center;">${p.ModeReglement}</td>
                            </tr>
                        `).join('')}
                        <tr class="total-row">
                            <td colspan="1" style="text-align: center; font-weight: bold;">TOTAL</td>
                            <td style="text-align: right; font-weight: bold; font-size: 14px;">${totalVerse.toLocaleString('fr-FR')} FCFA</td>
                            <td colspan="2" style="text-align: center; font-weight: bold;">Reste: ${resteAPayer.toLocaleString('fr-FR')} FCFA</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="footer">
                    <p>Ce document certifie les paiements effectués pour la vente ${venteNumero}</p>
                    <p>Imprimé le ${new Date().toLocaleString('fr-FR')}</p>
                </div>
            </div>
        </body>
        </html>
    `;

    const printWindow = window.open("", "PRINT", "height=800,width=900");
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

function supprimerPaiementHistorique(idPaiement, montant) {
    if (!canSupprimer) {
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas l\'autorisation pour supprimer des paiements.'
        });
        return;
    }
    
    Swal.fire({
        title: 'Confirmer la suppression',
        text: `Voulez-vous vraiment supprimer ce paiement de ${montant} FCFA ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('suivi_vente_credit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'supprimer_paiement',
                    IDPaiement: idPaiement,
                    MontantPaiement: montant
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Succès', 'Paiement supprimé avec succès.', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erreur', data.message || 'Erreur lors de la suppression.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erreur Réseau', 'Impossible de communiquer avec le serveur.', 'error');
            });
        }
    });
}

function showNotification(type, message) {
    Swal.fire({ icon: type, text: message, timer: 2500, showConfirmButton: false });
}
        </script>
</body>
</html>