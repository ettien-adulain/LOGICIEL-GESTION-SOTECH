<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('fonction_traitement/fonction.php');
include('db/connecting.php');

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('location: index.php');
    exit();
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'save_rights':
                $id_utilisateur = $_POST['id_utilisateur'];
                $rights = json_decode($_POST['rights'], true);
                $voir_prix_achat = isset($_POST['voir_prix_achat']) ? (int)$_POST['voir_prix_achat'] : 0;

                // Commencer une transaction
                $cnx->beginTransaction();
                
                try {
                    // Supprimer tous les droits existants pour cet utilisateur
                    $stmt = $cnx->prepare("DELETE FROM droits_acces WHERE id_utilisateur = ?");
                    $stmt->execute([$id_utilisateur]);
                    
                    // Liste blanche des pages autorisées (regroupées par page principale)
                    $PAGES_AUTORISEES = [
                        // Articles
                        'creation_d_article', 'liste_article', 'categorie_article', 'generateur_d_etiquette', 'journal_systeme', 'liste_numeroserie',
                        // Ventes (regroupées)
                        'caisse', 'listes_vente', 'vente_credit', 'suivi_vente_credit', 'vente_jour',
                        // Stock
                        'entre_stock', 'liste_entree_stock', 'liste_stock', 'correction_stock', 'liste_correction_stock', 'etat_stock',
                        // Inventaire
                        'inventaire_liste',
                        // Commandes (regroupées)
                        'bon_commande', 'liste_commande', 'facture_proforma', 'liste_proforma',
                        // SAV
                        'sav', 'sav_suivi',
                        // Clients
                        'repertoire_client',
                        // Paramètres
                        'parametre',
                        // Utilisateurs
                        'utilisateur',
                        // Rapports
                        'menu_chiffre_daffaire', 'comptabilite',
                        // Communication
                        'envoyer_sms', 'suivi_sms', 'parametre_sms', 'envoyer_email', 'e_mail', 'parametre_email', 'suivi_email',
                        // Trésorerie (regroupées)
                        'versement', 'mode_reglement',
                    ];
                    
                    // Insérer les nouveaux droits PAR PAGE/ACTION (système unifié)
                    $stmt = $cnx->prepare("INSERT IGNORE INTO droits_acces(id_utilisateur, page, action, autorise, date_modif) VALUES (?, ?, ?, 1, NOW())");
                    $inserted = [];
                    
                    foreach ($rights as $page => $actions) {
                        if (empty($page) || !in_array($page, $PAGES_AUTORISEES)) continue;
                        
                        foreach ($actions as $action) {
                            $key = $page . '-' . $action;
                            if (isset($inserted[$key])) continue; // éviter les doublons
                            
                            // Sauvegarder avec la page et l'action
                                $stmt->execute([$id_utilisateur, $page, $action]);
                            $inserted[$key] = true;
                            }
                        }
                    // Sauvegarder le droit spécial "voir prix d'achat"
                    $stmt = $cnx->prepare("INSERT INTO droits_acces (id_utilisateur, voir_prix_achat, date_modif) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE voir_prix_achat = VALUES(voir_prix_achat), date_modif = NOW()");
                    $stmt->execute([$id_utilisateur, $voir_prix_achat]);
                    
                    // Valider la transaction
                    $cnx->commit();
                } catch (Exception $e) {
                    // Annuler la transaction en cas d'erreur
                    $cnx->rollBack();
                    error_log("Erreur SQL droits_acces: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
                    exit();
                }
                echo json_encode(['success' => true, 'message' => 'Droits sauvegardés avec succès']);
                break;
                
            case 'get_user_rights':
                $id_utilisateur = $_POST['id_utilisateur'];
                $stmt = $cnx->prepare("SELECT page, action FROM droits_acces WHERE id_utilisateur = ? AND autorise = 1");
                $stmt->execute([$id_utilisateur]);
                $rights = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $page = $row['page'];
                    $action = $row['action'];
                    if (!isset($rights[$page])) $rights[$page] = [];
                    if (!in_array($action, $rights[$page])) {
                        $rights[$page][] = $action;
                    }
                }
                
                // Récupérer le droit spécial "voir prix d'achat"
                $stmt = $cnx->prepare("SELECT voir_prix_achat FROM droits_acces WHERE id_utilisateur = ? LIMIT 1");
                $stmt->execute([$id_utilisateur]);
                $droit_special = $stmt->fetch(PDO::FETCH_ASSOC);
                $voir_prix_achat = $droit_special ? (int)$droit_special['voir_prix_achat'] : 0;
                
                echo json_encode(['success' => true, 'rights' => $rights, 'voir_prix_achat' => $voir_prix_achat]);
                break;
                
                
            default:
                echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

// Récupérer la liste des utilisateurs
$stmt = $cnx->prepare("SELECT IDUTILISATEUR, NomPrenom, Identifiant FROM utilisateur WHERE actif = 'oui' ORDER BY NomPrenom");
$stmt->execute();
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les pages PHP du projet (hors pages publiques et techniques)
function getAllPhpPages() {
    $ignore = ['connexion.php', 'index.php', 'droit_acces.php', 'db/connecting.php', 'fonction_traitement/fonction.php'];
    $files = glob('*.php');
    $pages = [];
    foreach ($files as $file) {
        if (!in_array($file, $ignore)) {
            $pages[] = $file;
        }
    }
    return $pages;
}
$allPhpPages = getAllPhpPages();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Droits d'Accès - Version Améliorée</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .module-section {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 6px;
            margin-bottom: 8px;
            border-left: 2px solid #007bff;
        }
        .category-section {
            margin-bottom: 5px;
        }
        .category-title {
            color: #333;
            font-weight: bold;
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid #dee2e6;
            font-size: 1rem;
            letter-spacing: 0.05px;
        }
        .page-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 8px 8px 6px 8px;
            height: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.1s ease;
            margin-bottom: 4px;
        }
        .page-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            padding-bottom: 3px;
            border-bottom: 1px solid #eee;
        }
        .page-title {
            margin: 0;
            font-weight: 600;
            color: #222;
            font-size: 0.85rem;
        }
        .page-actions {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 4px 6px;
            margin-top: 4px;
        }
        .action-item {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
        .action-checkbox {
            display: flex;
            align-items: center;
            width: auto;
            cursor: pointer;
            padding: 3px 4px 3px 3px;
            border-radius: 3px;
            transition: background-color 0.1s;
            background: #f4f7fa;
            border: 1px solid #e3e7ed;
            margin-right: 2px;
            position: relative;
            min-width: 30px;
        }
        .action-checkbox:hover {
            background-color: #eaf2fb;
            border-color: #b6d4fa;
        }
        .action-checkbox input[type="checkbox"] {
            appearance: none;
            width: 16px;
            height: 16px;
            border: 2px solid #007bff;
            border-radius: 3px;
            background: #fff;
            margin-right: 4px;
            transition: border-color 0.1s, box-shadow 0.1s;
            position: relative;
            outline: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .action-checkbox input[type="checkbox"]:checked {
            background: #007bff;
            border-color: #007bff;
        }
        .action-checkbox input[type="checkbox"]:checked:after {
            content: '';
            display: block;
            position: absolute;
            left: 4px;
            top: 1px;
            width: 3px;
            height: 6px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .action-label {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            color: #333;
            font-weight: 600;
            letter-spacing: 0.01px;
            position: relative;
        }
        .action-label i {
            margin-right: 1px;
            width: 6px;
        }
        .action-label[title] {
            cursor: help;
            border-bottom: 1px dotted #aaa;
        }
        .module-title {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 0.8rem;
        }
        .btn-toggle-all {
            margin-bottom: 4px;
        }
        .user-selector {
            background: #e9ecef;
            padding: 4px;
            border-radius: 3px;
            margin-bottom: 5px;
        }
        .rights-container {
            max-height: none;
            overflow-y: visible;
        }
        .save-section {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 3px;
            border-top: 1px solid #dee2e6;
            margin-top: 4px;
        }
        .alert {
            margin-bottom: 4px;
        }
        .container-fluid {
            padding: 2px;
        }
        .card {
            margin-bottom: 2px;
        }
        .card-header {
            padding: 3px 6px;
        }
        .card-body {
            padding: 4px;
        }
        .btn {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        .btn-lg {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        .form-select, .form-control {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        .form-label {
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        h5 {
            font-size: 1rem;
            margin-bottom: 4px;
        }
        @media (max-width: 991px) {
            .page-card { font-size: 0.8em; }
            .page-title { font-size: 0.65em; }
        }
        @media (max-width: 767px) {
            .page-card { font-size: 0.75em; }
            .page-title { font-size: 0.6em; }
            .category-section .row > div { flex: 0 0 100%; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-shield-alt"></i> Gestion des Droits d'Accès - Version Améliorée</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>

                <!-- Sélecteur d'utilisateur -->
                <div class="user-selector">
                    <div class="row align-items-end">
            <div class="col-md-6">
                            <label for="userSelect" class="form-label">
                                <i class="fas fa-user"></i> Sélectionner un utilisateur :
                            </label>
                            <select id="userSelect" class="form-select">
                                <option value="">-- Choisir un utilisateur --</option>
                                <?php foreach ($utilisateurs as $user): ?>
                                    <option value="<?= $user['IDUTILISATEUR'] ?>">
                                        <?= htmlspecialchars($user['NomPrenom']) ?> (<?= htmlspecialchars($user['Identifiant']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                        </div>
                        <div class="col-md-6">
                            <button id="loadRights" class="btn btn-primary" disabled>
                                <i class="fas fa-search"></i> Charger les droits
                            </button>
                            <button id="resetRights" class="btn btn-warning" disabled>
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Messages d'alerte -->
                <div id="alertContainer"></div>

                <!-- Interface des droits -->
                <div id="rightsInterface" style="display: none;">
                    
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-cogs"></i> Configuration Détaillée des Droits d'Accès
                                        <span id="currentUser" class="badge bg-light text-dark ms-2"></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="rights-container">
                                        <!-- Les modules seront générés ici -->
                                    </div>
                                    
                                    <!-- Section droits spéciaux -->
                                    <div class="special-rights-section mb-4">
                                        <h5 class="text-primary">
                                            <i class="fas fa-shield-alt"></i> Droits Spéciaux
                                        </h5>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="voir_prix_achat_global" name="voir_prix_achat_global">
                                                            <label class="form-check-label" for="voir_prix_achat_global">
                                                                <strong>Voir Prix d'Achat</strong>
                                                                <small class="text-muted d-block">
                                                                    Permet de voir les prix d'achat dans les modules : Entrée en stock, Correction de stock, Liste correction, Saisie inventaire
                                                                </small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle"></i>
                                                            <strong>Note :</strong> Par défaut, les prix d'achat sont masqués pour la sécurité. Seuls les utilisateurs autorisés peuvent les voir.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Section de sauvegarde -->
                                    <div class="save-section">
                                        <div class="row">
                                                            <div class="col-md-6">
                    <button id="selectAll" class="btn btn-success">
                        <i class="fas fa-check-double"></i> Tout sélectionner
                    </button>
                    <button id="deselectAll" class="btn btn-danger">
                        <i class="fas fa-times"></i> Tout désélectionner
                    </button>
                </div>
                                            <div class="col-md-6 text-end">
                                                <button id="saveRights" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-save"></i> Sauvegarder les droits
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modules = [
          {
            name: "Articles",
            icon: "box",
            color: "#2196f3",
            pages: [
              { key: "creation_d_article", label: "Création d'article", actions: ["voir", "ajouter", "enregistrer", "annuler"] },
              { key: "liste_article", label: "Liste des articles", actions: ["voir", "modifier", "supprimer", "ajouter_au_panier"] },
              { key: "categorie_article", label: "Catégories d'articles", actions: ["voir", "ajouter", "supprimer"] },
              { key: "generateur_d_etiquette", label: "Générateur d'étiquettes", actions: ["voir"] },
              { key: "journal_systeme", label: "Journal", actions: ["voir"] },
              { key: "liste_numeroserie", label: "Numéro de série", actions: ["voir"] }
            ]
          },
          {
            name: "Vente",
            icon: "shopping-cart",
            color: "#4caf50",
            pages: [
              { key: "caisse", label: "Caisse", actions: ["voir", "enregistrer", "annuler", "multi_paiement"] },
              { key: "listes_vente", label: "Liste des ventes", actions: ["voir", "exporter", "supprimer", "imprimer"] },
              { key: "vente_credit", label: "Vente à crédit", actions: ["voir", "enregistrer", "annuler", "multi_paiement", "imprimer"] },
              { key: "suivi_vente_credit", label: "Suivi vente crédit", actions: ["voir", "enregistrer", "multi_paiement", "supprimer", "exporter", "imprimer"] },
              { key: "vente_jour", label: "Vente du jour", actions: ["voir"] }
            ]
          },
          {
            name: "Stock",
            icon: "warehouse",
            color: "#9c27b0",
            pages: [
              { key: "entre_stock", label: "Entrée en stock", actions: ["voir", "enregistrer", "annuler"] },
              { key: "liste_entree_stock", label: "Liste entrées stock", actions: ["voir", "supprimer", "supprimer_tous"] },
              { key: "liste_stock", label: "Liste du stock", actions: ["voir", "supprimer"] },
              { key: "correction_stock", label: "Correction de stock", actions: ["voir", "valider"] },
              { key: "liste_correction_stock", label: "Liste corrections stock", actions: ["voir"] },
              { key: "etat_stock", label: "États de stock", actions: ["voir"] }
            ]
          },
          {
            name: "Inventaire",
            icon: "clipboard-list",
            color: "#ff9800",
            pages: [
              { key: "inventaire_liste", label: "Inventaire", actions: ["voir"] }
            ]
          },
          {
            name: "Commandes",
            icon: "file-alt",
            color: "#607d8b",
            pages: [
              { key: "bon_commande", label: "Bon de commande", actions: ["voir", "valider"] },
              { key: "liste_commande", label: "Liste des commandes", actions: ["voir", "exporter", "imprimer", "supprimer"] },
              { key: "facture_proforma", label: "Facture proforma", actions: ["voir", "valider"] },
              { key: "liste_proforma", label: "Liste des proformas", actions: ["voir", "imprimer", "envoyer", "transformer", "supprimer"] }
            ]
          },
          {
            name: "SAV",
            icon: "tools",
            color: "#e67e22",
            pages: [
              { key: "sav", label: "Dépôt SAV", actions: ["voir", "supprimer"] },
              { key: "sav_suivi", label: "Suivi SAV", actions: ["voir", "administrer"] }
            ]
          },
          {
            name: "Clients",
            icon: "user-friends",
            color: "#00bcd4",
            pages: [
              { key: "repertoire_client", label: "Répertoire client", actions: ["voir", "ajouter", "modifier", "supprimer", "exporter"] }
            ]
          },
          {
            name: "Paramètres",
            icon: "cogs",
            color: "#607d8b",
            pages: [
              { key: "parametre", label: "Paramètres", actions: ["voir"] }
            ]
          },
          {
            name: "Utilisateurs",
            icon: "users-cog",
            color: "#607d8b",
            pages: [
              { key: "utilisateur", label: "Gestion des utilisateurs", actions: ["voir"] }
            ]
          },
          {
            name: "Rapports",
            icon: "chart-bar",
            color: "#607d8b",
            pages: [
              { key: "menu_chiffre_daffaire", label: "Menu Chiffre d'Affaire", actions: ["voir"] }
            ]
          },
          {
            name: "Comptabilité",
            icon: "calculator",
            color: "#795548",
            pages: [
              { key: "comptabilite", label: "Comptabilité générale", actions: ["voir"] }
            ]
          },
          {
            name: "Communication",
            icon: "envelope",
            color: "#00bcd4",
            pages: [
              { key: "envoyer_sms", label: "Envoi SMS", actions: ["voir", "envoyer"] },
              { key: "suivi_sms", label: "Suivi SMS", actions: ["voir"] },
              { key: "parametre_sms", label: "Parametre SMS", actions: ["voir", "enregistrer"] },
              { key: "envoyer_email", label: "Envoyer Email", actions: ["voir", "envoyer", "historique"] },
              { key: "parametre_email", label: "Parametre Email", actions: ["voir", "modifier", "enregistrer"] },
              { key: "suivi_email", label: "Suivi Email", actions: ["voir", "historique"] }
            ]
          },
          {
            name: "Trésorerie",
            icon: "money-bill-wave",
            color: "#795548",
            pages: [
              { key: "versement", label: "Versement", actions: ["voir", "ajouter", "supprimer", "modifier", "imprimer", "exporter"] },
            ]
          }
        ];

        // Génère un mapping page -> module automatiquement
        const pageToModule = {};
        modules.forEach(module => {
          module.pages.forEach(page => {
            pageToModule[page.key] = module.name;
          });
        });

        // Génération professionnelle de l'interface droits
        function generateRightsInterface() {
          const container = document.querySelector('.rights-container');
          let html = '';
          modules.forEach(module => {
            // Génération standard pour tous les modules (système unifié)
            html += `<div class="category-section mb-1">
              <h4 class="category-title" style="color:${module.color}"><i class="fas fa-${module.icon}"></i> ${module.name}</h4>
              <div class="row">`;
            module.pages.forEach(page => {
              html += `<div class="col-xxl-2 col-xl-3 col-lg-4 col-md-6 mb-1">
                <div class="page-card" style="border-left: 2px solid ${module.color};">
                  <div class="page-header">
                    <h6 class="page-title"><i class="fas fa-${module.icon}" style="color:${module.color}"></i> ${page.label}</h6>
                  </div>
                  <div class="page-actions">`;
              page.actions.forEach(action => {
                // Ajout d'un tooltip explicatif sur chaque droit
                let tooltip = '';
                switch(action) {
                  case 'voir': tooltip = 'Permet de voir la page ou le module'; break;
                  case 'ajouter': tooltip = 'Permet d\'ajouter de nouveaux éléments'; break;
                  case 'modifier': tooltip = 'Permet de modifier les éléments existants'; break;
                  case 'supprimer': tooltip = 'Permet de supprimer des éléments'; break;
                  case 'imprimer': tooltip = 'Permet d\'imprimer les données'; break;
                  case 'exporter': tooltip = 'Permet d\'exporter les données'; break;
                  case 'enregistrer': tooltip = 'Permet d\'enregistrer les modifications'; break;
                  case 'annuler': tooltip = 'Permet d\'annuler une opération'; break;
                  case 'administrer': tooltip = 'Permet d\'accéder à l\'administration avancée'; break;
                  case 'multi_paiement': tooltip = 'Permet d\'utiliser le multi-paiement'; break;
                  case 'valider': tooltip = 'Permet de valider une opération'; break;
                  case 'envoyer': tooltip = 'Permet d\'envoyer des documents'; break;
                  case 'transformer': tooltip = 'Permet de transformer un document'; break;
                  case 'historique': tooltip = 'Permet d\'accéder à l\'historique'; break;
                  case 'ajouter_au_panier': tooltip = 'Permet d\'ajouter des articles au panier'; break;
                  case 'supprimer_tous': tooltip = 'Permet de supprimer tous les éléments'; break;
                  default: tooltip = action.charAt(0).toUpperCase() + action.slice(1);
                }
                html += `<div class="action-item">
                  <label class="action-checkbox" title="${tooltip}">
                    <input type="checkbox" data-page="${page.key}" data-action="${action}" class="right-checkbox">
                    <span class="action-label" title="${tooltip}">${action.charAt(0).toUpperCase() + action.slice(1)}</span>
                  </label>
                </div>`;
              });
              html += `</div></div></div>`;
            });
            html += `</div></div>`;
          });
          container.innerHTML = html;
        }
        
        let currentUserId = null;
        let currentRights = {};

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            generateRightsInterface();
        });

        function initializeEventListeners() {
            // Sélecteur d'utilisateur
            document.getElementById('userSelect').addEventListener('change', function() {
                const userId = this.value;
                const loadBtn = document.getElementById('loadRights');
                const resetBtn = document.getElementById('resetRights');
                
                // Réinitialise toutes les cases à cocher
                document.querySelectorAll('.right-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                hideRightsInterface();
                document.getElementById('saveRights').disabled = true;
                
                if (userId) {
                    loadBtn.disabled = false;
                    resetBtn.disabled = false;
                } else {
                    loadBtn.disabled = true;
                    resetBtn.disabled = true;
                }
            });

            // Charger les droits
            document.getElementById('loadRights').addEventListener('click', loadUserRights);
            
            // Réinitialiser
            document.getElementById('resetRights').addEventListener('click', resetRights);
            
            // Sauvegarder
            document.getElementById('saveRights').addEventListener('click', saveRights);
            
            // Tout sélectionner/désélectionner
            document.getElementById('selectAll').addEventListener('click', selectAllRights);
            document.getElementById('deselectAll').addEventListener('click', deselectAllRights);
        }

        async function loadUserRights() {
            const userId = document.getElementById('userSelect').value;
            if (!userId) return;

            try {
                const response = await fetch('droit_acces.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_user_rights&id_utilisateur=${userId}`
                });

                const data = await response.json();
                
                // Réinitialise toutes les cases à cocher
                document.querySelectorAll('.right-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                if (data.success) {
                    currentUserId = userId;
                    currentRights = data.rights;
                    
                    // Afficher l'interface
                    showRightsInterface();
                    
                    // Mettre à jour les checkboxes
                    updateCheckboxes();
                    
                    // Mettre à jour le droit spécial "voir prix d'achat"
                    if (data.voir_prix_achat !== undefined) {
                        document.getElementById('voir_prix_achat_global').checked = data.voir_prix_achat == 1;
                    }
                    
                    // Afficher le nom de l'utilisateur
                    const userSelect = document.getElementById('userSelect');
                    const selectedOption = userSelect.options[userSelect.selectedIndex];
                    document.getElementById('currentUser').textContent = selectedOption.text;
                    
                    showAlert('Droits chargés avec succès', 'success');
                    document.getElementById('saveRights').disabled = false;
                } else {
                    showAlert('Erreur lors du chargement des droits', 'danger');
                    document.getElementById('saveRights').disabled = true;
                }
            } catch (error) {
                showAlert('Erreur de connexion', 'danger');
                document.getElementById('saveRights').disabled = true;
                console.error('Erreur:', error);
            }
        }

        function updateCheckboxes() {
            // Décocher toutes les checkboxes
            document.querySelectorAll('.right-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            // Cocher les droits existants
            for (const [page, pageActions] of Object.entries(currentRights)) {
                pageActions.forEach(action => {
                    const checkbox = document.querySelector(`input[data-page=\"${page}\"][data-action=\"${action}\"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
        }

        function togglePage(page, enable) {
            document.querySelectorAll(`input[data-page="${page}"]`).forEach(checkbox => {
                checkbox.checked = enable;
            });
        }

        function selectAllRights() {
            document.querySelectorAll('.right-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function deselectAllRights() {
            document.querySelectorAll('.right-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        }


        function resetRights() {
            if (confirm('Voulez-vous vraiment réinitialiser tous les droits de cet utilisateur ?')) {
                currentRights = {};
                updateCheckboxes();
                showAlert('Droits réinitialisés', 'warning');
            }
        }

        async function saveRights() {
            console.log('Bouton Sauvegarder cliqué');
            if (!currentUserId) {
                showAlert('Veuillez d\'abord sélectionner un utilisateur', 'warning');
                return;
            }
            // Collecter tous les droits cochés
            const rights = {};
            document.querySelectorAll('.right-checkbox:checked').forEach(checkbox => {
                const page = checkbox.dataset.page;
                const action = checkbox.dataset.action;
                if (!rights[page]) rights[page] = [];
                rights[page].push(action);
            });
            try {
                const response = await fetch('droit_acces.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=save_rights&id_utilisateur=${currentUserId}&rights=${encodeURIComponent(JSON.stringify(rights))}&voir_prix_achat=${document.getElementById('voir_prix_achat_global').checked ? 1 : 0}`
                });
                const data = await response.json();
                if (data.success) {
                    showAlert('Droits sauvegardés avec succès', 'success');
                    currentRights = rights;
                } else {
                    showAlert('Erreur lors de la sauvegarde: ' + data.message, 'danger');
                }
            } catch (error) {
                showAlert('Erreur de connexion lors de la sauvegarde', 'danger');
                console.error('Erreur:', error);
            }
        }

        function showRightsInterface() {
            document.getElementById('rightsInterface').style.display = 'block';
        }

        function hideRightsInterface() {
            document.getElementById('rightsInterface').style.display = 'none';
        }

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            container.appendChild(alert);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
    }
</script>
</body>
</html>