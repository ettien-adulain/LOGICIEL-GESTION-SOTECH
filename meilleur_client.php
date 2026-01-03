<?php
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();
    $bestClients = [];
    $recurringClients = [];
    $creditClients = [];
    $panierMoyenClients = [];
    // Top chiffre d'affaires (excluant les clients masqués)
    $sqlBestClients = "
        SELECT 
            c.IDCLIENT, 
            c.NomPrenomClient AS nom, 
            c.telephone, 
            c.Adresse_email,
            SUM(f.PrixVenteTTC) AS total_depense
        FROM 
            client c
        JOIN 
            vente v ON c.IDCLIENT = v.IDCLIENT
        JOIN 
            facture_article fa ON v.IDFactureVente = fa.IDFactureVente
        JOIN 
            article f ON fa.IDARTICLE = f.IDARTICLE
        LEFT JOIN 
            clients_masques_meilleur_client cm ON c.IDCLIENT = cm.id_client
        WHERE 
            cm.id_client IS NULL
        GROUP BY 
            c.IDCLIENT, c.NomPrenomClient, c.telephone, c.Adresse_email
        ORDER BY 
            total_depense DESC
        LIMIT 5";
    $stmtBestClients = $cnx->prepare($sqlBestClients);
    $stmtBestClients->execute();
    $bestClients = $stmtBestClients->fetchAll(PDO::FETCH_ASSOC);

    // Top récurrents (nombre d'achats) (excluant les clients masqués)
    $sqlRecurringClients = "
        SELECT 
            c.IDCLIENT,  
            c.NomPrenomClient AS nom, 
            c.telephone, 
            c.Adresse_email,
            COUNT(*) AS nombre_achats
        FROM 
            client c
        JOIN 
            vente v ON c.IDCLIENT = v.IDCLIENT
        LEFT JOIN 
            clients_masques_meilleur_client cm ON c.IDCLIENT = cm.id_client
        WHERE 
            cm.id_client IS NULL
        GROUP BY 
            c.IDCLIENT, c.NomPrenomClient, c.telephone, c.Adresse_email
        ORDER BY 
            nombre_achats DESC
        LIMIT 5";
    $stmtRecurringClients = $cnx->prepare($sqlRecurringClients);
    $stmtRecurringClients->execute();
    $recurringClients = $stmtRecurringClients->fetchAll(PDO::FETCH_ASSOC);

    // Top crédits soldés (excluant les clients masqués)
    $sqlCreditClients = "
        SELECT 
            c.IDCLIENT, 
            c.NomPrenomClient AS nom, 
            c.telephone, 
            c.Adresse_email,
            COUNT(vc.IDVenteCredit) AS nb_credits_soldes
        FROM client c
        JOIN ventes_credit vc ON c.IDCLIENT = vc.IDCLIENT
        LEFT JOIN clients_masques_meilleur_client cm ON c.IDCLIENT = cm.id_client
        WHERE vc.statut IN ('Soldé', 'Transféré') AND cm.id_client IS NULL
        GROUP BY c.IDCLIENT, c.NomPrenomClient, c.telephone, c.Adresse_email
        ORDER BY nb_credits_soldes DESC
        LIMIT 5";
    $stmtCreditClients = $cnx->prepare($sqlCreditClients);
    $stmtCreditClients->execute();
    $creditClients = $stmtCreditClients->fetchAll(PDO::FETCH_ASSOC);

    // Top panier moyen (excluant les clients masqués)
    $sqlPanierMoyen = "
        SELECT 
            c.IDCLIENT, 
            c.NomPrenomClient AS nom, 
            c.telephone, 
            c.Adresse_email,
            AVG(v.MontantTotal) AS panier_moyen
        FROM client c
        JOIN vente v ON c.IDCLIENT = v.IDCLIENT
        LEFT JOIN clients_masques_meilleur_client cm ON c.IDCLIENT = cm.id_client
        WHERE cm.id_client IS NULL
        GROUP BY c.IDCLIENT, c.NomPrenomClient, c.telephone, c.Adresse_email
        HAVING COUNT(v.IDFactureVente) >= 2
        ORDER BY panier_moyen DESC
        LIMIT 5";
    $stmtPanierMoyen = $cnx->prepare($sqlPanierMoyen);
    $stmtPanierMoyen->execute();
    $panierMoyenClients = $stmtPanierMoyen->fetchAll(PDO::FETCH_ASSOC);

    $message = '';

    // Gestion de l'enregistrement de l'email
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $email = $_POST['email'] ?? null;

        if ($id && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $sql = "UPDATE client SET Adresse_email = :email WHERE IDCLIENT = :id";
            $stmt = $cnx->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                $message = 'Email enregistré avec succès.';
            } else {
                $message = 'Erreur lors de l\'enregistrement de l\'email.';
            }
        } else {
            $message = 'ID ou email invalide.';
        }
    }

    // Masquage d'un client de cette interface (pas de suppression réelle)
    if (isset($_POST['supprimer_client_id'])) {
        if (can_user('repertoire_client', 'supprimer')) {
            $id = $_POST['supprimer_client_id'];
            // Vérifier si le client n'est pas déjà masqué
            $checkSql = "SELECT id FROM clients_masques_meilleur_client WHERE id_client = :id";
            $checkStmt = $cnx->prepare($checkSql);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                // Masquer le client de cette interface
                $sql = "INSERT INTO clients_masques_meilleur_client (id_client) VALUES (:id)";
                $stmt = $cnx->prepare($sql);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $message = 'Client masqué de cette interface avec succès.';
            } else {
                $message = 'Ce client est déjà masqué de cette interface.';
            }
        } else {
            $message = 'Accès refusé : vous n\'avez pas l\'autorisation pour cette action.';
        }
    }

    // Restauration d'un client masqué
    if (isset($_POST['restaurer_client_id'])) {
        if (can_user('repertoire_client', 'supprimer')) {
            $id = $_POST['restaurer_client_id'];
            $sql = "DELETE FROM clients_masques_meilleur_client WHERE id_client = :id";
            $stmt = $cnx->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = 'Client restauré avec succès.';
        } else {
            $message = 'Accès refusé : vous n\'avez pas l\'autorisation pour cette action.';
        }
    }
} catch (Exception $e) {
    $message = 'Erreur lors de la récupération des clients : ' . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meilleurs Clients</title>
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
        .client-card {
            margin-bottom: 30px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .client-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.13);
            border: none;
            overflow: hidden;
        }
        .card-header-client {
            background: linear-gradient(90deg, #ff0000 60%, #ff7675 100%);
            color: #fff;
            padding: 18px 20px 10px 20px;
            font-size: 1.25rem;
            font-weight: bold;
            letter-spacing: 1px;
            border-bottom: 1px solid #fff3;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header-client .fa-trophy, .card-header-client .fa-medal {
            color: gold;
            font-size: 1.5em;
            margin-right: 8px;
        }
        .card-title {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: #222;
        }
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            justify-content: flex-end;
        }
        .card-actions .btn {
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(255,0,0,0.08);
            transition: background 0.2s, color 0.2s;
        }
        .card-actions .btn-outline-danger {
            border: 1.5px solid #ff0000;
            color: #ff0000;
            background: #fff;
        }
        .card-actions .btn-outline-danger:hover {
            background: #ff0000;
            color: #fff;
        }
        .card-actions .btn-outline-danger i {
            margin-right: 4px;
        }
        .list-group-item {
            border: none;
            background: transparent;
            padding: 0.5rem 0;
        }
        .article-list {
            margin-top: 10px;
            padding: 12px 15px 10px 15px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .article-list h6 {
            margin-bottom: 10px;
            font-size: 1.05rem;
            color: #ff0000;
            font-weight: 600;
        }
        .article-list ul {
            padding-left: 18px;
            margin-bottom: 0;
        }
        .article-list li {
            margin-bottom: 4px;
            font-size: 0.98rem;
            color: #333;
            display: flex;
            align-items: center;
        }
        .article-list li .fa-credit-card {
            color: #27ae60;
            margin-right: 6px;
        }
        .article-list li .fa-shopping-bag {
            color: #0984e3;
            margin-right: 6px;
        }
        .copy-icon {
            cursor: pointer;
            color: #007bff;
            margin-left: 10px;
            transition: transform 0.3s, color 0.3s;
            font-size: 1.2rem;
        }
        .copy-icon:hover {
            color: #0056b3;
            transform: scale(1.3);
        }
        .badge-credit {
            background: #27ae60;
            color: #fff;
            font-size: 0.85rem;
            border-radius: 8px;
            padding: 2px 8px;
            margin-left: 8px;
        }
        .total-articles {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 6px;
        }
        .toast-copied {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #222;
            color: #fff;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 1rem;
            opacity: 0;
            pointer-events: none;
            z-index: 9999;
            transition: opacity 0.4s;
        }
        .toast-copied.show {
            opacity: 1;
            pointer-events: auto;
        }
        .medal {
            font-size: 24px;
            color: gold;
        }
        .medal-container {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>

<?php include('includes/user_indicator.php'); ?>
<?php include('includes/navigation_buttons.php'); ?>      <header>
        <h1><i class="fas fa-trophy"></i> Meilleurs Clients</h1>
    </header>

    <main class="container">
        <div class="notification">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bouton d'administration pour restaurer les clients masqués -->
        <?php if (can_user('repertoire_client', 'supprimer')): ?>
        <div class="mb-4">
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#restoreClientsModal">
                <i class="fas fa-undo"></i> Restaurer les clients masqués
            </button>
        </div>

        <!-- Modal pour restaurer les clients masqués -->
        <div class="modal fade" id="restoreClientsModal" tabindex="-1" role="dialog" aria-labelledby="restoreClientsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="restoreClientsModalLabel">Restaurer les clients masqués</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Voici la liste des clients masqués de cette interface :</p>
                        <?php
                        // Récupérer les clients masqués
                        $sqlMasques = "SELECT c.IDCLIENT, c.NomPrenomClient, c.telephone, cm.date_masquage 
                                      FROM client c 
                                      JOIN clients_masques_meilleur_client cm ON c.IDCLIENT = cm.id_client 
                                      ORDER BY cm.date_masquage DESC";
                        $stmtMasques = $cnx->prepare($sqlMasques);
                        $stmtMasques->execute();
                        $clientsMasques = $stmtMasques->fetchAll(PDO::FETCH_ASSOC);
                        
                        if ($clientsMasques): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Téléphone</th>
                                            <th>Date de masquage</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientsMasques as $client): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($client['NomPrenomClient']) ?></td>
                                            <td><?= htmlspecialchars($client['telephone']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($client['date_masquage'])) ?></td>
                                            <td>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="restaurer_client_id" value="<?= htmlspecialchars($client['IDCLIENT']) ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Restaurer ce client ?')">
                                                        <i class="fas fa-undo"></i> Restaurer
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucun client masqué.</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <h2 class="mb-4" style="color:#ff0000;">🏆 Top 5 Chiffre d'Affaires</h2>
        <div class="row" id="top-chiffre">
        <?php foreach ($bestClients as $row): ?>
            <div class="col-md-4 client-card">
                <div class="card">
                    <div class="card-header-client"><i class="fas fa-trophy"></i> <?= htmlspecialchars($row['nom']) ?></div>
                    <div class="card-body">
                        <div class="mb-2" style="font-size:1.1rem;color:#222;font-weight:500;">
                            <span class="medal"><i class="fas fa-medal"></i></span> Total dépensé : <span style="color:#ff0000;font-weight:bold;"><?= number_format($row['total_depense'], 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <ul class="list-group mb-2">
                            <li class="list-group-item"><strong>Téléphone:</strong> <?= htmlspecialchars($row['telephone']) ?></li>
                            <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($row['Adresse_email']) ?></li>
                        </ul>
                        <?php if (can_user('repertoire_client', 'supprimer')): ?>
                        <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir masquer ce client de cette interface ? Le client restera dans la base de données.');">
                            <input type="hidden" name="supprimer_client_id" value="<?= htmlspecialchars($row['IDCLIENT']) ?>">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-eye-slash"></i> Masquer</button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled title="Accès refusé : vous n'avez pas l'autorisation pour cette action">
                            <i class="fas fa-ban"></i> Masquer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <h2 class="mb-4 mt-5" style="color:#ff0000;">🔁 Top 5 Clients Récurrents</h2>
        <div class="row" id="top-recurrents">
        <?php foreach ($recurringClients as $row): ?>
            <div class="col-md-4 client-card">
                <div class="card">
                    <div class="card-header-client"><i class="fas fa-redo"></i> <?= htmlspecialchars($row['nom']) ?></div>
                    <div class="card-body">
                        <div class="mb-2" style="font-size:1.1rem;color:#222;font-weight:500;">
                            <span class="badge badge-info">Nombre d'achats : <?= $row['nombre_achats'] ?></span>
                        </div>
                        <ul class="list-group mb-2">
                            <li class="list-group-item"><strong>Téléphone:</strong> <?= htmlspecialchars($row['telephone']) ?></li>
                            <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($row['Adresse_email']) ?></li>
                        </ul>
                        <?php if (can_user('repertoire_client', 'supprimer')): ?>
                        <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir masquer ce client de cette interface ? Le client restera dans la base de données.');">
                            <input type="hidden" name="supprimer_client_id" value="<?= htmlspecialchars($row['IDCLIENT']) ?>">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-eye-slash"></i> Masquer</button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled title="Accès refusé : vous n'avez pas l'autorisation pour cette action">
                            <i class="fas fa-ban"></i> Masquer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <h2 class="mb-4 mt-5" style="color:#ff0000;">💳 Top 5 Crédits Soldés</h2>
        <div class="row" id="top-credit">
        <?php foreach ($creditClients as $row): ?>
            <div class="col-md-4 client-card">
                <div class="card">
                    <div class="card-header-client"><i class="fas fa-credit-card"></i> <?= htmlspecialchars($row['nom']) ?></div>
                    <div class="card-body">
                        <div class="mb-2" style="font-size:1.1rem;color:#222;font-weight:500;">
                            <span class="badge badge-success">Crédits soldés : <?= $row['nb_credits_soldes'] ?></span>
                        </div>
                        <ul class="list-group mb-2">
                            <li class="list-group-item"><strong>Téléphone:</strong> <?= htmlspecialchars($row['telephone']) ?></li>
                            <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($row['Adresse_email']) ?></li>
                        </ul>
                        <?php if (can_user('repertoire_client', 'supprimer')): ?>
                        <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir masquer ce client de cette interface ? Le client restera dans la base de données.');">
                            <input type="hidden" name="supprimer_client_id" value="<?= htmlspecialchars($row['IDCLIENT']) ?>">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-eye-slash"></i> Masquer</button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled title="Accès refusé : vous n'avez pas l'autorisation pour cette action">
                            <i class="fas fa-ban"></i> Masquer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <h2 class="mb-4 mt-5" style="color:#ff0000;">💰 Top 5 Panier Moyen</h2>
        <div class="row" id="top-panier-moyen">
        <?php foreach ($panierMoyenClients as $row): ?>
            <div class="col-md-4 client-card">
                <div class="card">
                    <div class="card-header-client"><i class="fas fa-shopping-basket"></i> <?= htmlspecialchars($row['nom']) ?></div>
                    <div class="card-body">
                        <div class="mb-2" style="font-size:1.1rem;color:#222;font-weight:500;">
                            <span class="badge badge-warning">Panier moyen : <?= number_format($row['panier_moyen'], 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <ul class="list-group mb-2">
                            <li class="list-group-item"><strong>Téléphone:</strong> <?= htmlspecialchars($row['telephone']) ?></li>
                            <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($row['Adresse_email']) ?></li>
                        </ul>
                        <?php if (can_user('repertoire_client', 'supprimer')): ?>
                        <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir masquer ce client de cette interface ? Le client restera dans la base de données.');">
                            <input type="hidden" name="supprimer_client_id" value="<?= htmlspecialchars($row['IDCLIENT']) ?>">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-eye-slash"></i> Masquer</button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled title="Accès refusé : vous n'avez pas l'autorisation pour cette action">
                            <i class="fas fa-ban"></i> Masquer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </main>

    <div class="toast-copied" id="toastCopied">Détails copiés !</div>

    <script>
        function sendSMS(phoneNumber) {
        const countryCode = '+225';  
        const fullNumber = countryCode + phoneNumber;
        window.location.href = `envoyer_sms.php?telephone=${encodeURIComponent(fullNumber)}`;
    }

    function saveEmail(clientId) {
        const email = document.getElementById('email_' + clientId).value;

        if (email) { 
            fetch('repertoire_client.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: clientId, email: email }),
            })
            .then(response => response.json())
            .then(data => {
                const notification = document.querySelector('.notification');
                notification.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">${data.message}<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>`;
                document.getElementById('email_' + clientId).value = email; 
            })
            .catch((error) => {
                console.error('Erreur:', error);
            });
        } else {
            const notification = document.querySelector('.notification');
            notification.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">Veuillez entrer un email valide.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>`;
        }
    }
    function copyDetails(clientId) { 
    const articlesList = document.querySelector(`.article-list`).textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(articlesList).then(() => {
            // Animation toast
            const toast = document.getElementById('toastCopied');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 1500);
        }).catch(err => {
            console.error('Erreur lors de la copie dans le presse-papiers :', err);
            alert('Échec de la copie des détails.');
        });
    } else {
        alert('La fonctionnalité de presse-papiers n\'est pas supportée par ce navigateur.');
    }
}

    
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
