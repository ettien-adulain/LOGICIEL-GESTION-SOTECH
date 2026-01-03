<?php
include('db/connecting.php');
require_once 'fonction_traitement/fonction.php';
check_access();
// Gestion de la soumission du formulaire
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creation_sav'])) {
    try {
        $numero_serie = trim($_POST['numero_serie']);
        $description_panne = trim($_POST['description_panne']);
        $etat_reception = trim($_POST['etat_reception']);
        $date_depot = $_POST['date_depot'];
        $id_client = !empty($_POST['id_client']) ? intval($_POST['id_client']) : null;
        $cout_estime = !empty($_POST['cout_estime']) ? floatval($_POST['cout_estime']) : null;
        $date_previsionnelle = !empty($_POST['date_previsionnelle']) ? $_POST['date_previsionnelle'] : null;
        $cree_par = $_SESSION['nom_utilisateur'];
        $date_creation = date('Y-m-d H:i:s');

        // Insertion sans numéro_sav
        $stmt = $cnx->prepare("INSERT INTO sav_dossier (id_client, numero_serie, description_panne, etat_reception, date_depot, date_previsionnelle, cout_estime, statut, cree_par, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, ?)");
        $stmt->execute([
            $id_client,
            $numero_serie,
            $description_panne,
            $etat_reception,
            $date_depot,
            $date_previsionnelle,
            $cout_estime,
            $cree_par,
            $date_creation
        ]);
        $id_sav = $cnx->lastInsertId();

        // Générer le numéro SAV à partir de l'ID
        $numero_sav = 'SAV-' . date('Y') . '-' . str_pad($id_sav, 6, '0', STR_PAD_LEFT);

        // Mettre à jour le dossier avec le vrai numéro
        $stmt = $cnx->prepare("UPDATE sav_dossier SET numero_sav = ? WHERE id_sav = ?");
        $stmt->execute([$numero_sav, $id_sav]);
        
        // --- JOURNALISATION : Création dossier SAV ---
        // Récupérer le nom du client
        $nom_client = 'Non renseigné';
        if ($id_client) {
            $stmt = $cnx->prepare("SELECT NomPrenomClient, Telephone FROM client WHERE IDCLIENT = ?");
            $stmt->execute([$id_client]);
            $client_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client_info) {
                $nom_client = $client_info['NomPrenomClient'];
                if (!empty($client_info['Telephone'])) {
                    $nom_client .= ' (' . $client_info['Telephone'] . ')';
                }
            }
        }
        
        $description_creation = sprintf(
            "Création dossier SAV N°%s - Client: %s - Série: %s - Panne: %s - Coût: %.2f FCFA",
            $numero_sav,
            $nom_client,
            $numero_serie,
            substr($description_panne, 0, 50) . (strlen($description_panne) > 50 ? '...' : ''),
            $cout_estime ?? 0
        );
        
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'CREATION_DOSSIER_SAV',
                'SAV',
                'sav.php',
                $description_creation,
                [
                    'id_sav' => $id_sav,
                    'numero_sav' => $numero_sav,
                    'id_client' => $id_client,
                    'nom_client' => $nom_client,
                    'numero_serie' => $numero_serie,
                    'description_panne' => $description_panne,
                    'etat_reception' => $etat_reception,
                    'cout_estime' => $cout_estime,
                    'date_previsionnelle' => $date_previsionnelle,
                    'utilisateur' => $cree_par,
                    'materiaux_saisis' => $materiaux_saisis
                ],
                [
                    'action' => 'creation_dossier_sav',
                    'dossier_cree' => true,
                    'materiaux_optionnels' => true,
                    'statut_initial' => 'en_attente'
                ],
                'HIGH',
                'SUCCESS',
                null
            );
        }
        // --- FIN JOURNALISATION ---

        // Gestion des achats de matériaux (pour justifier le coût estimatif)
        $materiaux_saisis = false;
        if (isset($_POST['materiaux']) && is_array($_POST['materiaux'])) {
            foreach ($_POST['materiaux'] as $index => $materiau) {
                if (!empty($materiau['designation']) && !empty($materiau['cout'])) {
                    $designation = trim($materiau['designation']);
                    $cout = floatval($materiau['cout']);
                    $quantite = !empty($materiau['quantite']) ? intval($materiau['quantite']) : 1;
                    $cout_total = $cout * $quantite;
                    
                    $stmt = $cnx->prepare("INSERT INTO sav_piece (id_sav, designation, prix_unitaire, quantite, cout_total, date_achat) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_sav, $designation, $cout, $quantite, $cout_total, $date_creation]);
                    $materiaux_saisis = true;
                }
            }
        }
        
        // CORRECTION : Les matériaux sont optionnels (pour justifier le coût estimatif)
        // Si aucun matériau n'est saisi, le coût estimatif sera considéré comme main d'œuvre uniquement
        if (!$materiaux_saisis) {
            // Optionnel : enregistrer une entrée "Main d'œuvre" pour la traçabilité
           
        }

        $success = "Dossier SAV créé avec succès (N° $numero_sav) !";
    } catch (Exception $e) {
        // --- JOURNALISATION : Erreur création dossier SAV ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_CREATION_DOSSIER_SAV',
                'SAV',
                'sav.php',
                'Erreur lors de la création du dossier SAV : ' . $e->getMessage(),
                [
                    'numero_serie' => $_POST['numero_serie'] ?? 'N/A',
                    'id_client' => $_POST['id_client'] ?? null,
                    'description_panne' => $_POST['description_panne'] ?? 'N/A',
                    'cout_estime' => $_POST['cout_estime'] ?? null,
                    'utilisateur' => $_SESSION['nom_utilisateur'] ?? 'N/A',
                    'erreur' => $e->getMessage()
                ],
                null,
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        $error = "Erreur lors de la création du dossier SAV : " . $e->getMessage();
    }
}

// Gestion de la suppression de dossier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_dossier_liste'])) {
    try {
        $id_sav = intval($_POST['id_sav']);
        
        // Supprimer en cascade : paiements, pièces, historique, puis dossier
        $cnx->beginTransaction();
        $stmt = $cnx->prepare("DELETE FROM sav_paiement WHERE id_sav = ?");
        $stmt->execute([$id_sav]);
        $stmt = $cnx->prepare("DELETE FROM sav_piece WHERE id_sav = ?");
        $stmt->execute([$id_sav]);
        $stmt = $cnx->prepare("DELETE FROM sav_historique WHERE id_sav = ?");
        $stmt->execute([$id_sav]);
        $stmt = $cnx->prepare("DELETE FROM sav_dossier WHERE id_sav = ?");
        $stmt->execute([$id_sav]);
        
        // --- JOURNALISATION : Suppression dossier SAV ---
        // Récupérer les informations du dossier avant suppression
        $stmt = $cnx->prepare("
            SELECT sd.numero_sav, sd.numero_serie, sd.id_client, c.NomPrenomClient, c.Telephone 
            FROM sav_dossier sd 
            LEFT JOIN client c ON sd.id_client = c.IDCLIENT 
            WHERE sd.id_sav = ?
        ");
        $stmt->execute([$id_sav]);
        $dossier_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Construire le nom du client
        $nom_client_suppression = 'Non renseigné';
        if ($dossier_info['id_client'] && $dossier_info['NomPrenomClient']) {
            $nom_client_suppression = $dossier_info['NomPrenomClient'];
            if (!empty($dossier_info['Telephone'])) {
                $nom_client_suppression .= ' (' . $dossier_info['Telephone'] . ')';
            }
        }
        
        $description_suppression = sprintf(
            "Suppression dossier SAV N°%s - Série: %s - Client: %s - Suppression en cascade",
            $dossier_info['numero_sav'] ?? 'N/A',
            $dossier_info['numero_serie'] ?? 'N/A',
            $nom_client_suppression
        );
        
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'SUPPRESSION_DOSSIER_SAV',
                'SAV',
                'sav.php',
                $description_suppression,
                [
                    'id_sav' => $id_sav,
                    'numero_sav' => $dossier_info['numero_sav'] ?? 'N/A',
                    'numero_serie' => $dossier_info['numero_serie'] ?? 'N/A',
                    'id_client' => $dossier_info['id_client'],
                    'nom_client' => $nom_client_suppression,
                    'utilisateur' => $_SESSION['nom_utilisateur'] ?? 'N/A',
                    'suppression_cascade' => true
                ],
                [
                    'action' => 'suppression_dossier_sav',
                    'paiements_supprimes' => true,
                    'pieces_supprimees' => true,
                    'historique_supprime' => true,
                    'dossier_supprime' => true
                ],
                'CRITICAL',
                'SUCCESS',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        $cnx->commit();
        
        $success = "Dossier SAV supprimé avec succès !";
    } catch (Exception $e) {
        $cnx->rollBack();
        
        // --- JOURNALISATION : Erreur suppression dossier SAV ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_SUPPRESSION_DOSSIER_SAV',
                'SAV',
                'sav.php',
                'Erreur lors de la suppression du dossier SAV : ' . $e->getMessage(),
                [
                    'id_sav' => $id_sav,
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
        
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Message de succès pour suppression depuis suivi
if (isset($_GET['success']) && $_GET['success'] === 'suppression') {
    $success = "Dossier SAV supprimé avec succès !";
}

// Récupérer la liste des clients
$clients = $cnx->query("SELECT IDCLIENT, NomPrenomClient, Telephone FROM client ORDER BY NomPrenomClient")->fetchAll(PDO::FETCH_ASSOC);

// --- LISTE SAV ---
// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Recherche rapide
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Filtres
$statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$id_client = isset($_GET['id_client']) ? intval($_GET['id_client']) : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

$where = [];
$params = [];
if ($search) {
    $where[] = "(sd.numero_sav LIKE ? OR c.NomPrenomClient LIKE ? OR sd.numero_serie LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statut) {
    $where[] = "sd.statut = ?";
    $params[] = $statut;
}
if ($id_client) {
    $where[] = "sd.id_client = ?";
    $params[] = $id_client;
}
if ($date_debut) {
    $where[] = "sd.date_depot >= ?";
    $params[] = $date_debut . ' 00:00:00';
}
if ($date_fin) {
    $where[] = "sd.date_depot <= ?";
    $params[] = $date_fin . ' 23:59:59';
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Compter le total pour la pagination
$count_stmt = $cnx->prepare("SELECT COUNT(*) FROM sav_dossier sd LEFT JOIN client c ON sd.id_client = c.IDCLIENT $where_clause");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// Récupérer les dossiers paginés
$stmt = $cnx->prepare("SELECT sd.*, c.NomPrenomClient, c.Telephone FROM sav_dossier sd LEFT JOIN client c ON sd.id_client = c.IDCLIENT $where_clause ORDER BY sd.date_depot DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAV - Dépôt & Liste</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 1200px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px #0001; padding: 2rem; }
        h2 { color: #dc3545; font-weight: bold; }
        .form-label { font-weight: 600; }
        .badge { font-size: 1em; }
        .sticky-header th { position: sticky; top: 0; background: #fff; z-index: 2; }
        .table-responsive { max-height: 60vh; overflow-y: auto; }
    </style>
</head>
<body>
    
<?php include('includes/user_indicator.php'); ?>
<?php include('includes/navigation_buttons.php'); ?>
<div class="container">
    <h2 class="mb-4"><i class="fas fa-tools me-2"></i>Dépôt Service Après-Vente (SAV)</h2>
    <?php if ($success): ?>
        <div class="alert alert-success"> <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?> </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"> <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?> </div>
    <?php endif; ?>
    <form method="post" class="row g-3 mb-5">
        <input type="hidden" name="creation_sav" value="1">
        <div class="col-md-6">
            <label class="form-label">Numéro de série ou référence</label>
            <input type="text" name="numero_serie" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Client</label>
            <div class="input-group">
                <select name="id_client" id="id_client" class="form-select">
                    <option value="">-- Non renseigné --</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['IDCLIENT'] ?>" data-tel="<?= htmlspecialchars($client['Telephone'] ?? '') ?>">
                            <?= htmlspecialchars($client['NomPrenomClient']) ?>
                            <?= !empty($client['Telephone']) ? ' (' . htmlspecialchars($client['Telephone']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-outline-primary" id="btn-nouveau-client" title="Créer un nouveau client"><i class="fas fa-plus"></i></button>
            </div>
            <div id="form-nouveau-client" class="mt-2" style="display:none;">
                <div class="card card-body p-2">
                    <div class="row g-2 align-items-end">
                        <div class="col">
                            <input type="text" id="nouveau_nom" class="form-control form-control-sm" placeholder="Nom et prénom">
                        </div>
                        <div class="col">
                            <input type="text" id="nouveau_tel" class="form-control form-control-sm" placeholder="Téléphone">
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-success btn-sm" id="valider-nouveau-client"><i class="fas fa-check"></i> Ajouter</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="annuler-nouveau-client"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div id="msg-nouveau-client" class="small mt-1"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Téléphone client</label>
            <input type="text" name="telephone_client" id="telephone_client" class="form-control" placeholder="Sera rempli automatiquement ou saisir manuellement">
        </div>
        <div class="col-md-12">
            <label class="form-label">Description de la panne</label>
            <textarea name="description_panne" class="form-control" required></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">État général du produit</label>
            <input type="text" name="etat_reception" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Date de dépôt</label>
            <input type="datetime-local" name="date_depot" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Coût estimatif (F.CFA)</label>
            <input type="number" name="cout_estime" id="cout_estime" class="form-control" step="0.01" required>
            <small class="text-muted">Saisi par le responsable en tenant compte des matériaux</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Délai prévisionnel</label>
            <input type="date" name="date_previsionnelle" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Achats de matériaux <span class="text-muted">(optionnel)</span></label>
            <div id="materiaux-container">
                <div class="row g-2 mb-2 materiau-row">
                    <div class="col-md-4">
                        <input type="text" name="materiaux[0][designation]" class="form-control" placeholder="Désignation du matériau">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="materiaux[0][cout]" class="form-control cout-unitaire" placeholder="Coût unitaire" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="materiaux[0][quantite]" class="form-control quantite" placeholder="Quantité" value="1" min="1">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control cout-total" placeholder="Total" readonly>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm supprimer-materiau" style="display:none;"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="ajouter-materiau">
                <i class="fas fa-plus"></i> Ajouter un matériau
            </button>
           
            <div class="mt-2">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Information :</strong> Les matériaux sont optionnels. Saisissez uniquement les achats réels de matériaux.
                    Si aucun matériau n'est nécessaire (alors il s'agit d'une main d'œuvre), laissez vide.
                </div>
            </div>
        </div>
        <div class="col-12 text-end mt-3">
            <button type="submit" class="btn btn-danger btn-lg"><i class="fas fa-save me-2"></i>Créer le dossier SAV</button>
        </div>
    </form>
    <hr class="mb-4">
    <h2 class="mb-3"><i class="fas fa-list me-2"></i>Liste des dossiers SAV</h2>
    <div class="d-flex justify-content-end mb-2 gap-2">
        <?php
        $exportBase = 'sav_export.php?' . http_build_query(array_merge($_GET));
        ?>
        <a href="<?= $exportBase . '&format=csv' ?>" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv"></i> Export CSV/Excel</a>
        <a href="<?= $exportBase . '&format=txt' ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-alt"></i> Export Bloc-notes</a>
        <a href="<?= $exportBase . '&format=word' ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-word"></i> Export Word</a>
    </div>
    <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Recherche rapide</label>
            <input type="text" name="search" class="form-control" placeholder="N° SAV, client, n° série..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select">
                <option value="">Tous</option>
                <option value="en_attente" <?= $statut=='en_attente'?'selected':'' ?>>En attente</option>
                <option value="en_cours" <?= $statut=='en_cours'?'selected':'' ?>>En cours</option>
                <option value="pret" <?= $statut=='pret'?'selected':'' ?>>Prêt</option>
                <option value="livre" <?= $statut=='livre'?'selected':'' ?>>Livré</option>
                <option value="annule" <?= $statut=='annule'?'selected':'' ?>>Annulé</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Client</label>
            <select name="id_client" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['IDCLIENT'] ?>" <?= $id_client==$client['IDCLIENT']?'selected':'' ?>>
                        <?= htmlspecialchars($client['NomPrenomClient']) ?>
                        <?= !empty($client['Telephone']) ? ' (' . htmlspecialchars($client['Telephone']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Date début</label>
            <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($date_debut) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Date fin</label>
            <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($date_fin) ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-danger w-100"><i class="fas fa-search"></i></button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light sticky-header">
                <tr>
                    <th>N° SAV</th>
                    <th>Date dépôt</th>
                    <th>Client</th>
                    <th>Produit (N° série)</th>
                    <th>Description panne</th>
                    <th>Coût estimatif</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dossiers)): ?>
                    <tr><td colspan="8" class="text-center text-muted">Aucun dossier trouvé</td></tr>
                <?php else: ?>
                    <?php foreach ($dossiers as $dossier): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dossier['numero_sav']) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($dossier['date_depot'])) ?></td>
                            <td>
                                <?php if ($dossier['NomPrenomClient']): ?>
                                    <?= htmlspecialchars($dossier['NomPrenomClient']) ?>
                                    <?= !empty($dossier['Telephone']) ? '<br><small class="text-muted">' . htmlspecialchars($dossier['Telephone']) . '</small>' : '' ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($dossier['numero_serie']) ?></td>
                            <td><?= htmlspecialchars($dossier['description_panne']) ?></td>
                            <td><?= number_format($dossier['cout_estime'], 0, ',', ' ') . ' F.CFA' ?></td>
                            <td>
                                <?php
                                $statut = $dossier['statut'];
                                $badge = 'secondary';
                                if ($statut == 'en_attente') $badge = 'warning';
                                elseif ($statut == 'en_cours') $badge = 'info';
                                elseif ($statut == 'pret') $badge = 'success';
                                elseif ($statut == 'livre') $badge = 'dark';
                                elseif ($statut == 'annule') $badge = 'danger';
                                ?>
                                <span class="badge bg-<?= $badge ?> text-uppercase"><?= htmlspecialchars($statut) ?></span>
                            </td>
                            <td>
                                <?php if (can_user('sav', 'voir')): ?>
                                    <a href="sav_suivi.php?id_sav=<?= $dossier['id_sav'] ?>" class="btn btn-outline-primary btn-sm" title="Consulter"><i class="fas fa-eye"></i></a>
                                    <a href="sav_impression.php?id_sav=<?= $dossier['id_sav'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank" title="Imprimer"><i class="fas fa-print"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-outline-primary btn-sm" disabled title="Accès refusé"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-outline-secondary btn-sm" disabled title="Accès refusé"><i class="fas fa-print"></i></button>
                                <?php endif; ?>
                                <?php if (can_user('sav', 'supprimer')): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce dossier SAV ?')">
                                        <input type="hidden" name="id_sav" value="<?= $dossier['id_sav'] ?>">
                                        <button type="submit" name="supprimer_dossier_liste" class="btn btn-outline-danger btn-sm" title="Supprimer"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-outline-danger btn-sm" disabled title="Accès refusé"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Pagination dossiers SAV">
        <ul class="pagination justify-content-center mt-3">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">&laquo;</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item<?= $i == $page ? ' active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item<?= $page >= $total_pages ? ' disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">&raquo;</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
<script>
// Gestion du téléphone client automatique
document.getElementById('id_client').onchange = function() {
    var select = this;
    var telField = document.getElementById('telephone_client');
    var selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        var tel = selectedOption.getAttribute('data-tel');
        telField.value = tel || '';
    } else {
        telField.value = '';
    }
};

// Mise à jour du client si téléphone modifié
document.getElementById('telephone_client').onblur = function() {
    var tel = this.value.trim();
    var select = document.getElementById('id_client');
    var selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value && tel && tel !== selectedOption.getAttribute('data-tel')) {
        // Mettre à jour le téléphone du client en base
        fetch('client_update_tel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_client=' + selectedOption.value + '&tel=' + encodeURIComponent(tel)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour l'option dans le select
                selectedOption.setAttribute('data-tel', tel);
                selectedOption.textContent = selectedOption.textContent.split(' (')[0] + ' (' + tel + ')';
            }
        });
    }
};

document.getElementById('btn-nouveau-client').onclick = function() {
    document.getElementById('form-nouveau-client').style.display = 'block';
};
document.getElementById('annuler-nouveau-client').onclick = function() {
    document.getElementById('form-nouveau-client').style.display = 'none';
    document.getElementById('msg-nouveau-client').textContent = '';
};
document.getElementById('valider-nouveau-client').onclick = function() {
    var nom = document.getElementById('nouveau_nom').value.trim();
    var tel = document.getElementById('nouveau_tel').value.trim();
    var msg = document.getElementById('msg-nouveau-client');
    if (!nom || !tel) {
        msg.textContent = 'Veuillez saisir le nom et le téléphone.';
        msg.className = 'text-danger small';
        return;
    }
    msg.textContent = 'Ajout en cours...';
    msg.className = 'text-info small';
    fetch('client_ajout_rapide.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'nom=' + encodeURIComponent(nom) + '&tel=' + encodeURIComponent(tel)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msg.textContent = 'Client ajouté !';
            msg.className = 'text-success small';
            // Ajouter à la liste
            var select = document.getElementById('id_client');
            var opt = document.createElement('option');
            opt.value = data.id;
            opt.textContent = nom + ' (' + tel + ')';
            opt.setAttribute('data-tel', tel);
            select.appendChild(opt);
            select.value = data.id;
            document.getElementById('telephone_client').value = tel;
            document.getElementById('form-nouveau-client').style.display = 'none';
            document.getElementById('nouveau_nom').value = '';
            document.getElementById('nouveau_tel').value = '';
        } else {
            msg.textContent = data.message || 'Erreur lors de l\'ajout.';
            msg.className = 'text-danger small';
        }
    })
    .catch(() => {
        msg.textContent = 'Erreur réseau.';
        msg.className = 'text-danger small';
    });
};

//PERMET DIMPRIMER SANS REDIRECTION
        function imprimerSansNouvelOnglet(url) {
    const largeur = 800;
    const hauteur = 600;
    const left = (screen.width / 2) - (largeur / 2);
    const top = (screen.height / 2) - (hauteur / 2);

    const fenetre = window.open(url, '_blank', `width=${largeur},height=${hauteur},top=${top},left=${left}`);

    if (!fenetre) {
        alert("Le pop-up a été bloqué. Veuillez autoriser les fenêtres pop-up.");
        return;
    }

    const timer = setInterval(() => {
        if (fenetre.document.readyState === 'complete') {
            clearInterval(timer);
            fenetre.focus();
            fenetre.print();
            setTimeout(() => {
                fenetre.close();
            }, 1500);
        }
    }, 500);
}

// Gestion des matériaux SAV
let materiauIndex = 1;

document.getElementById('ajouter-materiau').addEventListener('click', function() {
    const container = document.getElementById('materiaux-container');
    const newRow = document.createElement('div');
    newRow.className = 'row g-2 mb-2 materiau-row';
    newRow.innerHTML = `
        <div class="col-md-4">
            <input type="text" name="materiaux[${materiauIndex}][designation]" class="form-control" placeholder="Désignation du matériau" required>
        </div>
        <div class="col-md-2">
            <input type="number" name="materiaux[${materiauIndex}][cout]" class="form-control cout-unitaire" placeholder="Coût unitaire" step="0.01" required>
        </div>
        <div class="col-md-2">
            <input type="number" name="materiaux[${materiauIndex}][quantite]" class="form-control quantite" placeholder="Quantité" value="1" min="1" required>
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control cout-total" placeholder="Total" readonly>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger btn-sm supprimer-materiau"><i class="fas fa-trash"></i></button>
        </div>
    `;
    container.appendChild(newRow);
    materiauIndex++;
    
    // Afficher le bouton supprimer pour tous les matériaux sauf le premier
    document.querySelectorAll('.supprimer-materiau').forEach(btn => {
        btn.style.display = 'inline-block';
    });
});

// Calcul automatique des coûts des matériaux (pour référence)
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('cout-unitaire') || e.target.classList.contains('quantite')) {
        const row = e.target.closest('.materiau-row');
        const coutUnitaire = parseFloat(row.querySelector('.cout-unitaire').value) || 0;
        const quantite = parseInt(row.querySelector('.quantite').value) || 0;
        const coutTotal = row.querySelector('.cout-total');
        coutTotal.value = (coutUnitaire * quantite).toFixed(2);
        
        // Afficher le total des matériaux pour référence
        afficherTotalMateriaux();
    }
});

// Supprimer un matériau
document.addEventListener('click', function(e) {
    if (e.target.closest('.supprimer-materiau')) {
        const row = e.target.closest('.materiau-row');
        row.remove();
        afficherTotalMateriaux();
        
        // Masquer le bouton supprimer s'il ne reste qu'un matériau
        if (document.querySelectorAll('.materiau-row').length === 1) {
            document.querySelector('.supprimer-materiau').style.display = 'none';
        }
    }
});

function afficherTotalMateriaux() {
    let total = 0;
    document.querySelectorAll('.cout-total').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    
    // Afficher le total des matériaux pour référence (ne pas modifier le coût estimatif)
    const totalElement = document.getElementById('total-materiaux');
    if (totalElement) {
        totalElement.textContent = total.toFixed(2);
    }
}
</script>
</body>
</html>