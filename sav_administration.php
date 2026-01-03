<?php
include('db/connecting.php');
require_once 'fonction_traitement/fonction.php';
check_access();

// Vérifier les droits d'administration
if (!can_user_page('sav_suivi', 'administrer')) {
    echo '<div class="alert alert-danger">Accès refusé : vous n\'avez pas le droit d\'administrer ce dossier SAV.</div>';
    exit;
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

// Récupérer les matériaux
$stmt = $cnx->prepare("SELECT * FROM sav_piece WHERE id_sav = ? ORDER BY date_achat DESC");
$stmt->execute([$id_sav]);
$materiaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les paiements
$stmt = $cnx->prepare("SELECT * FROM sav_paiement WHERE id_sav = ? ORDER BY date_paiement DESC");
$stmt->execute([$id_sav]);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CORRECTION : Calculs internes (le coût réel = matériaux + main d'œuvre)
$cout_estime = $dossier['cout_estime'] ?? 0; // Prix de réparation (60 000 F.CFA)
$cout_materiaux = 0;
foreach ($materiaux as $m) {
    $cout_materiaux += $m['cout_total'] ??"";
}

// CORRECTION : Le coût réel = matériaux achetés (coûts réels)
// Si aucun matériau n'est saisi, le coût réel = 0 (main d'œuvre = bénéfice pur)
//$cout_total_reel = $cout_materiaux;
// Le coût réel = matériaux ou estimation main d'œuvre
if ($cout_materiaux > 0) {
    // CAS 1 : Réparation avec matériaux
    $cout_total_reel = $cout_materiaux;
} else {
    // CAS 2 : Réparation sans matériaux (main d'œuvre uniquement)
    // Estimation : 30% du prix estimé pour la main d'œuvre
    $cout_total_reel = $cout_materiaux ;
}
// Paiements
$total_payements = 0;
foreach ($paiements as $p) {
    $total_payements += $p['montant'];
}

// Bénéfice interne
$benefice_interne = $total_payements - $cout_total_reel;
$marge_interne = $total_payements > 0 ? ($benefice_interne / $total_payements) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration SAV - Bénéfices Internes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 1200px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px #0001; padding: 2rem; }
        h2 { color: #dc3545; font-weight: bold; }
        .card { margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-header { border-bottom: none; }
        .table th { background-color: #f8f9fa; font-weight: 600; }
        .benefice-positif { color: #28a745; font-weight: bold; }
        .benefice-negatif { color: #dc3545; font-weight: bold; }
        .materiaux-section { background: #fff3cd; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
<?php include('includes/user_indicator.php'); ?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line me-2"></i>Administration SAV - <?= htmlspecialchars($dossier['numero_sav']) ?></h2>
        <a href="sav_suivi.php?id_sav=<?= $id_sav ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Retour au suivi
        </a>
    </div>
    
    <!-- Informations dossier -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h5>Informations client</h5>
            <ul class="list-group mb-2">
                <li class="list-group-item"><strong>Client :</strong> <?= htmlspecialchars($dossier['NomPrenomClient'] ?? '—') ?></li>
                <li class="list-group-item"><strong>Date dépôt :</strong> <?= date('d/m/Y H:i', strtotime($dossier['date_depot'])) ?></li>
                <li class="list-group-item"><strong>Statut :</strong> <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($dossier['statut']) ?></span></li>
            </ul>
        </div>
        <div class="col-md-6">
            <h5>Produit & panne</h5>
            <ul class="list-group mb-2">
                <li class="list-group-item"><strong>N° série :</strong> <?= htmlspecialchars($dossier['numero_serie']) ?></li>
                <li class="list-group-item"><strong>Description panne :</strong> <?= htmlspecialchars($dossier['description_panne']) ?></li>
            </ul>
        </div>
    </div>
    
    <!-- Section Matériaux (INTERNE) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card materiaux-section">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Matériaux achetés (INTERNE)</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Désignation</th>
                                <th>Coût unitaire</th>
                                <th>Quantité</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($materiaux)): ?>
                                <tr><td colspan="5" class="text-center text-muted">Aucun matériau enregistré</td></tr>
                            <?php else: ?>
                                <?php foreach ($materiaux as $m): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($m['date_achat'])) ?></td>
                                        <td><?= htmlspecialchars($m['designation']) ?></td>
                                        <td><?= number_format($m['prix_unitaire'], 0) ?> F.CFA</td>
                                        <td><?= $m['quantite'] ?></td>
                                        <td><?= number_format($m['cout_total'], 0) ?> F.CFA</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="text-end">
                        <strong>Total matériaux : <?= number_format($cout_materiaux, 0) ?> F.CFA</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section Bénéfices Internes -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Analyse Bénéfices (INTERNE)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Coûts Internes</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Coût estimatif (réel) :</strong></td>
                                    <td class="text-end"><?= number_format($cout_estime, 0) ?> F.CFA</td>
                                </tr>
                                <tr>
                                    <td><strong>Coût matériaux :</strong></td>
                                    <td class="text-end"><?= number_format($cout_materiaux, 0) ?> F.CFA</td>
                                </tr>
                                <tr>
                                    <td><strong>Marge + Main d'œuvre :</strong></td>
                                    <td class="text-end"><?= number_format($cout_estime - $cout_materiaux, 0) ?> F.CFA</td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>Coût total réel :</strong></td>
                                    <td class="text-end"><strong class="text-danger"><?= number_format($cout_total_reel, 0) ?> F.CFA</strong></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Revenus & Bénéfice</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Total payé :</strong></td>
                                    <td class="text-end text-success"><?= number_format($total_payements, 0) ?> F.CFA</td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>Bénéfice interne :</strong></td>
                                    <td class="text-end">
                                        <strong class="<?= $benefice_interne >= 0 ? 'benefice-positif' : 'benefice-negatif' ?>">
                                            <?= number_format($benefice_interne, 0) ?> F.CFA
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Marge interne :</strong></td>
                                    <td class="text-end">
                                        <strong class="<?= $marge_interne >= 0 ? 'benefice-positif' : 'benefice-negatif' ?>">
                                            <?= number_format($marge_interne, 1) ?>%
                                        </strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Graphique de rentabilité -->
                    <div class="mt-3">
                        <h6>Rentabilité du dossier</h6>
                        <div class="progress" style="height: 25px;">
                            <?php if ($total_payements > 0): ?>
                                <?php $pourcentage_couts = ($cout_total_reel / $total_payements) * 100; ?>
                                <div class="progress-bar bg-danger" style="width: <?= min($pourcentage_couts, 100) ?>%">
                                    Coûts: <?= number_format($pourcentage_couts, 1) ?>%
                                </div>
                                <div class="progress-bar bg-success" style="width: <?= max(100 - $pourcentage_couts, 0) ?>%">
                                    Bénéfice: <?= number_format(100 - $pourcentage_couts, 1) ?>%
                                </div>
                            <?php else: ?>
                                <div class="progress-bar bg-secondary" style="width: 100%">
                                    Aucun paiement
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section Paiements -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Paiements reçus</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Type</th>
                                <th>Opérateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paiements)): ?>
                                <tr><td colspan="4" class="text-center text-muted">Aucun paiement</td></tr>
                            <?php else: ?>
                                <?php foreach ($paiements as $p): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                                        <td><?= number_format($p['montant'], 0) ?> F.CFA</td>
                                        <td>
                                            <span class="badge bg-<?= $p['type_paiement'] == 'acompte' ? 'warning' : 'success' ?>">
                                                <?= htmlspecialchars($p['type_paiement']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($p['utilisateur']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 