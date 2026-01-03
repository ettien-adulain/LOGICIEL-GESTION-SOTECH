<?php


class Comptabilite {
    private $cnx;

    public function __construct($cnx) {
        $this->cnx = $cnx;
    }

    // Récupérer le chiffre d'affaires
    public function getChiffreAffaires($debut, $fin) {
        $stmt = $this->cnx->prepare("
            SELECT SUM(MontantTotal) as total
            FROM vente
            WHERE DateIns BETWEEN ? AND ?
        ");
        $stmt->execute([$debut, $fin]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        return number_format($total, 0, ',', ' ');
    }

    // Récupérer le bénéfice net
    public function getBeneficeNet($debut, $fin) {
        // Calcul du bénéfice net (CA - Coûts)
        $stmt = $this->cnx->prepare("
            SELECT 
                (SELECT SUM(MontantTotal) FROM vente WHERE DateIns BETWEEN ? AND ?) as ca,
                (SELECT SUM(MontantTotal) FROM achat WHERE DateIns BETWEEN ? AND ?) as couts
        ");
        $stmt->execute([$debut, $fin, $debut, $fin]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $benefice = ($result['ca'] ?? 0) - ($result['couts'] ?? 0);
        return number_format($benefice, 0, ',', ' ');
    }

    // Récupérer la TVA à payer
    public function getTvaAPayer($debut, $fin) {
        $stmt = $this->cnx->prepare("
            SELECT 
                (SELECT SUM(MontantTVA) FROM vente WHERE DateIns BETWEEN ? AND ?) as tva_collectee,
                (SELECT SUM(MontantTVA) FROM achat WHERE DateIns BETWEEN ? AND ?) as tva_deductible
        ");
        $stmt->execute([$debut, $fin, $debut, $fin]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $tva = ($result['tva_collectee'] ?? 0) - ($result['tva_deductible'] ?? 0);
        return number_format($tva, 0, ',', ' ');
    }

    // Récupérer les dettes fournisseurs
    public function getDettesFournisseurs() {
        $stmt = $this->cnx->prepare("
            SELECT SUM(MontantTotal - MontantPaye) as total
            FROM achat
            WHERE MontantTotal > MontantPaye
        ");
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        return number_format($total, 0, ',', ' ');
    }

    // Récupérer le journal comptable
    public function getJournalComptable($debut, $fin) {
        $stmt = $this->cnx->prepare("
            SELECT 
                j.DateOperation,
                j.NumeroPiece,
                j.Compte,
                j.Libelle,
                j.Debit,
                j.Credit
            FROM journal_comptable j
            WHERE j.DateOperation BETWEEN ? AND ?
            ORDER BY j.DateOperation, j.NumeroPiece
        ");
        $stmt->execute([$debut, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer le grand livre
    public function getGrandLivre($compte, $debut, $fin) {
        $stmt = $this->cnx->prepare("
            SELECT 
                j.DateOperation,
                j.NumeroPiece,
                j.Libelle,
                j.Debit,
                j.Credit,
                SUM(j.Debit - j.Credit) OVER (ORDER BY j.DateOperation, j.NumeroPiece) as Solde
            FROM journal_comptable j
            WHERE j.Compte = ? AND j.DateOperation BETWEEN ? AND ?
            ORDER BY j.DateOperation, j.NumeroPiece
        ");
        $stmt->execute([$compte, $debut, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer la balance
    public function getBalance($debut, $fin) {
        $stmt = $this->cnx->prepare("
            SELECT 
                j.Compte,
                c.Intitule,
                SUM(CASE WHEN j.DateOperation < ? THEN j.Debit - j.Credit ELSE 0 END) as SoldeInitial,
                SUM(CASE WHEN j.DateOperation BETWEEN ? AND ? THEN j.Debit ELSE 0 END) as Debit,
                SUM(CASE WHEN j.DateOperation BETWEEN ? AND ? THEN j.Credit ELSE 0 END) as Credit,
                SUM(j.Debit - j.Credit) as SoldeFinal
            FROM journal_comptable j
            JOIN comptes c ON j.Compte = c.Numero
            WHERE j.DateOperation <= ?
            GROUP BY j.Compte, c.Intitule
            ORDER BY j.Compte
        ");
        $stmt->execute([$debut, $debut, $fin, $debut, $fin, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer le bilan
    public function getBilan($date) {
        // Actif
        $stmt = $this->cnx->prepare("
            SELECT 
                j.Compte,
                c.Intitule,
                SUM(j.Debit - j.Credit) as Montant
            FROM journal_comptable j
            JOIN comptes c ON j.Compte = c.Numero
            WHERE j.DateOperation <= ? AND c.Type = 'Actif'
            GROUP BY j.Compte, c.Intitule
            ORDER BY j.Compte
        ");
        $stmt->execute([$date]);
        $actif = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Passif
        $stmt = $this->cnx->prepare("
            SELECT 
                j.Compte,
                c.Intitule,
                SUM(j.Credit - j.Debit) as Montant
            FROM journal_comptable j
            JOIN comptes c ON j.Compte = c.Numero
            WHERE j.DateOperation <= ? AND c.Type = 'Passif'
            GROUP BY j.Compte, c.Intitule
            ORDER BY j.Compte
        ");
        $stmt->execute([$date]);
        $passif = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['actif' => $actif, 'passif' => $passif];
    }

    // Récupérer le compte de résultat
    public function getCompteResultat($debut, $fin) {
        $stmt = $this->cnx->prepare("
            SELECT 
                j.Compte,
                c.Intitule,
                SUM(j.Debit - j.Credit) as Montant
            FROM journal_comptable j
            JOIN comptes c ON j.Compte = c.Numero
            WHERE j.DateOperation BETWEEN ? AND ? AND c.Type IN ('Charges', 'Produits')
            GROUP BY j.Compte, c.Intitule
            ORDER BY c.Type, j.Compte
        ");
        $stmt->execute([$debut, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer la TVA
    public function getTva($debut, $fin) {
        // TVA collectée
        $stmt = $this->cnx->prepare("
            SELECT 
                DATE_FORMAT(DateIns, '%Y-%m') as Periode,
                SUM(MontantHT) as BaseHT,
                SUM(MontantTVA) as TVA
            FROM vente
            WHERE DateIns BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(DateIns, '%Y-%m')
            ORDER BY Periode
        ");
        $stmt->execute([$debut, $fin]);
        $tvaCollectee = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // TVA déductible
        $stmt = $this->cnx->prepare("
            SELECT 
                DATE_FORMAT(DateIns, '%Y-%m') as Periode,
                SUM(MontantHT) as BaseHT,
                SUM(MontantTVA) as TVA
            FROM achat
            WHERE DateIns BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(DateIns, '%Y-%m')
            ORDER BY Periode
        ");
        $stmt->execute([$debut, $fin]);
        $tvaDeductible = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'collectee' => $tvaCollectee,
            'deductible' => $tvaDeductible
        ];
    }

    // Générer un rapport
    public function genererRapport($type, $debut, $fin) {
        switch ($type) {
            case 'tresorerie':
                return $this->getRapportTresorerie($debut, $fin);
            case 'rentabilite':
                return $this->getRapportRentabilite($debut, $fin);
            case 'gestion':
                return $this->getRapportGestion($debut, $fin);
            default:
                throw new Exception('Type de rapport non reconnu');
        }
    }

    private function getRapportTresorerie($debut, $fin) {
        // Implémentation du rapport de trésorerie
        $stmt = $this->cnx->prepare("
            SELECT 
                DATE_FORMAT(DateOperation, '%Y-%m') as Periode,
                SUM(CASE WHEN Type = 'Entree' THEN Montant ELSE 0 END) as Entrees,
                SUM(CASE WHEN Type = 'Sortie' THEN Montant ELSE 0 END) as Sorties,
                SUM(CASE WHEN Type = 'Entree' THEN Montant ELSE -Montant END) as Solde
            FROM operations_tresorerie
            WHERE DateOperation BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(DateOperation, '%Y-%m')
            ORDER BY Periode
        ");
        $stmt->execute([$debut, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRapportRentabilite($debut, $fin) {
        // Implémentation du rapport de rentabilité
        $stmt = $this->cnx->prepare("
            SELECT 
                a.libelle as Article,
                COUNT(v.IDFactureVente) as QuantiteVendue,
                SUM(v.MontantTotal) as ChiffreAffaires,
                SUM(a.PrixAchatTTC * COUNT(v.IDFactureVente)) as CoutAchat,
                SUM(v.MontantTotal) - SUM(a.PrixAchatTTC * COUNT(v.IDFactureVente)) as Marge
            FROM vente v
            JOIN facture_article fa ON v.IDFactureVente = fa.IDFactureVente
            JOIN article a ON fa.IDARTICLE = a.IDARTICLE
            WHERE v.DateIns BETWEEN ? AND ?
            GROUP BY a.IDARTICLE, a.libelle
            ORDER BY Marge DESC
        ");
        $stmt->execute([$debut, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRapportGestion($debut, $fin) {
        // Implémentation du rapport de gestion
        return [
            'chiffre_affaires' => $this->getChiffreAffaires($debut, $fin),
            'benefice_net' => $this->getBeneficeNet($debut, $fin),
            'tva_a_payer' => $this->getTvaAPayer($debut, $fin),
            'dettes_fournisseurs' => $this->getDettesFournisseurs()
        ];
    }
}

// Traitement des requêtes AJAX
if (isset($_POST['action'])) {
    $comptabilite = new Comptabilite($cnx);
    $response = ['success' => false];

    try {
        switch ($_POST['action']) {
            case 'get_dashboard':
                $debut = $_POST['debut'] ?? date('Y-m-01');
                $fin = $_POST['fin'] ?? date('Y-m-t');
                $response = [
                    'success' => true,
                    'data' => [
                        'chiffre_affaires' => $comptabilite->getChiffreAffaires($debut, $fin),
                        'benefice_net' => $comptabilite->getBeneficeNet($debut, $fin),
                        'tva_a_payer' => $comptabilite->getTvaAPayer($debut, $fin),
                        'dettes_fournisseurs' => $comptabilite->getDettesFournisseurs()
                    ]
                ];
                break;

            case 'get_journal':
                $debut = $_POST['debut'] ?? date('Y-m-01');
                $fin = $_POST['fin'] ?? date('Y-m-t');
                $response = [
                    'success' => true,
                    'data' => $comptabilite->getJournalComptable($debut, $fin)
                ];
                break;

            case 'get_grand_livre':
                $compte = $_POST['compte'] ?? '';
                $debut = $_POST['debut'] ?? date('Y-m-01');
                $fin = $_POST['fin'] ?? date('Y-m-t');
                $response = [
                    'success' => true,
                    'data' => $comptabilite->getGrandLivre($compte, $debut, $fin)
                ];
                break;

            case 'get_balance':
                $debut = $_POST['debut'] ?? date('Y-m-01');
                $fin = $_POST['fin'] ?? date('Y-m-t');
                $response = [
                    'success' => true,
                    'data' => $comptabilite->getBalance($debut, $fin)
                ];
                break;

            case 'get_bilan':
                $date = $_POST['date'] ?? date('Y-m-d');
                $response = [
                    'success' => true,
                    'data' => $comptabilite->getBilan($date)
                ];
                break;

            case 'get_resultat':
                $debut = $_POST['debut'] ?? date('Y-m-01');
                $fin = $_POST['fin'] ?? date('Y-m-t');
                $response = [
                    'success' => true,
                    'data' => $comptabilite->getCompteResultat($debut, $fin)
                ];
                break;

            case 'get_tva':
                $debut = $_POST['debut'] ?? date('Y-m-01');
                $fin = $_POST['fin'] ?? date('Y-m-t');
                $response = [
                    'success' => true,
                    'data' => $comptabilite->getTva($debut, $fin)
                ];
                break;

            case 'generer_rapport':
                $type = $_POST['type'] ?? '';
                $debut = $_POST['debut'] ?? date('Y-m-01');
                $fin = $_POST['fin'] ?? date('Y-m-t');
                $response = [
                    'success' => true,
                    'data' => $comptabilite->genererRapport($type, $debut, $fin)
                ];
                break;

            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?> 