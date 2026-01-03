<?php
try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    check_access();
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}

// Requête optimisée pour récupérer les entrées en stock avec les informations du fournisseur
$sql = "SELECT e.*, f.NomFournisseur 
        FROM entree_en_stock e 
        LEFT JOIN fournisseur f ON e.IDFOURNISSEUR = f.IDFOURNISSEUR 
        ORDER BY e.Date_arrivee DESC";
$stmt = $cnx->prepare($sql);
$stmt->execute();
$entrer_stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fournisseurs = selection_element('fournisseur');
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Entrées en Stock</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <style>
        :root {
            --primary-color: #ff0000;
            --secondary-color: #000000;
            --background-color: #ffffff;
            --text-color: #000000;
            --border-radius: 8px;
            --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .navbar {
            background-color: var(--primary-color);
            box-shadow: var(--box-shadow);
        }

        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
            padding: 15px 20px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
        }

        .btn-primary:hover {
            background-color: #cc0000;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }

        .table td {
            vertical-align: middle;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-en-cours {
            background-color: #ffeeba;
            color: #856404;
        }

        .status-termine {
            background-color: #d4edda;
            color: #155724;
        }

        .filter-section {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-footer {
            border-top: 2px solid var(--background-color);
        }

        .detail-row {
            padding: 10px;
            border-bottom: 1px solid var(--background-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                box-shadow: none !important;
            }
            .table th {
                background-color: #f8f9fa !important;
                color: black !important;
            }
        }

        .search-highlight {
            background-color: #fff3cd;
        }

        /* Styles optimisés pour le modal de détails */
        .modal-xl {
            max-width: 95%;
        }

        .modal-body {
            padding: 20px;
        }

        .details-container {
            max-height: 70vh;
            overflow-y: auto;
        }

        .details-section {
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }

        .details-section-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #495057;
        }

        .details-section-content {
            padding: 20px;
        }

        .numeros-serie-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .numeros-serie-table {
            margin-bottom: 0;
        }

        .numeros-serie-table th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
            border-bottom: 2px solid #dee2e6;
        }

        .numeros-serie-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .numeros-serie-table tbody tr:hover {
            background-color: #e9ecef;
        }

        .numeros-count {
            background-color: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        /* Masquer l'en-tête par défaut */
        .company-header {
            display: none;
        }
        .print-footer {
            display: none;
        }

        /* Styles pour l'impression */
        @media print {
            /* Cacher les éléments non nécessaires */
            .no-print, 
            .btn-group,
            .navbar,
            .filter-section,
            .card-header button {
                display: none !important;
            }

            /* Afficher l'en-tête uniquement à l'impression */
            .company-header {
                display: block !important;
                text-align: center !important;
                margin-bottom: 30px !important;
                page-break-after: avoid !important;
            }

            /* Style de la page */
            body {
                padding: 20px;
                background: white;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .card-header {
                background: white !important;
                border-bottom: 2px solid #000 !important;
                padding: 20px 0 !important;
            }

            .card-header h5 {
                font-size: 24px !important;
                color: black !important;
                text-align: center !important;
                width: 100% !important;
            }

            /* Style du tableau */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            th {
                background-color: #f8f9fa !important;
                color: black !important;
                border: 1px solid #000 !important;
                padding: 10px !important;
                font-weight: bold !important;
            }

            td {
                border: 1px solid #000 !important;
                padding: 8px !important;
            }

            /* En-tête de l'entreprise */
            .company-header img {
                max-width: 150px !important;
                height: auto !important;
                margin-bottom: 10px !important;
            }

            .company-header h2 {
                font-size: 20px !important;
                margin: 10px 0 !important;
            }

            .company-header p {
                margin: 5px 0 !important;
                font-size: 14px !important;
            }

            /* Pied de page */
            .print-footer {
                display: block !important;
                text-align: center !important;
                margin-top: 30px !important;
                padding-top: 20px !important;
                border-top: 1px solid #000 !important;
                font-size: 12px !important;
            }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
    <!-- En-tête de l'entreprise (visible uniquement à l'impression) -->
    <div class="company-header no-print">
        <?php
        if (isset($_SESSION['entreprise'])) {
            $entreprise = $_SESSION['entreprise'];
        } else {
            $result = $cnx->query("SELECT * FROM entreprise WHERE id = 1");
            $entreprise = $result->fetch();
            $_SESSION['entreprise'] = $entreprise;
        }
        ?>
        <img src="<?= isset($entreprise['logo1']) && !empty($entreprise['logo1']) ? $entreprise['logo1'] : 'Image_article/sotech.png' ?>" alt="Logo de l'entreprise">
        <h2><?= isset($entreprise['nom']) ? htmlspecialchars($entreprise['nom']) : '' ?></h2>
        <p><?= isset($entreprise['adresse']) ? htmlspecialchars($entreprise['adresse']) : '' ?></p>
        <p>Tél: <?= isset($entreprise['telephone']) ? htmlspecialchars($entreprise['telephone']) : '' ?></p>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-boxes mr-2"></i>
                Gestion des Entrées en Stock
            </a>
            <div class="navbar-nav ml-auto">
                <a href="index.php" class="btn btn-outline-light mr-2">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="menu_entree_stock.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Messages d'alerte -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                    </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filter-section no-print">
            <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                        <label for="dateDebut">Date de début</label>
                        <input type="date" class="form-control" id="dateDebut">
                    </div>
                </div>
                    <div class="col-md-3">
                        <div class="form-group">
                        <label for="dateFin">Date de fin</label>
                        <input type="date" class="form-control" id="dateFin">
                    </div>
                </div>
                    <div class="col-md-3">
                        <div class="form-group">
                        <label for="fournisseur">Fournisseur</label>
                        <select class="form-control" id="fournisseur">
                            <option value="">Tous les fournisseurs</option>
                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                <option value="<?= htmlspecialchars($fournisseur['NomFournisseur']) ?>">
                                    <?= htmlspecialchars($fournisseur['NomFournisseur']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="numeroBon">Numéro de bon</label>
                        <input type="text" class="form-control" id="numeroBon" placeholder="Rechercher par numéro de bon">
                    </div>
                </div>
                    </div>
                </div>

        <!-- Liste des entrées en stock -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Liste des entrées en stock</h5>
                <div class="no-print">
                    <?php if (can_user('liste_entree_stock', 'supprimer_tous')): ?>
                    <button class="btn btn-danger mr-2" onclick="supprimerToutesEntrees();">
                        <i class="fas fa-trash-alt"></i> Supprimer toutes les entrées
                    </button>
                    <?php else: ?>
                    <button class="btn btn-danger mr-2" disabled title="Accès refusé">
                        <i class="fas fa-trash-alt"></i> Supprimer toutes les entrées
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="entreesTable">
                    <thead>
                            <tr>
                                <th>N° Entrée</th>
                                <th>N° Bon</th>
                                <th>Date</th>
                                <th>Fournisseur</th>
                                <th>Frais annexes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entrer_stocks as $entree): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entree['IDENTREE_STOCK']) ?></td>
                                    <td><?= htmlspecialchars($entree['Numero_bon']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($entree['Date_arrivee'])) ?></td>
                                    <td><?= htmlspecialchars($entree['NomFournisseur'] ?? 'Non spécifié') ?></td>
                                    <td><?= number_format($entree['frais_annexes'] ?? 0, 0, ',', ' ') ?> FCFA</td>
                                    <td>
                                        <?php if (can_user('liste_entree_stock', 'voir')): ?>
                                        <button class="btn btn-sm btn-info" onclick="voirDetails(<?= $entree['IDENTREE_STOCK'] ?>);">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-info" disabled title="Accès refusé">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($entree['statut'] !== 'TERMINE'): ?>
                                            <a href="entrer_numero.php?id=<?= $entree['IDENTREE_STOCK'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-danger" onclick="imprimerSansNouvelOnglet('print_entree_stock.php?id=<?= $entree['IDENTREE_STOCK'] ?>')">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        
                                        <?php if (can_user('liste_entree_stock', 'supprimer')): ?>
                                        <button class="btn btn-sm btn-danger" onclick="supprimerEntree(<?= $entree['IDENTREE_STOCK'] ?>, '<?= htmlspecialchars($entree['Numero_bon']) ?>');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-danger" disabled title="Accès refusé">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
            </div>
        </div>
    </div>

    <!-- Modal pour les détails - Optimisé pour de nombreux numéros de série -->
    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-boxes mr-2"></i>
                        Détails de l'entrée en stock
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="detailsContent" style="max-height: 80vh; overflow-y: auto;">
                    <!-- Le contenu sera chargé dynamiquement -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement des détails...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="imprimerDetails()">
                        <i class="fas fa-print mr-1"></i> Imprimer
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pied de page (visible uniquement à l'impression) -->
    <div class="print-footer no-print">
        <p>Document généré le <?= date('d/m/Y à H:i') ?></p>
        <p><?= isset($entreprise['nom']) ? htmlspecialchars($entreprise['nom']) : '' ?> - Tous droits réservés</p>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de DataTables avec recherche personnalisée
            var table = $('#entreesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                },
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true,
                search: {
                    smart: false // Désactive la recherche intelligente pour permettre la recherche lettre par lettre
                }
            });

            // Filtres personnalisés
            $('#dateDebut, #dateFin, #fournisseur, #numeroBon').on('keyup change', function() {
                table.draw();
            });

            // Personnalisation des filtres
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    var dateDebut = $('#dateDebut').val();
                    var dateFin = $('#dateFin').val();
                    var fournisseur = $('#fournisseur').val();
                    var numeroBon = $('#numeroBon').val().toLowerCase();

                    var rowDate = new Date(data[2].split('/').reverse().join('-'));
                    var rowFournisseur = data[3];
                    var rowNumeroBon = data[1].toLowerCase();

                    if (dateDebut && rowDate < new Date(dateDebut)) return false;
                    if (dateFin && rowDate > new Date(dateFin)) return false;
                    if (fournisseur && rowFournisseur !== fournisseur) return false;
                    if (numeroBon && !rowNumeroBon.includes(numeroBon)) return false;

                    return true;
                }
            );

            // Mise en évidence des résultats de recherche
            table.on('search.dt', function() {
                var searchTerm = table.search();
                if (searchTerm) {
                    $('.dataTables_filter input').addClass('search-highlight');
                    } else {
                    $('.dataTables_filter input').removeClass('search-highlight');
                    }
            });
        });

        function voirDetails(id) {
            // Afficher le modal avec le spinner de chargement
            $('#detailsModal').modal('show');
            
            // Afficher l'indicateur de chargement
            $('#detailsContent').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des détails...</p>
                </div>
            `);
            
            // Charger les détails avec gestion d'erreur
            $.get('fonction_traitement/get_entree_details.php', {id: id})
                .done(function(data) {
                    $('#detailsContent').html(data);
                    
                    // Initialiser DataTables pour les numéros de série s'ils existent
                    if ($('#numerosSerieTable').length) {
                        $('#numerosSerieTable').DataTable({
                            language: {
                                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                            },
                            pageLength: 50,
                            order: [[0, 'asc']],
                            responsive: true,
                            scrollY: '300px',
                            scrollCollapse: true,
                            paging: true,
                            searching: true,
                            info: true,
                            columnDefs: [
                                { targets: [0, 1], orderable: true }
                            ]
                        });
                    }
                })
                .fail(function(xhr, status, error) {
                    $('#detailsContent').html(`
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-triangle"></i> Erreur de chargement</h5>
                            <p>Impossible de charger les détails de l'entrée en stock.</p>
                            <p><strong>Erreur :</strong> ${error}</p>
                            <button class="btn btn-primary" onclick="voirDetails(${id})">
                                <i class="fas fa-redo"></i> Réessayer
                            </button>
                        </div>
                    `);
                });
        }

        function printList() {
            window.print();
        }

        function imprimerDetails() {
            // Créer une nouvelle fenêtre pour l'impression
            const printWindow = window.open('', '_blank');
            const modalContent = document.getElementById('detailsContent').innerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Détails de l'entrée en stock</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .details-section { margin-bottom: 30px; border: 1px solid #ccc; border-radius: 8px; }
                        .details-section-header { background-color: #f8f9fa; padding: 15px; font-weight: bold; }
                        .details-section-content { padding: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; font-weight: bold; }
                        .numeros-serie-container { max-height: none; }
                        .btn { display: none; }
                    </style>
                </head>
                <body>
                    <h2>Détails de l'entrée en stock</h2>
                    ${modalContent}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }

        // Pour gérer la sélection d'article
        document.querySelectorAll('.select-article').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const libelle = this.dataset.libelle;
                const stock = this.dataset.stock;
                
                // Remplir le formulaire de correction
                document.getElementById('produit').value = libelle;
                document.getElementById('stock').value = stock;
                document.getElementById('id_article').value = id;
                
                // Activer le formulaire de correction
                document.getElementById('correction-form').style.display = 'block';
            });
        });

        
//PERMET DIMPRIMER SANS REDIRECTION
function imprimerSansNouvelOnglet(url) {
    window.location.href = url;
}

        // Fonction pour supprimer une entrée en stock
        function supprimerEntree(id, numeroBon) {
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'entrée en stock N° ' + numeroBon + ' ?\n\nCette action supprimera définitivement l\'entrée et tous les numéros de série associés.')) {
                // Afficher un indicateur de chargement
                const button = event.target.closest('button');
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;

                // Envoyer la requête de suppression
                fetch('fonction_traitement/supprimer_entree_stock.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Supprimer la ligne du tableau
                        const row = button.closest('tr');
                        row.remove();
                        
                        // Afficher un message de succès
                        alert('Entrée en stock supprimée avec succès !');
                        
                        // Recharger la page pour mettre à jour les données
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression : ' + (data.message || 'Erreur inconnue'));
                        // Restaurer le bouton
                        button.innerHTML = originalContent;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la suppression : ' + error.message);
                    // Restaurer le bouton
                    button.innerHTML = originalContent;
                    button.disabled = false;
                });
            }
        }

        // Fonction pour supprimer toutes les entrées en stock
        function supprimerToutesEntrees() {
            if (confirm('ATTENTION ! Êtes-vous absolument sûr de vouloir supprimer TOUTES les entrées en stock ?\n\nCette action supprimera définitivement :\n- Toutes les entrées en stock\n- Tous les numéros de série associés\n- Tous les stocks seront remis à zéro\n\nCette action est IRREVERSIBLE !')) {
                if (confirm('DERNIÈRE CONFIRMATION : Voulez-vous vraiment supprimer toutes les entrées en stock ?\n\nTapez "OUI" pour confirmer :')) {
                    const confirmation = prompt('Tapez "OUI" pour confirmer la suppression de toutes les entrées :');
                    if (confirmation === 'OUI') {
                        // Afficher un indicateur de chargement
                        const button = event.target;
                        const originalContent = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression en cours...';
                        button.disabled = true;

                        // Envoyer la requête de suppression
                        fetch('fonction_traitement/supprimer_toutes_entrees.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Toutes les entrées en stock ont été supprimées avec succès !\n\n' + 
                                      'Entrées supprimées : ' + data.entreesSupprimees + '\n' +
                                      'Numéros de série supprimés : ' + data.numerosSupprimes);
                                
                                // Recharger la page
                                location.reload();
                            } else {
                                alert('Erreur lors de la suppression : ' + (data.message || 'Erreur inconnue'));
                                // Restaurer le bouton
                                button.innerHTML = originalContent;
                                button.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors de la suppression : ' + error.message);
                            // Restaurer le bouton
                            button.innerHTML = originalContent;
                            button.disabled = false;
                        });
                    }
                }
            }
        }


        
//PERMET DIMPRIMER SANS REDIRECTION

function imprimerSansNouvelOnglet(url) {
    window.location.href = url;
}
    </script>
   
</body>

</html>