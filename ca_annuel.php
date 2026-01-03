<?php    
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();

    $annee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');

    // CORRECTION : Logique pour éviter la double comptabilisation
    // On ne compte que les ventes normales + les acomptes des ventes à crédit
    
    // Ventes normales (incluant les ventes à crédit soldées)
    $sql_chiffre_affaires = "SELECT SUM(MontantTotal) AS chiffre_affaires FROM vente WHERE YEAR(DateIns) = :annee";
    
    // Acomptes des ventes à crédit non soldées (pour éviter double comptage)
    $sql_acomptes_credit = "
        SELECT SUM(AccompteVerse) AS acomptes_credit 
        FROM ventes_credit 
        WHERE YEAR(DateIns) = :annee 
        AND Statut != 'Soldé'
    ";
    
    // Ventes à crédit soldées (déjà comptées dans vente normale)
    $sql_ventes_credit_soldees = "
        SELECT SUM(MontantTotalCredit) AS ventes_soldees 
        FROM ventes_credit 
        WHERE YEAR(DateIns) = :annee 
        AND Statut = 'Soldé'
    ";

    // Exécution des requêtes
    $stmt = $cnx->prepare($sql_chiffre_affaires);
    $stmt->execute(['annee' => $annee]);
    $chiffre_affaires = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_acomptes_credit);
    $stmt->execute(['annee' => $annee]);
    $acomptes_credit = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_ventes_credit_soldees);
    $stmt->execute(['annee' => $annee]);
    $ventes_credit_soldees = $stmt->fetchColumn() ?: 0;

    // Chiffre d'affaires réel = Ventes normales + Acomptes crédit non soldés
    $chiffre_affaires_total = $chiffre_affaires + $acomptes_credit;
    
    // Pour l'affichage détaillé
    $chiffre_affaires_credit = $acomptes_credit; // Seuls les acomptes
    $chiffre_affaires_normal = $chiffre_affaires; // Ventes normales + crédit soldé

    // Requêtes SQL
    $sql_total_ventes = "SELECT COUNT(*) AS total_ventes FROM vente WHERE YEAR(DateIns) = :annee";
    $sql_total_versement = "SELECT SUM(MontantVersement) AS total_versement FROM versement WHERE YEAR(DateIns) = :annee";
    $sql_total_remise = "SELECT SUM(MontantRemise) AS total_remise FROM vente WHERE YEAR(DateIns) = :annee";
    
    $sql_cout_total = "
    SELECT SUM(a.PrixAchatHT * fa.QuantiteVendue) AS prixachat
    FROM facture_article fa
    JOIN article a ON fa.IDARTICLE = a.IDARTICLE
    JOIN vente v ON fa.NumeroVente = v.NumeroVente
    WHERE YEAR(v.DateIns) = :annee
    ";
    
    $sql_total_ventes_credit = "SELECT COUNT(*) AS total_ventes_credit FROM ventes_credit WHERE YEAR(DateIns) = :annee";

    // Exécution des requêtes
    $stmt = $cnx->prepare($sql_total_ventes);
    $stmt->execute(['annee' => $annee]);
    $total_ventes = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_total_versement);
    $stmt->execute(['annee' => $annee]);
    $total_versement = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_total_remise);
    $stmt->execute(['annee' => $annee]);
    $total_remise = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_cout_total);
    $stmt->execute(['annee' => $annee]);
    $prixachat = $stmt->fetchColumn() ?: 0;

    $stmt = $cnx->prepare($sql_total_ventes_credit);
    $stmt->execute(['annee' => $annee]);
    $total_ventes_credit = $stmt->fetchColumn() ?: 0;

    // Calculs
    $benefice = $chiffre_affaires - $prixachat;
    $total_ventes_complet = $total_ventes + $total_ventes_credit;
    $ecart_versement = $total_versement - $chiffre_affaires;
    $moyenne_mensuelle = $chiffre_affaires_total / 12;
    $marge_beneficiaire = $chiffre_affaires_total > 0 ? ($benefice / $chiffre_affaires_total) * 100 : 0;

    // Formatage
    if ($benefice < 0) {
        $benefice_format = '<span style="color: red; animation: blink 1s infinite;">-' . number_format(abs($benefice), 0, ',', ' ') . ' FCFA</span>';
    } else {
        $benefice_format = number_format($benefice, 0, ',', ' ') . ' FCFA';
    }

    if ($ecart_versement < 0) {
        $ecart_versement_format = '<span style="color: red; animation: blink 1s infinite;">-' . number_format(abs($ecart_versement), 0, ',', ' ') . ' FCFA</span>';
    } else {
        $ecart_versement_format = number_format($ecart_versement, 0, ',', ' ') . ' FCFA';
    }

    // Données mensuelles - CORRECTION pour éviter double comptabilisation
    $sql_ventes_mensuelles = "
        SELECT MONTH(DateIns) AS mois, SUM(MontantTotal) AS montant, COUNT(*) AS nombre_ventes
        FROM vente
        WHERE YEAR(DateIns) = :annee
        GROUP BY MONTH(DateIns)
        ORDER BY mois
    ";

    $stmt = $cnx->prepare($sql_ventes_mensuelles);
    $stmt->execute(['annee' => $annee]);
    $ventes_mensuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $montants_mensuels = array_fill(0, 12, 0);
    $debug_mensuel = "Données mensuelles:\n";
    foreach ($ventes_mensuelles as $row) {
        $montants_mensuels[$row['mois'] - 1] = (float)$row['montant'];
        $debug_mensuel .= "Mois " . $row['mois'] . ": " . number_format($row['montant'], 0, ',', ' ') . " FCFA (" . $row['nombre_ventes'] . " ventes)\n";
    }

    // AJOUT : Acomptes des ventes à crédit non soldées par mois
    $sql_acomptes_credit_mensuels = "
        SELECT MONTH(DateIns) AS mois, SUM(AccompteVerse) AS montant, COUNT(*) AS nombre_ventes
        FROM ventes_credit
        WHERE YEAR(DateIns) = :annee AND Statut != 'Soldé'
        GROUP BY MONTH(DateIns)
        ORDER BY mois
    ";

    $stmt = $cnx->prepare($sql_acomptes_credit_mensuels);
    $stmt->execute(['annee' => $annee]);
    $acomptes_credit_mensuels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $debug_mensuel .= "\nAcomptes crédit mensuels (non soldés):\n";
    foreach ($acomptes_credit_mensuels as $row) {
        $montants_mensuels[$row['mois'] - 1] += (float)$row['montant'];
        $debug_mensuel .= "Mois " . $row['mois'] . ": " . number_format($row['montant'], 0, ',', ' ') . " FCFA (" . $row['nombre_ventes'] . " acomptes)\n";
    }

    $debug_mensuel .= "\nTotal par mois (ventes + acomptes crédit):\n";
    for ($i = 0; $i < 12; $i++) {
        if ($montants_mensuels[$i] > 0) {
            $debug_mensuel .= "Mois " . ($i + 1) . ": " . number_format($montants_mensuels[$i], 0, ',', ' ') . " FCFA\n";
        }
    }

    // Meilleurs articles
    $sql_meilleurs_articles = "
        SELECT a.libelle, COUNT(fa.IDARTICLE) AS total_ventes
        FROM facture_article fa
        JOIN article a ON fa.IDARTICLE = a.IDARTICLE
        JOIN vente v ON fa.NumeroVente = v.NumeroVente
        WHERE YEAR(v.DateIns) = :annee
        GROUP BY a.libelle, a.IDARTICLE
        ORDER BY total_ventes DESC
        LIMIT 10
    ";

    $stmt = $cnx->prepare($sql_meilleurs_articles);
    $stmt->execute(['annee' => $annee]);
    $meilleurs_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $debug_info = "Année $annee - CA Total: " . number_format($chiffre_affaires_total, 0, ',', ' ') . " FCFA - Bénéfice: " . number_format($benefice, 0, ',', ' ') . " FCFA\n\n";
    $debug_info .= "Logique de comptabilisation:\n";
    $debug_info .= "- Ventes normales + crédit soldé: " . number_format($chiffre_affaires_normal, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Acomptes crédit non soldés: " . number_format($chiffre_affaires_credit, 0, ',', ' ') . " FCFA\n";
    $debug_info .= "- Ventes crédit soldées (déjà comptées): " . number_format($ventes_credit_soldees, 0, ',', ' ') . " FCFA\n\n";
    $debug_info .= $debug_mensuel;

} catch (Throwable $th) {
    echo 'Erreur serveur : ' . $th->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CA Annuel <?php echo $annee; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0; }
            100% { opacity: 1; }
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        header {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .year-picker {
            text-align: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .year-picker input[type="number"] {
            border: 3px solid #ddd;
            border-radius: 12px;
            padding: 15px;
            font-size: 18px;
            width: 150px;
            margin-right: 15px;
        }
        
        .year-picker button {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .year-picker button:hover {
            transform: translateY(-2px);
        }
        
        .info-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin: 15px 0;
            transition: all 0.3s;
        }
        
        .info-box:hover {
            transform: translateY(-8px);
        }
        
        .info-icon {
            font-size: 40px;
            color: #ff0000;
            margin-bottom: 15px;
        }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
        
        .debug-section {
            background: rgba(248, 249, 250, 0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #007bff;
        }
        
        .debug-section pre {
            background: white;
            padding: 15px;
            border-radius: 10px;
            font-size: 13px;
            margin: 0;
        }
        
        .stats-detail {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border: none;
            color: #0c5460;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .articles-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
        
        .articles-list {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #eee;
            border-radius: 15px;
            padding: 20px;
            background: white;
        }
        
        .article-item {
            padding: 15px;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .article-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }
        
        .article-name {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .article-sales {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .metric-highlight {
            font-size: 24px;
            font-weight: bold;
            color: #ff0000;
            margin: 10px 0;
        }
        
        .section-title {
            color: #333;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <header>
        <h1><i class="fas fa-chart-line"></i> Tableau de Bord CA Annuel <?php echo $annee; ?></h1>
        <p>Analyse complète de vos performances commerciales</p>
    </header>

    <div class="container">
        <div class="year-picker">
            <form method="GET" action="">
                <label for="annee"><strong>Sélectionnez l'année :</strong></label>
                <input type="number" name="annee" id="annee" value="<?php echo $annee; ?>" min="2000" max="2030" required>
                <button type="submit"><i class="fas fa-search"></i> Analyser</button>
            </form>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Année analysée :</strong> <?php echo $annee; ?> - Résultats complets
        </div>

        <div class="debug-section">
            <h5><i class="fas fa-bug"></i> Debug - Données SQL :</h5>
            <pre><?php echo htmlspecialchars($debug_info); ?></pre>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-euro-sign"></i>
                    <h5>Chiffre d'Affaires</h5>
                    <div class="metric-highlight"><?php echo number_format($chiffre_affaires_total, 0, ',', ' '); ?> FCFA</div>
                    <div class="stats-detail">
                        Ventes: <?php echo number_format($chiffre_affaires_normal, 0, ',', ' '); ?> FCFA<br>
                        Acomptes crédit: <?php echo number_format($chiffre_affaires_credit, 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-shopping-cart"></i>
                    <h5>Total Ventes</h5>
                    <div class="metric-highlight"><?php echo number_format($total_ventes_complet, 0, ',', ' '); ?></div>
                    <div class="stats-detail">
                        Ventes: <?php echo number_format($total_ventes, 0, ',', ' '); ?><br>
                        Crédit: <?php echo number_format($total_ventes_credit, 0, ',', ' '); ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-chart-line"></i>
                    <h5>Bénéfice Réalisé</h5>
                    <div class="metric-highlight"><?php echo $benefice_format; ?></div>
                    <div class="stats-detail">
                        Marge: <?php echo number_format($marge_beneficiaire, 2); ?>%<br>
                        Coût: <?php echo number_format($prixachat, 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="info-box">
                    <i class="info-icon fas fa-trophy"></i>
                    <h5>Moyenne Mensuelle</h5>
                    <div class="metric-highlight"><?php echo number_format($moyenne_mensuelle, 0, ',', ' '); ?> FCFA</div>
                    <div class="stats-detail">
                        Performance annuelle<br>
                        <?php echo number_format($total_ventes_complet / 12, 1); ?> ventes/mois
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-dollar-sign"></i>
                    <h5>Total Versements</h5>
                    <div class="metric-highlight"><?php echo number_format($total_versement, 0, ',', ' '); ?> FCFA</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-tags"></i>
                    <h5>Total Remises</h5>
                    <div class="metric-highlight"><?php echo number_format($total_remise, 0, ',', ' '); ?> FCFA</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="info-box">
                    <i class="info-icon fas fa-exclamation-circle"></i>
                    <h5>Écart Versements</h5>
                    <div class="metric-highlight"><?php echo $ecart_versement_format; ?></div>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <h3 class="section-title"><i class="fas fa-chart-line"></i> Évolution des Ventes par Mois</h3>
            <div style="height: 400px;">
                <canvas id="ventesChart"></canvas>
            </div>
        </div>

        <div class="articles-section">
            <h3 class="section-title"><i class="fas fa-star"></i> Top 10 des Meilleurs Articles</h3>
            <div class="articles-list">
                <?php if (!empty($meilleurs_articles)): ?>
                    <?php foreach ($meilleurs_articles as $index => $article): ?>
                        <div class="article-item">
                            <div>
                                <span class="article-name"><?php echo ($index + 1) . '. ' . htmlspecialchars($article['libelle']); ?></span>
                            </div>
                            <span class="article-sales"><?php echo $article['total_ventes']; ?> ventes</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted">Aucun article vendu pour cette année</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var ctx = document.getElementById('ventesChart').getContext('2d');
        var ventesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Chiffre d\'Affaires Mensuel (FCFA)',
                    data: <?php echo json_encode($montants_mensuels); ?>,
                    backgroundColor: 'rgba(255, 0, 0, 0.1)',
                    borderColor: 'rgba(255, 0, 0, 1)',
                    borderWidth: 4,
                    pointBackgroundColor: 'rgba(255, 0, 0, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: 8,
                    pointHoverRadius: 12,
                    fill: true,
                    tension: 0.3,
                    stepped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Montant (FCFA)',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                            },
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Mois',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#ff0000',
                        borderWidth: 2,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(tooltipItem) {
                                return tooltipItem.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(tooltipItem.raw) + ' FCFA';
                            }
                        }
                    },
                    legend: {
                        labels: {
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 