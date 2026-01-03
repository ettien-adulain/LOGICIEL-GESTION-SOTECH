<?php
session_start();
include('db/connecting.php');

echo "<h1>🔧 Correction Finale des Droits d'Accès</h1>";

// Récupérer l'ID utilisateur
$id_utilisateur = isset($_SESSION['id_utilisateur']) ? $_SESSION['id_utilisateur'] : 51;

echo "<p><strong>ID Utilisateur:</strong> $id_utilisateur</p>";

// Droits nécessaires pour un accès complet aux articles
$droits_complets = [
    // Interface principale des articles
    ['Articles', 'voir'],
    
    // Création d'articles
    ['creation_d_article', 'voir'],
    ['creation_d_article', 'ajouter'],
    ['creation_d_article', 'enregistrer'],
    ['creation_d_article', 'annuler'],
    
    // Liste des articles
    ['liste_article', 'voir'],
    ['liste_article', 'modifier'],
    ['liste_article', 'supprimer'],
    
    // Catégories d'articles
    ['categorie_article', 'voir'],
    ['categorie_article', 'ajouter'],
    ['categorie_article', 'modifier'],
    ['categorie_article', 'supprimer']
];

echo "<h2>📋 Droits à configurer :</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Page</th><th>Action</th><th>Description</th><th>Statut</th></tr>";

foreach ($droits_complets as $droit) {
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
        $status = "✅ Ajouté";
    } else {
        // Mettre à jour le droit
        $stmt = $cnx->prepare("UPDATE droits_acces SET autorise = 1, date_modif = NOW() WHERE id_utilisateur = ? AND module = ? AND action = ?");
        $stmt->execute([$id_utilisateur, $module, $action]);
        $status = "✅ Mis à jour";
    }
    
    // Description du droit
    $description = "";
    switch ($module) {
        case 'Articles':
            $description = "Accès au menu principal des articles";
            break;
        case 'creation_d_article':
            switch ($action) {
                case 'voir': $description = "Voir la page de création"; break;
                case 'ajouter': $description = "Créer un nouvel article"; break;
                case 'enregistrer': $description = "Sauvegarder l'article"; break;
                case 'annuler': $description = "Annuler la création"; break;
            }
            break;
        case 'liste_article':
            switch ($action) {
                case 'voir': $description = "Voir la liste des articles"; break;
                case 'modifier': $description = "Modifier un article"; break;
                case 'supprimer': $description = "Supprimer un article"; break;
            }
            break;
        case 'categorie_article':
            switch ($action) {
                case 'voir': $description = "Voir les catégories"; break;
                case 'ajouter': $description = "Ajouter une catégorie"; break;
                case 'modifier': $description = "Modifier une catégorie"; break;
                case 'supprimer': $description = "Supprimer une catégorie"; break;
            }
            break;
    }
    
    echo "<tr>";
    echo "<td><strong>$module</strong></td>";
    echo "<td>$action</td>";
    echo "<td>$description</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>🔍 Vérification finale :</h2>";

// Vérifier tous les droits configurés
$stmt = $cnx->prepare("SELECT module, action, autorise FROM droits_acces WHERE id_utilisateur = ? AND module IN ('Articles', 'creation_d_article', 'liste_article', 'categorie_article') ORDER BY module, action");
$stmt->execute([$id_utilisateur]);
$droits = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Page</th><th>Action</th><th>Autorisé</th></tr>";

foreach ($droits as $droit) {
    echo "<tr>";
    echo "<td>" . $droit['module'] . "</td>";
    echo "<td>" . $droit['action'] . "</td>";
    echo "<td>" . ($droit['autorise'] ? '✅ OUI' : '❌ NON') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>🧪 Tests recommandés :</h2>";
echo "<div style='display: flex; gap: 10px; flex-wrap: wrap;'>";
echo "<a href='articles.php' style='background: #007bff; color: white; padding: 15px; text-decoration: none; border-radius: 8px; display: inline-block;'>";
echo "🔗 <strong>articles.php</strong><br><small>Menu principal des articles</small>";
echo "</a>";

echo "<a href='creation_d_article.php' style='background: #28a745; color: white; padding: 15px; text-decoration: none; border-radius: 8px; display: inline-block;'>";
echo "🔗 <strong>Création Article</strong><br><small>Créer un nouvel article</small>";
echo "</a>";

echo "<a href='liste_article.php' style='background: #ffc107; color: black; padding: 15px; text-decoration: none; border-radius: 8px; display: inline-block;'>";
echo "🔗 <strong>Liste Articles</strong><br><small>Voir tous les articles</small>";
echo "</a>";

echo "<a href='categorie_article.php' style='background: #17a2b8; color: white; padding: 15px; text-decoration: none; border-radius: 8px; display: inline-block;'>";
echo "🔗 <strong>Catégories</strong><br><small>Gérer les catégories</small>";
echo "</a>";

echo "<a href='droit_acces_simple.php' style='background: #6c757d; color: white; padding: 15px; text-decoration: none; border-radius: 8px; display: inline-block;'>";
echo "🔗 <strong>Gestion Droits</strong><br><small>Interface simplifiée</small>";
echo "</a>";
echo "</div>";

echo "<h2>📝 Résumé :</h2>";
echo "<ul>";
echo "<li>✅ Tous les droits nécessaires ont été configurés</li>";
echo "<li>✅ L'utilisateur peut maintenant accéder à toutes les pages d'articles</li>";
echo "<li>✅ Les boutons seront activés selon les droits accordés</li>";
echo "<li>✅ Utilisez l'interface simplifiée pour ajuster les droits si nécessaire</li>";
echo "</ul>";

echo "<p><strong>Note :</strong> Si vous voulez des droits plus restrictifs, utilisez l'interface de gestion des droits pour décocher certaines actions.</p>";
?> 