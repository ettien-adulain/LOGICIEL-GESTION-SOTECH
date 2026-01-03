<?php

if (!function_exists('deconnexion')) {
function deconnexion(){
    session_start();
    session_unset();
    session_destroy();
    header('Location: ../connexion.php');
    exit();
    }
}

if (!function_exists('connexion')) {
function connexion($identifiant, $motdepasse, $type) {
    global $cnx;
    if (!empty($identifiant) && !empty($motdepasse)) {
        $sql = "SELECT * FROM utilisateur WHERE Identifiant = :identifiant";
        $stmt = $cnx->prepare($sql);
        $stmt->bindParam(':identifiant', $identifiant);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user['actif'] !== 'oui') {
                // Journalisation échec connexion - compte inactif
                logSystemAction($cnx, 'ECHEC_CONNEXION', 'AUTHENTIFICATION', 'fonction.php', 
                    'Tentative de connexion avec compte inactif: ' . $identifiant, 
                    null, null, 'CRITICAL', 'FAILED');
                
                $erreur = "Votre compte est inactif. Veuillez contacter un administrateur.";
                header('Location: ../connexion.php?error=' . urlencode($erreur));
                exit();
            }

            if (password_verify($motdepasse, $user['MotDePasse'])) {
                session_start();
                $_SESSION['id_utilisateur'] = $user['IDUTILISATEUR'];
                $_SESSION['nom_utilisateur'] = $user['Identifiant'];
                $_SESSION['type_utilisateur'] = $user['fonction'];
                $_SESSION['nom_complet'] = $user['NomPrenom'];
                $_SESSION['role'] = isset($user['role']) ? $user['role'] : 'user';
                
                // Journalisation connexion réussie
                logSystemAction($cnx, 'CONNEXION_REUSSIE', 'AUTHENTIFICATION', 'fonction.php', 
                    'Connexion réussie pour: ' . $identifiant . ' (Rôle: ' . $_SESSION['role'] . ')', 
                    null, null, 'CRITICAL', 'SUCCESS');
                
                header('Location: ../index.php');
                exit();
            } else {
                // Journalisation échec connexion - mot de passe incorrect
                logSystemAction($cnx, 'ECHEC_CONNEXION', 'AUTHENTIFICATION', 'fonction.php', 
                    'Mot de passe incorrect pour: ' . $identifiant, 
                    null, null, 'CRITICAL', 'FAILED');
                
                $erreur = 'Mot de passe ou identifiant incorrect';
                header('Location: ../connexion.php?error=' . urlencode($erreur));
                exit();
            }
        } else {
            // Journalisation échec connexion - utilisateur inexistant
            logSystemAction($cnx, 'ECHEC_CONNEXION', 'AUTHENTIFICATION', 'fonction.php', 
                'Utilisateur inexistant: ' . $identifiant, 
                null, null, 'CRITICAL', 'FAILED');
            
            $erreur = 'Mot de passe ou identifiant incorrect';
            header('Location: ../connexion.php?error=' . urlencode($erreur));
            exit();
        }
    } else {
        $erreur = 'Veuillez remplir tous les champs';
        header('Location: ../connexion.php?error=' . urlencode($erreur));
        exit();
    }
    header('Location: ../connexion.php?error=' . urlencode($erreur));
    exit();
}
}

if (!function_exists('changer_image')) {
function changer_image($file, $allowed_exts, $max_size, $upload_dir, $redirection) {
    $photo_name = $file['name'];
    $photo_tmp = $file['tmp_name'];
    $photo_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
    $photo_size = $file['size'];
    
    if (!in_array($photo_ext, $allowed_exts)) {
        $erreur = "Extension de fichier non autorisée.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }

    if ($photo_size > $max_size) {
        $erreur = "Le fichier dépasse la taille maximale autorisée de 2 Mo.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }

    $new_photo_name = uniqid() . '.' . $photo_ext;
    $destination = $upload_dir . $new_photo_name;
    if (move_uploaded_file($photo_tmp, $destination)) {
        return ['success' => true, 'file_path' => $destination];
    } else {
        $erreur = "Erreur lors du téléchargement du fichier.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
    }
}

if (!function_exists('insertion_element')) {
function insertion_element($tableName, $data, $redirection) {
    global $cnx;

    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $sql = "INSERT INTO $tableName ($columns) VALUES ($placeholders)";
    $stmt = $cnx->prepare($sql);

    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }

    $count = $stmt->execute();
    if ($count) {
        return true;
    } else {
        $erreur = "Erreur lors de l'insertion dans la table $tableName.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
    }
}

if (!function_exists('verifier_element')) {
function verifier_element($tableName, $columns, $values , $redirection) {
    global $cnx;

    if (count($columns) !== count($values)) {
        $erreur = "Le nombre de colonnes doit correspondre au nombre de valeurs.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }

    $conditions = [];
    foreach ($columns as $column) {
        $conditions[] = "$column = :$column";
    }
    $conditionString = implode(' AND ', $conditions);

    $sql = "SELECT * FROM $tableName WHERE $conditionString";
    $stmt = $cnx->prepare($sql);

    foreach ($columns as $index => $column) {
        $stmt->bindValue(":$column", $values[$index]);
    }

    $stmt->execute();

    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $count ;
}
}

if (!function_exists('verifier_element_tous')) {
function verifier_element_tous($tableName, $columns, $values, $redirection) {
    global $cnx;

    if (count($columns) !== count($values)) {
        $erreur = "Le nombre de colonnes doit correspondre au nombre de valeurs.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }

    if (empty($columns) && empty($values)) {
        // Aucun filtre, on retourne tout
        $sql = "SELECT * FROM $tableName";
        $stmt = $cnx->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $conditions = [];
    $params = [];
    foreach ($columns as $index => $column) {
        if ($column === 'DateIns') {
            $conditions[] = "DATE(DateIns) = :dateIns";
            $params[':dateIns'] = $values[$index];
        } else {
            $conditions[] = "$column = :$column";
            $params[":$column"] = $values[$index];
        }
    }
    $conditionString = implode(' AND ', $conditions);
    $sql = "SELECT * FROM $tableName WHERE $conditionString";
    $stmt = $cnx->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $count = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $count;
}
}

if (!function_exists('selection_element')) {
function selection_element($tableName) {
    global $cnx;
    $sql = "SELECT * FROM $tableName ORDER BY $tableName.DateIns DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $results;
}
}

if (!function_exists('modifier_element')) {
function modifier_element($tableName, $columns, $values, $conditionColumn, $conditionValue, $redirection) {
    global $cnx;

    if (count($columns) !== count($values)) {
        $erreur = "Le nombre de colonnes doit correspondre au nombre de valeurs.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }

    $updateFields = [];
    foreach ($columns as $column) {
        $updateFields[] = "$column = :$column";
    }
    $updateString = implode(', ', $updateFields);

    $sql = "UPDATE $tableName SET $updateString WHERE $conditionColumn = :conditionValue";
    $stmt = $cnx->prepare($sql);
    foreach ($columns as $index => $column) {
        $stmt->bindValue(":$column", $values[$index]);
    }
    $stmt->bindValue(':conditionValue', $conditionValue);
    $count = $stmt->execute();
    if ($count) {
        return $count;
    } else {
        $erreur = "Erreur lors de la mise à jour.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
    }
}

if (!function_exists('supprimer_element')) {
function supprimer_element($tableName, $idColumn, $idValue , $redirection) {

    global $cnx;
    $sql = "DELETE FROM $tableName WHERE $idColumn = :idValue";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(':idValue', $idValue, PDO::PARAM_INT);
    $count = $stmt->execute();
    if ($count) {
        $success = "L'élément a été supprimé avec succès.";
        header('Location: ' . $redirection . '?success=' . urlencode($success));
        exit();
    } else {
        $erreur = "Erreur lors de la suppression de l'élément.";
        header('Location: ' . $redirection . '?error=' . urlencode($erreur));
        exit();
    }
    }
}
// ==============================
// 📁 Définition centrale des actions et modules
// ==============================
$allActions = [
    'voir',        // Afficher/lister
    'ajouter',     // Ajouter/créer
    'modifier',    // Modifier/éditer
    'supprimer',   // Supprimer/effacer
    'imprimer',    // Imprimer
    'exporter',    // Exporter
    'valider',     // Valider une opération
    'corriger',    // Corriger (stock, etc.)
    'traiter',     // Traiter (paiement, commande...)
    'générer',     // Générer (facture, étiquette...)
    'envoyer',     // Envoyer (email, SMS...)
    'droits',      // Gestion des droits
    'administrer'  // Administration avancée
];

$allModules = [
    'clients'      => 'Clients',
    'produits'     => 'Produits',
    'vente'       => 'vente',
    'fournisseurs' => 'Fournisseurs',
    'stock'        => 'Stock',
    'tresorerie'   => 'Trésorerie',
    'facturation'  => 'Facturation',
    'caisse'       => 'caisse',
    'utilisateurs' => 'Utilisateurs',
    'SAV'          => 'SAV',
    'rapports'     => 'Rapports',
    'commandes'    => 'Commandes',
    'inventaire'   => 'Inventaire',
    'parametres'   => 'Paramètres',
    'communication'=> 'Communication',
    'autres'       => 'Autres'
];

// ==============================
// 📁 Mapping page → module/action (centralisé)
// ==============================
$DROITS_PAGES = [
    // ==============================
    // 📁 Articles
    // ==============================
    'articles.php'          => [ 'module' => 'produits', 'action' => 'voir' ],
    'creation_d_article' => [ 'module' => 'produits', 'action' => 'voir' ],
    'liste_article'  => [ 'module' => 'produits', 'action' => 'voir' ],
    'generateur_d_etiquette' => [ 'module' => 'produits', 'action' => 'voir' ],
    'journal_systeme'                => [ 'module' => 'produits', 'action' => 'voir' ],
    'categorie_article'      => [ 'module' => 'produits', 'action' => 'voir' ],
    'liste_numeroserie'      => [ 'module' => 'produits', 'action' => 'voir' ],
    // ==============================
    // 📁 Vente
    // ==============================
    'caisse'             => [ 'module' => 'caisse', 'action' => 'voir' ],
    'listes_vente'       => [ 'module' => 'vente', 'action' => 'voir' ],
    'vente_jour'         => [ 'module' => 'vente', 'action' => 'voir' ],
    'vente_credit'           => [ 'module' => 'vente', 'action' => 'voir' ],
    'suivi_vente_credit' => [ 'module' => 'vente', 'action' => 'voir' ],
// Ajout mapping multi-actions pour versement.php
    'versement'               => [ 'module' => 'tresorerie', 'action' => 'voir' ],
    // ==============================
    // 📁 Commande
    // ==============================
    'bon_commande'             => [ 'module' => 'commande', 'action' => 'voir' ],
    'bon_commande-valider'     => [ 'module' => 'commande', 'action' => 'valider' ],
    'liste_commande'           => [ 'module' => 'commande', 'action' => 'voir' ],
   
    // ==============================
    // 📁 Proforma
    // ==============================
    'facture_proforma'         => [ 'module' => 'proforma', 'action' => 'voir' ],
    'facture_proforma-valider' => [ 'module' => 'proforma', 'action' => 'valider' ],
    'liste_proforma'           => [ 'module' => 'proforma', 'action' => 'voir' ],
    
    // ==============================
// 📁 Paramètres
// ==============================
'parametre'                        => [ 'module' => 'parametres', 'action' => 'voir' ],
'repertoire_client'                => [ 'module' => 'parametres', 'action' => 'voir' ],
'creation_messages_personnalises.php' => ['module' => 'parametres', 'action' => 'voir' ],
    'parametre_email'                  => [ 'module' => 'parametres', 'action' => 'voir' ],
    'parametre_sms'                    => [ 'module' => 'communication', 'action' => 'voir' ],
'parametre_message.php'            => [ 'module' => 'parametres', 'action' => 'modifier' ],
    // ==============================
// 📁 Inventaire
// ==============================
'inventaire_liste'          => [ 'module' => 'inventaire', 'action' => 'voir' ],

    // ==============================
    // 📁 Stock
    // ==============================
    'menu_entree_stock.php'     => [ 'module' => 'stock', 'action' => 'ajouter' ],
    'entre_stock'               => [ 'module' => 'stock', 'action' => 'voir' ],
    'liste_entree_stock'                    => [ 'module' => 'stock', 'action' => 'voir' ],
    'correction_stock'             => [ 'module' => 'stock', 'action' => 'voir' ],
    'liste_correction_stock' => [ 'module' => 'stock', 'action' => 'voir' ],
    'etat_stock' => [ 'module' => 'stock', 'action' => 'voir' ],
    'liste_stock'               => [ 'module' => 'stock', 'action' => 'voir' ],
    'motif_correction_stock.php'=> [ 'module' => 'stock', 'action' => 'corriger' ],
    'entrer_numero.php'         => [ 'module' => 'stock', 'action' => 'ajouter' ],
    // ==============================
    // 📁 Chiffre d'Affaire / Rapports
    // ==============================
    'rapports.php'                => [ 'module' => 'rapports', 'action' => 'voir' ],
    'chiffre_daffaire_horaire.php'=> [ 'module' => 'rapports', 'action' => 'voir' ],
    'chiffre_daffaire_mensuel.php'=> [ 'module' => 'rapports', 'action' => 'voir' ],
    'chiffre_daffaire_annuel.php' => [ 'module' => 'rapports', 'action' => 'voir' ],
    'ca_annuel.php'               => [ 'module' => 'rapports', 'action' => 'voir' ],
    'comptabilite'                => [ 'module' => 'rapports', 'action' => 'voir' ],
    'menu_chiffre_daffaire'       => [ 'module' => 'rapports', 'action' => 'voir' ],
    // ==============================
    // 📁 Utilisateurs / Administration
    // ==============================
    'utilisateur'                      => [ 'module' => 'utilisateurs', 'action' => 'voir' ],
    'creer_compte_utilisateur.php'     => [ 'module' => 'utilisateurs', 'action' => 'ajouter' ],
    'liste_utilisateurs.php'           => [ 'module' => 'utilisateurs', 'action' => 'voir' ],
    'modifier_parametre_utilisateur.php'=> [ 'module' => 'utilisateurs', 'action' => 'modifier' ],
    'droit_acces.php'                  => [ 'module' => 'utilisateurs', 'action' => 'droits' ],
    'page_message.php'                 => [ 'module' => 'utilisateurs', 'action' => 'parametrer_message' ],
    // ==============================
    // 📁 Communication (NOUVEAU MAPPING UNIQUEMENT)
    // ==============================
    'envoyer_sms'             => [ 'module' => 'communication', 'action' => 'voir' ],
    'suivi_sms'               => [ 'module' => 'communication', 'action' => 'voir' ],
    'e_mail'                  => [ 'module' => 'communication', 'action' => 'voir' ],
    'envoyer_email'           => [ 'module' => 'communication', 'action' => 'voir' ],
    'suivi_email'             => [ 'module' => 'communication', 'action' => 'voir' ],
    // ==============================
    // 📁 SAV
    // ==============================
    'sav'                   => [ 'module' => 'SAV', 'action' => 'voir' ],
    'sav_suivi'             => [ 'module' => 'SAV', 'action' => 'voir' ],
    // ==============================
    // 📁 Trésorerie
    // ==============================
    'mode_reglement.php'    => [ 'module' => 'tresorerie', 'action' => 'voir' ],

    // ==============================
    // 📁 Pages Génériques ou de test
    // ==============================
    'index.php' => [ 'module' => 'autres', 'action' => 'voir' ],
    'test.php'  => [ 'module' => 'autres', 'action' => 'voir' ],
    // ==============================

];



// ==============================
// 📁 Fonction centrale de contrôle d'accès UNIFIÉE
// ==============================

// FONCTION UNIQUE : contrôle par page/action (méthode standardisée)
if (!function_exists('user_has_access')) {
function user_has_access($page, $action, $bypass_admin = true) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['id_utilisateur'])) return false;
    
    // L'administrateur a accès à tout par défaut
    if ($bypass_admin && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    
    include('db/connecting.php');
    $stmt = $cnx->prepare("SELECT autorise FROM droits_acces WHERE id_utilisateur = ? AND page = ? AND action = ? AND autorise = 1");
    $stmt->execute([$_SESSION['id_utilisateur'], $page, $action]);
    return $stmt->fetch() ? true : false;
}
}

// Alias pour compatibilité
if (!function_exists('user_has_access_page')) {
function user_has_access_page($page, $action) {
    return user_has_access($page, $action);
}
}

// Vérifie l'accès à la page courante
if (!function_exists('check_access')) {
function check_access() {
    global $DROITS_PAGES;
    // Désactiver l'affichage des erreurs en production
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);

    // Démarrer la session si besoin
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Détection de l'appareil et injection des styles responsive
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_mobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent);
    $is_tablet = preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $user_agent);
    
    // Favicon et meta tags pour toutes les pages
    echo <<<HTML
<link rel="icon" type="image/png" href="logo/sotech.png">
<link rel="shortcut icon" type="image/png" href="logo/sotech.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta charset="UTF-8">
<style>
/* ==============================
   📱 STYLES RESPONSIVE GLOBAUX
   ============================== */
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
}

/* Base responsive */
* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.6;
    margin: 0;
    padding: 0;
}

/* Conteneurs adaptatifs */
.container, .container-fluid {
    width: 100%;
    padding-right: 15px;
    padding-left: 15px;
    margin-right: auto;
    margin-left: auto;
}

@media (min-width: 576px) {
    .container { max-width: 540px; }
}

@media (min-width: 768px) {
    .container { max-width: 720px; }
}

@media (min-width: 992px) {
    .container { max-width: 960px; }
}

@media (min-width: 1200px) {
    .container { max-width: 1140px; }
}

/* Grilles responsive */
.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -15px;
    margin-left: -15px;
}

.col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6, 
.col-7, .col-8, .col-9, .col-10, .col-11, .col-12,
.col-auto, .col-sm, .col-sm-1, .col-sm-2, .col-sm-3, .col-sm-4, 
.col-sm-5, .col-sm-6, .col-sm-7, .col-sm-8, .col-sm-9, .col-sm-10, 
.col-sm-11, .col-sm-12, .col-sm-auto, .col-md, .col-md-1, .col-md-2, 
.col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, 
.col-md-9, .col-md-10, .col-md-11, .col-md-12, .col-md-auto, .col-lg, 
.col-lg-1, .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6, 
.col-lg-7, .col-lg-8, .col-lg-9, .col-lg-10, .col-lg-11, .col-lg-12, 
.col-lg-auto, .col-xl, .col-xl-1, .col-xl-2, .col-xl-3, .col-xl-4, 
.col-xl-5, .col-xl-6, .col-xl-7, .col-xl-8, .col-xl-9, .col-xl-10, 
.col-xl-11, .col-xl-12, .col-xl-auto {
    position: relative;
    width: 100%;
    padding-right: 15px;
    padding-left: 15px;
}

/* Colonnes par défaut (mobile first) */
.col { flex: 1 0 0%; }
.col-1 { flex: 0 0 8.333333%; max-width: 8.333333%; }
.col-2 { flex: 0 0 16.666667%; max-width: 16.666667%; }
.col-3 { flex: 0 0 25%; max-width: 25%; }
.col-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
.col-5 { flex: 0 0 41.666667%; max-width: 41.666667%; }
.col-6 { flex: 0 0 50%; max-width: 50%; }
.col-7 { flex: 0 0 58.333333%; max-width: 58.333333%; }
.col-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
.col-9 { flex: 0 0 75%; max-width: 75%; }
.col-10 { flex: 0 0 83.333333%; max-width: 83.333333%; }
.col-11 { flex: 0 0 91.666667%; max-width: 91.666667%; }
.col-12 { flex: 0 0 100%; max-width: 100%; }

/* Tablettes (≥768px) */
@media (min-width: 768px) {
    .col-md { flex: 1 0 0%; }
    .col-md-1 { flex: 0 0 8.333333%; max-width: 8.333333%; }
    .col-md-2 { flex: 0 0 16.666667%; max-width: 16.666667%; }
    .col-md-3 { flex: 0 0 25%; max-width: 25%; }
    .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
    .col-md-5 { flex: 0 0 41.666667%; max-width: 41.666667%; }
    .col-md-6 { flex: 0 0 50%; max-width: 50%; }
    .col-md-7 { flex: 0 0 58.333333%; max-width: 58.333333%; }
    .col-md-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
    .col-md-9 { flex: 0 0 75%; max-width: 75%; }
    .col-md-10 { flex: 0 0 83.333333%; max-width: 83.333333%; }
    .col-md-11 { flex: 0 0 91.666667%; max-width: 91.666667%; }
    .col-md-12 { flex: 0 0 100%; max-width: 100%; }
}

/* Desktop (≥992px) */
@media (min-width: 992px) {
    .col-lg { flex: 1 0 0%; }
    .col-lg-1 { flex: 0 0 8.333333%; max-width: 8.333333%; }
    .col-lg-2 { flex: 0 0 16.666667%; max-width: 16.666667%; }
    .col-lg-3 { flex: 0 0 25%; max-width: 25%; }
    .col-lg-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
    .col-lg-5 { flex: 0 0 41.666667%; max-width: 41.666667%; }
    .col-lg-6 { flex: 0 0 50%; max-width: 50%; }
    .col-lg-7 { flex: 0 0 58.333333%; max-width: 58.333333%; }
    .col-lg-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
    .col-lg-9 { flex: 0 0 75%; max-width: 75%; }
    .col-lg-10 { flex: 0 0 83.333333%; max-width: 83.333333%; }
    .col-lg-11 { flex: 0 0 91.666667%; max-width: 91.666667%; }
    .col-lg-12 { flex: 0 0 100%; max-width: 100%; }
}

/* Large Desktop (≥1200px) */
@media (min-width: 1200px) {
    .col-xl { flex: 1 0 0%; }
    .col-xl-1 { flex: 0 0 8.333333%; max-width: 8.333333%; }
    .col-xl-2 { flex: 0 0 16.666667%; max-width: 16.666667%; }
    .col-xl-3 { flex: 0 0 25%; max-width: 25%; }
    .col-xl-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
    .col-xl-5 { flex: 0 0 41.666667%; max-width: 41.666667%; }
    .col-xl-6 { flex: 0 0 50%; max-width: 50%; }
    .col-xl-7 { flex: 0 0 58.333333%; max-width: 58.333333%; }
    .col-xl-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
    .col-xl-9 { flex: 0 0 75%; max-width: 75%; }
    .col-xl-10 { flex: 0 0 83.333333%; max-width: 83.333333%; }
    .col-xl-11 { flex: 0 0 91.666667%; max-width: 91.666667%; }
    .col-xl-12 { flex: 0 0 100%; max-width: 100%; }
}

/* ==============================
   📱 OPTIMISATIONS MOBILE
   ============================== */
@media (max-width: 767px) {
    /* Conteneurs mobiles */
    .container, .container-fluid {
        padding-right: 10px;
        padding-left: 10px;
    }
    
    /* Tous les éléments en pleine largeur sur mobile */
    .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6, 
    .col-7, .col-8, .col-9, .col-10, .col-11, .col-12 {
        flex: 0 0 100%;
        max-width: 100%;
        margin-bottom: 10px;
    }
    
    /* Tables responsive */
    .table-responsive {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Boutons mobiles */
    .btn {
        width: 100%;
        margin-bottom: 5px;
        padding: 12px;
        font-size: 16px; /* Évite le zoom sur iOS */
    }
    
    /* Formulaires mobiles */
    .form-control, .form-select {
        font-size: 16px; /* Évite le zoom sur iOS */
        padding: 12px;
        margin-bottom: 10px;
    }
    
    /* Cards mobiles */
    .card {
        margin-bottom: 15px;
        border-radius: 8px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    /* Navigation mobile */
    .navbar-nav {
        text-align: center;
    }
    
    .navbar-nav .nav-link {
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
    }
    
    /* Modales mobiles */
    .modal-dialog {
        margin: 10px;
        max-width: calc(100% - 20px);
    }
    
    /* Alerts mobiles */
    .alert {
        margin: 10px;
        padding: 15px;
        border-radius: 8px;
    }
}

/* ==============================
   📱 OPTIMISATIONS TABLETTE
   ============================== */
@media (min-width: 768px) and (max-width: 991px) {
    .container {
        max-width: 750px;
    }
    
    /* Grille 2 colonnes sur tablette */
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    /* Boutons tablette */
    .btn {
        padding: 10px 20px;
        margin-bottom: 8px;
    }
    
    /* Formulaires tablette */
    .form-control, .form-select {
        padding: 10px;
    }
}

/* ==============================
   💻 OPTIMISATIONS DESKTOP
   ============================== */
@media (min-width: 992px) {
    .container {
        max-width: 960px;
    }
    
    /* Grille 3 colonnes sur desktop */
    .col-lg-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
    
    /* Boutons desktop */
    .btn {
        padding: 8px 16px;
        margin-right: 5px;
        margin-bottom: 5px;
    }
    
    /* Hover effects sur desktop */
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
}

/* ==============================
   🖥️ OPTIMISATIONS LARGE DESKTOP
   ============================== */
@media (min-width: 1200px) {
    .container {
        max-width: 1140px;
    }
    
    /* Grille 4 colonnes sur large desktop */
    .col-xl-3 {
        flex: 0 0 25%;
        max-width: 25%;
    }
}

/* ==============================
   🎨 STYLES GLOBAUX AMÉLIORÉS
   ============================== */
.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: all 0.15s ease-in-out;
    text-decoration: none;
}

.btn-primary {
    color: #fff;
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-secondary {
    color: #fff;
    background-color: var(--secondary-color);
    border-color: var(--secondary-color);
}

.btn-success {
    color: #fff;
    background-color: var(--success-color);
    border-color: var(--success-color);
}

.btn-danger {
    color: #fff;
    background-color: var(--danger-color);
    border-color: var(--danger-color);
}

.btn-warning {
    color: #212529;
    background-color: var(--warning-color);
    border-color: var(--warning-color);
}

.btn-info {
    color: #fff;
    background-color: var(--info-color);
    border-color: var(--info-color);
}

.btn-light {
    color: #212529;
    background-color: var(--light-color);
    border-color: var(--light-color);
}

.btn-dark {
    color: #fff;
    background-color: var(--dark-color);
    border-color: var(--dark-color);
}

/* Cards */
.card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    word-wrap: break-word;
    background-color: #fff;
    background-clip: border-box;
    border: 1px solid rgba(0,0,0,0.125);
    border-radius: 0.25rem;
    transition: all 0.3s ease;
}

.card-body {
    flex: 1 1 auto;
    padding: 1.25rem;
}

.card-header {
    padding: 0.75rem 1.25rem;
    margin-bottom: 0;
    background-color: rgba(0,0,0,0.03);
    border-bottom: 1px solid rgba(0,0,0,0.125);
}

.card-footer {
    padding: 0.75rem 1.25rem;
    background-color: rgba(0,0,0,0.03);
    border-top: 1px solid rgba(0,0,0,0.125);
}

/* Formulaires */
.form-control, .form-select {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus, .form-select:focus {
    color: #495057;
    background-color: #fff;
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}

/* Alerts */
.alert {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

.alert-primary {
    color: #004085;
    background-color: #cce7ff;
    border-color: #b3d7ff;
}

.alert-secondary {
    color: #383d41;
    background-color: #e2e3e5;
    border-color: #d6d8db;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-warning {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffeaa7;
}

.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

/* Tables */
.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 0.75rem;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
}

/* Utilitaires */
.d-none { display: none !important; }
.d-block { display: block !important; }
.d-flex { display: flex !important; }
.d-inline { display: inline !important; }
.d-inline-block { display: inline-block !important; }

.text-center { text-align: center !important; }
.text-left { text-align: left !important; }
.text-right { text-align: right !important; }

.mb-0 { margin-bottom: 0 !important; }
.mb-1 { margin-bottom: 0.25rem !important; }
.mb-2 { margin-bottom: 0.5rem !important; }
.mb-3 { margin-bottom: 1rem !important; }
.mb-4 { margin-bottom: 1.5rem !important; }
.mb-5 { margin-bottom: 3rem !important; }

.mt-0 { margin-top: 0 !important; }
.mt-1 { margin-top: 0.25rem !important; }
.mt-2 { margin-top: 0.5rem !important; }
.mt-3 { margin-top: 1rem !important; }
.mt-4 { margin-top: 1.5rem !important; }
.mt-5 { margin-top: 3rem !important; }

.p-0 { padding: 0 !important; }
.p-1 { padding: 0.25rem !important; }
.p-2 { padding: 0.5rem !important; }
.p-3 { padding: 1rem !important; }
.p-4 { padding: 1.5rem !important; }
.p-5 { padding: 3rem !important; }

/* ==============================
   📱 DÉTECTION D'APPAREIL
   ============================== */
HTML;
    
    // Ajouter des classes CSS spécifiques selon l'appareil
    if ($is_mobile) {
        echo '<body class="device-mobile">';
    } elseif ($is_tablet) {
        echo '<body class="device-tablet">';
    } else {
        echo '<body class="device-desktop">';
    }
    
    echo <<<HTML
<style>
/* Styles spécifiques mobile */
.device-mobile .container { padding: 5px; }
.device-mobile .btn { width: 100%; margin-bottom: 8px; }
.device-mobile .form-control { font-size: 16px; }
.device-mobile .card { margin-bottom: 10px; }
.device-mobile .table-responsive { font-size: 14px; }

/* Styles spécifiques tablette */
.device-tablet .container { padding: 10px; }
.device-tablet .btn { padding: 10px 20px; }
.device-tablet .card { margin-bottom: 15px; }

/* Styles spécifiques desktop */
.device-desktop .container { padding: 15px; }
.device-desktop .btn { padding: 8px 16px; }
.device-desktop .card { margin-bottom: 20px; }
</style>
HTML;

    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['id_utilisateur'])) {
        // Sécurité : redirection immédiate si non connecté
        echo '<script>window.location.href = "connexion.php?error=Veuillez vous connecter";</script>';
        exit();
    }

    $currentFile = basename($_SERVER['SCRIPT_NAME']);
    $fileKey = pathinfo($currentFile, PATHINFO_FILENAME); // Nom sans extension
    
    if (!isset($DROITS_PAGES[$fileKey])) return; // page non protégée
    
    $config = $DROITS_PAGES[$fileKey];
    
    // Utiliser la fonction unifiée de vérification des droits par page/action
    // bypass_admin = true pour que les admins aient accès à tout, false pour les utilisateurs normaux
    if (!user_has_access($fileKey, $config['action'], true)) {
        access_denied_page("Accès refusé : vous n'avez pas l'autorisation pour cette action.");
    }
}
}

// Vérifie si l'utilisateur courant a le droit d'effectuer une action sur une page
if (!function_exists('can_user')) {
function can_user($page, $action) {
    return user_has_access($page, $action);
}
}

// Alias pour compatibilité
if (!function_exists('can_user_page')) {
function can_user_page($page, $action) {
    return user_has_access($page, $action);
}
}

// Génère un bouton d'action automatiquement grisé ou actif selon les droits
if (!function_exists('bouton_action')) {
function bouton_action($label, $page, $action, $class = 'btn btn-primary', $extra = '') {
    $can = can_user($page, $action);
    $disabled = $can ? '' : 'disabled title="Accès refusé"';
    return '<button class="' . $class . '" ' . $extra . ' ' . $disabled . '>' . htmlspecialchars($label) . '</button>';
}
}

// Affiche un message d'erreur stylé pour les actions
if (!function_exists('show_action_error')) {
function show_action_error($message) {
    return '<div class="alert alert-danger" style="margin:10px 0;">'
        . '<i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($message) . '</div>';
}
}

// Génère tous les boutons d'action pour une page donnée, selon les droits de l'utilisateur.
if (!function_exists('bouton_action_page')) {
function bouton_action_page($page, $actions = [], $labels = [], $classes = [], $extras = []) {
    global $allActions;
    $result = [];
    
    $actions_to_generate = $actions && is_array($actions) && count($actions) > 0 ? $actions : $allActions;
    foreach ($actions_to_generate as $action) {
        if (!in_array($action, $allActions)) {
            $result[$action] = show_action_error("Action non supportée : $action");
            continue;
        }
        $label = isset($labels[$action]) ? $labels[$action] : ucfirst($action);
        $class = isset($classes[$action]) ? $classes[$action] : 'btn btn-outline-primary btn-sm';
        $extra = isset($extras[$action]) ? $extras[$action] : '';
        $result[$action] = bouton_action($label, $page, $action, $class, $extra);
    }
    return $result;
}
}

// Alias pour compatibilité
if (!function_exists('bouton_action_module')) {
function bouton_action_module($page, $actions = [], $labels = [], $classes = [], $extras = []) {
    return bouton_action_page($page, $actions, $labels, $classes, $extras);
}
}

// ==============================
// 📱 FONCTIONS RESPONSIVE UTILITAIRES
// ==============================

// Fonction pour générer des classes responsive automatiquement
if (!function_exists('responsive_col')) {
function responsive_col($mobile = 12, $tablet = 6, $desktop = 4, $large = 3) {
    return "col-{$mobile} col-md-{$tablet} col-lg-{$desktop} col-xl-{$large}";
}
}

// Fonction pour générer des boutons responsive
if (!function_exists('btn_responsive')) {
function btn_responsive($text, $class = 'btn-primary', $size = '') {
    $size_class = $size ? "btn-{$size}" : '';
    return "<button class='btn {$class} {$size_class} btn-responsive'>{$text}</button>";
}
}

// Fonction pour générer des cards responsive
if (!function_exists('card_responsive')) {
function card_responsive($title, $content, $class = '') {
    return "
    <div class='card card-responsive {$class}'>
        <div class='card-header'>
            <h5 class='card-title'>{$title}</h5>
        </div>
        <div class='card-body'>
            {$content}
        </div>
    </div>";
}
}

// Fonction pour générer des tables responsive
if (!function_exists('table_responsive')) {
function table_responsive($headers, $rows, $class = '') {
    $html = "<div class='table-responsive'><table class='table {$class}'>";
    
    // Headers
    if (!empty($headers)) {
        $html .= "<thead><tr>";
        foreach ($headers as $header) {
            $html .= "<th>{$header}</th>";
        }
        $html .= "</tr></thead>";
    }
    
    // Rows
    $html .= "<tbody>";
    foreach ($rows as $row) {
        $html .= "<tr>";
        foreach ($row as $cell) {
            $html .= "<td>{$cell}</td>";
        }
        $html .= "</tr>";
    }
    $html .= "</tbody></table></div>";
    
    return $html;
}
}

// Fonction pour générer des formulaires responsive
if (!function_exists('form_group_responsive')) {
function form_group_responsive($label, $input, $help = '') {
    $help_html = $help ? "<small class='form-text text-muted'>{$help}</small>" : '';
    return "
    <div class='form-group mb-3'>
        <label class='form-label'>{$label}</label>
        {$input}
        {$help_html}
    </div>";
}
}

// Fonction pour détecter le type d'appareil
if (!function_exists('get_device_type')) {
function get_device_type() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_mobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent);
    $is_tablet = preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $user_agent);
    
    if ($is_mobile) return 'mobile';
    if ($is_tablet) return 'tablet';
    return 'desktop';
}
}

// Fonction pour générer des grilles responsive automatiques
if (!function_exists('grid_responsive')) {
function grid_responsive($items, $mobile_cols = 1, $tablet_cols = 2, $desktop_cols = 3, $large_cols = 4) {
    $col_class = responsive_col(12/$mobile_cols, 12/$tablet_cols, 12/$desktop_cols, 12/$large_cols);
    $html = "<div class='row'>";
    
    foreach ($items as $item) {
        $html .= "<div class='{$col_class} mb-3'>{$item}</div>";
    }
    
    $html .= "</div>";
    return $html;
}
}

// Fonction pour générer des modales responsive
if (!function_exists('modal_responsive')) {
function modal_responsive($id, $title, $content, $footer = '') {
    return "
    <div class='modal fade' id='{$id}' tabindex='-1'>
        <div class='modal-dialog modal-dialog-scrollable'>
            <div class='modal-content'>
                <div class='modal-header'>
                    <h5 class='modal-title'>{$title}</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body'>
                    {$content}
                </div>
                <div class='modal-footer'>
                    {$footer}
                </div>
            </div>
        </div>
    </div>";
}
}

// Fonction pour générer des alertes responsive
if (!function_exists('alert_responsive')) {
function alert_responsive($message, $type = 'info', $dismissible = true) {
    $dismiss_btn = $dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';
    return "
    <div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
        {$message}
        {$dismiss_btn}
    </div>";
}
}
// (Optionnel) Mapping page → module si besoin ailleurs
if (!function_exists('getModuleFromPage')) {
function getModuleFromPage($page) {
    global $DROITS_PAGES;
    return isset($DROITS_PAGES[$page]) ? $DROITS_PAGES[$page]['module'] : 'autres';
}
}

// Fonction pour afficher la page d'accès refusé
if (!function_exists('access_denied_page')) {
function access_denied_page($message = "Accès refusé") {
    echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Refusé - SOTECH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #dc3545 0%, #c82333 50%, #a71e2a 100%);
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        .danger-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 2px, transparent 2px),
                radial-gradient(circle at 75% 75%, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 50px 50px, 30px 30px;
            z-index: 1;
        }
        
        .access-denied-container {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .access-denied-card { 
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 
                0 20px 60px rgba(0,0,0,0.4),
                0 0 0 1px rgba(255,255,255,0.2),
                inset 0 1px 0 rgba(255,255,255,0.3);
            padding: 3rem 2.5rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(220, 53, 69, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .access-denied-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #dc3545, #ff6b6b, #dc3545);
            background-size: 200% 100%;
            animation: shimmer 2s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .danger-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .danger-title {
            color: #dc3545;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
        }
        
        .danger-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            font-weight: 500;
        }
        
        .danger-message {
            background: linear-gradient(135deg, #fff5f5, #ffe6e6);
            border: 1px solid #f8d7da;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #721c24;
            font-weight: 500;
            box-shadow: inset 0 2px 4px rgba(220, 53, 69, 0.1);
        }
        
        .btn-danger-custom {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-danger-custom:hover {
            background: linear-gradient(135deg, #c82333, #a71e2a);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-danger-custom:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(220, 53, 69, 0.3);
        }
        
        .security-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .floating-elements::before,
        .floating-elements::after {
            content: "⚠";
            position: absolute;
            color: rgba(255, 255, 255, 0.1);
            font-size: 2rem;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-elements::before {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-elements::after {
            bottom: 20%;
            right: 10%;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        @media (max-width: 768px) {
            .access-denied-card {
                margin: 20px;
                padding: 2rem 1.5rem;
            }
            
            .danger-icon {
                font-size: 4rem;
            }
            
            .danger-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="danger-pattern"></div>
    <div class="access-denied-container">
        <div class="access-denied-card">
            <div class="floating-elements"></div>
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i> Sécurité
                </div>
            
            <i class="fas fa-exclamation-triangle danger-icon"></i>
            <h1 class="danger-title">Accès Refusé</h1>
            <p class="danger-subtitle">Vous n\'avez pas les autorisations nécessaires pour accéder à cette ressource.</p>
            
            <div class="danger-message">
                <i class="fas fa-info-circle me-2"></i>
                ' . htmlspecialchars($message) . '
            </div>
            
            <a href="index.php" class="btn-danger-custom">
                <i class="fas fa-home"></i>
                Retour à l\'accueil
            </a>
        </div>
    </div>
</body>
</html>';
    exit();
}
}
/*
// Fonction d'envoi de SMS via Vonage pour le système de notifications
if (!function_exists('envoyer_sms')) {
function envoyer_sms($numero, $message) {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../db/connecting.php';
    global $cnx;
    // Récupération config Vonage
    $sql = "SELECT api_key, api_secret, sender_id FROM parametre_sms WHERE provider = 'vonage' AND active = 1 ORDER BY id DESC LIMIT 1";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$config) return false;
    try {
        $basic  = new \Vonage\Client\Credentials\Basic($config['api_key'], $config['api_secret']);
        $client = new \Vonage\Client($basic);
        $response = $client->sms()->send(
            new \Vonage\SMS\Message\SMS($numero, $config['sender_id'] ?: 'Vonage', $message)
        );
        $messageObj = $response->current();
        return $messageObj->getStatus() == 0;
    } catch (Exception $e) {
        return false;
    }
}
}*/

// ==============================
// 📁 Fin gestion centralisée des droits
// ==============================

// ==============================
// 📁 Journalisation unifiée (journal_systeme)
// ==============================

if (!function_exists('logSystemAction')) {
function logSystemAction(
    $cnx,
    $action,
    $module,
    $page,
    $description = '',
    $donneesAvant = null,
    $donneesApres = null,
    $niveauSecurite = 'MEDIUM',
    $statutAction = 'SUCCESS',
    $tempsExecutionSec = null
) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $idUtilisateur = isset($_SESSION['id_utilisateur']) ? intval($_SESSION['id_utilisateur']) : 0;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionId = session_id();

        // Encodage JSON sûr
        $jsonAvant = null;
        $jsonApres = null;
        if ($donneesAvant !== null) {
            $jsonAvant = json_encode($donneesAvant, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        if ($donneesApres !== null) {
            $jsonApres = json_encode($donneesApres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        // Normalisation de base
        $module = strtoupper(trim($module));
        $action = strtoupper(trim($action));
        $page = basename($page);
        $niveauSecurite = strtoupper($niveauSecurite);
        $statutAction = strtoupper($statutAction);

        // Insertion
        $sql = "INSERT INTO journal_systeme (
                    id_utilisateur, action, module, page, description,
                    donnees_avant, donnees_apres, ip_address, user_agent,
                    niveau_securite, statut_action, erreur_message, session_id, temps_execution
                ) VALUES (
                    :id_utilisateur, :action, :module, :page, :description,
                    :donnees_avant, :donnees_apres, :ip_address, :user_agent,
                    :niveau_securite, :statut_action, :erreur_message, :session_id, :temps_execution
                )";

        $stmt = $cnx->prepare($sql);
        $stmt->execute([
            ':id_utilisateur' => $idUtilisateur,
            ':action' => $action,
            ':module' => $module,
            ':page' => $page,
            ':description' => $description,
            ':donnees_avant' => $jsonAvant,
            ':donnees_apres' => $jsonApres,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':niveau_securite' => $niveauSecurite,
            ':statut_action' => $statutAction,
            ':erreur_message' => ($statutAction === 'FAILED' ? ($description ?: null) : null),
            ':session_id' => $sessionId,
            ':temps_execution' => $tempsExecutionSec,
        ]);

        return true;
    } catch (Throwable $e) {
        // Ne jamais casser le flux applicatif à cause du log
        error_log('logSystemAction error: ' . $e->getMessage());
        return false;
    }
}
}

// Helpers pratiques pour mesurer la durée
if (!function_exists('log_action_start')) {
function log_action_start() {
    return microtime(true);
}
}

if (!function_exists('log_action_end')) {
function log_action_end($start) {
    if (!is_numeric($start)) return null;
    return round(microtime(true) - floatval($start), 4);
}
}

// FONCTION journaliserAction SUPPRIMÉE - UTILISER logSystemAction UNIQUEMENT
// Cette fonction a été supprimée pour éviter les doublons de journalisation
// Utilisez directement logSystemAction() dans tout le code

// ==============================
// 📁 Fonction de vérification des droits pour les prix d'achat
// ==============================
if (!function_exists('user_can_see_purchase_prices')) {
function user_can_see_purchase_prices() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['id_utilisateur'])) return false;
    
    // Vérifier dans la table droits_acces
    global $cnx;
    if (!$cnx) return false;
    
    try {
        $stmt = $cnx->prepare("SELECT voir_prix_achat FROM droits_acces WHERE id_utilisateur = ?");
        $stmt->execute([$_SESSION['id_utilisateur']]);
        $droit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $droit && $droit['voir_prix_achat'] == 1;
    } catch (Exception $e) {
        // En cas d'erreur, retourner false par sécurité
        return false;
    }
}
}
