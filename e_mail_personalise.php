<?php
include('db/connecting.php');
require_once 'fonction_traitement/fonction.php';

// Mise à jour automatique de la base de données

// Ajout d'un modèle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $titre = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $couleur = $_POST['couleur'] ?? 'red';
    $categorie = trim($_POST['categorie'] ?? 'general');
    $variables = trim($_POST['variables'] ?? '');
    $id_utilisateur = $_SESSION['id_utilisateur'] ?? null;
    
    if ($titre && $contenu) {
        $sql = "INSERT INTO modeles_message (type, titre, contenu, couleur, categorie, variables, id_utilisateur) VALUES ('email', :titre, :contenu, :couleur, :categorie, :variables, :id_utilisateur)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([
            'titre' => $titre,
            'contenu' => $contenu,
            'couleur' => $couleur,
            'categorie' => $categorie,
            'variables' => $variables,
            'id_utilisateur' => $id_utilisateur
        ]);
    }
    header('Location: e_mail_personalise.php');
    exit();
}
// Suppression d'un modèle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $id = intval($_POST['id'] ?? 0);
    $id_utilisateur = $_SESSION['id_utilisateur'] ?? null;
    if ($id) {
        $sql = "DELETE FROM modeles_message WHERE id = :id AND (id_utilisateur = :id_utilisateur OR id_utilisateur IS NULL)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute(['id' => $id, 'id_utilisateur' => $id_utilisateur]);
    }
    header('Location: e_mail_personalise.php');
    exit();
}
// Récupération des modèles avec filtres
$id_utilisateur = $_SESSION['id_utilisateur'] ?? null;
$categorie_filtre = $_GET['categorie'] ?? '';
$recherche = $_GET['recherche'] ?? '';

$sql = "SELECT * FROM modeles_message WHERE type = 'email' AND (id_utilisateur IS NULL OR id_utilisateur = :id_utilisateur)";
$params = ['id_utilisateur' => $id_utilisateur];

if ($categorie_filtre) {
    $sql .= " AND categorie = :categorie";
    $params['categorie'] = $categorie_filtre;
}

if ($recherche) {
    $sql .= " AND (titre LIKE :recherche OR contenu LIKE :recherche)";
    $params['recherche'] = "%$recherche%";
}

// Vérifier si date_modification existe, sinon utiliser date_creation
$stmt_check = $cnx->query("SHOW COLUMNS FROM modeles_message LIKE 'date_modification'");
if ($stmt_check->rowCount() > 0) {
    $sql .= " ORDER BY date_modification DESC";
} else {
    $sql .= " ORDER BY date_creation DESC";
}

$stmt = $cnx->prepare($sql);
$stmt->execute($params);
$modeles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des catégories pour le filtre
$stmt_cat = $cnx->prepare("SELECT DISTINCT categorie FROM modeles_message WHERE type = 'email' AND categorie IS NOT NULL ORDER BY categorie");
$stmt_cat->execute();
$categories = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modèles Email Personnalisés - SOTECH</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            padding: 30px;
            backdrop-filter: blur(10px);
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            border-radius: 15px;
            color: white;
        }
        
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header-section p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .creation-panel {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #4ecdc4;
            box-shadow: 0 0 0 0.2rem rgba(78, 205, 196, 0.25);
        }
        
        .color-palette {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .color-option {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .color-option.selected {
            border-color: #333;
            transform: scale(1.1);
        }
        
        .color-option:hover {
            transform: scale(1.05);
        }
        
        .color-option::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .color-option.selected::after {
            opacity: 1;
        }
        
        .btn-primary-custom {
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .models-section {
            margin-top: 40px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 25px;
            border: 2px solid #e9ecef;
            width: 250px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .model-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid;
            position: relative;
            overflow: hidden;
        }
        
        .model-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .model-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border-radius: 0 15px 0 100px;
        }
        
        .model-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .model-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        
        .model-category {
            background: #e9ecef;
            color: #495057;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .model-content {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 20px;
            max-height: 150px;
            overflow: hidden;
            position: relative;
        }
        
        .model-content.expanded {
            max-height: none;
        }
        
        .model-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-action {
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-use {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        
        .btn-copy {
            background: linear-gradient(45deg, #007bff, #6610f2);
            color: white;
        }
        
        .btn-edit {
            background: linear-gradient(45deg, #ffc107, #fd7e14);
            color: white;
        }
        
        .btn-delete {
            background: linear-gradient(45deg, #dc3545, #e83e8c);
            color: white;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .variables-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .variable-tag {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .variable-tag:hover {
            background: #0056b3;
            transform: scale(1.05);
        }
        
        .stats-section {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .preview-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            border: 2px dashed #dee2e6;
        }
        
        .preview-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .preview-content {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #007bff;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100px;
        }
        
        /* Éditeur Quill personnalisé */
        .ql-editor {
            min-height: 200px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .ql-toolbar {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            border: 2px solid #e9ecef;
        }
        
        .ql-container {
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
            border: 2px solid #e9ecef;
            border-top: none;
        }
        
        .template-templates {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .template-item {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .template-item:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        
        .template-item i {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                padding: 20px;
            }
            
            .header-section h1 {
                font-size: 2rem;
            }
            
            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        /* Styles personnalisés pour SweetAlert */
        .swal2-popup-custom {
            border-radius: 15px !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
        }
        
        .swal2-title-custom {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .swal2-content-custom {
            color: #6c757d !important;
            font-size: 1.1rem !important;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    
    <div class="container-fluid">
        <div class="main-container">
            <!-- Header Section -->
            <div class="header-section">
                <h1><i class="fas fa-envelope"></i> Modèles Email Personnalisés</h1>
                <p>Créez et gérez vos modèles d'emails professionnels avec un éditeur de texte riche</p>
            </div>

            <!-- Statistiques -->
            <div class="stats-section">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?= count($modeles) ?></div>
                            <div class="stat-label">Modèles créés</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?= count($categories) ?></div>
                            <div class="stat-label">Catégories</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?= array_sum(array_column($modeles, 'utilisation_count')) ?></div>
                            <div class="stat-label">Utilisations</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number">∞</div>
                            <div class="stat-label">Caractères max</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de création -->
            <div class="creation-panel">
                <h3><i class="fas fa-plus-circle"></i> Créer un nouveau modèle Email</h3>
            <form method="POST" action="" id="formAjoutModele">
                <input type="hidden" name="action" value="ajouter">
                    
                    <div class="row">
                        <div class="col-md-6">
                <div class="form-group">
                                <label for="titre"><i class="fas fa-tag"></i> Titre du modèle</label>
                                <input type="text" name="titre" id="titre" class="form-control" placeholder="Ex: Confirmation de commande" required>
                            </div>
                </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="categorie"><i class="fas fa-folder"></i> Catégorie</label>
                                <select name="categorie" id="categorie" class="form-control">
                                    <option value="general">Général</option>
                                    <option value="promotion">Promotion</option>
                                    <option value="rappel">Rappel</option>
                                    <option value="confirmation">Confirmation</option>
                                    <option value="notification">Notification</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="facture">Facture</option>
                                    <option value="newsletter">Newsletter</option>
                                </select>
                </div>
        </div>
    </div>
                    
                    <!-- Templates prédéfinis -->
                    <div class="form-group">
                        <label><i class="fas fa-magic"></i> Templates prédéfinis (cliquez pour charger)</label>
                        <div class="template-templates">
                            <div class="template-item" onclick="loadTemplate('confirmation')">
                                <i class="fas fa-check-circle"></i>
                                <div>Confirmation</div>
                            </div>
                            <div class="template-item" onclick="loadTemplate('facture')">
                                <i class="fas fa-file-invoice"></i>
                                <div>Facture</div>
                            </div>
                            <div class="template-item" onclick="loadTemplate('promotion')">
                                <i class="fas fa-percentage"></i>
                                <div>Promotion</div>
                            </div>
                            <div class="template-item" onclick="loadTemplate('newsletter')">
                                <i class="fas fa-newspaper"></i>
                                <div>Newsletter</div>
                        </div>
                    </div>
                </div>
                    
                <div class="form-group">
                        <label for="contenu"><i class="fas fa-edit"></i> Contenu du message</label>
                        <div id="editor"></div>
                        <textarea name="contenu" id="contenu" style="display: none;"></textarea>
                    </div>
                    
                    <!-- Variables disponibles -->
                    <div class="variables-section">
                        <label><i class="fas fa-code"></i> Variables disponibles (cliquez pour insérer)</label>
                        <div>
                            <span class="variable-tag" onclick="insertVariable('{NOM_CLIENT}')">{NOM_CLIENT}</span>
                            <span class="variable-tag" onclick="insertVariable('{MONTANT}')">{MONTANT}</span>
                            <span class="variable-tag" onclick="insertVariable('{DATE}')">{DATE}</span>
                            <span class="variable-tag" onclick="insertVariable('{HEURE}')">{HEURE}</span>
                            <span class="variable-tag" onclick="insertVariable('{PRODUIT}')">{PRODUIT}</span>
                            <span class="variable-tag" onclick="insertVariable('{QUANTITE}')">{QUANTITE}</span>
                            <span class="variable-tag" onclick="insertVariable('{REFERENCE}')">{REFERENCE}</span>
                            <span class="variable-tag" onclick="insertVariable('{ENTREPRISE}')">{ENTREPRISE}</span>
                            <span class="variable-tag" onclick="insertVariable('{EMAIL_CLIENT}')">{EMAIL_CLIENT}</span>
                            <span class="variable-tag" onclick="insertVariable('{TELEPHONE}')">{TELEPHONE}</span>
                </div>
                </div>
                    
                    <!-- Aperçu en temps réel -->
                    <div class="preview-section">
                        <div class="preview-title"><i class="fas fa-eye"></i> Aperçu du message</div>
                        <div class="preview-content" id="messagePreview">
                            Votre message apparaîtra ici...
        </div>
    </div>
                    
                    <!-- Sélecteur de couleur -->
                    <div class="form-group">
                        <label><i class="fas fa-palette"></i> Couleur du modèle</label>
                        <div class="color-palette">
                            <label><input type="radio" name="couleur" value="#ff6b6b" checked hidden><div class="color-option selected" style="background-color: #ff6b6b;" data-color="#ff6b6b"></div></label>
                            <label><input type="radio" name="couleur" value="#4ecdc4" hidden><div class="color-option" style="background-color: #4ecdc4;" data-color="#4ecdc4"></div></label>
                            <label><input type="radio" name="couleur" value="#45b7d1" hidden><div class="color-option" style="background-color: #45b7d1;" data-color="#45b7d1"></div></label>
                            <label><input type="radio" name="couleur" value="#96ceb4" hidden><div class="color-option" style="background-color: #96ceb4;" data-color="#96ceb4"></div></label>
                            <label><input type="radio" name="couleur" value="#feca57" hidden><div class="color-option" style="background-color: #feca57;" data-color="#feca57"></div></label>
                            <label><input type="radio" name="couleur" value="#ff9ff3" hidden><div class="color-option" style="background-color: #ff9ff3;" data-color="#ff9ff3"></div></label>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button class="btn btn-primary-custom" type="submit">
                            <i class="fas fa-save"></i> Enregistrer le modèle
                        </button>
                    </div>
                </form>
            </div>
            <!-- Section des modèles existants -->
            <div class="models-section">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> Mes modèles Email (<?= count($modeles) ?>)</h3>
                    <div class="search-filters">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Rechercher un modèle..." value="<?= htmlspecialchars($recherche) ?>">
                        </div>
                        <select id="categoryFilter" class="form-control" style="width: auto;">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $categorie_filtre === $cat ? 'selected' : '' ?>>
                                    <?= ucfirst(htmlspecialchars($cat)) ?>
                                </option>
            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <?php if (empty($modeles)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucun modèle Email créé</h4>
                        <p class="text-muted">Commencez par créer votre premier modèle Email personnalisé</p>
                    </div>
                <?php else: ?>
                    <div class="row" id="modelsContainer">
            <?php foreach ($modeles as $modele): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="model-card" style="border-left-color: <?= htmlspecialchars($modele['couleur']) ?>;">
                                    <div class="model-header">
                                        <h4 class="model-title"><?= htmlspecialchars($modele['titre']) ?></h4>
                                        <span class="model-category"><?= ucfirst(htmlspecialchars($modele['categorie'] ?? 'general')) ?></span>
                                    </div>
                                    
                                    <div class="model-content" id="content-<?= $modele['id'] ?>">
                                        <?= htmlspecialchars(strip_tags(html_entity_decode($modele['contenu'], ENT_QUOTES, 'UTF-8'))) ?>
                                    </div>
                                    
                                    <?php if (strlen(strip_tags($modele['contenu'])) > 150): ?>
                                        <button class="btn btn-link btn-sm p-0" onclick="toggleContent(<?= $modele['id'] ?>)">
                                            <i class="fas fa-chevron-down"></i> Voir plus
                                        </button>
                                    <?php endif; ?>
                                    
                                    <div class="model-actions">
                                        <button class="btn btn-action btn-use" data-content="<?= htmlspecialchars($modele['contenu'], ENT_QUOTES, 'UTF-8') ?>" onclick="utiliserModele(this.dataset.content)">
                                            <i class="fas fa-paper-plane"></i> Utiliser
                                        </button>
                                        <button class="btn btn-action btn-copy" data-content="<?= htmlspecialchars(strip_tags($modele['contenu']), ENT_QUOTES, 'UTF-8') ?>" onclick="copyToClipboard(this.dataset.content)">
                                            <i class="fas fa-copy"></i> Copier
                                        </button>
                                        <button class="btn btn-action btn-edit" onclick="editModele(<?= $modele['id'] ?>)">
                                            <i class="fas fa-edit"></i> Modifier
                                        </button>
                                        <button class="btn btn-action btn-delete" onclick="deleteMessage(<?= $modele['id'] ?>)">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?= date('d/m/Y à H:i', strtotime($modele['date_creation'])) ?>
                                            <?php if ($modele['utilisation_count'] > 0): ?>
                                                | <i class="fas fa-chart-line"></i> <?= $modele['utilisation_count'] ?> utilisation(s)
                                            <?php endif; ?>
                                        </small>
        </div>
    </div>
</div>
            <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let quill;
    
    $(document).ready(function() {
        // Initialiser l'éditeur Quill
        quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'],
                    ['clean']
                ]
            },
            placeholder: 'Saisissez votre message email ici...'
        });
        
        // Synchroniser avec le textarea caché
        quill.on('text-change', function() {
            let html = quill.root.innerHTML;
            
            // Nettoyer le HTML généré par Quill
            html = html.replace(/<p><br><\/p>/g, '<p></p>'); // Balises p vides
            html = html.replace(/<p><\/p>/g, ''); // Supprimer les paragraphes vides
            html = html.replace(/<p><br><\/p>/g, ''); // Supprimer les paragraphes avec br
            html = html.replace(/\s+/g, ' '); // Nettoyer les espaces multiples
            html = html.trim(); // Supprimer les espaces en début/fin
            
            document.getElementById('contenu').value = html;
            updatePreview(html);
        });
        
        // Gestion des couleurs
        $('.color-option').click(function() {
            $('.color-option').removeClass('selected');
            $(this).addClass('selected');
            $(this).prev('input[type=radio]').prop('checked', true);
        });
        
        // Recherche en temps réel
        $('#searchInput').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('.model-card').each(function() {
                const title = $(this).find('.model-title').text().toLowerCase();
                const content = $(this).find('.model-content').text().toLowerCase();
                
                if (title.includes(searchTerm) || content.includes(searchTerm)) {
                    $(this).closest('.col-lg-6').show();
                } else {
                    $(this).closest('.col-lg-6').hide();
                }
    });
});
        
        // Filtre par catégorie
        $('#categoryFilter').on('change', function() {
            const category = $(this).val();
            if (category) {
                window.location.href = '?categorie=' + encodeURIComponent(category);
            } else {
                window.location.href = 'e_mail_personalise.php';
            }
        });
        
        // Soumission du formulaire
        $('#formAjoutModele').on('submit', function() {
            const html = quill.root.innerHTML;
            document.getElementById('contenu').value = html;
    });
});
    
    // Templates prédéfinis
    const templates = {
        confirmation: `
            <h2>Confirmation de commande</h2>
            <p>Bonjour {NOM_CLIENT},</p>
            <p>Nous vous confirmons la réception de votre commande n°{REFERENCE} d'un montant de {MONTANT} FCFA.</p>
            <p>Votre commande sera traitée dans les plus brefs délais.</p>
            <p>Cordialement,<br>L'équipe {ENTREPRISE}</p>
        `,
        facture: `
            <h2>Facture n°{REFERENCE}</h2>
            <p>Bonjour {NOM_CLIENT},</p>
            <p>Veuillez trouver ci-joint votre facture d'un montant de {MONTANT} FCFA.</p>
            <p>Date d'émission : {DATE}</p>
            <p>Merci pour votre confiance.</p>
            <p>Cordialement,<br>L'équipe {ENTREPRISE}</p>
        `,
        promotion: `
            <h2>Offre spéciale !</h2>
            <p>Bonjour {NOM_CLIENT},</p>
            <p>Découvrez notre nouvelle offre sur {PRODUIT} !</p>
            <p>Profitez de cette promotion limitée dans le temps.</p>
            <p>L'équipe {ENTREPRISE}</p>
        `,
        newsletter: `
            <h2>Newsletter {ENTREPRISE}</h2>
            <p>Bonjour {NOM_CLIENT},</p>
            <p>Découvrez nos dernières actualités et offres.</p>
            <p>Merci de votre fidélité.</p>
            <p>L'équipe {ENTREPRISE}</p>
        `
    };
    
    function loadTemplate(templateName) {
        if (templates[templateName]) {
            quill.root.innerHTML = templates[templateName];
            updatePreview(templates[templateName]);
            showToast('Template chargé avec succès', 'success');
        }
    }
    
    // Insérer une variable dans l'éditeur
    function insertVariable(variable) {
        const range = quill.getSelection();
        if (range) {
            quill.insertText(range.index, variable);
        } else {
            quill.insertText(quill.getLength(), variable);
        }
        quill.focus();
    }
    
    // Utiliser un modèle (rediriger vers la page d'envoi email avec le message - même onglet)
    function utiliserModele(contenu) {
        const url = `envoyer_email.php?message=${encodeURIComponent(contenu)}`;
        window.location.href = url;
    }
    
// Copier dans le presse-papiers
    function copyToClipboard(text) {
        // Nettoyer le texte pour éviter les balises HTML et le code PHP
        let cleanText = text;
        if (typeof text === 'string') {
            // Créer un élément temporaire pour décoder les entités HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = text;
            
            // Récupérer le texte sans les balises HTML
            cleanText = tempDiv.textContent || tempDiv.innerText || '';
            
            // Nettoyer les espaces multiples et les caractères spéciaux
            cleanText = cleanText.replace(/\s+/g, ' ')
                               .replace(/&nbsp;/g, ' ')
                               .replace(/&amp;/g, '&')
                               .replace(/&lt;/g, '<')
                               .replace(/&gt;/g, '>')
                               .replace(/&quot;/g, '"')
                               .replace(/&#39;/g, "'")
                               .trim();
        }
        
    if (navigator.clipboard) {
            navigator.clipboard.writeText(cleanText).then(() => {
                showToast('Message copié dans le presse-papiers !', 'success');
            }).catch(() => {
                // Fallback si clipboard échoue
                fallbackCopy(cleanText);
        });
    } else {
            fallbackCopy(cleanText);
        }
    }
    
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Message copié dans le presse-papiers !', 'success');
    }
    
    // Basculer l'affichage du contenu
    function toggleContent(id) {
        const content = document.getElementById('content-' + id);
        const button = content.nextElementSibling;
        
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            button.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus';
        } else {
            content.classList.add('expanded');
            button.innerHTML = '<i class="fas fa-chevron-up"></i> Voir moins';
        }
    }
    
    // Modifier un modèle
    function editModele(id) {
        // Récupérer les données du modèle et les pré-remplir dans le formulaire
        const card = document.querySelector(`[onclick*="editModele(${id})"]`).closest('.model-card');
        const title = card.querySelector('.model-title').textContent;
        const content = card.querySelector('.model-content').textContent;
        const category = card.querySelector('.model-category').textContent.toLowerCase();
        
        document.getElementById('titre').value = title;
        quill.root.innerHTML = content;
        document.getElementById('categorie').value = category;
        
        // Faire défiler vers le formulaire
        document.querySelector('.creation-panel').scrollIntoView({ behavior: 'smooth' });
        
        showToast('Modèle chargé pour modification', 'info');
    }
    
    // Supprimer un message
    function deleteMessage(id) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible ! Le modèle sera définitivement supprimé.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer !',
            cancelButtonText: 'Annuler',
            background: '#fff',
            customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                content: 'swal2-content-custom'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Afficher un loader
                Swal.fire({
                    title: 'Suppression en cours...',
                    text: 'Veuillez patienter',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Créer et soumettre le formulaire
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    // Mettre à jour l'aperçu
    function updatePreview(html) {
        if (html.trim()) {
            $('#messagePreview').html(html);
    } else {
            $('#messagePreview').html('Votre message apparaîtra ici...');
        }
    }
    
    // Afficher une notification toast
    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        toast.toast({ delay: 3000 });
        toast.toast('show');
        
        setTimeout(() => toast.remove(), 3000);
}
</script>
</body>
</html>
