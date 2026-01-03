<?php
/**
 * CLASSE DE JOURNALISATION UNIFIÉE
 * Remplace toutes les fonctions de journalisation existantes
 * Utilise la table journal_unifie pour centraliser tous les logs
 */

if (!class_exists('JournalUnifie')) {
class JournalUnifie {
    private $cnx;
    
    public function __construct($cnx) {
        $this->cnx = $cnx;
    }
    
    /**
     * MÉTHODE PRINCIPALE DE JOURNALISATION
     * Remplace toutes les fonctions journaliser* existantes
     * Version améliorée avec gestion d'erreurs et validation renforcée
     */
    public function logAction($module, $entiteId, $entiteType, $action, $description, $donnees = []) {
        try {
            // Validation des paramètres obligatoires
            if (empty($module) || empty($entiteId) || empty($entiteType) || empty($action)) {
                throw new Exception("Paramètres obligatoires manquants pour la journalisation");
            }
            
            // Récupération de l'utilisateur depuis la session
            $idUtilisateur = $_SESSION['id_utilisateur'] ?? null;
            if (!$idUtilisateur) {
                // En mode développement, on peut accepter un utilisateur par défaut
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    $idUtilisateur = 1; // Utilisateur par défaut en mode debug
                } else {
                    throw new Exception("Utilisateur non connecté");
                }
            }
            
            // Validation du module
            $modulesValides = ['article', 'client', 'stock', 'commande', 'vente', 'numero_serie', 'utilisateur', 'connexion', 'comptabilite', 'correction_stock', 'test'];
            if (!in_array($module, $modulesValides)) {
                throw new Exception("Module invalide: $module");
            }
            
            // Validation de l'action
            $actionsValides = ['CREATION', 'MODIFICATION', 'VALIDATION', 'SUPPRESSION', 'ENTREE', 'SORTIE', 'AFFECTATION', 'CONNEXION', 'DECONNEXION', 'CORRECTION', 'AJOUT', 'RETRAIT', 'RETOUR', 'TEST'];
            if (!in_array($action, $actionsValides)) {
                throw new Exception("Action invalide: $action");
            }
            
            // Requête SQL ultra-simple qui fonctionne
            $sql = "INSERT INTO journal_unifie (
                module, entite_id, entite_type, action, IDUTILISATEUR, description_action, date_action
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->cnx->prepare($sql);
            $result = $stmt->execute([
                $module,
                $entiteId,
                $entiteType,
                $action,
                $idUtilisateur,
                $description
            ]);
            
            if (!$result) {
                throw new Exception("Erreur lors de l'insertion dans le journal unifié");
            }
            
            // Log de succès en mode debug
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Journalisation réussie: $module/$action pour entité $entiteId");
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur JournalUnifie::logAction: " . $e->getMessage());
            // En cas d'erreur, on peut essayer de journaliser dans les anciennes tables
            $this->fallbackToOldTables($module, $entiteId, $entiteType, $action, $description, $donnees);
            return false;
        }
    }
    
    /**
     * MÉTHODE DE FALLBACK - Utilise les anciennes tables en cas d'erreur
     */
    private function fallbackToOldTables($module, $entiteId, $entiteType, $action, $description, $donnees) {
        try {
            $idUtilisateur = $_SESSION['id_utilisateur'] ?? 1;
            
            switch($module) {
                case 'article':
                    $sql = "INSERT INTO journal_article (IDARTICLE, IDUTILISATEUR, action, description_action, stock_avant, stock_apres) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $this->cnx->prepare($sql);
                    $stmt->execute([$entiteId, $idUtilisateur, $action, $description, $donnees['stock_avant'] ?? null, $donnees['stock_apres'] ?? null]);
                    break;
                    
                case 'client':
                    $sql = "INSERT INTO journal_client (IDCLIENT, IDUTILISATEUR, action, description_action) VALUES (?, ?, ?, ?)";
                    $stmt = $this->cnx->prepare($sql);
                    $stmt->execute([$entiteId, $idUtilisateur, $action, $description]);
                    break;
                    
                case 'connexion':
                    $sql = "INSERT INTO journal_connexion (IDUTILISATEUR, action, description_action) VALUES (?, ?, ?)";
                    $stmt = $this->cnx->prepare($sql);
                    $stmt->execute([$idUtilisateur, $action, $description]);
                    break;
            }
            
            error_log("Fallback réussi vers les anciennes tables pour $module");
            
        } catch (Exception $e) {
            error_log("Erreur même avec le fallback: " . $e->getMessage());
        }
    }
    
    // =====================================================
    // MÉTHODES SPÉCIALISÉES POUR CHAQUE MODULE
    // =====================================================
    
    /**
     * JOURNALISATION DES ARTICLES
     * Remplace journaliserCreationArticle()
     */
    public function logArticle($articleId, $action, $description, $donnees = []) {
        $donnees['IDARTICLE'] = $articleId;
        return $this->logAction('article', $articleId, 'article', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DU STOCK
     * Remplace journaliserStock(), journaliserEntreeStock()
     */
    public function logStock($stockId, $action, $description, $donnees = []) {
        $donnees['IDSTOCK'] = $stockId;
        return $this->logAction('stock', $stockId, 'stock', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DES VENTES
     * Remplace journaliserVente(), journaliserVenteArticle()
     */
    public function logVente($venteId, $action, $description, $donnees = []) {
        $donnees['IDVENTE'] = $venteId;
        return $this->logAction('vente', $venteId, 'vente', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DES CLIENTS
     * Remplace journaliserClient()
     */
    public function logClient($clientId, $action, $description, $donnees = []) {
        $donnees['IDCLIENT'] = $clientId;
        return $this->logAction('client', $clientId, 'client', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DES NUMÉROS DE SÉRIE
     * Remplace les fonctions de journal_num_serie
     */
    public function logNumeroSerie($numeroSerieId, $action, $description, $donnees = []) {
        $donnees['numero_serie'] = $numeroSerieId;
        return $this->logAction('numero_serie', $numeroSerieId, 'numero_serie', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DES COMMANDES
     * Nouvelle fonctionnalité
     */
    public function logCommande($commandeId, $action, $description, $donnees = []) {
        return $this->logAction('commande', $commandeId, 'commande', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DES CONNEXIONS
     * Remplace journal_connexion
     */
    public function logConnexion($action, $description, $donnees = []) {
        $utilisateurId = $_SESSION['id_utilisateur'] ?? 0;
        return $this->logAction('connexion', $utilisateurId, 'connexion', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DES UTILISATEURS
     * Remplace journal_utilisateur
     */
    public function logUtilisateur($utilisateurId, $action, $description, $donnees = []) {
        $donnees['IDUTILISATEUR_ACTION'] = $utilisateurId;
        return $this->logAction('utilisateur', $utilisateurId, 'utilisateur', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION DES CORRECTIONS DE STOCK
     * Remplace journal_correction_stock
     */
    public function logCorrectionStock($correctionId, $action, $description, $donnees = []) {
        return $this->logAction('correction_stock', $correctionId, 'correction_stock', $action, $description, $donnees);
    }
    
    /**
     * JOURNALISATION COMPTABLE
     * Remplace journal_comptable
     */
    public function logComptabilite($operationId, $action, $description, $donnees = []) {
        return $this->logAction('comptabilite', $operationId, 'comptabilite', $action, $description, $donnees);
    }
    
    // =====================================================
    // MÉTHODES DE RÉCUPÉRATION DES DONNÉES
    // =====================================================
    
    /**
     * RÉCUPÉRER LE JOURNAL D'UN MODULE
     */
    public function getJournalModule($module, $filters = []) {
        try {
            $sql = "SELECT ju.*, u.NomPrenom as nom_utilisateur 
                    FROM journal_unifie ju 
                    JOIN utilisateur u ON ju.IDUTILISATEUR = u.IDUTILISATEUR 
                    WHERE ju.module = ?";
            
            $params = [$module];
            
            if (!empty($filters['date_debut'])) {
                $sql .= " AND DATE(ju.date_action) >= ?";
                $params[] = $filters['date_debut'];
            }
            
            if (!empty($filters['date_fin'])) {
                $sql .= " AND DATE(ju.date_action) <= ?";
                $params[] = $filters['date_fin'];
            }
            
            if (!empty($filters['action'])) {
                $sql .= " AND ju.action = ?";
                $params[] = $filters['action'];
            }
            
            if (!empty($filters['utilisateur_id'])) {
                $sql .= " AND ju.IDUTILISATEUR = ?";
                $params[] = $filters['utilisateur_id'];
            }
            
            $sql .= " ORDER BY ju.date_action DESC";
            
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT " . (int)$filters['limit'];
            }
            
            $stmt = $this->cnx->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur JournalUnifie::getJournalModule: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * RÉCUPÉRER LE JOURNAL COMPLET
     */
    public function getJournalComplet($filters = []) {
        try {
            $sql = "SELECT ju.*, u.NomPrenom as nom_utilisateur 
                    FROM journal_unifie ju 
                    JOIN utilisateur u ON ju.IDUTILISATEUR = u.IDUTILISATEUR 
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($filters['date_debut'])) {
                $sql .= " AND DATE(ju.date_action) >= ?";
                $params[] = $filters['date_debut'];
            }
            
            if (!empty($filters['date_fin'])) {
                $sql .= " AND DATE(ju.date_action) <= ?";
                $params[] = $filters['date_fin'];
            }
            
            if (!empty($filters['module'])) {
                $sql .= " AND ju.module = ?";
                $params[] = $filters['module'];
            }
            
            if (!empty($filters['action'])) {
                $sql .= " AND ju.action = ?";
                $params[] = $filters['action'];
            }
            
            if (!empty($filters['utilisateur_id'])) {
                $sql .= " AND ju.IDUTILISATEUR = ?";
                $params[] = $filters['utilisateur_id'];
            }
            
            $sql .= " ORDER BY ju.date_action DESC";
            
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT " . (int)$filters['limit'];
            }
            
            $stmt = $this->cnx->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur JournalUnifie::getJournalComplet: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * RÉCUPÉRER L'HISTORIQUE D'UNE ENTITÉ
     */
    public function getHistoriqueEntite($entiteType, $entiteId, $limit = 100) {
        try {
            $sql = "SELECT ju.*, u.NomPrenom as nom_utilisateur 
                    FROM journal_unifie ju 
                    JOIN utilisateur u ON ju.IDUTILISATEUR = u.IDUTILISATEUR 
                    WHERE ju.entite_type = ? AND ju.entite_id = ? 
                    ORDER BY ju.date_action DESC 
                    LIMIT ?";
            
            $stmt = $this->cnx->prepare($sql);
            $stmt->execute([$entiteType, $entiteId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur JournalUnifie::getHistoriqueEntite: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * STATISTIQUES DU JOURNAL
     */
    public function getStatistiques($filters = []) {
        try {
            $sql = "SELECT 
                        module,
                        action,
                        COUNT(*) as nombre_actions,
                        DATE(date_action) as date_action
                    FROM journal_unifie 
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($filters['date_debut'])) {
                $sql .= " AND DATE(date_action) >= ?";
                $params[] = $filters['date_debut'];
            }
            
            if (!empty($filters['date_fin'])) {
                $sql .= " AND DATE(date_action) <= ?";
                $params[] = $filters['date_fin'];
            }
            
            $sql .= " GROUP BY module, action, DATE(date_action) ORDER BY date_action DESC";
            
            $stmt = $this->cnx->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur JournalUnifie::getStatistiques: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * MIGRATION DES DONNÉES DES ANCIENNES TABLES
     * Migre les données des tables journal_* vers journal_unifie
     */
    public function migrerAnciennesTables() {
        try {
            $migrations = [
                'journal_article' => [
                    'module' => 'article',
                    'entite_type' => 'article',
                    'mapping' => [
                        'IDARTICLE' => 'entite_id',
                        'IDUTILISATEUR' => 'IDUTILISATEUR',
                        'action' => 'action',
                        'description_action' => 'description_action',
                        'stock_avant' => 'stock_avant',
                        'stock_apres' => 'stock_apres'
                    ]
                ],
                'journal_client' => [
                    'module' => 'client',
                    'entite_type' => 'client',
                    'mapping' => [
                        'IDCLIENT' => 'entite_id',
                        'IDUTILISATEUR' => 'IDUTILISATEUR',
                        'action' => 'action',
                        'description_action' => 'description_action'
                    ]
                ],
                'journal_connexion' => [
                    'module' => 'connexion',
                    'entite_type' => 'connexion',
                    'mapping' => [
                        'IDUTILISATEUR' => 'entite_id',
                        'IDUTILISATEUR' => 'IDUTILISATEUR',
                        'action' => 'action',
                        'description_action' => 'description_action'
                    ]
                ]
            ];
            
            $totalMigre = 0;
            
            foreach ($migrations as $table => $config) {
                $sql = "SELECT * FROM $table WHERE date_action IS NOT NULL";
                $stmt = $this->cnx->prepare($sql);
                $stmt->execute();
                $donnees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($donnees as $row) {
                    $donneesUnifie = [];
                    foreach ($config['mapping'] as $ancienChamp => $nouveauChamp) {
                        if (isset($row[$ancienChamp])) {
                            $donneesUnifie[$nouveauChamp] = $row[$ancienChamp];
                        }
                    }
                    
                    // Ajouter les champs spécifiques
                    $donneesUnifie['IDARTICLE'] = $row['IDARTICLE'] ?? null;
                    $donneesUnifie['IDCLIENT'] = $row['IDCLIENT'] ?? null;
                    $donneesUnifie['date_action'] = $row['date_action'] ?? date('Y-m-d H:i:s');
                    
                    $this->logAction(
                        $config['module'],
                        $donneesUnifie['entite_id'],
                        $config['entite_type'],
                        $donneesUnifie['action'],
                        $donneesUnifie['description_action'],
                        $donneesUnifie
                    );
                    
                    $totalMigre++;
                }
            }
            
            return $totalMigre;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la migration: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * EXPORT DU JOURNAL EN CSV
     */
    public function exporterCSV($filters = [], $filename = 'journal_export.csv') {
        try {
            $donnees = $this->getJournalComplet($filters);
            
            if (empty($donnees)) {
                return false;
            }
            
            $csv = fopen('php://temp', 'w');
            
            // En-têtes
            $headers = [
                'ID', 'Date', 'Module', 'Entité ID', 'Type Entité', 'Action', 
                'Utilisateur', 'Description', 'Article ID', 'Stock Avant', 'Stock Après',
                'Vente ID', 'Client ID', 'Montant Total', 'IP Address'
            ];
            fputcsv($csv, $headers);
            
            // Données
            foreach ($donnees as $row) {
                $ligne = [
                    $row['IDJOURNAL'],
                    $row['date_action'],
                    $row['module'],
                    $row['entite_id'],
                    $row['entite_type'],
                    $row['action'],
                    $row['nom_utilisateur'],
                    $row['description_action'],
                    $row['IDARTICLE'],
                    $row['stock_avant'],
                    $row['stock_apres'],
                    $row['IDVENTE'],
                    $row['IDCLIENT'],
                    $row['MontantTotal'],
                    $row['ip_address']
                ];
                fputcsv($csv, $ligne);
            }
            
            rewind($csv);
            $content = stream_get_contents($csv);
            fclose($csv);
            
            // Envoyer le fichier
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $content;
            exit;
            
        } catch (Exception $e) {
            error_log("Erreur export CSV: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * NETTOYAGE DU JOURNAL (suppression des anciennes entrées)
     */
    public function nettoyerJournal($joursConservation = 365) {
        try {
            $sql = "DELETE FROM journal_unifie 
                    WHERE date_action < DATE_SUB(NOW(), INTERVAL ? DAY) 
                    AND desactiver = 'non'";
            
            $stmt = $this->cnx->prepare($sql);
            $result = $stmt->execute([$joursConservation]);
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Erreur nettoyage journal: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * RECHERCHE AVANCÉE DANS LE JOURNAL
     */
    public function rechercherAvancee($criteria = []) {
        try {
            $sql = "SELECT ju.*, u.NomPrenom as nom_utilisateur 
                    FROM journal_unifie ju 
                    JOIN utilisateur u ON ju.IDUTILISATEUR = u.IDUTILISATEUR 
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($criteria['recherche'])) {
                $sql .= " AND (ju.description_action LIKE ? OR ju.action LIKE ? OR ju.module LIKE ?)";
                $searchTerm = '%' . $criteria['recherche'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($criteria['date_debut'])) {
                $sql .= " AND DATE(ju.date_action) >= ?";
                $params[] = $criteria['date_debut'];
            }
            
            if (!empty($criteria['date_fin'])) {
                $sql .= " AND DATE(ju.date_action) <= ?";
                $params[] = $criteria['date_fin'];
            }
            
            if (!empty($criteria['module'])) {
                $sql .= " AND ju.module = ?";
                $params[] = $criteria['module'];
            }
            
            if (!empty($criteria['action'])) {
                $sql .= " AND ju.action = ?";
                $params[] = $criteria['action'];
            }
            
            if (!empty($criteria['utilisateur_id'])) {
                $sql .= " AND ju.IDUTILISATEUR = ?";
                $params[] = $criteria['utilisateur_id'];
            }
            
            $sql .= " ORDER BY ju.date_action DESC";
            
            if (!empty($criteria['limit'])) {
                $sql .= " LIMIT " . (int)$criteria['limit'];
            }
            
            $stmt = $this->cnx->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur recherche avancée: " . $e->getMessage());
            return [];
        }
    }
}
}
?>
