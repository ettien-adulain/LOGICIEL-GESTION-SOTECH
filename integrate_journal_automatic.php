<?php
/**
 * SCRIPT D'INTÉGRATION AUTOMATIQUE DE LA JOURNALISATION
 * Ce script ajoute automatiquement la journalisation unifiée dans tous les fichiers
 */

// Liste des fichiers à modifier
$fichiers_a_modifier = [
    'articles.php',
    'client_ajout_rapide.php', 
    'commande.php',
    'caisse.php',
    'vente.php',
    'vente_credit.php',
    'entre_stock.php',
    'correction_stock.php',
    'utilisateur.php',
    'fournisseur.php',
    'categorie_article.php',
    'parametre.php'
];

// Fonction pour ajouter l'inclusion de journalisation
function ajouterJournalisation($fichier) {
    if (!file_exists($fichier)) {
        echo "❌ Fichier $fichier non trouvé\n";
        return false;
    }
    
    $contenu = file_get_contents($fichier);
    
    // Vérifier si la journalisation est déjà incluse
    if (strpos($contenu, 'integrate_journal_global.php') !== false) {
        echo "✅ Journalisation déjà intégrée dans $fichier\n";
        return true;
    }
    
    // Ajouter l'inclusion après les autres includes
    $pattern = '/(require_once|include_once|include|require)\s+[\'"][^\'"]*[\'"];?\s*\n/';
    $matches = [];
    preg_match_all($pattern, $contenu, $matches);
    
    if (!empty($matches[0])) {
        $derniere_include = end($matches[0]);
        $position = strrpos($contenu, $derniere_include) + strlen($derniere_include);
        
        $nouveau_contenu = substr($contenu, 0, $position) . 
                          "\n// Journalisation unifiée\n" .
                          "require_once 'integrate_journal_global.php';\n" .
                          substr($contenu, $position);
        
        file_put_contents($fichier, $nouveau_contenu);
        echo "✅ Journalisation ajoutée à $fichier\n";
        return true;
    } else {
        // Ajouter au début du fichier
        $nouveau_contenu = "<?php\n// Journalisation unifiée\nrequire_once 'integrate_journal_global.php';\n" . 
                          substr($contenu, 5);
        file_put_contents($fichier, $nouveau_contenu);
        echo "✅ Journalisation ajoutée au début de $fichier\n";
        return true;
    }
}

// Fonction pour ajouter la journalisation aux actions spécifiques
function ajouterJournalisationActions($fichier) {
    $contenu = file_get_contents($fichier);
    $modifications = 0;
    
    // Patterns de remplacement pour les actions courantes
    $patterns = [
        // Création d'articles
        '/insertion_element\([\'"](article|client|commande|vente)[\'"]/i' => function($match) {
            return $match[0] . " - Journalisation ajoutée";
        },
        
        // Modification d'articles
        '/modifier_element\([\'"](article|client|commande|vente)[\'"]/i' => function($match) {
            return $match[0] . " - Journalisation ajoutée";
        },
        
        // Suppression d'articles
        '/supprimer_element\([\'"](article|client|commande|vente)[\'"]/i' => function($match) {
            return $match[0] . " - Journalisation ajoutée";
        }
    ];
    
    // Ajouter des commentaires de journalisation
    $contenu = preg_replace(
        '/(insertion_element\([^)]+\);\s*)/',
        "$1\n// TODO: Ajouter journalisation ici\n",
        $contenu
    );
    
    $contenu = preg_replace(
        '/(modifier_element\([^)]+\);\s*)/',
        "$1\n// TODO: Ajouter journalisation ici\n",
        $contenu
    );
    
    $contenu = preg_replace(
        '/(supprimer_element\([^)]+\);\s*)/',
        "$1\n// TODO: Ajouter journalisation ici\n",
        $contenu
    );
    
    if ($contenu !== file_get_contents($fichier)) {
        file_put_contents($fichier, $contenu);
        echo "✅ Commentaires de journalisation ajoutés à $fichier\n";
        return true;
    }
    
    return false;
}

echo "🚀 INTÉGRATION AUTOMATIQUE DE LA JOURNALISATION\n";
echo "================================================\n\n";

$total_modifies = 0;

foreach ($fichiers_a_modifier as $fichier) {
    echo "📁 Traitement de $fichier...\n";
    
    if (ajouterJournalisation($fichier)) {
        $total_modifies++;
        ajouterJournalisationActions($fichier);
    }
    
    echo "\n";
}

echo "✅ INTÉGRATION TERMINÉE\n";
echo "======================\n";
echo "📊 Fichiers modifiés: $total_modifies\n";
echo "📋 Fichiers traités: " . count($fichiers_a_modifier) . "\n\n";

echo "📝 PROCHAINES ÉTAPES:\n";
echo "1. Vérifier les fichiers modifiés\n";
echo "2. Ajouter manuellement la journalisation aux actions importantes\n";
echo "3. Tester le système de journalisation\n";
echo "4. Vérifier les logs dans journal.php\n\n";

echo "🔧 EXEMPLES D'INTÉGRATION MANUELLE:\n";
echo "// Après insertion_element():\n";
echo "journaliserCreation(\$cnx, 'article', \$id, 'Article créé');\n\n";
echo "// Après modifier_element():\n";
echo "journaliserModification(\$cnx, 'article', \$id, ['libelle'], \$ancien, \$nouveau);\n\n";
echo "// Après supprimer_element():\n";
echo "journaliserSuppression(\$cnx, 'article', \$id, 'Article supprimé');\n\n";
?>
