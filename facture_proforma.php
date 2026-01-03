<?php
include('db/connecting.php');  // Connexion à la base de données
include('fonction_traitement/fonction.php');  // Inclusion des fonctions de traitement
session_start();

require_once 'fonction_traitement/fonction.php';
check_access();

// Redirection si l'utilisateur n'est pas connecté
if (!isset($_SESSION['nom_utilisateur'])) {
    header('location: connexion.php');
    exit();
}


// Initialisation des variables
$article = null;
$id_article = '';
$mode_paiement = selection_element('mode_reglement');


// Initialisation du panier si vide
if (!isset($_SESSION['proformaligne'])) {
    $_SESSION['proformaligne'] = [];
}


// Ajout d'un produit au panier
if (isset($_POST['add-btn'])) {
    // Récupération des données du produit depuis le formulaire
    $id_article = htmlspecialchars($_POST['IDARTICLE']);  // Récupération correcte de l'ID de l'article
    $libelle = htmlspecialchars($_POST['produit']);
    $identifiant = htmlspecialchars($_POST['identifiant']);
    $prix = floatval($_POST['prix']);
    $quantite = intval($_POST['quantite']);
    $remise = floatval($_POST['remise']);

    // Ajout du produit au panier (enregistrement dans la session)
    $_SESSION['proformaligne'][$id_article] = [
        'IDARTICLE' => $id_article,
        'produit' => $libelle,
        'identifiant' => $identifiant,
        'prix' => $prix,
        'quantite' => $quantite,
        'remise' => $remise
    ];
}

// Suppression d'un produit du panier
if (isset($_POST['supprimer_panier'])) {
    $id_article = htmlspecialchars($_POST['identifiant']);  // Récupération de l'identifiant du produit à supprimer
    if (isset($_SESSION['proformaligne'][$id_article])) {
        unset($_SESSION['proformaligne'][$id_article]);  // Suppression du produit du panier
    }
}

// Vider le panier
if (isset($_POST['vider_panier'])) {
    unset($_SESSION['proformaligne']);  // Suppression de tous les produits du panier
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture Proforma</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #ff0000;
            color: white;
            padding: 15px;
            text-align: center;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .form-container,
        .cart-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            margin-bottom: 20px;
        }

        .form-group,
        .form-control {
            border-radius: 12px;
        }

        .form-control:focus {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        button {
            margin-right: 0.5rem;
        }

        .modal-header {
            background-color: #ff0000;
            color: white;
        }

        .btn-navigation {
            background-color: #ff0000;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn-navigation:hover {
            background-color: #cc0000;
            text-decoration: none;
            color: white;
        }

        .navigation-links {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        /* Neige */
        .snow {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 9999;
            /* Pour être au-dessus des autres éléments */
        }

        .flake {
            position: absolute;
            background: white;
            opacity: 0.8;
            border-radius: 50%;
            animation: fall linear infinite;
        }

        @keyframes fall {
            0% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(100vh);
            }
        }

        .produit-container {
            border: 1px solid #ccc;
            /* Bordure de la liste */
            max-height: 200px;
            /* Hauteur maximale */
            overflow-y: auto;
            /* Activer le défilement vertical */
            position: absolute;
            /* Positionnement absolu pour superposition */
            z-index: 1000;
            /* Assurer que la liste est au-dessus des autres éléments */
            width: calc(100% - 20px);
            /* Ajuste la largeur pour correspondre à l'input */
            background-color: white;
            /* Fond blanc pour la liste */
        }

        .list-group-item {
            cursor: pointer;
            padding: 10px;
            /* Ajout de l'espace autour des éléments */
        }

        .list-group-item:hover {
            background-color: #f0f0f0;
            /* Changement de couleur au survol */
        }

        .cart-container {
            background-color: #f9f9f9;
            /* Couleur d'arrière-plan douce */
            border: 1px solid #ddd;
            border-radius: 10px;
            /* Bordures arrondies */
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            /* Ombre douce */
        }

        .cart-container h2 {
            color: #333;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            font-size: 24px;
        }

        .cart-container table {
            width: 100%;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
        }

        .cart-container th,
        .cart-container td {
            text-align: center;
            padding: 15px;
            font-size: 16px;
        }

        .cart-container th {
            background-color: #343a40;
            color: #fff;
            font-weight: 600;
        }

        .cart-container tbody tr:nth-child(odd) {
            background-color: #f1f1f1;
            /* Couleur pour les lignes impaires */
        }

        .cart-container tbody tr:hover {
            background-color: #e9ecef;
            /* Couleur au survol */
            transition: background-color 0.3s ease;
        }

        .cart-container .btn {
            font-size: 14px;
            padding: 10px 15px;
            margin: 5px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .cart-container .btn-danger {
            background-color: #dc3545;
            color: #fff;
            border: none;
        }

        .cart-container .btn-danger:hover {
            background-color: #c82333;
        }

        .cart-container .btn-success {
            background-color: #28a745;
            color: #fff;
            border: none;
        }

        .cart-container .btn-success:hover {
            background-color: #218838;
        }

        .total-amount {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-top: 20px;
            text-align: right;
            width: 100%;
            border: none;
            background-color: #fff;
        }

        .fas {
            margin-right: 8px;
        }
    </style>
    <!-- Système de thème sombre/clair -->
</head>

<body>

    <header>
        <h1><i class="fas fa-file-invoice"></i> Facture Proforma</h1>
    </header>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>

    <main class="container">
        <br><br>
        
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
        <section id="proforma-form">
            <form method="post">
                <div class="form-container">
                    <h2><i class="fas fa-plus-circle"></i> Ajouter un Produit</h2>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="produit"><i class="fas fa-tag"></i> Produit</label>
                                <input type="text" id="produit" name="produit" class="form-control" placeholder="Tapez pour rechercher un produit..." onkeyup="filterProducts()" value="<?= isset($_POST['produit']) ? htmlspecialchars($_POST['produit']) : '' ?>" required />
                                <input type="hidden" id="IDARTICLE" name="IDARTICLE" value="<?= isset($_POST['IDARTICLE']) ? htmlspecialchars($_POST['IDARTICLE']) : '' ?>">
                                <div id="produitContainer" class="produit-container" style="display: none;">
                                    <ul id="produitList" class="list-group"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="identifiant"><i class="fas fa-id-badge"></i> Identifiant</label>
                                <input type="text" id="identifiant" name="identifiant" class="form-control" readonly value="<?= isset($_POST['identifiant']) ? htmlspecialchars($_POST['identifiant']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group">
                                <label for="quantite"><i class="fas fa-sort-amount-up"></i> Quantité</label>
                                <input type="number" id="quantite" name="quantite" class="form-control" placeholder="Quantité" required value="<?= isset($_POST['quantite']) ? htmlspecialchars($_POST['quantite']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group">
                                <label for="prix">Prix de vente</label>
                                <input type="text" id="prix" name="prix" class="form-control" readonly value="<?= isset($_POST['prix']) ? htmlspecialchars($_POST['prix']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group">
                                <label for="remise"><i class="fas fa-percent"></i> Remise (%)</label>
                                <input type="number" id="remise" name="remise" class="form-control" placeholder="Remise en %" min="0" max="100" value="<?= isset($_POST['remise']) ? htmlspecialchars($_POST['remise']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" name="add-btn">Ajouter au Panier</button>
                </div>
            </form>
            <!-- Section pour le récapitulatif du panier -->
            <div class="cart-container">
                <h2><i class="fas fa-list-ul"></i> Produits dans la Facture Proforma</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Prix Unitaire</th>
                            <th>Quantité</th>
                            <th>Remise</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cartTable">
                        <?php
                        $totalPanier = 0; // Initialisation du total du panier

                        if (!empty($_SESSION['proformaligne'])) {
                            foreach ($_SESSION['proformaligne'] as $id_article => $produits) {
                                $produit = htmlspecialchars($produits['produit']);
                                $quantite = intval($produits['quantite']);
                                $prix = floatval($produits['prix']);
                                $remise = floatval($produits['remise']);
                                // Calcul du total par produit avec remise
                                $total = ($prix * $quantite) * (1 - $remise / 100);
                                // Ajout au total général du panier
                                $totalPanier += $total;
                        ?>
                                <tr>
                                    <td><?= $produit ?></td>
                                    <td><?= number_format($prix, 0, ',', ' ') ?> F.CFA</td>
                                    <td><?= $quantite ?></td>
                                    <td><?= number_format($remise) ?>%</td>
                                    <td><?= number_format($total, 0, ',', ' ') ?> F.CFA</td>
                                    <td>
                                        <!-- Bouton pour supprimer un produit du panier -->
                                        <form method="post">
                                            <input type="hidden" name="identifiant" value="<?= htmlspecialchars($id_article) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" name="supprimer_panier">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='6'><center>Votre panier est vide.</center></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <!-- Bouton pour vider le panier -->
                <form method="post" class="mb-4">
                    <button type="submit" name="vider_panier" class="btn btn-danger mt-3">
                        <i class="fas fa-trash"></i> Vider le panier
                    </button>
                </form>
                <form action="fonction_traitement/request.php" method="post">
                    <!-- Affichage du total du panier -->
                    <div class="text-right">
                        <label for="total" class="font-weight-bold">Total à payer :</label>
                        <input type="hidden" name="TotalNetPayer" value="<?= $totalPanier ?>">
                        <input type="text" class="total-amount" id="total" value="<?= number_format($totalPanier, 0, ',', ' ') ?> F.CFA" style="border:none; font-weight:bold; font-size:18px;">
                    </div>
                    <div class="form-container">
                        <h2><i class="fas fa-info-circle"></i> Informations de la Facture Proforma</h2>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="nomClient"><i class="fas fa-user"></i> Nom du Client</label>
                                    <input type="text" id="nomClient" name="nomClient" class="form-control" placeholder="Nom du client" required value="<?= isset($_POST['nomClient']) ? htmlspecialchars($_POST['nomClient']) : '' ?>">
                                </div>

                                <div class="form-group">
                                    <label for="telephone"><i class="fas fa-phone"></i> N° Téléphone</label>
                                    <input type="text" id="telephone" name="telephone" class="form-control" placeholder="Numéro de téléphone" required value="<?= isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : '' ?>">
                                </div>

                                <div class="form-group">
                                    <label for="email"><i class="fas fa-envelope"></i> Adresse Email</label>
                                    <input type="text" id="email" name="email" class="form-control" placeholder="Email Client" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="dateFacture"><i class="fas fa-calendar-alt"></i> Date de la Facture</label>
                                    <input type="date" id="dateFacture" name="dateFacture" class="form-control" required value="<?= isset($_POST['dateFacture']) ? htmlspecialchars($_POST['dateFacture']) : '' ?>">
                                </div>

                                <div class="form-group">
                                    <label for="conditionsReglement"><i class="fas fa-file-contract"></i> Conditions de Règlement</label>
                                    <select id="mode" name="mode_paiement" class="form-control" required>
                                        <option value="">------------</option>
                                        <?php foreach ($mode_paiement as $modes): ?>
                                            <option value="<?php echo htmlspecialchars($modes['IDMODE_REGLEMENT']); ?>">
                                                <?php echo htmlspecialchars($modes['ModeReglement']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="date_validite"><i class="fas fa-calendar-alt"></i> <strong> Validité du Proforma :</strong></label>
                                    <input type="date" id="date_validite" name="date_validite" class="form-control" required value="<?= isset($_POST['date_validite']) ? htmlspecialchars($_POST['date_validite']) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Bouton pour enregistrer la proforma -->
                    <?php echo bouton_action('Enregistrer la Proforma', 'facture_proforma', 'valider', 'btn btn-success', 'type="submit" name="submitProforma"'); ?>
                    <button class="btn btn-secondary btn-lg mr-2" type="reset">Annuler</button>
                </form>
            </div>

        </section>
    </main>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Stockez les produits dans une variable pour le filtrage
        const products = <?php
                            // Requête pour récupérer les produits
                            $query = "SELECT IDARTICLE, CodePersoArticle, libelle, prixVenteTTC FROM article";
                            $stmt = $cnx->prepare($query);
                            $stmt->execute();


                            // Récupération des produits sous forme de tableau associatif
                            $produits = [];
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $produits[] = $row;
                            }
                            echo json_encode($produits);

                            ?>;

        function filterProducts() {
            const input = document.getElementById('produit');
            const idarticle = document.getElementById('IDARTICLE');
            const identifiantInput = document.getElementById('identifiant');
            const prixInput = document.getElementById('prix');
            const filter = input.value.toLowerCase();
            const produitContainer = document.getElementById('produitContainer');
            const produitList = document.getElementById('produitList');

            produitList.innerHTML = ''; // Nettoyer la liste des produits précédents
            const filtered = products.filter(product => product.libelle.toLowerCase().includes(filter));

            if (filter === '' || filtered.length === 0) {
                produitContainer.style.display = 'none';
                identifiantInput.value = '';
                prixInput.value = '';
                return;
            }
            filtered.forEach(product => {
                const listItem = document.createElement('li');
                listItem.textContent = product.libelle;
                listItem.classList.add('list-group-item');
                listItem.addEventListener('click', () => {
                    input.value = product.libelle;
                    idarticle.value = product.IDARTICLE;
                    identifiantInput.value = product.CodePersoArticle;
                    prixInput.value = product.prixVenteTTC;
                    produitContainer.style.display = 'none';
                });
                produitList.appendChild(listItem);
            });
            produitContainer.style.display = 'block';
        }
    </script>
</body>

</html>