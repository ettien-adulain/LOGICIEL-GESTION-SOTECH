<?php
include('db/connecting.php');
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$id_entree = $data['id_entree'] ?? null;
$id_fournisseur = $data['id_fournisseur'] ?? null;
$montant = $data['montant'] ?? null;
$utilisateur = $data['utilisateur'] ?? 'Inconnu';

// Validations de sécurité
if (!$id_entree || !$id_fournisseur || !$montant) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

// Validation du montant
$montant = floatval($montant);
if ($montant <= 0) {
    echo json_encode(['success' => false, 'message' => 'Le montant doit être positif']);
    exit;
}

if ($montant > 100000000) { // 100 millions max
    echo json_encode(['success' => false, 'message' => 'Montant trop élevé (maximum 100 000 000 F.CFA)']);
    exit;
}

// Validation de l'entrée en stock
$stmt = $cnx->prepare("SELECT MontantAchatHT FROM entree_en_stock WHERE IDENTREE_STOCK = ?");
$stmt->execute([$id_entree]);
$montant_total = $stmt->fetchColumn();

if (!$montant_total) {
    echo json_encode(['success' => false, 'message' => 'Entrée en stock introuvable']);
    exit;
}

// Calculer le montant déjà payé
$stmt = $cnx->prepare("SELECT SUM(Montant) FROM paiement_fournisseur WHERE ID_ENTREE = ?");
$stmt->execute([$id_entree]);
$montant_paye = $stmt->fetchColumn() ?: 0;

// Vérifier que le paiement ne dépasse pas le montant restant
$montant_restant = $montant_total - $montant_paye;
if ($montant > $montant_restant) {
    echo json_encode(['success' => false, 'message' => "Montant trop élevé. Reste à payer: " . number_format($montant_restant, 2, ',', ' ') . " F.CFA"]);
    exit;
}

try {
    $cnx->beginTransaction();
    // 1. Insérer le paiement
    $sql = "INSERT INTO paiement_fournisseur (IDFOURNISSEUR, ID_ENTREE, Montant, Utilisateur) VALUES (?, ?, ?, ?)";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$id_fournisseur, $id_entree, $montant, $utilisateur]);

    // 2. Calculer le total payé
    $sql = "SELECT SUM(Montant) FROM paiement_fournisseur WHERE ID_ENTREE = ?";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$id_entree]);
    $total_paye = $stmt->fetchColumn() ?: 0;

    // 3. Récupérer le montant total à payer
    $sql = "SELECT MontantAchatHT FROM entree_en_stock WHERE IDENTREE_STOCK = ?";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$id_entree]);
    $montant_total = $stmt->fetchColumn() ?: 0;

    // 4. Mettre à jour le statut si tout est payé
    if (floatval($total_paye) >= floatval($montant_total)) {
        $sql = "UPDATE entree_en_stock SET statut = 'TERMINE' WHERE IDENTREE_STOCK = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$id_entree]);
    }
    $cnx->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $cnx->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
} 