<?php
include('db/connecting.php');

$numeroVente = $_GET['numero'] ?? '';

if (empty($numeroVente)) {
    echo json_encode(['success' => false, 'message' => 'Numéro de vente requis']);
    exit;
}

try {
    // Récupérer les informations du client et de la vente
    $clientSql = "SELECT v.IDCLIENT, v.MontantTotal, v.MontantRemise, v.MontantVerse, v.Monnaie, v.DateIns, c.NomPrenomClient, c.Telephone, c.Adresse_email
                  FROM vente v
                  LEFT JOIN client c ON v.IDCLIENT = c.IDCLIENT
                  WHERE v.NumeroVente = ?";
    
    $clientStmt = $cnx->prepare($clientSql);
    $clientStmt->execute([$numeroVente]);
    $venteInfo = $clientStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venteInfo) {
        echo json_encode(['success' => false, 'message' => 'Vente non trouvée']);
        exit;
    }
    
    // Récupérer les articles avec leurs numéros de série - MÊME LOGIQUE QUE LES FACTURES
    $sql = "SELECT DISTINCT a.libelle, a.PrixVenteTTC, fa.QuantiteVendue, ns.NUMERO_SERIE
            FROM facture_article fa
            JOIN article a ON fa.IDARTICLE = a.IDARTICLE
            INNER JOIN num_serie ns 
                ON ns.IDARTICLE = fa.IDARTICLE 
                AND ns.NumeroVente = fa.NumeroVente 
                AND ns.ID_VENTE = fa.IDFactureVente
                AND ns.statut = 'vendue'
            WHERE fa.NumeroVente = ?
            ORDER BY fa.IDFactureVente, ns.NUMERO_SERIE";
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute([$numeroVente]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si pas d'articles trouvés, vérifier s'il s'agit d'une vente à crédit
    if (empty($articles)) {
        $sql = "SELECT DISTINCT a.libelle, a.PrixVenteTTC, l.QuantiteVendue, ns.NUMERO_SERIE
                FROM ventes_credit_ligne l
                JOIN article a ON l.IDARTICLE = a.IDARTICLE
                INNER JOIN num_serie ns 
                    ON ns.IDARTICLE = l.IDARTICLE 
                    AND ns.NumeroVente = l.NumeroVente 
                    AND ns.IDvente_credit = l.IDVenteCredit
                    AND ns.statut = 'vendue_credit'
                WHERE l.IDVenteCredit = (
                    SELECT IDVenteCredit FROM ventes_credit WHERE NumeroVente = ?
                )
                ORDER BY a.libelle, ns.NUMERO_SERIE";
        
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$numeroVente]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculer les totaux - MÊME LOGIQUE QUE LES FACTURES
    $total_articles = count($articles);
    $total_prix = 0;
    $articles_final = [];
    
    foreach ($articles as $article) {
        $prix_unitaire = (float)$article['PrixVenteTTC'];
        $quantite = (int)$article['QuantiteVendue'];
        $prix_total = $prix_unitaire * $quantite;
        $total_prix += $prix_total;
        
        $articles_final[] = [
            'libelle' => $article['libelle'],
            'prix_unitaire' => $prix_unitaire,
            'quantite' => $quantite,
            'prix_total' => $prix_total,
            'numero_serie' => $article['NUMERO_SERIE']
        ];
    }
    
    // Récupérer les modes de paiement
    $modes_paiement = [];
    $stmt = $cnx->prepare("
        SELECT 
            vp.MONTANT,
            mr.ModeReglement
        FROM vente_paiement vp
        LEFT JOIN mode_reglement mr ON vp.IDMODE_REGLEMENT = mr.IDMODE_REGLEMENT
        WHERE vp.IDFactureVente = (
            SELECT IDFactureVente FROM vente WHERE NumeroVente = ?
        )
    ");
    $stmt->execute([$numeroVente]);
    $modes_paiement = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'articles' => $articles_final,
        'client' => [
            'nom' => $venteInfo['NomPrenomClient'],
            'telephone' => $venteInfo['Telephone'],
            'email' => $venteInfo['Adresse_email']
        ],
        'vente' => [
            'numero' => $numeroVente,
            'date' => $venteInfo['DateIns'],
            'montant_total' => (float)$venteInfo['MontantTotal'],
            'montant_remise' => (float)$venteInfo['MontantRemise'],
            'montant_verse' => (float)$venteInfo['MontantVerse'],
            'monnaie_rendue' => (float)$venteInfo['Monnaie'],
            'total_articles' => $total_articles,
            'total_prix_articles' => $total_prix
        ],
        'modes_paiement' => $modes_paiement
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
