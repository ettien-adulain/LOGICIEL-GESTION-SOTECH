<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('fonction.php');

function debug_log($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message);
}

// Fonction pour journaliser les actions sur les ventes
function journaliserVente($cnx, $idVente, $idUtilisateur, $action, $description = '') {
    try {
        // Récupérer les informations de la vente
        $sql = "SELECT v.*, c.NomPrenomClient 
                FROM vente v 
                JOIN client c ON v.IDCLIENT = c.IDCLIENT 
                WHERE v.NumeroVente = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idVente]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vente) {
            throw new Exception("Vente non trouvée");
        }

        // Insérer dans le journal des ventes
        $sql = "INSERT INTO journal_vente (NumeroVente, IDUTILISATEUR, action, description_action, montant_avant, montant_apres) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([
            $idVente,
            $idUtilisateur,
            $action,
            $description,
            $vente['MontantTotal'],
            $vente['MontantTotal']
        ]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Action sur vente - Vente #%s, Client: %s, Action: %s, Description: %s",
            $idVente,
            $vente['NomPrenomClient'],
            $action,
            $description
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de la vente: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser les actions sur les clients
function journaliserClient($cnx, $idClient, $idUtilisateur, $action, $description = '') {
    try {
        // Récupérer les informations du client
        $sql = "SELECT * FROM client WHERE IDCLIENT = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idClient]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            throw new Exception("Client non trouvé");
        }

        // Insérer dans le journal des clients
        $sql = "INSERT INTO journal_client (IDCLIENT, IDUTILISATEUR, action, description_action) 
                VALUES (?, ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idClient, $idUtilisateur, $action, $description]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Action sur client - Client: %s, Action: %s, Description: %s",
            $client['NomPrenomClient'],
            $action,
            $description
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation du client: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser les actions sur les utilisateurs
function journaliserUtilisateur($cnx, $idUtilisateur, $idUtilisateurAction, $action, $description = '') {
    try {
        // Récupérer les informations de l'utilisateur
        $sql = "SELECT * FROM utilisateur WHERE IDUTILISATEUR = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idUtilisateur]);
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$utilisateur) {
            throw new Exception("Utilisateur non trouvé");
        }

        // Insérer dans le journal des utilisateurs
        $sql = "INSERT INTO journal_utilisateur (IDUTILISATEUR, IDUTILISATEUR_ACTION, action, description_action) 
                VALUES (?, ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idUtilisateur, $idUtilisateurAction, $action, $description]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Action sur utilisateur - Utilisateur: %s, Action: %s, Description: %s",
            $utilisateur['NomPrenom'],
            $action,
            $description
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de l'utilisateur: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser les actions sur les stocks
function journaliserStock($cnx, $idStock, $idUtilisateur, $action, $description = '') {
    try {
        // Récupérer les informations du stock
        $sql = "SELECT s.*, a.libelle 
                FROM stock s 
                JOIN article a ON s.IDARTICLE = a.IDARTICLE 
                WHERE s.IDSTOCK = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idStock]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Stock non trouvé");
        }

        // Insérer dans le journal des stocks
        $sql = "INSERT INTO journal_stock (IDSTOCK, IDUTILISATEUR, action, description_action, stock_avant, stock_apres) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([
            $idStock,
            $idUtilisateur,
            $action,
            $description,
            $stock['StockActuel'],
            $stock['StockActuel']
        ]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Action sur stock - Article: %s, Action: %s, Description: %s",
            $stock['libelle'],
            $action,
            $description
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation du stock: " . $e->getMessage());
        return false;
    }
}

// Fonction pour récupérer l'historique complet d'un élément
function getHistoriqueComplet($cnx, $type, $id, $limit = 1000) {
    try {
        $table = "journal_" . strtolower($type);
        $idColumn = "ID" . strtoupper($type);
        
        $sql = "SELECT j.*, u.NomPrenom as nom_utilisateur 
                FROM $table j 
                JOIN utilisateur u ON j.IDUTILISATEUR = u.IDUTILISATEUR 
                WHERE j.$idColumn = ? 
                ORDER BY j.date_action DESC 
                LIMIT ?";
        
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération de l'historique: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les statistiques globales des actions
 * @param PDO $cnx Connexion à la base de données
 * @param string $startDate Date de début (optionnel)
 * @param string $endDate Date de fin (optionnel)
 * @return array Tableau contenant les statistiques
 */
function getStatistiquesGlobales($cnx, $startDate = '', $endDate = '') {
    try {
        $stats = [
            'ventes' => [],
            'articles' => [],
            'actions' => []
        ];

        // Statistiques des ventes
        $sqlVentes = "SELECT 
            COUNT(DISTINCT v.NumeroVente) as total_ventes,
            COALESCE(SUM(v.MontantTotal), 0) as montant_total,
            COUNT(DISTINCT v.IDCLIENT) as clients_actifs
            FROM vente v
            WHERE 1=1";
        
        if ($startDate) {
            $sqlVentes .= " AND DATE(v.DateIns) >= :startDate";
        }
        if ($endDate) {
            $sqlVentes .= " AND DATE(v.DateIns) <= :endDate";
        }

        $stmt = $cnx->prepare($sqlVentes);
        if ($startDate) $stmt->bindParam(':startDate', $startDate);
        if ($endDate) $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        $stats['ventes'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Statistiques des articles
        $sqlArticles = "SELECT 
            COUNT(DISTINCT a.IDARTICLE) as total_articles,
            COALESCE(SUM(s.StockActuel), 0) as stock_total
            FROM article a
            LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE
            WHERE 1=1";
        
        if ($startDate) {
            $sqlArticles .= " AND DATE(a.DateIns) >= :startDate";
        }
        if ($endDate) {
            $sqlArticles .= " AND DATE(a.DateIns) <= :endDate";
        }

        $stmt = $cnx->prepare($sqlArticles);
        if ($startDate) $stmt->bindParam(':startDate', $startDate);
        if ($endDate) $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        $stats['articles'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Statistiques des actions
        $sqlActions = "SELECT 
            action,
            COUNT(*) as nombre_actions
            FROM journal_article
            WHERE 1=1";
        
        if ($startDate) {
            $sqlActions .= " AND DATE(date_action) >= :startDate";
        }
        if ($endDate) {
            $sqlActions .= " AND DATE(date_action) <= :endDate";
        }
        
        $sqlActions .= " GROUP BY action ORDER BY nombre_actions DESC";

        $stmt = $cnx->prepare($sqlActions);
        if ($startDate) $stmt->bindParam(':startDate', $startDate);
        if ($endDate) $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        $stats['actions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    } catch (PDOException $e) {
        error_log("Erreur dans getStatistiquesGlobales: " . $e->getMessage());
        return [
            'ventes' => ['total_ventes' => 0, 'montant_total' => 0, 'clients_actifs' => 0],
            'articles' => ['total_articles' => 0, 'stock_total' => 0],
            'actions' => []
        ];
    }
}

// Fonction pour journaliser la création d'un article
function journaliserCreationArticle($cnx, $idArticle, $idUtilisateur, $description = '') {
    try {
        error_log("Début de journalisation - Article ID: " . $idArticle);
        error_log("Utilisateur ID: " . $idUtilisateur);
        
        // Récupérer les informations de l'article
        $sql = "SELECT * FROM article WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            throw new Exception("Article non trouvé");
        }

        // Insérer dans le journal des articles
        $sql = "INSERT INTO journal_article (IDARTICLE, IDUTILISATEUR, action, description_action, stock_avant, stock_apres) 
                VALUES (?, ?, 'CREATION', ?, 0, 0)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle, $idUtilisateur, $description]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Création article - Article: %s, Description: %s",
            $article['libelle'],
            $description
        ));

        error_log("Journalisation réussie");
        return true;
    } catch (Exception $e) {
        error_log("Erreur de journalisation: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser une entrée en stock
function journaliserEntreeStock($cnx, $idArticle, $idUtilisateur, $quantite, $description = '') {
    try {
        // Récupérer le stock actuel
        $sql = "SELECT StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stockActuel = $stmt->fetchColumn() ?: 0;

        // Calculer le nouveau stock
        $nouveauStock = $stockActuel + $quantite;

        // Mettre à jour le stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$nouveauStock, $idArticle]);

        // Insérer dans le journal des articles
        $sql = "INSERT INTO journal_article (IDARTICLE, IDUTILISATEUR, action, description_action, stock_avant, stock_apres) 
                VALUES (?, ?, 'ENTREE', ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle, $idUtilisateur, $description, $stockActuel, $nouveauStock]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Entrée en stock - Article ID: %s, Quantité: %d, Stock avant: %d, Stock après: %d",
            $idArticle,
            $quantite,
            $stockActuel,
            $nouveauStock
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de l'entrée en stock: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser une vente
function journaliserVenteArticle($cnx, $idArticle, $idUtilisateur, $quantite, $numeroVente, $description = '') {
    try {
        // Récupérer le stock actuel
        $sql = "SELECT StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stockActuel = $stmt->fetchColumn() ?: 0;

        // Vérifier si le stock est suffisant
        if ($stockActuel < $quantite) {
            throw new Exception("Stock insuffisant pour cette vente");
        }

        // Calculer le nouveau stock
        $nouveauStock = $stockActuel - $quantite;

        // Mettre à jour le stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$nouveauStock, $idArticle]);

        // Insérer dans le journal des articles
        $sql = "INSERT INTO journal_article (IDARTICLE, IDUTILISATEUR, action, description_action, stock_avant, stock_apres) 
                VALUES (?, ?, 'VENTE', ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle, $idUtilisateur, $description, $stockActuel, $nouveauStock]);

        // Journaliser la vente
        journaliserVente($cnx, $numeroVente, $idUtilisateur, 'VENTE', $description);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Vente article - Article ID: %s, Quantité: %d, Stock avant: %d, Stock après: %d, Vente #%s",
            $idArticle,
            $quantite,
            $stockActuel,
            $nouveauStock,
            $numeroVente
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de la vente: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser une correction de stock
function journaliserCorrectionStock($cnx, $idArticle, $idUtilisateur, $nouveauStock, $description = '') {
    try {
        // Récupérer le stock actuel
        $sql = "SELECT StockActuel FROM stock WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle]);
        $stockActuel = $stmt->fetchColumn() ?: 0;

        // Mettre à jour le stock
        $sql = "UPDATE stock SET StockActuel = ? WHERE IDARTICLE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$nouveauStock, $idArticle]);

        // Insérer dans le journal des articles
        $sql = "INSERT INTO journal_article (IDARTICLE, IDUTILISATEUR, action, description_action, stock_avant, stock_apres) 
                VALUES (?, ?, 'CORRECTION', ?, ?, ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idArticle, $idUtilisateur, $description, $stockActuel, $nouveauStock]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Correction stock - Article ID: %s, Stock avant: %d, Stock après: %d",
            $idArticle,
            $stockActuel,
            $nouveauStock
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de la correction de stock: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser la création d'une catégorie
function journaliserCreationCategorie($cnx, $idCategorie, $idUtilisateur, $description = '') {
    try {
        // Récupérer les informations de la catégorie
        $sql = "SELECT * FROM categorie WHERE IDCATEGORIE = ?";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idCategorie]);
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$categorie) {
            throw new Exception("Catégorie non trouvée");
        }

        // Insérer dans le journal des catégories
        $sql = "INSERT INTO journal_categorie (IDCATEGORIE, IDUTILISATEUR, action, description_action) 
                VALUES (?, ?, 'CREATION', ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idCategorie, $idUtilisateur, $description]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Création catégorie - Catégorie: %s, Description: %s",
            $categorie['libelle'],
            $description
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de la création de catégorie: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser la connexion d'un utilisateur
function journaliserConnexion($cnx, $idUtilisateur, $description = '') {
    try {
        // Insérer dans le journal des connexions
        $sql = "INSERT INTO journal_connexion (IDUTILISATEUR, action, description_action) 
                VALUES (?, 'CONNEXION', ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idUtilisateur, $description]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Connexion utilisateur - ID: %s, Description: %s",
            $idUtilisateur,
            $description
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de la connexion: " . $e->getMessage());
        return false;
    }
}

// Fonction pour journaliser la déconnexion d'un utilisateur
function journaliserDeconnexion($cnx, $idUtilisateur, $description = '') {
    try {
        // Insérer dans le journal des connexions
        $sql = "INSERT INTO journal_connexion (IDUTILISATEUR, action, description_action) 
                VALUES (?, 'DECONNEXION', ?)";
        $stmt = $cnx->prepare($sql);
        $stmt->execute([$idUtilisateur, $description]);

        // Journaliser dans les logs système
        error_log(sprintf(
            "Déconnexion utilisateur - ID: %s, Description: %s",
            $idUtilisateur,
            $description
        ));

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation de la déconnexion: " . $e->getMessage());
        return false;
    }
}
?> 