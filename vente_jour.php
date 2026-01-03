<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Africa/Abidjan'); 
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access(); // Protection automatique selon $DROITS_PAGES

    $date_selectionnee = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $ventes = verifier_element_tous('vente', ['DateIns'], [$date_selectionnee],'');
    $total_ventes = array_sum(array_column($ventes, 'MontantTotal'));
    $nombre_transactions = count($ventes);
    $total_remises = array_sum(array_column($ventes, 'MontantRemise'));
    
    // Calcul des montants pour le versement recommandé
    // 1. Ventes normales (comptant)
    $total_ventes_comptant = $total_ventes;
    
    // 2. Acomptes des ventes à crédit
    $sql_acomptes_credit = "
        SELECT SUM(vcp.AccompteVerse) AS total_acomptes
        FROM ventes_credit_paiement vcp
        JOIN ventes_credit vc ON vcp.IDVenteCredit = vc.IDVenteCredit
        WHERE DATE(vcp.DateIns) = :date 
        AND vc.Statut != 'Transféré'
    ";
    $stmt = $cnx->prepare($sql_acomptes_credit);
    $stmt->execute(['date' => $date_selectionnee]);
    $total_acomptes_credit = $stmt->fetchColumn() ?: 0;
    
    // 3. Paiements SAV
    $sql_sav_paiements = "
        SELECT SUM(sp.montant) AS total_sav
        FROM sav_paiement sp
        JOIN sav_dossier sd ON sp.id_sav = sd.id_sav
        WHERE DATE(sp.date_paiement) = :date
    ";
    $stmt = $cnx->prepare($sql_sav_paiements);
    $stmt->execute(['date' => $date_selectionnee]);
    $total_sav = $stmt->fetchColumn() ?: 0;
    
    // Total recommandé pour le versement
    $total_versement_recommande = $total_ventes_comptant + $total_acomptes_credit + $total_sav;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Liste de Vente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
    <style>
        .date-selector-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .date-selector-card:hover {
            border-color: #007bff;
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .form-control-lg {
            border-radius: 10px;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .form-control-lg:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .btn-lg {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-lg:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .date-indicator {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin: 10px 0;
        }
    </style>
</head>
<body id="liste_vente_jour">
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <header>
        <h1>Espace Vente du Jour
            <br>
            <span id="date"></span>
        </h1>
    </header>
    
    <!-- Sélecteur de date -->
    <div class="container mt-3">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm date-selector-card">
                    <div class="card-body">
                        <form method="GET" action="" class="d-flex align-items-center gap-3">
                            <div class="flex-grow-1">
                                <label for="dateSelector" class="form-label fw-bold text-primary">
                                    <i class="fas fa-calendar-alt me-2"></i>Sélectionner une date
                                </label>
                                <input type="date" 
                                       id="dateSelector" 
                                       name="date" 
                                       class="form-control form-control-lg" 
                                       value="<?= htmlspecialchars($date_selectionnee) ?>"
                                       max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-search me-2"></i>Valider
                                </button>
                                <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-lg" title="Aujourd'hui">
                                    <i class="fas fa-calendar-day"></i>
                                </a>
                            </div>
                        </form>
                        <?php if ($date_selectionnee !== date('Y-m-d')): ?>
                            <div class="text-center mt-3">
                                <div class="date-indicator">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    Affichage des ventes du <?= date('d/m/Y', strtotime($date_selectionnee)) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <main class="container">
        <div class="m-3">
            
        </div>
        <section class="card">
            <h2>
                <i class="fas fa-chart-bar me-2"></i>
                Résumé des Ventes 
                <?php if ($date_selectionnee === date('Y-m-d')): ?>
                    du Jour
                <?php else: ?>
                    du <?= date('d/m/Y', strtotime($date_selectionnee)) ?>
                <?php endif; ?>
            </h2>
            <?php
                if (isset($_GET['success'])) {
                    $successMessage = htmlspecialchars($_GET['success']);
                    echo '<div id="success-alert" class="alert alert-success" role="alert">' . $successMessage . '</div>';
                }
                if (isset($_GET['error'])) {
                    $errorMessage = htmlspecialchars($_GET['error']);
                    echo '<div id="error-alert" class="alert alert-danger" role="alert">' . $errorMessage . '</div>';
                }
            ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card bg-light mb-3">
                        <div class="card-body text-center">
                            <h3>Total des Ventes</h3>
                            <p style="" ><?php echo number_format($total_ventes, 0, ',', ' '); ?> CFA</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light mb-3">
                        <div class="card-body text-center">
                            <h3>Nombre de Transactions</h3>
                            <p class=""><?php echo htmlspecialchars($nombre_transactions, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                <div class="card bg-light mb-3">
                <div class="card-body text-center">
                    <h3>Total des Remises</h3>
                    <p><?php echo number_format($total_remises, 0, ',', ' '); ?> CFA</p> <!-- Affichage dynamique -->
                </div>
            </div>

                </div>
            </div>
        </section>

        <!-- Section Versement Recommandé -->
        <section class="card bg-light">
            <h2><i class="fas fa-calculator me-2"></i> Versement Recommandé</h2>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Instructions</h5>
                <p class="mb-2">Le versement doit inclure <strong>TOUS les encaissements</strong> de la journée :</p>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="card border-success h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-cash-register text-success mb-2" style="font-size: 2rem;"></i>
                            <h5>Ventes Comptant</h5>
                            <p class="text-success h4"><?= number_format($total_ventes_comptant, 0, ',', ' ') ?> FCFA</p>
                            <small class="text-muted">À verser intégralement</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-warning h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-credit-card text-warning mb-2" style="font-size: 2rem;"></i>
                            <h5>Acomptes Crédit</h5>
                            <p class="text-warning h4"><?= number_format($total_acomptes_credit, 0, ',', ' ') ?> FCFA</p>
                            <small class="text-muted">Seulement les acomptes reçus</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-info h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-tools text-info mb-2" style="font-size: 2rem;"></i>
                            <h5>Paiements SAV</h5>
                            <p class="text-info h4"><?= number_format($total_sav, 0, ',', ' ') ?> FCFA</p>
                            <small class="text-muted">Montants SAV encaissés</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <div class="card border-primary">
                    <div class="card-body">
                        <h4><i class="fas fa-money-bill-wave text-primary me-2"></i>Total à verser :</h4>
                        <h2 class="text-primary"><?= number_format($total_versement_recommande, 0, ',', ' ') ?> FCFA</h2>
                        <a href="versement.php" class="btn btn-primary btn-lg mt-2">
                            <i class="fas fa-arrow-right me-2"></i>Aller au Versement
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>
                <i class="fas fa-list me-2"></i>
                Liste des Ventes 
                <?php if ($date_selectionnee === date('Y-m-d')): ?>
                    du Jour
                <?php else: ?>
                    du <?= date('d/m/Y', strtotime($date_selectionnee)) ?>
                <?php endif; ?>
            </h2>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr class="text-center">
                            <th>#</th>
                            <th>Client</th>
                            <th>Numéro Vente</th>
                            <th>Total avec remise</th>
                            <th>Montant remise</th>
                            <th>Montant Versé</th>
                            <th>Monnaie Rendu</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (!empty($ventes)):?>
                            <?php
                                $id = 1; 
                                foreach ($ventes as $vente): 
                                    $collapseId = "collapseVente" . $id; // ID unique pour chaque ligne de vente
                            ?>
                            <tr>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo $id ?></td>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                                    <?php 
                                        $client = verifier_element('client', ['IDCLIENT'], [$vente['IDCLIENT']], '');
                                        echo htmlspecialchars($client['NomPrenomClient']);
                                    ?>
                                </td>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars($vente['NumeroVente']); ?></td>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['MontantTotal'], 0, ',', ' ')); ?> FCFA</td>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['MontantRemise'], 0, ',', ' ')); ?> FCFA</td>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['MontantVerse'], 0, ',', ' ')); ?> FCFA</td>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars(number_format($vente['Monnaie'], 0, ',', ' ')); ?> FCFA</td>
                                <td data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><?php echo htmlspecialchars($vente['DateIns']); ?></td>
                            </tr>
                            <tr>
                            <td colspan="9" class="p-0">
                                <div id="<?php echo $collapseId; ?>" class="collapse" data-bs-parent="#accordionExample">
                                    <div class="card card-body">
                                        <?php 
                                            // Récupérer les articles avec la requête corrigée pour éviter la duplication
                                            $sql_articles = "SELECT DISTINCT fa.IDARTICLE, a.libelle, a.PrixVenteTTC, fa.QuantiteVendue, ns.NUMERO_SERIE
                                                            FROM facture_article fa
                                                            JOIN article a ON fa.IDARTICLE = a.IDARTICLE
                                                            INNER JOIN num_serie ns 
                                                                ON ns.IDARTICLE = fa.IDARTICLE 
                                                                AND ns.NumeroVente = fa.NumeroVente 
                                                                AND ns.ID_VENTE = fa.IDFactureVente
                                                                AND ns.statut = 'vendue'
                                                            WHERE fa.NumeroVente = ?
                                                            ORDER BY fa.IDFactureVente, ns.NUMERO_SERIE";
                                            $stmt_articles = $cnx->prepare($sql_articles);
                                            $stmt_articles->execute([$vente['NumeroVente']]);
                                            $articles_details = $stmt_articles->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (empty($articles_details)) {
                                                // Fallback pour les ventes à crédit
                                                $factures = verifier_element_tous('ventes_credit_ligne', ['NumeroVente'], [$vente['NumeroVente']], '');
                                                if (is_array($factures) && !empty($factures)) {
                                                    echo '<table class="table">';
                                                    echo '<thead><tr><th>Article</th><th>Numéro de série</th><th>Prix</th><th>Quantité Vendue</th></tr></thead>';
                                                    echo '<tbody>';
                                                    foreach ($factures as $facture) {
                                                        $article = verifier_element('article', ['IDARTICLE'], [$facture['IDARTICLE']], '');
                                                        $num_serie = verifier_element('num_serie', ['IDARTICLE', 'NumeroVente'], [$article['IDARTICLE'], $vente['NumeroVente']], '');
                                                        echo '<tr>';
                                                        echo '<td>' . htmlspecialchars($article['libelle']) . '</td>';
                                                        echo '<td>' . htmlspecialchars($num_serie['NUMERO_SERIE']) . '</td>';
                                                        echo '<td>' . number_format($article['PrixVenteTTC'], 0, ',', ' ') . ' F CFA</td>';
                                                        echo '<td>' . htmlspecialchars($facture['QuantiteVendue']) . '</td>';
                                                        echo '</tr>';
                                                    }
                                                    echo '</tbody></table>';
                                                } else {
                                                    echo 'Aucune facture trouvée.';
                                                }
                                            } else {
                                                // Afficher les articles avec la requête corrigée
                                                echo '<table class="table">';
                                                echo '<thead><tr><th>Article</th><th>Numéro de série</th><th>Prix</th><th>Quantité Vendue</th></tr></thead>';
                                                echo '<tbody>';
                                                foreach ($articles_details as $article_detail) {
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($article_detail['libelle']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($article_detail['NUMERO_SERIE']) . '</td>';
                                                    echo '<td>' . number_format($article_detail['PrixVenteTTC'], 0, ',', ' ') . ' F CFA</td>';
                                                    echo '<td>' . htmlspecialchars($article_detail['QuantiteVendue']) . '</td>';
                                                    echo '</tr>';
                                                }
                                                echo '</tbody></table>';
                                            }
                                        ?>
                                    </div>
                                </div>
                            </td>

                            </tr>
                        <?php 
                            $id++;
                            endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">Aucune vente enregistrée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2><i class="fas fa-cogs me-2"></i>Actions</h2>
            <div class="text-center p-3">
                <?php echo bouton_action('Exporter vers Excel', 'vente_jour', 'voir', 'btn btn-success', 'href="export_ventes.php?format=excel&date=' . urlencode($date_selectionnee) . '"'); ?>
                <?php echo bouton_action('Exporter vers Word', 'vente_jour', 'voir', 'btn btn-primary', 'href="export_ventes.php?format=word&date=' . urlencode($date_selectionnee) . '"'); ?>
                <?php echo bouton_action('Exporter en Bloc-notes', 'vente_jour', 'voir', 'btn btn-secondary', 'href="export_ventes.php?format=txt&date=' . urlencode($date_selectionnee) . '"'); ?>
                <button class="btn btn-info" onclick="location.reload();">
                    <i class="fas fa-sync-alt me-1"></i> Mettre à Jour
                </button>
                <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-day me-1"></i> Retour à Aujourd'hui
                </a>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        setTimeout(function() {
            var errorAlert = document.getElementById('error-alert');
            var successAlert = document.getElementById('error-alert');
            if (errorAlert & successAlert) {
                errorAlert.style.display = 'none';
            }

            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                url.searchParams.delete('success');
                window.history.replaceState(null, null, url);
            }
        }, 2000);
        // Affichage de la date sélectionnée ou aujourd'hui
        const selectedDate = '<?= $date_selectionnee ?>';
        const date = new Date(selectedDate);
        const options = {
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        };
        const formatter = new Intl.DateTimeFormat('fr-FR', options);

        const formattedDate = formatter.format(date);

        document.getElementById('date').textContent = formattedDate;
        
        // Validation automatique si une date est sélectionnée
        document.getElementById('dateSelector').addEventListener('change', function() {
            if (this.value) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>