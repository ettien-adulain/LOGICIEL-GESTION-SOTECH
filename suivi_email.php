<?php
try {
    include('db/connecting.php');
    session_start();
    require_once 'fonction_traitement/fonction.php';
    check_access();
} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des données';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit();
}

// Récupérer les e-mails envoyés depuis la base de données
$sql = "SELECT adresse_email, Objet, messag, attachment, date_envoi FROM emails ORDER BY date_envoi DESC";
$stmt = $cnx->prepare($sql);
$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des E-mails Envoyés</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .header-bar {
            background: #007bff;
            color: #fff;
            padding: 1.2rem 2rem 1rem 2rem;
            border-radius: 0 0 18px 18px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-bar h2 {
            margin: 0;
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .search-bar {
            width: 320px;
            max-width: 100%;
        }
        .search-bar input {
            border-radius: 8px;
            border: 1.5px solid #b3b3b3;
            padding: 0.5em 1em;
            font-size: 1em;
            width: 100%;
            transition: border 0.2s;
        }
        .search-bar input:focus {
            border: 1.5px solid #007bff;
            outline: none;
        }
        .table-responsive {
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 1.2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background: #343a40;
            color: #fff;
            font-size: 1em;
            font-weight: 600;
            border: none;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        tbody tr {
            transition: background 0.18s;
            cursor: pointer;
        }
        tbody tr:hover {
            background: #e3f0ff !important;
        }
        td, th {
            padding: 0.55rem 0.7rem;
            font-size: 0.98em;
            border: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }
        .badge-attachment {
            background: #28a745;
            color: #fff;
            font-size: 0.92em;
            border-radius: 6px;
            padding: 0.2em 0.7em;
        }
        .no-data {
            text-align: center;
            color: #888;
            font-size: 1.1em;
            padding: 2em 0;
        }
        @media (max-width: 900px) {
            .header-bar, .table-responsive { padding: 1rem; }
            td, th { font-size: 0.92em; max-width: 120px; }
        }
        @media (max-width: 600px) {
            .header-bar { flex-direction: column; gap: 1em; }
            .search-bar { width: 100%; }
            .table-responsive { padding: 0.5rem; }
            td, th { font-size: 0.85em; max-width: 60px; padding: 0.3rem 0.3rem; }
        }
    </style>
    <!-- Système de thème sombre/clair -->
    <?php include('includes/theme_switcher.php'); ?>
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
    <div class="header-bar">
        <h2><i class="fas fa-envelope-open-text mr-2"></i>Suivi des E-mails Envoyés</h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Recherche rapide (destinataire, objet, message, date, pièce jointe)">
        </div>
    </div>
    <div class="container-fluid">
        <div class="table-responsive">
            <table class="table table-hover" id="emailsTable">
                <thead>
                    <tr>
                        <th>Destinataire</th>
                        <th>Objet</th>
                        <th>Message</th>
                        <th>Date d'envoi</th>
                        <th>Pièce jointe</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($emails)): ?>
                    <tr><td colspan="5" class="no-data">Aucun e-mail envoyé pour le moment.</td></tr>
                <?php else: ?>
                    <?php foreach ($emails as $i => $email): ?>
                        <tr data-index="<?= $i ?>">
                            <td title="<?= htmlspecialchars($email['adresse_email']) ?>"><?= htmlspecialchars($email['adresse_email']) ?></td>
                            <td title="<?= htmlspecialchars($email['Objet']) ?>"><?= htmlspecialchars($email['Objet']) ?></td>
                            <td title="<?= htmlspecialchars($email['messag']) ?>">
                                <?php 
                                $msg = strip_tags($email['messag']);
                                if (mb_strlen($msg) > 40) {
                                    echo htmlspecialchars(mb_substr($msg, 0, 40)) . '... <a href="#" class="see-more" data-index="' . $i . '">Voir tout</a>';
                                } else {
                                    echo htmlspecialchars($msg);
                                }
                                ?>
                            </td>
                            <td title="<?= htmlspecialchars($email['date_envoi']) ?>">
                                <?= date('d/m/Y H:i', strtotime($email['date_envoi'])) ?>
                            </td>
                            <td>
                            <?php if (!empty($email['attachment'])): ?>
    <a href="Image_article/<?= htmlspecialchars($email['attachment']) ?>" class="badge badge-attachment"><i class="fas fa-paperclip"></i> <?= htmlspecialchars($email['attachment']) ?></a>
<?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal pour afficher le message complet -->
    <div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="messageModalLabel"><i class="fas fa-envelope"></i> Message complet</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" id="modalMessageContent" style="white-space: pre-line;"></div>
        </div>
      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    // Recherche globale instantanée
    $("#searchInput").on("input", function() {
        const val = $(this).val().toLowerCase();
        $("#emailsTable tbody tr").each(function() {
            let show = false;
            $(this).find("td").each(function() {
                if ($(this).text().toLowerCase().indexOf(val) !== -1) show = true;
            });
            $(this).toggle(show);
        });
    });
    // Voir tout le message dans un modal
    $(document).on('click', '.see-more', function(e) {
        e.preventDefault();
        const idx = $(this).data('index');
        const msg = <?= json_encode(array_map(function($e){return strip_tags($e['messag']);}, $emails)); ?>[idx];
        $("#modalMessageContent").text(msg);
        $('#messageModal').modal('show');
    });
    // Ligne survolée = sélectionnée
    $("#emailsTable tbody tr").on('click', function() {
        $("#emailsTable tbody tr").removeClass('table-primary');
        $(this).addClass('table-primary');
    });
    </script>
</body>
</html>
