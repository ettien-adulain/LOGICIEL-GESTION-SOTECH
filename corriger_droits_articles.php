<?php
session_start();
include('db/connecting.php');

echo "<h1>Correction Automatique des Droits Articles</h1>";

// Récupérer l'ID utilisateur
$id_utilisateur = isset($_SESSION['id_utilisateur']) ? $_SESSION['id_utilisateur'] : 51;

echo "ID Utilisateur: $id_utilisateur<br><br>";

// Droits nécessaires pour les articles
$droits_necessaires = [
    ['Articles', 'voir'],
    ['creation_d_article', 'voir'],
    ['creation_d_article', 'ajouter'],
    ['creation_d_article', 'enregistrer'],
    ['creation_d_article', 'annuler']
];

echo "<h2>Ajout des droits manquants :</h2>";

foreach ($droits_necessaires as $droit) {
    $module = $droit[0];
    $action = $droit[1];
    
    // Vérifier si le droit existe déjà
    $stmt = $cnx->prepare("SELECT COUNT(*) FROM droits_acces WHERE id_utilisateur = ? AND module = ? AND action = ?");
    $stmt->execute([$id_utilisateur, $module, $action]);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Ajouter le droit
        $stmt = $cnx->prepare("INSERT INTO droits_acces (id_utilisateur, module, action, autorise, date_modif) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute([$id_utilisateur, $module, $action]);
        echo "✅ Ajouté: $module/$action<br>";
    } else {
        // Mettre à jour le droit
        $stmt = $cnx->prepare("UPDATE droits_acces SET autorise = 1, date_modif = NOW() WHERE id_utilisateur = ? AND module = ? AND action = ?");
        $stmt->execute([$id_utilisateur, $module, $action]);
        echo "✅ Mis à jour: $module/$action<br>";
    }
}

echo "<br><h2>Vérification finale :</h2>";

// Vérifier tous les droits
$stmt = $cnx->prepare("SELECT module, action, autorise FROM droits_acces WHERE id_utilisateur = ? AND module IN ('Articles', 'creation_d_article') ORDER BY module, action");
$stmt->execute([$id_utilisateur]);
$droits = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Module</th><th>Action</th><th>Autorisé</th></tr>";

foreach ($droits as $droit) {
    echo "<tr>";
    echo "<td>" . $droit['module'] . "</td>";
    echo "<td>" . $droit['action'] . "</td>";
    echo "<td>" . ($droit['autorise'] ? '✅ OUI' : '❌ NON') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h2>Test des liens :</h2>";
echo "<a href='articles.php' style='background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔗 Tester articles.php</a><br><br>";
echo "<a href='creation_d_article.php' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔗 Tester Création Article</a><br><br>";
echo "<a href='droit_acces.php' style='background: #6c757d; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔗 Gestion des Droits</a>";

?> 