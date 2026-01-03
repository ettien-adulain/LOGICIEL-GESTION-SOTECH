<?php
/**
 * Script d'analyse des références de fichiers
 * Identifie tous les liens et références vers des fichiers PHP
 */

echo "<h2>🔍 Analyse des Références de Fichiers</h2>\n";

// Liste des fichiers PHP avec majuscules
$filesWithCaps = [
    'articles.php',
    'bon_commande.php',
    'caisse.php',
    'categorie_article.php',
    'ca_annuel.php',
    'commande.php',
    'connexion.php',
    'correction_stock.php',
    'creation_d_article.php',
    'creer_compte_utilisateur.php',
    'creation_messages_personnalises.php',
    'entrer_numero.php',
    'envoyer_sms.php',
    'facture_proforma.php',
    'fournisseur.php',
    'generateur_d_etiquette.php',
    'index.php',
    'listes_vente.php',
    'liste_article.php',
    'liste_commande.php',
    'liste_numeroserie.php',
    'liste_utilisateurs.php',
    'mode_reglement.php',
    'parametre.php',
    'parametre_email.php',
    'parametre_entreprise.php',
    'parametre_general.php',
    'parametre_sms.php',
    'print_facture_standardcredit.php',
    'print_facture_tvacredit.php',
    'print_ticket_caissecredit.php',
    'sav.php',
    'sav_administration.php',
    'sav_export.php',
    'sav_facture.php',
    'sav_impression.php',
    'sav_suivi.php',
    'untitled-1.php',
    'untitled-2.php',
    'utilisateur.php',
    'vente.php',
    'vente_jour.php',
    'versement.php'
];

// Créer le mapping des anciens vers nouveaux noms
$fileMapping = [];
foreach ($filesWithCaps as $file) {
    $newName = strtolower($file);
    $fileMapping[$file] = $newName;
}

echo "<h3>📋 Mapping des Fichiers</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Ancien Nom</th><th>Nouveau Nom</th></tr>\n";
foreach ($fileMapping as $old => $new) {
    echo "<tr><td>$old</td><td>$new</td></tr>\n";
}
echo "</table>\n";

// Analyser tous les fichiers PHP pour trouver les références
$allPhpFiles = glob('*.php');
$references = [];

echo "<h3>🔗 Références Trouvées</h3>\n";

foreach ($allPhpFiles as $phpFile) {
    $content = file_get_contents($phpFile);
    $foundRefs = [];
    
    foreach ($filesWithCaps as $targetFile) {
        // Rechercher différents patterns de référence
        $patterns = [
            "/href\s*=\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i",
            "/action\s*=\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i",
            "/include\s*\(\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i",
            "/require\s*\(\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i",
            "/include_once\s*\(\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i",
            "/require_once\s*\(\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i",
            "/header\s*\(\s*['\"]Location:\s*" . preg_quote($targetFile, '/') . "['\"]/i",
            "/window\.location\s*=\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i",
            "/location\.href\s*=\s*['\"]" . preg_quote($targetFile, '/') . "['\"]/i"
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $foundRefs[] = $targetFile;
                break;
            }
        }
    }
    
    if (!empty($foundRefs)) {
        $references[$phpFile] = $foundRefs;
        echo "<h4>📄 $phpFile</h4>\n";
        echo "<ul>\n";
        foreach ($foundRefs as $ref) {
            echo "<li>→ $ref</li>\n";
        }
        echo "</ul>\n";
    }
}

// Générer le script de renommage
echo "<h3>🔄 Script de Renommage</h3>\n";
echo "<pre>\n";
echo "# Script PowerShell pour renommer les fichiers\n";
echo "# ATTENTION: Exécuter dans l'ordre pour éviter les conflits\n\n";

foreach ($fileMapping as $old => $new) {
    if ($old !== $new) {
        echo "Rename-Item \"$old\" \"$new\"\n";
    }
}
echo "</pre>\n";

// Générer le script de mise à jour des références
echo "<h3>✏️ Script de Mise à Jour des Références</h3>\n";
echo "<pre>\n";
echo "# Script PowerShell pour mettre à jour les références\n\n";

foreach ($fileMapping as $old => $new) {
    if ($old !== $new) {
        echo "# Mise à jour des références vers $old\n";
        echo "Get-ChildItem -Name \"*.php\" | ForEach-Object {\n";
        echo "    (Get-Content \$_) -replace \"$old\", \"$new\" | Set-Content \$_\n";
        echo "}\n\n";
    }
}
echo "</pre>\n";

echo "<h3>⚠️ Recommandations</h3>\n";
echo "<ol>\n";
echo "<li><strong>Sauvegarder</strong> tout le projet avant de commencer</li>\n";
echo "<li><strong>Tester</strong> sur un environnement de développement</li>\n";
echo "<li><strong>Exécuter</strong> d'abord le script de renommage</li>\n";
echo "<li><strong>Puis</strong> exécuter le script de mise à jour des références</li>\n";
echo "<li><strong>Vérifier</strong> que tous les liens fonctionnent</li>\n";
echo "</ol>\n";
?>
