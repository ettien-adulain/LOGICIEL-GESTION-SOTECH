<?php    
try {
    include('db/connecting.php');

    require_once 'fonction_traitement/fonction.php';
    check_access();

    // Suppression de tous les clients si demandé
    if (isset($_POST['vider_clients'])) {
        if (can_user('repertoire_client', 'supprimer')) {
            $sql = "DELETE FROM client";
            $cnx->exec($sql);
            $message = 'Tous les clients ont été supprimés.';
        } else {
            $message = 'Accès refusé : vous n\'avez pas l\'autorisation pour cette action.';
        }
    }

    // Suppression individuelle d'un client
    if (isset($_POST['supprimer_client_id'])) {
        if (can_user('repertoire_client', 'supprimer')) {
            $id = $_POST['supprimer_client_id'];
            $sql = "DELETE FROM client WHERE IDCLIENT = :id";
            $stmt = $cnx->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = 'Client supprimé avec succès.';
        } else {
            $message = 'Accès refusé : vous n\'avez pas l\'autorisation pour cette action.';
        }
    }

    $filterOption = $_POST['filterOption'] ?? 'all'; 
    $clients = [];
    $message = '';

    // Filtrage des clients
    if ($filterOption === 'today') {
        $today = date('Y-m-d');
        $sql = "SELECT IDCLIENT, NomPrenomClient AS nom, telephone, Adresse_email 
                FROM client 
                WHERE DATE(DateIns) = :today";
        $stmt = $cnx->prepare($sql);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else { // "all"
        $sql = "SELECT IDCLIENT, NomPrenomClient AS nom, telephone, Adresse_email FROM client";
        $stmt = $cnx->query($sql);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (isset($_POST['actionGroup'])) {
        $actionGroup = $_POST['actionGroup'];
    
        if ($actionGroup === 'sms') {
            // Récupérer tous les numéros de téléphone
            $sql = "SELECT telephone FROM client";
            $stmt = $cnx->query($sql);
            $numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
            // Redirection vers la page d'envoi de SMS avec les numéros
            header('Location: envoyer_sms.php?numbers=' . urlencode(implode(',', $numbers)));
            exit();
        } elseif ($actionGroup === 'email') {
            // Récupérer tous les e-mails
            $sql = "SELECT Adresse_email FROM client WHERE Adresse_email IS NOT NULL";
            $stmt = $cnx->query($sql);
            $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
            // Redirection vers la page d'envoi d'e-mail avec les adresses
            header('Location: envoyer_email.php?emails=' . urlencode(implode(',', $emails)));
            exit();
        }
    }

    // Filtrage par date
    $startDate = $_POST['startDate'] ?? null;
    $endDate = $_POST['endDate'] ?? null;
    $searchTerm = $_POST['searchTerm'] ?? null;

    if ($startDate && $endDate) {
        $sql = "SELECT IDCLIENT, NomPrenomClient AS nom, telephone, Adresse_email 
                FROM client 
                WHERE DateIns BETWEEN :startDate AND :endDate";
        $stmt = $cnx->prepare($sql);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($searchTerm) {
        $sql = "SELECT IDCLIENT, NomPrenomClient AS nom, telephone, Adresse_email 
                FROM client 
                WHERE NomPrenomClient LIKE :searchTerm OR telephone LIKE :searchTerm";
        $stmt = $cnx->prepare($sql);
        $searchTerm = '%' . $searchTerm . '%';
        $stmt->bindParam(':searchTerm', $searchTerm);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Gestion de l'enregistrement de l'email
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $email = $data['email'] ?? null;

    if ($id && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql = "UPDATE client SET Adresse_email = :email WHERE IDCLIENT = :id";
        $stmt = $cnx->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $message = 'Email enregistré avec succès.';
    } else {
        $message = 'ID ou email invalide.';
    }

    // Récupérer le nombre total de clients
    $sqlTotalClients = "SELECT COUNT(*) AS total_clients FROM client";
    $stmtTotalClients = $cnx->prepare($sqlTotalClients);
    $stmtTotalClients->execute();
    $totalClients = $stmtTotalClients->fetch(PDO::FETCH_ASSOC)['total_clients'];

} catch (Exception $e) {
    $message = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Répertoire Client</title>
    <!-- CSS Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        header {
            background: linear-gradient(90deg, #ff0000 0%, #cc0000 100%);
            color: #fff;
            padding: 32px 0 18px 0;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.13);
            border-radius: 0 0 24px 24px;
            margin-bottom: 28px;
            position: relative;
        }
        header h1 {
            font-size: 2.3rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }
        header .header-sub {
            font-size: 1.08rem;
            font-weight: 400;
            color: #fff9;
            margin-top: 6px;
            letter-spacing: 0.5px;
        }
        .header-icon {
            font-size: 2.1rem;
            color: #fff;
            margin-right: 10px;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            margin-top: 20px;
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
        .total-clients {
            background-color: #007bff;
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
<?php include('includes/user_indicator.php'); ?>
<?php include('includes/navigation_buttons.php'); ?>  
  <header>
        <h1><span class="header-icon"><i class="fas fa-address-book"></i></span>Répertoire Client</h1>
        <div class="header-sub">Gestion centralisée et professionnelle de vos clients</div>
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
        <!-- Bouton pour vider la liste des clients avec protection avancée -->
        <?php if (can_user('repertoire_client', 'supprimer')): ?>
        <button type="button" class="btn btn-danger btn-lg mb-4" data-toggle="modal" data-target="#confirmViderClientsModal">
            <i class="fas fa-trash"></i> Vider la liste des clients
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-secondary btn-lg mb-4" disabled title="Accès refusé : vous n'avez pas l'autorisation pour cette action">
            <i class="fas fa-ban"></i> Vider la liste des clients
        </button>
        <?php endif; ?>
        <!-- Modal de confirmation avancée -->
        <div class="modal fade" id="confirmViderClientsModal" tabindex="-1" role="dialog" aria-labelledby="confirmViderClientsModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
              <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmViderClientsModalLabel">Suppression critique !</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <p><strong>Attention :</strong> Supprimer tous les clients va aussi supprimer toutes les ventes associées. Cette action est <span class="text-danger">irréversible</span> et peut entraîner une perte définitive de l'historique de facturation.</p>
                <p>Pour confirmer, tapez <b>SUPPRIMER</b> dans le champ ci-dessous :</p>
                <input type="text" id="confirmDeleteInput" class="form-control" placeholder="Tapez SUPPRIMER pour valider">
              </div>
              <div class="modal-footer">
                <form method="POST" action="" id="viderClientsForm">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                  <button type="submit" name="vider_clients" class="btn btn-danger" id="confirmDeleteBtn" disabled>Vider la liste des clients</button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <!-- Filtres et actions regroupés dans une carte professionnelle -->
        <div class="card mb-4 p-4" style="border-radius:18px; box-shadow:0 2px 12px rgba(0,0,0,0.07); background:#fff;">
            <div class="row align-items-end g-3">
                <div class="col-md-3 mb-3 mb-md-0">
                    <label for="actionGroup" class="form-label fw-bold"><i class="fas fa-tasks"></i> Action Groupée</label>
                    <form method="POST" action="" id="actionGroupForm">
                        <select name="actionGroup" id="actionGroup" class="form-control" onchange="document.getElementById('actionGroupForm').submit()">
                            <option value="">Sélectionner une action</option>
                            <option value="sms">SMS Groupe</option>
                            <option value="email">E-mail Groupe</option>
                        </select>
                    </form>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <label for="filterOption" class="form-label fw-bold"><i class="fas fa-filter"></i> Filtrer par</label>
                    <form method="POST" action="" id="filterOptionForm">
                        <select name="filterOption" id="filterOption" class="form-control" onchange="document.getElementById('filterOptionForm').submit()">
                            <option value="all" <?= ($filterOption === 'all') ? 'selected' : '' ?>>Tous les Clients</option>
                            <option value="today" <?= ($filterOption === 'today') ? 'selected' : '' ?>>Clients enregistrés aujourd'hui</option>
                        </select>
                    </form>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="form-label fw-bold"><i class="fas fa-medal"></i> Distinctions</label>
                    <div class="medal-container">
                        <a href="meilleur_client.php" class="btn btn-outline-danger w-100"><i class="fas fa-medal"></i> Médailles d'Honneur</a>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <label for="search" class="form-label fw-bold"><i class="fas fa-search"></i> Rechercher</label>
                    <form method="POST" action="" id="searchForm">
                        <input type="text" name="searchTerm" id="search" class="form-control" placeholder="Nom ou Numéro de Téléphone">
                    </form>
                </div>
            </div>
            <div class="row mt-3 g-3">
                <div class="col-md-5">
                    <label for="startDate" class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Date de début</label>
                    <input type="date" name="startDate" id="startDate" class="form-control">
                </div>
                <div class="col-md-5">
                    <label for="endDate" class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Date de fin</label>
                    <input type="date" name="endDate" id="endDate" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" form="searchForm" class="btn btn-outline-danger w-100">Rechercher</button>
                </div>
            </div>
        </div>

        <div class="row" id="client-cards">
        <?php 
        if (count($clients) > 0) {
            // Éviter les doublons de clients par ID et par numéro de téléphone
            $uniqueClients = [];
            $uniquePhones = [];
            foreach ($clients as $row) {
                $phoneKey = preg_replace('/\D/', '', $row['telephone'] ?? ''); // Nettoyage du numéro
                if (isset($uniqueClients[$row['IDCLIENT']]) || (isset($uniquePhones[$phoneKey]) && $phoneKey !== '')) continue;
                $uniqueClients[$row['IDCLIENT']] = true;
                if ($phoneKey !== '') $uniquePhones[$phoneKey] = true;
                // Compter les articles
                $sqlArticles = "
                    SELECT 
                        article.libelle AS nom_article, 
                        article.descriptif, 
                        article.PrixVenteTTC 
                    FROM article 
                    LEFT JOIN facture_article ON article.IDARTICLE = facture_article.IDARTICLE 
                    LEFT JOIN vente ON facture_article.IDFactureVente = vente.IDFactureVente 
                    WHERE vente.IDCLIENT = :idClient";
                $stmtArticles = $cnx->prepare($sqlArticles);
                $stmtArticles->bindParam(':idClient', $row['IDCLIENT']);
                $stmtArticles->execute();
                $articles = $stmtArticles->fetchAll(PDO::FETCH_ASSOC);

                $sqlArticlesCredit = "
                    SELECT 
                        article.libelle AS nom_article, 
                        article.descriptif, 
                        article.PrixVenteTTC, 
                        vcl.QuantiteVendue,
                        vc.NumeroVente AS numero_credit
                    FROM article 
                    LEFT JOIN ventes_credit_ligne vcl ON article.IDARTICLE = vcl.IDARTICLE 
                    LEFT JOIN ventes_credit vc ON vcl.IDVenteCredit = vc.IDVenteCredit 
                    WHERE vc.IDCLIENT = :idClientCredit";
                $stmtArticlesCredit = $cnx->prepare($sqlArticlesCredit);
                $stmtArticlesCredit->bindParam(':idClientCredit', $row['IDCLIENT']);
                $stmtArticlesCredit->execute();
                $articlesCredit = $stmtArticlesCredit->fetchAll(PDO::FETCH_ASSOC);

                $totalArticles = count($articles) + count($articlesCredit);
                $totalCredit = count($articlesCredit);
                echo '
                <div class="col-md-4 client-card">
                    <div class="card">
                        <div class="card-header-client">
                            <i class="fas fa-user"></i> ' . htmlspecialchars($row["nom"] ?? 'Inconnu') . '
                            <span class="total-articles">(' . $totalArticles . ' article' . ($totalArticles > 1 ? 's' : '') . ')</span>' . ($totalCredit > 0 ? '<span class="badge-credit"><i class="fas fa-credit-card"></i> Crédit</span>' : '') . '
                        </div>
                        <div class="card-body">
                            <ul class="list-group mb-2">
                                <li class="list-group-item"><strong>Téléphone:</strong> ' . htmlspecialchars($row["telephone"] ?? 'Non renseigné') . '</li>
                                <li class="list-group-item"><strong>Email:</strong>
                                    <input type="email" id="email_' . $row['IDCLIENT'] . '" class="form-control" placeholder="Email" value="' . htmlspecialchars($row["Adresse_email"] ?? '') . '">
                                </li>
                                <li class="list-group-item">
                                    <div class="card-actions">
                                        <button class="btn btn-outline-danger" onclick="saveEmail(' . $row['IDCLIENT'] . ')">
                                            <i class="fas fa-save"></i> Enregistrer Email
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="sendSMS(\'' . htmlspecialchars($row['telephone']) . '\')">
                                            <i class="fas fa-sms"></i> SMS
                                        </button>
                                        <a href="envoyer_email.php?id=' . $row['IDCLIENT'] . '&email=' . ($row['Adresse_email'] ? urlencode($row['Adresse_email']) : '') . '" class="btn btn-outline-danger">
                                            <i class="fas fa-envelope"></i> Email
                                        </a>
                                        ' . (can_user('repertoire_client', 'supprimer') ? '
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible.\');">
                                            <input type="hidden" name="supprimer_client_id" value="' . htmlspecialchars($row['IDCLIENT']) . '">
                                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Supprimer</button>
                                        </form>' : '
                                        <button type="button" class="btn btn-secondary" disabled title="Accès refusé : vous n\'avez pas l\'autorisation pour cette action">
                                            <i class="fas fa-ban"></i> Supprimer
                                        </button>') . '
                                    </div>
                                </li>
                            </ul>
                            <div class="article-list">';
                            echo '<span class="copy-icon" onclick="copyDetails(' . $row['IDCLIENT'] . ') ">
                            <i class="fas fa-copy" ></i>
                        </span>';
                if ($totalArticles > 0) {
                    echo '<h6>Articles Achetés:</h6><ul>';
                    foreach ($articles as $article) {
                        echo '<li><i class="fas fa-shopping-bag"></i> ' . htmlspecialchars($article['nom_article']) . ' - ' . htmlspecialchars($article['descriptif']) . ' - ' . htmlspecialchars($article['PrixVenteTTC']) . ' FCFA</li>';
                    }
                    foreach ($articlesCredit as $article) {
                        echo '<li><i class="fas fa-credit-card"></i> ' . htmlspecialchars($article['nom_article']) . ' - ' . htmlspecialchars($article['descriptif']) . ' - ' . htmlspecialchars($article['PrixVenteTTC']) . ' FCFA (Crédit N°: ' . htmlspecialchars($article['numero_credit']) . ')</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<h6>Aucun article acheté.</h6>';
                }
                echo '
                            </div>
                        </div>
                    </div>
                </div>';
            }
        } else {
            echo '<p>Aucun client trouvé.</p>';
        }
        ?>
        </div>

         <!-- Carré pour afficher le nombre total de clients -->
    <div class="total-clients">
        <h5>Nombre Total de Clients</h5>
        <p><?= htmlspecialchars($totalClients) ?></p>
    </div>
    </div>
    
    <div class="toast-copied" id="toastCopied">Détails copiés !</div>
    
    <script>
    function sendSMS(phoneNumber) {
        // Nettoyer le numéro de téléphone
        let cleanNumber = phoneNumber.replace(/\D/g, ''); // Supprimer tous les caractères non numériques
        let fullNumber;
        
        // Vérifier si le numéro commence déjà par 225
        if (cleanNumber.startsWith('225')) {
            // Le numéro a déjà le préfixe pays, l'utiliser tel quel avec +
            fullNumber = '+' + cleanNumber;
        } else {
            // Le numéro n'a pas le préfixe pays, l'ajouter
            const countryCode = '+225';
            fullNumber = countryCode + cleanNumber;
        }
        
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
    function confirmViderClients() {
        return confirm("Êtes-vous VRAIMENT sûr de vouloir supprimer TOUS les clients ? Cette action est définitive et irréversible.");
    }
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Protection avancée sur la suppression de tous les clients -->
    <script>
    const confirmInput = document.getElementById('confirmDeleteInput');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (confirmInput && confirmBtn) {
        confirmInput.addEventListener('input', function() {
            confirmBtn.disabled = (this.value !== 'SUPPRIMER');
        });
        // Réinitialise le champ à l'ouverture de la modale
        document.getElementById('confirmViderClientsModal').addEventListener('show.bs.modal', function() {
            confirmInput.value = '';
            confirmBtn.disabled = true;
        });
    }
    </script>
</body>
</html>
