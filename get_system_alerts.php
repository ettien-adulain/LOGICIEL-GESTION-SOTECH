<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    
    // Vérifier la session
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['id_utilisateur'])) {
        echo json_encode(['error' => 'Non autorisé']);
        exit();
    }
    
    // Récupérer les alertes récentes (dernières 24h) non lues
    $sql = "SELECT 
                action,
                module,
                page,
                description,
                niveau_securite,
                statut_action,
                erreur_message,
                timestamp,
                ip_address
            FROM journal_systeme 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND (niveau_securite = 'CRITICAL' OR statut_action = 'FAILED' OR erreur_message IS NOT NULL)
            AND statut_action != 'READ'
            ORDER BY timestamp DESC 
            LIMIT 50";
    
    // Ajouter les alertes depuis les fichiers logs
    $logs_dir = 'logs/';
    $error_log_file = $logs_dir . 'errors.log';
    $production_log_file = $logs_dir . 'production_monitor.log';
    
    // Fonction pour lire les logs
    function readLogFile($file_path, $max_lines = 50) {
        if (!file_exists($file_path)) {
            return [];
        }
        $lines = file($file_path, FILE_IGNORE_NEW_LINES);
        return array_slice($lines, -$max_lines);
    }
    
    // Fonction pour parser les logs d'erreurs
    function parseErrorLog($lines) {
        $errors = [];
        foreach ($lines as $line) {
            if (preg_match('/\[(.*?)\] (.*?): (.*?) dans (.*?) ligne (\d+)/', $line, $matches)) {
                $errors[] = [
                    'timestamp' => $matches[1],
                    'type' => $matches[2],
                    'message' => $matches[3],
                    'file' => $matches[4],
                    'line' => $matches[5],
                    'raw' => $line
                ];
            }
        }
        return array_reverse($errors);
    }
    
    // Lire les logs depuis les fichiers
    $error_lines = readLogFile($error_log_file, 50);
    $file_errors = parseErrorLog($error_lines);
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $alerts = [];
    
    // Traiter les alertes de la base de données
    foreach ($logs as $log) {
        $alert = [
            'id' => uniqid(),
            'type' => determineAlertType($log),
            'title' => generateAlertTitle($log),
            'message' => generateAlertMessage($log),
            'file' => basename($log['page']),
            'timestamp' => $log['timestamp'],
            'severity' => $log['niveau_securite'],
            'status' => $log['statut_action'],
            'source' => 'database'
        ];
        
        $alerts[] = $alert;
    }
    
    // Traiter les alertes des fichiers logs
    foreach ($file_errors as $error) {
        $alert = [
            'id' => uniqid(),
            'type' => determineFileAlertType($error),
            'title' => generateFileAlertTitle($error),
            'message' => $error['message'],
            'file' => basename($error['file']),
            'timestamp' => $error['timestamp'],
            'severity' => determineFileSeverity($error),
            'status' => 'FAILED',
            'source' => 'file_log'
        ];
        
        $alerts[] = $alert;
    }
    
    // Ajouter des alertes de performance si nécessaire
    $performanceAlerts = checkPerformanceAlerts($cnx);
    $alerts = array_merge($alerts, $performanceAlerts);
    
    // Trier par timestamp décroissant
    usort($alerts, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // S'assurer qu'on retourne toujours un tableau
    if (!is_array($alerts)) {
        $alerts = [];
    }
    
    echo json_encode($alerts);
    
} catch (Exception $e) {
    // Log l'erreur pour le debugging
    error_log('Erreur get_system_alerts.php: ' . $e->getMessage());
    
    // Retourner un tableau vide en cas d'erreur
    echo json_encode([]);
}

// Fonction pour déterminer le type d'alerte
function determineAlertType($log) {
    if ($log['niveau_securite'] === 'CRITICAL' || $log['statut_action'] === 'FAILED') {
        return 'critical';
    }
    
    if ($log['niveau_securite'] === 'HIGH') {
        return 'warning';
    }
    
    if ($log['erreur_message']) {
        return 'error';
    }
    
    return 'info';
}

// Fonction pour générer le titre de l'alerte
function generateAlertTitle($log) {
    $action = ucfirst(strtolower($log['action']));
    $module = ucfirst(strtolower($log['module']));
    
    if ($log['statut_action'] === 'FAILED') {
        return "Échec: {$action} - {$module}";
    }
    
    if ($log['niveau_securite'] === 'CRITICAL') {
        return "Critique: {$action} - {$module}";
    }
    
    return "{$action} - {$module}";
}

// Fonction pour générer le message de l'alerte
function generateAlertMessage($log) {
    if ($log['erreur_message']) {
        return $log['erreur_message'];
    }
    
    if ($log['description']) {
        return $log['description'];
    }
    
    return "Action: {$log['action']} sur {$log['module']}";
}

// Fonction pour vérifier les alertes de performance
function checkPerformanceAlerts($cnx) {
    $alerts = [];
    
    // Vérifier les requêtes lentes
    $sql = "SELECT COUNT(*) as slow_queries 
            FROM journal_systeme 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND temps_execution > 5";
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['slow_queries'] > 10) {
        $alerts[] = [
            'id' => uniqid(),
            'type' => 'warning',
            'title' => 'Performance dégradée',
            'message' => "{$result['slow_queries']} requêtes lentes détectées dans la dernière heure",
            'file' => 'Système',
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => 'HIGH',
            'status' => 'WARNING'
        ];
    }
    
    // Vérifier les erreurs fréquentes
    $sql = "SELECT action, COUNT(*) as error_count 
            FROM journal_systeme 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND statut_action = 'FAILED'
            GROUP BY action
            HAVING error_count > 5";
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute();
    $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($errors as $error) {
        $alerts[] = [
            'id' => uniqid(),
            'type' => 'critical',
            'title' => 'Erreurs répétées',
            'message' => "Action '{$error['action']}' a échoué {$error['error_count']} fois",
            'file' => 'Système',
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => 'CRITICAL',
            'status' => 'FAILED'
        ];
    }
    
    return $alerts;
}

// Fonction pour déterminer le type d'alerte depuis les fichiers logs
function determineFileAlertType($error) {
    $type = strtoupper($error['type']);
    
    if (in_array($type, ['ERREUR PHP', 'FATAL ERROR', 'PARSE ERROR'])) {
        return 'critical';
    }
    
    if (in_array($type, ['WARNING', 'NOTICE', 'DEPRECATED'])) {
        return 'warning';
    }
    
    if (in_array($type, ['EXCEPTION', 'ERROR'])) {
        return 'error';
    }
    
    return 'info';
}

// Fonction pour générer le titre d'alerte depuis les fichiers logs
function generateFileAlertTitle($error) {
    $type = ucfirst(strtolower($error['type']));
    $file = basename($error['file']);
    
    return "{$type} - {$file}";
}

// Fonction pour déterminer la sévérité depuis les fichiers logs
function determineFileSeverity($error) {
    $type = strtoupper($error['type']);
    
    if (in_array($type, ['ERREUR PHP', 'FATAL ERROR', 'PARSE ERROR'])) {
        return 'CRITICAL';
    }
    
    if (in_array($type, ['WARNING', 'NOTICE'])) {
        return 'HIGH';
    }
    
    if (in_array($type, ['EXCEPTION', 'ERROR'])) {
        return 'MEDIUM';
    }
    
    return 'LOW';
}
?>
