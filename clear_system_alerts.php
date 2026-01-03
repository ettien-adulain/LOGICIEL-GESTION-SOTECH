<?php
header('Content-Type: application/json');

try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    
    // Vérifier la session
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['id_utilisateur'])) {
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit();
    }
    
    // Vérifier que l'utilisateur est admin
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Accès refusé - Admin requis']);
        exit();
    }
    
    // Marquer les alertes comme lues (au lieu de les supprimer)
    $sql = "UPDATE journal_systeme 
            SET statut_action = 'READ' 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND (niveau_securite = 'CRITICAL' OR statut_action = 'FAILED' OR erreur_message IS NOT NULL)";
    
    $stmt = $cnx->prepare($sql);
    $result = $stmt->execute();
    
    if ($result) {
        // Journaliser l'action
        logSystemAction(
            $cnx,
            'CLEAR_ALERTS',
            'SYSTEM',
            'clear_system_alerts.php',
            'Alertes système vidées par ' . $_SESSION['nom_utilisateur'],
            null,
            null,
            'MEDIUM',
            'SUCCESS'
        );
        
        echo json_encode(['success' => true, 'message' => 'Alertes vidées avec succès']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors du vidage des alertes']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
