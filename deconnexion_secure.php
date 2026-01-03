<?php
/**
 * SCRIPT DE DÉCONNEXION SÉCURISÉ
 * Évite toutes les erreurs de journalisation
 */

session_start();

// =====================================================
// JOURNALISATION DE LA DÉCONNEXION (SÉCURISÉE)
// =====================================================
if (isset($_SESSION['id_utilisateur'])) {
    try {
        // Sauvegarder les informations avant de détruire la session
        $id_utilisateur = $_SESSION['id_utilisateur'];
        $nom_complet = $_SESSION['nom_complet'] ?? 'Utilisateur inconnu';
        $nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'Inconnu';
        $type_utilisateur = $_SESSION['type_utilisateur'] ?? null;
        
        // Journalisation directe sans classe pour éviter les erreurs
        include_once('db/connecting.php');
        
        $sql = "INSERT INTO journal_unifie (
            module, entite_id, entite_type, action, IDUTILISATEUR, description_action,
            ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $cnx->prepare($sql);
        $result = $stmt->execute([
            'connexion',
            $id_utilisateur,
            'utilisateur',
            'DECONNEXION',
            $id_utilisateur,
            "Déconnexion de l'utilisateur {$nom_complet} ({$nom_utilisateur})",
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        if ($result) {
            error_log("Journalisation déconnexion réussie pour l'utilisateur {$id_utilisateur}");
        }
        
    } catch (Exception $e) {
        // En cas d'erreur, on continue quand même la déconnexion
        error_log("Erreur journalisation déconnexion: " . $e->getMessage());
    }
}

// Détruire la session
session_unset();
session_destroy();

// Redirection
header('Location: connexion.php');
exit();
?>
