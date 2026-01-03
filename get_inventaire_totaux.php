<?php
session_start();
include('db/connecting.php');

if (!isset($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

$idInventaire = isset($_GET['IDINVENTAIRE']) ? intval($_GET['IDINVENTAIRE']) : 0;

if ($idInventaire <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID d\'inventaire invalide']);
    exit();
}

try {
    // Vérifier le statut de l'inventaire
    $stmt = $cnx->prepare("SELECT StatutInventaire FROM inventaire WHERE IDINVENTAIRE = ?");
    $stmt->execute([$idInventaire]);
    $statut = $stmt->fetchColumn();

    if (!$statut) {
        throw new Exception("Inventaire non trouvé");
    }

    if ($statut === 'en_attente') {
        // --- INVENTAIRE EN ATTENTE ---
        // Valeurs théoriques fixes depuis inventaire_ligne
        // Valeurs physiques depuis inventaire_temp (saisie en cours)
        $query = "
            SELECT
                SUM(il.valeur_theorique_achat) AS valeur_theorique_achat,
                SUM(COALESCE(it.qte_physique, 0) * a.PrixAchatHT) AS valeur_physique_achat,
                SUM((COALESCE(it.qte_physique, 0) * a.PrixAchatHT) - il.valeur_theorique_achat) AS valeur_ecart_achat,

                SUM(il.valeur_theorique_vente) AS valeur_theorique_vente,
                SUM(COALESCE(it.qte_physique, 0) * a.PrixVenteTTC) AS valeur_physique_vente,
                SUM((COALESCE(it.qte_physique, 0) * a.PrixVenteTTC) - il.valeur_theorique_vente) AS valeur_ecart_vente
            FROM inventaire_ligne il
            LEFT JOIN inventaire_temp it 
                ON il.id_inventaire = it.id_inventaire 
                AND il.id_article = it.id_article
                AND it.id_utilisateur = ?
            LEFT JOIN article a ON il.id_article = a.IDARTICLE
            WHERE il.id_inventaire = ?
        ";
        $params = [$_SESSION['id_utilisateur'], $idInventaire];

    } else {
        // --- INVENTAIRE TERMINÉ ---
        // Tout provient d'inventaire_ligne
        $query = "
            SELECT
                SUM(il.valeur_theorique_achat) AS valeur_theorique_achat,
                SUM(COALESCE(il.qte_physique, 0) * a.PrixAchatHT) AS valeur_physique_achat,
                SUM((COALESCE(il.qte_physique, 0) * a.PrixAchatHT) - il.valeur_theorique_achat) AS valeur_ecart_achat,

                SUM(il.valeur_theorique_vente) AS valeur_theorique_vente,
                SUM(COALESCE(il.qte_physique, 0) * a.PrixVenteTTC) AS valeur_physique_vente,
                SUM((COALESCE(il.qte_physique, 0) * a.PrixVenteTTC) - il.valeur_theorique_vente) AS valeur_ecart_vente
            FROM inventaire_ligne il
            LEFT JOIN article a ON il.id_article = a.IDARTICLE
            WHERE il.id_inventaire = ?
        ";
        $params = [$idInventaire];
    }

    $stmt = $cnx->prepare($query);
    $stmt->execute($params);
    $totaux = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'totaux' => [
            'valeur_theorique_achat'  => floatval($totaux['valeur_theorique_achat'] ?? 0),
            'valeur_physique_achat'   => floatval($totaux['valeur_physique_achat'] ?? 0),
            'valeur_ecart_achat'      => floatval($totaux['valeur_ecart_achat'] ?? 0),

            'valeur_theorique_vente'  => floatval($totaux['valeur_theorique_vente'] ?? 0),
            'valeur_physique_vente'   => floatval($totaux['valeur_physique_vente'] ?? 0),
            'valeur_ecart_vente'      => floatval($totaux['valeur_ecart_vente'] ?? 0),
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
