<?php
session_start();
include('../db/connecting.php');

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">ID non spécifié</div>';
    exit;
}

$id_entree = $_GET['id'];

try {
    // Récupérer les informations de l'entrée en stock
    $stmt = $cnx->prepare("
        SELECT e.*, f.NomFournisseur, u.NomPrenom as operateur
        FROM entree_en_stock e
        LEFT JOIN fournisseur f ON e.IDFOURNISSEUR = f.IDFOURNISSEUR
        LEFT JOIN utilisateur u ON e.ID_utilisateurs = u.IDUTILISATEUR
        WHERE e.IDENTREE_STOCK = ?
    ");
    $stmt->execute([$id_entree]);
    $entree = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entree) {
        echo '<div class="alert alert-danger">Entrée en stock non trouvée</div>';
        exit;
    }

    // Récupérer les lignes d'entrée en stock
    $stmt = $cnx->prepare("
        SELECT esl.*, a.libelle, a.descriptif
        FROM entree_stock_ligne esl
        JOIN article a ON esl.IDARTICLE = a.IDARTICLE
        WHERE esl.IDENTREE_EN_STOCK = ?
    ");
    $stmt->execute([$id_entree]);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les numéros de série si l'entrée est terminée
    $numeros = [];
    $total_numeros = 0;
    if ($entree['statut'] === 'TERMINE') {
        $stmt = $cnx->prepare("
            SELECT n.*, a.libelle
            FROM num_serie n
            JOIN article a ON n.IDARTICLE = a.IDARTICLE
            WHERE n.ID_ENTRER_STOCK = ?
            ORDER BY a.libelle, n.NUMERO_SERIE
        ");
        $stmt->execute([$id_entree]);
        $numeros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_numeros = count($numeros);
    }

    // Afficher les informations de l'entrée
    ?>
    <div class="details-container">
        <!-- Section Informations générales -->
        <div class="details-section">
            <div class="details-section-header">
                <i class="fas fa-info-circle mr-2"></i>
                Informations générales
            </div>
            <div class="details-section-content">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-row">
                            <strong>N° Entrée :</strong> <?= htmlspecialchars($entree['IDENTREE_STOCK']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>N° Bon :</strong> <?= htmlspecialchars($entree['Numero_bon']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Date :</strong> <?= date('d/m/Y', strtotime($entree['Date_arrivee'])) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Fournisseur :</strong> <?= htmlspecialchars($entree['NomFournisseur'] ?? 'Non spécifié') ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-row">
                            <strong>Opérateur :</strong> <?= htmlspecialchars($entree['operateur'] ?? 'Non spécifié') ?>
                        </div>
                        <div class="detail-row">
                            <strong>Statut :</strong>
                            <span class="status-badge <?= $entree['statut'] === 'TERMINE' ? 'status-termine' : 'status-en-cours' ?>">
                                <?= $entree['statut'] === 'TERMINE' ? 'Terminé' : 'En cours' ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <strong>Frais annexes :</strong> <?= number_format($entree['frais_annexes'] ?? 0, 0, ',', ' ') ?> FCFA
                        </div>
                        <?php if ($total_numeros > 0): ?>
                        <div class="detail-row">
                            <strong>Numéros de série :</strong> 
                            <span class="numeros-count"><?= $total_numeros ?> numéro(s)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Articles -->
        <div class="details-section">
            <div class="details-section-header">
                <i class="fas fa-box mr-2"></i>
                Articles (<?= count($lignes) ?> article(s))
            </div>
            <div class="details-section-content">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th>Référence</th>
                                <th>Quantité</th>
                                <th>Prix unitaire réel <span title="Prix d'achat + part des frais annexes"> <i class='fas fa-info-circle'></i></span></th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_general = 0;
                            foreach ($lignes as $ligne): 
                                // Utiliser le coût unitaire réel si présent, sinon le prix d'achat classique
                                $prix_unitaire = isset($ligne['cout_unitaire_reel']) && $ligne['cout_unitaire_reel'] > 0 ? $ligne['cout_unitaire_reel'] : $ligne['PrixAchat'];
                                $total_ligne = $ligne['Quantite'] * $prix_unitaire;
                                $total_general += $total_ligne;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($ligne['libelle']) ?></td>
                                    <td><?= htmlspecialchars($ligne['descriptif']) ?></td>
                                    <td class="text-center"><?= $ligne['Quantite'] ?></td>
                                    <td class="text-right"><?= number_format($prix_unitaire, 2) ?> F CFA</td>
                                    <td class="text-right"><strong><?= number_format($total_ligne, 2) ?> F CFA</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <th colspan="4" class="text-right">Total général :</th>
                                <th class="text-right"><?= number_format($total_general, 2) ?> F CFA</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($entree['statut'] === 'TERMINE' && $total_numeros > 0): ?>
        <!-- Section Numéros de série -->
        <div class="details-section">
            <div class="details-section-header">
                <i class="fas fa-barcode mr-2"></i>
                Numéros de série 
                <span class="numeros-count ml-2"><?= $total_numeros ?> numéro(s)</span>
            </div>
            <div class="details-section-content">
                <div class="numeros-serie-container">
                    <table class="table numeros-serie-table" id="numerosSerieTable">
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th>Numéro de série</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($numeros as $numero): ?>
                                <tr>
                                    <td><?= htmlspecialchars($numero['libelle']) ?></td>
                                    <td><code><?= htmlspecialchars($numero['NUMERO_SERIE']) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 