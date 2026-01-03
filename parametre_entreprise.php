<?php

require_once 'fonction_traitement/fonction.php';
check_access();
include('db/connecting.php');

// Récupération des informations existantes de la base de données
$sql = "SELECT * FROM entreprise"; // Récupère toutes les lignes
$stmt = $cnx->prepare($sql);
$stmt->execute();
$entreprises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérification si aucune ligne n'est retournée
if (empty($entreprises)) {
    $entreprises = []; // Initialise un tableau vide si aucune donnée n'est trouvée
}

// Initialiser les variables pour le formulaire
$nomEntreprise = '';
$adresseEntreprise = '';
$telephoneEntreprise = '';
$email = '';
$adresseBureau = '';
$adresseSite = '';
$logo = null;
$editId = null;
$IBAN = '';
$Code_SWIFT =  '';
$NumeroCompte = '';
$NomBanque = '';
$NCC = '';
$RCCM = '';
$NUMERO = '';

// Vérification de l'ID pour la modification
if (isset($_GET['edit_id'])) {
    $editId = $_GET['edit_id'];
    $sql = "SELECT * FROM entreprise WHERE id = :id";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(':id', $editId);
    $stmt->execute();
    $entreprise = $stmt->fetch(PDO::FETCH_ASSOC);

    // Remplir les variables avec les données existantes
    if ($entreprise) {
        $nomEntreprise = $entreprise['nom'];
        $adresseEntreprise = $entreprise['adresse'];
        $telephoneEntreprise = $entreprise['telephone'];
        $email = $entreprise['Email'];
        $adresseBureau = $entreprise['adresse_bureau'];
        $adresseSite = $entreprise['adresse_site'];
        $logo = $entreprise['logo1'];
        $IBAN = $entreprise['IBAN'];
        $Code_SWIFT = $entreprise['Code_SWIFT'];
        $NumeroCompte = $entreprise['NumeroCompte'];
        $NomBanque = $entreprise['NomBanque'];
        $NCC = $entreprise['NCC'];
        $RCCM = $entreprise['RCCM'];
        $NUMERO = $entreprise['NUMERO'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Récupération des données du formulaire
    $nomEntreprise = $_POST['nom-entreprise'] ?? '';
    $adresseEntreprise = $_POST['adresse-entreprise'] ?? '';
    $telephoneEntreprise = $_POST['telephone-entreprise'] ?? '';
    $email = $_POST['email'] ?? '';
    $adresseBureau = $_POST['adresse-bureau'] ?? '';
    $adresseSite = $_POST['adresse-site'] ?? '';
    $IBAN = $_POST['IBAN'] ?? '';
    $Code_SWIFT = $_POST['Code_SWIFT'] ?? '';
    $NumeroCompte = $_POST['NumeroCompte'] ?? '';
    $NomBanque = $_POST['NomBanque'] ?? '';
    $NCC = $_POST['NCC'] ?? '';
    $RCCM = $_POST['RCCM'] ?? '';
    $NUMERO = $_POST['NUMERO'] ?? '';



    // Gestion du téléchargement du logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
        $uploads_dir = 'image_article/';
        $tmp_name = $_FILES['logo']['tmp_name'];
        $photo_name = basename($_FILES['logo']['name']);
        $photo_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
        $photo_size = $_FILES['logo']['size'];

        // Vérifier les extensions et la taille du fichier
        if (in_array($photo_ext, ['jpg', 'jpeg', 'png', 'gif']) && $photo_size <= 2 * 1024 * 1024) {
            $destination = $uploads_dir . $photo_name;
            if (move_uploaded_file($tmp_name, $destination)) {
                $logo = $destination;
            }
        }
    }
    // Mise à jour de l'entreprise
    if ($editId) { // Vérifiez si nous avons un ID d'édition
        $sql = "UPDATE entreprise SET 
                    nom = :nom, 
                    adresse = :adresse, 
                    telephone = :telephone, 
                    Email = :email, 
                    adresse_bureau = :adresse_bureau, 
                    adresse_site = :adresse_site, 
                    NCC = :NCC, 
                    RCCM = :RCCM, 
                    NUMERO = :NUMERO, 
                    NomBanque = :NomBanque, 
                    NumeroCompte = :NumeroCompte, 
                    IBAN = :IBAN, 
                    Code_SWIFT = :Code_SWIFT, 
                    logo1 = :logo 
                WHERE id = :id";

        $stmt = $cnx->prepare($sql);

        // Lier les paramètres
        $stmt->bindParam(':nom', $nomEntreprise);
        $stmt->bindParam(':adresse', $adresseEntreprise);
        $stmt->bindParam(':telephone', $telephoneEntreprise);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':adresse_bureau', $adresseBureau);
        $stmt->bindParam(':adresse_site', $adresseSite);
        $stmt->bindParam(':NCC', $NCC);
        $stmt->bindParam(':RCCM', $RCCM);
        $stmt->bindParam(':NUMERO', $NUMERO);
        $stmt->bindParam(':NomBanque', $NomBanque);
        $stmt->bindParam(':NumeroCompte', $NumeroCompte);
        $stmt->bindParam(':IBAN', $IBAN);
        $stmt->bindParam(':Code_SWIFT', $Code_SWIFT);
        $stmt->bindParam(':logo', $logo); // Assurez-vous que $logo contient le bon nom du fichier ou le chemin
        $stmt->bindParam(':id', $editId); // Lier l'ID à mettre à jour


        // Exécution de la requête
        if ($stmt->execute()) {
            echo "Les données ont été insérées avec succès.";
        } else {
            echo "Erreur lors de l'insertion des données.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paramètres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">


    <style>
        #logo-preview img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .form-container {
            background-color: #ffffff;
            /* Fond blanc */
            border: 1px solid #dee2e6;
            /* Bordure gris clair */
        }

        input[type="date"] {
            background-color: #f8f9fa;
            /* Fond gris clair pour le champ date */
        }

        .btn-primary {
            background-color: #007bff;
            /* Couleur du bouton */
            border-color: #007bff;
            /* Bordure du bouton */
        }

        .btn-primary:hover {
            background-color: #0056b3;
            /* Couleur du bouton au survol */
            border-color: #0056b3;
            /* Bordure du bouton au survol */
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

        /* Table customization */
        .table-responsive {
            overflow-x: auto;
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }

        .thead-dark th {
            background-color: #343a40;
            color: white;
            text-transform: uppercase;
            font-weight: bold;
            white-space: nowrap;
        }

        .logo-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #343a40;
        }

        /* Button customization */
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: white;
            font-weight: bold;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table thead {
                display: none;
            }

            .table tbody tr {
                display: flex;
                flex-direction: column;
                margin-bottom: 1rem;
                border-bottom: 1px solid #dee2e6;
            }

            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 1rem;
                text-align: left;
                font-weight: bold;
            }

            .table tbody td:before {
                content: attr(data-label);
                font-weight: normal;
                color: #6c757d;
                text-transform: uppercase;
            }
        }
    </style>

    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body id="parametres">
    <?php include('includes/user_indicator.php'); ?>
        <?php include('includes/navigation_buttons.php'); ?>
    <div class="loader-wrapper" id="loader">
        <div class="loader">
            <div class="logo"></div>
        </div>
    </div>

    <!-- Conteneur pour les messages -->
    <div id="message-container" class="container mt-3"></div>

    <div class="container">
        <div class="title-wrapper">
            <h1 class="title">Paramètre d’Entreprise</h1>
        </div>
        <div class="m-3">
            
        </div>

        <form id="form" enctype="multipart/form-data" method="post">
            <div class="row">

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-info-circle"></i> Informations Générales
                        </div>
                        <div class="card-body">
                            <p>Configurer les informations de base de l'entreprise.</p>
                            <div class="mb-3">
                                <label class="form-label">Nom de l'Entreprise</label>
                                <input type="text" name="nom-entreprise" id="nom-entreprise" class="form-control" placeholder="Ex: Mon Entreprise" value="<?= htmlspecialchars($nomEntreprise) ?>" >
                            </div>
                            <div class="mb-3">
                                <label for="adresse-entreprise" class="form-label">Adresse</label>
                                <input type="text" name="adresse-entreprise" id="adresse-entreprise" class="form-control" placeholder="Ex: 123 Rue Exemple" value="<?= htmlspecialchars($adresseEntreprise) ?>" >
                            </div>
                            <div class="mb-3">
                                <label for="telephone-entreprise" class="form-label">Téléphone</label>
                                <input type="tel" name="telephone-entreprise" id="telephone-entreprise" class="form-control" placeholder="Ex: +123456789" value="<?= htmlspecialchars($telephoneEntreprise) ?>" >
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" placeholder="Ex: exemple@entreprise.com" value="<?= htmlspecialchars($email) ?>" >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-building"></i> Détails de l'Entreprise
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Adresse du Bureau</label>
                            <input type="text" name="adresse-bureau" id="adresse-bureau" class="form-control" placeholder="Ex: 123 Bureau" value="<?= htmlspecialchars($adresseBureau) ?>">
                            </div>
                            <div class="mb-3">
                                <label for="adresse-site" class="form-label">Adresse du Site Web</label>
                                <input type="text" name="adresse-site" id="adresse-site" class="form-control" placeholder="Ex: www.entreprise.com" value="<?= htmlspecialchars($adresseSite) ?>" >
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Numéro de Compte Contribuable (N CC)</label>
                                <input type="text" name="NCC" id="NCC" class="form-control" placeholder="Ex: 1786955J" value="<?= htmlspecialchars($NCC) ?>" >
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Numéro du Registre de Commerce et du Crédit Mobilier (N RCCM) </label>
                                <input type="text" name="RCCM" id="RCCM" class="form-control" placeholder="Ex: 5000" value="<?= htmlspecialchars($RCCM) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Numéro </label>
                                <input type="text" name="NUMERO" id="NUMERO" class="form-control" value="<?= htmlspecialchars($NUMERO) ?>">
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-building"></i> informations Bancaire
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Nom de Banque</label>
                                <input type="text" name="NomBanque" id="NomBanque" class="form-control" placeholder="Ex: Banque Atlantique" value="<?= htmlspecialchars($NomBanque) ?>" >
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Numero de Compte</label>
                                <input type="text" name="NumeroCompte" id="NumeroCompte" class="form-control" placeholder="Ex: 010254698569" value="<?= htmlspecialchars($NumeroCompte) ?>" >
                            </div>
                            <div class="mb-3">
                                <label class="form-label">IBAN</label>
                                <input type="text" name="IBAN" id="IBAN" class="form-control" placeholder="Ex: CI56 CI52 ..." value="<?= htmlspecialchars($IBAN) ?>" >
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Code SWIFT</label>
                                <input type="text" name="Code_SWIFT" id="Code_SWIFT" class="form-control" placeholder="Ex: BG..." value="<?= htmlspecialchars($Code_SWIFT) ?>" >
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="logo" class="form-label">Logo de l'Entreprise</label>
                <input type="file" name="logo" id="logo" class="form-control" accept="image/*" onchange="previewLogo(this)">
            </div>
            <div id="logo-preview">
                <?php if ($logo): ?>
                    <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" id="logoImg">
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">valider</button>
        </form>
    </div>
    <div class="container mt-5">
        <h2 class="text-center text-uppercase mb-4">Liste des Information Entreprise</h2>
        <div>
            <table class="table table-hover table-bordered">
                <thead class="thead-dark text-center">
                    <tr>
                        <th></th>
                        <th><i class="fas fa-building"></i> Nom</th>
                        <th><i class="fas fa-map-marker-alt"></i> Adresse</th>
                        <th><i class="fas fa-phone"></i> Téléphone</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-id-card"></i> N CC</th>
                        <th><i class="fas fa-id-badge"></i> N RCCM</th>
                        <th><i class="fas fa-id-badge"></i> NUMERO</th>
                        <th><i class="fas fa-university"></i> Nom de Banque</th>
                        <th><i class="fas fa-university"></i> Numéro de Banque</th>
                        <th><i class="fas fa-credit-card"></i> IBAN</th>
                        <th><i class="fas fa-credit-card"></i> Code SWIFT</th>
                        <th><i class="fas fa-image"></i> Logo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entreprises as $index => $entreprise): ?>
                        <tr class="text-center">
                            <td><?= htmlspecialchars($index + 1) ?></td>
                            <td><?= htmlspecialchars($entreprise['nom'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['adresse'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['telephone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['Email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['NCC'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['RCCM'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['NUMERO'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['NomBanque'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['NumeroCompte'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['IBAN'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entreprise['Code_SWIFT'] ?? '') ?></td>
                            <td>
                                <?php if ($entreprise['logo1']): ?>
                                    <img src="<?= htmlspecialchars($entreprise['logo1']) ?>" alt="Logo" class="rounded-circle logo-img">
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit_id=<?= htmlspecialchars($entreprise['id']) ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Texte rouge centré -->
    <div class="text-center my-4">
        <p class="text-danger fw-bold">Veuillez vous déconnecter pour appliquer les modifications</p>
    </div>

    <!-- Bouton de déconnexion -->
    <div class="text-center">
        <form action="fonction_traitement/request.php" method="post">
            <button type="submit" name="deconnexion_admin" class="btn btn-danger px-4 py-2">Déconnexion</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-2sVh9Eu8D5BCRf8B1MZK/J+rM0/0SRoklB4JWZWY04T8hRPnUZ4gdjF7SmEr1hLJ" crossorigin="anonymous"></script>
    <script>
        $(window).on("load", function() {
            $(".loader-wrapper").fadeOut("slow");
        });

        // Vérification du logo
        $("#logo").on("change", function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $("#logo-preview").html('<img src="' + e.target.result + '" alt="Logo de l\'entreprise">');
                }
                reader.readAsDataURL(file);
            }
        });
        $(window).on('load', function() {
            $('#loader').fadeOut(500);
        });
    </script>
</body>

</html>