<?php
include('db/connecting.php');
require_once 'fonction_traitement/fonction.php';
check_access();

// Contrôle d'accès spécifique : droit 'voir' sur la page SAV_suivi
if (!user_has_access('SAV_suivi', 'voir')) {
    access_denied_page();
}

$id_sav = isset($_GET['id_sav']) ? intval($_GET['id_sav']) : 0;
if ($id_sav <= 0) {
    echo '<div class="alert alert-danger">Dossier SAV introuvable.</div>';
    exit;
}

// Récupérer le dossier SAV
$stmt = $cnx->prepare("SELECT sd.*, c.NomPrenomClient FROM sav_dossier sd LEFT JOIN client c ON sd.id_client = c.IDCLIENT WHERE sd.id_sav = ?");
$stmt->execute([$id_sav]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dossier) {
    echo '<div class="alert alert-danger">Dossier SAV introuvable.</div>';
    exit;
}

// Gestion des actions (modification statut, ajout commentaire, paiement, pièce)
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['maj_statut'])) {
            $new_statut = $_POST['statut'];
            $commentaire = trim($_POST['commentaire']);
            $stmt = $cnx->prepare("UPDATE sav_dossier SET statut = ? WHERE id_sav = ?");
            $stmt->execute([$new_statut, $id_sav]);
            // Historique
            $stmt = $cnx->prepare("INSERT INTO sav_historique (id_sav, statut, commentaire, utilisateur) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_sav, $new_statut, $commentaire, $_SESSION['nom_utilisateur']]);
            $success = "Statut mis à jour.";
        } elseif (isset($_POST['ajout_paiement'])) {
            $montant = floatval($_POST['montant']);
            $type_paiement = $_POST['type_paiement'];
            $utilisateur = $_SESSION['nom_utilisateur'];
            $date_paiement = date('Y-m-d H:i:s');
            
            $stmt = $cnx->prepare("INSERT INTO sav_paiement (id_sav, montant, type_paiement, utilisateur, date_paiement) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_sav, $montant, $type_paiement, $utilisateur, $date_paiement]);
            $success = "Paiement ajouté avec succès.";

        } elseif (isset($_POST['supprimer_paiement'])) {
            $id_paiement = intval($_POST['id_paiement']);
            $stmt = $cnx->prepare("DELETE FROM sav_paiement WHERE id_paiement = ? AND id_sav = ?");
            $stmt->execute([$id_paiement, $id_sav]);
            $success = "Paiement supprimé.";
        } elseif (isset($_POST['supprimer_historique'])) {
            $id_historique = intval($_POST['id_historique']);
            $stmt = $cnx->prepare("DELETE FROM sav_historique WHERE id_historique = ? AND id_sav = ?");
            $stmt->execute([$id_historique, $id_sav]);
            $success = "Commentaire supprimé.";
        } elseif (isset($_POST['supprimer_dossier'])) {
            // Supprimer en cascade : paiements, historique, puis dossier
            $cnx->beginTransaction();
            try {
                // --- JOURNALISATION : Suppression dossier SAV depuis suivi ---
                $description_suppression = sprintf(
                    "Suppression dossier SAV N°%s - Série: %s - Client: %s - Suppression depuis suivi",
                    $dossier['numero_sav'],
                    $dossier['numero_serie'],
                    $dossier['id_client'] ? 'Client #' . $dossier['id_client'] : 'Non renseigné'
                );
                
                if (function_exists('logSystemAction')) {
                    logSystemAction(
                        $cnx,
                        'SUPPRESSION_DOSSIER_SAV_SUIVI',
                        'SAV',
                        'sav_suivi.php',
                        $description_suppression,
                        [
                            'id_sav' => $id_sav,
                            'numero_sav' => $dossier['numero_sav'],
                            'numero_serie' => $dossier['numero_serie'],
                            'id_client' => $dossier['id_client'],
                            'utilisateur' => $_SESSION['nom_utilisateur'] ?? 'N/A',
                            'suppression_cascade' => true,
                            'depuis_suivi' => true
                        ],
                        [
                            'action' => 'suppression_dossier_sav_suivi',
                            'paiements_supprimes' => true,
                            'historique_supprime' => true,
                            'dossier_supprime' => true,
                            'redirection_liste' => true
                        ],
                        'CRITICAL',
                        'SUCCESS',
                        null
                    );
                }
                // --- FIN JOURNALISATION ---
                
                $stmt = $cnx->prepare("DELETE FROM sav_paiement WHERE id_sav = ?");
                $stmt->execute([$id_sav]);
                $stmt = $cnx->prepare("DELETE FROM sav_historique WHERE id_sav = ?");
                $stmt->execute([$id_sav]);
                $stmt = $cnx->prepare("DELETE FROM sav_dossier WHERE id_sav = ?");
                $stmt->execute([$id_sav]);
                $cnx->commit();
                header('Location: SAV_liste.php?success=suppression');
                exit;
            } catch (Exception $e) {
                $cnx->rollBack();
                
                // --- JOURNALISATION : Erreur suppression dossier SAV depuis suivi ---
                if (function_exists('logSystemAction')) {
                    logSystemAction(
                        $cnx,
                        'ERREUR_SUPPRESSION_DOSSIER_SAV_SUIVI',
                        'SAV',
                        'sav_suivi.php',
                        'Erreur lors de la suppression du dossier SAV depuis suivi : ' . $e->getMessage(),
                        [
                            'id_sav' => $id_sav,
                            'numero_sav' => $dossier['numero_sav'] ?? 'N/A',
                            'utilisateur' => $_SESSION['nom_utilisateur'] ?? 'N/A',
                            'erreur' => $e->getMessage(),
                            'transaction_rollback' => true,
                            'depuis_suivi' => true
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
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    // Recharger les infos à jour
    $stmt = $cnx->prepare("SELECT sd.*, c.NomPrenomClient FROM sav_dossier sd LEFT JOIN client c ON sd.id_client = c.IDCLIENT WHERE sd.id_sav = ?");
    $stmt->execute([$id_sav]);
    $dossier = $stmt->fetch(PDO::FETCH_ASSOC);

// Recharger les matériaux
$stmt = $cnx->prepare("SELECT * FROM sav_piece WHERE id_sav = ? ORDER BY date_achat DESC");
$stmt->execute([$id_sav]);
$materiaux = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les paiements
$stmt = $cnx->prepare("SELECT * FROM sav_paiement WHERE id_sav = ? ORDER BY date_paiement DESC");
$stmt->execute([$id_sav]);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'historique
$stmt = $cnx->prepare("SELECT * FROM sav_historique WHERE id_sav = ? ORDER BY date_action DESC");
$stmt->execute([$id_sav]);
$historiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les matériaux/pièces
$stmt = $cnx->prepare("SELECT * FROM sav_piece WHERE id_sav = ? ORDER BY date_achat DESC");
$stmt->execute([$id_sav]);
$materiaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CORRECTION : Le coût estimatif est le prix global, pas à additionner avec les matériaux
$cout_estime = $dossier['cout_estime'] ?? 0;
$cout_materiaux = 0;
foreach ($materiaux as $m) {
    $cout_materiaux += $m['cout_total'];
}
// Le coût total pour le client est le coût estimatif (prix de réparation)
$cout_total = $cout_estime;

// Paiements
$total_payements = 0;
$acomptes = 0;
$soldes = 0;
foreach ($paiements as $p) {
    $total_payements += $p['montant'];
    if ($p['type_paiement'] == 'acompte') {
        $acomptes += $p['montant'];
    } elseif ($p['type_paiement'] == 'solde') {
        $soldes += $p['montant'];
    }
}
$reste_a_payer = $cout_total - $total_payements;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi dossier SAV</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 1200px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px #0001; padding: 2rem; }
        h2 { color: #dc3545; font-weight: bold; }
        .form-label { font-weight: 600; }
        .badge { font-size: 1em; }
        .sticky-header th { position: sticky; top: 0; background: #fff; z-index: 2; }
        .table-responsive { max-height: 60vh; overflow-y: auto; }
        .card { margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-header { border-bottom: none; }
        .table th { background-color: #f8f9fa; font-weight: 600; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .table td { vertical-align: middle; }
        .section-spacing { margin-bottom: 2rem; }
    </style>
</head>
<body>
<?php include('includes/user_indicator.php'); ?>
<div class="container">
    <h2 class="mb-4"><i class="fas fa-wrench me-2"></i>Suivi dossier SAV - <?= htmlspecialchars($dossier['numero_sav']) ?></h2>
    <?php if ($success): ?><div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div><?php endif; ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <h5>Informations client</h5>
            <ul class="list-group mb-2">
                <li class="list-group-item"><strong>Client :</strong> <?= htmlspecialchars($dossier['NomPrenomClient'] ?? '—') ?></li>
                <li class="list-group-item"><strong>Date dépôt :</strong> <?= date('d/m/Y H:i', strtotime($dossier['date_depot'])) ?></li>
                <li class="list-group-item"><strong>Délai prévisionnel :</strong> <?= $dossier['date_previsionnelle'] ? date('d/m/Y', strtotime($dossier['date_previsionnelle'])) : '—' ?></li>
                <li class="list-group-item"><strong>Statut :</strong> <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($dossier['statut']) ?></span></li>
            </ul>
        </div>
        <div class="col-md-6">
            <h5>Produit & panne</h5>
            <ul class="list-group mb-2">
                <li class="list-group-item"><strong>N° série :</strong> <?= htmlspecialchars($dossier['numero_serie']) ?></li>
                <li class="list-group-item"><strong>Description panne :</strong> <?= htmlspecialchars($dossier['description_panne']) ?></li>
                <li class="list-group-item"><strong>État réception :</strong> <?= htmlspecialchars($dossier['etat_reception']) ?></li>
                <li class="list-group-item"><strong>Coût estimatif :</strong> <?= number_format($dossier['cout_estime'], 0, ',', ' ') . ' F.CFA' ?></li>
            </ul>
        </div>
    </div>
    
    <!-- Section Facture détaillée -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Facture détaillée</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Coût estimatif :</strong></td>
                                    <td class="text-end"><?= number_format($cout_estime, 0, ',', ' ') . ' F.CFA' ?></td>
                                </tr>

                                <tr class="border-top">
                                    <td><strong>Total :</strong></td>
                                    <td class="text-end"><strong class="text-primary fs-5"><?= number_format($cout_total, 0, ',', ' ') . ' F.CFA' ?></strong></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Paiements</h6>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td>Acomptes :</td>
                                            <td class="text-end text-success"><?= number_format($acomptes, 0, ',', ' ') . ' F.CFA' ?></td>
                                        </tr>
                                        <tr>
                                            <td>Soldes :</td>
                                            <td class="text-end text-info"><?= number_format($soldes, 0, ',', ' ') . ' F.CFA' ?></td>
                                        </tr>
                                        <tr class="border-top">
                                            <td><strong>Total payé :</strong></td>
                                            <td class="text-end"><strong class="text-success"><?= number_format($total_payements, 0, ',', ' ') . ' F.CFA' ?></strong></td>
                                        </tr>
                                        <tr class="border-top border-dark">
                                            <td><strong>Reste à payer :</strong></td>
                                            <td class="text-end">
                                                <strong class="<?= $reste_a_payer > 0 ? 'text-danger' : 'text-success' ?>">
                                                    <?= number_format($reste_a_payer, 0, ',', ' ') . ' F.CFA' ?>
                                                </strong>
                                            </td>
                                        </tr>
                                    </table>
                                    <?php if ($reste_a_payer <= 0): ?>
                                        <div class="alert alert-success text-center mb-0">
                                            <i class="fas fa-check-circle"></i> Dossier payé
                                        </div>
                                    <?php elseif ($reste_a_payer > 0): ?>
                                        <div class="alert alert-warning text-center mb-0">
                                            <i class="fas fa-exclamation-triangle"></i> Reste à payer
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="post" class="mb-3">
                <h6>Mettre à jour le statut</h6>
                <div class="mb-2">
                    <select name="statut" class="form-select">
                        <option value="en_attente" <?= $dossier['statut']=='en_attente'?'selected':'' ?>>En attente</option>
                        <option value="en_cours" <?= $dossier['statut']=='en_cours'?'selected':'' ?>>En cours</option>
                        <option value="pret" <?= $dossier['statut']=='pret'?'selected':'' ?>>Prêt</option>
                        <option value="livre" <?= $dossier['statut']=='livre'?'selected':'' ?>>Livré</option>
                        <option value="annule" <?= $dossier['statut']=='annule'?'selected':'' ?>>Annulé</option>
                    </select>
                </div>
                <div class="mb-2">
                    <textarea name="commentaire" class="form-control" placeholder="Commentaire intervention (optionnel)"></textarea>
                </div>
                <button type="submit" name="maj_statut" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Mettre à jour</button>
            </form>
        </div>
        <div class="col-md-6">
            <form method="post" class="mb-3">
                <h6>Ajouter un paiement</h6>
                <div class="row g-2">
                    <div class="col">
                        <input type="number" name="montant" class="form-control" placeholder="Montant" step="0.01" required>
                    </div>
                    <div class="col">
                        <select name="type_paiement" class="form-select">
                            <option value="acompte">Acompte</option>
                            <option value="solde">Solde</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" name="ajout_paiement" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Ajouter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    

    <!-- Section Paiements -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Paiements</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Type</th>
                                <th>Utilisateur</th>
                                <th width="60">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paiements)): ?>
                                <tr><td colspan="5" class="text-center text-muted">Aucun paiement</td></tr>
                            <?php else: ?>
                                <?php foreach ($paiements as $p): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                                        <td><?= number_format($p['montant'], 0, ',', ' ') . ' F.CFA' ?></td>
                                        <td>
                                            <span class="badge bg-<?= $p['type_paiement'] == 'acompte' ? 'warning' : 'success' ?>">
                                                <?= htmlspecialchars($p['type_paiement']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($p['utilisateur']) ?></td>
                                        <td class="text-center">
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce paiement ?')">
                                                <input type="hidden" name="id_paiement" value="<?= $p['id_paiement'] ?>">
                                                <button type="submit" name="supprimer_paiement" class="btn btn-danger btn-sm" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="text-end">
                        <strong>Total payé : <?= number_format($total_payements, 0, ',', ' ') . ' F.CFA' ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Section Historique -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historique des interventions</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Commentaire</th>
                                <th>Utilisateur</th>
                                <th width="60">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historiques)): ?>
                                <tr><td colspan="5" class="text-center text-muted">Aucun historique</td></tr>
                            <?php else: ?>
                                <?php foreach ($historiques as $h): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($h['date_action'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $h['statut'] == 'en_attente' ? 'warning' : ($h['statut'] == 'en_cours' ? 'info' : 'success') ?>">
                                                <?= htmlspecialchars($h['statut']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($h['commentaire']) ?></td>
                                        <td><?= htmlspecialchars($h['utilisateur']) ?></td>
                                        <td class="text-center">
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')">
                                                <input type="hidden" name="id_historique" value="<?= $h['id_historique'] ?>">
                                                <button type="submit" name="supprimer_historique" class="btn btn-danger btn-sm" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="text-end">
        <a href="SAV.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour aux Dossiers</a>
        <a href="sav_impression.php?id_sav=<?= $dossier['id_sav'] ?>" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print"></i> Imprimer le bon de dépôt</a>
        <a href="sav_facture.php?id_sav=<?= $dossier['id_sav'] ?>" class="btn btn-outline-primary" target="_blank"><i class="fas fa-file-invoice"></i> Générer facture</a>
        <?php if (can_user('sav_suivi', 'administrer')): ?>
        <a href="sav_administration.php?id_sav=<?= $dossier['id_sav'] ?>" class="btn btn-outline-warning"><i class="fas fa-chart-line"></i> Administration</a>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
<script>
    
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
</script>
</body>
</html>
 