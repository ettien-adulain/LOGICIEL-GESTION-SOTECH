<?php
try {
    include('db/connecting.php');
    session_start();

    // Vérification de l'utilisateur connecté
    if (!isset($_SESSION['nom_utilisateur'])) {
        throw new Exception('Non autorisé');
    }

    $id_entree = $_GET['id_entree'] ?? null;
    
    if (!$id_entree) {
        throw new Exception('ID entrée manquant');
    }

    // Récupération de l'historique des paiements
    $sql = "SELECT 
                pf.DatePaiement,
                pf.Montant,
                pf.Utilisateur,
                f.NomFournisseur
            FROM paiement_fournisseur pf
            JOIN entree_en_stock e ON pf.ID_ENTREE = e.IDENTREE_STOCK
            JOIN fournisseur f ON e.IDFOURNISSEUR = f.IDFOURNISSEUR
            WHERE pf.ID_ENTREE = ?
            ORDER BY pf.DatePaiement DESC";
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$id_entree]);
    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'paiements' => $paiements
    ]);

} catch (Throwable $th) {
    error_log('Erreur historique_paiements.php: ' . $th->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement de l\'historique'
    ]);
}
?>