<?php
/**
 * INTÉGRATION MANUELLE DE LA JOURNALISATION
 * Script pour ajouter manuellement la journalisation dans les fichiers critiques
 */

require_once 'fonction_traitement/fonction.php';
require_once 'integrate_journal_global.php';

// Vérification des droits d'accès
check_access();

try {
    include('db/connecting.php');
    
    echo "<h1>🔧 Intégration Manuelle de la Journalisation</h1>";
    
    // Liste des fichiers à traiter avec leurs actions
    $fichiers_actions = [
        'articles.php' => [
            'actions' => ['creation', 'modification', 'suppression'],
            'description' => 'Gestion des articles'
        ],
        'client_ajout_rapide.php' => [
            'actions' => ['creation_client'],
            'description' => 'Ajout rapide de clients'
        ],
        'commande.php' => [
            'actions' => ['creation_commande', 'modification_commande'],
            'description' => 'Gestion des commandes'
        ],
        'caisse.php' => [
            'actions' => ['vente_caisse'],
            'description' => 'Ventes en caisse'
        ],
        'vente.php' => [
            'actions' => ['vente_comptant'],
            'description' => 'Ventes comptant'
        ],
        'vente_credit.php' => [
            'actions' => ['vente_credit'],
            'description' => 'Ventes à crédit'
        ],
        'entre_stock.php' => [
            'actions' => ['entree_stock'],
            'description' => 'Entrées en stock'
        ],
        'correction_stock.php' => [
            'actions' => ['correction_stock'],
            'description' => 'Corrections de stock'
        ]
    ];
    
    $action = $_GET['action'] ?? 'dashboard';
    $message = '';
    
    switch($action) {
        case 'test_journal':
            // Test de journalisation
            $result = journaliserConnexion($cnx, 'TEST', 'Test d\'intégration manuelle');
            if ($result) {
                $message = "✅ Test de journalisation réussi";
            } else {
                $message = "❌ Test de journalisation échoué";
            }
            break;
            
        case 'add_to_file':
            $fichier = $_POST['fichier'] ?? '';
            $type_action = $_POST['type_action'] ?? '';
            
            if ($fichier && $type_action) {
                // Ajouter la journalisation au fichier
                $contenu = file_get_contents($fichier);
                
                switch($type_action) {
                    case 'creation':
                        $code_journal = "\n// Journalisation de la création\n" .
                                      "journaliserCreation(\$cnx, 'article', \$id, 'Article créé');\n";
                        break;
                    case 'modification':
                        $code_journal = "\n// Journalisation de la modification\n" .
                                      "journaliserModification(\$cnx, 'article', \$id, ['libelle'], \$ancien, \$nouveau);\n";
                        break;
                    case 'suppression':
                        $code_journal = "\n// Journalisation de la suppression\n" .
                                      "journaliserSuppression(\$cnx, 'article', \$id, 'Article supprimé');\n";
                        break;
                }
                
                // Ajouter le code après la dernière action
                $contenu .= $code_journal;
                file_put_contents($fichier, $contenu);
                
                $message = "✅ Journalisation ajoutée à $fichier pour $type_action";
            }
            break;
    }
    
    // Vérifier les fichiers existants
    $fichiers_existants = [];
    foreach ($fichiers_actions as $fichier => $info) {
        if (file_exists($fichier)) {
            $fichiers_existants[$fichier] = $info;
        }
    }
    
} catch (Exception $e) {
    $error = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intégration Manuelle Journalisation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .integration-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .integration-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .file-item { background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 10px 0; }
        .code-example { background: #f1f3f4; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="integration-container">
        <!-- Header -->
        <div class="integration-card">
            <h1><i class="fas fa-tools"></i> Intégration Manuelle de la Journalisation</h1>
            <p class="mb-0">Ajouter manuellement la journalisation dans les fichiers critiques du système</p>
        </div>

        <!-- Messages -->
        <?php if (isset($message)): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <i class="fas fa-info-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Test de journalisation -->
        <div class="integration-card">
            <h4><i class="fas fa-vial"></i> Test de Journalisation</h4>
            <p>Testez si la journalisation fonctionne correctement.</p>
            <a href="?action=test_journal" class="btn btn-primary">
                <i class="fas fa-play"></i> Tester la Journalisation
            </a>
        </div>

        <!-- Fichiers à intégrer -->
        <div class="integration-card">
            <h4><i class="fas fa-file-code"></i> Fichiers à Intégrer</h4>
            <p>Liste des fichiers qui nécessitent une intégration manuelle de la journalisation.</p>
            
            <?php foreach ($fichiers_existants as $fichier => $info): ?>
                <div class="file-item">
                    <h6><i class="fas fa-file"></i> <?php echo $fichier; ?></h6>
                    <p class="text-muted"><?php echo $info['description']; ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Actions à journaliser:</strong>
                            <ul>
                                <?php foreach ($info['actions'] as $action): ?>
                                    <li><?php echo ucfirst($action); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <strong>Code à ajouter:</strong>
                            <div class="code-example">
                                // Après insertion_element():<br>
                                journaliserCreation($cnx, 'article', $id, 'Article créé');<br><br>
                                
                                // Après modifier_element():<br>
                                journaliserModification($cnx, 'article', $id, ['libelle'], $ancien, $nouveau);<br><br>
                                
                                // Après supprimer_element():<br>
                                journaliserSuppression($cnx, 'article', $id, 'Article supprimé');
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="<?php echo $fichier; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fas fa-edit"></i> Modifier le fichier
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Guide d'intégration -->
        <div class="integration-card">
            <h4><i class="fas fa-book"></i> Guide d'Intégration</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>1. Création d'éléments</h5>
                    <div class="code-example">
                        // Après insertion_element()<br>
                        $id = $cnx->lastInsertId();<br>
                        journaliserCreation($cnx, 'article', $id, 'Article créé');
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5>2. Modification d'éléments</h5>
                    <div class="code-example">
                        // Avant modifier_element()<br>
                        $ancien = verifier_element(...);<br>
                        // Après modifier_element()<br>
                        journaliserModification($cnx, 'article', $id, ['libelle'], $ancien, $nouveau);
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <h5>3. Suppression d'éléments</h5>
                    <div class="code-example">
                        // Après supprimer_element()<br>
                        journaliserSuppression($cnx, 'article', $id, 'Article supprimé');
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5>4. Actions spéciales</h5>
                    <div class="code-example">
                        // Ventes<br>
                        journaliserVente($cnx, $idVente, 'CREATION', 'Vente créée');<br><br>
                        // Stock<br>
                        journaliserStock($cnx, $idStock, 'ENTREE', 'Entrée en stock');
                    </div>
                </div>
            </div>
        </div>

        <!-- Vérification -->
        <div class="integration-card">
            <h4><i class="fas fa-check-circle"></i> Vérification</h4>
            <p>Vérifiez que l'intégration fonctionne correctement.</p>
            
            <div class="row">
                <div class="col-md-4">
                    <a href="test_journal_unifie.php" class="btn btn-info w-100">
                        <i class="fas fa-vial"></i> Tests Système
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="journal.php" class="btn btn-success w-100">
                        <i class="fas fa-list"></i> Voir Journal
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="monitoring_journal.php" class="btn btn-warning w-100">
                        <i class="fas fa-chart-line"></i> Monitoring
                    </a>
                </div>
            </div>
        </div>

        <!-- Liens utiles -->
        <div class="integration-card">
            <h4><i class="fas fa-link"></i> Liens Utiles</h4>
            <div class="row">
                <div class="col-md-3">
                    <a href="admin_journal.php" class="btn btn-primary w-100">
                        <i class="fas fa-cogs"></i> Administration
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="backup_journal.php" class="btn btn-success w-100">
                        <i class="fas fa-save"></i> Sauvegarde
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="config_journal.php" class="btn btn-info w-100">
                        <i class="fas fa-sliders-h"></i> Configuration
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
