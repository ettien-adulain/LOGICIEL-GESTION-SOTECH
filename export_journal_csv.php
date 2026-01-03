<?php
/**
 * SCRIPT D'EXPORT CSV POUR LE JOURNAL UNIFIÉ
 * Exporte les données du journal vers un fichier CSV
 */

require_once 'fonction_traitement/fonction.php';
require_once 'fonction_traitement/JournalUnifie.php';

// Vérification des droits d'accès
check_access();

try {
    include('db/connecting.php');
    
    // Récupération des paramètres
    $journalType = $_POST['journalType'] ?? 'article';
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $actionFilter = $_POST['action_filter'] ?? '';
    
    // Initialisation de la classe JournalUnifie
    $journalUnifie = new JournalUnifie($cnx);
    
    // Préparation des filtres
    $filters = [];
    if ($startDate) $filters['date_debut'] = $startDate;
    if ($endDate) $filters['date_fin'] = $endDate;
    if ($actionFilter) $filters['action'] = $actionFilter;
    
    // Récupération des données selon le type de journal
    $journalData = [];
    
    switch($journalType) {
        case 'article':
            $journalData = $journalUnifie->getJournalModule('article', $filters);
            break;
        case 'client':
            $journalData = $journalUnifie->getJournalModule('client', $filters);
            break;
        case 'stock':
            $journalData = $journalUnifie->getJournalModule('stock', $filters);
            break;
        case 'vente':
            $journalData = $journalUnifie->getJournalModule('vente', $filters);
            break;
        case 'connexion':
            $journalData = $journalUnifie->getJournalModule('connexion', $filters);
            break;
        default:
            $journalData = $journalUnifie->getJournalComplet($filters);
            break;
    }
    
    if (empty($journalData)) {
        throw new Exception("Aucune donnée à exporter");
    }
    
    // Préparation du fichier CSV
    $filename = 'journal_' . $journalType . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    // En-têtes HTTP pour le téléchargement
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Création du fichier CSV
    $output = fopen('php://output', 'w');
    
    // Ajout du BOM pour l'UTF-8 (compatibilité Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes du CSV
    $headers = [
        'ID Journal',
        'Date/Heure',
        'Module',
        'Entité ID',
        'Type Entité',
        'Action',
        'Utilisateur',
        'Description',
        'Article ID',
        'Stock Avant',
        'Stock Après',
        'Vente ID',
        'Client ID',
        'Montant Total',
        'Montant Versé',
        'Mode Paiement',
        'Numéro Série',
        'Ancien Statut',
        'Nouveau Statut',
        'Motif',
        'IP Address',
        'User Agent'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Données
    foreach ($journalData as $row) {
        $ligne = [
            $row['IDJOURNAL'] ?? '',
            $row['date_action'] ?? '',
            $row['module'] ?? '',
            $row['entite_id'] ?? '',
            $row['entite_type'] ?? '',
            $row['action'] ?? '',
            $row['nom_utilisateur'] ?? '',
            $row['description_action'] ?? '',
            $row['IDARTICLE'] ?? '',
            $row['stock_avant'] ?? '',
            $row['stock_apres'] ?? '',
            $row['IDVENTE'] ?? '',
            $row['IDCLIENT'] ?? '',
            $row['MontantTotal'] ?? '',
            $row['MontantVerse'] ?? '',
            $row['ModePaiement'] ?? '',
            $row['numero_serie'] ?? '',
            $row['ancien_statut'] ?? '',
            $row['nouveau_statut'] ?? '',
            $row['motif'] ?? '',
            $row['ip_address'] ?? '',
            $row['user_agent'] ?? ''
        ];
        
        fputcsv($output, $ligne, ';');
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    // En cas d'erreur, rediriger vers le journal avec un message
    $message = urlencode("Erreur lors de l'export: " . $e->getMessage());
    header("Location: journal.php?error=" . $message);
    exit;
}
?>
