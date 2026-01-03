<?php
// ===== PROTECTION CONTRE LES ÉCRANS NOIRS EN PRODUCTION =====
// Buffer de sortie pour capturer toutes les erreurs
ob_start();

// Gestionnaire d'erreurs robuste pour éviter les écrans noirs
set_error_handler(function($severity, $message, $file, $line) {
    error_log("ERREUR PHP: $message dans $file ligne $line");
    
    // Si erreur fatale, envoyer réponse JSON propre
    if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
        exit;
    }
    return true; // Empêche l'affichage par défaut
});

// Gestionnaire d'exceptions pour éviter les écrans noirs
set_exception_handler(function($exception) {
    error_log("EXCEPTION: " . $exception->getMessage() . " dans " . $exception->getFile() . " ligne " . $exception->getLine());
    
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
    exit;
});

// Gestionnaire d'arrêt pour capturer les erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        error_log("ARRÊT FATAL: " . $error['message'] . " dans " . $error['file'] . " ligne " . $error['line']);
        
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
        exit;
    }
});

// Fonction utilitaire pour envoyer des réponses JSON propres
function sendJsonResponse($success, $message, $data = null, $code = 200) {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Fonction pour nettoyer le buffer avant les headers
function cleanBuffer() {
    if (ob_get_level() > 0) {
        ob_clean();
    }
}

// ===== FIN PROTECTION =====

error_log("REQUEST = " . json_encode($_REQUEST));
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs pour les clients
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Garder le logging des erreurs
error_log("===== Suppression déclenchée à " . date("Y-m-d H:i:s") . " =====");

// Gestionnaires d'erreurs supprimés - Utilisation de ceux en tête du fichier

session_start();
if (!isset($_SESSION['id_utilisateur']) && (!isset($_POST['connexion_admin']) && !isset($_POST['deconnexion_admin']))) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('../db/connecting.php');
include("fonction.php");
include ('notifications.php');
cleanBuffer();
header('Content-Type: application/json');

$date_aujourdhui = date('Y-m-d');
$date_aujourdhui2 = date('d-m-Y');

if (isset($_POST['deconnexion_admin'])) {
    // Journalisation avant déconnexion
    $startTime = log_action_start();
    logSystemAction($cnx, 'DECONNEXION', 'AUTHENTIFICATION', 'request.php', 
        'Déconnexion utilisateur: ' . ($_SESSION['nom_utilisateur'] ?? 'Inconnu'), 
        null, null, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
    
    deconnexion();
}

    
if (isset($_POST['connexion_admin'])) {
    $type = 'utilisateur';
    $identifiant = $_POST['Identifiant'];
    $motdepasse = $_POST['mdp'];
    
    // Journalisation tentative de connexion
    $startTime = log_action_start();
    logSystemAction($cnx, 'TENTATIVE_CONNEXION', 'AUTHENTIFICATION', 'request.php', 
        'Tentative de connexion pour: ' . $identifiant, 
        null, null, 'CRITICAL', 'PENDING', log_action_end($startTime));
    
    connexion($identifiant, $motdepasse, $type);
}

if (isset($_POST['creer_article'])) {
    
    $code_article = $_POST['CodeArticle'];
    $libelle = $_POST['Libelle'];
    $description = $_POST['Description'];
    $marque = $_POST['Marque'];
    $prix_achat = $_POST['PrixAchat'];
    $prix_vente = $_POST['PrixVente'];
    $categorie = $_POST['Categorie'];
    $file = $_FILES['Photo'];

    $tableName = "article";
    $redirection = '../creation_d_article.php';
    $upload_dir = '../image_article/';
    $max_size = 2 * 1024 * 1024;
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

    $uploadResult = changer_image($file, $allowed_exts, $max_size, $upload_dir, $redirection);
    if ($uploadResult['success']) {
        $photo_path = $uploadResult['file_path'];
        $data = [
            'CodePersoArticle' => $code_article,
            'libelle' => $libelle,
            'descriptif' => $description,
            'marque' => $marque,
            'PrixAchatHT' => $prix_achat,
            'PrixInitialHT' => $prix_achat, // ⚠️ champ essentiel pour restaurer PMP
            'prixVenteTTC' => $prix_vente,
            'photo' => $photo_path,
            'id_categorie' => $categorie
        ];

        // 🔒 SÉCURISATION : Transaction complète pour éviter les conflits
        $cnx->beginTransaction();
        
        try {
            $startTime = log_action_start();
            
            // 🔒 Vérification avec verrou FOR UPDATE pour éviter les doublons simultanés
            $stmt = $cnx->prepare("SELECT COUNT(*) as count FROM article WHERE CodePersoArticle = ? OR libelle = ? FOR UPDATE");
            $stmt->execute([$code_article, $libelle]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing['count'] > 0) {
                $cnx->rollBack();
                $erreur = "Un article avec ce code ou ce libellé existe déjà.";
                header('Location: ' . $redirection . '?error=' . urlencode($erreur));
                exit();
            }
            
            // 🔒 Insertion sécurisée avec gestion d'erreurs
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $sql = "INSERT INTO $tableName ($columns) VALUES ($placeholders)";
            $stmt = $cnx->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            if ($stmt->execute()) {
                $idArticle = $cnx->lastInsertId();
                $idUtilisateur = $_SESSION['id_utilisateur'];
                
                // Journalisation création article (système unifié uniquement)
                logSystemAction($cnx, 'CREATION_ARTICLE', 'PRODUITS', 'request.php', 
                    'Création article: ' . $libelle . ' (Code: ' . $code_article . ', ID: ' . $idArticle . ')', 
                    null, $data, 'HIGH', 'SUCCESS', log_action_end($startTime));
                
                $stock_aticle = verifier_element('stock' ,["IDARTICLE"],[$idArticle],"");
                if (isset($stock_aticle["IDSTOCK"])) {
                    $idStock= $stock_aticle["IDSTOCK"];
                }
                
                // 🔒 Validation de la transaction
                $cnx->commit();
                
                $success = "L'article a été créé avec succès.";
                header('Location: ' . $redirection . '?success=' . urlencode($success));
                exit();
            } else {
                $cnx->rollBack();
                logSystemAction($cnx, 'CREATION_ARTICLE', 'PRODUITS', 'request.php', 
                    'Échec création article: ' . $libelle . ' (Code: ' . $code_article . ')', 
                    null, $data, 'HIGH', 'FAILED', log_action_end($startTime));
                $erreur = "Erreur lors de l'insertion de l'article.";
                header('Location: ' . $redirection . '?error=' . urlencode($erreur));
                exit();
            }
            
        } catch (Exception $e) {
            // 🔒 Rollback automatique en cas d'erreur
            $cnx->rollBack();
            
            // Journalisation de l'erreur
            logSystemAction($cnx, 'CREATION_ARTICLE', 'PRODUITS', 'request.php', 
                'Erreur création article: ' . $libelle . ' - ' . $e->getMessage(), 
                null, $data, 'CRITICAL', 'FAILED', log_action_end($startTime));
            
            $erreur = "Erreur lors de la création de l'article: " . $e->getMessage();
            header('Location: ' . $redirection . '?error=' . urlencode($erreur));
            exit();
        }

    }
    else {
        $erreur = "Erreur lors de l'insertion dans la table $tableName.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
}

if (isset($_POST['mocreer_article'])) {

    
    $id_article = $_POST['id_article'];
    $molibelle = $_POST['moLibelle'];
    $modescription = $_POST['moDescription'];
    $momarque = $_POST['moMarque'];
    $moprix_achat = $_POST['moPrixAchat'];
    $moprix_vente = $_POST['moPrixVente'];
    $mofile = $_FILES['moPhoto'];
    $photo_path = null;

    $tableName = "article";
    $redirection = '../liste_article.php';
    $upload_dir = '../image_article/';
    $max_size = 2 * 1024 * 1024;
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

    $ancien = verifier_element($tableName, ['IDARTICLE'], [$id_article], $redirection);

    $details = "l'article a été modifier le " . date('Y-m-d H:i:s') . " par " . $_SESSION['nom_complet'];
    $modifications = [
        'Libellé' => ['old' => $ancien['libelle'], 'new' => $molibelle],
        'Description' => ['old' => $ancien['Descriptif'], 'new' => $modescription],
        'Marque' => ['old' => $ancien['marque'], 'new' => $momarque],
        'Prix d\'Achat HT' => ['old' => floatval($ancien['PrixAchatHT']), 'new' => floatval($moprix_achat)],
        'Prix de Vente TTC' => ['old' => floatval($ancien['PrixVenteTTC']), 'new' => floatval($moprix_vente)]
    ];
    foreach ($modifications as $field => $values) {
        if ($values['old'] !== $values['new']) {
            $details .= " $field: '" . $values['old'] . "' à '" . $values['new'] . "', ";
        }
    }
    $details = rtrim($details, ', ');

    if ($mofile['error'] != UPLOAD_ERR_NO_FILE) {
        $uploadResult = changer_image($mofile, $allowed_exts, $max_size, $upload_dir, $redirection);

        if (isset($uploadResult['success']) && $uploadResult['success']) {
            $photo_path = $uploadResult['file_path'];
            $details .= " Photo modifiée, ";
        }
    }

    $columns = [
        'libelle',
        'Descriptif',
        'marque',
        'PrixAchatHT',
        'prixVenteTTC',
    ];

    $values = [
        $molibelle,
        $modescription,
        $momarque,
        $moprix_achat,
        $moprix_vente,
    ];
    if ($photo_path !== null) {
        $columns[] = 'photo';
        $values[] = $photo_path;
    }
    $startTime = log_action_start();
    modifier_element($tableName, $columns, $values, 'IDARTICLE', $id_article, $redirection);
    
    // Journalisation modification article
    logSystemAction($cnx, 'MODIFICATION_ARTICLE', 'PRODUITS', 'request.php', 
        'Modification article: ' . $molibelle . ' (ID: ' . $id_article . ') - ' . $details, 
        $ancien, [
            'libelle' => $molibelle,
            'Descriptif' => $modescription,
            'marque' => $momarque,
            'PrixAchatHT' => $moprix_achat,
            'prixVenteTTC' => $moprix_vente,
            'photo' => $photo_path
        ], 'HIGH', 'SUCCESS', log_action_end($startTime));
    
    $success = "La modification a été effectuée.";
    header('Location: ../liste_article.php?success=' . urlencode($success));
    exit();
}

if (isset($_POST['supprimer_article'])) {
    $id_article = $_POST['id_article'];
    $tableName = "article";
    $redirection = '../liste_article.php';
    
    // Récupérer les données avant suppression
    $stmt = $cnx->prepare("SELECT * FROM article WHERE IDARTICLE = ?");
    $stmt->execute([$id_article]);
    $articleAvant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $startTime = log_action_start();
    modifier_element($tableName, ['desactiver'], ['oui'], 'IDARTICLE', $id_article, $redirection);
    
    // Journalisation désactivation article
    logSystemAction($cnx, 'DESACTIVATION_ARTICLE', 'PRODUITS', 'request.php', 
        'Désactivation article: ' . ($articleAvant['libelle'] ?? 'Inconnu') . ' (ID: ' . $id_article . ')', 
        $articleAvant, ['desactiver' => 'oui'], 'HIGH', 'SUCCESS', log_action_end($startTime));
    
    $erreur = "Un article a été Désactivé avec succès";
    header('Location: ' . $redirection . '?error=' . urlencode($erreur));
    exit();
}
if (isset($_POST['reactiver_article'])) {
    $id_article = $_POST['id_article'];
    $tableName = "article";
    $redirection = '../liste_article.php';
    
    // Récupérer les données avant réactivation
    $stmt = $cnx->prepare("SELECT * FROM article WHERE IDARTICLE = ?");
    $stmt->execute([$id_article]);
    $articleAvant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $startTime = log_action_start();
    modifier_element($tableName, ['desactiver'], ['non'], 'IDARTICLE', $id_article, $redirection);
    
    // Journalisation réactivation article
    logSystemAction($cnx, 'REACTIVATION_ARTICLE', 'PRODUITS', 'request.php', 
        'Réactivation article: ' . ($articleAvant['libelle'] ?? 'Inconnu') . ' (ID: ' . $id_article . ')', 
        $articleAvant, ['desactiver' => 'non'], 'HIGH', 'SUCCESS', log_action_end($startTime));
    
    $success = "L'article a été réactivé avec succès";
    header('Location: ' . $redirection . '?success=' . urlencode($success));
    exit();
}

if (isset ($_POST['creer_categorie'])) {
    $nom_categorie = $_POST['nom_categorie'];
    $tableName = "categorie_article";
    $redirection = '../categorie_article.php';
    
    // Vérification si la catégorie existe déjà
    $data = ['nom_categorie' => $nom_categorie];
    $values = [$nom_categorie];
    $columns = ['nom_categorie'];
    
    $count = verifier_element($tableName, $columns, $values, $redirection);
    
    if ($count > 0) {
        $erreur = "Une catégorie avec ce nom existe déjà";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    } else {
        // Insertion de la catégorie
        $startTime = log_action_start();
        if (insertion_element($tableName, $data, $redirection)) {
            $idCategorie = $cnx->lastInsertId();
            
            // Journalisation création catégorie
            // Journalisation de la création (système unifié)
            logSystemAction($cnx, 'CREATION_CATEGORIE', 'PRODUITS', 'request.php', 
                'Création catégorie: ' . $nom_categorie . ' (ID: ' . $idCategorie . ')', 
                null, $data, 'HIGH', 'SUCCESS', log_action_end($startTime));
            
            
            $success = "La catégorie a été créée avec succès";
            header('Location: ' . $redirection . '?success=' . urlencode($success));
            exit();
        }
    }
}

if (isset($_POST['modifier_categorie'])) {

    $id_categorie = $_POST['id_categorie'];
    $nom_categorie = $_POST['nom_categorie'];
    $tableName = "categorie_article";
    $redirection = '../categorie_article.php';
    
    // Récupérer les données avant modification
    $stmt = $cnx->prepare("SELECT * FROM categorie_article WHERE id_categorie = ?");
    $stmt->execute([$id_categorie]);
    $categorieAvant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $startTime = log_action_start();
    modifier_element($tableName, ['nom_categorie'], [$nom_categorie], 'id_categorie', $id_categorie, $redirection);
    
    // Journalisation modification catégorie
    logSystemAction($cnx, 'MODIFICATION_CATEGORIE', 'PRODUITS', 'request.php', 
        'Modification catégorie: ' . ($categorieAvant['nom_categorie'] ?? 'Inconnu') . ' → ' . $nom_categorie, 
        $categorieAvant, ['nom_categorie' => $nom_categorie], 'HIGH', 'SUCCESS', log_action_end($startTime));
    
    $success = "La modification a été effectuée.";
    header('Location: ../categorie_article.php?success=' . urlencode($success));
    exit();
}


if (isset($_POST['supprimer_categorie'])) {
    
    $idCategorie = $_POST['id_categorie'];
    $tableName = "categorie_article";
    $redirection = '../categorie_article.php';

    // Récupérer les données complètes avant suppression
    $sql = "SELECT * FROM categorie_article WHERE id_categorie = ?";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$idCategorie]);
    $categorieAvant = $stmt->fetch(PDO::FETCH_ASSOC);
    $nomCategorie = $categorieAvant['nom_categorie'];

    // Suppression
    $idColumn = 'id_categorie';
    $idValue = $idCategorie;

    $startTime = log_action_start();
    
    // Suppression directe sans redirection automatique
    $sql = "DELETE FROM $tableName WHERE $idColumn = ?";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(1, $idValue, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Journalisation suppression catégorie (système unifié)
        logSystemAction($cnx, 'SUPPRESSION_CATEGORIE', 'PRODUITS', 'request.php', 
            'Suppression catégorie: ' . $nomCategorie . ' (ID: ' . $idCategorie . ')', 
            $categorieAvant, null, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
        
        $success = "La catégorie a été supprimée avec succès";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
        exit();
    } else {
        // Journalisation de l'échec
        logSystemAction($cnx, 'SUPPRESSION_CATEGORIE', 'PRODUITS', 'request.php', 
            'Échec suppression catégorie: ' . $nomCategorie . ' (ID: ' . $idCategorie . ')', 
            $categorieAvant, null, 'CRITICAL', 'FAILED', log_action_end($startTime));
        
        $erreur = "Erreur lors de la suppression de la catégorie";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
}


if (isset($_POST['enregistrer_caise'])) {
   
    try {
    $nomprenom = trim($_POST['nomprenom'] ?? '');
    $numero = trim($_POST['numero_client'] ?? '');
        $email = trim($_POST['Adresse_email'] ?? '');
    $mode_paiement = $_POST['mode_paiement'] ?? '';
    $montant_total = $_POST['montantTotal'] ?? 0;
    $montant_verse = $_POST['montantVerse'] ?? 0;
    // Calcul de la monnaie à rendre (ne peut pas être négative)
    $monnaie_rendre = max(0, (float)$montant_verse - (float)$montant_total);
    $remiseMontant = $_POST['remiseMontant'] ?? 0;
    $vrai_Montanttotal = $_POST['vrai_Montanttotal'] ?? 0;
    $redirection = '../caisse.php';

        // VALIDATION SERVEUR OBLIGATOIRE - NOM ET TÉLÉPHONE
        if (empty($nomprenom)) {
            throw new Exception("Le nom du client est obligatoire.");
        }
        
        if (empty($numero)) {
            throw new Exception("Le numéro de téléphone du client est obligatoire.");
        }
        
        // Validation du format de téléphone (international)
        if (!preg_match('/^(\+)?[0-9]{8,15}$/', $numero)) {
            throw new Exception("Format de téléphone invalide. Utilisez un numéro de 8 à 15 chiffres avec ou sans indicatif pays (+).");
        }
        
        // Validation du format d'email si fourni (suppression de la restriction)
        // Les emails sont optionnels et acceptent tous les formats valides (icloud, yahoo.fr, etc.)
        
        // Validation des montants
        if ($montant_total < 0) {
            throw new Exception("Le montant total ne peut pas être négatif.");
        }
        
        if ($montant_verse < 0) {
            throw new Exception("Le montant versé ne peut pas être négatif.");
        }
        
        if ($remiseMontant < 0) {
            throw new Exception("La remise ne peut pas être négative.");
        }
        
        if ($remiseMontant > $montant_total) {
            throw new Exception("La remise ne peut pas dépasser le montant total.");
        }

        // Vérifier si le panier n'est pas vide
        if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
            throw new Exception("Le panier est vide.");
        }

        // Vérifier le stock pour chaque article avant de commencer la transaction
        $articles_insuffisants = [];
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            // Compter le nombre de numéros de série pour cet article
            $nombre_series = count($quantites);
            
            // Récupérer les informations de l'article
            $stmt = $cnx->prepare("SELECT a.libelle, s.StockActuel FROM article a LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE WHERE a.IDARTICLE = ?");
            $stmt->execute([$id_article]);
            $article_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$article_info['StockActuel'] || $article_info['StockActuel'] < $nombre_series) {
                $articles_insuffisants[] = [
                    'libelle' => $article_info['libelle'],
                    'stock_actuel' => $article_info['StockActuel'] ?? 0,
                    'quantite_demandee' => $nombre_series
                ];
            }
        }

        // Stocker les articles en stock insuffisant dans la session pour l'affichage
        if (!empty($articles_insuffisants)) {
            $_SESSION['stock_insuffisant'] = $articles_insuffisants;
        }

        // Démarrer une transaction
        $cnx->beginTransaction();

        try {
            // Vérifier si le client existe déjà
            $client_existant = verifier_element('client', ['Telephone'], [$numero], '');
            
            if ($client_existant) {
                // Mettre à jour les informations du client si nécessaire
    $data_client = [
        'NomPrenomClient' => $nomprenom,
                    'Adresse_email' => $email
                ];
                modifier_element('client', ['NomPrenomClient', 'Adresse_email'], [$nomprenom, $email], 'IDCLIENT', $client_existant['IDCLIENT'], '');
                $client_id = $client_existant['IDCLIENT'];
    } else {
                // Créer un nouveau client
                $data_client = [
                    'NomPrenomClient' => $nomprenom,
                    'Telephone' => $numero,
                    'Adresse_email' => $email
                ];
    insertion_element('client', $data_client, '');
    $client_id = $cnx->lastInsertId();
            }

            // Compter le nombre de ventes pour aujourd'hui
            $stmt = $cnx->prepare("SELECT COUNT(*) FROM vente WHERE DATE(DateIns) = ?");
            $stmt->execute([date('Y-m-d')]);
            $nombre_ventes = $stmt->fetchColumn();

            // Nouveau format : V + YYYYMMDD + NNNN (ex: V202509130001)
            $numeroVente = 'V' . date('Ymd') . str_pad($nombre_ventes + 1, 4, '0', STR_PAD_LEFT);

    $data_vente = [
        'NumeroVente' => $numeroVente,
        'IDCLIENT' => $client_id,
        'ModePaiement' => $mode_paiement,
        'MontantTotal' => $montant_total,
        'MontantVerse' => $montant_verse,
        'Monnaie' => $monnaie_rendre,
        'MontantRemise' => $remiseMontant,
        'MontantTotal_sansRemise' => $vrai_Montanttotal,
    ];
    insertion_element('vente', $data_vente, '');
    $vente_id = $cnx->lastInsertId();

            // Enregistrer les articles vendus et mettre à jour le stock
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $quantite) {
                    // Vérifier si le numéro de série existe déjà et s'il est disponible
                    $stmt = $cnx->prepare("
                        SELECT * FROM num_serie 
                        WHERE NUMERO_SERIE = ? 
                        AND statut = 'disponible'
                        AND (ID_VENTE IS NULL OR ID_VENTE = '') 
                        AND (IDvente_credit IS NULL OR IDvente_credit = '')
                    ");
                    $stmt->execute([$numeroSerie]);
                    $num_serie_existant = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$num_serie_existant) {
                        throw new Exception("Le numéro de série " . $numeroSerie . " n'est pas disponible ou n'existe pas.");
                    }

                    // Enregistrer dans facture_article
                $data_facture = [
                    'NumeroVente' => $numeroVente,
                    'IDFactureVente' => $vente_id,
                    'IDARTICLE' => $id_article,
                    'QuantiteVendue' => 1,
                ];
                insertion_element('facture_article', $data_facture, '');

                    // Mettre à jour le numéro de série
                    if ($num_serie_existant) {
                        $data_num_serie = [
                            'ID_VENTE' => $vente_id,
                            'NumeroVente' => $numeroVente,
                            'statut' => 'vendue_credit'
                        ];
                        modifier_element('num_serie', ['ID_VENTE', 'NumeroVente', 'statut'], [$vente_id, $numeroVente, 'vendue'], 'NUMERO_SERIE', $numeroSerie, '');
                    } else {
                $data_num_serie = [
                    'IDARTICLE' => $id_article,
                    'NUMERO_SERIE' => $numeroSerie,
                    'ID_VENTE' => $vente_id,
                    'NumeroVente' => $numeroVente,
                    'statut' => 'vendue'
                ];
                        insertion_element('num_serie', $data_num_serie, '');
                    }
                    // Récupérer le stock actuel
                    $stmt = $cnx->prepare("
    SELECT s.StockActuel, s.IDSTOCK, a.libelle, a.PrixVenteTTC 
    FROM stock s 
    JOIN article a ON s.IDARTICLE = a.IDARTICLE 
    WHERE s.IDARTICLE = ? FOR UPDATE
");

                    $stmt->execute([$id_article]);
                    $stock_actuel = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$stock_actuel) {
                        throw new Exception("Stock non trouvé pour l'article ID: " . $id_article);
                    }

                    $stock_avant = $stock_actuel['StockActuel'];
                    $nouveau_stock = $stock_avant - 1;
                    
                    // Vérification supplémentaire pour éviter les stocks négatifs inattendus
                    if ($nouveau_stock < 0) {
                        error_log("ATTENTION: Stock négatif détecté pour l'article ID: $id_article, Stock avant: $stock_avant, Nouveau stock: $nouveau_stock");
                    }
                    
                    // Mettre à jour le stock même s'il devient négatif
                    $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?");
                    $stmt->execute([$nouveau_stock, $id_article]);

                    // Note: Journalisation individuelle supprimée pour éviter les doublons
                    // La journalisation unifiée se fait après le commit
                }
            }
           // Dans la section où on enregistre la vente (avant le commit)
           $_SESSION['vente_data'] = [
               'numeroVente' => $numeroVente,
               'client' => [
                   'nom' => $nomprenom,
                   'telephone' => $numero,
                   'email' => $email
               ],
               'articles' => [], // On va le remplir avec les détails complets
               'montants' => [
                   'total_sans_remise' => $vrai_Montanttotal,
                   'remise' => $remiseMontant,
                   'total_avec_remise' => $montant_total,
                   'montant_verse' => $montant_verse,
                   'monnaie_rendre' => $monnaie_rendre
               ],
               'mode_paiement' => $mode_paiement,
               'date' => date('d/m/Y'),
               'heure' => date('H:i:s'),
               'vendeur' => $_SESSION['nom_complet']
           ];

           // Récupérer les détails complets des articles
           foreach ($_SESSION['panier'] as $id_article => $quantites) {
               foreach ($quantites as $numeroSerie => $details) {
                   // Récupérer les informations complètes de l'article
                   $stmt = $cnx->prepare("
                       SELECT a.*, ns.NUMERO_SERIE 
                       FROM article a 
                       JOIN num_serie ns ON a.IDARTICLE = ns.IDARTICLE 
                       WHERE a.IDARTICLE = ? AND ns.NUMERO_SERIE = ?
                   ");
                   $stmt->execute([$id_article, $numeroSerie]);
                   $article_details = $stmt->fetch(PDO::FETCH_ASSOC);
                   
                   if ($article_details) {
                       $_SESSION['vente_data']['articles'][] = [
                           'id' => $id_article,
                           'libelle' => $article_details['libelle'],
                           'numero_serie' => $numeroSerie,
                           'prix_unitaire' => $article_details['PrixVenteTTC'],
                           'quantite' => $details['quantite']
                       ];
                   }
               }
           }

           // Récupérer les informations du mode de paiement
           $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
           $stmt->execute([$mode_paiement]);
           $mode_paiement_details = $stmt->fetch(PDO::FETCH_ASSOC);
           $_SESSION['vente_data']['mode_paiement'] = $mode_paiement_details['ModeReglement'];

           // Récupérer les informations de l'entreprise
           $stmt = $cnx->prepare("SELECT * FROM entreprise WHERE id = 1");
           $stmt->execute();
           $entreprise = $stmt->fetch(PDO::FETCH_ASSOC);
           $_SESSION['vente_data']['entreprise'] = $entreprise;

           // Valider la transaction
           $cnx->commit();

           // Journalisation vente réussie (système unifié) - APRÈS le commit
           $startTime = log_action_start();
           
           // Préparer les données détaillées pour la journalisation
           $articles_vente = [];
           foreach ($_SESSION['panier'] as $id_article => $quantites) {
               foreach ($quantites as $numeroSerie => $details) {
                   $articles_vente[] = [
                       'id_article' => $id_article,
                       'libelle' => $details['libelle'] ?? 'Article inconnu',
                       'numero_serie' => $numeroSerie,
                       'prix_unitaire' => $details['prixVenteUnitaire'] ?? 0
                   ];
               }
           }
           
           $donnees_vente = [
               'client' => [
                   'nom' => $nomprenom,
                   'telephone' => $numero,
                   'email' => $email
               ],
               'operateur' => [
                   'id' => $_SESSION['id_utilisateur'],
                   'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                   'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
               ],
               'articles' => $articles_vente,
               'montants' => [
                   'total_sans_remise' => $vrai_Montanttotal,
                   'remise' => $remiseMontant,
                   'total_avec_remise' => $montant_total,
                   'montant_verse' => $montant_verse,
                   'monnaie_rendre' => $monnaie_rendre
               ],
               'numero_vente' => $numeroVente,
               'mode_paiement' => $mode_paiement,
               'date_vente' => date('Y-m-d H:i:s')
           ];
           
           // Créer une description détaillée avec tous les articles
           $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
           $description_detaille = 'Vente comptant: ' . $nomprenom . ' (Opérateur: ' . $operateur_nom . ') - Montant: ' . $montant_total . ' FCFA - N°: ' . $numeroVente;
           if ($remiseMontant > 0) {
               $description_detaille .= ' - Remise: ' . $remiseMontant . ' FCFA';
           }
           $description_detaille .= ' - Articles vendus: ';
           
           $articles_details = [];
           foreach ($articles_vente as $article) {
               $articles_details[] = $article['libelle'] . ' (N°Série: ' . $article['numero_serie'] . ', Prix: ' . $article['prix_unitaire'] . ' FCFA)';
           }
           $description_detaille .= implode(', ', $articles_details);
           
           logSystemAction($cnx, 'VENTE_COMPTANT', 'VENTES', 'request.php', 
               $description_detaille, 
               null, $donnees_vente, 'HIGH', 'SUCCESS', log_action_end($startTime));

          

           // Vider le panier
           unset($_SESSION['panier']);
           header("Location: ../caisse.php?success=vente_enregistree&numero=" . urlencode($numeroVente));
           exit();


        } catch (Exception $e) {
            // En cas d'erreur, annuler la transaction
            if ($cnx->inTransaction()) {
                $cnx->rollBack();
            }
            
            // Journalisation de l'échec de vente
            $startTime = log_action_start();
            $donnees_echec = [
                'client' => [
                    'nom' => $nomprenom ?? 'Inconnu',
                    'telephone' => $numero ?? 'Inconnu',
                    'email' => $email ?? 'Inconnu'
                ],
                'operateur' => [
                    'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                    'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                    'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
                ],
                'montants' => [
                    'total' => $montant_total ?? 0,
                    'remise' => $remiseMontant ?? 0
                ],
                'erreur' => $e->getMessage()
            ];
            
            logSystemAction($cnx, 'VENTE_COMPTANT', 'VENTES', 'request.php', 
                'Échec vente comptant: ' . ($nomprenom ?? 'Client inconnu') . ' - Erreur: ' . $e->getMessage(), 
                null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
            
            throw $e;
        }
    } catch (Exception $e) {
        sendJsonResponse(false, 'Erreur: ' . $e->getMessage(), null, 500);
    }
    unset($_SESSION['panier']);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'multi_paiement') {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        // Log de débogage temporaire
        error_log("=== MULTI-PAIEMENT DEBUG ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session panier: " . print_r($_SESSION['panier'] ?? 'VIDE', true));
        
        $cnx->beginTransaction();

        // Initialisations sécurisées
        $paiements = json_decode($_POST['paiements'] ?? '[]', true);
        if (!is_array($paiements)) $paiements = [];
        error_log("Paiements décodés: " . print_r($paiements, true));

        $montant_total = isset($_POST['montant_total']) ? floatval($_POST['montant_total']) : 0;
        $remiseMontant = isset($_POST['remiseMontant']) ? floatval($_POST['remiseMontant']) : 0;
        error_log("Montants: total=$montant_total, remise=$remiseMontant");

        $nomprenom = trim($_POST['nomprenom'] ?? '');
        $numero_client = trim($_POST['numero_client'] ?? '');
        $email = trim($_POST['Adresse_email'] ?? '');
        error_log("Client: nom=$nomprenom, tel=$numero_client, email=$email");

        $date_vente = date('Y-m-d H:i:s');
        $mode_paiement = 'multi_paiement';

        // Validation des données
        error_log("Début validation des données");
        if (empty($nomprenom)) {
            throw new Exception("Le nom du client est obligatoire.");
        }
        
        if (empty($numero_client)) {
            throw new Exception("Le numéro de téléphone du client est obligatoire.");
        }
        
        // Validation du format de téléphone (international)
        if (!preg_match('/^(\+)?[0-9]{8,15}$/', $numero_client)) {
            throw new Exception("Format de téléphone invalide. Utilisez un numéro de 8 à 15 chiffres avec ou sans indicatif pays (+).");
        }
        
        // Validation du format d'email si fourni (suppression de la restriction)
        // Les emails sont optionnels et acceptent tous les formats valides (icloud, yahoo.fr, etc.)

        if (empty($paiements)) {
            throw new Exception("Aucun paiement fourni.");
        }

        if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
            throw new Exception("Le panier est vide.");
        }
        error_log("Validation des données OK");

        // 1. Vérifier si client existe
        error_log("Recherche du client existant");
        $client_existant = verifier_element('client', ['Telephone'], [$numero_client], '');
        if ($client_existant) {
            error_log("Client existant trouvé, mise à jour");
            modifier_element('client', ['NomPrenomClient', 'Adresse_email'], [$nomprenom, $email], 'IDCLIENT', $client_existant['IDCLIENT'], '');
            $client_id = $client_existant['IDCLIENT'];
        } else {
            error_log("Nouveau client, création");
            $data_client = [
                'NomPrenomClient' => $nomprenom,
                'Telephone' => $numero_client,
                'Adresse_email' => $email
            ];
            insertion_element('client', $data_client, '');
            $client_id = $cnx->lastInsertId();
        }
        error_log("Client ID: $client_id");

        // 2. Générer le numéro de vente
        $stmt = $cnx->prepare("SELECT COUNT(*) FROM vente WHERE DATE(DateIns) = ?");
        $stmt->execute([date('Y-m-d')]);
        $nombre_ventes = $stmt->fetchColumn();

        // Nouveau format : V + YYYYMMDD + NNNN (ex: V202509130001)
        $numeroVente = 'V' . date('Ymd') . str_pad($nombre_ventes + 1, 4, '0', STR_PAD_LEFT);

        // 3. Calculs des montants AVANT insertion
        $montant_verse = 0;
        foreach ($paiements as $paiement) {
            $montant = isset($paiement['montant']) ? floatval($paiement['montant']) : 0;
            $montant_verse += $montant;
        }

        // Calcul de la monnaie à rendre (ne peut pas être négative)
        $monnaie_rendre = max(0, (float)$montant_verse - (float)$montant_total);
        $vrai_Montanttotal = $montant_total + $remiseMontant;

        // 4. Insertion de la vente
        error_log("Préparation insertion vente");
        $data_vente = [
            'NumeroVente' => $numeroVente,
            'IDCLIENT' => $client_id,
            'ModePaiement' => $mode_paiement,
            'MontantTotal' => $montant_total,
            'MontantVerse' => $montant_verse,
            'Monnaie' => $monnaie_rendre,
            'MontantRemise' => $remiseMontant,
            'MontantTotal_sansRemise' => $vrai_Montanttotal,
            'DateIns' => $date_vente
        ];
        error_log("Data vente: " . print_r($data_vente, true));
        insertion_element('vente', $data_vente, '');
        $vente_id = $cnx->lastInsertId();
        error_log("Vente ID: $vente_id");

        // 5. Insertion des paiements multiples
        error_log("Début insertion des paiements multiples");
        foreach ($paiements as $index => $paiement) {
            $mode = isset($paiement['mode']) ? intval($paiement['mode']) : 0;
            $montant = isset($paiement['montant']) ? floatval($paiement['montant']) : 0;
            error_log("Paiement $index: mode=$mode, montant=$montant");

            if ($mode > 0 && $montant > 0) {
                $data_paiement = [
                    'IDFactureVente' => $vente_id,
                    'IDMODE_REGLEMENT' => $mode,
                    'montant' => $montant,
                    'DATE_PAIEMENT' => $date_vente
                ];
                error_log("Insertion paiement: " . print_r($data_paiement, true));
                insertion_element('vente_paiement', $data_paiement, '');
            }
        }
        error_log("Insertion des paiements terminée");

        // 6. Traitement des articles et stock
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $quantite) {
                // Vérifier si le numéro de série existe déjà et s'il est disponible
                $stmt = $cnx->prepare("
                    SELECT * FROM num_serie 
                    WHERE NUMERO_SERIE = ? 
                    AND statut = 'disponible'
                    AND (ID_VENTE IS NULL OR ID_VENTE = '') 
                    AND (IDvente_credit IS NULL OR IDvente_credit = '')
                ");
                $stmt->execute([$numeroSerie]);
                $num_serie_existant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$num_serie_existant) {
                    throw new Exception("Le numéro de série " . $numeroSerie . " n'est pas disponible ou n'existe pas.");
                }

                // Enregistrer dans facture_article
                $data_facture = [
                    'NumeroVente' => $numeroVente,
                    'IDFactureVente' => $vente_id,
                    'IDARTICLE' => $id_article,
                    'QuantiteVendue' => 1,
                ];
                insertion_element('facture_article', $data_facture, '');

                // Mettre à jour le numéro de série
                $data_num_serie = [
                    'ID_VENTE' => $vente_id,
                    'NumeroVente' => $numeroVente,
                    'statut' => 'vendue'
                ];
                modifier_element('num_serie', ['ID_VENTE', 'NumeroVente', 'statut'], [$vente_id, $numeroVente, 'vendue'], 'NUMERO_SERIE', $numeroSerie, '');

                // Récupérer le stock actuel
                $stmt = $cnx->prepare("
                    SELECT s.StockActuel, s.IDSTOCK, a.libelle, a.PrixVenteTTC 
                    FROM stock s 
                    JOIN article a ON s.IDARTICLE = a.IDARTICLE 
                    WHERE s.IDARTICLE = ? FOR UPDATE
                ");
                $stmt->execute([$id_article]);
                $stock_actuel = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$stock_actuel) {
                    throw new Exception("Stock non trouvé pour l'article ID: " . $id_article);
                }

                $stock_avant = $stock_actuel['StockActuel'];
                $nouveau_stock = $stock_avant - 1;
                
                // Mettre à jour le stock
                $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?");
                $stmt->execute([$nouveau_stock, $id_article]);

                // Note: Journalisation individuelle supprimée pour éviter les doublons
                // La journalisation unifiée se fait après le commit
            }
        }

        // 7. Préparer session vente_data
        // Correction des variables undefined
        $numero_client = isset($numero) ? $numero : '';
        $email = isset($Adresse_email) ? $Adresse_email : '';
        $_SESSION['vente_data'] = [
            'numeroVente' => $numeroVente,
            'client' => [
                'nom' => $nomprenom,
                'telephone' => $numero_client,
                'email' => $email
            ],
            'articles' => [],
            'montants' => [
                'total_sans_remise' => $vrai_Montanttotal,
                'remise' => $remiseMontant,
                'total_avec_remise' => $montant_total,
                'montant_verse' => $montant_verse,
                'monnaie_rendre' => $monnaie_rendre
            ],
            'mode_paiement' => $mode_paiement,
            'date' => date('d/m/Y'),
            'heure' => date('H:i:s'),
            'vendeur' => $_SESSION['nom_complet'] ?? 'Inconnu'
        ];

        // 8. Articles dans session vente_data
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                $stmt = $cnx->prepare("
                    SELECT a.*, ns.NUMERO_SERIE 
                    FROM article a 
                    JOIN num_serie ns ON a.IDARTICLE = ns.IDARTICLE 
                    WHERE a.IDARTICLE = ? AND ns.NUMERO_SERIE = ?
                ");
                $stmt->execute([$id_article, $numeroSerie]);
                $article_details = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($article_details) {
                    $_SESSION['vente_data']['articles'][] = [
                        'id' => $id_article,
                        'libelle' => $article_details['libelle'],
                        'numero_serie' => $numeroSerie,
                        'prix_unitaire' => $article_details['PrixVenteTTC'],
                        'quantite' => $details['quantite'] ?? 1
                    ];
                }
            }
        }

        // 9. Stocker tous les modes de paiement avec leur montant dans la session
        // Correction : initialiser $paiements correctement
        $paiements = [];
        if (isset($_POST['paiements'])) {
            $paiements = json_decode($_POST['paiements'], true);
        }
        if (!is_array($paiements)) {
            $paiements = [];
        }
        $modePaiements = [];
        if (is_array($paiements)) {
            foreach ($paiements as $paiement) {
                $idMode = isset($paiement['mode']) ? intval($paiement['mode']) : 0;
                if ($idMode > 0) {
                    $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
                    $stmt->execute([$idMode]);
                    $modeReglement = $stmt->fetchColumn() ?: 'Inconnu';
                    
                    $modePaiements[] = [
                        'id' => $idMode,
                        'libelle' => $modeReglement,
                        'montant' => floatval($paiement['montant']),
                    ];
                }
            }
        }
        $_SESSION['vente_data']['mode_paiement'] = $modePaiements;

        // 10. Infos entreprise
        $stmt = $cnx->prepare("SELECT * FROM entreprise WHERE id = 1");
        $stmt->execute();
        $_SESSION['vente_data']['entreprise'] = $stmt->fetch(PDO::FETCH_ASSOC);
 
        // 11. Fin du traitement
        $cnx->commit();

        // 12. Journalisation multi-paiement réussie (système unifié) - APRÈS le commit
        $startTime = log_action_start();
        
        // Préparer les données détaillées pour la journalisation
        $articles_vente = [];
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                $articles_vente[] = [
                    'id_article' => $id_article,
                    'libelle' => $details['libelle'] ?? 'Article inconnu',
                    'numero_serie' => $numeroSerie,
                    'prix_unitaire' => $details['prixVenteUnitaire'] ?? 0
                ];
            }
        }
        
        // Préparer les détails des paiements pour la journalisation
        $details_paiements_journal = [];
        foreach ($paiements as $paiement) {
            $mode = isset($paiement['mode']) ? intval($paiement['mode']) : 0;
            $montant = isset($paiement['montant']) ? floatval($paiement['montant']) : 0;
            if ($mode > 0 && $montant > 0) {
                $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
                $stmt->execute([$mode]);
                $mode_libelle = $stmt->fetchColumn() ?: 'Mode inconnu';
                
                $details_paiements_journal[] = [
                    'mode_id' => $mode,
                    'mode_libelle' => $mode_libelle,
                    'montant' => $montant
                ];
            }
        }
        
        $donnees_multi_paiement = [
            'client' => [
                'nom' => $nomprenom,
                'telephone' => $numero_client,
                'email' => $email
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ],
            'articles' => $articles_vente,
            'montants' => [
                'total_sans_remise' => $vrai_Montanttotal,
                'remise' => $remiseMontant,
                'total_avec_remise' => $montant_total,
                'montant_verse' => $montant_verse,
                'monnaie_rendre' => $monnaie_rendre
            ],
            'paiements' => $details_paiements_journal,
            'numero_vente' => $numeroVente,
            'mode_paiement' => 'multi_paiement',
            'date_vente' => $date_vente
        ];
        
        // Créer une description détaillée avec tous les articles et paiements
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Vente multi-paiement: ' . $nomprenom . ' (Opérateur: ' . $operateur_nom . ') - Montant: ' . $montant_total . ' FCFA - N°: ' . $numeroVente;
        if ($remiseMontant > 0) {
            $description_detaille .= ' - Remise: ' . $remiseMontant . ' FCFA';
        }
        $description_detaille .= ' - Articles vendus: ';
        
        $articles_details = [];
        foreach ($articles_vente as $article) {
            $articles_details[] = $article['libelle'] . ' (N°Série: ' . $article['numero_serie'] . ', Prix: ' . $article['prix_unitaire'] . ' FCFA)';
        }
        $description_detaille .= implode(', ', $articles_details);
        
        $description_detaille .= ' - Paiements: ';
        $paiements_details = [];
        foreach ($details_paiements_journal as $paiement) {
            $paiements_details[] = $paiement['mode_libelle'] . ' (' . $paiement['montant'] . ' FCFA)';
        }
        $description_detaille .= implode(', ', $paiements_details);
        
        logSystemAction($cnx, 'VENTE_MULTI_PAIEMENT', 'VENTES', 'request.php', 
            $description_detaille, 
            null, $donnees_multi_paiement, 'HIGH', 'SUCCESS', log_action_end($startTime));

        // Préparer les données pour la notification
        $articles_pour_notification = [];
        foreach ($_SESSION['panier'] as $id_article => $produits) {
            foreach ($produits as $numeroSerie => $details) {
                // Vérification et nettoyage des données articles
                $articles_pour_notification[] = [
                    'libelle' => $details['libelle'] ?? 'Article sans nom',
                    'Quantite' => intval($details['quantite'] ?? 1),
                    'MontantProduitTTC' => floatval($details['prixVenteUnitaire'] ?? 0)
                ];
            }
        }

        // Nettoyage du montant total
        $montant_total_clean = floatval(preg_replace('/[^0-9]/', '', $montant_total));

        // Préparer les détails des paiements au format attendu
        $details_paiements = [];
        $total_verse = 0;
        foreach ($paiements as $p) {
            $montant = floatval($p['montant'] ?? 0);
            $total_verse += $montant;
            $details_paiements[] = [
                'DateIns' => date('Y-m-d H:i:s'),
                'AccompteVerse' => $montant,
                'ModeReglement' => $p['modeLibelle'] ?? 'Mode inconnu',
                'restant' => $montant_total_clean - $total_verse
            ];
        }

        unset($_SESSION['panier']);
        echo json_encode(['success' => true, 'numero_vente' => $numeroVente]);

    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        
        // Journalisation de l'échec multi-paiement
        $startTime = log_action_start();
        $donnees_echec = [
            'client' => [
                'nom' => $nomprenom ?? 'Inconnu',
                'telephone' => $numero_client ?? 'Inconnu',
                'email' => $email ?? 'Inconnu'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ],
            'montants' => [
                'total' => $montant_total ?? 0,
                'remise' => $remiseMontant ?? 0
            ],
            'erreur' => $e->getMessage()
        ];
        
        logSystemAction($cnx, 'VENTE_MULTI_PAIEMENT', 'VENTES', 'request.php', 
            'Échec vente multi-paiement: ' . ($nomprenom ?? 'Client inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        // Log de l'erreur pour débogage
        error_log("=== ERREUR MULTI-PAIEMENT ===");
        error_log("Message: " . $e->getMessage());
        error_log("Fichier: " . $e->getFile() . " Ligne: " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()
        ]);
    }
    exit;
}


if (isset($_POST['vente_credit'])) {

    try {
        // 1. Vérifier le panier
        if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
            throw new Exception("Le panier est vide.");
        }

        // 2. Récupérer les données du formulaire
        $nomprenom = trim($_POST['nomprenom'] ?? '');
        $numero = trim($_POST['numero_client'] ?? '');
        $Adresse_email = trim($_POST['Adresse_email'] ?? '');
        $mode_paiement = $_POST['mode_paiement'] ?? '';
        $montant_total = $_POST['montantTotal'] ?? 0;
        $montant_verse = $_POST['montantVerse'] ?? 0;
        $acompteVerse = isset($_POST['AccompteVerse']) ? floatval($_POST['AccompteVerse']) : 0;
        $remiseMontant = $_POST['remiseMontant'] ?? 0;
        $vrai_Montanttotal = $_POST['vrai_Montanttotal'] ?? 0;
        $monnaie_rendre = (int)$montant_verse - (int)$acompteVerse;
        $RestantAPayer = (int)$montant_total - (int)$acompteVerse;
        $redirection = '../vente_credit.php';

        // VALIDATION SERVEUR OBLIGATOIRE - NOM ET TÉLÉPHONE
        if (empty($nomprenom)) {
            throw new Exception("Le nom du client est obligatoire.");
        }
        
        if (empty($numero)) {
            throw new Exception("Le numéro de téléphone du client est obligatoire.");
        }
        
        // Validation du format de téléphone (international)
        if (!preg_match('/^(\+)?[0-9]{8,15}$/', $numero)) {
            throw new Exception("Format de téléphone invalide. Utilisez un numéro de 8 à 15 chiffres avec ou sans indicatif pays (+).");
        }
        
        // Validation du format d'email si fourni (suppression de la restriction)
        // Les emails sont optionnels et acceptent tous les formats valides (icloud, yahoo.fr, etc.)
        
        // Validation des montants
        if ($montant_total < 0) {
            throw new Exception("Le montant total ne peut pas être négatif.");
        }
        
        if ($acompteVerse < 0) {
            throw new Exception("L'acompte ne peut pas être négatif.");
        }
        
        if ($remiseMontant < 0) {
            throw new Exception("La remise ne peut pas être négative.");
        }
        
        if ($remiseMontant > $montant_total) {
            throw new Exception("La remise ne peut pas dépasser le montant total.");
        }

        // 3. Vérifier/Créer le client
        $client_existant = verifier_element('client', ['Telephone'], [$numero], '');
        if ($client_existant) {
            modifier_element('client', ['NomPrenomClient', 'Adresse_email'], [$nomprenom, $Adresse_email], 'IDCLIENT', $client_existant['IDCLIENT'], '');
            $client_id = $client_existant['IDCLIENT'];
        } else {
            $data_client = [
                'NomPrenomClient' => $nomprenom,
                'Adresse_email' => $Adresse_email,
                'Telephone' => $numero,
            ];
            insertion_element('client', $data_client, '');
            $client_id = $cnx->lastInsertId();
        }

        // 4. Générer le numéro de vente
        $stmt = $cnx->prepare("SELECT COUNT(*) FROM ventes_credit WHERE DATE(DateIns) = ?");
        $stmt->execute([date('Y-m-d')]);
        $nombre_ventes_credit = $stmt->fetchColumn();

        // Nouveau format pour vente à crédit : VC + YYYYMMDD + NNNN (ex: VC202509130001)
        $numeroVente = 'VC' . date('Ymd') . str_pad($nombre_ventes_credit + 1, 4, '0', STR_PAD_LEFT);

        // 5. Démarrer la transaction
        $cnx->beginTransaction();

        // 6. Enregistrer la vente à crédit
        $data_ventes_credit = [
            'NumeroVente' => $numeroVente,
            'IDCLIENT' => $client_id,
            'ModePaiement' => $mode_paiement,
            'MontantTotalCredit' => $montant_total,
            'MontantVerse' => $montant_verse,
            'AccompteVerse' => $acompteVerse,
            'Monnaie' => $monnaie_rendre,
            'RestantAPayer' => $RestantAPayer,
            'MontantRemise' => $remiseMontant,
            'MontantTotal_sansRemise' => $vrai_Montanttotal,
        ];
        insertion_element('ventes_credit', $data_ventes_credit, '');
        $vente_id = $cnx->lastInsertId();

        // 7. Enregistrer le paiement (acompte)
        $data_ventes_credit_paiement = [
            'IDVenteCredit' => $vente_id,
            'IDMODE_REGLEMENT' => $mode_paiement,
            'AccompteVerse' => $acompteVerse,
            'restant' => $RestantAPayer,
        ];
        insertion_element('ventes_credit_paiement', $data_ventes_credit_paiement, '');

        // 8. Enregistrer les lignes de vente, MAJ stock/numéro de série, journaliser
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                // Lignes de vente
                $data_ventes_credit_ligne = [
                    'NumeroVente' => $numeroVente,
                    'IDVenteCredit' => $vente_id,
                    'IDARTICLE' => $id_article,
                    'QuantiteVendue' => 1,
                ];
                insertion_element('ventes_credit_ligne', $data_ventes_credit_ligne, '');

                // Numéro de série
                $num_serie_existe = verifier_element('num_serie', ['NUMERO_SERIE'], [$numeroSerie], '');
                if ($num_serie_existe) {
                    $data_num_serie = [
                        'IDvente_credit' => $vente_id,
                        'NumeroVente' => $numeroVente,
                        'statut' => 'vendue_credit'
                    ];
                    modifier_element('num_serie', ['IDvente_credit', 'NumeroVente', 'statut'], [$vente_id, $numeroVente, 'vendue_credit'], 'NUMERO_SERIE', $numeroSerie, '');
                } else {
                    $data_num_serie = [
                        'IDARTICLE' => $id_article,
                        'NUMERO_SERIE' => $numeroSerie,
                        'IDvente_credit' => $vente_id,
                        'NumeroVente' => $numeroVente,
                        'statut' => 'vendue_credit'
                    ];
                    insertion_element('num_serie', $data_num_serie, '');
                }

                // Stock + journalisation
                $stmt = $cnx->prepare("
                    SELECT s.StockActuel, s.IDSTOCK, a.libelle, a.PrixVenteTTC 
                    FROM stock s 
                    JOIN article a ON s.IDARTICLE = a.IDARTICLE 
                    WHERE s.IDARTICLE = ? FOR UPDATE
                ");
                $stmt->execute([$id_article]);
                $stock_actuel = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$stock_actuel) {
                    throw new Exception("Stock non trouvé pour l'article ID: " . $id_article);
                }

                $stock_avant = $stock_actuel['StockActuel'];
                $nouveau_stock = $stock_avant - 1;
                
                // Vérification supplémentaire pour éviter les stocks négatifs inattendus
                if ($nouveau_stock < 0) {
                    error_log("ATTENTION: Stock négatif détecté pour l'article ID: $id_article, Stock avant: $stock_avant, Nouveau stock: $nouveau_stock");
                }
                
                // Mettre à jour le stock même s'il devient négatif
                $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?");
                $stmt->execute([$nouveau_stock, $id_article]);

                // Note: Journalisation individuelle supprimée pour éviter les doublons
                // La journalisation unifiée se fait après le commit
            }
        }

        // 7. Préparer session vente_data
        // Correction des variables undefined
        $numero_client = isset($numero) ? $numero : '';
        $email = isset($Adresse_email) ? $Adresse_email : '';
        $_SESSION['vente_data'] = [
            'numeroVente' => $numeroVente,
            'client' => [
                'nom' => $nomprenom,
                'telephone' => $numero_client,
                'email' => $email
            ],
            'articles' => [],
            'montants' => [
                'total_sans_remise' => $vrai_Montanttotal,
                'remise' => $remiseMontant,
                'total_avec_remise' => $montant_total,
                'montant_verse' => $montant_verse,
                'monnaie_rendre' => $monnaie_rendre
            ],
            'mode_paiement' => $mode_paiement,
            'date' => date('d/m/Y'),
            'heure' => date('H:i:s'),
            'vendeur' => $_SESSION['nom_complet'] ?? 'Inconnu'
        ];

        // 8. Articles dans session vente_data
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                $stmt = $cnx->prepare("
                    SELECT a.*, ns.NUMERO_SERIE 
                    FROM article a 
                    JOIN num_serie ns ON a.IDARTICLE = ns.IDARTICLE 
                    WHERE a.IDARTICLE = ? AND ns.NUMERO_SERIE = ?
                ");
                $stmt->execute([$id_article, $numeroSerie]);
                $article_details = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($article_details) {
                    $_SESSION['vente_data']['articles'][] = [
                        'id' => $id_article,
                        'libelle' => $article_details['libelle'],
                        'numero_serie' => $numeroSerie,
                        'prix_unitaire' => $article_details['PrixVenteTTC'],
                        'quantite' => $details['quantite'] ?? 1
                    ];
                }
            }
        }
        // 9. Stocker tous les modes de paiement avec leur montant dans la session
        $paiements = [];
        if (isset($_POST['paiements'])) {
            $paiements = json_decode($_POST['paiements'], true);
        }
        if (!is_array($paiements)) {
            $paiements = [];
        }
        $modePaiements = [];
        if (is_array($paiements)) {
            foreach ($paiements as $paiement) {
                $idMode = isset($paiement['mode']) ? intval($paiement['mode']) : 0;
                if ($idMode > 0) {
                    $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
                    $stmt->execute([$idMode]);
                    $modeReglement = $stmt->fetchColumn() ?: 'Inconnu';
                    
                    $modePaiements[] = [
                        'id' => $idMode,
                        'libelle' => $modeReglement,
                        'montant' => floatval($paiement['montant']),
                    ];
                }
            }
        }
        $_SESSION['vente_data']['mode_paiement'] = $modePaiements;

        // 10. Infos entreprise
        $stmt = $cnx->prepare("SELECT * FROM entreprise WHERE id = 1");
        $stmt->execute();
        $_SESSION['vente_data']['entreprise'] = $stmt->fetch(PDO::FETCH_ASSOC);
 
        // 11. Fin du traitement
        $cnx->commit();
        
        // Journalisation vente crédit réussie (système unifié) - APRÈS le commit
        $startTime = log_action_start();
        
        // Préparer les données détaillées pour la journalisation
        $articles_vente = [];
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                $articles_vente[] = [
                    'id_article' => $id_article,
                    'libelle' => $details['libelle'] ?? 'Article inconnu',
                    'numero_serie' => $numeroSerie,
                    'prix_unitaire' => $details['prixVenteUnitaire'] ?? 0
                ];
            }
        }
        
        $donnees_vente_credit = [
            'client' => [
                'nom' => $nomprenom,
                'telephone' => $numero,
                'email' => $Adresse_email
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ],
            'articles' => $articles_vente,
            'montants' => [
                'total_sans_remise' => $vrai_Montanttotal,
                'remise' => $remiseMontant,
                'total_avec_remise' => $montant_total,
                'acompte_verse' => $acompteVerse,
                'reste_a_payer' => $RestantAPayer,
                'monnaie_rendre' => $monnaie_rendre
            ],
            'numero_vente' => $numeroVente,
            'mode_paiement' => $mode_paiement,
            'date_vente' => date('Y-m-d H:i:s')
        ];
        
        // Créer une description détaillée avec tous les articles
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Vente crédit: ' . $nomprenom . ' (Opérateur: ' . $operateur_nom . ') - Montant: ' . $montant_total . ' FCFA - Acompte: ' . $acompteVerse . ' FCFA - N°: ' . $numeroVente;
        if ($remiseMontant > 0) {
            $description_detaille .= ' - Remise: ' . $remiseMontant . ' FCFA';
        }
        $description_detaille .= ' - Reste à payer: ' . $RestantAPayer . ' FCFA';
        $description_detaille .= ' - Articles vendus: ';
        
        $articles_details = [];
        foreach ($articles_vente as $article) {
            $articles_details[] = $article['libelle'] . ' (N°Série: ' . $article['numero_serie'] . ', Prix: ' . $article['prix_unitaire'] . ' FCFA)';
        }
        $description_detaille .= implode(', ', $articles_details);
        
        logSystemAction($cnx, 'VENTE_CREDIT', 'VENTES', 'request.php', 
            $description_detaille, 
            null, $donnees_vente_credit, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
        unset($_SESSION['panier']);
        header('Location: ../vente_credit.php?success=vente_enregistree&numero=' . urlencode($numeroVente));
        exit();

    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        
        // Journalisation de l'échec de vente crédit
        $startTime = log_action_start();
        $donnees_echec = [
            'client' => [
                'nom' => $nomprenom ?? 'Inconnu',
                'telephone' => $numero ?? 'Inconnu',
                'email' => $Adresse_email ?? 'Inconnu'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ],
            'montants' => [
                'total' => $montant_total ?? 0,
                'acompte' => $acompteVerse ?? 0,
                'remise' => $remiseMontant ?? 0
            ],
            'erreur' => $e->getMessage()
        ];
        
        logSystemAction($cnx, 'VENTE_CREDIT', 'VENTES', 'request.php', 
            'Échec vente crédit: ' . ($nomprenom ?? 'Client inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        $_SESSION['error'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        header('Location: ../vente_credit.php');
        exit();
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'multi_paiement_credit') {
    ob_clean();
    header('Content-Type: application/json');

    try {
        // Log de débogage temporaire
        error_log("=== MULTI-PAIEMENT CREDIT DEBUG ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session panier: " . print_r($_SESSION['panier'] ?? 'VIDE', true));
        
        $cnx->beginTransaction();

        // Récupération et validation des données
        $paiements = json_decode($_POST['paiements'] ?? '[]', true);
        if (!is_array($paiements) || empty($paiements)) {
            throw new Exception("Aucun paiement fourni.");
        }
        if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
            throw new Exception("Le panier est vide.");
        }

        $remiseMontant = isset($_POST['remiseMontant']) ? floatval($_POST['remiseMontant']) : 0;
        $vrai_Montanttotal = isset($_POST['vrai_Montanttotal']) ? floatval($_POST['vrai_Montanttotal']) : 0;
        $montant_total = $vrai_Montanttotal - $remiseMontant;
        $acompte = isset($_POST['acompte']) ? floatval($_POST['acompte']) : 0;

        $nomprenom = trim($_POST['nomprenom'] ?? '');
        $numero_client = trim($_POST['numero_client'] ?? '');
        $email = trim($_POST['Adresse_email'] ?? '');

        // VALIDATION SERVEUR OBLIGATOIRE - NOM ET TÉLÉPHONE
        if (empty($nomprenom)) {
            throw new Exception("Le nom du client est obligatoire.");
        }
        
        if (empty($numero_client)) {
            throw new Exception("Le numéro de téléphone du client est obligatoire.");
        }
        
        // Validation du format de téléphone (international)
        if (!preg_match('/^(\+)?[0-9]{8,15}$/', $numero_client)) {
            throw new Exception("Format de téléphone invalide. Utilisez un numéro de 8 à 15 chiffres avec ou sans indicatif pays (+).");
        }
        
        // Validation du format d'email si fourni (suppression de la restriction)
        // Les emails sont optionnels et acceptent tous les formats valides (icloud, yahoo.fr, etc.)
        
        // Validation des montants
        if ($montant_total < 0) {
            throw new Exception("Le montant total ne peut pas être négatif.");
        }
        
        if ($acompte < 0) {
            throw new Exception("L'acompte ne peut pas être négatif.");
        }
        
        if ($remiseMontant < 0) {
            throw new Exception("La remise ne peut pas être négative.");
        }
        
        if ($remiseMontant > $montant_total) {
            throw new Exception("La remise ne peut pas dépasser le montant total.");
        }

        if ($acompte <= 0) {
            throw new Exception("L'acompte doit être supérieur à zéro.");
        }

        // Vérification préalable de tous les numéros de série
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                // Vérifier si le numéro de série existe et s'il est disponible
                $stmt = $cnx->prepare("
                    SELECT * FROM num_serie 
                    WHERE NUMERO_SERIE = ? 
                    AND statut = 'disponible'
                    AND (ID_VENTE IS NULL OR ID_VENTE ='') 
                    AND (IDvente_credit IS NULL OR IDvente_credit = '')
                ");
                $stmt->execute([$numeroSerie]);
                $num_serie_existant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$num_serie_existant) {
                    throw new Exception("Le numéro de série " . $numeroSerie . " n'est pas disponible ou n'existe pas.");
                }
            }
        }

        // 1. Gérer le client
        $client_existant = verifier_element('client', ['Telephone'], [$numero_client], '');
        $client_id = $client_existant ? $client_existant['IDCLIENT'] : null;
        if ($client_id) {
            modifier_element('client', ['NomPrenomClient', 'Adresse_email'], [$nomprenom, $email], 'IDCLIENT', $client_id, '');
        } else {
            $data_client = ['NomPrenomClient' => $nomprenom, 'Telephone' => $numero_client, 'Adresse_email' => $email];
            insertion_element('client', $data_client, '');
            $client_id = $cnx->lastInsertId();
        }

        // 2. Générer le numéro de vente
        $stmt = $cnx->prepare("SELECT COUNT(*) FROM ventes_credit WHERE DATE(DateIns) = ?");
        $stmt->execute([date('Y-m-d')]);
        $nombre_ventes_credit = $stmt->fetchColumn();

        // Nouveau format pour vente à crédit multi-paiement : VCM + YYYYMMDD + NNNN (ex: VCM202509130001)
        $numeroVente = 'VCM' . date('Ymd') . str_pad($nombre_ventes_credit + 1, 4, '0', STR_PAD_LEFT);
        
        // 3. Calculs des montants
        $reste_a_payer = $montant_total - $acompte;

        // 4. Insertion de la vente à crédit
        $data_ventes_credit = [
            'NumeroVente' => $numeroVente,
            'IDCLIENT' => $client_id,
            'ModePaiement' => 'multi_paiement_credit',
            'MontantTotalCredit' => $montant_total,
            'MontantVerse' => $acompte,
            'AccompteVerse' => $acompte,
            'RestantAPayer' => $reste_a_payer,
            'MontantRemise' => $remiseMontant,
            'MontantTotal_sansRemise' => $vrai_Montanttotal,
            'DateIns' => date('Y-m-d H:i:s')
        ];
        insertion_element('ventes_credit', $data_ventes_credit, '');
        $vente_id = $cnx->lastInsertId();

        // 5. Insertion des paiements multiples
        foreach ($paiements as $paiement) {
            $data_paiement = [
                'IDVenteCredit' => $vente_id,
                'IDMODE_REGLEMENT' => intval($paiement['mode']),
                'AccompteVerse' => floatval($paiement['montant']),
                'restant' => $reste_a_payer
            ];
            insertion_element('ventes_credit_paiement', $data_paiement, '');
        }

        // 6. Traitement des articles et du stock avec journalisation
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                // Insertion de la ligne de vente à crédit
                insertion_element('ventes_credit_ligne', [
                    'IDVenteCredit' => $vente_id, 
                    'IDARTICLE' => $id_article, 
                    'QuantiteVendue' => 1, 
                    'NumeroVente' => $numeroVente
                ], '');

                // Mise à jour du numéro de série
                modifier_element("num_serie", 
                    ['IDvente_credit', 'NumeroVente', 'statut'], 
                    [$vente_id, $numeroVente, 'vendue_credit'], 
                    'NUMERO_SERIE', 
                    $numeroSerie, 
                    ''
                );
                
                // Récupération des informations de stock et article pour la journalisation
                $stmt = $cnx->prepare("
                    SELECT s.StockActuel, s.IDSTOCK, a.libelle, a.PrixVenteTTC 
                    FROM stock s 
                    JOIN article a ON s.IDARTICLE = a.IDARTICLE 
                    WHERE s.IDARTICLE = ? FOR UPDATE
                ");
                $stmt->execute([$id_article]);
                $stock_actuel = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$stock_actuel) {
                    throw new Exception("Stock non trouvé pour l'article ID: " . $id_article);
                }

                $stock_avant = $stock_actuel['StockActuel'];
                $nouveau_stock = $stock_avant - 1;

                // Mise à jour du stock
                $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?");
                $stmt->execute([$nouveau_stock, $id_article]);

                // Note: Journalisation individuelle supprimée pour éviter les doublons
                // La journalisation unifiée se fait après le commit
            }
        }

        // 7. Commit et réponse
        $cnx->commit();
        
        // Journalisation multi-paiement crédit réussie (système unifié) - APRÈS le commit
        $startTime = log_action_start();
        
        // Préparer les données détaillées pour la journalisation
        $articles_vente = [];
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                $articles_vente[] = [
                    'id_article' => $id_article,
                    'libelle' => $details['libelle'] ?? 'Article inconnu',
                    'numero_serie' => $numeroSerie,
                    'prix_unitaire' => $details['prixVenteUnitaire'] ?? 0
                ];
            }
        }
        
        // Préparer les détails des paiements pour la journalisation
        $details_paiements_journal = [];
        foreach ($paiements as $paiement) {
            $mode = isset($paiement['mode']) ? intval($paiement['mode']) : 0;
            $montant = isset($paiement['montant']) ? floatval($paiement['montant']) : 0;
            if ($mode > 0 && $montant > 0) {
                $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
                $stmt->execute([$mode]);
                $mode_libelle = $stmt->fetchColumn() ?: 'Mode inconnu';
                
                $details_paiements_journal[] = [
                    'mode_id' => $mode,
                    'mode_libelle' => $mode_libelle,
                    'montant' => $montant
                ];
            }
        }
        
        $donnees_multi_paiement_credit = [
            'client' => [
                'nom' => $nomprenom,
                'telephone' => $numero_client,
                'email' => $email
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ],
            'articles' => $articles_vente,
            'montants' => [
                'total_sans_remise' => $vrai_Montanttotal,
                'remise' => $remiseMontant,
                'total_avec_remise' => $montant_total,
                'acompte_verse' => $acompte,
                'reste_a_payer' => $reste_a_payer
            ],
            'paiements' => $details_paiements_journal,
            'numero_vente' => $numeroVente,
            'mode_paiement' => 'multi_paiement_credit',
            'date_vente' => date('Y-m-d H:i:s')
        ];
        
        // Créer une description détaillée avec tous les articles et paiements
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Vente crédit multi-paiement: ' . $nomprenom . ' (Opérateur: ' . $operateur_nom . ') - Montant: ' . $montant_total . ' FCFA - Acompte: ' . $acompte . ' FCFA - N°: ' . $numeroVente;
        if ($remiseMontant > 0) {
            $description_detaille .= ' - Remise: ' . $remiseMontant . ' FCFA';
        }
        $description_detaille .= ' - Reste à payer: ' . $reste_a_payer . ' FCFA';
        $description_detaille .= ' - Articles vendus: ';
        
        $articles_details = [];
        foreach ($articles_vente as $article) {
            $articles_details[] = $article['libelle'] . ' (N°Série: ' . $article['numero_serie'] . ', Prix: ' . $article['prix_unitaire'] . ' FCFA)';
        }
        $description_detaille .= implode(', ', $articles_details);
        
        $description_detaille .= ' - Paiements: ';
        $paiements_details = [];
        foreach ($details_paiements_journal as $paiement) {
            $paiements_details[] = $paiement['mode_libelle'] . ' (' . $paiement['montant'] . ' FCFA)';
        }
        $description_detaille .= implode(', ', $paiements_details);
        
        logSystemAction($cnx, 'VENTE_CREDIT_MULTI_PAIEMENT', 'VENTES', 'request.php', 
            $description_detaille, 
            null, $donnees_multi_paiement_credit, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
        unset($_SESSION['panier']);
        echo json_encode(['success' => true, 'numero_vente' => $numeroVente]);

    } catch (Exception $e) {
        if ($cnx->inTransaction()) $cnx->rollBack();
        
        // Journalisation de l'échec multi-paiement crédit
        $startTime = log_action_start();
        $donnees_echec = [
            'client' => [
                'nom' => $nomprenom ?? 'Inconnu',
                'telephone' => $numero_client ?? 'Inconnu',
                'email' => $email ?? 'Inconnu'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ],
            'montants' => [
                'total' => $montant_total ?? 0,
                'acompte' => $acompte ?? 0,
                'remise' => $remiseMontant ?? 0
            ],
            'erreur' => $e->getMessage()
        ];
        
        logSystemAction($cnx, 'VENTE_CREDIT_MULTI_PAIEMENT', 'VENTES', 'request.php', 
            'Échec vente crédit multi-paiement: ' . ($nomprenom ?? 'Client inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        // Log détaillé de l'erreur
        error_log("=== ERREUR MULTI-PAIEMENT CREDIT ===");
        error_log("Message: " . $e->getMessage());
        error_log("Fichier: " . $e->getFile() . " Ligne: " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['enregistrer_versement'])) {
   
    $user = $_POST['utilisateur'];
    $montant = $_POST['montant'];
    $date = $_POST['dat'];
    $mode = $_POST['mode'];
    $tableName = "versement";
    $redirection = '../versement.php';

    $data = [
        'IDUTILISATEUR' => $user,
        'MontantVersement' => $montant,
        'DateVersement' => $date,
        'IDMODE_REGLEMENT' => $mode,
    ];
    
    $startTime = log_action_start();
    
    // Récupérer les informations de l'utilisateur et du mode de paiement
    $stmt = $cnx->prepare("SELECT NomPrenom FROM utilisateur WHERE IDUTILISATEUR = ?");
    $stmt->execute([$user]);
    $nom_utilisateur = $stmt->fetchColumn() ?: 'Utilisateur inconnu';
    
    $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
    $stmt->execute([$mode]);
    $mode_reglement = $stmt->fetchColumn() ?: 'Mode inconnu';
    
    insertion_element($tableName, $data, $redirection);
    
    // Préparer les données détaillées pour la journalisation
    $donnees_versement = [
        'versement' => [
            'montant' => $montant,
            'date' => $date,
            'mode_reglement' => $mode_reglement
        ],
        'utilisateur' => [
            'id' => $user,
            'nom' => $nom_utilisateur
        ],
        'operateur' => [
            'id' => $_SESSION['id_utilisateur'],
            'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
            'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
        ]
    ];
    
    // Journalisation création versement (système unifié)
    $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
    $description_detaille = 'Création versement: ' . number_format($montant, 0, ',', ' ') . ' FCFA - Utilisateur: ' . $nom_utilisateur . ' (Opérateur: ' . $operateur_nom . ') - Date: ' . $date . ' - Mode: ' . $mode_reglement;
    
    logSystemAction($cnx, 'CREATION_VERSEMENT', 'FINANCE', 'request.php', 
        $description_detaille, 
        null, $donnees_versement, 'HIGH', 'SUCCESS', log_action_end($startTime));
    
    $success = "Le versement a ete enregistrer.";
    header('Location: ' . $redirection . '?success=' . urlencode($success));
    exit();
}

if (isset($_POST['supprimerVersement'])) {
    try {
    $idVersement = $_POST['idVersement'];
    $tableName = "versement";
    $redirection = '../versement.php';
        
        // Récupérer les données avant suppression
        $stmt = $cnx->prepare("SELECT * FROM versement WHERE IDVERSEMENTS = ?");
        $stmt->execute([$idVersement]);
        $versementAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$versementAvant) {
            throw new Exception("Versement non trouvé avec l'ID: " . $idVersement);
        }
        
        $startTime = log_action_start();
        
        // Récupérer les informations pour la journalisation AVANT la suppression
        $stmt = $cnx->prepare("SELECT NomPrenom FROM utilisateur WHERE IDUTILISATEUR = ?");
        $stmt->execute([$versementAvant['IDUTILISATEUR'] ?? 0]);
        $nom_utilisateur = $stmt->fetchColumn() ?: 'Utilisateur inconnu';
        
        $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
        $stmt->execute([$versementAvant['IDMODE_REGLEMENT'] ?? 0]);
        $mode_reglement = $stmt->fetchColumn() ?: 'Mode inconnu';
        
        // Préparer les données détaillées pour la journalisation
        $donnees_versement_supprime = [
            'versement' => [
                'id' => $idVersement,
                'montant' => $versementAvant['MontantVersement'] ?? 0,
                'date' => $versementAvant['DateVersement'] ?? 'Inconnue',
                'mode_reglement' => $mode_reglement
            ],
            'utilisateur' => [
                'id' => $versementAvant['IDUTILISATEUR'] ?? 0,
                'nom' => $nom_utilisateur
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation suppression versement (système unifié) - AVANT la suppression
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Suppression versement: ' . number_format($versementAvant['MontantVersement'] ?? 0, 0, ',', ' ') . ' FCFA - Utilisateur: ' . $nom_utilisateur . ' (Opérateur: ' . $operateur_nom . ') - Date: ' . ($versementAvant['DateVersement'] ?? 'Inconnue') . ' - Mode: ' . $mode_reglement;
        
        logSystemAction($cnx, 'SUPPRESSION_VERSEMENT', 'FINANCE', 'request.php', 
            $description_detaille, 
            $versementAvant, $donnees_versement_supprime, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
        
        // Maintenant supprimer l'élément
    supprimer_element($tableName, 'IDVERSEMENTS', $idVersement, $redirection);
        
    } catch (Exception $e) {
        // Journalisation de l'échec de suppression
        $startTime = log_action_start();
        $donnees_echec = [
            'versement' => [
                'id' => $idVersement ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'SUPPRESSION_VERSEMENT', 'FINANCE', 'request.php', 
            'Échec suppression versement ID: ' . ($idVersement ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'CRITICAL', 'FAILED', log_action_end($startTime));
        
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        header('Location: ../versement.php');
        exit();
    }
}

if (isset($_POST['modifierVersement'])) {
    $idVersement = $_POST['idVersement'];
    $user = $_POST['utilisateur'];
    $montant = $_POST['montant'];
    $date = $_POST['dat'];
    $mode = $_POST['mode'];
    $tableName = "versement";
    $redirection = '../versement.php';

    $columns = ['IDUTILISATEUR', 'MontantVersement', 'DateVersement', 'IDMODE_REGLEMENT'];
    $values = [$user, $montant, $date, $mode];

    // Récupérer les données avant modification
    $stmt = $cnx->prepare("SELECT * FROM versement WHERE IDVERSEMENTS = ?");
    $stmt->execute([$idVersement]);
    $versementAvant = $stmt->fetch(PDO::FETCH_ASSOC);

    try {
        $startTime = log_action_start();
        modifier_element($tableName, $columns, $values, 'IDVERSEMENTS', $idVersement, $redirection);
        
        // Récupérer les informations pour la journalisation
        $stmt = $cnx->prepare("SELECT NomPrenom FROM utilisateur WHERE IDUTILISATEUR = ?");
        $stmt->execute([$user]);
        $nom_utilisateur = $stmt->fetchColumn() ?: 'Utilisateur inconnu';
        
        $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
        $stmt->execute([$mode]);
        $mode_reglement = $stmt->fetchColumn() ?: 'Mode inconnu';
        
        // Préparer les données détaillées pour la journalisation
        $donnees_versement_apres = [
            'versement' => [
                'id' => $idVersement,
                'montant' => $montant,
                'date' => $date,
                'mode_reglement' => $mode_reglement
            ],
            'utilisateur' => [
                'id' => $user,
                'nom' => $nom_utilisateur
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation modification versement (système unifié)
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        
        // Détecter les changements pour une description plus précise
        $changements = [];
        if (($versementAvant['MontantVersement'] ?? 0) != $montant) {
            $changements[] = 'Montant: ' . number_format($versementAvant['MontantVersement'] ?? 0, 0, ',', ' ') . ' → ' . number_format($montant, 0, ',', ' ') . ' FCFA';
        }
        if (($versementAvant['DateVersement'] ?? '') != $date) {
            $changements[] = 'Date: ' . ($versementAvant['DateVersement'] ?? 'Inconnue') . ' → ' . $date;
        }
        if (($versementAvant['IDMODE_REGLEMENT'] ?? 0) != $mode) {
            $stmt = $cnx->prepare("SELECT ModeReglement FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
            $stmt->execute([$versementAvant['IDMODE_REGLEMENT'] ?? 0]);
            $mode_avant = $stmt->fetchColumn() ?: 'Inconnu';
            $changements[] = 'Mode: ' . $mode_avant . ' → ' . $mode_reglement;
        }
        if (($versementAvant['IDUTILISATEUR'] ?? 0) != $user) {
            $stmt = $cnx->prepare("SELECT NomPrenom FROM utilisateur WHERE IDUTILISATEUR = ?");
            $stmt->execute([$versementAvant['IDUTILISATEUR'] ?? 0]);
            $utilisateur_avant = $stmt->fetchColumn() ?: 'Inconnu';
            $changements[] = 'Utilisateur: ' . $utilisateur_avant . ' → ' . $nom_utilisateur;
        }
        
        $description_detaille = 'Modification versement ID: ' . $idVersement . ' - Changements: ' . implode(', ', $changements) . ' (Opérateur: ' . $operateur_nom . ')';
        
        logSystemAction($cnx, 'MODIFICATION_VERSEMENT', 'FINANCE', 'request.php', 
            $description_detaille, 
            $versementAvant, $donnees_versement_apres, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
        $success = "Le versement a été modifié.";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
        exit();
    } catch (Exception $e) {
        // Journalisation de l'échec de modification
        $startTime = log_action_start();
        $donnees_echec = [
            'versement' => [
                'id' => $idVersement,
                'montant_avant' => $versementAvant['MontantVersement'] ?? 0,
                'montant_apres' => $montant,
                'date' => $date,
                'erreur' => $e->getMessage()
            ],
            'utilisateur' => [
                'id' => $user,
                'nom' => 'Inconnu (erreur)'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'MODIFICATION_VERSEMENT', 'FINANCE', 'request.php', 
            'Échec modification versement ID: ' . $idVersement . ' - Erreur: ' . $e->getMessage(), 
            $versementAvant, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        $erreur = "Erreur lors de la modification du versement : " . $e->getMessage();
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
}

if (isset($_POST['enregister_founisseur'])) {
    $nomFournisseur = $_POST['nomFournisseur'];
    $emailFournisseur = $_POST['emailFournisseur'];
    $telephoneFournisseur = $_POST['telephoneFournisseur'];
    $tableName = "fournisseur";
    $redirection = '../fournisseur.php';

    $data = [
        'NomFournisseur' => $nomFournisseur,
        'eMailFournisseur' => $emailFournisseur,
        'TelephoneFournisseur' => $telephoneFournisseur
    ];

    $values = [$nomFournisseur, $emailFournisseur, $telephoneFournisseur];
    $columns = ['NomFournisseur', 'eMailFournisseur', 'TelephoneFournisseur'];
    $count = verifier_element($tableName, $columns, $values, $redirection);
    if ($count > 0) {
        $erreur = "un insetion de l'element existe deja dans la table $tableName";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    } else {
        try {
            $startTime = log_action_start();
        insertion_element($tableName, $data, $redirection);
            $idFournisseur = $cnx->lastInsertId();
            
            // Préparer les données détaillées pour la journalisation
            $donnees_fournisseur = [
                'fournisseur' => [
                    'id' => $idFournisseur,
                    'nom' => $nomFournisseur,
                    'email' => $emailFournisseur,
                    'telephone' => $telephoneFournisseur
                ],
                'operateur' => [
                    'id' => $_SESSION['id_utilisateur'],
                    'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                    'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
                ]
            ];
            
            // Journalisation création fournisseur (système unifié)
            $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
            $description_detaille = 'Création fournisseur: ' . $nomFournisseur . ' (Opérateur: ' . $operateur_nom . ') - Email: ' . $emailFournisseur . ' - Téléphone: ' . $telephoneFournisseur;
            
            logSystemAction($cnx, 'CREATION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
                $description_detaille, 
                null, $donnees_fournisseur, 'HIGH', 'SUCCESS', log_action_end($startTime));
            
        $success = "L'element a ete enregistrer.";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
        exit();
            
        } catch (Exception $e) {
            // Journalisation de l'échec de création
            $startTime = log_action_start();
            $donnees_echec = [
                'fournisseur' => [
                    'nom' => $nomFournisseur,
                    'email' => $emailFournisseur,
                    'telephone' => $telephoneFournisseur,
                    'erreur' => $e->getMessage()
                ],
                'operateur' => [
                    'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                    'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                    'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
                ]
            ];
            
            logSystemAction($cnx, 'CREATION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
                'Échec création fournisseur: ' . $nomFournisseur . ' - Erreur: ' . $e->getMessage(), 
                null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
            
            $erreur = "Erreur lors de la création du fournisseur : " . $e->getMessage();
            header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
        }
    }
}

if (isset($_POST['supprimer_fournisseur'])) {
    try {
    $idfournisseur = $_POST['idFournisseur'];
    $tableName = "fournisseur";
    $redirection = '../fournisseur.php';
    
    // Vérifier si c'est le fournisseur système (ID = 1)
    if ($idfournisseur == 1) {
        $erreur = "Le fournisseur système (ID: 1) ne peut pas être supprimé car il est utilisé par le système d'inventaire.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
    
        // Récupérer les données avant suppression
        $stmt = $cnx->prepare("SELECT * FROM fournisseur WHERE IDFOURNISSEUR = ?");
        $stmt->execute([$idfournisseur]);
        $fournisseurAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fournisseurAvant) {
            throw new Exception("Fournisseur non trouvé avec l'ID: " . $idfournisseur);
        }
        
        $startTime = log_action_start();
        
        // Préparer les données détaillées pour la journalisation
        $donnees_fournisseur_supprime = [
            'fournisseur' => [
                'id' => $idfournisseur,
                'nom' => $fournisseurAvant['NomFournisseur'] ?? 'Inconnu',
                'email' => $fournisseurAvant['eMailFournisseur'] ?? 'Inconnu',
                'telephone' => $fournisseurAvant['TelephoneFournisseur'] ?? 'Inconnu'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation suppression fournisseur (système unifié) - AVANT la suppression
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Suppression fournisseur: ' . ($fournisseurAvant['NomFournisseur'] ?? 'Inconnu') . ' (Opérateur: ' . $operateur_nom . ') - Email: ' . ($fournisseurAvant['eMailFournisseur'] ?? 'Inconnu') . ' - Téléphone: ' . ($fournisseurAvant['TelephoneFournisseur'] ?? 'Inconnu');
        
        logSystemAction($cnx, 'SUPPRESSION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
            $description_detaille, 
            $fournisseurAvant, $donnees_fournisseur_supprime, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
        
        // Maintenant supprimer l'élément
    supprimer_element($tableName, 'IDFOURNISSEUR', $idfournisseur, $redirection);
        
    } catch (Exception $e) {
        // Journalisation de l'échec de suppression
        $startTime = log_action_start();
        $donnees_echec = [
            'fournisseur' => [
                'id' => $idfournisseur ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'SUPPRESSION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
            'Échec suppression fournisseur ID: ' . ($idfournisseur ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'CRITICAL', 'FAILED', log_action_end($startTime));
        
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        header('Location: ../fournisseur.php');
        exit();
    }
}

if (isset($_POST['mettre_a_jour_fournisseur'])) {
    try {
        $idFournisseur = $_POST['idFournisseur'];
        $nomFournisseur = $_POST['nomFournisseur'];
        $emailFournisseur = $_POST['emailFournisseur'];
        $telephoneFournisseur = $_POST['telephoneFournisseur'];
        $tableName = "fournisseur";
        $redirection = '../fournisseur.php';

        // Vérifier si c'est le fournisseur système (ID = 1)
        if ($idFournisseur == 1) {
            $erreur = "Le fournisseur système (ID: 1) ne peut pas être modifié car il est utilisé par le système d'inventaire.";
            header('Location: ' . $redirection . '?error=' . urlencode($erreur));
            exit();
        }

        // Récupérer les données avant modification
        $stmt = $cnx->prepare("SELECT * FROM fournisseur WHERE IDFOURNISSEUR = ?");
        $stmt->execute([$idFournisseur]);
        $fournisseurAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fournisseurAvant) {
            throw new Exception("Fournisseur non trouvé avec l'ID: " . $idFournisseur);
        }

        $startTime = log_action_start();
        
        $columns = ['NomFournisseur', 'eMailFournisseur', 'TelephoneFournisseur'];
        $values = [$nomFournisseur, $emailFournisseur, $telephoneFournisseur];

        modifier_element($tableName, $columns, $values, 'IDFOURNISSEUR', $idFournisseur, $redirection);
        
        // Détecter les changements pour une description plus précise
        $changements = [];
        if (($fournisseurAvant['NomFournisseur'] ?? '') != $nomFournisseur) {
            $changements[] = 'Nom: ' . ($fournisseurAvant['NomFournisseur'] ?? 'Inconnu') . ' → ' . $nomFournisseur;
        }
        if (($fournisseurAvant['eMailFournisseur'] ?? '') != $emailFournisseur) {
            $changements[] = 'Email: ' . ($fournisseurAvant['eMailFournisseur'] ?? 'Inconnu') . ' → ' . $emailFournisseur;
        }
        if (($fournisseurAvant['TelephoneFournisseur'] ?? '') != $telephoneFournisseur) {
            $changements[] = 'Téléphone: ' . ($fournisseurAvant['TelephoneFournisseur'] ?? 'Inconnu') . ' → ' . $telephoneFournisseur;
        }
        
        // Préparer les données détaillées pour la journalisation
        $donnees_fournisseur_apres = [
            'fournisseur' => [
                'id' => $idFournisseur,
                'nom' => $nomFournisseur,
                'email' => $emailFournisseur,
                'telephone' => $telephoneFournisseur
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation modification fournisseur (système unifié)
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Modification fournisseur ID: ' . $idFournisseur . ' - Changements: ' . implode(', ', $changements) . ' (Opérateur: ' . $operateur_nom . ')';
        
        logSystemAction($cnx, 'MODIFICATION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
            $description_detaille, 
            $fournisseurAvant, $donnees_fournisseur_apres, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
        $success = "Le fournisseur a été mis à jour avec succès.";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
        exit();
        
    } catch (Exception $e) {
        // Journalisation de l'échec de modification
        $startTime = log_action_start();
        $donnees_echec = [
            'fournisseur' => [
                'id' => $idFournisseur ?? 'Inconnu',
                'nom' => $nomFournisseur ?? 'Inconnu',
                'email' => $emailFournisseur ?? 'Inconnu',
                'telephone' => $telephoneFournisseur ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'MODIFICATION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
            'Échec modification fournisseur ID: ' . ($idFournisseur ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        $erreur = "Erreur lors de la modification du fournisseur : " . $e->getMessage();
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
}


if (isset($_POST['enregister_paiement'])) {
    try {
    $mode_reglement = $_POST['mode_reglement'];
    $numero = $_POST['numero'];
    $tableName = "mode_reglement";
    $redirection = '../mode_reglement.php';

    $data = [
        'ModeReglement' => $mode_reglement,
        'numero' => $numero
    ];

    $values = [$mode_reglement, $numero];
    $columns = ['ModeReglement', 'numero'];
    $count = verifier_element($tableName, $columns, $values, $redirection);
    if ($count > 0) {
        $erreur = "un insetion de l'element existe deja dans la table $tableName";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    } else {
            $startTime = log_action_start();
        insertion_element($tableName, $data, $redirection);
            $idModeReglement = $cnx->lastInsertId();
            
            // Préparer les données détaillées pour la journalisation
            $donnees_mode_reglement = [
                'mode_reglement' => [
                    'id' => $idModeReglement,
                    'nom' => $mode_reglement,
                    'numero' => $numero
                ],
                'operateur' => [
                    'id' => $_SESSION['id_utilisateur'],
                    'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                    'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
                ]
            ];
            
            // Journalisation création mode de règlement (système unifié)
            $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
            $description_detaille = 'Création mode de règlement: ' . $mode_reglement . ' (Opérateur: ' . $operateur_nom . ') - Numéro: ' . $numero;
            
            logSystemAction($cnx, 'CREATION_MODE_REGLEMENT', 'PARAMETRES', 'request.php', 
                $description_detaille, 
                null, $donnees_mode_reglement, 'MEDIUM', 'SUCCESS', log_action_end($startTime));
            
        $success = "L'element a ete enregistrer.";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
            exit();
        }
    } catch (Exception $e) {
        // Journalisation de l'échec de création
        $startTime = log_action_start();
        $donnees_echec = [
            'mode_reglement' => [
                'nom' => $mode_reglement ?? 'Inconnu',
                'numero' => $numero ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'CREATION_MODE_REGLEMENT', 'PARAMETRES', 'request.php', 
            'Échec création mode de règlement: ' . ($mode_reglement ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'MEDIUM', 'FAILED', log_action_end($startTime));
        
        $erreur = "Erreur lors de la création du mode de règlement : " . $e->getMessage();
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
}

if (isset($_POST['supprimer_mode_paiement'])) {
    try {
    $idmode_reglement = $_POST['idmode_reglement'];
    $tableName = "mode_reglement";
    $redirection = '../mode_reglement.php';
        
        // Récupérer les données avant suppression
        $stmt = $cnx->prepare("SELECT * FROM mode_reglement WHERE IDMODE_REGLEMENT = ?");
        $stmt->execute([$idmode_reglement]);
        $modeReglementAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$modeReglementAvant) {
            throw new Exception("Mode de règlement non trouvé avec l'ID: " . $idmode_reglement);
        }
        
        $startTime = log_action_start();
        
        // Préparer les données détaillées pour la journalisation
        $donnees_mode_reglement_supprime = [
            'mode_reglement' => [
                'id' => $idmode_reglement,
                'nom' => $modeReglementAvant['ModeReglement'] ?? 'Inconnu',
                'numero' => $modeReglementAvant['numero'] ?? 'Inconnu'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation suppression mode de règlement (système unifié) - AVANT la suppression
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Suppression mode de règlement: ' . ($modeReglementAvant['ModeReglement'] ?? 'Inconnu') . ' (Opérateur: ' . $operateur_nom . ') - Numéro: ' . ($modeReglementAvant['numero'] ?? 'Inconnu');
        
        logSystemAction($cnx, 'SUPPRESSION_MODE_REGLEMENT', 'PARAMETRES', 'request.php', 
            $description_detaille, 
            $modeReglementAvant, $donnees_mode_reglement_supprime, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
        // Maintenant supprimer l'élément
    supprimer_element($tableName, 'IDMODE_REGLEMENT', $idmode_reglement, $redirection);
        
    } catch (Exception $e) {
        // Journalisation de l'échec de suppression
        $startTime = log_action_start();
        $donnees_echec = [
            'mode_reglement' => [
                'id' => $idmode_reglement ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'SUPPRESSION_MODE_REGLEMENT', 'PARAMETRES', 'request.php', 
            'Échec suppression mode de règlement ID: ' . ($idmode_reglement ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        header('Location: ../mode_reglement.php');
        exit();
    }
}

if (isset($_POST['enregistrer_utilisateur'])) {
    $nom_simple = htmlspecialchars(trim($_POST['nom']));
    $prenom = htmlspecialchars(trim($_POST['prenom']));
    $nom = $nom_simple . ' ' . $prenom;
    $fonction = htmlspecialchars(trim($_POST['fonction']));
    $identifiant = htmlspecialchars(trim($_POST['identifiant']));
    $mdp = $_POST['mdp'];
    $Confirme_mdp = $_POST['Confirme_mdp'];

    $tableName = "utilisateur";
    $redirection = '../creer_compte_utilisateur.php';

    if ($mdp !== $Confirme_mdp) {
        $erreur = "Les mots de passe ne correspondent pas. Veuillez vérifier votre saisie.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }

    // Vérification de l'unicité du mot de passe (vérification sur les hashs)
    $sql = "SELECT MotDePasse FROM utilisateur";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $hashedPasswords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $passwordExists = false;
    foreach ($hashedPasswords as $hashedPassword) {
        if (password_verify($mdp, $hashedPassword)) {
            $passwordExists = true;
            break;
        }
    }
    
    if ($passwordExists) {
        $erreur = "Ce mot de passe est déjà utilisé par un autre utilisateur. Veuillez en choisir un autre.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
    $hashedPassword = password_hash($mdp, PASSWORD_DEFAULT);

    $role = $_POST['role'] ?? 'user';
    $data = [
        'NomPrenom' => $nom,
        'Identifiant' => $identifiant,
        'fonction' => $fonction,
        'role' => $role,
        'MotDePasse' => $hashedPassword,
    ];

    $values = [$nom, $identifiant];
    $columns = ['NomPrenom', 'Identifiant'];

    $count = verifier_element($tableName, $columns, $values, $redirection);
    $count2 = verifier_element($tableName, ['Identifiant'], [$identifiant], $redirection);

    if ($count > 0) {
        $erreur = "Un utilisateur avec ce nom et prénom existe déjà dans le système.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    } elseif ($count2 > 0) {
        $erreur = "Cet identifiant est déjà utilisé par un autre utilisateur. Veuillez en choisir un autre.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    } else {
        try {
            $startTime = log_action_start();
        insertion_element($tableName, $data, $redirection);
            $idUtilisateur = $cnx->lastInsertId();
            
            // Préparer les données détaillées pour la journalisation
            $donnees_utilisateur = [
                'utilisateur' => [
                    'id' => $idUtilisateur,
                    'nom' => $nom,
                    'identifiant' => $identifiant,
                    'fonction' => $fonction,
                    'role' => $role
                ],
                'operateur' => [
                    'id' => $_SESSION['id_utilisateur'],
                    'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                    'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
                ]
            ];
            
            // Journalisation création utilisateur (système unifié)
            $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
            $description_detaille = 'Création utilisateur: ' . $nom . ' (Opérateur: ' . $operateur_nom . ') - Identifiant: ' . $identifiant . ' - Fonction: ' . $fonction . ' - Rôle: ' . $role;
            
            logSystemAction($cnx, 'CREATION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
                $description_detaille, 
                null, $donnees_utilisateur, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
            
        $success = "Utilisateur créé avec succès ! Le compte est maintenant actif.";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
        exit();
            
        } catch (Exception $e) {
            // Journalisation de l'échec de création
            $startTime = log_action_start();
            $donnees_echec = [
                'utilisateur' => [
                    'nom' => $nom,
                    'identifiant' => $identifiant,
                    'fonction' => $fonction,
                    'role' => $role,
                    'erreur' => $e->getMessage()
                ],
                'operateur' => [
                    'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                    'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                    'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
                ]
            ];
            
            logSystemAction($cnx, 'CREATION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
                'Échec création utilisateur: ' . $nom . ' - Erreur: ' . $e->getMessage(), 
                null, $donnees_echec, 'CRITICAL', 'FAILED', log_action_end($startTime));
            
            $erreur = "Erreur lors de la création de l'utilisateur : " . $e->getMessage();
            header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
        }
    }
}

if (isset($_POST['supprimer_utilisateur'])) {
    try {
    $idutilisateur = $_POST['idutilisateur'];
    $tableName = "utilisateur";
    $redirection = '../liste_utilisateurs.php';
        
        // Récupérer les données avant suppression
        $stmt = $cnx->prepare("SELECT * FROM utilisateur WHERE IDUTILISATEUR = ?");
        $stmt->execute([$idutilisateur]);
        $utilisateurAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilisateurAvant) {
            throw new Exception("Utilisateur non trouvé avec l'ID: " . $idutilisateur);
        }
        
        $startTime = log_action_start();
        
        // Préparer les données détaillées pour la journalisation
        $donnees_utilisateur_supprime = [
            'utilisateur' => [
                'id' => $idutilisateur,
                'nom' => $utilisateurAvant['NomPrenom'] ?? 'Inconnu',
                'identifiant' => $utilisateurAvant['Identifiant'] ?? 'Inconnu',
                'fonction' => $utilisateurAvant['fonction'] ?? 'Inconnu',
                'role' => $utilisateurAvant['role'] ?? 'Inconnu'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation suppression utilisateur (système unifié) - AVANT la suppression
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Suppression utilisateur: ' . ($utilisateurAvant['NomPrenom'] ?? 'Inconnu') . ' (Opérateur: ' . $operateur_nom . ') - Identifiant: ' . ($utilisateurAvant['Identifiant'] ?? 'Inconnu') . ' - Fonction: ' . ($utilisateurAvant['fonction'] ?? 'Inconnu');
        
        logSystemAction($cnx, 'SUPPRESSION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
            $description_detaille, 
            $utilisateurAvant, $donnees_utilisateur_supprime, 'CRITICAL', 'SUCCESS', log_action_end($startTime));
        
        // Maintenant supprimer l'élément
    supprimer_element($tableName, 'IDUTILISATEUR', $idutilisateur, $redirection);
        
    } catch (Exception $e) {
        // Journalisation de l'échec de suppression
        $startTime = log_action_start();
        $donnees_echec = [
            'utilisateur' => [
                'id' => $idutilisateur ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'SUPPRESSION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
            'Échec suppression utilisateur ID: ' . ($idutilisateur ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'CRITICAL', 'FAILED', log_action_end($startTime));
        
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        header('Location: ../liste_utilisateurs.php');
        exit();
    }
}

// Désactiver un utilisateur
if (isset($_POST['desactiver_utilisateur'])) {
    try {
        $id = $_POST['idutilisateur'];
        
        // Récupérer les données avant désactivation
        $stmt = $cnx->prepare("SELECT * FROM utilisateur WHERE IDUTILISATEUR = ?");
        $stmt->execute([$id]);
        $utilisateurAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilisateurAvant) {
            throw new Exception("Utilisateur non trouvé avec l'ID: " . $id);
        }
        
        $startTime = log_action_start();
        
        $stmt = $cnx->prepare("UPDATE utilisateur SET actif = 'non' WHERE IDUTILISATEUR = ?");
        $stmt->execute([$id]);
        
        // Préparer les données détaillées pour la journalisation
        $donnees_utilisateur_desactive = [
            'utilisateur' => [
                'id' => $id,
                'nom' => $utilisateurAvant['NomPrenom'] ?? 'Inconnu',
                'identifiant' => $utilisateurAvant['Identifiant'] ?? 'Inconnu',
                'fonction' => $utilisateurAvant['fonction'] ?? 'Inconnu',
                'role' => $utilisateurAvant['role'] ?? 'Inconnu',
                'statut' => 'Désactivé'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation désactivation utilisateur (système unifié)
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Désactivation utilisateur: ' . ($utilisateurAvant['NomPrenom'] ?? 'Inconnu') . ' (Opérateur: ' . $operateur_nom . ') - Identifiant: ' . ($utilisateurAvant['Identifiant'] ?? 'Inconnu');
        
        logSystemAction($cnx, 'DESACTIVATION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
            $description_detaille, 
            $utilisateurAvant, $donnees_utilisateur_desactive, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
        header('Location: ../liste_utilisateurs.php?success=utilisateur désactivé avec succès');
        exit();
        
                } catch (Exception $e) {
        // Journalisation de l'échec de désactivation
        $startTime = log_action_start();
        $donnees_echec = [
            'utilisateur' => [
                'id' => $id ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'DESACTIVATION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
            'Échec désactivation utilisateur ID: ' . ($id ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        $_SESSION['error'] = "Erreur lors de la désactivation : " . $e->getMessage();
        header('Location: ../liste_utilisateurs.php');
        exit();
    }
}

// Activer un utilisateur
if (isset($_POST['activer_utilisateur'])) {
    try {
        $id = $_POST['idutilisateur'];
        
        // Récupérer les données avant activation
        $stmt = $cnx->prepare("SELECT * FROM utilisateur WHERE IDUTILISATEUR = ?");
        $stmt->execute([$id]);
        $utilisateurAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilisateurAvant) {
            throw new Exception("Utilisateur non trouvé avec l'ID: " . $id);
        }
        
        $startTime = log_action_start();
        
        $stmt = $cnx->prepare("UPDATE utilisateur SET actif = 'oui' WHERE IDUTILISATEUR = ?");
        $stmt->execute([$id]);
        
        // Préparer les données détaillées pour la journalisation
        $donnees_utilisateur_active = [
            'utilisateur' => [
                'id' => $id,
                'nom' => $utilisateurAvant['NomPrenom'] ?? 'Inconnu',
                'identifiant' => $utilisateurAvant['Identifiant'] ?? 'Inconnu',
                'fonction' => $utilisateurAvant['fonction'] ?? 'Inconnu',
                'role' => $utilisateurAvant['role'] ?? 'Inconnu',
                'statut' => 'Activé'
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation activation utilisateur (système unifié)
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Activation utilisateur: ' . ($utilisateurAvant['NomPrenom'] ?? 'Inconnu') . ' (Opérateur: ' . $operateur_nom . ') - Identifiant: ' . ($utilisateurAvant['Identifiant'] ?? 'Inconnu');
        
        logSystemAction($cnx, 'ACTIVATION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
            $description_detaille, 
            $utilisateurAvant, $donnees_utilisateur_active, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
        header('Location: ../liste_utilisateurs.php?success=utilisateur activé avec succès');
        exit();

    } catch (Exception $e) {
        // Journalisation de l'échec d'activation
        $startTime = log_action_start();
        $donnees_echec = [
            'utilisateur' => [
                'id' => $id ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'ACTIVATION_UTILISATEUR', 'UTILISATEURS', 'request.php', 
            'Échec activation utilisateur ID: ' . ($id ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        $_SESSION['error'] = "Erreur lors de l'activation : " . $e->getMessage();
        header('Location: ../liste_utilisateurs.php');
        exit();
    }
}


if (isset($_POST['valider_entree_stock'])) {
    try {
        // ... code existant ...
        // AJOUT : Récupération des frais annexes (optionnel)
        $frais_annexes = isset($_POST['frais_annexes']) ? floatval($_POST['frais_annexes']) : 0;
        // AJOUT : Calcul du total valeur d'achat pour la répartition
        $total_valeur_achat = 0;
        foreach ($_POST['id_article'] as $i => $id_article) {
            $quantite = (int)$_POST['quantite'][$i];
            $prix_achat = (float)str_replace(',', '.', $_POST['prixAchat'][$i]);
            $total_valeur_achat += $prix_achat * $quantite;
        }
        // AJOUT : Répartition des frais annexes et calcul du coût unitaire réel
        $repartition_frais = [];
        foreach ($_POST['id_article'] as $i => $id_article) {
            $quantite = (int)$_POST['quantite'][$i];
            $prix_achat = (float)str_replace(',', '.', $_POST['prixAchat'][$i]);
            $part_frais = ($total_valeur_achat > 0) ? ($prix_achat * $quantite / $total_valeur_achat) * $frais_annexes : 0;
            $cout_unitaire_reel = $prix_achat + ($quantite > 0 ? $part_frais / $quantite : 0);
            $repartition_frais[$i] = [
                'part_frais' => $part_frais,
                'cout_unitaire_reel' => $cout_unitaire_reel
            ];
            // Optionnel : journaliser la répartition pour debug
            error_log("Article $id_article : part_frais=$part_frais, cout_unitaire_reel=$cout_unitaire_reel");
        }
        // AJOUT : Si la colonne existe dans entree_en_stock, enregistrer les frais annexes
        // (à activer si la colonne existe)
        if ($frais_annexes > 0) {
            $stmt = $cnx->prepare("UPDATE entree_en_stock SET frais_annexes = ? WHERE IDENTREE_STOCK = ?");
            $stmt->execute([$frais_annexes, $id_entre_stock]);
        }
        // AJOUT : Si la colonne existe dans entree_stock_ligne, enregistrer le coût unitaire réel
        // (à activer dans la boucle d'insertion des lignes)
        // ... code existant ...
    // ... code existant ...

        // ... code existant ...
        // Validation des données de base
        if (empty($_POST['fournisseur']) || empty($_POST['numeroBon']) || empty($_POST['dateLivraison'])) {
            throw new Exception("⚠️ ERREUR : Tous les champs sont obligatoires (Fournisseur, Numéro de bon, Date de livraison)");
        }

        // Validation du fournisseur
        $stmt = $cnx->prepare("SELECT IDFOURNISSEUR, NomFournisseur FROM fournisseur WHERE IDFOURNISSEUR = ?");
        $stmt->execute([$_POST['fournisseur']]);
        $fournisseur_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fournisseur_info) {
            throw new Exception("⚠️ ERREUR : Le fournisseur sélectionné n'existe pas");
        }

        // Validation du numéro de bon
        $stmt = $cnx->prepare("SELECT COUNT(*) FROM entree_en_stock WHERE Numero_bon = ?");
        $stmt->execute([$_POST['numeroBon']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("⚠️ ERREUR : Ce numéro de bon existe déjà");
        }

        // Validation de la date de livraison
        $dateLivraison = $_POST['dateLivraison'];
        $fournisseur = $_POST['fournisseur'];
        $numeroBon = $_POST['numeroBon'];
        $id_utilisateur = $_SESSION['id_utilisateur'];

        // Début de la transaction
        $cnx->beginTransaction();

        // Insertion de l'entrée en stock
        $stmt = $cnx->prepare("
            INSERT INTO entree_en_stock 
            (IDFOURNISSEUR, Numero_bon, Date_arrivee, ID_utilisateurs, statut, MontantAchatHT, MontantVenteTTC, frais_annexes) 
            VALUES (?, ?, ?, ?, 'EN_COURS', ?, ?, ?)
        ");
        $stmt->execute([
            $fournisseur, 
            $numeroBon, 
            $dateLivraison, 
            $id_utilisateur,
            0, // MontantAchatHT initial
            0, // MontantVenteTTC initial
            $frais_annexes // ENREGISTREMENT DIRECT DU MONTANT
        ]);
        $id_entre_stock = $cnx->lastInsertId();

        // Traitement des articles
        $articles_avec_serie = [];
        $total_prix_achat = 0;
        $total_prix_vente = 0;
        $articles_traites = [];
        
        // Nettoyer et valider les données POST
        foreach ($_POST['id_article'] as $i => $id_article) {
            // Ignorer les lignes vides ou invalides
            if (empty($id_article) || empty($_POST['quantite'][$i]) || $_POST['quantite'][$i] === '') {
                continue;
            }

            // Vérifier si l'article a déjà été traité
            if (isset($articles_traites[$id_article])) {
                throw new Exception("⚠️ ERREUR : L'article ID $id_article est en double dans la liste.");
            }

            // Récupération et validation de l'article
            $stmt = $cnx->prepare("SELECT IDARTICLE, libelle FROM article WHERE IDARTICLE = ? AND desactiver != 'oui'");
            $stmt->execute([$id_article]);
            $article_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$article_info) {
                throw new Exception("⚠️ ERREUR : L'article ID $id_article n'existe pas ou est désactivé");
            }

            // Récupération et validation des données
            $quantite = (int)$_POST['quantite'][$i];
            $prix_achat = (float)str_replace(',', '.', $_POST['prixAchat'][$i]);
            $prix_vente = (float)str_replace(',', '.', $_POST['prixVente'][$i]);

            // Validation des valeurs
            if ($quantite <= 0) {
                throw new Exception("⚠️ ERREUR : La quantité doit être supérieure à 0 pour l'article " . $article_info['libelle']);
            }
            if ($prix_achat <= 0) {
                throw new Exception("⚠️ ERREUR : Le prix d'achat doit être supérieur à 0 pour l'article " . $article_info['libelle']);
            }
            if ($prix_vente <= 0) {
                throw new Exception("⚠️ ERREUR : Le prix de vente doit être supérieur à 0 pour l'article " . $article_info['libelle']);
            }

            // Créer ou récupérer le stock pour l'article
            $stmt = $cnx->prepare("SELECT IDSTOCK, StockActuel FROM stock WHERE IDARTICLE = ? FOR UPDATE");
            $stmt->execute([$id_article]);
            $stock_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stock_info) {
                // Créer un nouveau stock pour l'article
                $stmt = $cnx->prepare("INSERT INTO stock (IDARTICLE, StockActuel, TotalEntree, TotalVente) VALUES (?, 0, 0, 0)");
                $stmt->execute([$id_article]);
                $id_stock = $cnx->lastInsertId();
                $stock_avant = 0;
            } else {
                $id_stock = $stock_info['IDSTOCK'];
                $stock_avant = (int)$stock_info['StockActuel'];
            }

            // Insérer la ligne d'entrée en stock avec l'IDSTOCK
            $stmt = $cnx->prepare("
                INSERT INTO entree_stock_ligne 
                (IDENTREE_EN_STOCK, IDARTICLE, IDSTOCK, Quantite, PrixAchat, PrixVente, part_frais_annexe, cout_unitaire_reel) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_entre_stock, 
                $id_article,
                $id_stock,
                $quantite,
                $prix_achat,
                $prix_vente,
                $repartition_frais[$i]['part_frais'],         // part des frais annexes pour cette ligne
                $repartition_frais[$i]['cout_unitaire_reel']  // coût unitaire réel pour cette ligne
            ]);

            // --- MODIFICATION : PMP calculé mais pas encore appliqué ---
            // Récupérer le PMP actuel
            $stmt = $cnx->prepare("SELECT PrixAchatHT FROM article WHERE IDARTICLE = ?");
            $stmt->execute([$id_article]);
            $article_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $pmp_avant = $article_data ? (float)$article_data['PrixAchatHT'] : 0;
            $cout_unitaire_reel = $repartition_frais[$i]['cout_unitaire_reel'];
            $nouveau_stock = $stock_avant + $quantite;
            if ($nouveau_stock > 0) {
                $nouveau_pmp = (($stock_avant * $pmp_avant) + ($quantite * $cout_unitaire_reel)) / $nouveau_stock;
                $nouveau_pmp = round($nouveau_pmp, 2);
            } else {
                $nouveau_pmp = $cout_unitaire_reel;
            }
            // NOUVEAU PMP : Stocké dans la ligne d'entrée pour application ultérieure
            $stmt = $cnx->prepare("UPDATE entree_stock_ligne SET nouveau_pmp = ? WHERE IDENTREE_EN_STOCK = ? AND IDARTICLE = ?");
            $stmt->execute([$nouveau_pmp, $id_entre_stock, $id_article]);
            // --- FIN MODIFICATION ---

            // --- MODIFICATION : Alerte marge faible basée sur le nouveau PMP calculé ---
            // Récupérer le prix de vente actuel
            $stmt = $cnx->prepare("SELECT PrixVenteTTC FROM article WHERE IDARTICLE = ?");
            $stmt->execute([$id_article]);
            $article_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $prix_vente = $article_data ? (float)$article_data['PrixVenteTTC'] : 0;

            // Calcul de la marge avec le nouveau PMP
            $marge = $prix_vente - $nouveau_pmp;
            $marge_percent = $prix_vente > 0 ? ($marge / $prix_vente) * 100 : 0;

            // Marge cible (exemple : 30%)
            $marge_cible = 30;
            $prix_vente_conseille = round($nouveau_pmp * (1 + $marge_cible / 100), 2);

            if ($marge_percent < $marge_cible) {
                $_SESSION['info_message'] = "⚠️ Marge faible sur l'article {$article_info['libelle']} : ".round($marge_percent,2)."% seulement. Prix de vente conseillé : {$prix_vente_conseille} (marge cible {$marge_cible}%).";
            }
            // --- FIN MODIFICATION ---

            // Ajouter l'article à la liste des articles avec numéro de série
            $articles_avec_serie[] = [
                'id_article' => $id_article,
                'id_stock' => $id_stock,
                'libelle' => $article_info['libelle'],
                'quantite' => $quantite,
                'prix_achat' => $prix_achat,
                'prix_vente' => $prix_vente
            ];

            $total_prix_achat += $prix_achat * $quantite;
            $total_prix_vente += $prix_vente * $quantite;
            $articles_traites[$id_article] = true;
        }

        // Vérification s'il reste des articles valides après filtrage
        if (empty($articles_avec_serie)) {
            throw new Exception("⚠️ ERREUR : Aucun article valide n'a été trouvé.");
        }

        // Mise à jour des montants totaux dans l'entrée en stock
            $stmt = $cnx->prepare("
            UPDATE entree_en_stock 
            SET MontantAchatHT = ?, MontantVenteTTC = ? 
            WHERE IDENTREE_STOCK = ?
        ");
        $stmt->execute([$total_prix_achat, $total_prix_vente, $id_entre_stock]);

        // Stocker les articles en session pour la saisie des numéros de série
            $_SESSION['articles_avec_serie'] = $articles_avec_serie;
        $_SESSION['success_message'] = "✅ ENTRÉE EN STOCK CRÉÉE !\n\n" .
            "• L'entrée en stock a été créée avec succès\n" .
            "• Les articles ont été enregistrés\n" .
            "• Veuillez maintenant saisir les numéros de série pour chaque article";
            
        // --- AMÉLIORATION : Journalisation de la création d'entrée en stock ---
        $description_creation = sprintf(
            "Création entrée en stock n°%d - Fournisseur: %s - Bon: %s - Articles: %d - Montant: %.2f FCFA",
            $id_entre_stock,
            $fournisseur_info['NomFournisseur'],
            $numeroBon,
            count($articles_avec_serie),
            $total_prix_achat
        );
        
        // Journaliser la création d'entrée en stock
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'CREATION_ENTREE_STOCK',
                'STOCK',
                'entre_stock.php',
                $description_creation,
                null,
                [
                    'id_entree_stock' => $id_entre_stock,
                    'fournisseur' => $fournisseur_info['NomFournisseur'],
                    'numero_bon' => $numeroBon,
                    'date_livraison' => $dateLivraison,
                    'articles_count' => count($articles_avec_serie),
                    'montant_achat' => $total_prix_achat,
                    'montant_vente' => $total_prix_vente,
                    'frais_annexes' => $frais_annexes
                ],
                'MEDIUM',
                'SUCCESS',
                null
            );
        }
        // --- FIN AMÉLIORATION ---
            
        // Validation de la transaction avant la redirection
        $cnx->commit();
            
        header("Location: ../entrer_numero.php?id=" . $id_entre_stock);
        exit();

    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
      
        // --- AMÉLIORATION : Journalisation des erreurs de création d'entrée en stock ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_CREATION_ENTREE_STOCK',
                'STOCK',
                'entre_stock.php',
                "Erreur lors de la création d'entrée en stock: " . $e->getMessage(),
                null,
                [
                    'erreur' => $e->getMessage(),
                    'fournisseur' => $_POST['fournisseur'] ?? 'N/A',
                    'numero_bon' => $_POST['numeroBon'] ?? 'N/A',
                    'date_livraison' => $_POST['dateLivraison'] ?? 'N/A'
                ],
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN AMÉLIORATION ---
        
        // Nettoyer les messages précédents
        unset($_SESSION['success_message']);
        unset($_SESSION['info_message']);
        
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../entre_stock.php');
        exit();
    }
}

if (isset($_POST['enregistrer_numero_serie'])) {
    try {
    $numeros_serie = $_POST['numero_serie'] ?? [];
    $redirection = '../entre_stock.php';
    $redirection_numeros = '../entrer_numero.php';
        $id_entre_stock = $_POST['id_entre_stock'] ?? null;  // Changé de $_GET à $_POST

        if (empty($numeros_serie)) {
            $_SESSION['error_message'] = "⚠️ ERREUR : Aucun numéro de série n'a été saisi. Veuillez saisir les numéros de série pour tous les articles.";
            header('Location: ' . $redirection_numeros . '?id=' . $id_entre_stock);
            exit();
        }

        if (!$id_entre_stock) {
            $_SESSION['error_message'] = "⚠️ ERREUR : Identifiant de l'entrée en stock manquant. Veuillez réessayer depuis la page d'entrée en stock.";
            header('Location: ' . $redirection);
            exit();
        }

      

        $cnx->beginTransaction();
        
        // Récupérer tous les articles de l'entrée en stock
            $stmt = $cnx->prepare("
            SELECT e.*, esl.*, a.libelle, a.IDARTICLE, e.IDFOURNISSEUR 
            FROM entree_en_stock e
            JOIN entree_stock_ligne esl ON e.IDENTREE_STOCK = esl.IDENTREE_EN_STOCK
                JOIN article a ON esl.IDARTICLE = a.IDARTICLE 
            WHERE e.IDENTREE_STOCK = ? AND e.statut = 'EN_COURS'
            FOR UPDATE
            ");
            $stmt->execute([$id_entre_stock]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($articles)) {
            $stmt = $cnx->prepare("SELECT statut FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
            $stmt->execute([$id_entre_stock]);
            $statut = $stmt->fetchColumn();
            
            if ($statut === 'TERMINE') {
                $_SESSION['info_message'] = "✅ INFORMATION : Cette entrée en stock a déjà été validée avec succès.";
                header('Location: ' . $redirection);
                exit();
            } else {
                $_SESSION['error_message'] = "⚠️ ERREUR : L'entrée en stock n°$id_entre_stock n'a pas été trouvée.";
                header('Location: ' . $redirection);
                exit();
            }
        }

        // Supprimer les anciens numéros de série s'ils existent
        $stmt = $cnx->prepare("DELETE FROM num_serie WHERE ID_ENTRER_STOCK = ?");
        $stmt->execute([$id_entre_stock]);

        // Vérifier que nous avons le bon nombre de numéros de série pour chaque article
        $numeros_par_article = [];
        $index_courant = 0;

        foreach ($articles as $article) {
            $id_article = $article['IDARTICLE'];
            $quantite = $article['Quantite'];
            $numeros_article = [];

            // Récupérer les numéros de série pour cet article
            for ($i = 0; $i < $quantite; $i++) {
                if (!isset($numeros_serie[$index_courant])) {
                    throw new Exception("⚠️ ERREUR : Il manque des numéros de série pour l'article " . $article['libelle']);
                }
                $numeros_article[] = $numeros_serie[$index_courant];
                $index_courant++;
            }

            $numeros_par_article[$id_article] = $numeros_article;
        }

        // Vérifier qu'il n'y a pas de numéros de série en trop
        if ($index_courant < count($numeros_serie)) {
            throw new Exception("⚠️ ERREUR : Vous avez saisi trop de numéros de série.");
        }

        // Vérification préalable de tous les numéros de série pour détecter les conflits
        $numeros_en_conflit = [];
        foreach ($numeros_par_article as $id_article => $numeros) {
            foreach ($numeros as $numero) {
                if (!empty($numero)) {
                    $stmt = $cnx->prepare("SELECT COUNT(*) FROM num_serie WHERE NUMERO_SERIE = ?");
                    $stmt->execute([$numero]);
                    if ($stmt->fetchColumn() > 0) {
                        $numeros_en_conflit[] = $numero;
                    }
                }
            }
        }
        
        // Si des numéros sont en conflit, afficher tous les conflits d'un coup
        if (!empty($numeros_en_conflit)) {
            $stmt = $cnx->prepare("SELECT libelle FROM article WHERE IDARTICLE = ?");
            $stmt->execute([array_keys($numeros_par_article)[0]]);
            $libelle_article = $stmt->fetchColumn();
            
            $numeros_conflit_str = implode(', ', $numeros_en_conflit);
            $_SESSION['error_message'] = "⚠️ ERREUR : Les numéros de série suivants existent déjà dans la base de données pour l'article '$libelle_article' : $numeros_conflit_str. Veuillez utiliser des numéros différents.";
            
            // Redirection vers la page de saisie des numéros de série
            header('Location: ' . $redirection_numeros . '?id=' . $id_entre_stock);
            exit();
        }

        // Enregistrer les numéros de série et mettre à jour le stock
        foreach ($numeros_par_article as $id_article => $numeros) {
            // Récupérer les informations de l'article pour la journalisation (une seule fois par article)
            $stmt = $cnx->prepare("SELECT libelle FROM article WHERE IDARTICLE = ?");
            $stmt->execute([$id_article]);
            $libelle_article = $stmt->fetchColumn();
            
            // Récupérer le nom du fournisseur
            $stmt = $cnx->prepare("SELECT NomFournisseur FROM fournisseur WHERE IDFOURNISSEUR = ?");
            $stmt->execute([$articles[0]['IDFOURNISSEUR']]);
            $nom_fournisseur = $stmt->fetchColumn();

            $description = sprintf(
                "Entrée en stock de %s - Fournisseur: %s - Quantité: %d - Numéros de série: %s",
                $libelle_article,
                $nom_fournisseur,
                count($numeros),
                implode(', ', $numeros)
            );
            
            foreach ($numeros as $numero) {
                if (empty($numero)) {
                    throw new Exception("⚠️ ERREUR : Tous les numéros de série doivent être renseignés.");
                }

                // Vérification d'unicité avec verrouillage (déjà vérifié avant, mais double sécurité)
                $stmt = $cnx->prepare("SELECT COUNT(*) FROM num_serie WHERE NUMERO_SERIE = ? FOR UPDATE");
                $stmt->execute([$numero]);
                if ($stmt->fetchColumn() > 0) {
                    // Cette vérification ne devrait plus jamais se déclencher car on a déjà vérifié avant
                    throw new Exception("⚠️ ERREUR : Le numéro de série '$numero' existe déjà dans la base de données.");
                }

                // Récupération du stock actuel avant modification
                $stmt = $cnx->prepare("SELECT StockActuel, TotalEntree FROM stock WHERE IDARTICLE = ? FOR UPDATE");
                $stmt->execute([$id_article]);
                $stock_actuel = $stmt->fetch(PDO::FETCH_ASSOC);

                // Insertion du numéro de série
                $stmt = $cnx->prepare("
                    INSERT INTO num_serie (
                        IDARTICLE, 
                        NUMERO_SERIE, 
                        ID_ENTRER_STOCK, 
                        DATE_ENTREE,
                        statut
                    ) VALUES (?, ?, ?, CURDATE(), 'disponible')
                ");
                $stmt->execute([
                    $id_article,
                    $numero,
                    $id_entre_stock
                ]);

              
                // Variables supprimées - journalisation déplacée après la boucle

                // Mise à jour du stock avec vérification
                $nouveau_stock = $stock_actuel['StockActuel'] + 1;
                $nouveau_total_entree = $stock_actuel['TotalEntree'] + 1;
                
                // Vérifier que le stock ne devient pas négatif
                if ($nouveau_stock < 0) {
                    throw new Exception("Erreur: Le stock ne peut pas être négatif pour l'article {$libelle_article}.");
                }
                
                $stmt = $cnx->prepare("
                    UPDATE stock 
                    SET StockActuel = ?,
                        TotalEntree = ?
                    WHERE IDARTICLE = ?
                ");
                $stmt->execute([
                    $nouveau_stock,
                    $nouveau_total_entree,
                    $id_article
                ]);

                // Vérification après mise à jour
                $stmt = $cnx->prepare("SELECT StockActuel, TotalEntree FROM stock WHERE IDARTICLE = ?");
                $stmt->execute([$id_article]);
                $stock_apres = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // --- NOUVEAU : Application du PMP après validation des numéros de série ---
                // Récupérer le nouveau PMP calculé lors de la création de l'entrée
                $stmt = $cnx->prepare("SELECT nouveau_pmp FROM entree_stock_ligne WHERE IDENTREE_EN_STOCK = ? AND IDARTICLE = ?");
                $stmt->execute([$id_entre_stock, $id_article]);
                $nouveau_pmp = $stmt->fetchColumn();
                
                if ($nouveau_pmp !== false && $nouveau_pmp > 0) {
                    // Appliquer le nouveau PMP à l'article
                    $stmt = $cnx->prepare("UPDATE article SET PrixAchatHT = ? WHERE IDARTICLE = ?");
                    $stmt->execute([$nouveau_pmp, $id_article]);
                    
                    // Marquer le PMP comme appliqué
                    $stmt = $cnx->prepare("UPDATE entree_stock_ligne SET pmp_applique = 1 WHERE IDENTREE_EN_STOCK = ? AND IDARTICLE = ?");
                    $stmt->execute([$id_entre_stock, $id_article]);
                }
                // --- FIN NOUVEAU ---

                // Journalisation supprimée de la boucle - sera faite une seule fois par article après
            }
            
            // Journalisation unifiée (une seule fois par article)
            if (function_exists('logSystemAction')) {
                logSystemAction(
                    $cnx,
                    'ENREGISTREMENT_NUMERO_SERIE',
                    'STOCK',
                    'entrer_numero.php',
                    $description,
                    null,
                    [
                        'id_article' => $id_article,
                        'libelle_article' => $libelle_article,
                        'numeros_serie' => $numeros,
                        'id_entree_stock' => $id_entre_stock,
                        'fournisseur' => $nom_fournisseur,
                        'quantite' => count($numeros)
                    ],
                    'MEDIUM',
                    'SUCCESS',
                    null
                );
            }
        }

        // Mise à jour du statut de l'entrée en stock
        $stmt = $cnx->prepare("UPDATE entree_en_stock SET statut = 'TERMINE' WHERE IDENTREE_STOCK = ?");
        $stmt->execute([$id_entre_stock]);

        // --- AMÉLIORATION : Journalisation de la validation complète d'entrée en stock ---
        $description_validation = sprintf(
            "Validation complète entrée en stock n°%d - %d articles traités - Stock mis à jour",
            $id_entre_stock,
            count($articles)
        );
        
        // Journaliser la validation complète
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'VALIDATION_ENTREE_STOCK',
                'STOCK',
                'entrer_numero.php',
                $description_validation,
                null,
                [
                    'id_entree_stock' => $id_entre_stock,
                    'articles_traites' => count($articles),
                    'statut' => 'TERMINE',
                    'action' => 'validation_complete'
                ],
                'MEDIUM',
                'SUCCESS',
                null
            );
        }
        // --- FIN AMÉLIORATION ---

        $cnx->commit();
        unset($_SESSION['articles_avec_serie']);
     
        $_SESSION['success_message'] = "✅ ENTRÉE EN STOCK COMPLÈTEMENT TERMINÉE !\n\n" .
            "• Tous les numéros de série ont été enregistrés\n" .
            "• L'entrée en stock a été validée\n" .
            "• Le stock a été mis à jour\n" .
            "• Les montants ont été calculés\n\n" .
            "Vous pouvez maintenant consulter la liste des entrées en stock.";
        
      
        
        header('Location: ' . $redirection);
        exit();

    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
      
        // --- AMÉLIORATION : Journalisation des erreurs de validation d'entrée en stock ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_VALIDATION_ENTREE_STOCK',
                'STOCK',
                'entrer_numero.php',
                "Erreur lors de la validation d'entrée en stock: " . $e->getMessage(),
                null,
                [
                    'erreur' => $e->getMessage(),
                    'id_entree_stock' => $id_entre_stock,
                    'numeros_serie_count' => count($numeros_serie ?? [])
                ],
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN AMÉLIORATION ---
      
        $_SESSION['error_message'] = $e->getMessage();
        
        
        
        if ($id_entre_stock) {
            header('Location: ../entrer_numero.php?id=' . $id_entre_stock);
        } else {
            header('Location: ' . $redirection);
        }
        exit();
    }
}
if (isset($_POST['annuler_entree_stock'])) {
    $id_entre_stock = $_POST['id_entre_stock'] ?? null;
    if (!$id_entre_stock) {
        $_SESSION['error_message'] = "Identifiant de l'entrée en stock manquant.";
        header('Location: ../entre_stock.php');
        exit();
    }

    try {
        $cnx->beginTransaction();

        // Vérifier l'existence de l'entrée en stock
        $stmt = $cnx->prepare("SELECT * FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
        $stmt->execute([$id_entre_stock]);
        $entree = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entree) {
            throw new Exception("Entrée en stock non trouvée.");
        }

        // Récupérer les articles liés à cette entrée AVANT suppression
        $stmt = $cnx->prepare("SELECT IDARTICLE, Quantite FROM entree_stock_ligne WHERE IDENTREE_EN_STOCK = ?");
        $stmt->execute([$id_entre_stock]);
        $articles_entree = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$articles_entree || count($articles_entree) === 0) {
            throw new Exception("Aucun article lié à cette entrée en stock.");
        }

        /* Suppression des numéros de série liés
        $stmt = $cnx->prepare("DELETE FROM num_serie WHERE ID_ENTRER_STOCK = ?");
        $stmt->execute([$id_entre_stock]);*/

        // Suppression des lignes de l'entrée
        $stmt = $cnx->prepare("DELETE FROM entree_stock_ligne WHERE IDENTREE_EN_STOCK = ?");
        $stmt->execute([$id_entre_stock]);

        // Suppression de l'entrée en stock
        $stmt = $cnx->prepare("DELETE FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
        $stmt->execute([$id_entre_stock]);

        // --- CORRECTION : Restauration intelligente du stock selon le statut ---
        // Vérifier d'abord le statut de l'entrée pour déterminer l'action
        $statut_entree = $entree['statut'];
        
        if ($statut_entree === 'TERMINE') {
            // Entrée complètement validée avec numéros de série → Restaurer le stock
        foreach ($articles_entree as $article_entree) {
            $id_article = $article_entree['IDARTICLE'];
            $quantite_entree = $article_entree['Quantite'];
            
            if ($quantite_entree > 0) {
                // Vérifier le stock actuel avant de soustraire
                $stmt = $cnx->prepare("SELECT StockActuel, TotalEntree FROM stock WHERE IDARTICLE = ?");
                $stmt->execute([$id_article]);
                $stock_actuel = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($stock_actuel) {
                    $nouveau_stock = max(0, $stock_actuel['StockActuel'] - $quantite_entree);
                    $nouveau_total_entree = max(0, $stock_actuel['TotalEntree'] - $quantite_entree);
                    
                    // Restaurer le stock en évitant les valeurs négatives
                    $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ?, TotalEntree = ? WHERE IDARTICLE = ?");
                    $stmt->execute([$nouveau_stock, $nouveau_total_entree, $id_article]);
                    
                    // Journaliser si le stock a été limité à 0
                    if ($stock_actuel['StockActuel'] - $quantite_entree < 0) {
                            $desc = "⚠️ Stock limité à 0 lors de l'annulation de l'entrée validée (était: {$stock_actuel['StockActuel']}, soustraction: {$quantite_entree})";
                            if (function_exists('logSystemAction')) {
                                logSystemAction(
                                    $cnx,
                                    'ANNULATION_ENTREE_STOCK_LIMITE',
                                    'STOCK',
                                    'entre_stock.php',
                                    $desc,
                                    [
                                        'stock_avant' => $stock_actuel['StockActuel'],
                                        'quantite_soustraction' => $quantite_entree,
                                        'id_article' => $id_article,
                                        'statut_entree' => 'TERMINE'
                                    ],
                                    [
                                        'stock_limite' => 0,
                                        'action' => 'limitation_stock_entree_validee'
                                    ],
                                    'HIGH',
                                    'SUCCESS',
                                    null
                                );
                            }
                        }
                    }
                }
            }
        } else {
            // Entrée partielle (EN_COURS) → AUCUNE modification du stock
            // Le stock n'a jamais été augmenté car aucun numéro de série n'a été enregistré
            error_log("Annulation d'entrée partielle - Aucune modification du stock (statut: $statut_entree)");
        }
        
        // Recalculer le PMP pour tous les articles concernés (seulement si entrée validée)
        if ($statut_entree === 'TERMINE') {
            foreach ($articles_entree as $article_entree) {
                $id_article = $article_entree['IDARTICLE'];
            
            // Recalculer le PMP basé sur les entrées restantes
            $stmt = $cnx->prepare("
                SELECT 
                    SUM(Quantite) AS total_qte, 
                    SUM((PrixAchat * Quantite) + part_frais_annexe) AS total_montant
                FROM entree_stock_ligne
                WHERE IDARTICLE = ?
            ");
            $stmt->execute([$id_article]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $total_qte = (float) ($data['total_qte'] ?? 0);
            $total_montant = (float) ($data['total_montant'] ?? 0);

            // Si plus de stock → restaurer le prix initial
            if ($total_qte > 0) {
                $nouveau_pmp = $total_montant / $total_qte;
            } else {
                // Récupération du prix initial
                $stmt = $cnx->prepare("SELECT PrixInitialHT FROM article WHERE IDARTICLE = ?");
                $stmt->execute([$id_article]);
                $prix_initial = $stmt->fetchColumn();

                if ($prix_initial === false || !is_numeric($prix_initial)) {
                    throw new Exception("PrixInitialHT invalide pour l'article $id_article.");
                }

                $nouveau_pmp = (float)$prix_initial;
            }

            // Mise à jour de l'article avec le PMP recalculé
            $stmt = $cnx->prepare("UPDATE article SET PrixAchatHT = ? WHERE IDARTICLE = ?");
            $stmt->execute([$nouveau_pmp, $id_article]);
            }
        }
        // --- FIN MODIFICATION ---

        // --- JOURNALISATION UNIFIÉE : Annulation d'entrée en stock ---
        // Récupérer le nom du fournisseur
        $stmt = $cnx->prepare("SELECT NomFournisseur FROM fournisseur WHERE IDFOURNISSEUR = ?");
        $stmt->execute([$entree['IDFOURNISSEUR']]);
        $nom_fournisseur = $stmt->fetchColumn();
        
        // Description adaptée selon le statut
        if ($statut_entree === 'TERMINE') {
            $description_annulation = sprintf(
                "Annulation entrée en stock validée n°%d - Fournisseur: %s - Bon: %s - %d articles restaurés - Stock et PMP recalculés",
                $id_entre_stock,
                $nom_fournisseur,
                $entree['Numero_bon'],
                count($articles_entree)
            );
        } else {
            $description_annulation = sprintf(
                "Annulation entrée en stock partielle n°%d - Fournisseur: %s - Bon: %s - %d articles - Aucune modification du stock (pas de numéros de série enregistrés)",
                $id_entre_stock,
                $nom_fournisseur,
                $entree['Numero_bon'],
                count($articles_entree)
            );
        }
        
        // Journaliser l'annulation (une seule ligne)
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ANNULATION_ENTREE_STOCK',
                'STOCK',
                'entre_stock.php',
                $description_annulation,
                [
                    'id_entree_stock' => $id_entre_stock,
                    'fournisseur_id' => $entree['IDFOURNISSEUR'],
                    'fournisseur_nom' => $nom_fournisseur,
                    'numero_bon' => $entree['Numero_bon'],
                    'articles_count' => count($articles_entree)
                ],
                [
                    'statut' => 'ANNULE',
                    'statut_entree_original' => $statut_entree,
                    'articles_restaures' => count($articles_entree),
                    'stock_restaure' => ($statut_entree === 'TERMINE'),
                    'pmp_recalcule' => ($statut_entree === 'TERMINE')
                ],
                'MEDIUM',
                'SUCCESS',
                null
            );
        }
        // --- FIN JOURNALISATION UNIFIÉE ---

        $cnx->commit();

        $_SESSION['success_message'] = "L'entrée en stock n°$id_entre_stock a bien été annulée.";
        header('Location: ../entre_stock.php');
        exit();

    } catch (Exception $e) {
        if ($cnx->inTransaction()) $cnx->rollBack();
        
        // --- AMÉLIORATION : Journalisation des erreurs d'annulation ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_ANNULATION_ENTREE_STOCK',
                'STOCK',
                'entre_stock.php',
                "Erreur lors de l'annulation d'entrée en stock: " . $e->getMessage(),
                null,
                [
                    'erreur' => $e->getMessage(),
                    'id_entree_stock' => $id_entre_stock
                ],
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN AMÉLIORATION ---
        
        error_log("Erreur annulation entrée stock : " . $e->getMessage());
        $_SESSION['error_message'] = "Erreur lors de l'annulation : " . $e->getMessage();
        header('Location: ../entre_stock.php');
        exit();
    }
}


if (isset($_POST['creer_motif'])) {
    $motif = $_POST['LibelleMotifMouvementStock'];
    $tableName = "motif_correction";
    $redirection = '../motif_correction_stock.php';
    $data = [
        'LibelleMotifMouvementStock' => $motif,
    ];

    $values = [$motif];
    $columns = ['LibelleMotifMouvementStock'];
    $count = verifier_element($tableName, $columns, $values, $redirection);
    if ($count > 0) {
        $erreur = "un insetion de l'element existe deja dans la table $tableName";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    } else {
        insertion_element($tableName, $data, $redirection);
        $success = "L'element a ete enregistrer.";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
        exit();
    }
}
if (isset($_POST['supprimer_motif'])) {
    $idIDMOTIF_MOUVEMENT_STOCK = $_POST['id_categorie'];
    $tableName = "motif_correction";
    $redirection = '../motif_correction_stock.php';
    supprimer_element($tableName, 'IDMOTIF_MOUVEMENT_STOCK', $idIDMOTIF_MOUVEMENT_STOCK, $redirection);
}

if (isset($_POST['submitProforma'])) {
    // Récupération des informations de la proforma
    $nomClient = htmlspecialchars($_POST['nomClient']);
    $telephone = htmlspecialchars($_POST['telephone']);
    $email = htmlspecialchars($_POST['email']);
    $dateFacture = htmlspecialchars($_POST['dateFacture']);
    $date_validite = htmlspecialchars($_POST['date_validite']);
    $conditionsReglement = htmlspecialchars($_POST['mode_paiement']);
    $TotalNetPayer = floatval($_POST['TotalNetPayer']);
    $redirection = '../facture_proforma.php';

    // Validation basique des données saisies
    if (empty($nomClient) || empty($telephone) || empty($dateFacture)) {
        echo 'Veuillez remplir tous les champs obligatoires.';
        exit();
    }

    $mode_paement = verifier_element('mode_reglement', ['IDMODE_REGLEMENT'], [$conditionsReglement], '');

    // Enregistrement de la facture proforma
    $data_Facture_proforma = [
        'ClientProforma' => $nomClient,
        'ContactClientProforma' => $telephone,
        'email' => $email,
        'DateProforma' => $dateFacture,
        'date_validite' => $date_validite,
        'ConditionReglement' => $conditionsReglement,
        'TotalNetPayer' => $TotalNetPayer,
    ];
    $startTime = log_action_start();
    insertion_element('proforma', $data_Facture_proforma, '');  // Insertion de la proforma
    $IDPROFORMA = $cnx->lastInsertId();  // Récupération de l'ID de la proforma insérée

    // Enregistrement des lignes de la proforma (les articles)
    if (isset($_SESSION['proformaligne']) && !empty($_SESSION['proformaligne'])) {
        foreach ($_SESSION['proformaligne'] as $id_article => $produits) {
            // Récupération des détails de chaque produit dans le panier
            $quantite = intval($produits['quantite']);
            $prix = floatval($produits['prix']);
            $remise = floatval($produits['remise']);

            // Préparation des données pour insertion
            $data_proformaLigne = [
                'IDPROFORMA' => $IDPROFORMA,
                'IDARTICLE' => $id_article,  // Utilisation correcte de l'ID de l'article dans la session
                'Quantite' => $quantite,
                'MontantRemise' => $remise,
                'MontantProduitTTC' => $prix
            ];
            insertion_element('proformaligne', $data_proformaLigne, '');  // Insertion des lignes de la proforma
        }
    }
    
    // Préparer les données détaillées pour la journalisation
    $donnees_proforma = [
        'proforma' => [
            'id' => $IDPROFORMA,
            'client' => $nomClient,
            'telephone' => $telephone,
            'email' => $email,
            'date_proforma' => $dateFacture,
            'date_validite' => $date_validite,
            'total_net_payer' => $TotalNetPayer,
            'nombre_articles' => count($_SESSION['proformaligne'] ?? [])
        ],
        'articles' => $_SESSION['proformaligne'] ?? [],
        'operateur' => [
            'id' => $_SESSION['id_utilisateur'],
            'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
            'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
        ]
    ];
    
    // Journalisation création proforma (système unifié)
    $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
    $description_detaille = 'Création proforma: Client ' . $nomClient . ' (Opérateur: ' . $operateur_nom . ') - Total: ' . number_format($TotalNetPayer, 0, ',', ' ') . ' FCFA - Articles: ' . count($_SESSION['proformaligne'] ?? []);
    
    logSystemAction($cnx, 'CREATION_PROFORMA', 'PROFORMA', 'request.php', 
        $description_detaille, 
        null, $donnees_proforma, 'HIGH', 'SUCCESS', log_action_end($startTime));
    
    // Vider le panier après l'enregistrement de la proforma
    //unset($_SESSION['proformaligne']);

    // Redirection ou confirmation après l'enregistrement
    //$success = "Proforma valider avec success";
    //header("Location: $redirection?success=" . urlencode($success));
    //exit();
    header('Location: ../facture_proforma.php?success=commande enregistrée avec succès !');
exit();
    exit();
}

if (isset($_POST['valider_commande'])) {
    $fournisseur = htmlspecialchars($_POST['fournisseur']);
    $numbon = htmlspecialchars($_POST['numeroBon']); // Corrected input name
    $date_commande = htmlspecialchars($_POST['dateLivraison']);
    $totalprixAchat = htmlspecialchars($_POST['TotalNetPayer']);
    $redirection = '../bon_commande.php';
    $data_commande = [
        'numero_commande' => $numbon,
        'IDFOURNISSEUR' => $fournisseur,
        'date_commande' => $date_commande,
        'totalprixAchat' => $totalprixAchat,
    ];
    $startTime = log_action_start();
    insertion_element('commande', $data_commande, '');
    $id_commande = $cnx->lastInsertId();

    if (isset($_SESSION['commandemaligne']) && !empty($_SESSION['commandemaligne'])) {
        foreach ($_SESSION['commandemaligne'] as $id_article => $produits) {

            $quantite = intval($produits['quantite']);
            $prix = floatval($produits['prix']);
            $data_commandeligne = [
                'id' => $id_commande,
                'IDARTICLE' => $id_article,
                'quantite' => $quantite,
                'prixAchat' => $prix
            ];
            insertion_element('commande_ligne', $data_commandeligne, '');
        }
    }
    
    // Journalisation création commande (version simplifiée)
    try {
        logSystemAction($cnx, 'CREATION_COMMANDE', 'COMMANDES', 'request.php', 
            'Création commande: N°' . $numbon . ' - Total: ' . $totalprixAchat . ' FCFA', 
            null, [
                'numero_commande' => $numbon,
                'id_fournisseur' => $fournisseur,
                'date_commande' => $date_commande,
                'total_prix_achat' => $totalprixAchat,
                'nombre_articles' => count($_SESSION['commandemaligne'] ?? [])
            ], 'HIGH', 'SUCCESS', log_action_end($startTime));
    } catch (Exception $e) {
        // En cas d'erreur de journalisation, continuer quand même
        error_log("Erreur journalisation commande: " . $e->getMessage());
    }
    
    // Redirection après journalisation
    header('Location: ../bon_commande.php?success=commande enregistrée avec succès !');
exit();
    exit();
}


// Suppression d'une vente
if (isset($_POST['action']) && $_POST['action'] === 'supprimer_vente') {
    try {
        $numero_vente = $_POST['numero_vente_suppression'];
        
        if (empty($numero_vente)) {
            throw new Exception("Numéro de vente manquant.");
        }

        // Démarrer une transaction
        $cnx->beginTransaction();

        // 1. Récupérer les informations de la vente
        $stmt = $cnx->prepare("SELECT * FROM vente WHERE NumeroVente = ?");
        $stmt->execute([$numero_vente]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vente) {
            throw new Exception("Vente introuvable.");
        }

        // 2. Récupérer les articles de la vente (sans duplication)
        $stmt = $cnx->prepare("
            SELECT DISTINCT fa.IDARTICLE, fa.QuantiteVendue, a.libelle
            FROM facture_article fa
            JOIN article a ON fa.IDARTICLE = a.IDARTICLE
            WHERE fa.NumeroVente = ?
        ");
        $stmt->execute([$numero_vente]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Récupérer tous les numéros de série de cette vente
        $stmt = $cnx->prepare("
            SELECT NUMERO_SERIE, IDARTICLE
            FROM num_serie 
            WHERE NumeroVente = ? AND statut = 'vendue'
        ");
        $stmt->execute([$numero_vente]);
        $numeros_serie = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Remettre le stock (une seule fois par article)
        foreach ($articles as $article) {
            // Remettre le stock
            $stmt = $cnx->prepare("UPDATE stock SET StockActuel = StockActuel + ? WHERE IDARTICLE = ?");
            $stmt->execute([$article['QuantiteVendue'], $article['IDARTICLE']]);

            // Journaliser la suppression
            $description = "Suppression de vente - Article: " . $article['libelle'] . 
                          " - Quantité: " . $article['QuantiteVendue'] . 
                          " - Vente: " . $numero_vente;
            
            // Journalisation unifiée de la suppression de vente
            if (function_exists('logSystemAction')) {
                logSystemAction(
                    $cnx,
                    'SUPPRESSION_VENTE',
                    'VENTES',
                    'request.php',
                    $description,
                    [
                        'id_article' => $article['IDARTICLE'],
                        'libelle_article' => $article['libelle'],
                        'numero_vente' => $numero_vente
                    ],
                    [
                        'action' => 'suppression_vente',
                        'utilisateur' => $_SESSION['id_utilisateur']
                    ],
                    'MEDIUM',
                    'SUCCESS',
                    null
                );
            }
        }

        // 5. Libérer tous les numéros de série
        foreach ($numeros_serie as $numero) {
            $stmt = $cnx->prepare("UPDATE num_serie SET ID_VENTE = NULL, NumeroVente = NULL, statut = 'disponible' WHERE NUMERO_SERIE = ?");
            $stmt->execute([$numero['NUMERO_SERIE']]);
            
            // Journalisation libération numéro de série
            $startTime = log_action_start();
            logSystemAction($cnx, 'LIBERATION_NUMERO_SERIE', 'STOCK', 'request.php', 
                'Libération numéro de série: ' . $numero['NUMERO_SERIE'] . ' - Article ID: ' . $numero['IDARTICLE'], 
                ['statut_avant' => 'vendue'], ['statut_apres' => 'disponible'], 'MEDIUM', 'SUCCESS', log_action_end($startTime));
        }

        // 4. Supprimer les paiements multiples s'ils existent
        $stmt = $cnx->prepare("DELETE FROM vente_paiement WHERE IDFactureVente = ?");
        $stmt->execute([$vente['IDFactureVente']]);

        // 5. Supprimer les lignes de facture
        $stmt = $cnx->prepare("DELETE FROM facture_article WHERE NumeroVente = ?");
        $stmt->execute([$numero_vente]);

        // 6. Supprimer la vente
        $stmt = $cnx->prepare("DELETE FROM vente WHERE NumeroVente = ?");
        $stmt->execute([$numero_vente]);

        // 7. Valider la transaction
        $cnx->commit();
        
        // Préparer les données détaillées pour la journalisation
        $donnees_vente_supprime = [
            'vente_supprime' => [
                'numero_vente' => $numero_vente,
                'client' => $vente['NomPrenomClient'] ?? 'Inconnu',
                'montant_total' => $vente['MontantTotal'] ?? 0,
                'date_vente' => $vente['DateVente'] ?? 'Inconnu',
                'articles_restaures' => count($articles),
                'numeros_serie_liberes' => count($numeros_serie)
            ],
            'articles_restaures' => $articles,
            'numeros_serie_liberes' => $numeros_serie,
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 0,
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation suppression vente (système unifié)
        $startTime = log_action_start();
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Suppression vente: N°' . $numero_vente . ' - Client: ' . ($vente['NomPrenomClient'] ?? 'Inconnu') . ' (Opérateur: ' . $operateur_nom . ') - Montant: ' . number_format($vente['MontantTotal'] ?? 0, 0, ',', ' ') . ' FCFA - Articles restaurés: ' . count($articles);
        
        logSystemAction($cnx, 'SUPPRESSION_VENTE', 'VENTES', 'request.php', 
            $description_detaille, 
            $vente, $donnees_vente_supprime, 'CRITICAL', 'SUCCESS', log_action_end($startTime));

        $success = "La vente N°" . $numero_vente . " a été supprimée avec succès.";
        header('Location: ../listes_vente.php?success=' . urlencode($success));
        exit();

    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        $erreur = "Erreur lors de la suppression : " . $e->getMessage();
        header('Location: ../listes_vente.php?error=' . urlencode($erreur));
        exit();
    }
}

if (isset($_POST['mettre_a_jour_fournisseur'])) {
    try {
    $idFournisseur = $_POST['idFournisseur'];
    $nomFournisseur = $_POST['nomFournisseur'];
    $emailFournisseur = $_POST['emailFournisseur'];
    $telephoneFournisseur = $_POST['telephoneFournisseur'];
    $tableName = "fournisseur";
    $redirection = '../fournisseur.php';

    // Vérifier si c'est le fournisseur système (ID = 1)
        if ($idFournisseur == 1) {
            $erreur = "Le fournisseur système (ID: 1) ne peut pas être modifié car il est utilisé par le système d'inventaire.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }

        // Récupérer les données avant modification
        $stmt = $cnx->prepare("SELECT * FROM fournisseur WHERE IDFOURNISSEUR = ?");
        $stmt->execute([$idFournisseur]);
        $fournisseurAvant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fournisseurAvant) {
            throw new Exception("Fournisseur non trouvé avec l'ID: " . $idFournisseur);
        }

        $startTime = log_action_start();

    $columns = ['NomFournisseur', 'eMailFournisseur', 'TelephoneFournisseur'];
    $values = [$nomFournisseur, $emailFournisseur, $telephoneFournisseur];

    modifier_element($tableName, $columns, $values, 'IDFOURNISSEUR', $idFournisseur, $redirection);
        
        // Détecter les changements pour une description plus précise
        $changements = [];
        if (($fournisseurAvant['NomFournisseur'] ?? '') != $nomFournisseur) {
            $changements[] = 'Nom: ' . ($fournisseurAvant['NomFournisseur'] ?? 'Inconnu') . ' → ' . $nomFournisseur;
        }
        if (($fournisseurAvant['eMailFournisseur'] ?? '') != $emailFournisseur) {
            $changements[] = 'Email: ' . ($fournisseurAvant['eMailFournisseur'] ?? 'Inconnu') . ' → ' . $emailFournisseur;
        }
        if (($fournisseurAvant['TelephoneFournisseur'] ?? '') != $telephoneFournisseur) {
            $changements[] = 'Téléphone: ' . ($fournisseurAvant['TelephoneFournisseur'] ?? 'Inconnu') . ' → ' . $telephoneFournisseur;
        }
        
        // Préparer les données détaillées pour la journalisation
        $donnees_fournisseur_apres = [
            'fournisseur' => [
                'id' => $idFournisseur,
                'nom' => $nomFournisseur,
                'email' => $emailFournisseur,
                'telephone' => $telephoneFournisseur
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'],
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        // Journalisation modification fournisseur (système unifié)
        $operateur_nom = $_SESSION['nom_complet'] ?? 'Opérateur inconnu';
        $description_detaille = 'Modification fournisseur ID: ' . $idFournisseur . ' - Changements: ' . implode(', ', $changements) . ' (Opérateur: ' . $operateur_nom . ')';
        
        logSystemAction($cnx, 'MODIFICATION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
            $description_detaille, 
            $fournisseurAvant, $donnees_fournisseur_apres, 'HIGH', 'SUCCESS', log_action_end($startTime));
        
    $success = "Le fournisseur a été mis à jour avec succès.";
    header('Location: ' . $redirection . '?success=' . urlencode($success));
    exit();
        
    } catch (Exception $e) {
        // Journalisation de l'échec de modification
        $startTime = log_action_start();
        $donnees_echec = [
            'fournisseur' => [
                'id' => $idFournisseur ?? 'Inconnu',
                'nom' => $nomFournisseur ?? 'Inconnu',
                'email' => $emailFournisseur ?? 'Inconnu',
                'telephone' => $telephoneFournisseur ?? 'Inconnu',
                'erreur' => $e->getMessage()
            ],
            'operateur' => [
                'id' => $_SESSION['id_utilisateur'] ?? 'Inconnu',
                'nom' => $_SESSION['nom_complet'] ?? 'Inconnu',
                'identifiant' => $_SESSION['identifiant'] ?? 'Inconnu'
            ]
        ];
        
        logSystemAction($cnx, 'MODIFICATION_FOURNISSEUR', 'FOURNISSEURS', 'request.php', 
            'Échec modification fournisseur ID: ' . ($idFournisseur ?? 'Inconnu') . ' - Erreur: ' . $e->getMessage(), 
            null, $donnees_echec, 'HIGH', 'FAILED', log_action_end($startTime));
        
        $erreur = "Erreur lors de la modification du fournisseur : " . $e->getMessage();
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
    exit();
    }
}

if (isset($_POST['modifier_paiement'])) {
    $idModeReglement = $_POST['idModeReglement'];
    $modeReglement = $_POST['mode_reglement'];
    $numero = $_POST['numero'];
    $tableName = "mode_reglement";
    $redirection = '../mode_reglement.php';

    $columns = ['ModeReglement', 'numero'];
    $values = [$modeReglement, $numero];

    modifier_element($tableName, $columns, $values, 'IDMODE_REGLEMENT', $idModeReglement, $redirection);
    $success = "Le mode de règlement a été mis à jour avec succès.";
    header('Location: ' . $redirection . '?success=' . urlencode($success));
    exit();
}



if (isset($_POST['action']) && ($_POST['action'] === 'supprimer_entree_stock' || $_POST['action'] === 'vider_toutes_entrees')) {
    try {
        $cnx->beginTransaction();
        error_log("Début de la transaction.");
        if (isset($_POST['action']) && $_POST['action'] === 'supprimer_entree_stock') {
            $id_entre_stock = $_POST['id_entre_stock'] ?? null;
        
            error_log("===== Suppression déclenchée à " . date("Y-m-d H:i:s") . " =====");
        
            if (empty($id_entre_stock)) {
                error_log("❌ ID entrée stock non défini !");
                return;
            }
        
        
            try {
                $cnx->beginTransaction();
        
                // 1. Vérification existence
                $stmt = $cnx->prepare("SELECT * FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
                $stmt->execute([$id_entre_stock]);
                $entree = $stmt->fetch(PDO::FETCH_ASSOC);
        
                if (!$entree) {
                    throw new Exception("Entrée introuvable.");
                }
                error_log("Entrée trouvée : " . json_encode($entree));
        
                // 2. Récupération des articles liés
                $stmt = $cnx->prepare("SELECT IDARTICLE FROM entree_stock_ligne WHERE IDENTREE_EN_STOCK = ?");
                $stmt->execute([$id_entre_stock]);
                $articles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                error_log("Articles à recalculer : " . implode(",", $articles));
        
                // 3. Supprimer lignes
                $stmt = $cnx->prepare("DELETE FROM entree_stock_ligne WHERE IDENTREE_EN_STOCK = ?");
                $stmt->execute([$id_entre_stock]);
                error_log("Lignes supprimées.");
        
                // 4. Supprimer numéros de série
                $stmt = $cnx->prepare("DELETE FROM num_serie WHERE ID_ENTRER_STOCK = ?");
                $stmt->execute([$id_entre_stock]);
                error_log("Numéros de série supprimés.");
        
                // 5. Supprimer l’entrée principale
                $stmt = $cnx->prepare("DELETE FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
                $stmt->execute([$id_entre_stock]);
                error_log("Entrée principale supprimée.");
        
                // 6. Recalcul du PMP
                foreach ($articles as $id_article) {
                    error_log("Recalcul PMP pour article $id_article");
        
                    $stmt = $cnx->prepare("
                        SELECT 
                            SUM(Quantite) AS total_qte,
                            SUM((PrixAchat * Quantite) + part_frais_annexe) AS total_montant
                        FROM entree_stock_ligne
                        WHERE IDARTICLE = ?
                    ");
                    $stmt->execute([$id_article]);
                    $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
                    $qte = (float)($data['total_qte'] ?? 0);
                    $mnt = (float)($data['total_montant'] ?? 0);
        
                    error_log("Article $id_article : qte=$qte, montant=$mnt");
        
                    if ($qte > 0) {
                        $pmp = $mnt / $qte;
                    } else {
                        // Stock vide => on remet PrixInitialHT
                        $stmt = $cnx->prepare("SELECT PrixInitialHT FROM article WHERE IDARTICLE = ?");
                        $stmt->execute([$id_article]);
                        $pmp = (float)$stmt->fetchColumn();
                        error_log("Stock vide. Prix initial pour $id_article : $pmp");
                    }
        
                    // Mise à jour
                    $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ?, PMP = ? WHERE IDARTICLE = ?");
                    $stmt->execute([$qte, $pmp, $id_article]);
        
                    $stmt = $cnx->prepare("UPDATE article SET PrixAchatHT = ? WHERE IDARTICLE = ?");
                    $stmt->execute([$pmp, $id_article]);
        
                    error_log("Stock et article mis à jour pour $id_article : Stock=$qte, PMP=$pmp");
                }
        
                // --- JOURNALISATION : Suppression d'entrée en stock ---
                $description_suppression = sprintf(
                    "Suppression entrée en stock n°%d - %d articles supprimés - Stock et PMP recalculés",
                    $id_entre_stock,
                    count($articles)
                );
                
                if (function_exists('logSystemAction')) {
                    logSystemAction(
                        $cnx,
                        'SUPPRESSION_ENTREE_STOCK',
                        'STOCK',
                        'request.php',
                        $description_suppression,
                        [
                            'id_entree_stock' => $id_entre_stock,
                            'articles_count' => count($articles),
                            'statut_entree' => $entree['statut'] ?? 'N/A'
                        ],
                        [
                            'action' => 'suppression_entree',
                            'articles_supprimes' => count($articles),
                            'stock_recalcule' => true,
                            'pmp_recalcule' => true
                        ],
                        'HIGH',
                        'SUCCESS',
                        null
                    );
                }
                // --- FIN JOURNALISATION ---
        
                $cnx->commit();
                error_log("Suppression terminée avec succès.");
                $_SESSION['success_message'] = "Suppression réussie.";
                header('Location: ../liste_entree_stock.php');
                exit();
        
            } catch (Exception $e) {
                if ($cnx->inTransaction()) {
                    $cnx->rollBack();
                }
                error_log("Erreur : " . $e->getMessage());
                
                // --- JOURNALISATION : Erreur suppression ---
                if (function_exists('logSystemAction')) {
                    logSystemAction(
                        $cnx,
                        'ERREUR_SUPPRESSION_ENTREE_STOCK',
                        'STOCK',
                        'request.php',
                        'Erreur lors de la suppression de l\'entrée en stock n°' . $id_entre_stock . ' : ' . $e->getMessage(),
                        [
                            'id_entree_stock' => $id_entre_stock,
                            'erreur' => $e->getMessage()
                        ],
                        null,
                        'HIGH',
                        'FAILED',
                        null
                    );
                }
                // --- FIN JOURNALISATION ---
                
                $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
                header('Location: ../liste_entree_stock.php');
                exit();
            }
        }
        

        // ------------------- VIDER TOUTES LES ENTREES --------------------
        else if ($_POST['action'] === 'vider_toutes_entrees') {
            error_log("Action : vider_toutes_entrees");

            // Compter les éléments avant suppression pour la journalisation
            $stmt = $cnx->query("SELECT COUNT(*) as nb_entrees FROM entree_en_stock");
            $nb_entrees = $stmt->fetchColumn();
            
            $stmt = $cnx->query("SELECT COUNT(*) as nb_numeros FROM num_serie");
            $nb_numeros = $stmt->fetchColumn();
            
            $stmt = $cnx->query("SELECT COUNT(*) as nb_articles FROM article");
            $nb_articles = $stmt->fetchColumn();

            $cnx->exec("DELETE FROM num_serie");
            $cnx->exec("DELETE FROM entree_stock_ligne");
            $cnx->exec("DELETE FROM entree_en_stock");
            error_log("Toutes les entrées, lignes et numéros de série supprimées.");

            $stmt = $cnx->query("SELECT IDARTICLE, PrixInitialHT FROM article");
            $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($articles as $article) {
                $id_article = $article['IDARTICLE'];
                $prix_initial = (float)$article['PrixInitialHT'];

                $stmt = $cnx->prepare("UPDATE stock SET StockActuel = 0, PMP = ? WHERE IDARTICLE = ?");
                $stmt->execute([$prix_initial, $id_article]);

                $stmt = $cnx->prepare("UPDATE article SET PrixAchatHT = ? WHERE IDARTICLE = ?");
                $stmt->execute([$prix_initial, $id_article]);

                error_log("Réinitialisation du stock pour article $id_article : StockActuel = 0, PMP = $prix_initial");
            }

            // --- JOURNALISATION : Vidage de toutes les entrées ---
            $description_vidage = sprintf(
                "Vidage complet de toutes les entrées en stock - %d entrées supprimées - %d numéros de série supprimés - %d articles réinitialisés",
                $nb_entrees,
                $nb_numeros,
                $nb_articles
            );
            
            if (function_exists('logSystemAction')) {
                logSystemAction(
                    $cnx,
                    'VIDAGE_TOUTES_ENTREES',
                    'STOCK',
                    'request.php',
                    $description_vidage,
                    [
                        'entrees_supprimees' => $nb_entrees,
                        'numeros_serie_supprimes' => $nb_numeros,
                        'articles_reinitialises' => $nb_articles
                    ],
                    [
                        'action' => 'vidage_complet',
                        'stock_reinitialise' => true,
                        'pmp_reinitialise' => true,
                        'toutes_entrees_supprimees' => true
                    ],
                    'CRITICAL',
                    'SUCCESS',
                    null
                );
            }
            // --- FIN JOURNALISATION ---

            $_SESSION['success_message'] = "Toutes les entrées en stock ont été vidées avec succès.";
        }

        $cnx->commit();
        error_log("Transaction commitée avec succès.");
        
        header('Location: ../liste_entree_stock.php');
        exit();

    } catch (Exception $e) {
        if ($cnx->inTransaction()) $cnx->rollBack();
        error_log("Erreur attrapée : " . $e->getMessage());
        
        // --- JOURNALISATION : Erreur générale ---
        if (function_exists('logSystemAction')) {
            $action_type = $_POST['action'] ?? 'UNKNOWN';
            $log_action = ($action_type === 'supprimer_entree_stock') ? 'ERREUR_SUPPRESSION_ENTREE_STOCK' : 'ERREUR_VIDAGE_ENTREES';
            
            logSystemAction(
                $cnx,
                $log_action,
                'STOCK',
                'request.php',
                'Erreur lors de l\'action ' . $action_type . ' : ' . $e->getMessage(),
                [
                    'action_type' => $action_type,
                    'erreur' => $e->getMessage()
                ],
                null,
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
        header('Location: ../liste_entree_stock.php');
        exit();
    }
}

// Correction automatique du stock basée sur les numéros de série disponibles
if (isset($_POST['action']) && $_POST['action'] === 'corriger_stock_auto') {
    try {
        $id_article = $_POST['id_article'];
        $nouveau_stock = $_POST['nouveau_stock'];
        
        if (empty($id_article) || !is_numeric($nouveau_stock)) {
            throw new Exception("Paramètres invalides pour la correction du stock.");
        }

        // Démarrer une transaction
        $cnx->beginTransaction();

        // Récupérer les informations de l'article
        $stmt = $cnx->prepare("SELECT a.libelle, s.StockActuel FROM article a LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE WHERE a.IDARTICLE = ?");
        $stmt->execute([$id_article]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            throw new Exception("Article introuvable.");
        }

        $stock_avant = $article['StockActuel'] ?? 0;
        $stock_apres = (int)$nouveau_stock;

        // Mettre à jour le stock
        $stmt = $cnx->prepare("UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?");
        $result = $stmt->execute([$stock_apres, $id_article]);

        if (!$result) {
            throw new Exception("Erreur lors de la mise à jour du stock.");
        }

        // Journaliser la correction
        $description = "CORRECTION AUTOMATIQUE STOCK - Article: " . $article['libelle'] . 
                      " - Stock avant: " . $stock_avant . 
                      " - Stock après: " . $stock_apres . 
                      " - Raison: Alignement avec les numéros de série disponibles";
        
        // Journalisation unifiée de la correction automatique de stock
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'CORRECTION_STOCK_AUTO',
                'STOCK',
                'request.php',
                $description,
                [
                    'id_article' => $id_article,
                    'stock_avant' => $stock_avant,
                    'stock_apres' => $stock_apres,
                    'ecart' => $stock_apres - $stock_avant
                ],
                [
                    'action' => 'correction_automatique',
                    'utilisateur' => $_SESSION['id_utilisateur']
                ],
                'MEDIUM',
                'SUCCESS',
                null
            );
        }

        // Valider la transaction
        $cnx->commit();

        $success = "✅ Stock corrigé avec succès pour l'article " . $article['libelle'] . " (Stock: $stock_avant → $stock_apres)";
        header('Location: ../verification_stock_consistency.php?success=' . urlencode($success));
        exit();

    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        
        $erreur = "❌ Erreur lors de la correction du stock : " . $e->getMessage();
        header('Location: ../verification_stock_consistency.php?error=' . urlencode($erreur));
        exit();
    }
}



// ... existing code ...
// Après la validation de la vente (juste avant la redirection et unset du panier)
if (isset($_POST['enregistrer_caise']) || (isset($_POST['action']) && $_POST['action'] === 'multi_paiement')) {
    // ... code existant ...
    // Après commit et avant unset($_SESSION['panier'])
    // Récupérer les données directement sans session
    $client = $nomprenom ?? '';
    $telephone = $numero ?? ($numero_client ?? '');
    $email = $email ?? ($Adresse_email ?? '');
    $articles = [];
    if (isset($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $id_article => $quantites) {
            foreach ($quantites as $numeroSerie => $details) {
                // Récupérer les informations complètes de l'article
                $stmt = $cnx->prepare("SELECT a.libelle, ns.NUMERO_SERIE, a.PrixVenteTTC FROM article a JOIN num_serie ns ON a.IDARTICLE = ns.IDARTICLE WHERE a.IDARTICLE = ? AND ns.NUMERO_SERIE = ?");
                $stmt->execute([$id_article, $numeroSerie]);
                $article_details = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($article_details) {
                    $articles[] = [
                        'id' => $id_article,
                        'libelle' => $article_details['libelle'],
                        'numero_serie' => $numeroSerie,
                        'prix_unitaire' => $article_details['PrixVenteTTC'],
                        'quantite' => $details['quantite'] ?? 1
                    ];
                }
            }
        }
    }
    $total = $montant_total ?? 0;
    $type_vente = (isset($_POST['action']) && $_POST['action'] === 'multi_paiement') ? 'comptant' : 'comptant';
    // Appel centralisé
    notifier_client_vente($client, $articles, $total, $email, $telephone, $type_vente);
    // ... code existant ...
}
// ... existing code ...

?>
<?php 
// Function to display session messages
function afficherMessagesSession() {
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo $_SESSION['success_message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        sendJsonResponse(false, $_SESSION['error_message'], null, 400);
        unset($_SESSION['error_message']);
    }

    if (isset($_SESSION['info_message'])) {
        echo '<div class="alert alert-info alert-dismissible fade show" role="alert">';
        echo $_SESSION['info_message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['info_message']);
    }
}

afficherMessagesSession(); 
?>