<?php
try {
    include('db/connecting.php');
    session_start();

    // Vérification de l'utilisateur connecté
    if (!isset($_SESSION['nom_utilisateur'])) {
        throw new Exception('Non autorisé');
    }

    // Récupération de la date
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    // 1. Fournisseurs actifs
    $sql_fournisseurs = "SELECT * FROM fournisseur";
    $stmt = $cnx->query($sql_fournisseurs);
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data_fournisseurs = [];
    $total_achats_jour = 0;
    $total_dettes = 0;
    $total_paiements_jour = 0;

    foreach ($fournisseurs as $f) {
        $id = $f['IDFOURNISSEUR'];

        // Achats du jour (entrées en stock du jour)
        $sql_achats_jour = "SELECT SUM(MontantAchatHT) FROM entree_en_stock WHERE IDFOURNISSEUR = ? AND DATE(Date_arrivee) = ?";
        $stmt = $cnx->prepare($sql_achats_jour);
        $stmt->execute([$id, $date]);
        $achats_jour = $stmt->fetchColumn() ?: 0;
        $total_achats_jour += $achats_jour;

        // Dette totale (entrées non terminées)
        $sql_dette = "SELECT SUM(MontantAchatHT) FROM entree_en_stock WHERE IDFOURNISSEUR = ? AND statut != 'TERMINE'";
        $stmt = $cnx->prepare($sql_dette);
        $stmt->execute([$id]);
        $dette_totale = $stmt->fetchColumn() ?: 0;
        $total_dettes += $dette_totale;

        // Paiements effectués du jour (table paiement_fournisseur)
        $sql_paiement_jour = "SELECT SUM(pf.Montant) FROM paiement_fournisseur pf WHERE pf.IDFOURNISSEUR = ? AND DATE(pf.DatePaiement) = ?";
        $stmt = $cnx->prepare($sql_paiement_jour);
        $stmt->execute([$id, $date]);
        $paiements_jour = $stmt->fetchColumn() ?: 0;
        $total_paiements_jour += $paiements_jour;

        // Dernier paiement (date du dernier paiement effectué)
        $sql_dernier_paiement = "SELECT MAX(pf.DatePaiement) FROM paiement_fournisseur pf WHERE pf.IDFOURNISSEUR = ?";
        $stmt = $cnx->prepare($sql_dernier_paiement);
        $stmt->execute([$id]);
        $dernier_paiement = $stmt->fetchColumn();

        // Total payé à ce fournisseur
        $sql_total_paye = "SELECT SUM(pf.Montant) FROM paiement_fournisseur pf WHERE pf.IDFOURNISSEUR = ?";
        $stmt = $cnx->prepare($sql_total_paye);
        $stmt->execute([$id]);
        $total_paye = $stmt->fetchColumn() ?: 0;

        // Total des achats (toutes entrées)
        $sql_total_achats = "SELECT SUM(MontantAchatHT) FROM entree_en_stock WHERE IDFOURNISSEUR = ?";
        $stmt = $cnx->prepare($sql_total_achats);
        $stmt->execute([$id]);
        $total_achats = $stmt->fetchColumn() ?: 0;

        // Solde réel (achats - paiements)
        $solde_reel = $total_achats - $total_paye;

        // Statut professionnel
        if ($solde_reel <= 0) {
            $statut = 'Solde positif';
            $statut_class = 'success';
        } elseif ($solde_reel <= 10000) {
            $statut = 'Dette faible';
            $statut_class = 'warning';
        } else {
            $statut = 'Dette importante';
            $statut_class = 'danger';
        }

        $data_fournisseurs[] = [
            'id_fournisseur' => $id,
            'nom_fournisseur' => $f['NomFournisseur'] ?? '',
            'telephone' => $f['TelephoneFournisseur'] ?? '',
            'email' => $f['eMailFournisseur'] ?? '',
            'achats_jour' => floatval($achats_jour),
            'dette_totale' => floatval($solde_reel),
            'total_achats' => floatval($total_achats),
            'total_paye' => floatval($total_paye),
            'dernier_paiement' => $dernier_paiement,
            'statut' => $statut,
            'statut_class' => $statut_class,
            'paiements_jour' => floatval($paiements_jour),
        ];
    }

    // Réponse JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_fournisseurs' => count($fournisseurs),
            'achats_jour' => floatval($total_achats_jour),
            'dettes_total' => floatval($total_dettes),
            'paiements_jour' => floatval($total_paiements_jour),
        ],
        'fournisseurs' => $data_fournisseurs
    ]);

} catch (Throwable $th) {
    error_log('Erreur get_fournisseurs_data.php: ' . $th->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement des données'
    ]);
}
?> 