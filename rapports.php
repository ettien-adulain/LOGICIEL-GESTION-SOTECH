<?php
require_once 'fonction_traitement/fonction.php';
check_access();

session_start();
include('db/connecting.php');
if (!isset($_SESSION['nom_utilisateur'])) {
    header('location: connexion.php');
    exit();
}
include('fonction_traitement/fonction.php');

// Vérification de l'authentification
if (!isset($_SESSION['id_utilisateur'])) {
    header('Location: login.php');
    exit;
}

// Vérification des droits d'accès
$stmt = $cnx->prepare("SELECT * FROM utilisateur WHERE IDUTILISATEUR = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur || $utilisateur['fonction'] !== 'DIRECTEUR') {
    header('Location: acces_refuse.php');
    exit;
}

// Récupération des données de l'entreprise
$stmt = $cnx->prepare("SELECT * FROM entreprise WHERE id = 1");
$stmt->execute();
$entreprise = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - SOTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Système de thème sombre/clair -->
</head>
<body>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Rapports</h1>
                </div>

                <!-- Onglets -->
                <ul class="nav nav-tabs mb-4" id="rapportsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="fournisseurs-tab" data-bs-toggle="tab" href="#fournisseurs" role="tab">Fournisseurs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="clients-tab" data-bs-toggle="tab" href="#clients" role="tab">Clients</a>
                    </li>
                </ul>

                <!-- Contenu des onglets -->
                <div class="tab-content" id="rapportsTabsContent">
                    <!-- Fournisseurs -->
                    <div class="tab-pane fade show active" id="fournisseurs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Fournisseur</th>
                                        <th>Total Achats</th>
                                        <th>Total Paiements</th>
                                        <th>Solde</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="tableFournisseurs">
                                    <!-- Les données seront chargées via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Clients -->
                    <div class="tab-pane fade" id="clients" role="tabpanel">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select class="form-select" id="moisClient">
                                    <option value="">Tous les mois</option>
                                    <?php
                                    $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                                    foreach ($mois as $key => $value) {
                                        echo "<option value='" . ($key + 1) . "'>" . $value . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="anneeClient">
                                    <?php
                                    $annee_courante = date('Y');
                                    for ($i = $annee_courante; $i >= $annee_courante - 2; $i--) {
                                        echo "<option value='$i'>$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-primary" onclick="chargerChiffreAffaires()">Actualiser</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Téléphone</th>
                                        <th>Chiffre d'affaires</th>
                                        <th>Nombre de ventes</th>
                                    </tr>
                                </thead>
                                <tbody id="tableClients">
                                    <!-- Les données seront chargées via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Charger la liste des fournisseurs
        function chargerFournisseurs() {
            fetch('fonction_traitement/request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_liste_fournisseurs'
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('tableFournisseurs');
                tbody.innerHTML = '';
                
                data.forEach(fournisseur => {
                    const statut = fournisseur.solde > 0 ? 
                        '<span class="badge bg-danger">Non soldé</span>' : 
                        '<span class="badge bg-success">Soldé</span>';
                    
                    tbody.innerHTML += `
                        <tr>
                            <td>${fournisseur.nom}</td>
                            <td>${formatMontant(fournisseur.total_achats)}</td>
                            <td>${formatMontant(fournisseur.total_paiements)}</td>
                            <td>${formatMontant(fournisseur.solde)}</td>
                            <td>${statut}</td>
                        </tr>
                    `;
                });
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire('Erreur', 'Impossible de charger les fournisseurs', 'error');
            });
        }

        // Charger le chiffre d'affaires des clients
        function chargerChiffreAffaires() {
            const mois = document.getElementById('moisClient').value;
            const annee = document.getElementById('anneeClient').value;
            
            fetch('fonction_traitement/request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_chiffre_affaires_clients&mois=${mois}&annee=${annee}`
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('tableClients');
                tbody.innerHTML = '';
                
                data.forEach(client => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${client.nom} ${client.prenom}</td>
                            <td>${client.telephone}</td>
                            <td>${formatMontant(client.chiffre_affaires)}</td>
                            <td>${client.nombre_ventes}</td>
                        </tr>
                    `;
                });
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire('Erreur', 'Impossible de charger le chiffre d\'affaires', 'error');
            });
        }

        // Formater les montants
        function formatMontant(montant) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'MAD'
            }).format(montant);
        }

        // Charger les données au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            chargerFournisseurs();
            chargerChiffreAffaires();
        });
    </script>
</body>
</html> 