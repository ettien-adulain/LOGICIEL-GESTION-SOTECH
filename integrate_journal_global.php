<?php
/**
 * INTÉGRATION GLOBALE DE LA JOURNALISATION
 * Ce fichier doit être inclus dans tous les fichiers qui effectuent des actions
 * pour automatiquement journaliser toutes les opérations
 */

// Inclure la classe JournalUnifie
require_once 'fonction_traitement/JournalUnifie.php';

// Fonction globale pour journaliser automatiquement (renommée pour éviter les conflits)
function journaliserActionUnifie($cnx, $module, $entiteId, $entiteType, $action, $description, $donnees = []) {
    try {
        $journalUnifie = new JournalUnifie($cnx);
        return $journalUnifie->logAction($module, $entiteId, $entiteType, $action, $description, $donnees);
    } catch (Exception $e) {
        error_log("Erreur journalisation: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser les articles
function journaliserArticle($cnx, $idArticle, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'article', $idArticle, 'article', $action, $description, $donnees);
}

// Fonction pour journaliser les clients
function journaliserClient($cnx, $idClient, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'client', $idClient, 'client', $action, $description, $donnees);
}

// Fonction pour journaliser le stock
function journaliserStock($cnx, $idStock, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'stock', $idStock, 'stock', $action, $description, $donnees);
}

// Fonction pour journaliser les ventes
function journaliserVente($cnx, $idVente, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'vente', $idVente, 'vente', $action, $description, $donnees);
}

// Fonction pour journaliser les connexions
function journaliserConnexion($cnx, $action, $description, $donnees = []) {
    $idUtilisateur = $_SESSION['id_utilisateur'] ?? 1;
    return journaliserActionUnifie($cnx, 'connexion', $idUtilisateur, 'utilisateur', $action, $description, $donnees);
}

// Fonction pour journaliser les commandes
function journaliserCommande($cnx, $idCommande, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'commande', $idCommande, 'commande', $action, $description, $donnees);
}

// Auto-journalisation des erreurs
function journaliserErreur($cnx, $erreur, $fichier, $ligne) {
    $description = "Erreur dans $fichier ligne $ligne: $erreur";
    return journaliserConnexion($cnx, 'ERREUR', $description);
}

// Auto-journalisation des connexions
function autoJournaliserConnexion($cnx) {
    if (isset($_SESSION['id_utilisateur'])) {
        journaliserConnexion($cnx, 'CONNEXION', 'Utilisateur connecté');
    }
}

// Auto-journalisation des déconnexions
function autoJournaliserDeconnexion($cnx) {
    if (isset($_SESSION['id_utilisateur'])) {
        journaliserConnexion($cnx, 'DECONNEXION', 'Utilisateur déconnecté');
    }
}

// Fonction pour journaliser les modifications de données
function journaliserModification($cnx, $table, $id, $champsModifies, $valeursAvant, $valeursApres) {
    $description = "Modification de $table ID $id: " . implode(', ', $champsModifies);
    $donnees = [
        'valeurs_avant' => json_encode($valeursAvant),
        'valeurs_apres' => json_encode($valeursApres)
    ];
    
    return journaliserActionUnifie($cnx, $table, $id, $table, 'MODIFICATION', $description, $donnees);
}

// Fonction pour journaliser les suppressions
function journaliserSuppression($cnx, $table, $id, $description = '') {
    if (empty($description)) {
        $description = "Suppression de $table ID $id";
    }
    
    return journaliserActionUnifie($cnx, $table, $id, $table, 'SUPPRESSION', $description);
}

// Fonction pour journaliser les créations
function journaliserCreation($cnx, $table, $id, $description = '') {
    if (empty($description)) {
        $description = "Création de $table ID $id";
    }
    
    return journaliserActionUnifie($cnx, $table, $id, $table, 'CREATION', $description);
}

// Fonction pour journaliser les entrées en stock
function journaliserEntreeStock($cnx, $idEntreeStock, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'stock', $idEntreeStock, 'entree_stock', $action, $description, $donnees);
}

// Fonction pour journaliser les corrections de stock
function journaliserCorrectionStock($cnx, $idCorrection, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'correction_stock', $idCorrection, 'correction_stock', $action, $description, $donnees);
}

// Fonction pour journaliser les inventaires
function journaliserInventaire($cnx, $idInventaire, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'inventaire', $idInventaire, 'inventaire', $action, $description, $donnees);
}

// Fonction pour journaliser les dossiers SAV
function journaliserSAV($cnx, $idSAV, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'sav', $idSAV, 'sav', $action, $description, $donnees);
}

// Fonction pour journaliser les numéros de série
function journaliserNumeroSerie($cnx, $idNumeroSerie, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'numero_serie', $idNumeroSerie, 'numero_serie', $action, $description, $donnees);
}

// Fonction pour journaliser les proforma
function journaliserProforma($cnx, $idProforma, $action, $description, $donnees = []) {
    return journaliserActionUnifie($cnx, 'proforma', $idProforma, 'proforma', $action, $description, $donnees);
}

// Auto-journalisation au début de chaque script
if (isset($cnx)) {
    // Journaliser l'accès à la page
    $page = basename($_SERVER['PHP_SELF']);
    journaliserConnexion($cnx, 'ACCES', "Accès à la page $page");
}
?>
