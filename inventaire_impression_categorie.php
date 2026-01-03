<?php
/**
 * IMPRESSION INVENTAIRE PAR CATÉGORIE
 * Génération de fiches d'inventaire PDF par catégorie avec différents types de comptage
 * 
 * @author SOTECH
 * @version 2.0
 * @date 2024
 */

// Nettoyer tous les buffers de sortie pour éviter les headers prématurés
while (ob_get_level()) {
    ob_end_clean();
}

// Désactiver l'affichage des erreurs pour éviter les headers prématurés
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Charger les dépendances
    include('db/connecting.php');
    
    // Charger DOMPDF directement sans passer par config_paths.php
    $autoloadPath = null;
    $possiblePaths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $autoloadPath = $path;
            break;
        }
    }
    
    if (!$autoloadPath) {
        throw new Exception("Aucun autoloader Composer trouvé. Veuillez installer DOMPDF via Composer.");
    }
    
    require_once($autoloadPath);
    
    if (!class_exists('Dompdf\Dompdf')) {
        throw new Exception("DOMPDF n'est pas installé. Veuillez exécuter : composer require dompdf/dompdf");
    }
    
    // Vérifier l'authentification
    if (!isset($_SESSION['nom_utilisateur'])) {
        throw new Exception("Vous devez être connecté pour accéder à cette page.");
    }
    
    // Récupérer et valider les paramètres
    $idInventaire = isset($_GET['IDINVENTAIRE']) ? intval($_GET['IDINVENTAIRE']) : 0;
    $categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
    $imprimer_ecarts = isset($_GET['ecarts']) ? $_GET['ecarts'] == '1' : false;
    $type_comptage = isset($_GET['type_comptage']) ? $_GET['type_comptage'] : 'comptage1';
    
    // Validation des paramètres
    if ($idInventaire <= 0) {
        throw new Exception("ID d'inventaire invalide");
    }
    
    // Types de comptage valides
    $types_comptage_valides = ['comptage1', 'comptage2', 'comptage3', 'comptage4'];
    if (!in_array($type_comptage, $types_comptage_valides)) {
        $type_comptage = 'comptage1';
    }
    
    // Récupération des informations de l'inventaire
    $stmt = $cnx->prepare("SELECT * FROM inventaire WHERE IDINVENTAIRE = ?");
    $stmt->execute([$idInventaire]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventaire) {
        throw new Exception("Inventaire non trouvé");
    }
    
    // Récupération des informations de l'entreprise
    $stmt = $cnx->prepare("SELECT * FROM entreprise LIMIT 1");
    $stmt->execute();
    $entreprise = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Valeurs par défaut si pas d'entreprise
    if (!$entreprise) {
        $entreprise = [
            'nom' => 'ENTREPRISE',
            'adresse' => 'Adresse de l\'entreprise',
            'telephone' => 'Téléphone',
            'email' => 'email@entreprise.com'
        ];
    }
    
    // Construction de la requête selon les filtres
    $where_conditions = ["il.id_inventaire = ?"];
    $params = [$idInventaire];
    
    if (!empty($categorie)) {
        $where_conditions[] = "il.categorie = ?";
        $params[] = $categorie;
    }
    
    if ($imprimer_ecarts) {
        $where_conditions[] = "(il.ecart != 0 OR il.ecart IS NULL)";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Récupération des lignes d'inventaire
    $sql = "
        SELECT 
            il.*,
            a.PrixAchatHT,
            a.PrixVenteTTC,
            s.StockActuel as stock_actuel
        FROM inventaire_ligne il 
        LEFT JOIN article a ON il.id_article = a.IDARTICLE 
        LEFT JOIN stock s ON il.id_article = s.IDARTICLE
        WHERE $where_clause 
        ORDER BY il.categorie, il.code_article
    ";
    
    $stmt = $cnx->prepare($sql);
    $stmt->execute($params);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lignes)) {
        throw new Exception("Aucune ligne d'inventaire trouvée pour les critères sélectionnés");
    }
    
    // Récupération des données de tous les comptages précédents
    foreach ($lignes as &$ligne) {
        $stmt = $cnx->prepare("
            SELECT 
                it.qte_physique,
                it.date_saisie,
                it.id_utilisateur,
                GROUP_CONCAT(its.numero_serie ORDER BY its.numero_serie SEPARATOR ', ') as series_trouvees
            FROM inventaire_temp it
            LEFT JOIN inventaire_temp_series its ON it.id = its.id_inventaire_temp
            WHERE it.id_inventaire = ? AND it.id_article = ?
            GROUP BY it.id
            ORDER BY it.date_saisie ASC
        ");
        $stmt->execute([$idInventaire, $ligne['id_article']]);
        $comptages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les données par comptage
        $ligne['comptages'] = [];
        foreach ($comptages as $index => $comptage) {
            $ligne['comptages'][] = [
                'numero' => $index + 1,
                'qte' => $comptage['qte_physique'],
                'date' => $comptage['date_saisie'],
                'utilisateur' => $comptage['id_utilisateur'],
                'series' => $comptage['series_trouvees'] ? explode(', ', $comptage['series_trouvees']) : []
            ];
        }
    }
    unset($ligne);
    
    // Groupement par catégorie
    $categories = [];
    foreach ($lignes as $ligne) {
        $cat = $ligne['categorie'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = [];
        }
        $categories[$cat][] = $ligne;
    }
    
    // Définir les informations selon le type de comptage
    $comptage_info = [
        'comptage1' => [
            'titre' => 'PREMIER COMPTAGE',
            'description' => 'Comptage initial - Fiches vides pour premier comptage',
            'instructions' => [
                '1. Comptez physiquement chaque article dans le magasin',
                '2. Notez la quantité trouvée dans la colonne "Quantité Comptée"',
                '3. Notez les numéros de série trouvés dans les espaces prévus',
                '4. Laissez vide si l\'article n\'est pas trouvé',
                '5. Soyez attentif et méthodique'
            ],
            'couleur' => '#007bff'
        ],
        'comptage2' => [
            'titre' => 'DEUXIÈME COMPTAGE',
            'description' => 'Vérification - Recompter avec données du premier comptage',
            'instructions' => [
                '1. Vérifiez les quantités du premier comptage',
                '2. Recomptez physiquement chaque article',
                '3. Corrigez si nécessaire dans la colonne "Quantité Comptée"',
                '4. Vérifiez les numéros de série précédemment notés',
                '5. Notez les différences et les raisons'
            ],
            'couleur' => '#28a745'
        ],
        'comptage3' => [
            'titre' => 'TROISIÈME COMPTAGE',
            'description' => 'Contrôle - Troisième vérification par personne différente',
            'instructions' => [
                '1. Contrôlez les résultats des deux premiers comptages',
                '2. Recomptez avec attention particulière aux écarts',
                '3. Vérifiez la cohérence des numéros de série',
                '4. Identifiez les causes des différences',
                '5. Documentez les corrections apportées'
            ],
            'couleur' => '#ffc107'
        ],
        'comptage4' => [
            'titre' => 'COMPTAGE FINAL',
            'description' => 'Audit final - Validation par superviseur',
            'instructions' => [
                '1. Audit final par un superviseur',
                '2. Vérification des écarts persistants',
                '3. Validation des corrections apportées',
                '4. Contrôle de la cohérence globale',
                '5. Approbation finale de l\'inventaire'
            ],
            'couleur' => '#dc3545'
        ]
    ];
    
    $info_comptage = $comptage_info[$type_comptage];
    
    // Génération du HTML pour l'impression
    $html = generateInventaireHTML($inventaire, $entreprise, $categories, $info_comptage, $_SESSION['nom_utilisateur']);
    
    // Configuration DOMPDF
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->setPaper('A4', 'portrait');
    
    // Charger le HTML
    $dompdf->loadHtml($html);
    
    // Rendre le PDF
    $dompdf->render();
    
    // Vérifier que les headers n'ont pas été envoyés
    if (headers_sent($file, $line)) {
        throw new Exception("Headers déjà envoyés depuis $file ligne $line");
    }
    
    // Générer le nom de fichier
    $filename = 'Inventaire_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $inventaire['Commentaires']);
    if (!empty($categorie)) {
        $filename .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $categorie);
    }
    $filename .= '_' . $type_comptage . '_' . date('Y-m-d_H-i') . '.pdf';
    
    // Envoyer le PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Afficher le PDF
    echo $dompdf->output();
    
} catch (Exception $e) {
    // En cas d'erreur, nettoyer les buffers et afficher un message d'erreur
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    
    echo "<!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <title>Erreur d'impression</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; }
            .error h1 { color: #721c24; margin-top: 0; }
            .error p { color: #721c24; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h1>❌ Erreur d'impression</h1>
            <p><strong>Message d'erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>Fichier :</strong> " . htmlspecialchars($e->getFile()) . "</p>
            <p><strong>Ligne :</strong> " . $e->getLine() . "</p>
            <p><a href='javascript:history.back()'>← Retour</a></p>
        </div>
    </body>
    </html>";
}

/**
 * Génère le HTML pour l'impression de l'inventaire
 */
function generateInventaireHTML($inventaire, $entreprise, $categories, $info_comptage, $nom_utilisateur) {
    $html = '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>FICHE D\'INVENTAIRE - ' . htmlspecialchars($inventaire['Commentaires']) . ' - ' . $info_comptage['titre'] . '</title>
        <style>
            @page {
                size: A4 portrait;
                margin: 1cm;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 10pt;
                line-height: 1.2;
                margin: 0;
                padding: 0;
            }
            .page-header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .entreprise-info {
                margin-bottom: 15px;
            }
            .entreprise-nom {
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .entreprise-details {
                font-size: 9pt;
                color: #666;
            }
            .inventaire-info {
                margin-bottom: 20px;
                text-align: center;
            }
            .inventaire-titre {
                font-size: 16pt;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .inventaire-details {
                font-size: 10pt;
                color: #333;
            }
            .type-comptage {
                background-color: ' . $info_comptage['couleur'] . ';
                color: white;
                padding: 8px 15px;
                border-radius: 5px;
                font-weight: bold;
                margin-bottom: 10px;
                display: inline-block;
                font-size: 12pt;
            }
            .description-comptage {
                font-size: 10pt;
                color: #666;
                margin-bottom: 15px;
            }
            .categorie-header {
                background-color: #f0f0f0;
                padding: 8px;
                margin: 20px 0 10px 0;
                border-left: 4px solid ' . $info_comptage['couleur'] . ';
                font-weight: bold;
                font-size: 12pt;
            }
            .table-articles {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .table-articles th {
                background-color: #e9ecef;
                border: 1px solid #dee2e6;
                padding: 6px;
                font-size: 9pt;
                font-weight: bold;
                text-align: center;
            }
            .table-articles td {
                border: 1px solid #dee2e6;
                padding: 4px 6px;
                font-size: 9pt;
                vertical-align: top;
            }
            .code-article {
                font-weight: bold;
                font-family: monospace;
            }
            .libelle-article {
                text-align: left;
            }
            .prix-vente {
                text-align: right;
                font-family: monospace;
            }
            .qte-theorique {
                text-align: center;
                font-weight: bold;
                background-color: #f8f9fa;
            }
            .qte-comptee {
                text-align: center;
                font-weight: bold;
                border: 2px solid ' . $info_comptage['couleur'] . ';
                min-height: 25px;
            }
            .comptages-precedents {
                font-size: 8pt;
                color: #666;
                background-color: #fff3cd;
                padding: 2px;
                margin-bottom: 2px;
            }
            .comptage-item {
                border-bottom: 1px dotted #ccc;
                padding: 1px 0;
            }
            .comptage-numero {
                font-weight: bold;
                color: ' . $info_comptage['couleur'] . ';
            }
            .series-cell {
                min-height: 20px;
                font-family: monospace;
                font-size: 8pt;
                border: 1px solid #dee2e6;
            }
            .series-precedentes {
                background-color: #fff3cd;
                font-size: 7pt;
                color: #666;
                margin-bottom: 2px;
            }
            .page-break {
                page-break-before: always;
            }
            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 8pt;
                color: #666;
                border-top: 1px solid #dee2e6;
                padding-top: 10px;
            }
            .instructions {
                background-color: #e7f3ff;
                border: 1px solid #b3d9ff;
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 5px;
                font-size: 9pt;
            }
            .instructions h4 {
                margin: 0 0 8px 0;
                color: ' . $info_comptage['couleur'] . ';
            }
            .instructions ul {
                margin: 0;
                padding-left: 20px;
            }
            .instructions li {
                margin-bottom: 3px;
            }
            .ecart-warning {
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                padding: 2px 4px;
                font-size: 7pt;
                color: #721c24;
                margin-top: 2px;
            }
        </style>
    </head>
    <body>';

    // En-tête de page
    $html .= '
        <div class="page-header">
            <div class="entreprise-info">
                <div class="entreprise-nom">' . htmlspecialchars($entreprise['nom']) . '</div>
                <div class="entreprise-details">
                    ' . htmlspecialchars($entreprise['adresse']) . '<br>
                    Tél: ' . htmlspecialchars($entreprise['telephone']) . ' | Email: ' . htmlspecialchars($entreprise['email']) . '
                </div>
            </div>
            <div class="inventaire-info">
                <div class="inventaire-titre">FICHE D\'INVENTAIRE</div>
                <div class="inventaire-details">
                    ' . htmlspecialchars($inventaire['Commentaires']) . '<br>
                    Date: ' . date('d/m/Y') . ' | Compteur: ' . htmlspecialchars($nom_utilisateur) . '
                </div>
                <div class="type-comptage">' . $info_comptage['titre'] . '</div>
                <div class="description-comptage">' . $info_comptage['description'] . '</div>
            </div>
        </div>';

    // Instructions selon le type de comptage
    $html .= '
    <div class="instructions">
        <h4>📋 INSTRUCTIONS ' . $info_comptage['titre'] . '</h4>
        <ul>';
    foreach ($info_comptage['instructions'] as $instruction) {
        $html .= '<li>' . htmlspecialchars($instruction) . '</li>';
    }
    $html .= '</ul>
    </div>';

    // Génération des fiches par catégorie
    $firstCategory = true;
    foreach ($categories as $nomCategorie => $articles) {
        if (!$firstCategory) {
            $html .= '<div class="page-break"></div>';
        }
        $firstCategory = false;
        
        $html .= '<div class="categorie-header">' . htmlspecialchars($nomCategorie) . '</div>';
        
        $html .= '
        <table class="table-articles">
            <thead>
                <tr>
                    <th style="width: 12%;">Code Article</th>
                    <th style="width: 25%;">Libellé</th>
                    <th style="width: 10%;">Prix Vente</th>
                    <th style="width: 8%;">Qte Théorique</th>
                    <th style="width: 15%;">Comptages Précédents</th>
                    <th style="width: 10%;">Quantité Comptée</th>
                    <th style="width: 20%;">Numéros de Série Trouvés</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($articles as $article) {
            $html .= '<tr>';
            $html .= '<td class="code-article">' . htmlspecialchars($article['code_article']) . '</td>';
            $html .= '<td class="libelle-article">' . htmlspecialchars($article['designation']) . '</td>';
            $html .= '<td class="prix-vente">' . number_format($article['PrixVenteTTC'], 0, ',', ' ') . ' F.CFA</td>';
            $html .= '<td class="qte-theorique">' . $article['qte_theorique'] . '</td>';
            
            // Cellule pour les comptages précédents
            $html .= '<td class="comptages-precedents">';
            if (!empty($article['comptages'])) {
                foreach ($article['comptages'] as $comptage) {
                    $html .= '<div class="comptage-item">';
                    $html .= '<span class="comptage-numero">C' . $comptage['numero'] . ':</span> ';
                    $html .= $comptage['qte'] . ' (' . date('d/m H:i', strtotime($comptage['date'])) . ')';
                    $html .= '</div>';
                    
                    // Afficher les écarts si il y en a
                    if ($comptage['numero'] > 1) {
                        $ecart = $article['comptages'][$comptage['numero']-2]['qte'] - $comptage['qte'];
                        if ($ecart != 0) {
                            $html .= '<div class="ecart-warning">Écart: ' . ($ecart > 0 ? '+' : '') . $ecart . '</div>';
                        }
                    }
                }
            } else {
                $html .= '<em>Aucun comptage précédent</em>';
            }
            $html .= '</td>';
            
            $html .= '<td class="qte-comptee"></td>';
            
            // Cellule pour les numéros de série
            $html .= '<td class="series-cell">';
            
            // Afficher les numéros de série des comptages précédents
            if (!empty($article['comptages'])) {
                foreach ($article['comptages'] as $comptage) {
                    if (!empty($comptage['series'])) {
                        $html .= '<div class="series-precedentes">';
                        $html .= '<strong>C' . $comptage['numero'] . ':</strong> ' . implode(', ', $comptage['series']);
                        $html .= '</div>';
                    }
                }
            }
            
            // Espaces pour les nouveaux numéros de série
            $maxSeries = max($article['qte_theorique'], 5);
            foreach ($article['comptages'] as $comptage) {
                $maxSeries = max($maxSeries, $comptage['qte']);
            }
            
            for ($i = 1; $i <= $maxSeries; $i++) {
                $html .= '<div style="border-bottom: 1px dotted #ccc; padding: 2px; min-height: 15px;">' . $i . '. ________________</div>';
            }
            $html .= '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        $html .= '
        <div class="footer">
            <strong>Fiche générée le ' . date('d/m/Y H:i') . ' | Catégorie: ' . htmlspecialchars($nomCategorie) . ' | ' . $info_comptage['titre'] . '</strong><br>
            Signature compteur: _____________________ | Signature vérificateur: _____________________<br>
            Observations: _____________________________________________________________________________
        </div>';
    }

    $html .= '</body></html>';
    
    return $html;
}
?>