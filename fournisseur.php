<?php
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();
    include('fonction_traitement/fonction.php');
    $fournisseurs = selection_element('fournisseur');
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . $tableName;
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
    <title>Gestion des Fournisseurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        header {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: #fff;
            padding: 2rem 0 1.5rem 0;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .form-section {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section h2 {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #ff0000;
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: #555;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #ff0000;
            box-shadow: 0 0 0 0.2rem rgba(255, 0, 0, 0.1);
        }
        
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #ff0000;
            border: none;
        }
        
        .btn-primary:hover {
            background: #cc0000;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            border: none;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }
        
        .table-section {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .table-section h2 {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #ff0000;
            padding-bottom: 0.5rem;
        }
        
        .search-container {
            margin-bottom: 1.5rem;
        }
        
        .search-input {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            width: 100%;
            max-width: 400px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #ff0000;
            box-shadow: 0 0 0 0.2rem rgba(255, 0, 0, 0.1);
            outline: none;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .table thead {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: #fff;
        }
        
        .table thead th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 1rem;
            text-align: center;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }
        
        .table tbody td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid #e9ecef;
            text-align: center;
            vertical-align: middle;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 8px;
        }
        
        .btn-edit {
            background: #007bff;
            border: none;
            color: #fff;
        }
        
        .btn-edit:hover {
            background: #0056b3;
            color: #fff;
        }
        
        .btn-delete {
            background: #dc3545;
            border: none;
            color: #fff;
        }
        
        .btn-delete:hover {
            background: #c82333;
            color: #fff;
        }
        
        .btn-sms {
            background: #28a745;
            border: none;
            color: #fff;
        }
        
        .btn-sms:hover {
            background: #218838;
            color: #fff;
        }
        
        .btn-email {
            background: #17a2b8;
            border: none;
            color: #fff;
        }
        
        .btn-email:hover {
            background: #138496;
            color: #fff;
        }
        
        .empty-message {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
            font-style: italic;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .form-section, .table-section {
                padding: 1rem;
            }
            
            .table thead th, .table tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .btn-sm {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>

<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    
    <header>
        <h1>
            <i class="fas fa-truck"></i>
            Gestion des Fournisseurs
        </h1>
    </header>
    
    <div class="container">
        <?php
        if (isset($_GET['success'])) {
            $successMessage = htmlspecialchars($_GET['success']);
            echo '<div class="alert alert-success" role="alert">' . $successMessage . '</div>';
        }
        if (isset($_GET['error'])) {
            $errorMessage = htmlspecialchars($_GET['error']);
            echo '<div class="alert alert-danger" role="alert">' . $errorMessage . '</div>';
        }
        ?>
        
        <div class="row">
            <div class="col-lg-4">
                <div class="form-section">
                    <h2 id="form-title">
                        <i class="fas fa-plus-circle"></i>
                        Ajouter un Fournisseur
                    </h2>
                    <form method="post" action="fonction_traitement/request.php" id="supplier-form">
                        <input type="hidden" id="idFournisseur" name="idFournisseur">
                        
                        <div class="form-group">
                            <label for="nomFournisseur">
                                <i class="fas fa-user"></i>
                                Nom du Fournisseur
                            </label>
                            <input type="text" id="nomFournisseur" name="nomFournisseur" class="form-control" placeholder="Entrez le nom du fournisseur" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="emailFournisseur">
                                <i class="fas fa-envelope"></i>
                                Adresse Email
                            </label>
                            <input type="email" id="emailFournisseur" name="emailFournisseur" class="form-control" placeholder="exemple@email.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="telephoneFournisseur">
                                <i class="fas fa-phone"></i>
                                Numéro de Téléphone
                            </label>
                            <input type="text" id="telephoneFournisseur" name="telephoneFournisseur" class="form-control" placeholder="+225 0700000000" required>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" id="submitButton" name="enregister_founisseur" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Ajouter
                            </button>
                            <button type="submit" id="updateButton" name="mettre_a_jour_fournisseur" class="btn btn-success d-none">
                                <i class="fas fa-edit"></i>
                                Modifier
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="table-section">
                    <h2>
                        <i class="fas fa-list"></i>
                        Liste des Fournisseurs
                    </h2>
                    
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="🔍 Rechercher un fournisseur, email ou téléphone...">
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Date d'Ajout</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="table-body">
                                <?php
                                if (!empty($fournisseurs)) {
                                    $id = 11;
                                    foreach ($fournisseurs as $fournisseur): 
                                        $isSystemSupplier = $fournisseur['IDFOURNISSEUR'] == 11; // ID du fournisseur système
                                    ?>
                                        <tr class="<?php echo $isSystemSupplier ? 'table-warning' : ''; ?>">
                                            <td><strong><?php echo $id; ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($fournisseur['NomFournisseur']); ?>
                                                <?php if ($isSystemSupplier): ?>
                                                    <span class="badge bg-warning text-dark ms-2">
                                                        <i class="fas fa-shield-alt"></i> Système
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($fournisseur['eMailFournisseur']); ?></td>
                                            <td><?php echo htmlspecialchars($fournisseur['TelephoneFournisseur']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($fournisseur['DateIns'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if (!$isSystemSupplier): ?>
                                                        <button class="btn btn-sm btn-edit edit-btn"
                                                            data-id="<?php echo htmlspecialchars($fournisseur['IDFOURNISSEUR']); ?>"
                                                            data-nom="<?php echo htmlspecialchars($fournisseur['NomFournisseur']); ?>"
                                                            data-email="<?php echo htmlspecialchars($fournisseur['eMailFournisseur']); ?>"
                                                            data-telephone="<?php echo htmlspecialchars($fournisseur['TelephoneFournisseur']); ?>"
                                                            title="Modifier">
                                                            <i class="fas fa-pen-to-square"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="Ce fournisseur système ne peut pas être modifié">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$isSystemSupplier): ?>
                                                        <form method="post" action="fonction_traitement/request.php" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?');">
                                                            <input type="hidden" name="idFournisseur" value="<?php echo htmlspecialchars($fournisseur['IDFOURNISSEUR']); ?>">
                                                            <button type="submit" name="supprimer_fournisseur" class="btn btn-sm btn-delete" title="Supprimer">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="Ce fournisseur système ne peut pas être supprimé">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <a href="envoyer_sms.php?id=<?php echo $fournisseur['IDFOURNISSEUR']; ?>&sms=<?php echo urlencode($fournisseur['TelephoneFournisseur']); ?>" class="btn btn-sm btn-sms" title="Envoyer SMS">
                                                        <i class="fas fa-sms"></i>
                                                    </a>
                                                    
                                                    <a href="envoyer_email.php?id=<?php echo $fournisseur['IDFOURNISSEUR']; ?>&email=<?php echo urlencode($fournisseur['eMailFournisseur']); ?>" class="btn btn-sm btn-email" title="Envoyer Email">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                        $id++;
                                    endforeach;
                                } else { ?>
                                    <tr>
                                        <td colspan="6" class="empty-message">
                                            <i class="fas fa-inbox fa-2x mb-3"></i>
                                            <br>
                                            Aucun fournisseur enregistré pour le moment.
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Barre de recherche instantanée
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#table-body tr');
            
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Fonction pour charger les informations du fournisseur sélectionné dans le formulaire
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Récupérer les données du fournisseur
                const id = this.getAttribute('data-id');
                const nom = this.getAttribute('data-nom');
                const email = this.getAttribute('data-email');
                const telephone = this.getAttribute('data-telephone');
                
                // Remplir le formulaire avec les données du fournisseur
                document.getElementById('idFournisseur').value = id;
                document.getElementById('nomFournisseur').value = nom;
                document.getElementById('emailFournisseur').value = email;
                document.getElementById('telephoneFournisseur').value = telephone;
                
                // Changer le titre du formulaire
                document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Modifier le Fournisseur';
                
                // Masquer le bouton "Ajouter" et afficher le bouton "Modifier"
                document.getElementById('submitButton').classList.add('d-none');
                document.getElementById('updateButton').classList.remove('d-none');
                
                // Scroll vers le formulaire
                document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
            });
        });
        
        // Réinitialiser le formulaire
        document.querySelector('form').addEventListener('reset', function() {
            // Réinitialiser le titre et les boutons
            document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Ajouter un Fournisseur';
            document.getElementById('submitButton').classList.remove('d-none');
            document.getElementById('updateButton').classList.add('d-none');
        });
    </script>
</body>

</html>