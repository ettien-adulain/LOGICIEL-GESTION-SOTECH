<?php
// Version corrigée avec gestion d'erreur robuste
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Vérifier les fichiers critiques avant de les inclure
    if (!file_exists('db/connecting.php')) {
        throw new Exception('Fichier db/connecting.php manquant');
    }
    
    if (!file_exists('fonction_traitement/fonction.php')) {
        throw new Exception('Fichier fonction_traitement/fonction.php manquant');
    }
    
    include('db/connecting.php');
    require_once 'fonction_traitement/fonction.php';
    
    // Vérifier si integrate_journal_global.php existe avant de l'inclure
    if (file_exists('integrate_journal_global.php')) {
        require_once 'integrate_journal_global.php';
    }
    
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
            
            // Récupérer les infos du client avant suppression
            $stmt = $cnx->prepare("SELECT NomPrenomClient, Telephone FROM client WHERE IDCLIENT = ?");
            $stmt->execute([$id]);
            $client_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $sql = "DELETE FROM client WHERE IDCLIENT = :id";
            $stmt = $cnx->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Journaliser la suppression du client si la fonction existe
            if ($client_info && function_exists('journaliserClient')) {
                journaliserClient($cnx, $id, 'SUPPRESSION', "Client supprimé - Nom: {$client_info['NomPrenomClient']} - Tél: {$client_info['Telephone']}");
            }
            
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
    $clients = [];
    $totalClients = 0;
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
        }
    </style>
</head>
<body>
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

        <!-- Filtres -->
        <div class="card mb-4 p-4">
            <div class="row align-items-end g-3">
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
                    <label for="search" class="form-label fw-bold"><i class="fas fa-search"></i> Rechercher</label>
                    <form method="POST" action="" id="searchForm">
                        <input type="text" name="searchTerm" id="search" class="form-control" placeholder="Nom ou Numéro de Téléphone">
                    </form>
                </div>
            </div>
        </div>

        <div class="row" id="client-cards">
        <?php 
        if (count($clients) > 0) {
            foreach ($clients as $row) {
                echo '
                <div class="col-md-4 client-card">
                    <div class="card">
                        <div class="card-header-client">
                            <i class="fas fa-user"></i> ' . htmlspecialchars($row["nom"] ?? 'Inconnu') . '
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
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>';
            }
        } else {
            echo '<div class="col-12"><p class="text-center">Aucun client trouvé.</p></div>';
        }
        ?>
        </div>

        <!-- Nombre total de clients -->
        <div class="text-center mt-4">
            <div class="badge bg-primary fs-5">
                Nombre Total de Clients: <?= htmlspecialchars($totalClients) ?>
            </div>
        </div>
    </main>
    
    <script>
    function sendSMS(phoneNumber) {
        let cleanNumber = phoneNumber.replace(/\D/g, '');
        let fullNumber;
        
        if (cleanNumber.startsWith('225')) {
            fullNumber = '+' + cleanNumber;
        } else {
            const countryCode = '+225';
            fullNumber = countryCode + cleanNumber;
        }
        
        window.location.href = `envoyer_sms.php?telephone=${encodeURIComponent(fullNumber)}`;
    }

    function saveEmail(clientId) {
        const email = document.getElementById('email_' + clientId).value;

        if (email) { 
            fetch('repertoire_client_fixed.php', {
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
            })
            .catch((error) => {
                console.error('Erreur:', error);
            });
        } else {
            const notification = document.querySelector('.notification');
            notification.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">Veuillez entrer un email valide.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>`;
        }
    }
    </script>
</body>
</html>
