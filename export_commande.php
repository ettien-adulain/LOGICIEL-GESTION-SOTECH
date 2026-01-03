<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Vérification de la session AVANT tout
session_start();
if (!isset($_SESSION['nom_utilisateur'])) {
    header('Location: connexion.php');
    exit();
}

include('db/connecting.php');

$type = isset($_GET['type']) ? $_GET['type'] : 'excel';

// Récupérer toutes les commandes et leurs lignes
$commandes = $cnx->query("SELECT c.id, c.numero_commande, c.date_commande, c.totalprixAchat, f.NomFournisseur FROM commande c INNER JOIN fournisseur f ON c.IDFOURNISSEUR = f.IDFOURNISSEUR ORDER BY c.date_commande DESC")->fetchAll(PDO::FETCH_ASSOC);

// Préparer les lignes de commande groupées par commande
$commandes_lignes = [];
foreach ($commandes as $commande) {
    $stmt = $cnx->prepare("SELECT a.libelle, a.Descriptif, cl.prixAchat, cl.quantite FROM commande_ligne cl INNER JOIN article a ON cl.IDARTICLE = a.IDARTICLE WHERE cl.id = ?");
    $stmt->execute([$commande['id']]);
    $commandes_lignes[$commande['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($type === 'excel') {
    // Export CSV (compatible Excel)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commandes.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, ['N° Bon', 'Date', 'Fournisseur', 'Total (F.CFA)', 'Libellé', 'Descriptif', 'Prix Achat', 'Quantité'], ';');
    
    // Données
    foreach ($commandes as $commande) {
        if (empty($commandes_lignes[$commande['id']])) {
            // Commande sans lignes
            fputcsv($output, [
                $commande['numero_commande'],
                $commande['date_commande'],
                $commande['NomFournisseur'],
                number_format($commande['totalprixAchat'], 0, ',', ' '),
                '', '', '', ''
            ], ';');
        } else {
            // Commande avec lignes
            foreach ($commandes_lignes[$commande['id']] as $ligne) {
                fputcsv($output, [
                    $commande['numero_commande'],
                    $commande['date_commande'],
                    $commande['NomFournisseur'],
                    number_format($commande['totalprixAchat'], 0, ',', ' '),
                    $ligne['libelle'],
                    $ligne['Descriptif'],
                    number_format($ligne['prixAchat'], 0, ',', ' '),
                    $ligne['quantite']
                ], ';');
            }
        }
    }
    
    fclose($output);
    exit();
}

if ($type === 'word') {
    // Export HTML (compatible Word)
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="commandes.doc"');
    
    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<h1>Liste des Commandes</h1>';
    echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<th>N° Bon</th><th>Date</th><th>Fournisseur</th><th>Total (F.CFA)</th><th>Libellé</th><th>Descriptif</th><th>Prix Achat</th><th>Quantité</th>';
    echo '</tr>';
    
    foreach ($commandes as $commande) {
        if (empty($commandes_lignes[$commande['id']])) {
            // Commande sans lignes
            echo '<tr>';
            echo '<td>' . htmlspecialchars($commande['numero_commande']) . '</td>';
            echo '<td>' . htmlspecialchars($commande['date_commande']) . '</td>';
            echo '<td>' . htmlspecialchars($commande['NomFournisseur']) . '</td>';
            echo '<td>' . number_format($commande['totalprixAchat'], 0, ',', ' ') . '</td>';
            echo '<td colspan="4"></td>';
            echo '</tr>';
        } else {
            // Commande avec lignes
            $first = true;
            foreach ($commandes_lignes[$commande['id']] as $ligne) {
                echo '<tr>';
                if ($first) {
                    echo '<td rowspan="' . count($commandes_lignes[$commande['id']]) . '">' . htmlspecialchars($commande['numero_commande']) . '</td>';
                    echo '<td rowspan="' . count($commandes_lignes[$commande['id']]) . '">' . htmlspecialchars($commande['date_commande']) . '</td>';
                    echo '<td rowspan="' . count($commandes_lignes[$commande['id']]) . '">' . htmlspecialchars($commande['NomFournisseur']) . '</td>';
                    echo '<td rowspan="' . count($commandes_lignes[$commande['id']]) . '">' . number_format($commande['totalprixAchat'], 0, ',', ' ') . '</td>';
                    $first = false;
                }
                echo '<td>' . htmlspecialchars($ligne['libelle']) . '</td>';
                echo '<td>' . htmlspecialchars($ligne['Descriptif']) . '</td>';
                echo '<td>' . number_format($ligne['prixAchat'], 0, ',', ' ') . '</td>';
                echo '<td>' . htmlspecialchars($ligne['quantite']) . '</td>';
                echo '</tr>';
            }
        }
    }
    
    echo '</table></body></html>';
    exit();
}

if ($type === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="commandes.txt"');
    
    echo "LISTE DES COMMANDES\n";
    echo "==================\n\n";
    
    foreach ($commandes as $commande) {
        echo "N° Bon: " . $commande['numero_commande'] . "\n";
        echo "Date: " . $commande['date_commande'] . "\n";
        echo "Fournisseur: " . $commande['NomFournisseur'] . "\n";
        echo "Total: " . number_format($commande['totalprixAchat'], 0, ',', ' ') . " F.CFA\n";
        
        if (!empty($commandes_lignes[$commande['id']])) {
            echo "Détail des articles:\n";
            echo "Libellé\t\t\tDescriptif\t\t\tPrix Achat\tQuantité\n";
            echo str_repeat("-", 80) . "\n";
            foreach ($commandes_lignes[$commande['id']] as $ligne) {
                echo $ligne['libelle'] . "\t\t" . $ligne['Descriptif'] . "\t\t" . 
                     number_format($ligne['prixAchat'], 0, ',', ' ') . "\t\t" . $ligne['quantite'] . "\n";
            }
        }
        echo "\n" . str_repeat("=", 80) . "\n\n";
    }
    exit();
}

echo 'Format non supporté.'; 