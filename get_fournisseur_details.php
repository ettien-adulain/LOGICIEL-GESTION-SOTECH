<?php
include('db/connecting.php');
header('Content-Type: application/json');
$id_fournisseur = $_GET['id_fournisseur'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$id_fournisseur) {
    echo json_encode(['success' => false, 'message' => 'ID fournisseur manquant']);
    exit;
}

// Si date spécifiée, filtrer par date, sinon montrer tous les achats
if ($date && $date !== date('Y-m-d')) {
    $sql = "SELECT 
                e.IDENTREE_STOCK AS id_entree,
                e.IDFOURNISSEUR AS id_fournisseur,
                e.Date_arrivee AS date_achat,
                e.Numero_bon AS libelle,
                COALESCE(e.Quantite, 1) AS quantite,
                COALESCE(e.PrixAchat, e.MontantAchatHT) AS prix_unitaire,
                e.MontantAchatHT AS total_ht,
                e.statut AS statut_entree,
                COALESCE(SUM(pf.Montant), 0) AS montant_paye,
                (e.MontantAchatHT - COALESCE(SUM(pf.Montant), 0)) AS reste_a_payer,
                CASE 
                    WHEN (e.MontantAchatHT - COALESCE(SUM(pf.Montant), 0)) <= 0 THEN 'Payé'
                    WHEN COALESCE(SUM(pf.Montant), 0) = 0 THEN 'En attente'
                    ELSE 'Paiement partiel'
                END AS statut_paiement
            FROM entree_en_stock e
            LEFT JOIN paiement_fournisseur pf ON e.IDENTREE_STOCK = pf.ID_ENTREE
            WHERE e.IDFOURNISSEUR = ? AND DATE(e.Date_arrivee) = ?
            GROUP BY e.IDENTREE_STOCK
            ORDER BY e.Date_arrivee DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$id_fournisseur, $date]);
} else {
    // Montrer tous les achats du fournisseur
    $sql = "SELECT 
                e.IDENTREE_STOCK AS id_entree,
                e.IDFOURNISSEUR AS id_fournisseur,
                e.Date_arrivee AS date_achat,
                e.Numero_bon AS libelle,
                COALESCE(e.Quantite, 1) AS quantite,
                COALESCE(e.PrixAchat, e.MontantAchatHT) AS prix_unitaire,
                e.MontantAchatHT AS total_ht,
                e.statut AS statut_entree,
                COALESCE(SUM(pf.Montant), 0) AS montant_paye,
                (e.MontantAchatHT - COALESCE(SUM(pf.Montant), 0)) AS reste_a_payer,
                CASE 
                    WHEN (e.MontantAchatHT - COALESCE(SUM(pf.Montant), 0)) <= 0 THEN 'Payé'
                    WHEN COALESCE(SUM(pf.Montant), 0) = 0 THEN 'En attente'
                    ELSE 'Paiement partiel'
                END AS statut_paiement
            FROM entree_en_stock e
            LEFT JOIN paiement_fournisseur pf ON e.IDENTREE_STOCK = pf.ID_ENTREE
            WHERE e.IDFOURNISSEUR = ?
            GROUP BY e.IDENTREE_STOCK
            ORDER BY e.Date_arrivee DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$id_fournisseur]);
}
$achats = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'achats' => $achats
]);