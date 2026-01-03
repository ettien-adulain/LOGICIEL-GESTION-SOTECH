<?php
try {
    include('db/connecting.php');
   
    require_once 'fonction_traitement/fonction.php';
    check_access();
    if (!can_user_page('liste_numeroserie', 'voir')) {
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Accès refusé</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: #f8d7da; display: flex; align-items: center; justify-content: center; height: 100vh; }
                .denied-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(220,38,38,0.15); padding: 2.5rem 3rem; text-align: center; border: 2px solid #dc3545; }
                .denied-box h1 { color: #dc3545; font-size: 2.5rem; margin-bottom: 1rem; }
                .denied-box p { color: #333; font-size: 1.15rem; }
                .btn-retour { margin-top: 2rem; font-size: 1.1rem; }
                .fa-ban { font-size: 2.5rem; color: #dc3545; margin-bottom: 1rem; }
            </style>
        </head>
        <body>
            <div class="denied-box">
                <div><i class="fas fa-ban"></i></div>
                <h1>Accès refusé</h1>
                <p>Vous n\'avez pas l\'autorisation d\'accéder à la page <strong>Numéro de Série</strong>.</p>
                <p><strong>Droit requis :</strong> Voir sur la page "Numéro de Série"</p>
                <a href="index.php" class="btn btn-danger btn-retour"><i class="fas fa-arrow-left"></i> Retour au menu</a>
            </div>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </body>
        </html>';
        exit();
    }
    $numSeries = selection_element('num_serie');
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . $tableName;
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit();
}

$currentPage = 'liste_numeroserie';
$canView = can_user_page($currentPage, 'voir');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventes Validées</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Styles personnalisés pour les badges de statut */
        .badge-disponible {
            background-color: #28a745 !important; /* Vert */
            color: white !important;
        }
        
        .badge-vendue {
            background-color: #fd7e14 !important; /* Orange */
            color: white !important;
        }
        
        .badge-vendue-credit {
            background-color: #ffc107 !important; /* Jaune */
            color: #212529 !important;
        }
        
        .badge-introuvable {
            background-color: #dc3545 !important; /* Rouge */
            color: white !important;
        }
        
        .badge-correction-stock {
            background-color: #6f42c1 !important; /* Violet */
            color: white !important;
        }
        
        .badge-statut-inconnu {
            background-color: #6c757d !important; /* Gris */
            color: white !important;
        }
        
        /* Amélioration des badges */
        .badge {
            font-size: 0.875em;
            font-weight: 600;
            padding: 0.5em 0.75em;
            border-radius: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            display: inline-flex;
            align-items: center;
            gap: 0.25em;
        }
        
        /* Icônes pour les badges */
        .badge i {
            font-size: 0.875em;
        }
    </style>
</head>

<body id="liste_numeroserie">
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    

    <header>
        <h1>Numéros de Série</h1>
    </header>
    <div class="container mt-5">
        <div class="m-3">
        </div>
        <h2 class="mb-4">Liste des Articles avec Numéros de Série et Date de Vente</h2>
        
        <!-- Légende des statuts -->
        <div class="alert alert-info mb-4">
            <h6 class="mb-3"><i class="fas fa-info-circle"></i> Légende des statuts :</h6>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <span class="badge badge-disponible">
                        <i class="fas fa-check-circle"></i>
                        Disponible
                    </span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="badge badge-vendue">
                        <i class="fas fa-shopping-cart"></i>
                        Vendue
                    </span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="badge badge-vendue-credit">
                        <i class="fas fa-credit-card"></i>
                        Vendue à Crédit
                    </span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="badge badge-introuvable">
                        <i class="fas fa-exclamation-triangle"></i>
                        Introuvable
                    </span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="badge badge-correction-stock">
                        <i class="fas fa-tools"></i>
                        Correction de Stock
                    </span>
                </div>
            </div>
        </div>
        <?php if (isset($_GET['success'])): ?>
            <div id="success-alert" class="alert alert-success" role="alert"><?= htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div id="error-alert" class="alert alert-danger" role="alert"><?= htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="mb-3">
            <label for="rowsPerPageSelect" class="form-label">Lignes par page:</label>
            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="15">15</option>
                <option value="20">20</option>
                <option value="all">Liste Complete</option>
            </select>
        </div>

        <div class="row mb-3">
            <div class="col-sm-6 mb-3">
                <label for="startDate" class="form-label">Date de début:</label>
                <input id="startDate" type="date" class="form-control">
            </div>
            <div class="col-sm-6 mb-3">
                <label for="endDate" class="form-label">Date de fin:</label>
                <input id="endDate" type="date" class="form-control">
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-sm-6 mb-3">
                <select name="typevendu" class="form-control" id="typevendu">
                    <option value="all">Tous les statuts</option>
                    <option value="disponible">Disponible</option>
                    <option value="vendue">Vendue</option>
                    <option value="vendue_credit">Vendue à crédit</option>
                    <option value="introuvable">Introuvable</option>
                    <option value="correction_stock">Correction de Stock</option>
                </select>
            </div>
        </div>

        <?php

        // Code supprimé car obsolète - maintenant on utilise le champ statut directement

        ?>
        <table id="myTable" class="table table-bordered table-striped mb-5">
            <thead>
                <tr>
                    <th>Nom de l'Article</th>
                    <th>Numéro de Série</th>
                    <th>Statut</th>
                    <th>Statut Technique</th>
                    <th>Date de Vente</th>
                </tr>
            </thead>
            <tbody id="table-body">
                <?php if (count($numSeries) > 0): ?>
                    <?php foreach ($numSeries as $row): ?>
                        <tr>
                            <?php
                            // Récupérer les informations de l'article
                            $idArticle = $row['IDARTICLE'];
                            $stmt = $cnx->prepare("SELECT libelle FROM article WHERE IDARTICLE = ?");
                            $stmt->execute([$idArticle]);
                            $article = $stmt->fetch(PDO::FETCH_ASSOC);
                            $libelle = $article ? htmlspecialchars($article['libelle']) : 'Article non trouvé';
                            ?>
                            <td><?= $libelle; ?></td>
                            <td><?= htmlspecialchars($row['NUMERO_SERIE']); ?></td>

                            <?php
                            // Utiliser le nouveau champ statut
                            $statut = $row['statut'] ?? 'disponible';
                            
                            // Vérifier si c'est suite à une correction de stock (pour les statuts introuvable)
                            $is_correction_stock = false;
                            if ($statut === 'introuvable' || $statut === 'INTROUVABLE') {
                                $stmt_check = $cnx->prepare("SELECT COUNT(*) as count FROM journal_num_serie WHERE NUMERO_SERIE = ? AND action = 'RETRAIT' AND nouveau_statut = 'INTROUVABLE'");
                                $stmt_check->execute([$row['NUMERO_SERIE']]);
                                $result_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                                $is_correction_stock = $result_check['count'] > 0;
                            }
                            
                            // Déterminer le libellé, l'icône et la classe CSS selon le statut
                            // PRIORITÉ: Vérifier d'abord si c'est une vente à crédit (IDvente_credit)
                            if (!empty($row['IDvente_credit'])) {
                                $libelle_vente = 'Vendue à Crédit';
                                $status_class = 'badge-vendue-credit';
                                $status_icon = 'fas fa-credit-card';
                                // Récupérer la date de vente à crédit
                                $stmt = $cnx->prepare("SELECT DateMod FROM ventes_credit WHERE IDVenteCredit = ?");
                                $stmt->execute([$row['IDvente_credit']]);
                                $vente_credit = $stmt->fetch(PDO::FETCH_ASSOC);
                                $date_vente = $vente_credit ? $vente_credit['DateMod'] : 'Date inconnue';
                            } else {
                                switch($statut) {
                                    case 'disponible':
                                        $libelle_vente = 'Disponible';
                                        $status_class = 'badge-disponible';
                                        $status_icon = 'fas fa-check-circle';
                                        $date_vente = 'En stock';
                                        break;
                                        
                                    case 'vendue':
                                        $libelle_vente = 'Vendue';
                                        $status_class = 'badge-vendue';
                                        $status_icon = 'fas fa-shopping-cart';
                                        // Récupérer la date de vente
                                        if ($row['ID_VENTE']) {
                                            $stmt = $cnx->prepare("SELECT DateIns FROM vente WHERE IDFactureVente = ?");
                                            $stmt->execute([$row['ID_VENTE']]);
                                            $vente = $stmt->fetch(PDO::FETCH_ASSOC);
                                            $date_vente = $vente ? $vente['DateIns'] : 'Date inconnue';
                                        } else {
                                            $date_vente = 'Date inconnue';
                                        }
                                        break;
                                    
                                    case 'introuvable':
                                    case 'INTROUVABLE':
                                        if ($is_correction_stock) {
                                            $libelle_vente = 'Correction de Stock';
                                            $status_class = 'badge-correction-stock';
                                            $status_icon = 'fas fa-tools';
                                        } else {
                                            $libelle_vente = 'Introuvable';
                                            $status_class = 'badge-introuvable';
                                            $status_icon = 'fas fa-exclamation-triangle';
                                        }
                                        $date_vente = $is_correction_stock ? 'Correction de stock' : 'Marqué introuvable';
                                        break;
                                        
                                    default:
                                        $libelle_vente = 'Statut inconnu';
                                        $status_class = 'badge-statut-inconnu';
                                        $status_icon = 'fas fa-question-circle';
                                        $date_vente = 'Non défini';
                                        break;
                                }
                            }
                            ?>

                            <td class="text-center">
                                <span class="badge <?= $status_class; ?>">
                                    <i class="<?= $status_icon; ?>"></i>
                                    <?= htmlspecialchars($libelle_vente); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php
                                // Déterminer le statut technique selon le statut et l'origine
                                $statut_technique = $statut;
                                
                                if (($statut === 'introuvable' || $statut === 'INTROUVABLE') && $is_correction_stock) {
                                    $statut_technique = 'CORRECTION DE STOCK';
                                }
                                ?>
                                <small class="text-muted"><?= htmlspecialchars($statut_technique); ?></small>
                            </td>
                            <td><?= htmlspecialchars($date_vente); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-row">Le tableau est vide</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-center" id="pagination">
                <li class="page-item disabled" id="prevButton">
                    <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                </li>
                <li class="page-item" id="nextButton">
                    <a class="page-link" href="#">Next</a>
                </li>
            </ul>
        </nav>

        <button id="printButton" class="btn btn-success mb-3">Imprimer</button>
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
        <script src="js/script.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const rowsPerPageSelect = document.getElementById("rowsPerPageSelect");
                const tableBody = document.getElementById("table-body");
                const paginationContainer = document.getElementById("pagination");

                let currentPage = 1;
                let rowsPerPage = parseInt(rowsPerPageSelect.value) || rows.length; // Par défaut, toutes les lignes si 'all' est choisi
                let rows = tableBody.getElementsByTagName("tr");

                // Fonction d'affichage des lignes du tableau
                function displayTableRows() {
                    if (rowsPerPage === "all") {
                        // Afficher toutes les lignes
                        for (let i = 0; i < rows.length; i++) {
                            rows[i].style.display = ""; // Tout afficher
                        }
                        paginationContainer.style.display = "none"; // Masquer la pagination
                    } else {
                        // Pagination normale
                        paginationContainer.style.display = ""; // Afficher la pagination
                        const start = (currentPage - 1) * rowsPerPage;
                        const end = start + rowsPerPage;

                        for (let i = 0; i < rows.length; i++) {
                            rows[i].style.display = (i >= start && i < end) ? "" : "none";
                        }

                        updatePagination();
                    }
                }

                // Fonction de mise à jour de la pagination
                function updatePagination() {
                    const visibleRows = document.querySelectorAll('#table-body tr:not([style*="display: none"])');
                    const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
                    
                    // Mettre à jour l'affichage des pages
                    paginationContainer.innerHTML = `
                        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}" id="prevButton">
                            <a class="page-link" href="#" tabindex="-1" aria-disabled="${currentPage === 1}">Previous</a>
                        </li>
                    `;

                    for (let i = 1; i <= totalPages; i++) {
                        paginationContainer.innerHTML += `
                            <li class="page-item ${currentPage === i ? 'active' : ''}">
                                <a class="page-link" href="#">${i}</a>
                            </li>
                        `;
                    }

                    paginationContainer.innerHTML += `
                        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}" id="nextButton">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    `;

                    // Réattacher les événements de pagination
                    attachPaginationEvents();
                }

                // Fonction pour attacher les événements de pagination
                function attachPaginationEvents() {
                    document.getElementById("prevButton").querySelector(".page-link").addEventListener("click", function(event) {
                        event.preventDefault();
                        if (currentPage > 1) {
                            currentPage--;
                            displayTableRows();
                        }
                    });

                    const pageLinks = paginationContainer.querySelectorAll(".page-item:not(#prevButton):not(#nextButton)");
                    pageLinks.forEach((pageItem, index) => {
                        pageItem.addEventListener("click", function(event) {
                            event.preventDefault();
                            currentPage = index + 1;
                            displayTableRows();
                        });
                    });

                    document.getElementById("nextButton").querySelector(".page-link").addEventListener("click", function(event) {
                        event.preventDefault();
                        if (currentPage < totalPages) {
                            currentPage++;
                            displayTableRows();
                        }
                    });
                }

                // Gestion du changement du nombre de lignes par page
                rowsPerPageSelect.addEventListener("change", function() {
                    rowsPerPage = this.value === "all" ? "all" : parseInt(this.value);
                    currentPage = 1;
                    displayTableRows();
                });

                // Initialiser l'affichage du tableau
                displayTableRows();
            });

            document.getElementById('startDate').addEventListener('change', filterTable);
            document.getElementById('endDate').addEventListener('change', filterTable);
            document.getElementById('typevendu').addEventListener('change', filterTable);

            function filterTable() {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                const selectedValue = document.getElementById('typevendu').value;
                const rows = document.querySelectorAll('#table-body tr');

                rows.forEach(row => {
                    const dateCell = row.querySelector('td:last-child').textContent; // 5ème colonne maintenant
                    const statusCell = row.querySelector('td:nth-child(3)').textContent.trim(); // 3ème colonne (statut)
                    
                    // Gestion des dates
                    let dateMatch = true;
                    if (startDate || endDate) {
                        if (dateCell !== 'En stock' && dateCell !== 'Marqué introuvable' && dateCell !== 'Date inconnue' && dateCell !== 'Non défini') {
                            const datePart = dateCell.split(' ')[0];
                            const date = new Date(datePart);
                            const start = startDate ? new Date(startDate) : null;
                            const end = endDate ? new Date(endDate) : null;
                            
                            dateMatch = (!start || date >= start) && (!end || date <= end);
                        } else {
                            dateMatch = false;
                        }
                    }

                    // Gestion du statut
                    let statusMatch = true;
                    if (selectedValue !== 'all') {
                        const statutTechniqueCell = row.querySelector('td:nth-child(4)').textContent.trim(); // 4ème colonne (statut technique)
                        
                        switch(selectedValue) {
                            case 'disponible':
                                statusMatch = statusCell === 'Disponible';
                                break;
                            case 'vendue':
                                statusMatch = statusCell === 'Vendue';
                                break;
                            case 'vendue_credit':
                                statusMatch = statusCell === 'Vendue à Crédit';
                                break;
                            case 'introuvable':
                                statusMatch = statusCell === 'Introuvable';
                                break;
                            case 'correction_stock':
                                statusMatch = statutTechniqueCell === 'CORRECTION DE STOCK';
                                break;
                        }
                    }

                    // Afficher ou masquer la ligne selon les critères
                    row.style.display = (dateMatch && statusMatch) ? '' : 'none';
                });

                // Mettre à jour la pagination
                updatePagination();
            }

            document.getElementById('printButton').addEventListener('click', function() {
                // Détection mobile
                const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                
                if (isMobile) {
                    // Méthode mobile : impression directe
                    printMobile();
                } else {
                    // Méthode PC : fenêtre popup
                    printDesktop();
                }
            });

            function printMobile() {
                // Masquer tous les éléments sauf le tableau
                const originalStyles = {};
                const elementsToHide = [
                    '.user-info', '.navigation-buttons', '.btn', 'button', 
                    '.form-group', '.alert', 'h1', 'h2', '.pagination',
                    '.mb-3', '.row', '.col-sm-6', '.form-label', '.form-control', '.form-select',
                    'nav', '.navbar', '.nav', '.nav-link', '.navbar-nav', '.navbar-brand',
                    '.fas', '.fa', 'i[class*="fa"]', 'i[class*="fas"]', 'i[class*="far"]',
                    '.icon', '.glyphicon', '[class*="icon-"]', '[class*="fa-"]'
                ];
                
                // Sauvegarder les styles originaux et masquer les éléments
                elementsToHide.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(el => {
                        originalStyles[el] = el.style.display;
                        el.style.display = 'none';
                    });
                });
                
                // Masquer les éléments spécifiques
                const specificElements = [
                    document.querySelector('header'),
                    document.querySelector('.alert-info'),
                    document.querySelector('.mb-3'),
                    document.querySelector('.row'),
                    document.querySelector('.pagination'),
                    document.querySelector('#printButton'),
                    document.querySelector('.user-info'),
                    document.querySelector('.navigation-buttons'),
                    document.querySelector('nav'),
                    document.querySelector('.navbar')
                ];
                
                specificElements.forEach(el => {
                    if (el) {
                        originalStyles[el] = el.style.display;
                        el.style.display = 'none';
                    }
                });
                
                // Afficher seulement le tableau
                const table = document.getElementById('myTable');
                if (table) {
                    table.style.display = 'table';
                    table.style.width = '100%';
                    table.style.margin = '0';
                }
                
                // Ajouter un titre pour l'impression
                const printTitle = document.createElement('h1');
                printTitle.textContent = 'Numéros de Série';
                printTitle.style.textAlign = 'center';
                printTitle.style.marginBottom = '20px';
                printTitle.style.fontSize = '18px';
                printTitle.style.fontWeight = 'bold';
                
                const printDate = document.createElement('p');
                printDate.textContent = 'Date: ' + new Date().toLocaleDateString();
                printDate.style.textAlign = 'center';
                printDate.style.marginBottom = '20px';
                printDate.style.fontSize = '14px';
                
                // Insérer le titre et la date avant le tableau
                table.parentNode.insertBefore(printDate, table);
                table.parentNode.insertBefore(printTitle, table);
                
                // Styles d'impression
                const printStyles = document.createElement('style');
                printStyles.textContent = `
                    @media print {
                        body { margin: 0; padding: 10px; font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
                        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        .badge { display: none !important; }
                        .text-muted { color: #000 !important; }
                        h1 { font-size: 18px; margin-bottom: 10px; }
                        p { font-size: 14px; margin-bottom: 10px; }
                        
                        /* Masquer tous les éléments de navigation et icônes */
                        .user-info, .navigation-buttons, nav, .navbar, .nav, .nav-link, 
                        .navbar-nav, .navbar-brand, .fas, .fa, i[class*="fa"], 
                        i[class*="fas"], i[class*="far"], .icon, .glyphicon, 
                        [class*="icon-"], [class*="fa-"], .btn, button, .form-group, 
                        .alert, h1, h2, .pagination, .mb-3, .row, .col-sm-6, 
                        .form-label, .form-control, .form-select, header, 
                        .alert-info, #printButton { 
                            display: none !important; 
                            visibility: hidden !important; 
                        }
                        
                        @page { margin: 1cm; }
                    }
                `;
                document.head.appendChild(printStyles);
                
                // Déclencher l'impression
                setTimeout(() => {
                    window.print();
                    
                    // Restaurer les styles après impression
                    setTimeout(() => {
                        // Restaurer les styles originaux
                        Object.keys(originalStyles).forEach(el => {
                            if (originalStyles[el] !== undefined) {
                                el.style.display = originalStyles[el];
                            }
                        });
                        
                        // Supprimer le titre et la date ajoutés
                        if (printTitle.parentNode) printTitle.remove();
                        if (printDate.parentNode) printDate.remove();
                        
                        // Supprimer les styles d'impression
                        if (printStyles.parentNode) printStyles.remove();
                        
                        // Forcer le retour à la page normale sans rechargement
                        // Restaurer tous les éléments masqués
                        const allHiddenElements = document.querySelectorAll('*');
                        allHiddenElements.forEach(el => {
                            if (el.style.display === 'none') {
                                el.style.display = '';
                            }
                        });
                        
                        // S'assurer que le tableau est visible
                        const table = document.getElementById('myTable');
                        if (table) {
                            table.style.display = 'table';
                        }
                        
                        // Restaurer la pagination
                        const pagination = document.querySelector('.pagination');
                        if (pagination) {
                            pagination.style.display = '';
                        }
                        
                        // Restaurer les boutons
                        const buttons = document.querySelectorAll('button, .btn');
                        buttons.forEach(btn => {
                            btn.style.display = '';
                        });
                        
                        // Restaurer les formulaires
                        const forms = document.querySelectorAll('.form-group, .form-control, .form-select');
                        forms.forEach(form => {
                            form.style.display = '';
                        });
                        
                        // Restaurer les alertes
                        const alerts = document.querySelectorAll('.alert');
                        alerts.forEach(alert => {
                            alert.style.display = '';
                        });
                        
                        // Restaurer les éléments de navigation
                        const navElements = document.querySelectorAll('.user-info, .navigation-buttons, nav, .navbar');
                        navElements.forEach(nav => {
                            nav.style.display = '';
                        });
                        
                        // Restaurer le header
                        const header = document.querySelector('header');
                        if (header) {
                            header.style.display = '';
                        }
                        
                        // Restaurer les titres
                        const titles = document.querySelectorAll('h1, h2');
                        titles.forEach(title => {
                            title.style.display = '';
                        });
                        
                        // Restaurer les lignes et colonnes
                        const rows = document.querySelectorAll('.row, .col-sm-6, .mb-3');
                        rows.forEach(row => {
                            row.style.display = '';
                        });
                    }, 1000);
                }, 100);
            }

            function printDesktop() {
                // Clonage du tableau pour l'impression
                const table = document.getElementById('myTable').cloneNode(true);
                const date = new Date().toLocaleDateString();

                // Ouverture d'une nouvelle fenêtre pour l'impression
                const printWindow = window.open('', '_blank');
                
                if (!printWindow || printWindow.closed) {
                    // Fallback si popup bloqué
                    alert('Pop-up bloqué. Utilisation de l\'impression directe...');
                    printMobile();
                    return;
                }
                
                printWindow.document.write('<html><head><title>Impression du Tableau</title>');
                printWindow.document.write('<style>');
                printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
                printWindow.document.write('th, td { border: 1px solid black; padding: 8px; text-align: left; }');
                printWindow.document.write('th { background-color: #f2f2f2; }');
                printWindow.document.write('h1, p { text-align: center; }');
                printWindow.document.write('</style></head><body>');

                // Contenu du document
                printWindow.document.write('<h1>Numéros de Série</h1>');
                printWindow.document.write('<p>Date: ' + date + '</p>');
                printWindow.document.write('<table>');
                printWindow.document.write(table.outerHTML);
                printWindow.document.write('</table>');

                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();

                // Impression
                printWindow.print();
                printWindow.close();
            }
        </script>
    </div>
</body>

</html>