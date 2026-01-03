<?php
include('../db/connecting.php');
include('fonction.php');
include('journal_functions.php');

session_start();

if (!isset($_SESSION['nom_utilisateur'])) {
    header('Location: ../connexion.php');
    exit();
}

// Fonction pour gérer l'entrée en stock
function entreeStock($cnx, $idArticle, $quantite, $description = '') {
    try {
        // Récupérer le stock actuel
        $sql = "SELECT IDSTOCK, StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Stock non trouvé pour cet article");
        }

        $stockAvant = $stock['StockActuel'];
        $stockApres = $stockAvant + $quantite;

        // Mettre à jour le stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDSTOCK = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$stockApres, $stock['IDSTOCK']]);

        // Journaliser l'action
        journaliserStock(
            $cnx,
            $stock['IDSTOCK'],
            $_SESSION['id_utilisateur'],
            'ENTREE',
            "Entrée en stock de $quantite unités. " . $description
        );

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de l'entrée en stock: " . $e->getMessage());
        return false;
    }
}

// Fonction pour gérer la sortie de stock
function sortieStock($cnx, $idArticle, $quantite, $description = '') {
    try {
        // Récupérer le stock actuel
        $sql = "SELECT IDSTOCK, StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Stock non trouvé pour cet article");
        }

        if ($stock['StockActuel'] < $quantite) {
            throw new Exception("Stock insuffisant");
        }

        $stockAvant = $stock['StockActuel'];
        $stockApres = $stockAvant - $quantite;

        // Mettre à jour le stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDSTOCK = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$stockApres, $stock['IDSTOCK']]);

        // Journaliser l'action
        journaliserStock(
            $cnx,
            $stock['IDSTOCK'],
            $_SESSION['id_utilisateur'],
            'SORTIE',
            "Sortie de stock de $quantite unités. " . $description
        );

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la sortie de stock: " . $e->getMessage());
        return false;
    }
}

// Fonction pour gérer la correction de stock
function correctionStock($cnx, $idArticle, $nouvelleQuantite, $description = '') {
    try {
        // Récupérer le stock actuel
        $sql = "SELECT IDSTOCK, StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Stock non trouvé pour cet article");
        }

        $stockAvant = $stock['StockActuel'];
        $stockApres = $nouvelleQuantite;

        // Mettre à jour le stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDSTOCK = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$stockApres, $stock['IDSTOCK']]);

        // Journaliser l'action
        journaliserStock(
            $cnx,
            $stock['IDSTOCK'],
            $_SESSION['id_utilisateur'],
            'CORRECTION',
            "Correction de stock: $stockAvant -> $stockApres. " . $description
        );

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la correction de stock: " . $e->getMessage());
        return false;
    }
}

// Fonction pour gérer la vente d'un article
function venteArticle($cnx, $idArticle, $quantite, $idVente, $description = '') {
    try {
        // Récupérer le stock actuel
        $sql = "SELECT IDSTOCK, StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Stock non trouvé pour cet article");
        }

        if ($stock['StockActuel'] < $quantite) {
            throw new Exception("Stock insuffisant pour la vente");
        }

        $stockAvant = $stock['StockActuel'];
        $stockApres = $stockAvant - $quantite;

        // Mettre à jour le stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDSTOCK = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$stockApres, $stock['IDSTOCK']]);

        // Journaliser l'action de stock
        journaliserStock(
            $cnx,
            $stock['IDSTOCK'],
            $_SESSION['id_utilisateur'],
            'VENTE',
            "Vente de $quantite unités (Vente #$idVente). " . $description
        );

        // Journaliser l'action de vente
        journaliserVente(
            $cnx,
            $idVente,
            $_SESSION['id_utilisateur'],
            'VENTE_ARTICLE',
            "Vente de $quantite unités de l'article #$idArticle"
        );

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la vente: " . $e->getMessage());
        return false;
    }
}

// Traitement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idArticle = $_POST['idArticle'] ?? null;
    $quantite = $_POST['quantite'] ?? null;
    $description = $_POST['description'] ?? '';

    if (!$idArticle || !$quantite) {
        header('Location: ../journal.php?error=' . urlencode('Paramètres manquants'));
        exit();
    }

    $success = false;
    switch ($action) {
        case 'entree':
            $success = entreeStock($cnx, $idArticle, $quantite, $description);
            break;
        case 'sortie':
            $success = sortieStock($cnx, $idArticle, $quantite, $description);
            break;
        case 'correction':
            $success = correctionStock($cnx, $idArticle, $quantite, $description);
            break;
        case 'vente':
            $idVente = $_POST['idVente'] ?? null;
            if (!$idVente) {
                header('Location: ../journal.php?error=' . urlencode('ID de vente manquant'));
                exit();
            }
            $success = venteArticle($cnx, $idArticle, $quantite, $idVente, $description);
            break;
        default:
            header('Location: ../journal.php?error=' . urlencode('Action non reconnue'));
            exit();
    }

    if ($success) {
        header('Location: ../journal.php?success=' . urlencode('Action effectuée avec succès'));
    } else {
        header('Location: ../journal.php?error=' . urlencode('Erreur lors de l\'action'));
    }
    exit();
}
?> 