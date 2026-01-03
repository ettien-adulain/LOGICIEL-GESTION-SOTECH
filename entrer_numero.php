<?php
session_start();
require_once 'includes/header.php';
require_once 'db/connecting.php';
require_once 'fonction_traitement/fonction.php';

$error_message = '';
$success_message = '';
$id_entre_stock = null;

if (isset($_GET['id'])) {
    $id_entre_stock = $_GET['id'];
    
    // Vérifier si l'entrée en stock existe et n'est pas déjà terminée
    $stmt = $cnx->prepare("
        SELECT e.*, f.NomFournisseur 
        FROM entree_en_stock e
        JOIN fournisseur f ON e.IDFOURNISSEUR = f.IDFOURNISSEUR
        WHERE e.IDENTREE_STOCK = ? AND e.statut = 'EN_COURS'
    ");
    $stmt->execute([$id_entre_stock]);
    $entree_stock = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entree_stock) {
        setAlertMessage('error', "⚠️ L'entrée en stock n°$id_entre_stock n'existe pas ou a déjà été validée.");
        header('Location: entre_stock.php');
        exit();
    }

    // Récupérer les articles de cette entrée en stock
    $stmt = $cnx->prepare("
        SELECT esl.*, a.libelle 
        FROM entree_stock_ligne esl
        JOIN article a ON esl.IDARTICLE = a.IDARTICLE
        WHERE esl.IDENTREE_EN_STOCK = ?
    ");
    $stmt->execute([$id_entre_stock]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    setAlertMessage('error', '⚠️ Aucune entrée en stock spécifiée.');
    header('Location: entre_stock.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des numéros de série</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-group {
            margin-bottom: 1rem;
        }
        .numero-serie-input {
            margin-bottom: 0.5rem;
        }
        
        /* Styles pour les alertes de numéros de série */
        .numero-serie-input .form-control.duplicate {
            border-color: #dc3545;
            background-color: #f8d7da;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .numero-serie-input .form-control.valid {
            border-color: #28a745;
            background-color: #d4edda;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .duplicate-alert {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border-radius: 0.375rem;
            display: none;
        }
        
        .duplicate-alert.show {
            display: block;
            animation: shake 0.5s ease-in-out;
        }
        
        .duplicate-list {
            margin-top: 0.5rem;
            font-weight: bold;
        }
        
        .duplicate-list .badge {
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-barcode"></i> Saisie des numéros de série</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Entrée en stock n°<?php echo htmlspecialchars($id_entre_stock); ?> - 
                    Fournisseur: <?php echo htmlspecialchars($entree_stock['NomFournisseur']); ?>
                </div>

                <!-- Alerte pour les numéros de série dupliqués -->
                <div id="duplicateAlert" class="duplicate-alert">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Attention !</strong> Des numéros de série identiques ont été détectés :
                    <div id="duplicateList" class="duplicate-list"></div>
                </div>

                <form method="post" action="fonction_traitement/request.php" id="numeroSerieForm">
                    <input type="hidden" name="enregistrer_numero_serie" value="1">
                    <input type="hidden" name="id_entre_stock" value="<?php echo htmlspecialchars($id_entre_stock); ?>">

                    <?php foreach ($articles as $article): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4><?php echo htmlspecialchars($article['libelle']); ?></h4>
                                <p>Quantité: <?php echo htmlspecialchars($article['Quantite']); ?></p>
                            </div>
                            <div class="card-body">
                                <?php for ($i = 0; $i < $article['Quantite']; $i++): ?>
                                    <div class="form-group numero-serie-input">
                                        <label for="numero_serie_<?php echo $article['IDARTICLE']; ?>_<?php echo $i; ?>">
                                            Numéro de série <?php echo $i + 1; ?>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="numero_serie_<?php echo $article['IDARTICLE']; ?>_<?php echo $i; ?>"
                                               name="numero_serie[]" 
                                               required>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary submit-btn" id="submitBtn">
                            <i class="fas fa-save"></i> Enregistrer les numéros de série
                        </button>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#annulerModal">
                            <i class="fas fa-times"></i> Annuler l'entrée en stock
                        </button>
                      
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation d'annulation -->
    <div class="modal fade" id="annulerModal" tabindex="-1" aria-labelledby="annulerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="annulerModalLabel">Confirmer l'annulation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir annuler cette entrée en stock ?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Cette action est irréversible.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Non, retour</button>
                    <form method="post" action="fonction_traitement/request.php">
                        <input type="hidden" name="annuler_entree_stock" value="1">
                        <input type="hidden" name="id_entre_stock" value="<?php echo htmlspecialchars($id_entre_stock); ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Oui, annuler l'entrée en stock
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const numeroSerieInputs = document.querySelectorAll('input[name="numero_serie[]"]');
            const duplicateAlert = document.getElementById('duplicateAlert');
            const duplicateList = document.getElementById('duplicateList');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('numeroSerieForm');
            
            // Fonction pour vérifier les doublons
            function checkDuplicates() {
                const values = [];
                const duplicates = new Map();
                let hasDuplicates = false;
                
                // Collecter toutes les valeurs non vides
                numeroSerieInputs.forEach((input, index) => {
                    const value = input.value.trim().toUpperCase();
                    if (value) {
                        if (values.includes(value)) {
                            // C'est un doublon
                            if (!duplicates.has(value)) {
                                duplicates.set(value, []);
                            }
                            duplicates.get(value).push(index);
                            hasDuplicates = true;
                        } else {
                            values.push(value);
                        }
                    }
                });
                
                // Mettre à jour l'affichage des champs
                numeroSerieInputs.forEach((input, index) => {
                    const value = input.value.trim().toUpperCase();
                    input.classList.remove('duplicate', 'valid');
                    
                    if (value) {
                        if (duplicates.has(value)) {
                            input.classList.add('duplicate');
                        } else {
                            input.classList.add('valid');
                        }
                    }
                });
                
                // Afficher/masquer l'alerte
                if (hasDuplicates) {
                    let alertHtml = '';
                    duplicates.forEach((indices, value) => {
                        alertHtml += `<span class="badge bg-danger">${value}</span> `;
                    });
                    duplicateList.innerHTML = alertHtml;
                    duplicateAlert.classList.add('show');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Corriger les doublons avant de continuer';
                } else {
                    duplicateAlert.classList.remove('show');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer les numéros de série';
                }
                
                return !hasDuplicates;
            }
            
            // Ajouter les événements sur tous les champs
            numeroSerieInputs.forEach(input => {
                input.addEventListener('input', checkDuplicates);
                input.addEventListener('blur', checkDuplicates);
                input.addEventListener('paste', function() {
                    setTimeout(checkDuplicates, 100); // Attendre que le paste soit terminé
                });
            });
            
            // Empêcher la soumission du formulaire s'il y a des doublons
            form.addEventListener('submit', function(e) {
                if (!checkDuplicates()) {
                    e.preventDefault();
                    
                    // Afficher une alerte Bootstrap
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i> <strong>Erreur !</strong> 
                        Vous ne pouvez pas enregistrer des numéros de série identiques. 
                        Veuillez corriger les doublons avant de continuer.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    // Insérer l'alerte au début du formulaire
                    form.insertBefore(alertDiv, form.firstChild);
                    
                    // Faire défiler vers le haut pour voir l'alerte
                    alertDiv.scrollIntoView({ behavior: 'smooth' });
                    
                    return false;
                }
            });
            
            // Vérification initiale
            checkDuplicates();
        });
    </script>
</body>
</html>
