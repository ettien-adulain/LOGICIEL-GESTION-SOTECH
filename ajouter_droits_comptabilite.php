<?php
include('db/connecting.php');

// Récupérer tous les utilisateurs
$stmt = $cnx->prepare('SELECT IDUTILISATEUR FROM utilisateur');
$stmt->execute();
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Ajout des droits comptabilite pour tous les utilisateurs..." . PHP_EOL;

foreach ($utilisateurs as $user) {
    $id_utilisateur = $user['IDUTILISATEUR'];
    
    // Vérifier si le droit existe déjà
    $stmt_check = $cnx->prepare('SELECT COUNT(*) FROM droits_acces WHERE id_utilisateur = ? AND page = ? AND action = ?');
    $stmt_check->execute([$id_utilisateur, 'comptabilite', 'voir']);
    $exists = $stmt_check->fetchColumn();
    
    if ($exists == 0) {
        // Ajouter le droit
        $stmt_insert = $cnx->prepare('INSERT INTO droits_acces (id_utilisateur, page, action, autorise) VALUES (?, ?, ?, ?)');
        $result = $stmt_insert->execute([$id_utilisateur, 'comptabilite', 'voir', 1]);
        
        if ($result) {
            echo "✅ Droit ajouté pour utilisateur ID: $id_utilisateur" . PHP_EOL;
        } else {
            echo "❌ Erreur pour utilisateur ID: $id_utilisateur" . PHP_EOL;
        }
    } else {
        echo "ℹ️  Droit déjà existant pour utilisateur ID: $id_utilisateur" . PHP_EOL;
    }
}

echo "\nVérification finale..." . PHP_EOL;
$stmt_final = $cnx->prepare('SELECT COUNT(*) FROM droits_acces WHERE page = ? AND action = ?');
$stmt_final->execute(['comptabilite', 'voir']);
$total_droits = $stmt_final->fetchColumn();
echo "Total des droits comptabilite: $total_droits" . PHP_EOL;
?>
