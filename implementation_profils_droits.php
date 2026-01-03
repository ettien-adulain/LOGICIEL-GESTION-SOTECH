<?php
require_once 'db/connecting.php';
require_once 'fonction_traitement/fonction.php';
check_access();

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('location: index.php');
    exit();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'appliquer_profil':
                $profil = $_POST['profil'];
                $user_id = $_POST['user_id'] ?? null;
                
                // Définir les profils
                $profils = [
                    'vendeur' => [
                        'name' => 'Vendeur',
                        'description' => 'Peut vendre, consulter les articles et clients',
                        'droits' => [
                            ['module' => 'produits', 'action' => 'voir'],
                            ['module' => 'ventes', 'action' => 'voir'],
                            ['module' => 'ventes', 'action' => 'ajouter'],
                            ['module' => 'clients', 'action' => 'voir'],
                            ['module' => 'clients', 'action' => 'ajouter'],
                            ['module' => 'caisse', 'action' => 'voir'],
                            ['module' => 'caisse', 'action' => 'ajouter']
                        ]
                    ],
                    'caissier' => [
                        'name' => 'Caissier',
                        'description' => 'Peut encaisser et gérer la caisse',
                        'droits' => [
                            ['module' => 'caisse', 'action' => 'voir'],
                            ['module' => 'caisse', 'action' => 'ajouter'],
                            ['module' => 'caisse', 'action' => 'modifier'],
                            ['module' => 'ventes', 'action' => 'voir'],
                            ['module' => 'tresorerie', 'action' => 'voir'],
                            ['module' => 'produits', 'action' => 'voir'],
                            ['module' => 'clients', 'action' => 'voir']
                        ]
                    ],
                    'stock' => [
                        'name' => 'Gestionnaire Stock',
                        'description' => 'Gère les entrées/sorties et inventaires',
                        'droits' => [
                            ['module' => 'stock', 'action' => 'voir'],
                            ['module' => 'stock', 'action' => 'ajouter'],
                            ['module' => 'stock', 'action' => 'corriger'],
                            ['module' => 'produits', 'action' => 'voir'],
                            ['module' => 'produits', 'action' => 'modifier'],
                            ['module' => 'fournisseurs', 'action' => 'voir'],
                            ['module' => 'inventaire', 'action' => 'voir'],
                            ['module' => 'inventaire', 'action' => 'ajouter']
                        ]
                    ],
                    'manager' => [
                        'name' => 'Manager',
                        'description' => 'Accès complet sauf administration',
                        'droits' => [
                            ['module' => 'produits', 'action' => 'voir'],
                            ['module' => 'produits', 'action' => 'ajouter'],
                            ['module' => 'produits', 'action' => 'modifier'],
                            ['module' => 'stock', 'action' => 'voir'],
                            ['module' => 'stock', 'action' => 'ajouter'],
                            ['module' => 'stock', 'action' => 'corriger'],
                            ['module' => 'ventes', 'action' => 'voir'],
                            ['module' => 'ventes', 'action' => 'ajouter'],
                            ['module' => 'ventes', 'action' => 'modifier'],
                            ['module' => 'clients', 'action' => 'voir'],
                            ['module' => 'clients', 'action' => 'ajouter'],
                            ['module' => 'clients', 'action' => 'modifier'],
                            ['module' => 'fournisseurs', 'action' => 'voir'],
                            ['module' => 'fournisseurs', 'action' => 'ajouter'],
                            ['module' => 'fournisseurs', 'action' => 'modifier'],
                            ['module' => 'tresorerie', 'action' => 'voir'],
                            ['module' => 'tresorerie', 'action' => 'ajouter'],
                            ['module' => 'rapports', 'action' => 'voir'],
                            ['module' => 'rapports', 'action' => 'exporter'],
                            ['module' => 'caisse', 'action' => 'voir'],
                            ['module' => 'caisse', 'action' => 'ajouter'],
                            ['module' => 'caisse', 'action' => 'modifier']
                        ]
                    ],
                    'admin' => [
                        'name' => 'Administrateur',
                        'description' => 'Accès complet à tout le système',
                        'droits' => 'ALL'
                    ]
                ];
                
                if (!isset($profils[$profil])) {
                    throw new Exception("Profil inconnu : $profil");
                }
                
                $cnx->beginTransaction();
                
                try {
                    if ($user_id) {
                        // Appliquer à un utilisateur spécifique
                        $utilisateurs = [$user_id];
                    } else {
                        // Appliquer à tous les utilisateurs actifs
                        $stmt = $cnx->prepare("SELECT IDUTILISATEUR FROM utilisateur WHERE actif = 'oui' AND role != 'admin'");
                        $stmt->execute();
                        $utilisateurs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    
                    $droits_appliques = 0;
                    
                    foreach ($utilisateurs as $uid) {
                        // Supprimer les droits existants
                        $stmt = $cnx->prepare("DELETE FROM droits_acces WHERE id_utilisateur = ?");
                        $stmt->execute([$uid]);
                        
                        if ($profils[$profil]['droits'] === 'ALL') {
                            // Pour admin, donner tous les droits
                            $stmt = $cnx->prepare("INSERT INTO droits_acces (id_utilisateur, module, action, autorise, date_modif) VALUES (?, ?, ?, 1, NOW())");
                            
                            $tous_modules = ['produits', 'stock', 'ventes', 'clients', 'fournisseurs', 'tresorerie', 'rapports', 'utilisateurs', 'caisse', 'facturation', 'autres'];
                            $toutes_actions = ['voir', 'ajouter', 'modifier', 'supprimer', 'imprimer', 'exporter', 'corriger', 'envoyer'];
                            
                            foreach ($tous_modules as $module) {
                                foreach ($toutes_actions as $action) {
                                    $stmt->execute([$uid, $module, $action]);
                                    $droits_appliques++;
                                }
                            }
                        } else {
                            // Appliquer les droits spécifiques du profil
                            $stmt = $cnx->prepare("INSERT INTO droits_acces (id_utilisateur, module, action, autorise, date_modif) VALUES (?, ?, ?, 1, NOW())");
                            
                            foreach ($profils[$profil]['droits'] as $droit) {
                                $stmt->execute([$uid, $droit['module'], $droit['action']]);
                                $droits_appliques++;
                            }
                        }
                    }
                    
                    $cnx->commit();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Profil '{$profils[$profil]['name']}' appliqué avec succès à " . count($utilisateurs) . " utilisateur(s). $droits_appliques droits configurés."
                    ]);
                    
                } catch (Exception $e) {
                    $cnx->rollBack();
                    throw $e;
                }
                break;
                
            case 'voir_droits_utilisateur':
                $user_id = $_POST['user_id'];
                
                $stmt = $cnx->prepare("
                    SELECT d.module, d.action, d.autorise, d.date_modif
                    FROM droits_acces d
                    WHERE d.id_utilisateur = ?
                    ORDER BY d.module, d.action
                ");
                $stmt->execute([$user_id]);
                $droits = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'droits' => $droits]);
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
$stmt = $cnx->prepare("SELECT IDUTILISATEUR, NomPrenom, Identifiant, role FROM utilisateur WHERE actif = 'oui' ORDER BY NomPrenom");
$stmt->execute();
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Implémentation des Profils de Droits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .profile-card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .profile-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px 15px 0 0; }
        .rights-preview { max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-4">
                    <i class="fas fa-user-shield"></i> 
                    Implémentation des Profils de Droits d'Accès
                </h1>
            </div>
        </div>

        <!-- Sélection d'utilisateur -->
        <div class="profile-card">
            <div class="profile-header">
                <h4><i class="fas fa-users"></i> Sélection d'Utilisateur</h4>
            </div>
            <div class="p-4">
                <div class="row">
                    <div class="col-md-6">
                        <label for="userSelect" class="form-label">Utilisateur :</label>
                        <select id="userSelect" class="form-select">
                            <option value="">-- Sélectionner un utilisateur --</option>
                            <?php foreach ($utilisateurs as $user): ?>
                                <option value="<?php echo $user['IDUTILISATEUR']; ?>">
                                    <?php echo htmlspecialchars($user['NomPrenom']); ?> 
                                    (<?php echo htmlspecialchars($user['Identifiant']); ?>)
                                    - <?php echo $user['role'] ?? 'user'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button id="voirDroitsBtn" class="btn btn-info" disabled>
                                <i class="fas fa-eye"></i> Voir les droits actuels
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profils disponibles -->
        <div class="row">
            <div class="col-md-6">
                <div class="profile-card">
                    <div class="profile-header">
                        <h4><i class="fas fa-user-tag"></i> Profils Prédéfinis</h4>
                    </div>
                    <div class="p-4">
                        <div class="mb-3">
                            <h6><i class="fas fa-store"></i> Vendeur</h6>
                            <p class="text-muted">Peut vendre, consulter les articles et clients</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="appliquerProfil('vendeur')">
                                <i class="fas fa-magic"></i> Appliquer
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <h6><i class="fas fa-cash-register"></i> Caissier</h6>
                            <p class="text-muted">Peut encaisser et gérer la caisse</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="appliquerProfil('caissier')">
                                <i class="fas fa-magic"></i> Appliquer
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <h6><i class="fas fa-boxes"></i> Gestionnaire Stock</h6>
                            <p class="text-muted">Gère les entrées/sorties et inventaires</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="appliquerProfil('stock')">
                                <i class="fas fa-magic"></i> Appliquer
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <h6><i class="fas fa-user-tie"></i> Manager</h6>
                            <p class="text-muted">Accès complet sauf administration</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="appliquerProfil('manager')">
                                <i class="fas fa-magic"></i> Appliquer
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <h6><i class="fas fa-crown"></i> Administrateur</h6>
                            <p class="text-muted">Accès complet à tout le système</p>
                            <button class="btn btn-outline-danger btn-sm" onclick="appliquerProfil('admin')">
                                <i class="fas fa-magic"></i> Appliquer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="profile-card">
                    <div class="profile-header">
                        <h4><i class="fas fa-eye"></i> Droits Actuels</h4>
                    </div>
                    <div class="p-4">
                        <div id="droitsActuels">
                            <p class="text-muted">Sélectionnez un utilisateur pour voir ses droits actuels</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions globales -->
        <div class="profile-card">
            <div class="profile-header">
                <h4><i class="fas fa-cogs"></i> Actions Globales</h4>
            </div>
            <div class="p-4">
                <div class="row">
                    <div class="col-md-4">
                        <button class="btn btn-warning w-100" onclick="appliquerProfilGlobal('vendeur')">
                            <i class="fas fa-users"></i> Appliquer profil Vendeur à tous
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-info w-100" onclick="appliquerProfilGlobal('manager')">
                            <i class="fas fa-users"></i> Appliquer profil Manager à tous
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-success w-100" onclick="nettoyerDroits()">
                            <i class="fas fa-broom"></i> Nettoyer tous les droits
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div id="messages"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentUserId = null;

        // Gestionnaire de sélection d'utilisateur
        document.getElementById('userSelect').addEventListener('change', function() {
            currentUserId = this.value;
            document.getElementById('voirDroitsBtn').disabled = !currentUserId;
            
            if (currentUserId) {
                voirDroitsUtilisateur(currentUserId);
            } else {
                document.getElementById('droitsActuels').innerHTML = 
                    '<p class="text-muted">Sélectionnez un utilisateur pour voir ses droits actuels</p>';
            }
        });

        // Voir les droits d'un utilisateur
        document.getElementById('voirDroitsBtn').addEventListener('click', function() {
            if (currentUserId) {
                voirDroitsUtilisateur(currentUserId);
            }
        });

        async function voirDroitsUtilisateur(userId) {
            try {
                const response = await fetch('implementation_profils_droits.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=voir_droits_utilisateur&user_id=${userId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    afficherDroits(data.droits);
                } else {
                    showMessage('Erreur lors de la récupération des droits: ' + data.message, 'danger');
                }
            } catch (error) {
                showMessage('Erreur de connexion', 'danger');
            }
        }

        function afficherDroits(droits) {
            const container = document.getElementById('droitsActuels');
            
            if (droits.length === 0) {
                container.innerHTML = '<p class="text-warning">Aucun droit configuré</p>';
                return;
            }
            
            let html = '<div class="rights-preview">';
            let modules = {};
            
            // Grouper par module
            droits.forEach(droit => {
                if (!modules[droit.module]) {
                    modules[droit.module] = [];
                }
                modules[droit.module].push(droit.action);
            });
            
            // Afficher par module
            Object.keys(modules).forEach(module => {
                html += `<div class="mb-2">
                    <strong>${module}</strong>: ${modules[module].join(', ')}
                </div>`;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        async function appliquerProfil(profil) {
            if (!currentUserId) {
                showMessage('Veuillez d\'abord sélectionner un utilisateur', 'warning');
                return;
            }
            
            if (confirm(`Voulez-vous appliquer le profil '${profil}' à cet utilisateur ?`)) {
                await appliquerProfilAction(profil, currentUserId);
            }
        }

        async function appliquerProfilGlobal(profil) {
            if (confirm(`Voulez-vous appliquer le profil '${profil}' à TOUS les utilisateurs actifs ?`)) {
                await appliquerProfilAction(profil);
            }
        }

        async function appliquerProfilAction(profil, userId = null) {
            try {
                const body = `action=appliquer_profil&profil=${profil}`;
                if (userId) {
                    body += `&user_id=${userId}`;
                }
                
                const response = await fetch('implementation_profils_droits.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    if (currentUserId) {
                        voirDroitsUtilisateur(currentUserId);
                    }
                } else {
                    showMessage('Erreur: ' + data.message, 'danger');
                }
            } catch (error) {
                showMessage('Erreur de connexion', 'danger');
            }
        }

        async function nettoyerDroits() {
            if (confirm('Voulez-vous supprimer TOUS les droits de TOUS les utilisateurs ?')) {
                try {
                    const response = await fetch('implementation_profils_droits.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=nettoyer_droits'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showMessage('Tous les droits ont été supprimés', 'success');
                        if (currentUserId) {
                            voirDroitsUtilisateur(currentUserId);
                        }
                    } else {
                        showMessage('Erreur: ' + data.message, 'danger');
                    }
                } catch (error) {
                    showMessage('Erreur de connexion', 'danger');
                }
            }
        }

        function showMessage(message, type) {
            const container = document.getElementById('messages');
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