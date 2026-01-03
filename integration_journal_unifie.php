<?php
/**
 * SCRIPT D'INTÉGRATION DU JOURNAL UNIFIÉ
 * Remplace progressivement les anciennes fonctions de journalisation
 * Compatible avec le système existant
 */

require_once 'fonction_traitement/fonction.php';
require_once 'fonction_traitement/JournalUnifie.php';

// Fonction pour initialiser le journal unifié
function initJournalUnifie($cnx) {
    static $journalUnifie = null;
    if ($journalUnifie === null) {
        $journalUnifie = new JournalUnifie($cnx);
    }
    return $journalUnifie;
}

/**
 * FONCTIONS DE REMPLACEMENT PROGRESSIF
 * Ces fonctions remplacent les anciennes fonctions journaliser*
 * tout en gardant la compatibilité
 */

/**
 * Remplace journaliserAction()
 */
function journaliserActionUnifie($cnx, $idArticle, $idUtilisateur, $idStock, $action, $description, $stockAvant = null, $stockApres = null) {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        $donnees = [
            'IDARTICLE' => $idArticle,
            'IDSTOCK' => $idStock,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres
        ];
        
        return $journalUnifie->logAction('article', $idArticle, 'article', $action, $description, $donnees);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserAction($cnx, $idArticle, $idUtilisateur, $idStock, $action, $description, $stockAvant, $stockApres);
    }
}

/**
 * Remplace journaliserVente()
 */
function journaliserVenteUnifie($cnx, $idVente, $idUtilisateur, $action, $description = '') {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        // Récupérer les informations de la vente
        $sql = "SELECT v.*, c.NomPrenomClient 
                FROM vente v 
                JOIN client c ON v.IDCLIENT = c.IDCLIENT 
                WHERE v.IDFactureVente = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idVente]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vente) {
            $donnees = [
                'IDVENTE' => $idVente,
                'IDCLIENT' => $vente['IDCLIENT'],
                'MontantTotal' => $vente['MontantTotal'],
                'MontantVerse' => $vente['MontantVerse'],
                'Monnaie' => $vente['Monnaie'],
                'ModePaiement' => $vente['ModePaiement']
            ];
            
            return $journalUnifie->logAction('vente', $idVente, 'vente', $action, $description, $donnees);
        }
        
        return false;
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserVente($cnx, $idVente, $idUtilisateur, $action, $description);
    }
}

/**
 * Remplace journaliserClient()
 */
function journaliserClientUnifie($cnx, $idClient, $idUtilisateur, $action, $description = '') {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        $donnees = [
            'IDCLIENT' => $idClient
        ];
        
        return $journalUnifie->logAction('client', $idClient, 'client', $action, $description, $donnees);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserClient($cnx, $idClient, $idUtilisateur, $action, $description);
    }
}

/**
 * Remplace journaliserConnexion()
 */
function journaliserConnexionUnifie($cnx, $idUtilisateur, $description = '') {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        return $journalUnifie->logAction('connexion', $idUtilisateur, 'connexion', 'CONNEXION', $description);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserConnexion($cnx, $idUtilisateur, $description);
    }
}

/**
 * Remplace journaliserDeconnexion()
 */
function journaliserDeconnexionUnifie($cnx, $idUtilisateur, $description = '') {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        return $journalUnifie->logAction('connexion', $idUtilisateur, 'connexion', 'DECONNEXION', $description);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserDeconnexion($cnx, $idUtilisateur, $description);
    }
}

/**
 * Remplace journaliserCreationArticle()
 */
function journaliserCreationArticleUnifie($cnx, $idArticle, $idUtilisateur, $description = '') {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        $donnees = [
            'IDARTICLE' => $idArticle
        ];
        
        return $journalUnifie->logAction('article', $idArticle, 'article', 'CREATION', $description, $donnees);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserCreationArticle($cnx, $idArticle, $idUtilisateur, $description);
    }
}

/**
 * Remplace journaliserEntreeStock()
 */
function journaliserEntreeStockUnifie($cnx, $idArticle, $idUtilisateur, $quantite, $description = '', $idEntreeStock = 0) {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        // Récupérer le stock actuel
        $sql = "SELECT StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stockAvant = $stock['StockActuel'] ?? 0;
        $stockApres = $stockAvant + $quantite;
        
        $donnees = [
            'IDARTICLE' => $idArticle,
            'IDENTREE_STOCK' => $idEntreeStock,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres
        ];
        
        return $journalUnifie->logAction('stock', $idEntreeStock, 'entree_stock', 'ENTREE', $description, $donnees);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserEntreeStock($cnx, $idArticle, $idUtilisateur, $quantite, $description, $idEntreeStock);
    }
}

/**
 * Remplace journaliserVenteArticle()
 */
function journaliserVenteArticleUnifie($cnx, $idArticle, $idUtilisateur, $quantite, $numeroVente, $description = '') {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        // Récupérer le stock actuel
        $sql = "SELECT StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stockAvant = $stock['StockActuel'] ?? 0;
        $stockApres = $stockAvant - $quantite;
        
        $donnees = [
            'IDARTICLE' => $idArticle,
            'IDVENTE' => $numeroVente,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres
        ];
        
        return $journalUnifie->logAction('vente', $numeroVente, 'vente', 'SORTIE', $description, $donnees);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserVenteArticle($cnx, $idArticle, $idUtilisateur, $quantite, $numeroVente, $description);
    }
}

/**
 * Remplace journaliserCorrectionStock()
 */
function journaliserCorrectionStockUnifie($cnx, $idArticle, $idUtilisateur, $stock_avant, $stock_apres, $motif = '') {
    try {
        $journalUnifie = initJournalUnifie($cnx);
        
        $difference = $stock_apres - $stock_avant;
        
        $donnees = [
            'IDARTICLE' => $idArticle,
            'stock_avant' => $stock_avant,
            'stock_apres' => $stock_apres,
            'difference' => $difference,
            'motif_correction' => $motif
        ];
        
        return $journalUnifie->logAction('correction_stock', $idArticle, 'correction_stock', 'CORRECTION', "Correction de stock: $motif", $donnees);
        
    } catch (Exception $e) {
        // Fallback vers l'ancienne fonction
        return journaliserCorrectionStock($cnx, $idArticle, $idUtilisateur, $stock_avant, $stock_apres, $motif);
    }
}

/**
 * FONCTION DE TEST D'INTÉGRATION
 * Teste si le système unifié fonctionne correctement
 */
function testerJournalUnifie($cnx) {
    try {
        $journalUnifie = new JournalUnifie($cnx);
        
        // Test de journalisation
        $result = $journalUnifie->logAction(
            'test',
            999,
            'test',
            'CREATION',
            'Test d\'intégration du journal unifié'
        );
        
        if ($result) {
            return "✅ Journal unifié fonctionne correctement";
        } else {
            return "❌ Erreur dans le journal unifié";
        }
        
    } catch (Exception $e) {
        return "❌ Erreur: " . $e->getMessage();
    }
}

// Si ce fichier est appelé directement, afficher les informations
if (basename($_SERVER['PHP_SELF']) == 'integration_journal_unifie.php') {
    echo "<h2>Intégration du Journal Unifié</h2>";
    echo "<p>Ce fichier contient les fonctions de remplacement pour l'intégration progressive du journal unifié.</p>";
    echo "<p>Les fonctions sont prêtes à être utilisées dans votre application.</p>";
}
?>
