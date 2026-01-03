<?php
/**
 * EXEMPLES D'UTILISATION DE LA CLASSE JOURNAL UNIFIÉE
 * Remplace toutes les fonctions de journalisation existantes
 */

// Inclure la connexion et la classe
include('db/connecting.php');
include('fonction_traitement/JournalUnifie.php');

// Créer une instance de la classe
$journal = new JournalUnifie($cnx);

// =====================================================
// EXEMPLES D'UTILISATION POUR CHAQUE MODULE
// =====================================================

echo "<h1>EXEMPLES D'UTILISATION DE LA CLASSE JOURNAL UNIFIÉE</h1>";

// =====================================================
// 1. JOURNALISATION D'UN ARTICLE
// =====================================================
echo "<h2>1. Journalisation d'un article</h2>";

// Création d'un article
$journal->logArticle(123, 'CREATION', 'Création d\'un nouvel article : IPHONE 15', [
    'stock_avant' => 0,
    'stock_apres' => 0
]);

// Modification d'un article
$journal->logArticle(123, 'MODIFICATION', 'Modification du prix de l\'article IPHONE 15', [
    'stock_avant' => 10,
    'stock_apres' => 10
]);

echo "✅ Article journalisé<br>";

// =====================================================
// 2. JOURNALISATION DU STOCK
// =====================================================
echo "<h2>2. Journalisation du stock</h2>";

// Entrée en stock
$journal->logStock(95, 'ENTREE', 'Entrée en stock de IPHONE 15 - Fournisseur: Apple - Quantité: 10', [
    'IDARTICLE' => 123,
    'IDENTREE_STOCK' => 456,
    'stock_avant' => 5,
    'stock_apres' => 15
]);

// Sortie de stock
$journal->logStock(95, 'SORTIE', 'Sortie de stock pour vente - Client: Jean Dupont', [
    'IDARTICLE' => 123,
    'IDVENTE' => 789,
    'IDCLIENT' => 12,
    'stock_avant' => 15,
    'stock_apres' => 14
]);

echo "✅ Stock journalisé<br>";

// =====================================================
// 3. JOURNALISATION D'UNE VENTE
// =====================================================
echo "<h2>3. Journalisation d'une vente</h2>";

// Vente avec numéro de série
$journal->logVente(789, 'SORTIE', 'Vente de l\'article IPHONE 15 - Prix: 500000.00 FCFA - Client: Jean Dupont', [
    'IDARTICLE' => 123,
    'IDSTOCK' => 95,
    'IDCLIENT' => 12,
    'MontantTotal' => 500000.00,
    'MontantVerse' => 500000.00,
    'Monnaie' => 0.00,
    'ModePaiement' => 'Espèces',
    'numero_serie' => 'IPH-0001',
    'ancien_statut' => 'DISPONIBLE',
    'nouveau_statut' => 'VENDU',
    'stock_avant' => 15,
    'stock_apres' => 14
]);

echo "✅ Vente journalisée<br>";

// =====================================================
// 4. JOURNALISATION D'UN CLIENT
// =====================================================
echo "<h2>4. Journalisation d'un client</h2>";

// Création d'un client
$journal->logClient(12, 'CREATION', 'Création d\'un nouveau client : Jean Dupont', [
    'IDCLIENT' => 12
]);

// Modification d'un client
$journal->logClient(12, 'MODIFICATION', 'Modification des informations du client Jean Dupont', [
    'IDCLIENT' => 12
]);

echo "✅ Client journalisé<br>";

// =====================================================
// 5. JOURNALISATION D'UN NUMÉRO DE SÉRIE
// =====================================================
echo "<h2>5. Journalisation d'un numéro de série</h2>";

// Ajout d'un numéro de série
$journal->logNumeroSerie('IPH-0001', 'AJOUT', 'Ajout du numéro de série IPH-0001', [
    'IDARTICLE' => 123,
    'ancien_statut' => null,
    'nouveau_statut' => 'DISPONIBLE',
    'motif' => 'Entrée en stock'
]);

// Affectation d'un numéro de série
$journal->logNumeroSerie('IPH-0001', 'AFFECTATION', 'Numéro de série IPH-0001 affecté à la vente #789', [
    'IDARTICLE' => 123,
    'IDVENTE' => 789,
    'ancien_statut' => 'DISPONIBLE',
    'nouveau_statut' => 'VENDU',
    'motif' => 'Vente client'
]);

echo "✅ Numéro de série journalisé<br>";

// =====================================================
// 6. JOURNALISATION D'UNE COMMANDE
// =====================================================
echo "<h2>6. Journalisation d'une commande</h2>";

// Création d'une commande
$journal->logCommande(101, 'CREATION', 'Création de la commande BON00001', [
    'IDFOURNISSEUR' => 5
]);

// Validation d'une commande
$journal->logCommande(101, 'VALIDATION', 'Commande BON00001 validée - Fournisseur: Apple - Total: 1500000 F.CFA', [
    'IDFOURNISSEUR' => 5,
    'MontantTotal' => 1500000.00
]);

echo "✅ Commande journalisée<br>";

// =====================================================
// 7. JOURNALISATION D'UNE CONNEXION
// =====================================================
echo "<h2>7. Journalisation d'une connexion</h2>";

// Connexion utilisateur
$journal->logConnexion('CONNEXION', 'Connexion de l\'utilisateur Jean Dupont');

// Déconnexion utilisateur
$journal->logConnexion('DECONNEXION', 'Déconnexion de l\'utilisateur Jean Dupont');

echo "✅ Connexion journalisée<br>";

// =====================================================
// 8. JOURNALISATION D'UNE CORRECTION DE STOCK
// =====================================================
echo "<h2>8. Journalisation d'une correction de stock</h2>";

// Correction de stock
$journal->logCorrectionStock(102, 'CORRECTION', 'Correction de stock pour IPHONE 15 - Inventaire physique', [
    'IDARTICLE' => 123,
    'stock_avant' => 10,
    'stock_apres' => 8,
    'difference' => -2,
    'motif_correction' => 'Inventaire physique'
]);

echo "✅ Correction de stock journalisée<br>";

// =====================================================
// 9. JOURNALISATION COMPTABLE
// =====================================================
echo "<h2>9. Journalisation comptable</h2>";

// Opération comptable
$journal->logComptabilite(201, 'ECRITURE', 'Écriture comptable pour vente #789', [
    'DateOperation' => date('Y-m-d'),
    'NumeroPiece' => 'VTE-789',
    'Compte' => '701',
    'Libelle' => 'Vente de marchandises',
    'Debit' => 0.00,
    'Credit' => 500000.00
]);

echo "✅ Comptabilité journalisée<br>";

// =====================================================
// 10. RÉCUPÉRATION DES DONNÉES
// =====================================================
echo "<h2>10. Récupération des données</h2>";

// Journal des articles
$journalArticles = $journal->getJournalModule('article', ['limit' => 5]);
echo "📊 Articles journalisés : " . count($journalArticles) . "<br>";

// Journal des ventes
$journalVentes = $journal->getJournalModule('vente', ['limit' => 5]);
echo "📊 Ventes journalisées : " . count($journalVentes) . "<br>";

// Journal complet
$journalComplet = $journal->getJournalComplet(['limit' => 10]);
echo "📊 Total des actions : " . count($journalComplet) . "<br>";

// Historique d'un article
$historiqueArticle = $journal->getHistoriqueEntite('article', 123, 10);
echo "📊 Historique de l'article 123 : " . count($historiqueArticle) . " actions<br>";

// Statistiques
$statistiques = $journal->getStatistiques(['date_debut' => date('Y-m-01')]);
echo "📊 Statistiques du mois : " . count($statistiques) . " groupes d'actions<br>";

echo "<br><h2>✅ TOUS LES EXEMPLES ONT ÉTÉ EXÉCUTÉS AVEC SUCCÈS !</h2>";

// =====================================================
// MIGRATION DES DONNÉES EXISTANTES
// =====================================================
echo "<h2>11. Migration des données existantes</h2>";

// Exemple de migration depuis journal_article
try {
    $sql = "SELECT * FROM journal_article ORDER BY date_action DESC LIMIT 5";
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $anciensArticles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Articles existants à migrer : " . count($anciensArticles) . "<br>";
    
    foreach ($anciensArticles as $article) {
        $journal->logArticle(
            $article['IDARTICLE'],
            $article['action'],
            $article['description_action'],
            [
                'stock_avant' => $article['stock_avant'],
                'stock_apres' => $article['stock_apres']
            ]
        );
    }
    
    echo "✅ Migration des articles terminée<br>";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la migration : " . $e->getMessage() . "<br>";
}

echo "<br><h1>🎉 CLASSE JOURNAL UNIFIÉE PRÊTE À L'UTILISATION !</h1>";
?>
