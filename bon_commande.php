<?php
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();
    
    
    $fournisseurs = selection_element('fournisseur');
    // Ajout d'un produit au panier
    if (isset($_POST['add-btn'])) {
        if (!isset($_SESSION['commandemaligne'])) {
            $_SESSION['commandemaligne'] = [];
        }        
        // Récupération des données du produit depuis le formulaire
        $id_article = htmlspecialchars($_POST['IDARTICLE']);  // Récupération correcte de l'ID de l'article
        $produit = htmlspecialchars($_POST['produit']);
        $prix = floatval($_POST['prix']);
        $quantite = intval($_POST['quantite']);

        // Ajout du produit au panier (enregistrement dans la session)
        $_SESSION['commandemaligne'][$id_article] = [ // Use 'identifiant' as the key
            'IDARTICLE' => $id_article,
            'produit' => $produit,
            'prix' => $prix,
            'quantite' => $quantite
        ];
    }

    // Suppression d'un produit du panier
    if (isset($_POST['supprimer_panier'])) {
        $id_article = htmlspecialchars($_POST['identifiant']);
        if (isset($_SESSION['commandemaligne'][$id_article])) {
            unset($_SESSION['commandemaligne'][$id_article]);
        }
        
    }
    // Vider le panier
    if (isset($_POST['vider_panier'])) {
        unset($_SESSION['commandemaligne']);
    }
} catch (\Throwable $th) {
    
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de Commande</title>
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
            width: 100%;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .container {
            max-width: 1200px;
            width: 100%;
            margin: 20px auto;
            padding: 0 20px;
        }

        .form-container, .cart-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            margin-bottom: 20px;
        }

        .form-group, .form-control {
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
         /* Style pour le tableau */
        .table {
            border: 1px solid #dee2e6;
        }

        .table th {
            background-color: #ff4b5c; /* Rouge doux pour les en-têtes */
            color: white; /* Texte blanc */
        }

        .table td {
            background-color: #f8f9fa; /* Couleur de fond douce pour les lignes */
            color: #333; /* Texte gris foncé */
            font-weight: 500;
        }

        .table tbody tr:hover {
            background-color: #f1f1f1; /* Survol des lignes */
        }

        /* Boutons d'action */
        .btn-success {
            background-color: #28a745; /* Vert standard pour "Valider" */
            border-color: #28a745;
        }

        .btn-warning {
            background-color: #ffc107; /* Jaune pour "Vider le Panier" */
            border-color: #ffc107;
            color: black;
        }

        .btn-danger {
            background-color: #ff4b5c; /* Rouge pour "Supprimer" */
            border-color: #ff4b5c;
        }

        .btn-success:hover, .btn-warning:hover, .btn-danger:hover {
            opacity: 0.8; /* Effet hover doux sur les boutons */
        }

        /* Total à payer */
        .total-amount {
            color: #ff4b5c; /* Texte rouge pour le montant total */
            font-size: 1.5em;
            font-weight: bold;
        }

        /* Conteneur pour le titre */
        h3 {
            color: #ff4b5c; /* Rouge pour le titre */
            font-size: 1.75em;
            font-weight: bold;
            margin-bottom: 20px;
        }

        /* Style pour le formulaire et boutons */
        .form-group button {
            font-size: 1.1em;
            font-weight: bold;
        }

        /* Style des bordures */
        .table-bordered th, .table-bordered td {
            border: 1px solid #dee2e6;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    
    <header>
        <h1><i class="fas fa-box"></i> Bon de Commande</h1>
    </header>

    <main class="container">
    <br><br>
        
    <?php include('includes/navigation_buttons.php'); ?>
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
        <section id="livraison-form">
            <form method="post">
                <div class="form-container">
                    <h2><i class="fas fa-plus-circle"></i> Ajouter un Article</h2>
                    <div id="article-form">
                        <div class="row">
                           
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                <label for="produit"><i class="fas fa-tag"></i> Produit</label>
                                <input type="text" id="produit" name="produit" class="form-control" placeholder="Tapez pour rechercher un produit..." onkeyup="filterProducts()" value="<?= isset($_POST['produit']) ? htmlspecialchars($_POST['produit']) : '' ?>"/>
                                <input type="hidden" id="IDARTICLE" name="IDARTICLE" value="<?= isset($_POST['IDARTICLE']) ? htmlspecialchars($_POST['IDARTICLE']) : '' ?>">
                                <div id="produitContainer" class="produit-container" style="display: none;">
                                    <ul id="produitList" class="list-group"></ul>
                                </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="identifiant"><i class="fas fa-id-badge"></i> Identifiant</label>
                                    <input type="text" id="identifiant" class="form-control" name="identifiant" placeholder="Identifiant" value="<?php isset($_POST['identifiant']) ? htmlspecialchars($_POST['identifiant']) : ''; ?>"readonly>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label for="quantite"><i class="fas fa-sort-amount-up"></i> Quantité</label>
                                    <input type="number" id="quantite" name="quantite" class="form-control" placeholder="Quantité"  value="<?= isset($_POST['quantite']) ? htmlspecialchars($_POST['quantite']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label for="prixAchat"><i class="fas fa-money-bill-wave"></i> Prix d'Achat</label>
                                    <input type="text" id="prix" name="prix" class="form-control" readonly value="<?= isset($_POST['prix']) ? htmlspecialchars($_POST['prix']) : '' ?>">
                                </div>
                            </div>
                    
                            <div class="col-md-4 mb-3">
                                <button type="submit" class="btn btn-danger add-btn mb-3"   name="add-btn"><i class="fas fa-plus"></i> Ajouter au Bon de Commande</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="cart-container">
           
                <h3 class="text-center mt-4"><i class="fas fa-box"></i> Produits Sélectionnés</h3>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered shadow-sm bg-white rounded">
                        <thead class="thead-dark text-center">
                                <tr>
                                    <th>Libellé</th>
                                    <th>Prix Achat</th>
                                    <th>Quantité</th>
                                    <th>Prix Total</th>
                                    <th>Actions</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalPanier = 0; // Initialisation du total du panier
                            if (!empty($_SESSION['commandemaligne'])) {
                                    foreach ($_SESSION['commandemaligne'] as $id_article => $produits) {
                                        $produit = htmlspecialchars($produits['produit'] ?? '');
                                        $prixAchat = floatval($produits['prix']);
                                        $quantite = intval($produits['quantite']);
                                        $prixGlobal = $prixAchat * $quantite;
                            ?>
                            <tr class="text-center align-middle">
                                <td class="align-middle"><?= $produit ?></td>
                                <td class="align-middle"><?= number_format($prixAchat, 0, ',', ' ') ?> F.CFA</td>
                                <td class="align-middle"><?= $quantite ?></td>
                                <td class="align-middle"><?= number_format($prixAchat * $quantite, 0, ',', ' ') ?> F.CFA</td>
                                <td class="align-middle">
                                    <form method="post">
                                        <input type="hidden" name="identifiant" value="<?= htmlspecialchars($id_article) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" name="supprimer_panier">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php
                                $totalPanier += $prixGlobal;
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted'>Aucun produit dans le panier</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <!-- Total du panier -->
                    <div class="d-flex justify-content-between mt-3">
                        <div for="total" class="font-weight-bold mr-2" style="font-size: 1.2em;">Total à payer :</div>
                        <input type="text" class="total-amount form-control-plaintext text-right w-50" id="total" name="TotalNetPayer" 
                            value="<?= number_format($totalPanier, 0, ',', ' ') ?> F.CFA" readonly style="font-weight:bold; font-size: 1.5em;">
                    </div>
                </div>

                <div class="text-right mt-4">
                    <form method="post">
                        <button type="submit" name="vider_panier" class="btn btn-warning btn-lg">
                            <i class="fas fa-trash-alt"></i> Vider le Panier
                        </button>
                    </form>
                </div>
            </div>
            <form action="fonction_traitement/request.php" method="post">
                <input type="hidden" name="TotalNetPayer" value="<?= $totalPanier ?> F.CFA" readonly>
                <div class="form-container">
                    <h2><i class="fas fa-info-circle"></i> Informations du Bon de Commande</h2>
                    <div id="form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="fournisseur"><i class="fas fa-truck"></i> Fournisseur</label>
                                    <select class="form-control" id="fournisseur" name="fournisseur" class="form-control" required>
                                        <option value="">------------</option> 
                                        <?php foreach ($fournisseurs as $fournisseur): ?>
                                            <option value="<?= htmlspecialchars($fournisseur['IDFOURNISSEUR']) ?>"><?= htmlspecialchars($fournisseur['NomFournisseur']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="numeroBon"><i class="fas fa-file-invoice"></i> Numéro du Bon Commande</label>
                                    <input type="text" id="numeroBon" name="numeroBon" class="form-control" placeholder="Numéro du Bon" value="<?= isset($_POST['numeroBon']) ? htmlspecialchars($_POST['numeroBon']) : '' ?>" readonly required>
                                     <button type="button" onclick="generateNextCode()" class="btn btn-primary mt-2">Générer un code</button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="dateLivraison"><i class="fas fa-calendar-alt"></i> Date de Commande</label>
                                    <input type="date" id="dateLivraison" name="dateLivraison" class="form-control" required  value="<?= isset($_POST['dateLivraison']) ? htmlspecialchars($_POST['dateLivraison']) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-right mt-4">
                        <?php echo bouton_action('Valider la Commande', 'bon_commande', 'valider', 'btn btn-success btn-lg mr-2', 'type="submit" name="valider_commande"'); ?>
                        <button class="btn btn-secondary btn-lg mr-2" type="reset">Annuler</button>
                    </div>
                </div>
            </form>
        </section>
    </main>


    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>

        
        // Stockez les produits dans une variable pour le filtrage
        const products = <?php
            // Requête pour récupérer les produits
            $query = "SELECT IDARTICLE, CodePersoArticle, libelle, prixAchatHT FROM article";
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
                    prixInput.value = product.prixAchatHT;
                    produitContainer.style.display = 'none';
                });
                produitList.appendChild(listItem);
            });
            produitContainer.style.display = 'block';
        }
        
// Variables globales pour la génération de codes
let generatedCode = '';
let codeGenerated = false;

window.onload = function() {
    // Réactive le bouton si le champ est vide au chargement
    if (!document.getElementById('numeroBon').value) {
        document.querySelector('button[onclick="generateNextCode()"]')?.removeAttribute('disabled');
        generatedCode = '';
        codeGenerated = false;
    }
};

function generateNextCode() {
    if (codeGenerated) {
        alert("Vous devez enregistrer la commande ou vider le champ avant de générer un nouveau code.");
        return;
    }
    
    // Afficher un indicateur de chargement
    const button = document.querySelector('button[onclick="generateNextCode()"]');
    const originalText = button.textContent;
    button.textContent = 'Génération...';
    button.disabled = true;
    
    // Récupérer le prochain code depuis la base de données
    fetch('fonction_traitement/generate_commande_code.php', {
        method: 'GET',
        credentials: 'include' // Inclure les cookies de session
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            generatedCode = data.code;
            document.getElementById('numeroBon').value = generatedCode;
            codeGenerated = true;
            // Désactive le bouton jusqu'à enregistrement ou vidage du champ
            button.textContent = 'Code généré';
            button.disabled = true;
        } else {
            alert('Erreur lors de la génération du code: ' + data.error);
            button.textContent = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur de connexion lors de la génération du code');
        button.textContent = originalText;
        button.disabled = false;
    });
}

    </script>    
</body>
</html>
