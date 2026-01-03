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
        error_log("Fatal error inventaire_liste: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
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
    
    // Vérification de l'accès utilisateur
    if (function_exists('check_access')) {
        check_access();
    }
    
} catch (Exception $e) {
    error_log("Erreur de connexion inventaire_liste.php : " . $e->getMessage());
    http_response_code(500);
    echo "Erreur de connexion : " . $e->getMessage();
    exit;
}

// Filtres
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : '';

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if (!empty($date_debut)) {
    $where_conditions[] = "i.DateInventaire >= ?";
    $params[] = $date_debut . ' 00:00:00';
}

if (!empty($date_fin)) {
    $where_conditions[] = "i.DateInventaire <= ?";
    $params[] = $date_fin . ' 23:59:59';
}

if (!empty($statut_filter)) {
    $where_conditions[] = "i.StatutInventaire = ?";
    $params[] = $statut_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Récupération des inventaires avec valeurs détaillées avec gestion d'erreurs
try {
    $query = "
        SELECT 
            i.*,
            COUNT(il.id) as nombre_articles,
            SUM(CASE WHEN il.ecart != 0 THEN 1 ELSE 0 END) as articles_avec_ecart,
            SUM(il.ecart) as total_ecart_quantite
           
        FROM inventaire i
        LEFT JOIN inventaire_ligne il ON i.IDINVENTAIRE = il.id_inventaire
        LEFT JOIN article a ON il.id_article = a.IDARTICLE
        $where_clause
        GROUP BY i.IDINVENTAIRE
        ORDER BY i.DateInventaire DESC
    ";

    $stmt = $cnx->prepare($query);
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête inventaires");
    }
    $stmt->execute($params);
    $inventaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération inventaires : " . $e->getMessage());
    $inventaires = [];
}

// Récupération du nombre d'inventaires en cours avec gestion d'erreurs
try {
    $stmt = $cnx->query("SELECT COUNT(*) FROM inventaire WHERE StatutInventaire = 'en_attente'");
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête inventaires en cours");
    }
    $inventaires_en_cours = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Erreur récupération inventaires en cours : " . $e->getMessage());
    $inventaires_en_cours = 0;
}

// Calcul des totaux globaux
$totaux_globaux = [
    'nombre_inventaires' => count($inventaires),
    'valeur_theorique_achat' => 0,
    'valeur_physique_achat' => 0,
    'valeur_ecart_achat' => 0,
    'valeur_theorique_vente' => 0,
    'valeur_physique_vente' => 0,
    'valeur_ecart_vente' => 0
];

foreach ($inventaires as $inv) {
    $totaux_globaux['valeur_theorique_achat'] += $inv['valeur_theorique_achat'] ?? 0;
    $totaux_globaux['valeur_physique_achat'] += $inv['valeur_physique_achat'] ?? 0;
    $totaux_globaux['valeur_ecart_achat'] += $inv['valeur_ecart_achat'] ?? 0;
    $totaux_globaux['valeur_theorique_vente'] += $inv['valeur_theorique_vente'] ?? 0;
    $totaux_globaux['valeur_physique_vente'] += $inv['valeur_physique_vente'] ?? 0;
    $totaux_globaux['valeur_ecart_vente'] += $inv['valeur_ecart_vente'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Inventaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #dc3545;
            --dark-red: #c82333;
            --light-red: #f8d7da;
            --black: #212529;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --gray-800: #495057;
        }

        body { 
            background-color: var(--gray-100);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--black);
        }

        .container { 
            max-width: 1400px; 
            margin: 2rem auto; 
            padding: 0 1rem;
        }

        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-left: 5px solid var(--primary-red);
        }

        .page-title {
            color: var(--black);
            margin: 0;
            font-weight: 700;
            font-size: 2.2rem;
        }

        .btn-primary {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--dark-red);
            border-color: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        .filters-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: 1px solid var(--gray-200);
        }

        .filters-title {
            color: var(--black);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stats-title {
            color: var(--gray-600);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .stats-value {
            color: var(--black);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .stats-value.positive { color: #28a745; }
        .stats-value.negative { color: var(--primary-red); }
        .stats-value.neutral { color: var(--gray-600); }

        .table-container {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: var(--black);
            color: var(--white);
            font-weight: 600;
            padding: 1rem;
            border: none;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--gray-100);
        }

        .badge {
            padding: 0.5em 1em;
            font-weight: 600;
            border-radius: 6px;
        }

        .badge-success {
            background-color: #28a745;
            color: var(--white);
        }

        .badge-warning {
            background-color: #ffc107;
            color: var(--black);
        }

        .badge-danger {
            background-color: var(--primary-red);
            color: var(--white);
        }

        .btn-group .btn {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            margin: 0 2px;
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-secondary {
            background-color: var(--gray-600);
            border-color: var(--gray-600);
        }

        .btn-outline-primary {
            color: var(--primary-red);
            border-color: var(--primary-red);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
            color: var(--white);
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: var(--light-red);
            color: #721c24;
        }

        .value-display {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .value-positive { color: #28a745; }
        .value-negative { color: var(--primary-red); }
        .value-neutral { color: var(--gray-600); }

        .category-details {
            background-color: var(--gray-100);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-name {
            font-weight: 600;
            color: var(--black);
        }

        .category-values {
            display: flex;
            gap: 1rem;
            font-family: 'Courier New', monospace;
        }

        .totals-row {
            background-color: var(--black);
            color: var(--white);
            font-weight: 700;
        }

        .totals-row td {
            border-bottom: none;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
            font-style: italic;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--gray-600);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .btn-group .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>

</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
        <!-- Système de thème sombre/clair -->
        <?php include('includes/theme_switcher.php'); ?>

<div class="container">
    <!-- En-tête de page -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title">
            <i class="fas fa-clipboard-list me-3" style="color: var(--primary-red);"></i>
            Historique des Inventaires
        </h1>
        <a href="inventaire_lancement.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nouvel Inventaire
        </a>
    </div>

    <!-- Messages d'alerte -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Section des filtres -->
    <div class="filters-section">
        <h5 class="filters-title">
            <i class="fas fa-filter me-2" style="color: var(--primary-red);"></i>
            Filtres de recherche
        </h5>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Date de début</label>
                <input type="date" name="date_debut" class="form-control" value="<?php echo htmlspecialchars($date_debut); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date de fin</label>
                <input type="date" name="date_fin" class="form-control" value="<?php echo htmlspecialchars($date_fin); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="en_attente" <?php echo $statut_filter == 'en_attente' ? 'selected' : ''; ?>>En cours</option>
                    <option value="valide" <?php echo $statut_filter == 'valide' ? 'selected' : ''; ?>>Validé</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-flex gap-2 w-100">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="fas fa-search me-1"></i>Rechercher
                    </button>
                    <a href="inventaire_liste.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistiques globales -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="stats-title">Inventaires en cours</div>
            <p class="stats-value <?php echo $inventaires_en_cours > 0 ? 'negative' : 'neutral'; ?>">
                <?php echo $inventaires_en_cours; ?>
            </p>
        </div>
        <div class="stats-card">
            <div class="stats-title">Total des inventaires</div>
            <p class="stats-value neutral"><?php echo $totaux_globaux['nombre_inventaires']; ?></p>
        </div>
    </div>

    <!-- Tableau des inventaires -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nom de l'inventaire</th>
                        <th>Date</th>
                        <th>Articles</th>
                        <th>Valeur Théorique (Achat)</th>
                        <th>Valeur Physique (Achat)</th>
                        <th>Écart (Achat)</th>
                        <th>Valeur Théorique (Vente)</th>
                        <th>Valeur Physique (Vente)</th>
                        <th>Écart (Vente)</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($inventaires)): ?>
                    <tr>
                        <td colspan="9" class="no-data">
                            <i class="fas fa-inbox fa-3x mb-3" style="color: var(--gray-300);"></i>
                            <br>Aucun inventaire trouvé avec les critères sélectionnés
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $modals = '';
                    foreach($inventaires as $inv): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($inv['Commentaires']); ?></strong>
                                <?php if ($inv['articles_avec_ecart'] > 0): ?>
                                    <br><small class="text-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php echo $inv['articles_avec_ecart']; ?> articles avec écart
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo date('d/m/Y', strtotime($inv['DateInventaire'])); ?></div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($inv['DateInventaire'])); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?php echo $inv['nombre_articles']; ?> articles
                                </span>
                            </td>
                           <td>
   <div class="value-display value-neutral" id="valeur-theorique-achat-<?php echo $inv['IDINVENTAIRE']; ?>">
      <?php echo number_format($inv['valeur_theorique_achat'] ?? 0, 0, ',', ' '); ?>
   </div>
</td>
<td>
   <div class="value-display value-neutral" id="valeur-physique-achat-<?php echo $inv['IDINVENTAIRE']; ?>">
      <?php echo number_format($inv['valeur_physique_achat'] ?? 0, 0, ',', ' '); ?> 
   </div>
</td>
<td>
   <div class="value-display value-neutral" id="valeur-ecart-achat-<?php echo $inv['IDINVENTAIRE']; ?>">
      <?php 
         $ecart = $inv['valeur_ecart_achat'] ?? 0;
         echo ($ecart > 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') . '';
      ?>
   </div>
</td>

<td>
   <div class="value-display value-neutral" id="valeur-theorique-vente-<?php echo $inv['IDINVENTAIRE']; ?>">
      <?php echo number_format($inv['valeur_theorique_vente'] ?? 0, 0, ',', ' '); ?> 
       </div>
</td>
<td>
   <div class="value-display value-neutral" id="valeur-physique-vente-<?php echo $inv['IDINVENTAIRE']; ?>">
      <?php echo number_format($inv['valeur_physique_vente'] ?? 0, 0, ',', ' '); ?>
   </div>
</td>
<td>
   <div class="value-display value-neutral" id="valeur-ecart-vente-<?php echo $inv['IDINVENTAIRE']; ?>">
      <?php 
         $ecartV = $inv['valeur_ecart_vente'] ?? 0;
         echo ($ecartV > 0 ? '+' : '') . number_format($ecartV, 0, ',', ' ') . '';
      ?>
   </div>
</td>
                            <td>
                                <?php if($inv['StatutInventaire'] == 'valide'): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle me-1"></i>Validé
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock me-1"></i>En cours
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="inventaire_saisie.php?IDINVENTAIRE=<?php echo $inv['IDINVENTAIRE']; ?>" 
                                       class="btn btn-info" 
                                       title="Consulter">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Imprimer rapport">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="inventaire_rapport.php?IDINVENTAIRE=<?php echo $inv['IDINVENTAIRE']; ?>&type=achat_vente">
                                                <i class="fas fa-file-invoice-dollar me-2"></i>Rapport Achat/Vente
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="inventaire_rapport.php?IDINVENTAIRE=<?php echo $inv['IDINVENTAIRE']; ?>&type=achat">
                                                <i class="fas fa-file-invoice me-2"></i>Rapport Achat
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="showCategorieModal(<?php echo $inv['IDINVENTAIRE']; ?>); return false;">
                                                <i class="fas fa-layer-group me-2"></i>Rapport par Catégorie
                                            </a>
                                        </li>
                                    </ul>
                                    <button type="button" 
                                            class="btn btn-outline-secondary category-details-btn" 
                                            data-inventaire="<?php echo $inv['IDINVENTAIRE']; ?>"
                                            title="Voir détails par catégorie">
                                        <i class="fas fa-list"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Détail par catégorie (affiché au clic) -->
                        <tr class="category-detail-row" style="display: none;" data-inventaire="<?php echo $inv['IDINVENTAIRE']; ?>">
                            <td colspan="9">
                                <div class="category-details">
                                    <div class="text-center mb-2">
                                        <strong>Détail par catégorie - <?php echo htmlspecialchars($inv['Commentaires']); ?></strong>
                                    </div>
                                    <div id="categories-<?php echo $inv['IDINVENTAIRE']; ?>">
                                        <div class="loading">
                                            <i class="fas fa-spinner fa-spin me-2"></i>Chargement des catégories...
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php ob_start(); ?>
                        <!-- Modal de sélection de catégorie POUR CET INVENTAIRE -->
                        <div class="modal fade" id="modalCategorieRapport-<?php echo $inv['IDINVENTAIRE']; ?>" tabindex="-1" aria-labelledby="modalCategorieRapportLabel-<?php echo $inv['IDINVENTAIRE']; ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modalCategorieRapportLabel-<?php echo $inv['IDINVENTAIRE']; ?>">Choisir une catégorie</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                              </div>
                              <div class="modal-body">
                                <select class="form-select" id="selectCategorie-<?php echo $inv['IDINVENTAIRE']; ?>">
                                  <option value="">-- Sélectionner --</option>
                                  <?php
                                  try {
                                      $stmt = $cnx->prepare("SELECT DISTINCT categorie FROM inventaire_ligne WHERE id_inventaire = ? ORDER BY categorie");
                                      if ($stmt) {
                                          $stmt->execute([intval($inv['IDINVENTAIRE'])]);
                                          $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                          foreach ($cats as $cat) {
                                              echo '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
                                          }
                                      }
                                  } catch (Exception $e) {
                                      error_log("Erreur récupération catégories pour inventaire " . $inv['IDINVENTAIRE'] . ": " . $e->getMessage());
                                      echo '<option value="">Erreur de chargement</option>';
                                  }
                                  ?>
                                </select>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="button" class="btn btn-primary" onclick="imprimerRapportCategorie(<?php echo $inv['IDINVENTAIRE']; ?>)">Imprimer</button>
                              </div>
                            </div>
                          </div>
                        </div>
                        <?php $modals .= ob_get_clean(); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            
            </table>
        </div>
    </div>
<?php echo $modals; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Gestion de l'affichage des détails par catégorie avec le nouveau bouton
    document.querySelectorAll('.category-details-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const inventaireId = this.dataset.inventaire;
            const detailRow = document.querySelector(`tr[data-inventaire="${inventaireId}"]`);
            
            if (detailRow) {
                const isVisible = detailRow.style.display !== 'none';
                
                // Masquer tous les détails
                document.querySelectorAll('.category-detail-row').forEach(row => {
                    row.style.display = 'none';
                });
                
                // Afficher le détail cliqué si il était masqué
                if (!isVisible) {
                    detailRow.style.display = 'table-row';
                    loadCategoryDetails(inventaireId);
                }
            }
        });
    });
});

// Fonction pour charger les détails par catégorie
function loadCategoryDetails(inventaireId) {
    const container = document.getElementById(`categories-${inventaireId}`);
    
    // Simuler le chargement des données (à remplacer par un appel AJAX réel)
    fetch(`get_categories_details.php?IDINVENTAIRE=${inventaireId}`)
        .then(response => response.json())
        .then(data => {
            let html = '';
            
            if (data.categories && data.categories.length > 0) {
                data.categories.forEach(category => {
                    html += `
                        <div class="category-item">
                            <div class="category-name">${category.nom}</div>
                            <div class="category-values">
                                <span class="value-neutral">Théorique: ${formatCurrency(category.valeur_theorique)}</span>
                                <span class="value-neutral">Physique: ${formatCurrency(category.valeur_physique)}</span>
                                <span class="${category.valeur_ecart > 0 ? 'value-negative' : (category.valeur_ecart < 0 ? 'value-positive' : 'value-neutral')}">
                                    Écart: ${formatCurrency(category.valeur_ecart)}
                                </span>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="text-center text-muted">Aucune donnée de catégorie disponible</div>';
            }
            
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Erreur lors du chargement des catégories:', error);
            container.innerHTML = '<div class="text-center text-danger">Erreur lors du chargement des données</div>';
        });
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id^="valeur-physique-achat-"]').forEach(div => {
        const inventaireId = div.id.replace("valeur-physique-achat-", "");
        updateInventaireTotaux(inventaireId);
    });
});

function formatCurrency(valeur) {
    return new Intl.NumberFormat('fr-FR').format(valeur);
}

function updateInventaireTotaux(inventaireId) {
    fetch(`get_inventaire_totaux.php?IDINVENTAIRE=${inventaireId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.totaux) {
                // ACHAT
                document.querySelector(`#valeur-theorique-achat-${inventaireId}`).textContent =
                    formatCurrency(data.totaux.valeur_theorique_achat) + " F.CFA";

                document.querySelector(`#valeur-physique-achat-${inventaireId}`).textContent =
                    formatCurrency(data.totaux.valeur_physique_achat) + " F.CFA";

                document.querySelector(`#valeur-ecart-achat-${inventaireId}`).textContent =
                    (data.totaux.valeur_ecart_achat > 0 ? "+" : "") +
                    formatCurrency(data.totaux.valeur_ecart_achat) + " F.CFA";

                // VENTE
                document.querySelector(`#valeur-theorique-vente-${inventaireId}`).textContent =
                    formatCurrency(data.totaux.valeur_theorique_vente) + " F.CFA";

                document.querySelector(`#valeur-physique-vente-${inventaireId}`).textContent =
                    formatCurrency(data.totaux.valeur_physique_vente) + " F.CFA";

                document.querySelector(`#valeur-ecart-vente-${inventaireId}`).textContent =
                    (data.totaux.valeur_ecart_vente > 0 ? "+" : "") +
                    formatCurrency(data.totaux.valeur_ecart_vente) + " F.CFA";
            }
        })
        .catch(error => {
            console.error("Erreur totaux inventaire:", error);
        });
}


// Fonction pour formater les montants
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR').format(amount) + ' F.CFA';
}

function showCategorieModal(id) {
    var modalEl = document.getElementById('modalCategorieRapport-' + id);
    if (!modalEl) {
        alert("Erreur : le modal pour cet inventaire n'existe pas.");
        return;
    }
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}
function imprimerRapportCategorie(id) {
    var select = document.getElementById('selectCategorie-' + id);
    var cat = select.value;
    if (!cat) { alert('Veuillez choisir une catégorie'); return; }
    window.location.href = 'inventaire_rapport.php?IDINVENTAIRE=' + id + '&type=categorie&categorie=' + encodeURIComponent(cat);
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalCategorieRapport-' + id));
    modal.hide();
}
</script>
</body>
</html>