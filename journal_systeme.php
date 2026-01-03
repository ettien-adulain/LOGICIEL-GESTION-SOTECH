<?php
require_once 'fonction_traitement/fonction.php';
check_access();

try {
    include('db/connecting.php');

    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $module = $_POST['module'] ?? '';
    $action = $_POST['action'] ?? '';
    $utilisateur = $_POST['utilisateur'] ?? '';

    // Construction de la requête principale
    $sql = "SELECT 
                js.id,
                js.id_utilisateur,
                js.action,
                js.module,
                js.page,
                js.description,
                js.donnees_avant,
                js.donnees_apres,
                js.ip_address,
                js.user_agent,
                js.date_action,
                js.niveau_securite,
                js.statut_action,
                js.erreur_message,
                js.session_id,
                js.temps_execution,
                u.NomPrenom as nom_utilisateur,
                u.Identifiant as identifiant_utilisateur
            FROM journal_systeme js
            LEFT JOIN utilisateur u ON js.id_utilisateur = u.IDUTILISATEUR
            WHERE 1=1";

    // Filtres
    if ($startDate) {
        $sql .= " AND DATE(js.date_action) >= :startDate";
    }
    if ($endDate) {
        $sql .= " AND DATE(js.date_action) <= :endDate";
    }
    if ($module) {
        $sql .= " AND js.module = :module";
    }
    if ($action) {
        $sql .= " AND js.action = :action";
    }
    if ($utilisateur) {
        $sql .= " AND js.id_utilisateur = :utilisateur";
    }

    $sql .= " ORDER BY js.date_action DESC";

    $stmt = $cnx->prepare($sql);

    if ($startDate) $stmt->bindParam(':startDate', $startDate);
    if ($endDate) $stmt->bindParam(':endDate', $endDate);
    if ($module) $stmt->bindParam(':module', $module);
    if ($action) $stmt->bindParam(':action', $action);
    if ($utilisateur) $stmt->bindParam(':utilisateur', $utilisateur);

    try {
        $stmt->execute();
        $journalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
    } catch (PDOException $e) {
        $journalData = [];
    }

    // Récupération des listes pour les filtres
    $modules = $cnx->query("SELECT DISTINCT module FROM journal_systeme ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
    $actions = $cnx->query("SELECT DISTINCT action FROM journal_systeme ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $utilisateurs = $cnx->query("
        SELECT DISTINCT u.IDUTILISATEUR, u.NomPrenom, u.Identifiant 
        FROM journal_systeme js 
        LEFT JOIN utilisateur u ON js.id_utilisateur = u.IDUTILISATEUR 
        ORDER BY u.NomPrenom
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $journalData = [];
    $modules = [];
    $actions = [];
    $utilisateurs = [];
}

// Variables pour les droits
$currentPage = 'journal_systeme.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Système Unifié</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .journal-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        .journal-sidebar {
            width: 250px;
            background: linear-gradient(180deg, #dc3545, #c82333);
            padding: 20px;
            color: #fff;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .journal-sidebar h3 {
            color: #fff;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .journal-tab {
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #fff;
            display: flex;
            align-items: center;
        }

        .journal-tab:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .journal-tab.active {
            background: #fff;
            color: #dc3545;
            font-weight: 600;
        }

        .journal-tab i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .journal-content {
            flex: 1;
            padding: 25px;
            margin-left: 250px;
            background: #fff;
            min-height: 100vh;
            overflow-y: auto;
        }

        h2 {
            font-weight: bold;
            color: #333;
            margin-bottom: 25px;
        }

        .table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .table thead {
            background: #dc3545;
            color: #fff;
        }

        .table thead th {
            text-align: center;
            font-weight: 600;
            padding: 12px;
        }

        .table tbody td {
            vertical-align: middle;
            text-align: center;
            padding: 10px;
        }

        .table-hover tbody tr:hover {
            background: #ffe6e6;
        }

        .badge-niveau {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-LOW { background: #6c757d; color: #fff; }
        .badge-MEDIUM { background: #ffc107; color: #000; }
        .badge-HIGH { background: #dc3545; color: #fff; }
        .badge-CRITICAL { background: #000; color: #fff; }

        .badge-statut {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-SUCCESS { background: #28a745; color: #fff; }
        .badge-FAILED { background: #dc3545; color: #fff; }
        .badge-PENDING { background: #ffc107; color: #000; }

        .action-details {
            max-width: 300px;
            word-wrap: break-word;
        }

        .json-data {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            font-family: monospace;
            font-size: 0.8rem;
            max-height: 100px;
            overflow-y: auto;
        }
        
        /* Optimisation pour millions de lignes */
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
            contain: layout style paint;
            will-change: scroll-position;
        }
        
        .table tbody {
            display: block;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .table thead,
        .table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        
        .table thead {
            width: calc(100% - 1em);
        }
        
        /* Boutons de navigation */
        .btn-group .btn {
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-group .btn-danger {
            background: linear-gradient(90deg, #dc3545, #c82333);
            border: none;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }
        
        .btn-group .btn-danger:hover {
            background: linear-gradient(90deg, #c82333, #a71e2a);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }
        
        .btn-group .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
        }
        
        .btn-group .btn-outline-secondary:hover {
            background: #6c757d;
            color: #fff;
            transform: translateY(-2px);
        }


        @media (max-width: 768px) {
            .journal-sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .journal-content {
                margin-left: 0;
            }
            
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-group .btn {
                margin-bottom: 5px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <div class="journal-container">
        <!-- Sidebar avec les modules -->
        <div class="journal-sidebar">
            <h3 class="mb-4"><i class="fas fa-shield-alt"></i> Modules</h3>
            
            <!-- Liste des modules -->
            <div class="journal-tab <?php echo $module === '' ? 'active' : ''; ?>" 
                 onclick="changeModule('')">
                <i class="fas fa-list"></i> Tous les modules
            </div>
            
            <?php foreach ($modules as $mod): ?>
                <div class="journal-tab <?php echo $module === $mod ? 'active' : ''; ?>" 
                     onclick="changeModule('<?= htmlspecialchars($mod) ?>')">
                    <i class="fas fa-<?= getModuleIcon($mod) ?>"></i> <?= htmlspecialchars($mod) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Contenu principal -->
        <div class="journal-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="fas fa-history"></i> 
                    <?php if ($module): ?>
                        Journal - <?= htmlspecialchars($module) ?>
                    <?php else: ?>
                        Journal Système
                    <?php endif; ?>
                </h2>
                
                <!-- Bouton retour vers Articles -->
                <div class="btn-group">
                    <a href="articles.php" class="btn btn-danger">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>
                        Accueil
                    </a>
                </div>
            </div>
            
            <!-- Filtres -->
            <form method="POST" class="mb-4">
                <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Utilisateur</label>
                        <select name="utilisateur" class="form-select">
                            <option value="">Tous les utilisateurs</option>
                            <?php foreach ($utilisateurs as $utilisateurData): ?>
                                <option value="<?= $utilisateurData['IDUTILISATEUR'] ?>" 
                                        <?= $utilisateur == $utilisateurData['IDUTILISATEUR'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($utilisateurData['NomPrenom'] ?? 'Utilisateur ' . $utilisateurData['IDUTILISATEUR']) ?>
                                    <?= $utilisateurData['Identifiant'] ? ' (' . htmlspecialchars($utilisateurData['Identifiant']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select">
                            <option value="">Toutes les actions</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?= htmlspecialchars($act) ?>" 
                                        <?= $action === $act ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($act) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date début</label>
                        <input type="date" name="startDate" class="form-control" 
                               value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date fin</label>
                        <input type="date" name="endDate" class="form-control" 
                               value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </div>
                </div>
            </form>

            <!-- Statistiques simples -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-danger"><?= number_format(count($journalData)) ?></h5>
                            <p class="card-text">Actions affichées</p>
                            <?php if (count($journalData) > 100000): ?>
                                <small class="text-muted">Capacité: Millions de lignes supportées</small>
                            <?php elseif (count($journalData) > 10000): ?>
                                <small class="text-muted">Capacité: Centaines de milliers de lignes</small>
                            <?php elseif (count($journalData) > 1000): ?>
                                <small class="text-muted">Capacité: Milliers de lignes</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-dark">
                                <?php
                                $criticalCount = 0;
                                foreach ($journalData as $row) {
                                    if ($row['niveau_securite'] === 'CRITICAL') {
                                        $criticalCount++;
                                    }
                                }
                                echo number_format($criticalCount);
                                ?>
                            </h5>
                            <p class="card-text">Actions critiques</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-danger">
                                <?php
                                $failedCount = 0;
                                foreach ($journalData as $row) {
                                    if ($row['statut_action'] === 'FAILED') {
                                        $failedCount++;
                                    }
                                }
                                echo number_format($failedCount);
                                ?>
                            </h5>
                            <p class="card-text">Actions échouées</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-dark">
                                <?php
                                $uniqueUsers = array_unique(array_column($journalData, 'id_utilisateur'));
                                echo number_format(count($uniqueUsers));
                                ?>
                            </h5>
                            <p class="card-text">Utilisateurs</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des données -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Résultat</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($journalData)): ?>
                            <?php foreach ($journalData as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d/m/Y H:i:s', strtotime($row['date_action'])) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['nom_utilisateur'] ?? 'Système') ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars(getActionTranslation($row['action'])) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars(getModuleTranslation($row['module'])) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['description']) ?>
                                        <?php if ($row['erreur_message']): ?>
                                            <br><small class="text-danger"><?= htmlspecialchars($row['erreur_message']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-statut badge-<?= $row['statut_action'] ?>">
                                            <?= htmlspecialchars(getStatutTranslation($row['statut_action'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="showDetails(<?= htmlspecialchars(json_encode($row)) ?>)">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                                    <br>Aucune donnée trouvée
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal pour afficher les détails -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Contenu dynamique -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showDetails(row) {
            const modalBody = document.getElementById('modalBody');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informations principales</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Date:</strong></td><td>${new Date(row.date_action).toLocaleString('fr-FR')}</td></tr>
                            <tr><td><strong>Utilisateur:</strong></td><td>${row.nom_utilisateur || 'Système'}</td></tr>
                            <tr><td><strong>Module:</strong></td><td>${row.module}</td></tr>
                            <tr><td><strong>Action:</strong></td><td>${row.action}</td></tr>
                            <tr><td><strong>Page:</strong></td><td>${row.page}</td></tr>
                            <tr><td><strong>Statut:</strong></td><td><span class="badge-statut badge-${row.statut_action}">${row.statut_action}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Informations techniques</h6>
                        <table class="table table-sm">
                            <tr><td><strong>IP:</strong></td><td>${row.ip_address || 'N/A'}</td></tr>
                            <tr><td><strong>Session:</strong></td><td>${row.session_id ? row.session_id.substring(0, 20) + '...' : 'N/A'}</td></tr>
                            <tr><td><strong>Durée:</strong></td><td>${row.temps_execution ? row.temps_execution + 's' : 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <div class="alert alert-info">${row.description || 'Aucune description'}</div>
                    </div>
                </div>
                ${row.erreur_message ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Erreur</h6>
                        <div class="alert alert-danger">${row.erreur_message}</div>
                    </div>
                </div>
                ` : ''}
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
        }

        // Fonction pour changer de module
        function changeModule(moduleName) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'journal_systeme.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'module';
            input.value = moduleName;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>

<?php
// Fonction pour obtenir l'icône du module
function getModuleIcon($module) {
    $icons = [
        'AUTHENTIFICATION' => 'sign-in-alt',
        'PRODUITS' => 'box',
        'VENTES' => 'shopping-cart',
        'STOCK' => 'warehouse',
        'SAV' => 'tools',
        'INVENTAIRE' => 'clipboard-list',
        'COMMANDES' => 'file-alt',
        'PROFORMA' => 'file-invoice',
        'CLIENTS' => 'users',
        'FOURNISSEURS' => 'truck',
        'COMPTABILITE' => 'calculator',
        'PARAMETRES' => 'cog'
    ];
    return $icons[$module] ?? 'folder';
}

// Fonction pour traduire les modules
function getModuleTranslation($module) {
    $traductions = [
        'AUTHENTIFICATION' => 'Connexion',
        'PRODUITS' => 'Produits',
        'VENTES' => 'Ventes',
        'STOCK' => 'Stock',
        'SAV' => 'SAV',
        'INVENTAIRE' => 'Inventaire',
        'COMMANDES' => 'Commandes',
        'PROFORMA' => 'Devis',
        'CLIENTS' => 'Clients',
        'FOURNISSEURS' => 'Fournisseurs',
        'UTILISATEURS' => 'Utilisateurs',
        'COMPTABILITE' => 'Comptabilité',
        'PARAMETRES' => 'Paramètres',
        'TEST' => 'Tests'
    ];
    return $traductions[$module] ?? $module;
}

// Fonction pour traduire les actions
function getActionTranslation($action) {
    $traductions = [
        'CONNEXION_REUSSIE' => 'Connexion',
        'DECONNEXION' => 'Déconnexion',
        'TENTATIVE_CONNEXION' => 'Tentative connexion',
        'CREATION_ARTICLE' => 'Création article',
        'MODIFICATION_ARTICLE' => 'Modification article',
        'DESACTIVATION_ARTICLE' => 'Désactivation article',
        'REACTIVATION_ARTICLE' => 'Réactivation article',
        'CREATION_CATEGORIE' => 'Création catégorie',
        'SUPPRESSION_CATEGORIE' => 'Suppression catégorie',
        'VENTE_COMPTANT' => 'Vente comptant',
        'SUPPRESSION_VENTE' => 'Suppression vente',
        'CREATION_DOSSIER_SAV' => 'Création SAV',
        'SUPPRESSION_DOSSIER_SAV' => 'Suppression SAV',
        'LANCEMENT_INVENTAIRE' => 'Lancement inventaire',
        'VALIDATION_INVENTAIRE' => 'Validation inventaire',
        'CORRECTION_STOCK' => 'Correction stock',
        'CREATION_COMMANDE' => 'Création commande',
        'SUPPRESSION_COMMANDE' => 'Suppression commande',
        'CREATION_PROFORMA' => 'Création devis',
        'SUPPRESSION_PROFORMA' => 'Suppression devis',
        'CREATION_CLIENT' => 'Création client',
        'SUPPRESSION_CLIENT' => 'Suppression client',
        'VENTE_COMPTANT' => 'Vente comptant',
        'VENTE_MULTI_PAIEMENT' => 'Vente multi-paiement',
        'VENTE_CREDIT' => 'Vente crédit',
        'VENTE_CREDIT_MULTI_PAIEMENT' => 'Vente crédit multi-paiement',
        'CREATION_VERSEMENT' => 'Création versement',
        'MODIFICATION_VERSEMENT' => 'Modification versement',
        'SUPPRESSION_VERSEMENT' => 'Suppression versement',
        'CREATION_FOURNISSEUR' => 'Création fournisseur',
        'MODIFICATION_FOURNISSEUR' => 'Modification fournisseur',
        'SUPPRESSION_FOURNISSEUR' => 'Suppression fournisseur',
        'CREATION_MODE_REGLEMENT' => 'Création mode de règlement',
        'SUPPRESSION_MODE_REGLEMENT' => 'Suppression mode de règlement',
        'CREATION_UTILISATEUR' => 'Création utilisateur',
        'SUPPRESSION_UTILISATEUR' => 'Suppression utilisateur',
        'ACTIVATION_UTILISATEUR' => 'Activation utilisateur',
        'DESACTIVATION_UTILISATEUR' => 'Désactivation utilisateur',
        'CREATION_PROFORMA' => 'Création proforma',
        'SUPPRESSION_PROFORMA' => 'Suppression proforma',
        'CREATION_COMMANDE' => 'Création commande',
        'SUPPRESSION_COMMANDE' => 'Suppression commande',
        'SUPPRESSION_VENTE' => 'Suppression vente',
        'VALIDATION_ENTREE_STOCK' => 'Validation entrée stock',
        'ENREGISTREMENT_NUMEROS_SERIE' => 'Enregistrement numéros série',
        'ANNULATION_ENTREE_STOCK' => 'Annulation entrée stock',
        'CREATION_NUMERO_SERIE' => 'Création numéro série',
        'VENTE_ARTICLE' => 'Vente article',
        'MISE_A_JOUR_STOCK' => 'Mise à jour stock',
        'VENTE_CREDIT_ARTICLE' => 'Vente crédit article',
        'MISE_A_JOUR_STOCK_CREDIT' => 'Mise à jour stock crédit',
        'ANNULATION_ENTREE_STOCK' => 'Annulation entrée stock',
        'ANNULATION_ENTREE_STOCK_LIMITE' => 'Annulation entrée stock limitée',
        'SUPPRESSION_VENTE' => 'Suppression vente',
        'SUPPRESSION_NUMEROS_SERIE' => 'Suppression numéros série',
        'SUPPRESSION_STOCK' => 'Suppression stock',
        'CREATION_ENTREE_STOCK' => 'Création entrée stock',
        'VALIDATION_ENTREE_STOCK' => 'Validation entrée stock',
        'ERREUR_CREATION_ENTREE_STOCK' => 'Erreur création entrée stock',
        'ERREUR_VALIDATION_ENTREE_STOCK' => 'Erreur validation entrée stock',
        'ERREUR_ANNULATION_ENTREE_STOCK' => 'Erreur annulation entrée stock',
        'ENREGISTREMENT_NUMERO_SERIE' => 'Enregistrement numéro série',
        'ANNULATION_ENTREE_STOCK_LIMITE' => 'Annulation entrée stock limitée',
        'CORRECTION_STOCK_AUTO' => 'Correction stock automatique',
        'SUPPRESSION_ENTREE_STOCK' => 'Suppression entrée stock',
        'VIDAGE_TOUTES_ENTREES' => 'Vidage toutes entrées',
        'ERREUR_SUPPRESSION_ENTREE_STOCK' => 'Erreur suppression entrée stock',
        'ERREUR_VIDAGE_ENTREES' => 'Erreur vidage entrées',
        'CORRECTION_STOCK_MANUELLE' => 'Correction stock manuelle',
        'ERREUR_CORRECTION_STOCK' => 'Erreur correction stock',
        'SUPPRESSION_VENTE_CREDIT' => 'Suppression vente crédit',
        'SUPPRESSION_COMPLETE_VENTE_CREDIT' => 'Suppression complète vente crédit',
        'ERREUR_SUPPRESSION_VENTE_CREDIT' => 'Erreur suppression vente crédit',
        'LANCEMENT_INVENTAIRE' => 'Lancement inventaire',
        'ERREUR_LANCEMENT_INVENTAIRE' => 'Erreur lancement inventaire',
        'VALIDATION_INVENTAIRE' => 'Validation inventaire',
        'ERREUR_VALIDATION_INVENTAIRE' => 'Erreur validation inventaire',
        'CREATION_DOSSIER_SAV' => 'Création dossier SAV',
        'ERREUR_CREATION_DOSSIER_SAV' => 'Erreur création dossier SAV',
        'SUPPRESSION_DOSSIER_SAV' => 'Suppression dossier SAV',
        'ERREUR_SUPPRESSION_DOSSIER_SAV' => 'Erreur suppression dossier SAV',
        'SUPPRESSION_DOSSIER_SAV_SUIVI' => 'Suppression dossier SAV (suivi)',
        'ERREUR_SUPPRESSION_DOSSIER_SAV_SUIVI' => 'Erreur suppression dossier SAV (suivi)',
        'TEST_SYSTEME' => 'Test système',
        'TEST_NIVEAU_LOW' => 'Test faible',
        'TEST_NIVEAU_MEDIUM' => 'Test moyen',
        'TEST_NIVEAU_HIGH' => 'Test élevé',
        'TEST_NIVEAU_CRITICAL' => 'Test critique'
    ];
    return $traductions[$action] ?? $action;
}

// Fonction pour traduire les statuts
function getStatutTranslation($statut) {
    $traductions = [
        'SUCCESS' => 'Réussi',
        'FAILED' => 'Échoué',
        'PENDING' => 'En cours'
    ];
    return $traductions[$statut] ?? $statut;
}
?>
