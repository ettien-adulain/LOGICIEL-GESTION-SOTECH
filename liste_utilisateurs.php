<?php
try {
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    check_access();
    $utilisateurs = selection_element('utilisateur');
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . (isset(
        $tableName) ? $tableName : 'utilisateurs');
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body#liste_utilisateur {
            background: #f8f9fa;
        }
        .table-container {
            overflow-x: auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 1rem;
        }
        table.table {
            min-width: 900px;
        }
        thead th {
            position: sticky;
            top: 0;
            background: #f1f3f4;
            z-index: 2;
        }
        .badge.bg-success {
            background: linear-gradient(90deg, #28a745 60%, #218838 100%);
        }
        .badge.bg-danger {
            background: linear-gradient(90deg, #dc3545 60%, #b21f2d 100%);
        }
        .action-btns .btn {
            margin-right: 0.3rem;
        }
        .search-bar {
            max-width: 350px;
            margin-bottom: 1.2rem;
        }
        .search-input {
            border-radius: 30px;
            padding-left: 2.2rem;
        }
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 1.1rem;
        }
        @media (max-width: 600px) {
            .table-container {
                padding: 0.2rem;
            }
            table.table {
                font-size: 0.95rem;
            }
            .search-bar {
                max-width: 100%;
            }
        }
    </style>
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body id="liste_utilisateur">
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
<header class="mb-4">
    <h1 class="text-center mt-3 mb-2"><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>
</header>
<main class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-11 col-xl-10">
            <?php
                if (isset($_GET['success'])) {
                    $successMessage = htmlspecialchars($_GET['success']);
                    echo '<div id="success-alert" class="alert alert-success text-center" role="alert">' . $successMessage . '</div>';
                }
                if (isset($_GET['error'])) {
                    $errorMessage = htmlspecialchars($_GET['error']);
                    echo '<div id="error-alert" class="alert alert-danger text-center" role="alert">' . $errorMessage . '</div>';
                }
            ?>
            <section id="user-list">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                    <h2 class="mb-0"><i class="fas fa-list"></i> Liste des Utilisateurs</h2>
                    <form class="search-bar position-relative w-100 w-md-auto" onsubmit="return false;">
                        <span class="search-icon"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control search-input ps-5" placeholder="Rechercher un utilisateur...">
                    </form>
                    <a href="creer_compte_utilisateur.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Nouvel Utilisateur</a>
                </div>
                <div class="table-container mb-3">
                    <table class="table table-striped align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nom Complet</th>
                                <th>Identifiant</th>
                                <th>Fonction</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <?php if (count($utilisateurs) > 0) {
                                $id = 1;
                                foreach ($utilisateurs as $utilisateur){?>
                                <tr>
                                    <td><?= htmlspecialchars($id); ?></td>
                                    <td><?= htmlspecialchars($utilisateur['NomPrenom']); ?></td>
                                    <td><?= htmlspecialchars($utilisateur['Identifiant']); ?></td>
                                    <td><?= htmlspecialchars($utilisateur['fonction']); ?></td>
                                    <td>
                                        <?php if ($utilisateur['actif'] === 'oui'): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-btns">
                                        <form method="post" action="fonction_traitement/request.php" style="display:inline;">
                                            <input type="hidden" name="idutilisateur" value="<?= htmlspecialchars($utilisateur['IDUTILISATEUR']); ?>">
                                            <?php if ($utilisateur['actif'] === 'oui'): ?>
                                                <button type="submit" name="desactiver_utilisateur" class="btn btn-warning btn-sm" title="Désactiver">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="activer_utilisateur" class="btn btn-success btn-sm" title="Activer">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <a href="modifier_parametre_utilisateur.php?id=<?= htmlspecialchars($utilisateur['IDUTILISATEUR']); ?>" class="btn btn-secondary btn-sm" title="Modifier">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="post" action="fonction_traitement/request.php" style="display:inline;">
                                            <input type="hidden" name="idutilisateur" value="<?= htmlspecialchars($utilisateur['IDUTILISATEUR']); ?>">
                                            <button type="submit" name="supprimer_utilisateur" class="btn btn-danger btn-sm" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php 
                                $id++;
                                }
                            } else { ?>
                                <tr>
                                    <td colspan="6">Le tableau est vide</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination (front, à intégrer côté backend si besoin) -->
                <nav>
                  <ul class="pagination justify-content-center" id="pagination"></ul>
                </nav>
            </section>
        </div>
    </div>
</main>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pagination côté interface (front, pour 15 utilisateurs/page)
const rowsPerPage = 15;
const tableBody = document.getElementById('userTableBody');
const rows = tableBody ? Array.from(tableBody.getElementsByTagName('tr')) : [];
const pagination = document.getElementById('pagination');

function showPage(page) {
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    rows.forEach((row, i) => {
        row.style.display = (i >= start && i < end) ? '' : 'none';
    });
}

function setupPagination() {
    if (!pagination) return;
    pagination.innerHTML = '';
    const pageCount = Math.ceil(rows.length / rowsPerPage);
    for (let i = 1; i <= pageCount; i++) {
        const li = document.createElement('li');
        li.className = 'page-item';
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = i;
        a.onclick = function(e) {
            e.preventDefault();
            showPage(i);
            document.querySelectorAll('#pagination .page-item').forEach(el => el.classList.remove('active'));
            li.classList.add('active');
        };
        li.appendChild(a);
        pagination.appendChild(li);
    }
    if (pagination.firstChild) pagination.firstChild.classList.add('active');
}

if (rows.length > 0) {
    showPage(1);
    setupPagination();
}

// Recherche instantanée
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        let visibleRows = 0;
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.indexOf(filter) > -1) {
                row.style.display = '';
                visibleRows++;
            } else {
                row.style.display = 'none';
            }
        });
        // Réinitialise la pagination sur la recherche
        if (filter === '') {
            showPage(1);
            setupPagination();
        } else {
            pagination.innerHTML = '';
        }
    });
}

// Alertes auto-disparition
setTimeout(function() {
    var errorAlert = document.getElementById('error-alert');
    if (errorAlert) errorAlert.style.display = 'none';
    var successAlert = document.getElementById('success-alert');
    if (successAlert) successAlert.style.display = 'none';
}, 2500);
</script>
</body>
</html>