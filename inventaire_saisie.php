<?php
// Configuration pour Hostinger
error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs en production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Gestion des erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error inventaire_saisie: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        http_response_code(500);
        echo "Une erreur s'est produite. Veuillez réessayer plus tard.";
        exit;
    }
});

try {
    session_start();
    
    if (!file_exists('db/connecting.php')) {
        throw new Exception("Fichier connecting.php introuvable");
    }
    include('db/connecting.php');
    
    if (!file_exists('fonction_traitement/fonction.php')) {
        throw new Exception("Fichier fonction.php introuvable");
    }
    require_once 'fonction_traitement/fonction.php';
    
    // Vérification de la connexion à la base de données
    if (!isset($cnx) || $cnx === null) {
        throw new Exception("Connexion à la base de données échouée");
    }
    
} catch (Exception $e) {
    error_log("Erreur de connexion inventaire_saisie.php : " . $e->getMessage());
    http_response_code(500);
    echo "Erreur de connexion : " . $e->getMessage();
    exit;
}

// Vérifier que l'ID inventaire est fourni avec gestion d'erreurs
try {
    if (!isset($_GET['IDINVENTAIRE']) || empty($_GET['IDINVENTAIRE'])) {
        throw new Exception("ID inventaire manquant");
    }

    $idInventaire = intval($_GET['IDINVENTAIRE']);
    if ($idInventaire <= 0) {
        throw new Exception("ID inventaire invalide");
    }

    // Vérifier que l'utilisateur est connecté
    if (!isset($_SESSION['id_utilisateur']) || empty($_SESSION['id_utilisateur'])) {
        throw new Exception("Session utilisateur manquante");
    }
    
} catch (Exception $e) {
    error_log("Erreur validation inventaire_saisie: " . $e->getMessage());
    if ($e->getMessage() === "Session utilisateur manquante") {
        header('Location: connexion.php?error=Session expirée');
    } else {
        header('Location: inventaire_liste.php?error=' . urlencode($e->getMessage()));
    }
    exit();
}

// Charger les données temporaires existantes avec gestion d'erreurs
$tempData = [];
try {
    $stmt = $cnx->prepare("SELECT * FROM inventaire_temp WHERE id_inventaire = ? AND id_utilisateur = ?");
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête inventaire_temp");
    }
    $stmt->execute([$idInventaire, $_SESSION['id_utilisateur']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tempData[$row['id_article']] = $row;
    }
} catch (Exception $e) {
    error_log("Erreur chargement données temporaires : " . $e->getMessage());
    $tempData = [];
}

foreach ($tempData as $idArticle => &$ligne) {
    try {
        $stmtSeries = $cnx->prepare("SELECT numero_serie FROM inventaire_temp_series WHERE id_inventaire_temp = ?");
        if (!$stmtSeries) {
            throw new Exception("Erreur de préparation de la requête inventaire_temp_series");
        }
        $stmtSeries->execute([$ligne['id']]);
        $series = [];
        while ($serieRow = $stmtSeries->fetch(PDO::FETCH_ASSOC)) {
            $series[] = $serieRow['numero_serie'];
        }
        $ligne['num_series'] = $series;
    } catch (Exception $e) {
        error_log("Erreur chargement séries temporaires : " . $e->getMessage());
        $ligne['num_series'] = [];
    }
}
unset($ligne);

// Debug temporaire désactivé pour la production
// error_log(print_r($tempData, true));

// Fonction pour sauvegarder les données temporaires
function saveTempData($cnx, $idInventaire, $idArticle, $qtePhysique, $numSeries) {
    try {
        $cnx->beginTransaction();
        
        // Log des paramètres
        error_log("Sauvegarde temporaire - Paramètres : " . print_r([
            'idInventaire' => $idInventaire,
            'idArticle' => $idArticle,
            'qtePhysique' => $qtePhysique,
            'numSeries' => $numSeries
        ], true));
        
        // Vérifier si une entrée existe déjà
        $stmt = $cnx->prepare("SELECT id FROM inventaire_temp WHERE id_inventaire = ? AND id_article = ? AND id_utilisateur = ?");
        $stmt->execute([$idInventaire, $idArticle, $_SESSION['id_utilisateur']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Récupérer la quantité théorique pour le calcul de l'écart
            $stmtQte = $cnx->prepare("
                SELECT qte_theorique 
                FROM inventaire_temp 
                WHERE id = ?
            ");
            $stmtQte->execute([$existing['id']]);
            $rowQte = $stmtQte->fetch(PDO::FETCH_ASSOC);
            $qteTheorique = $rowQte ? $rowQte['qte_theorique'] : 0;

            // Mise à jour
            $stmt = $cnx->prepare("
                UPDATE inventaire_temp 
                SET qte_physique = ?, 
                    ecart = ?,
                    date_saisie = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$qtePhysique, $qtePhysique - $qteTheorique, $existing['id']]);
            $tempId = $existing['id'];
            error_log("Mise à jour de l'entrée existante ID: " . $tempId);
        } else {
            // Récupérer les données de base
            $stmt = $cnx->prepare("
                SELECT il.*, a.CodePersoArticle, a.libelle, a.CATEGORIE
                FROM inventaire_ligne il
                JOIN article a ON il.id_article = a.IDARTICLE
                WHERE il.id_inventaire = ? AND il.id_article = ?
            ");
            $stmt->execute([$idInventaire, $idArticle]);
            $baseData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$baseData) {
                throw new Exception("Données de base non trouvées pour l'inventaire $idInventaire et l'article $idArticle");
            }
            
            // Nouvelle insertion
            $stmt = $cnx->prepare("
                INSERT INTO inventaire_temp (
                    id_inventaire, id_article, code_article, designation,
                    categorie, qte_theorique, qte_physique, ecart,
                    statut, date_saisie, id_utilisateur
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_cours', NOW(), ?)
            ");
            $ecart = $qtePhysique - $baseData['qte_theorique'];
            $stmt->execute([
                $idInventaire, $idArticle, $baseData['CodePersoArticle'],
                $baseData['libelle'], $baseData['CATEGORIE'],
                $baseData['qte_theorique'], $qtePhysique, $ecart,
                $_SESSION['id_utilisateur']
            ]);
            $tempId = $cnx->lastInsertId();
            error_log("Nouvelle entrée créée ID: " . $tempId);
        }
        
        // Gérer les numéros de série
        if (!empty($numSeries)) {
            if (!is_array($numSeries)) {
                $numSeries = explode(',', $numSeries);
            }
            // Supprimer les anciens numéros de série
            $stmt = $cnx->prepare("DELETE FROM inventaire_temp_series WHERE id_inventaire_temp = ?");
            $stmt->execute([$tempId]);
            // Insérer les nouveaux numéros de série
            $stmt = $cnx->prepare("INSERT INTO inventaire_temp_series (id_inventaire_temp, numero_serie) VALUES (?, ?)");
            foreach ($numSeries as $numSerie) {
                if (!empty(trim($numSerie))) {
                    $stmt->execute([$tempId, trim($numSerie)]);
                    error_log("Numéro de série ajouté: " . trim($numSerie));
                }
            }
        }
        
        $cnx->commit();
        error_log("Transaction validée avec succès");
        return true;
    } catch (Exception $e) {
        $cnx->rollBack();
        error_log("Erreur sauvegarde temporaire : " . $e->getMessage());
        return false;
    }
}

// Endpoint AJAX pour la sauvegarde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_temp') {
    // Nettoyer le buffer de sortie si possible
    if (ob_get_level()) {
        ob_clean();
    }

    // Désactiver l'affichage des erreurs pour cette requête AJAX
    ini_set('display_errors', 0);
    error_reporting(0);
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Log des données reçues (désactivé pour la production)
        // error_log("Données reçues pour sauvegarde temporaire : " . print_r($_POST, true));
    
    if (isset($_POST['id_inventaire'], $_POST['id_article'], $_POST['qte_physique'])) {
        $numSeries = isset($_POST['num_series']) ? $_POST['num_series'] : [];
        $success = saveTempData(
            $cnx,
            $_POST['id_inventaire'],
            $_POST['id_article'],
            $_POST['qte_physique'],
            $numSeries
        );
        $response['success'] = $success;
            $response['message'] = $success ? 'Sauvegarde réussie' : 'Erreur lors de la sauvegarde';
        
        // Log du résultat (désactivé pour la production)
        // error_log("Résultat de la sauvegarde temporaire : " . ($success ? "succès" : "échec"));
    } else {
        $response['message'] = 'Données manquantes dans la requête';
        error_log("Données manquantes dans la requête de sauvegarde temporaire");
    }
    } catch (Exception $e) {
        $response['message'] = 'Erreur : ' . $e->getMessage();
        error_log("Exception lors de la sauvegarde temporaire : " . $e->getMessage());
    }
    
    // S'assurer qu'aucune sortie n'a été envoyée avant
    if (headers_sent()) {
        error_log("Headers already sent - cannot send JSON response");
        exit;
    }
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($response);
    exit;
}

// Vérification du statut de l'inventaire avec gestion d'erreurs
try {
    $stmt = $cnx->prepare("SELECT * FROM inventaire WHERE IDINVENTAIRE = ?");
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête INVENTAIRE");
    }
    $stmt->execute([$idInventaire]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventaire) {
        throw new Exception("Inventaire non trouvé avec l'ID : " . $idInventaire);
    }
} catch (Exception $e) {
    error_log("Erreur base de données inventaire : " . $e->getMessage());
    header('Location: inventaire_liste.php?error=' . urlencode($e->getMessage()));
    exit();
}

// Définir le statut par défaut si NULL
if (!isset($inventaire['StatutInventaire']) || $inventaire['StatutInventaire'] === null) {
    $inventaire['StatutInventaire'] = 'en_attente';
    // Mettre à jour le statut dans la base
    $cnx->prepare("UPDATE inventaire SET StatutInventaire = 'en_attente' WHERE IDINVENTAIRE = ?")->execute([$idInventaire]);
}

// Debug du statut (désactivé pour la production)
// error_log("Statut de l'inventaire : " . $inventaire['StatutInventaire']);

// Recherche et filtres améliorés
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtre_ecart = isset($_GET['filtre_ecart']) ? $_GET['filtre_ecart'] : 'tous';
$filtre_categorie = isset($_GET['filtre_categorie']) ? $_GET['filtre_categorie'] : 'toutes';
$filtre_statut = isset($_GET['filtre_statut']) ? $_GET['filtre_statut'] : 'tous';

$where = "il.id_inventaire=$idInventaire";

// Recherche améliorée
if (!empty($search)) {
    $search = $cnx->quote("%$search%");
    $where .= " AND (
        il.code_article LIKE $search 
        OR il.designation LIKE $search 
        OR il.categorie LIKE $search
        OR a.libelle LIKE $search
    )";
}

// Filtre pour les écarts amélioré
if ($filtre_ecart == 'avec_ecart') {
    $where .= " AND (il.ecart != 0 OR il.ecart IS NULL)";
} elseif ($filtre_ecart == 'manque') {
    $where .= " AND il.ecart > 0";
} elseif ($filtre_ecart == 'surplus') {
    $where .= " AND il.ecart < 0";
} elseif ($filtre_ecart == 'sans_ecart') {
    $where .= " AND il.ecart = 0";
}

// Filtre par catégorie
if ($filtre_categorie != 'toutes') {
    $categorie = $cnx->quote($filtre_categorie);
    $where .= " AND il.categorie = $categorie";
}

// Filtre par statut de saisie
if ($filtre_statut == 'saisis') {
    $where .= " AND il.qte_physique IS NOT NULL AND il.qte_physique > 0";
} elseif ($filtre_statut == 'non_saisis') {
    $where .= " AND (il.qte_physique IS NULL OR il.qte_physique = 0)";
}

// Récupérer les catégories disponibles pour le filtre avec gestion d'erreurs
try {
    $stmt = $cnx->prepare("
        SELECT DISTINCT categorie 
        FROM inventaire_ligne 
        WHERE id_inventaire = ? 
        ORDER BY categorie
    ");
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête catégories");
    }
    $stmt->execute([$idInventaire]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Erreur récupération catégories : " . $e->getMessage());
    $categories = [];
}

try {
    $stmt = $cnx->prepare("
        SELECT 
            il.*,
            a.PrixAchatHT,
            a.PrixVenteTTC,
            s.StockActuel as stock_actuel,
            (il.qte_physique * a.PrixAchatHT) as valeur_physique,
            (il.qte_theorique * a.PrixAchatHT) as valeur_theorique,
            ((il.qte_physique - il.qte_theorique) * a.PrixAchatHT) as valeur_ecart
        FROM inventaire_ligne il 
        LEFT JOIN article a ON il.id_article = a.IDARTICLE 
        LEFT JOIN stock s ON il.id_article = s.IDARTICLE
        WHERE $where 
        ORDER BY il.categorie, il.code_article
    ");
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête lignes inventaire");
    }
    $stmt->execute();
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération lignes inventaire : " . $e->getMessage());
    $lignes = [];
}

// Traitement de la validation de l'inventaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $inventaire['StatutInventaire'] == 'en_attente') {
    try {
        $cnx->beginTransaction();
        
        // Récupérer l'ID du fournisseur inventaire
        $stmt = $cnx->prepare("SELECT IDFOURNISSEUR FROM fournisseur WHERE NomFournisseur = 'Inventaire Physique'");
        $stmt->execute();
        $fournisseurInventaire = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fournisseurInventaire) {
            // Créer le fournisseur inventaire s'il n'existe pas
            $stmt = $cnx->prepare("
                INSERT INTO fournisseur (NomFournisseur, Adresse, Telephone, Email, DateIns) 
                VALUES ('Inventaire Physique', 'Stock trouvé lors d''inventaire', 'N/A', 'inventaire@system.com', NOW())
            ");
            $stmt->execute();
            $fournisseurInventaireId = $cnx->lastInsertId();
        } else {
            $fournisseurInventaireId = $fournisseurInventaire['IDFOURNISSEUR'];
        }
        
        // Créer une entrée en stock pour l'inventaire
        $numeroBonInventaire = 'INV-' . date('Ymd-His') . '-' . $idInventaire;
        $dateInventaire = date('Y-m-d H:i:s');
        
        $stmt = $cnx->prepare("
            INSERT INTO entree_en_stock 
            (IDFOURNISSEUR, Numero_bon, Date_arrivee, ID_utilisateurs, statut, MontantAchatHT, MontantVenteTTC) 
            VALUES (?, ?, ?, ?, 'EN_COURS', 0, 0)
        ");
        $stmt->execute([
            $fournisseurInventaireId,
            $numeroBonInventaire,
            $dateInventaire,
            $_SESSION['id_utilisateur']
        ]);
        $idEntreeInventaire = $cnx->lastInsertId();
        
        // error_log("Entrée en stock inventaire créée : ID = $idEntreeInventaire, Bon = $numeroBonInventaire");
        
        // Variables pour calculer les montants totaux
        $totalMontantAchat = 0;
        $totalMontantVente = 0;
        $articlesTraites = [];
        
        foreach ($_POST['qte_physique'] as $idLigne => $qtePhysique) {
            $ligne = $cnx->query("SELECT * FROM inventaire_ligne WHERE id = $idLigne")->fetch(PDO::FETCH_ASSOC);
            
            if (!$ligne) {
                error_log("Ligne d'inventaire non trouvée pour ID: $idLigne");
                continue;
            }
            
            // Log désactivé pour la production
            // error_log("Traitement de l'article ID: {$ligne['id_article']}, Quantité physique: $qtePhysique");
            
            // Calcul des écarts
            //$qtePhysique = intval($qtePhysique);
            $qtePhysique = is_numeric($qtePhysique) ? intval($qtePhysique) : 0;
            $qteTheorique = intval($ligne['qte_theorique']);
            $ecart = $qtePhysique - $qteTheorique;
            
            // Récupérer les numéros de série trouvés pour cet article
            $numSeriesTrouves = [];
            if (isset($_POST['series_trouves'][$idLigne]) && is_array($_POST['series_trouves'][$idLigne])) {
                $numSeriesTrouves = array_filter(array_map('trim', $_POST['series_trouves'][$idLigne]));
            }
            
            // Si pas de numéros de série dans le POST, essayer de les récupérer depuis les données temporaires
            if (empty($numSeriesTrouves)) {
                $stmt = $cnx->prepare("
                    SELECT its.numero_serie 
                    FROM inventaire_temp it
                    JOIN inventaire_temp_series its ON it.id = its.id_inventaire_temp
                    WHERE it.id_inventaire = ? AND it.id_article = ? AND it.id_utilisateur = ?
                ");
                $stmt->execute([$idInventaire, $ligne['id_article'], $_SESSION['id_utilisateur']]);
                $tempSeries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $numSeriesTrouves = array_column($tempSeries, 'numero_serie');
            }
            
            // Récupérer les numéros de série attendus depuis la table de référence (au moment du lancement)
            $stmt = $cnx->prepare("
                SELECT id_num_serie, numero_serie 
                FROM inventaire_series_attendues 
                WHERE id_inventaire = ? AND id_article = ?
            ");
            $stmt->execute([$idInventaire, $ligne['id_article']]);
            $numSeriesAttendus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Analyser les écarts de numéros de série
            $numSeriesAttendusList = array_column($numSeriesAttendus, 'numero_serie');
            $numSeriesAttendusIds = array_column($numSeriesAttendus, 'id_num_serie');
            
            // Log pour débogage (désactivé pour la production)
            // error_log("Article {$ligne['id_article']} - Numéros attendus: " . implode(', ', $numSeriesAttendusList));
            // error_log("Article {$ligne['id_article']} - Numéros trouvés: " . implode(', ', $numSeriesTrouves));
            
            // Numéros manquants (attendus mais non trouvés) - seulement s'il y a des numéros attendus
            $numSeriesManquants = [];
            $numSeriesManquantsIds = [];
            if (!empty($numSeriesAttendusList)) {
                $numSeriesManquants = array_diff($numSeriesAttendusList, $numSeriesTrouves);
                foreach ($numSeriesManquants as $numSerie) {
                    $key = array_search($numSerie, $numSeriesAttendusList);
                    if ($key !== false) {
                        $numSeriesManquantsIds[] = $numSeriesAttendusIds[$key];
                    }
                }
                // error_log("Article {$ligne['id_article']} - Numéros manquants: " . implode(', ', $numSeriesManquants));
            }
            
            // Numéros en trop (trouvés mais non attendus) ou nouveaux (si pas de numéros attendus)
            $numSeriesEnTrop = !empty($numSeriesAttendusList) ? 
                array_diff($numSeriesTrouves, $numSeriesAttendusList) : 
                $numSeriesTrouves;
            // error_log("Article {$ligne['id_article']} - Numéros en trop/nouveaux: " . implode(', ', $numSeriesEnTrop));
            
            // Mise à jour de la ligne d'inventaire
            $stmt = $cnx->prepare("
                UPDATE inventaire_ligne 
                SET qte_physique = ?, 
                    ecart = ?,
                    date_saisie = NOW(),
                    statut = 'valide'
                WHERE id = ?
            ");
            $stmt->execute([$qtePhysique, $ecart, $idLigne]);
            
            // Gestion des numéros de série manquants (marquer comme introuvable)
            if (!empty($numSeriesManquantsIds)) {
                // error_log("Marquage de " . count($numSeriesManquantsIds) . " numéros comme introuvables");
                $placeholders = str_repeat('?,', count($numSeriesManquantsIds) - 1) . '?';
                $stmt = $cnx->prepare("
                    UPDATE num_serie 
                    SET statut = 'introuvable', 
                        DateMod = NOW() 
                    WHERE IDNUM_SERIE IN ($placeholders)
                ");
                $stmt->execute($numSeriesManquantsIds);
                
                // Journalisation des numéros marqués comme introuvables
                foreach ($numSeriesManquantsIds as $idNumSerie) {
                    $stmt = $cnx->prepare("
                        INSERT INTO inventaire_log 
                        (id_inventaire, id_article, utilisateur, date_action, action, qte_avant, qte_apres, commentaire)
                        VALUES (?, ?, ?, NOW(), 'modification', 1, 0, ?)
                    ");
                    $stmt->execute([
                        $idInventaire,
                        $ligne['id_article'],
                        $_SESSION['nom_utilisateur'] ?? 'Utilisateur',
                        "Numéro de série marqué comme introuvable lors de l'inventaire"
                    ]);
                }
            }
            
            // Gestion des numéros de série en trop (créer des entrées en stock)
            if (!empty($numSeriesEnTrop)) {
                // error_log("Traitement de " . count($numSeriesEnTrop) . " numéros en trop/nouveaux");
                
                // Récupérer les informations de l'article pour les prix
                $stmt = $cnx->prepare("SELECT PrixAchatHT, PrixVenteTTC FROM article WHERE IDARTICLE = ?");
                $stmt->execute([$ligne['id_article']]);
                $articleInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Créer une ligne d'entrée en stock pour cet article
                if (!isset($articlesTraites[$ligne['id_article']])) {
                    $stmt = $cnx->prepare("
                        INSERT INTO entree_stock_ligne 
                        (IDENTREE_EN_STOCK, IDARTICLE, Quantite, PrixAchat, PrixVente) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $idEntreeInventaire,
                        $ligne['id_article'],
                        count($numSeriesEnTrop),
                        $articleInfo['PrixAchatHT'],
                        $articleInfo['PrixVenteTTC']
                    ]);
                    
                    $totalMontantAchat += $articleInfo['PrixAchatHT'] * count($numSeriesEnTrop);
                    $totalMontantVente += $articleInfo['PrixVenteTTC'] * count($numSeriesEnTrop);
                    $articlesTraites[$ligne['id_article']] = true;
                }
                
                foreach ($numSeriesEnTrop as $numSerie) {
                    // Vérifier si le numéro existe déjà (même avec statut différent)
                    $stmt = $cnx->prepare("SELECT IDNUM_SERIE, statut FROM num_serie WHERE NUMERO_SERIE = ?");
                    $stmt->execute([$numSerie]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing) {
                        // error_log("Réactivation du numéro de série: $numSerie (statut actuel: {$existing['statut']})");
                        // Réactiver le numéro existant et le lier à l'entrée inventaire
                        $stmt = $cnx->prepare("
                            UPDATE num_serie 
                            SET statut = 'disponible', 
                                ID_ENTRER_STOCK = ?,
                                DateMod = NOW() 
                            WHERE IDNUM_SERIE = ?
                        ");
                        $stmt->execute([$idEntreeInventaire, $existing['IDNUM_SERIE']]);
                        
                        $stmt = $cnx->prepare("
                            INSERT INTO inventaire_log 
                            (id_inventaire, id_article, utilisateur, date_action, action, qte_avant, qte_apres, commentaire)
                            VALUES (?, ?, ?, NOW(), 'modification', 0, 1, ?)
                        ");
                        $stmt->execute([
                            $idInventaire,
                            $ligne['id_article'],
                            $_SESSION['nom_utilisateur'] ?? 'Utilisateur',
                            "Numéro de série réactivé lors de l'inventaire: $numSerie"
                        ]);
                    } else {
                        // error_log("Création d'un nouveau numéro de série: $numSerie");
                        // Créer un nouveau numéro de série lié à l'entrée inventaire
                        $stmt = $cnx->prepare("
                            INSERT INTO num_serie 
                            (IDARTICLE, NUMERO_SERIE, ID_ENTRER_STOCK, DATE_ENTREE, statut, DateIns)
                            VALUES (?, ?, ?, CURDATE(), 'disponible', NOW())
                        ");
                        $stmt->execute([$ligne['id_article'], $numSerie, $idEntreeInventaire]);
                        
                        $stmt = $cnx->prepare("
                            INSERT INTO inventaire_log 
                            (id_inventaire, id_article, utilisateur, date_action, action, qte_avant, qte_apres, commentaire)
                            VALUES (?, ?, ?, NOW(), 'creation', 0, 1, ?)
                        ");
                        $stmt->execute([
                            $idInventaire,
                            $ligne['id_article'],
                            $_SESSION['nom_utilisateur'] ?? 'Utilisateur',
                            "Nouveau numéro de série ajouté lors de l'inventaire: $numSerie"
                        ]);
                    }
                }
            }
            
            // Mise à jour du stock avec la quantité physique comptée (toujours)
                $stmt = $cnx->prepare("SELECT IDSTOCK FROM stock WHERE IDARTICLE = ?");
                $stmt->execute([$ligne['id_article']]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$stock) {
                // Créer un nouveau stock pour l'article s'il n'existe pas
                $stmt = $cnx->prepare("
                    INSERT INTO stock (IDARTICLE, StockActuel, TotalEntree, TotalVente, DateIns) 
                    VALUES (?, 0, 0, 0, NOW())
                ");
                $stmt->execute([$ligne['id_article']]);
                $idStock = $cnx->lastInsertId();
                // error_log("Nouveau stock créé pour l'article ID: {$ligne['id_article']}, ID Stock: $idStock");
                
                // Récupérer les informations du stock créé
                $stmt = $cnx->prepare("SELECT IDSTOCK FROM stock WHERE IDARTICLE = ?");
                $stmt->execute([$ligne['id_article']]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Mise à jour du stock avec la quantité physique
                $stmt = $cnx->prepare("
                    UPDATE stock 
                    SET StockActuel = ?,
                        DateMod = NOW()
                    WHERE IDARTICLE = ?
                ");
                $stmt->execute([$qtePhysique, $ligne['id_article']]);
                
            // Journalisation de la mise à jour du stock
                $stmt = $cnx->prepare("
                    INSERT INTO inventaire_log 
                    (id_inventaire, id_article, utilisateur, date_action, qte_avant, qte_apres, commentaire)
                    VALUES (?, ?, ?, NOW(), ?, ?, ?)
                ");
                $stmt->execute([
                    $idInventaire,
                    $ligne['id_article'],
                    $_SESSION['nom_utilisateur'],
                    $qteTheorique,
                    $qtePhysique,
                "Mise à jour du stock après inventaire"
            ]);
            
            // Si écart, créer une correction
            if ($ecart !== 0) {
                // Générer le numéro de correction unique pour l'inventaire
                $date = date('dmY');
                $numero_correction = $date . 'INV' . sprintf('%03d', $idInventaire);
                
                // Récupérer dynamiquement l'ID du motif "INVENTAIRE"
                $stmtMotif = $cnx->prepare("SELECT IDMOTIF_MOUVEMENT_STOCK FROM motif_correction WHERE LibelleMotifMouvementStock LIKE 'INVENTAIRE%'");
                $stmtMotif->execute();
                $motifInventaire = $stmtMotif->fetch(PDO::FETCH_ASSOC);
                $idMotifInventaire = $motifInventaire ? $motifInventaire['IDMOTIF_MOUVEMENT_STOCK'] : null;
                
                // Journalisation dans correction
                $stmt = $cnx->prepare("
                    INSERT INTO correction 
                    (NumeroCorrection, DateMouvementStock, QuantiteMoved, PrixAchat, 
                     IDSTOCK, ID_utilisateurs, IDMOTIF_MOUVEMENT_STOCK, UtilCrea, DateIns)
                    VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $numero_correction,
                    $ecart,
                    $ligne['PrixAchatHT'] ?? 0,
                    $stock['IDSTOCK'],
                    $_SESSION['id_utilisateur'], // ID_utilisateurs
                    $idMotifInventaire, // ID du motif "INVENTAIRE" dynamique
                    $_SESSION['id_utilisateur']  // UtilCrea (créateur)
                ]);
            }
        }
        
        // Finaliser l'entrée en stock inventaire
        if ($totalMontantAchat > 0 || $totalMontantVente > 0) {
            $stmt = $cnx->prepare("
                UPDATE entree_en_stock 
                SET MontantAchatHT = ?, MontantVenteTTC = ?, statut = 'TERMINE'
                WHERE IDENTREE_STOCK = ?
            ");
            $stmt->execute([$totalMontantAchat, $totalMontantVente, $idEntreeInventaire]);
            // error_log("Entrée en stock inventaire finalisée - Achat: $totalMontantAchat, Vente: $totalMontantVente");
        } else {
            // Si aucun numéro nouveau trouvé, supprimer l'entrée vide
            $stmt = $cnx->prepare("DELETE FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
            $stmt->execute([$idEntreeInventaire]);
            // error_log("Entrée en stock inventaire supprimée (aucun numéro nouveau trouvé)");
        }
        // --- JOURNALISATION : Validation inventaire ---
        $description_validation = sprintf(
            "Validation inventaire '%s' - %d articles traités - %d corrections créées - Stock mis à jour - Statut: validé",
            $inventaire['Commentaires'],
            count($articlesTraites),
            count($articlesTraites)
        );
        
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'VALIDATION_INVENTAIRE',
                'INVENTAIRE',
                'inventaire_saisie.php',
                $description_validation,
                [
                    'id_inventaire' => $idInventaire,
                    'nom_inventaire' => $inventaire['Commentaires'],
                    'utilisateur' => $_SESSION['nom_utilisateur'],
                    'articles_traites' => count($articlesTraites),
                    'corrections_creees' => count($articlesTraites),
                    'montant_total_achat' => $totalMontantAchat,
                    'montant_total_vente' => $totalMontantVente,
                    'statut_final' => 'valide'
                ],
                [
                    'action' => 'validation_inventaire',
                    'stock_mis_a_jour' => true,
                    'corrections_automatiques' => true,
                    'inventaire_finalise' => true
                ],
                'CRITICAL',
                'SUCCESS',
                null
            );
        }
        // --- FIN JOURNALISATION ---

        // Validation de l'inventaire
        $stmt = $cnx->prepare("
            UPDATE inventaire 
            SET StatutInventaire = 'valide',
                ModifieLe = NOW(),
                ModifiePar = ?
            WHERE IDINVENTAIRE = ?
        ");
        $stmt->execute([$_SESSION['nom_utilisateur'], $idInventaire]);
        $cnx->commit();
        header("Location: inventaire_liste.php?success=Inventaire validé avec succès");
        exit();
        
    } catch (Exception $e) {
        $cnx->rollBack();
        
        // --- JOURNALISATION : Erreur validation inventaire ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_VALIDATION_INVENTAIRE',
                'INVENTAIRE',
                'inventaire_saisie.php',
                'Erreur lors de la validation de l\'inventaire : ' . $e->getMessage(),
                [
                    'id_inventaire' => $idInventaire,
                    'nom_inventaire' => $inventaire['Commentaires'] ?? 'N/A',
                    'utilisateur' => $_SESSION['nom_utilisateur'] ?? 'N/A',
                    'erreur' => $e->getMessage(),
                    'transaction_rollback' => true
                ],
                null,
                'CRITICAL',
                'FAILED',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        $error = "Erreur lors de la validation : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie Inventaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .table-inventaire {
                width: 100% !important;
                font-size: 12px !important;
            }
            .table-inventaire th, .table-inventaire td {
                padding: 4px !important;
            }
        }
        .print-only {
            display: none;
        }
        .table-inventaire {
            font-size: 0.9em;
        }
        .table-inventaire th, .table-inventaire td {
            padding: 0.3rem;
            vertical-align: middle;
        }
        .table-inventaire input {
            width: 80px;
            padding: 0.2rem;
        }
        .search-box {
            max-width: 300px;
            margin-bottom: 1rem;
        }
        .ecart-positif { color: green; font-weight: bold; }
        .ecart-negatif { color: red; font-weight: bold; }
        .ecart-zero { color: #666; }
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .input-group-sm {
            width: 120px;
        }
        .qte-input {
            text-align: right;
            font-weight: bold;
        }
        .alert-info {
            margin-bottom: 1rem;
        }
        .debug-info {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            font-size: 0.8em;
        }
        .series-trouves {
            font-family: monospace;
            font-size: 0.9em;
        }
        .list-group-item {
            font-family: monospace;
            font-size: 0.9em;
        }
        .collapse {
            transition: all 0.3s ease;
        }
        .btn-outline-info {
            font-size: 0.8em;
            padding: 0.2rem 0.5rem;
        }
        .badge {
            font-size: 0.7em;
            margin-left: 0.3rem;
        }
        .card-body {
            padding: 0.75rem;
            font-size: 0.9em;
        }
        .form-label {
            margin-bottom: 0.3rem;
            font-size: 0.9em;
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .filter-card .card-body {
            padding: 0.75rem;
        }
        .filter-card .h5 {
            font-weight: 600;
            margin-bottom: 0;
        }
        .filter-card small {
            font-size: 0.75rem;
            font-weight: 500;
        }
        .form-select, .form-control {
            border-radius: 6px;
            border: 1px solid #ced4da;
            transition: all 0.2s ease;
        }
        .form-select:focus, .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        .search-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
    <!-- Système de thème sombre/clair -->
</head>
<body>
    <!-- Bouton flottant de validation en haut de page -->
    <?php if ($inventaire['StatutInventaire'] == 'en_attente'): ?>
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1055;">
        <button class="btn btn-success btn-lg shadow" style="border-radius: 50px;" data-bs-toggle="modal" data-bs-target="#modalValidationSecurisee">
            <i class="fas fa-check-circle me-2"></i>Valider l'inventaire
        </button>
    </div>
    <?php endif; ?>
<?php include('includes/navigation_buttons.php'); ?>    
 
<div class="container-fluid mt-3">
        <div class="print-only text-center mb-4">
            <h2>FICHE D'INVENTAIRE</h2>
            <h4><?php echo htmlspecialchars($inventaire['Commentaires'] ?? ''); ?></h4>
            <p>Date : <?php echo date('d/m/Y'); ?></p>
        </div>

        <div class="no-print">
            <h2>Saisie Inventaire : <?php echo htmlspecialchars($inventaire['Commentaires'] ?? ''); ?></h2>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Debug info -->
            <div class="debug-info">
                Statut actuel : <?php echo htmlspecialchars($inventaire['StatutInventaire'] ?? 'en_attente'); ?><br>
                ID Inventaire : <?php echo $idInventaire; ?>
            </div>

            <?php if ($inventaire['StatutInventaire'] == 'en_attente'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Instructions :
                <ul>
                    <li>Saisissez la quantité physique comptée pour chaque article</li>
                    <li>Les écarts seront calculés automatiquement</li>
                    <li>La validation mettra à jour les stocks et créera les corrections nécessaires</li>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Barre de recherche et filtres améliorés -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="IDINVENTAIRE" value="<?php echo $idInventaire; ?>">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher par code, nom, catégorie..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary ms-2">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                <div class="col-md-2">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="IDINVENTAIRE" value="<?php echo $idInventaire; ?>">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        <select name="filtre_ecart" class="form-select" onchange="this.form.submit()">
                            <option value="tous" <?php echo $filtre_ecart == 'tous' ? 'selected' : ''; ?>>Tous les écarts</option>
                            <option value="avec_ecart" <?php echo $filtre_ecart == 'avec_ecart' ? 'selected' : ''; ?>>Avec écart</option>
                            <option value="manque" <?php echo $filtre_ecart == 'manque' ? 'selected' : ''; ?>>Manque (écart +)</option>
                            <option value="surplus" <?php echo $filtre_ecart == 'surplus' ? 'selected' : ''; ?>>Surplus (écart -)</option>
                            <option value="sans_ecart" <?php echo $filtre_ecart == 'sans_ecart' ? 'selected' : ''; ?>>Sans écart</option>
                        </select>
                    </form>
                </div>
                <div class="col-md-2">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="IDINVENTAIRE" value="<?php echo $idInventaire; ?>">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        <?php if ($filtre_ecart != 'tous'): ?>
                            <input type="hidden" name="filtre_ecart" value="<?php echo htmlspecialchars($filtre_ecart); ?>">
                        <?php endif; ?>
                        <select name="filtre_categorie" class="form-select" onchange="this.form.submit()">
                            <option value="toutes" <?php echo $filtre_categorie == 'toutes' ? 'selected' : ''; ?>>Toutes catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filtre_categorie == $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-2">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="IDINVENTAIRE" value="<?php echo $idInventaire; ?>">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        <?php if ($filtre_ecart != 'tous'): ?>
                            <input type="hidden" name="filtre_ecart" value="<?php echo htmlspecialchars($filtre_ecart); ?>">
                        <?php endif; ?>
                        <?php if ($filtre_categorie != 'toutes'): ?>
                            <input type="hidden" name="filtre_categorie" value="<?php echo htmlspecialchars($filtre_categorie); ?>">
                        <?php endif; ?>
                        <select name="filtre_statut" class="form-select" onchange="this.form.submit()">
                            <option value="tous" <?php echo $filtre_statut == 'tous' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="saisis" <?php echo $filtre_statut == 'saisis' ? 'selected' : ''; ?>>Articles saisis</option>
                            <option value="non_saisis" <?php echo $filtre_statut == 'non_saisis' ? 'selected' : ''; ?>>Articles non saisis</option>
                        </select>
                    </form>
                </div>
                <div class="col-md-3 text-end">
                    <button onclick="showPrintOptions()" class="btn btn-secondary me-2">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <a href="?IDINVENTAIRE=<?php echo $idInventaire; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Effacer filtres
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistiques des filtres -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body py-2">
                        <div class="row text-center">
                            <div class="col-md-2">
                                <small class="text-muted">Articles trouvés</small>
                                <div class="h5 mb-0"><?php echo count($lignes); ?></div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Avec écart</small>
                                <div class="h5 mb-0 text-warning">
                                    <?php 
                                    $avecEcart = array_filter($lignes, function($l) { 
                                        return ($l['ecart'] ?? 0) != 0; 
                                    });
                                    echo count($avecEcart);
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Manque</small>
                                <div class="h5 mb-0 text-danger">
                                    <?php 
                                    $manque = array_filter($lignes, function($l) { 
                                        return ($l['ecart'] ?? 0) > 0; 
                                    });
                                    echo count($manque);
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Surplus</small>
                                <div class="h5 mb-0 text-success">
                                    <?php 
                                    $surplus = array_filter($lignes, function($l) { 
                                        return ($l['ecart'] ?? 0) < 0; 
                                    });
                                    echo count($surplus);
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Saisis</small>
                                <div class="h5 mb-0 text-info">
                                    <?php 
                                    $saisis = array_filter($lignes, function($l) { 
                                        return ($l['qte_physique']) > 0; 
                                    });
                                    echo count($saisis);
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Non saisis</small>
                                <div class="h5 mb-0 text-secondary">
                                    <?php 
                                    $nonSaisis = array_filter($lignes, function($l) { 
                                        return ($l['qte_physique'] ?? 0) == 0; 
                                    });
                                    echo count($nonSaisis);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($inventaire['StatutInventaire'] == 'en_attente'): ?>
        <form method="POST" id="inventaireForm">
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-inventaire">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Désignation</th>
                        <?php if (user_can_see_purchase_prices()): ?>
                            <th>Prix Achat</th>
                        <?php endif; ?>
                        <th>Prix Vente</th>
                        <th>Stock Théorique</th>
                        <th>Quantité Comptée</th>
                        <th>Quantité Retenue</th>
                        <th>Écart</th>
                        <th>Valeur Écart</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalTheorique = 0;
                    $totalPhysique = 0;
                    $totalEcart = 0;
                    $totalValeurEcart = 0;
                    $currentCategory = '';
                    
                    foreach ($lignes as $ligne): 
                        if ($currentCategory !== $ligne['categorie']) {
                            $currentCategory = $ligne['categorie'];
                            echo "<tr class='table-secondary'><td colspan='10'><strong>" . htmlspecialchars($currentCategory) . "</strong></td></tr>";
                        }
                        
                        // Surcharger avec les données temporaires si elles existent
                        if (isset($tempData[$ligne['id_article']])) {
                            $ligne['qte_physique'] = $tempData[$ligne['id_article']]['qte_physique'];
                            $ligne['ecart'] = $tempData[$ligne['id_article']]['ecart'];
                            $ligne['date_saisie'] = $tempData[$ligne['id_article']]['date_saisie'];
                            $ligne['num_series'] = $tempData[$ligne['id_article']]['num_series'] ?? [];
                            // Si des numéros de série sont présents, la quantité comptée doit refléter leur nombre
                            if (!empty($ligne['num_series'])) {
                                $ligne['qte_physique'] = count($ligne['num_series']);
                            }
                        }
                        $totalTheorique += $ligne['qte_theorique'];
                        $totalPhysique += $ligne['qte_physique'] ?? 0;
                        $totalEcart += $ligne['ecart'] ?? 0;
                        $totalValeurEcart += $ligne['valeur_ecart'] ?? 0;

                        // Récupérer les numéros de série attendus depuis la table de référence (au moment du lancement)
                        try {
                            $stmt = $cnx->prepare("
                                SELECT id_num_serie, numero_serie 
                                FROM inventaire_series_attendues 
                                WHERE id_inventaire = ? AND id_article = ?
                            ");
                            $stmt->execute([$idInventaire, $ligne['id_article']]);
                            $numSeriesAttendus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            error_log("Erreur récupération séries attendues : " . $e->getMessage());
                            $numSeriesAttendus = [];
                        }
                        
                        // Analyser les écarts de numéros de série
                        $numSeriesAttendusList = array_column($numSeriesAttendus, 'numero_serie');
                        $numSeriesAttendusIds = array_column($numSeriesAttendus, 'id_num_serie');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ligne['code_article']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($ligne['designation']); ?>
                            <?php if (count($numSeriesAttendus) > 0): ?>
                            <div class="mt-2">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-info" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#series-attendus-<?php echo $ligne['id']; ?>"
                                        aria-expanded="false">
                                    <i class="fas fa-barcode"></i> Numéros attendus
                                    <span class="badge bg-info"><?php echo count($numSeriesAttendus); ?></span>
                                </button>
                                <div class="collapse mt-2" id="series-attendus-<?php echo $ligne['id']; ?>">
                                    <div class="card card-body">
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($numSeriesAttendus as $ns): ?>
                                                <div class="list-group-item py-1 px-2">
                                                    <?php echo htmlspecialchars($ns['numero_serie']); ?>
                                                </div>
                                                <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php if (user_can_see_purchase_prices()): ?>
                            <td class="text-end"><?php echo number_format($ligne['PrixAchatHT'], 0, ',', ' '); ?> F.CFA</td>
                        <?php endif; ?>
                        <td class="text-end"><?php echo number_format($ligne['PrixVenteTTC'], 0, ',', ' '); ?> F.CFA</td>
                        <td class="text-end"><?php echo $ligne['qte_theorique']; ?></td>
                      <td>
    <?php if ($inventaire['StatutInventaire'] == 'en_attente'): ?>

        <?php
        // Déterminer la quantité physique à afficher : temporaire si existante, sinon valeur originale
        $qtePhysique = $tempData[$ligne['id_article']]['qte_physique'] ?? $ligne['qte_physique'] ?? 0;

        // Récupérer les numéros de série déjà saisis temporairement
        $seriesTrouves = $tempData[$ligne['id_article']]['num_series'] ?? [];
        ?>

        <div class="input-group input-group-sm">
            <input type="number"
                   name="qte_physique[<?php echo $ligne['id']; ?>]"
                   class="form-control form-control-sm qte-physique qte-input"
                   value="<?php echo $qtePhysique; ?>"
                   min="0"
                   data-theorique="<?php echo $ligne['qte_theorique']; ?>"
                   data-prix-achat="<?php echo $ligne['PrixAchatHT']; ?>"
                   data-id-article="<?php echo $ligne['id_article']; ?>">
        </div>

        <!-- Afficher les champs numéros de série seulement si qte > 0 -->
        <?php if (intval($qtePhysique) > 0): ?>
            <div class="mt-2">
                <div class="series-fields"
                     data-id="<?php echo $ligne['id']; ?>"
                     data-series-attendus='<?php echo json_encode($numSeriesAttendusList); ?>'
                     data-series-trouves='<?php echo json_encode($seriesTrouves); ?>'>
                    <?php
                    $nb = intval($qtePhysique);
                    for ($i = 0; $i < $nb; $i++) {
                        $val = isset($seriesTrouves[$i]) ? htmlspecialchars($seriesTrouves[$i]) : '';
                        echo '<div class="input-group input-group-sm mb-1">';
                        echo '<input type="text" class="form-control form-control-sm series-input" name="series_trouves['.$ligne['id'].'][]" placeholder="Numéro de série '.($i+1).'" value="'.$val.'" data-index="'.$i.'">';
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="mt-1">
                    <span class="text-danger small manquants"></span>
                    <span class="text-warning small en-trop"></span>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</td>

                        <td class="text-end qte-retenue"><?php echo $ligne['qte_physique'] ?? 0; ?></td>
                        <td class="text-end ecart" data-ecart="<?php echo $ligne['ecart'] ?? 0; ?>">
                            <?php 
                            $ecart = $ligne['ecart'] ?? 0;
                            if ($ecart > 0) {
                                echo '<span class="ecart-negatif">+' . $ecart . '</span>';
                            } elseif ($ecart < 0) {
                                echo '<span class="ecart-positif">' . $ecart . '</span>';
                            } else {
                                echo '<span class="ecart-zero">0</span>';
                            }
                            ?>
                        </td>
                        <td class="text-end valeur-ecart">
                            <?php 
                            $valeurEcart = $ligne['valeur_ecart'] ?? 0;
                            if ($valeurEcart > 0) {
                                echo '<span class="ecart-negatif">+' . number_format($valeurEcart, 0, ',', ' ') . ' F.CFA</span>';
                            } elseif ($valeurEcart < 0) {
                                echo '<span class="ecart-positif">' . number_format($valeurEcart, 0, ',', ' ') . ' F.CFA</span>';
                            } else {
                                echo '<span class="ecart-zero">0 F.CFA</span>';
                            }
                            ?>
                        </td>
                        <td class="text-end">
                            <?php 
                            if (!empty($ligne['date_saisie'])) {
                                echo date('d/m/Y', strtotime($ligne['date_saisie']));
                            } else {
                                echo date('d/m/Y');
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="4" class="text-end"><strong>Totaux :</strong></td>
                        <td class="text-end"><?php echo $totalTheorique; ?></td>
                        <td class="text-end"><?php echo $totalPhysique; ?></td>
                        <td class="text-end"><?php echo $totalPhysique; ?></td>
                        <td class="text-end">
                            <?php 
                            if ($totalEcart > 0) {
                                echo '<span class="ecart-negatif">+' . $totalEcart . '</span>';
                            } elseif ($totalEcart < 0) {
                                echo '<span class="ecart-positif">' . $totalEcart . '</span>';
                            } else {
                                echo '<span class="ecart-zero">0</span>';
                            }
                            ?>
                        </td>
                        <td class="text-end">
                            <?php 
                            if ($totalValeurEcart > 0) {
                                echo '<span class="ecart-negatif">+' . number_format($totalValeurEcart, 0, ',', ' ') . ' F.CFA</span>';
                            } elseif ($totalValeurEcart < 0) {
                                echo '<span class="ecart-positif">' . number_format($totalValeurEcart, 0, ',', ' ') . ' F.CFA</span>';
                            } else {
                                echo '<span class="ecart-zero">0 F.CFA</span>';
                            }
                            ?>
                        </td>
                        <td></td>
                    </tr>
                    <tr class="table-light">
                        <td colspan="10" class="text-end">
                            <strong>Nombre total d'articles : <?php echo count($lignes); ?></strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if ($inventaire['StatutInventaire'] == 'en_attente'): ?>
        <div class="mt-3 no-print">
            <a href="inventaire_liste.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
        </form>
        <!-- Modal Bootstrap pour la validation sécurisée -->
        <div class="modal fade" id="modalValidationSecurisee" tabindex="-1" aria-labelledby="modalValidationSecuriseeLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalValidationSecuriseeLabel">
                  <i class="fas fa-shield-alt me-2"></i>Zone de Validation Sécurisée
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-warning">
                  <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h6>
                  <p class="mb-2">La validation de l'inventaire est une action irréversible qui :</p>
                  <ul class="mb-0">
                    <li>Met à jour définitivement les stocks</li>
                    <li>Crée des corrections automatiques</li>
                    <li>Ferme l'inventaire</li>
                    <li>Ne peut pas être annulée</li>
                  </ul>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" id="confirmValidation1">
                      <label class="form-check-label" for="confirmValidation1">
                        <strong>Je confirme avoir vérifié toutes les quantités saisies</strong>
                      </label>
                    </div>
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" id="confirmValidation2">
                      <label class="form-check-label" for="confirmValidation2">
                        <strong>Je comprends que cette action est irréversible</strong>
                      </label>
                    </div>
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" id="confirmValidation3">
                      <label class="form-check-label" for="confirmValidation3">
                        <strong>J'ai l'autorisation de valider cet inventaire</strong>
                      </label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-grid gap-2">
                      <button type="button" 
                              class="btn btn-danger btn-lg" 
                              id="btnValidationSecurisee"
                              disabled
                              onclick="validerInventaireSecurise()">
                        <i class="fas fa-lock me-2"></i>
                        VALIDER L'INVENTAIRE
                      </button>
                      <small class="text-muted text-center">
                        <i class="fas fa-info-circle me-1"></i>
                        Tous les champs de confirmation doivent être cochés
                      </small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="mt-3 no-print">
            <a href="inventaire_liste.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Options d'impression -->
    <div class="modal fade" id="printOptionsModal" tabindex="-1" aria-labelledby="printOptionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printOptionsModalLabel">
                        <i class="fas fa-print me-2"></i>Options d'impression - Système 4 comptages
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">
                                <i class="fas fa-layer-group me-2"></i>Impression par catégorie
                            </h6>
                            <div class="mb-3">
                                <label class="form-label">Sélectionner une catégorie :</label>
                                <select id="printCategorie" class="form-select">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type de comptage :</label>
                                <select id="printTypeComptage" class="form-select">
                                    <option value="comptage1">1er Comptage (fiches vides)</option>
                                    <option value="comptage2">2ème Comptage (vérification)</option>
                                    <option value="comptage3">3ème Comptage (contrôle)</option>
                                    <option value="comptage4">4ème Comptage (audit final)</option>
                                </select>
                                <small class="text-muted">
                                    <strong>1er :</strong> Comptage initial avec fiches vides<br>
                                    <strong>2ème :</strong> Vérification avec données du 1er comptage<br>
                                    <strong>3ème :</strong> Contrôle par personne différente<br>
                                    <strong>4ème :</strong> Audit final par superviseur
                                </small>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="printEcartOnly">
                                    <label class="form-check-label" for="printEcartOnly">
                                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                        Imprimer uniquement les articles avec écart
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Utile pour les comptages de vérification et contrôle
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">
                                <i class="fas fa-shield-alt me-2"></i>Système anti-erreurs humaines
                            </h6>
                            <div class="alert alert-info">
                                <h6>🎯 Processus recommandé :</h6>
                                <ol class="mb-0">
                                    <li><strong>1er comptage :</strong> Compteur principal → Fiches vides</li>
                                    <li><strong>2ème comptage :</strong> Même compteur → Vérification</li>
                                    <li><strong>3ème comptage :</strong> Compteur différent → Contrôle</li>
                                    <li><strong>4ème comptage :</strong> Superviseur → Audit final</li>
                                </ol>
                            </div>
                            <div class="alert alert-warning">
                                <h6>⚠️ Critères de validation :</h6>
                                <ul class="mb-0">
                                    <li>Écart max entre comptages : ±2 unités</li>
                                    <li>Consensus on 3 comptages minimum</li>
                                    <li>Validation obligatoire du superviseur</li>
                                    <li>Documentation des écarts persistants</li>
                                </ul>
                            </div>
                            <div class="alert alert-success">
                                <h6>✅ Avantages du système :</h6>
                                <ul class="mb-0">
                                    <li>Réduction drastique des erreurs</li>
                                    <li>Traçabilité complète</li>
                                    <li>Validation multi-niveaux</li>
                                    <li>Audit trail professionnel</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations sur les comptages existants -->
                    <div class="mt-4">
                        <h6 class="mb-3">
                            <i class="fas fa-history me-2"></i>Historique des comptages
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Comptage</th>
                                        <th>Date</th>
                                        <th>Compteur</th>
                                        <th>Articles comptés</th>
                                        <th>Écarts détectés</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Récupérer l'historique des comptages
                                    try {
                                        $stmt = $cnx->prepare("
                                            SELECT 
                                                it.id_utilisateur,
                                                it.date_saisie,
                                                COUNT(DISTINCT it.id_article) as nb_articles,
                                                COUNT(DISTINCT CASE WHEN it.ecart != 0 THEN it.id_article END) as nb_ecarts
                                            FROM inventaire_temp it
                                            WHERE it.id_inventaire = ?
                                            GROUP BY it.id_utilisateur, DATE(it.date_saisie)
                                            ORDER BY it.date_saisie ASC
                                        ");
                                        $stmt->execute([$idInventaire]);
                                        $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Exception $e) {
                                        $historique = [];
                                    }
                                    
                                    foreach ($historique as $index => $comptage):
                                        $numero = $index + 1;
                                        $statut = $numero <= 4 ? 'En cours' : 'Terminé';
                                        $couleur = $numero <= 4 ? 'success' : 'secondary';
                                    ?>
                                    <tr>
                                        <td><strong>Comptage <?php echo $numero; ?></strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($comptage['date_saisie'])); ?></td>
                                        <td><?php echo htmlspecialchars($comptage['id_utilisateur']); ?></td>
                                        <td><?php echo $comptage['nb_articles']; ?> articles</td>
                                        <td>
                                            <?php if ($comptage['nb_ecarts'] > 0): ?>
                                                <span class="badge bg-warning text-dark"><?php echo $comptage['nb_ecarts']; ?> écarts</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Aucun écart</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?php echo $couleur; ?>"><?php echo $statut; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($historique)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <em>Aucun comptage effectué pour le moment</em>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="printInventory()">
                        <i class="fas fa-print me-1"></i>Imprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Fonction pour calculer les totaux
    function calculerTotaux() {
        let totalTheorique = 0;
        let totalPhysique = 0;
        let totalEcart = 0;
        let totalValeurEcart = 0;

        document.querySelectorAll('.table-inventaire tbody tr').forEach(row => {
            const qteInput = row.querySelector('.qte-physique');
            if (!qteInput) return;

            const theorique = parseInt(qteInput.dataset.theorique) || 0;
            const physique = parseInt(qteInput.value) || 0;
            const prixAchat = parseFloat(qteInput.dataset.prixAchat) || 0;
            const ecart = physique - theorique;
            const valeurEcart = ecart * prixAchat;

            totalTheorique += theorique;
            totalPhysique += physique;
            totalEcart += ecart;
            totalValeurEcart += valeurEcart;
        });

        // Correction ici :
        const footer = document.querySelector('.table-inventaire tfoot');
        if (footer) {
            const totalRow = footer.querySelector('.total-row');
            if (
                totalRow &&
                totalRow.children[4] && totalRow.children[5] &&
                totalRow.children[6] && totalRow.children[7] &&
                totalRow.children[8]
            ) {
                totalRow.children[4].textContent = totalTheorique;
                totalRow.children[5].textContent = totalPhysique;
                totalRow.children[6].textContent = totalPhysique;

                // Mise à jour de l'écart
                const ecartCell = totalRow.children[7];
                if (totalEcart < 0) {
                    ecartCell.innerHTML = '<span class="ecart-negatif">' + totalEcart + '</span>';
                } else if (totalEcart > 0) {
                    ecartCell.innerHTML = '<span class="ecart-positif">+' + totalEcart + '</span>';
                } else {
                    ecartCell.innerHTML = '<span class="ecart-zero">0</span>';
                }

                // Mise à jour de la valeur de l'écart
                const valeurEcartCell = totalRow.children[8];
                if (totalValeurEcart < 0) {
                    valeurEcartCell.innerHTML = '<span class="ecart-negatif">' + totalValeurEcart.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F.CFA</span>';
                } else if (totalValeurEcart > 0) {
                    valeurEcartCell.innerHTML = '<span class="ecart-positif">+' + totalValeurEcart.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F.CFA</span>';
                } else {
                    valeurEcartCell.innerHTML = '<span class="ecart-zero">0 F.CFA</span>';
                }
            }
        }
    }

    // Fonction pour sauvegarder les données temporaires
    function saveTempData(row) {
        const qteInput = row.querySelector('.qte-physique');
        const seriesInputs = row.querySelectorAll('.series-input');
        const idArticle = qteInput.dataset.idArticle;
        
        const numSeries = Array.from(seriesInputs)
            .map(input => input.value.trim())
            .filter(Boolean);
        
        const data = {
            action: 'save_temp',
            id_inventaire: <?php echo $idInventaire; ?>,
            id_article: idArticle,
            qte_physique: qteInput.value,
            num_series: numSeries
        };
        
        // Afficher l'indicateur de sauvegarde en cours
        const saveIndicator = row.querySelector('.save-indicator') || document.createElement('span');
        saveIndicator.className = 'save-indicator text-info ms-2';
        saveIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        if (!row.querySelector('.save-indicator')) {
            qteInput.parentNode.appendChild(saveIndicator);
        }
        
        // Log pour débogage
        console.log('Envoi des données:', data);
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => {
            console.log('Réponse brute:', response);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                console.log('Réponse texte brute:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erreur parsing JSON:', e);
                    console.error('Texte reçu:', text);
                    throw new Error('Réponse non-JSON reçue du serveur');
                }
            });
        })
        .then(data => {
            console.log('Données reçues:', data);
            if (data.success) {
                saveIndicator.className = 'save-indicator text-success ms-2';
                saveIndicator.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    saveIndicator.remove();
                }, 2000);
            } else {
                throw new Error(data.message || 'Erreur de sauvegarde');
            }
        })
        .catch(error => {
            console.error('Erreur de sauvegarde:', error);
            saveIndicator.className = 'save-indicator text-danger ms-2';
            saveIndicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            setTimeout(() => {
                saveIndicator.remove();
            }, 3000);
        });
    }

    // 1. Gestionnaire d'événement pour la quantité physique
    function attachQteEvents() {
        document.querySelectorAll('.qte-physique').forEach(function(input) {
            input.removeEventListener('input', qteInputHandler);
            input.addEventListener('input', qteInputHandler);
        });
    }

    function qteInputHandler(event) {
        calculerEcart(this);
    }

    // 2. Calcul de l'écart et gestion des champs de série
    function calculerEcart(input) {
        const row = input.closest('tr');
        const theorique = parseInt(input.dataset.theorique) || 0;
        const physique = parseInt(input.value) || 0;
        const prixAchat = parseFloat(input.dataset.prixAchat) || 0;
        const ecart = physique - theorique;
        const valeurEcart = ecart * prixAchat;

        // Mise à jour de la quantité retenue
        row.querySelector('.qte-retenue').textContent = physique;

        // Mise à jour de l'écart
        const ecartCell = row.querySelector('.ecart');
        ecartCell.dataset.ecart = ecart;
        if (ecart < 0) {
            ecartCell.innerHTML = '<span class="ecart-negatif">' + ecart + '</span>';
        } else if (ecart > 0) {
            ecartCell.innerHTML = '<span class="ecart-positif">+' + ecart + '</span>';
        } else {
            ecartCell.innerHTML = '<span class="ecart-zero">0</span>';
        }

        // Mise à jour de la valeur de l'écart
        const valeurEcartCell = row.querySelector('.valeur-ecart');
        if (valeurEcart < 0) {
            valeurEcartCell.innerHTML = '<span class="ecart-negatif">' + valeurEcart.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' F.CFA</span>';
        } else if (valeurEcart > 0) {
            valeurEcartCell.innerHTML = '<span class="ecart-positif">+' + valeurEcart.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits:  0}) + ' F.CFA</span>';
        } else {
            valeurEcartCell.innerHTML = '<span class="ecart-zero">0 F.CFA</span>';
        }

        // Gestion des champs de numéros de série
        const qteCell = row.querySelector('td:nth-child(6)'); // Cellule de la quantité comptée
        let seriesFields = qteCell.querySelector('.series-fields');
        
        if (physique > 0) {
            // Si pas de conteneur de champs de série, le créer
            if (!seriesFields) {
                const seriesContainer = document.createElement('div');
                seriesContainer.className = 'mt-2';
                seriesContainer.innerHTML = `
                    <div class="series-fields" data-id="${row.querySelector('.qte-physique').closest('tr').querySelector('td:first-child').textContent.trim()}" data-series-attendus='[]' data-series-trouves='[]'>
                    </div>
                    <div class="mt-1">
                        <span class="text-danger small manquants"></span>
                        <span class="text-warning small en-trop"></span>
                    </div>
                `;
                qteCell.appendChild(seriesContainer);
                seriesFields = seriesContainer.querySelector('.series-fields');
            }
            updateSeriesFields(row);
        } else {
            // Si quantité = 0, supprimer les champs de série
            if (seriesFields) {
                seriesFields.closest('.mt-2').remove();
            }
        }

        // Recalculer les totaux
        calculerTotaux();

        // Sauvegarder les données temporaires
        saveTempData(row);
    }

    // 3. Génération dynamique des champs de numéros de série
    function updateSeriesFields(row) {
        const qteInput = row.querySelector('.qte-physique');
        const seriesFields = row.querySelector('.series-fields');
        if (!seriesFields) return;

        const nb = parseInt(qteInput.value) || 0;
        
        // Récupérer les valeurs existantes depuis les champs actuels AVANT de les supprimer
        const existingInputs = seriesFields.querySelectorAll('.series-input');
        let trouves = Array.from(existingInputs).map(input => input.value.trim()).filter(Boolean);
        
        // Si pas de valeurs dans les champs, essayer de récupérer depuis le dataset
        if (trouves.length === 0 && seriesFields.dataset.seriesTrouves) {
            try {
                trouves = JSON.parse(seriesFields.dataset.seriesTrouves);
            } catch (e) {
                trouves = [];
            }
        }

        // Vider le conteneur et recréer les champs
        seriesFields.innerHTML = '';
        
        for (let i = 0; i < nb; i++) {
            const val = trouves[i] ? trouves[i] : '';
            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'input-group input-group-sm mb-1';
            fieldDiv.innerHTML = `
                <input type="text" 
                       class="form-control form-control-sm series-input" 
                       name="series_trouves[]" 
                       placeholder="Numéro de série ${i + 1}"
                       data-index="${i}"
                       value="${val}">
            `;
            seriesFields.appendChild(fieldDiv);
        }

        // Mettre à jour le dataset avec les valeurs actuelles
        seriesFields.dataset.seriesTrouves = JSON.stringify(trouves);

        // Attacher les événements sur les nouveaux champs
        seriesFields.querySelectorAll('.series-input').forEach(input => {
            input.addEventListener('input', function() {
                validateSeries(seriesFields);
                const row = input.closest('tr');
                saveTempData(row);
            });
        });
    }

    // 4. Validation des numéros de série
    function validateSeries(container) {
        const row = container.closest('tr');
        const attendus = container.dataset.seriesAttendus ? JSON.parse(container.dataset.seriesAttendus) : [];
        const trouves = Array.from(container.querySelectorAll('.series-input'))
            .map(input => input.value.trim())
            .filter(Boolean);

        // Mettre à jour le dataset avec les valeurs actuelles
        container.dataset.seriesTrouves = JSON.stringify(trouves);

        // Manquants = attendus non trouvés (seulement s'il y a des numéros attendus)
        const manquants = attendus.length > 0 ? attendus.filter(ns => !trouves.includes(ns)) : [];
        // En trop = trouvés non attendus
        const enTrop = attendus.length > 0 ? trouves.filter(ns => !attendus.includes(ns)) : trouves;

        // Affichage des résultats
        const manquantsSpan = container.parentNode.querySelector('.manquants');
        const enTropSpan = container.parentNode.querySelector('.en-trop');

        // Réinitialiser les classes CSS
        manquantsSpan.className = 'text-danger small manquants';
        enTropSpan.className = 'text-warning small en-trop';

        // Gestion des messages manquants
        if (manquants.length > 0) {
            manquantsSpan.textContent = 'Manquants : ' + manquants.join(', ');
        } else {
            manquantsSpan.textContent = '';
        }

        // Gestion des messages en trop/nouveaux
        if (enTrop.length > 0) {
            if (attendus.length > 0) {
            enTropSpan.textContent = 'En trop : ' + enTrop.join(', ');
                enTropSpan.className = 'text-warning small en-trop';
            } else {
                enTropSpan.textContent = 'Nouveaux numéros : ' + enTrop.join(', ');
                enTropSpan.className = 'text-info small en-trop';
            }
        } else {
            enTropSpan.textContent = '';
        }

        // Messages de validation positive
        if (attendus.length > 0) {
            if (trouves.length === attendus.length && manquants.length === 0 && enTrop.length === 0) {
                // Tous les numéros attendus sont présents et aucun en trop
            enTropSpan.textContent = 'Tous les numéros attendus sont présents ✓';
            enTropSpan.className = 'text-success small en-trop';
            } else if (trouves.length === attendus.length && manquants.length === 0 && enTrop.length > 0) {
                // Tous les attendus sont présents mais il y a des numéros en trop
                enTropSpan.textContent = 'Tous les attendus présents + ' + enTrop.length + ' en trop ✓';
                enTropSpan.className = 'text-success small en-trop';
            } else if (trouves.length < attendus.length && manquants.length > 0) {
                // Il manque des numéros
                manquantsSpan.textContent = 'Manquants : ' + manquants.join(', ');
                manquantsSpan.className = 'text-danger small manquants';
            }
        } else if (trouves.length > 0) {
            // Pas de numéros attendus mais des numéros trouvés
            enTropSpan.textContent = 'Nouveaux numéros : ' + trouves.join(', ');
            enTropSpan.className = 'text-info small en-trop';
        }

        // Sauvegarder les données temporaires
        saveTempData(row);
    }

    // 5. Initialisation au chargement
    document.addEventListener('DOMContentLoaded', function() {
        attachQteEvents();
        // Initialiser les champs de numéros de série pour chaque ligne qui en a
        document.querySelectorAll('.series-fields').forEach(function(seriesFields) {
            const row = seriesFields.closest('tr');
            // Si une quantité physique existe, on génère les champs
            const qteInput = row.querySelector('.qte-physique');
            if (qteInput && parseInt(qteInput.value) > 0) {
                updateSeriesFields(row);
                // Valider les numéros de série après initialisation
                validateSeries(seriesFields);
            }
        });

        // Amélioration de l'expérience utilisateur des filtres
        enhanceFilterExperience();
    });

    // 6. Amélioration de l'expérience utilisateur des filtres
    function enhanceFilterExperience() {
        // Auto-submit des filtres avec délai pour éviter trop de requêtes
        const filterSelects = document.querySelectorAll('select[name^="filtre"]');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                // Ajouter un indicateur de chargement
                const loadingIndicator = document.createElement('div');
                loadingIndicator.className = 'position-fixed top-50 start-50 translate-middle';
                loadingIndicator.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div>';
                document.body.appendChild(loadingIndicator);
                
                // Soumettre le formulaire après un court délai
                setTimeout(() => {
                    this.form.submit();
                }, 100);
            });
        });

        // Amélioration de la recherche
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });

            // Focus automatique sur le champ de recherche
            searchInput.focus();
        }

        // Bouton pour effacer les filtres
        const clearFiltersBtn = document.querySelector('a[href*="IDINVENTAIRE"]');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Voulez-vous vraiment effacer tous les filtres ?')) {
                    window.location.href = this.href;
                }
            });
        }

        // Mise en surbrillance des résultats de recherche
        highlightSearchResults();
    }

    // 7. Mise en surbrillance des résultats de recherche
    function highlightSearchResults() {
        const searchTerm = new URLSearchParams(window.location.search).get('search');
        if (searchTerm && searchTerm.length > 0) {
            const searchRegex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            const cells = document.querySelectorAll('td');
            
            cells.forEach(cell => {
                const text = cell.textContent;
                if (searchRegex.test(text)) {
                    cell.innerHTML = text.replace(searchRegex, '<span class="search-highlight">$1</span>');
                }
            });
        }
    }

    // 8. Fonctions pour l'impression
    function showPrintOptions() {
        const modal = new bootstrap.Modal(document.getElementById('printOptionsModal'));
        modal.show();
    }

    function printInventory() {
        const categorie = document.getElementById('printCategorie').value;
        const ecartsOnly = document.getElementById('printEcartOnly').checked;
        const typeComptage = document.getElementById('printTypeComptage').value;
        
        // Construire l'URL d'impression
        let url = 'inventaire_impression_categorie.php?IDINVENTAIRE=<?php echo $idInventaire; ?>';
        
        if (categorie) {
            url += '&categorie=' + encodeURIComponent(categorie);
        }
        
        if (ecartsOnly) {
            url += '&ecarts=1';
        }
        
        // Ajouter le type de comptage
        url += '&type_comptage=' + encodeURIComponent(typeComptage);
        
        // Fermer le modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('printOptionsModal'));
        modal.hide();
        
        // Ouvrir l'impression dans la même page
        window.location.href = url;
    }

    // 9. Fonctions de validation sécurisée
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion des checkboxes de confirmation
        const checkboxes = [
            document.getElementById('confirmValidation1'),
            document.getElementById('confirmValidation2'),
            document.getElementById('confirmValidation3')
        ];
        
        const btnValidation = document.getElementById('btnValidationSecurisee');
        
        // Vérifier l'état des checkboxes
        function verifierConfirmations() {
            const toutesCochees = checkboxes.every(cb => cb.checked);
            btnValidation.disabled = !toutesCochees;
            
            if (toutesCochees) {
                btnValidation.classList.remove('btn-danger');
                btnValidation.classList.add('btn-warning');
                btnValidation.innerHTML = '<i class="fas fa-unlock me-2"></i>PRÊT À VALIDER';
            } else {
                btnValidation.classList.remove('btn-warning');
                btnValidation.classList.add('btn-danger');
                btnValidation.innerHTML = '<i class="fas fa-lock me-2"></i>VALIDER L\'INVENTAIRE';
            }
        }
        
        // Attacher les événements aux checkboxes
        checkboxes.forEach(cb => {
            cb.addEventListener('change', verifierConfirmations);
        });
        
        // Désactiver la touche Entrée sur les champs de saisie pour éviter la validation accidentelle
        document.querySelectorAll('input[type="number"], input[type="text"]').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    // Optionnel : afficher un message d'avertissement
                    showEnterKeyWarning();
                }
            });
        });
    });

    // Fonction de validation sécurisée avec double confirmation
    function validerInventaireSecurise() {
        // Première confirmation
        const confirmation1 = confirm(
            '⚠️ ATTENTION - VALIDATION D\'INVENTAIRE\n\n' +
            'Êtes-vous ABSOLUMENT sûr de vouloir valider cet inventaire ?\n\n' +
            'Cette action va :\n' +
            '• Mettre à jour définitivement tous les stocks\n' +
            '• Créer des corrections automatiques\n' +
            '• Fermer l\'inventaire\n' +
            '• Cette action est IRRÉVERSIBLE\n\n' +
            'Cliquez sur "OK" pour continuer ou "Annuler" pour abandonner.'
        );
        
        if (!confirmation1) {
            return false;
        }
        
        // Deuxième confirmation avec code de sécurité
        const codeSecurite = prompt(
            '🔐 CODE DE SÉCURITÉ REQUIS\n\n' +
            'Pour finaliser la validation, entrez le code de sécurité :\n' +
            'Code : VALIDER-' + new Date().getFullYear()
        );
        
        if (!codeSecurite || codeSecurite !== 'VALIDER-' + new Date().getFullYear()) {
            alert('❌ Code de sécurité incorrect. Validation annulée.');
            return false;
        }
        
        // Troisième confirmation finale
        const confirmationFinale = confirm(
            '🚨 VALIDATION FINALE\n\n' +
            'Dernière chance de confirmer la validation de l\'inventaire.\n\n' +
            'Cette action va être exécutée IMMÉDIATEMENT et ne pourra pas être annulée.\n\n' +
            'Êtes-vous PRÊT à valider définitivement cet inventaire ?'
        );
        
        if (!confirmationFinale) {
            alert('Validation annulée par l\'utilisateur.');
            return false;
        }
        
        // Si toutes les confirmations sont passées, soumettre le formulaire
        alert('✅ Validation confirmée. L\'inventaire va être validé...');
        
        // Créer un formulaire temporaire pour la soumission
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        // Ajouter tous les champs du formulaire principal
        const formPrincipal = document.getElementById('inventaireForm');
        const inputs = formPrincipal.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (input.name && input.value !== undefined) {
                const clone = input.cloneNode(true);
                form.appendChild(clone);
            }
        });
        
        // Ajouter le bouton de soumission au body et le déclencher
        document.body.appendChild(form);
        form.submit();
    }

    // Fonction pour afficher un avertissement quand la touche Entrée est pressée
    function showEnterKeyWarning() {
        // Créer une notification temporaire
        const notification = document.createElement('div');
        notification.className = 'position-fixed top-50 start-50 translate-middle alert alert-warning alert-dismissible';
        notification.style.zIndex = '9999';
        notification.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attention !</strong> La touche Entrée est désactivée pour éviter les validations accidentelles.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Supprimer la notification après 3 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
    </script>
</body>
</html>