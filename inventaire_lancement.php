<?php
include('db/connecting.php');
require_once 'fonction_traitement/fonction.php';
check_access();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nomInventaire = trim($_POST['nom_inventaire']);
        if (empty($nomInventaire)) {
            throw new Exception("Le nom de l'inventaire est requis");
        }

        // Vérification de la session
        if (!isset($_SESSION['nom_utilisateur']) || empty($_SESSION['nom_utilisateur'])) {
            throw new Exception("Session utilisateur invalide. Veuillez vous reconnecter.");
        }
        
        $utilisateur = $_SESSION['nom_utilisateur'];
        $date = date('Y-m-d H:i:s');

        // Vérifier qu'il n'y a pas déjà un inventaire en cours
        $stmt = $cnx->prepare("SELECT COUNT(*) FROM inventaire WHERE StatutInventaire = 'en_attente'");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Un inventaire est déjà en cours. Veuillez le finaliser avant d'en créer un nouveau.");
        }

        // Transaction
        $cnx->beginTransaction();

        // Créer l'inventaire
        $stmt = $cnx->prepare("INSERT INTO inventaire (Commentaires, DateInventaire, CreePar, StatutInventaire) 
                               VALUES (?, ?, ?, 'en_attente')");
        $stmt->execute([$nomInventaire, $date, $utilisateur]);
        $idInventaire = $cnx->lastInsertId();
        
        // Récupérer tous les articles avec stock
        $articles = $cnx->query("
            SELECT 
                a.IDARTICLE, a.CodePersoArticle, a.libelle, 
                c.nom_categorie, 
                IFNULL(s.StockActuel, 0) AS StockActuel,
                COALESCE(a.PrixAchatHT, 0) AS PrixAchatHT,
                COALESCE(a.PrixVenteTTC, 0) AS PrixVenteTTC
            FROM article a
            JOIN categorie_article c ON a.id_categorie = c.id_categorie
            LEFT JOIN stock s ON a.IDARTICLE = s.IDARTICLE
            WHERE a.desactiver = 'non'
            ORDER BY c.nom_categorie, a.libelle
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $cnx->prepare("
            INSERT INTO inventaire_ligne 
            (id_inventaire, id_article, code_article, designation, categorie, qte_theorique, valeur_theorique_achat, valeur_theorique_vente)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($articles as $art) {
            // Vérifier numéros de série disponibles
            $stmtSerie = $cnx->prepare("
                SELECT COUNT(*) as nb_series
                FROM num_serie 
                WHERE IDARTICLE = ? 
                AND statut = 'disponible' 
                AND (ID_VENTE IS NULL OR ID_VENTE = '')
                AND (IDvente_credit IS NULL OR IDvente_credit = '')
            ");
            $stmtSerie->execute([$art['IDARTICLE']]);
            $nbSeries = $stmtSerie->fetch(PDO::FETCH_ASSOC)['nb_series'];

            $qteTheorique = ($nbSeries > 0) ? $nbSeries : $art['StockActuel'];

            // Valeurs figées
            $valeurTheoriqueAchat = $qteTheorique * $art['PrixAchatHT'];
            $valeurTheoriqueVente = $qteTheorique * $art['PrixVenteTTC'];

            $stmt->execute([
                $idInventaire,
                $art['IDARTICLE'],
                $art['CodePersoArticle'],
                $art['libelle'],
                $art['nom_categorie'],
                $qteTheorique,
                $valeurTheoriqueAchat,
                $valeurTheoriqueVente
            ]);

            // Sauvegarder les séries si besoin
            if ($nbSeries > 0) {
                $stmtSeries = $cnx->prepare("
                    SELECT IDNUM_SERIE, NUMERO_SERIE 
                    FROM num_serie 
                    WHERE IDARTICLE = ? 
                    AND statut = 'disponible' 
                    AND (ID_VENTE IS NULL OR ID_VENTE = '')
                    AND (IDvente_credit IS NULL OR IDvente_credit = '')
                ");
                $stmtSeries->execute([$art['IDARTICLE']]);
                $seriesAttendues = $stmtSeries->fetchAll(PDO::FETCH_ASSOC);

                $stmtRef = $cnx->prepare("
                    INSERT INTO inventaire_series_attendues 
                    (id_inventaire, id_article, id_num_serie, numero_serie, date_lancement)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                foreach ($seriesAttendues as $serie) {
                    $stmtRef->execute([
                        $idInventaire,
                        $art['IDARTICLE'],
                        $serie['IDNUM_SERIE'],
                        $serie['NUMERO_SERIE']
                    ]);
                }
            }
        }

        // --- JOURNALISATION : Création inventaire ---
        $description_creation = sprintf(
            "Lancement inventaire '%s' - %d articles inclus - Valeur théorique calculée - Statut: en_attente",
            $nomInventaire,
            count($articles)
        );
        
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'LANCEMENT_INVENTAIRE',
                'INVENTAIRE',
                'inventaire_lancement.php',
                $description_creation,
                [
                    'id_inventaire' => $idInventaire,
                    'nom_inventaire' => $nomInventaire,
                    'utilisateur' => $utilisateur,
                    'date_lancement' => $date,
                    'articles_inclus' => count($articles),
                    'statut' => 'en_attente'
                ],
                [
                    'action' => 'lancement_inventaire',
                    'articles_prepares' => true,
                    'series_attendues_sauvegardees' => true,
                    'valeurs_theoriques_calculees' => true
                ],
                'HIGH',
                'SUCCESS',
                null
            );
        }
        // --- FIN JOURNALISATION ---

        $cnx->commit();

        // Redirection avec gestion d'erreur
        $redirect_url = "inventaire_liste.php?IDINVENTAIRE=" . $idInventaire;
        
        // Debug : Log de la redirection
        error_log("Tentative de redirection vers: " . $redirect_url);
        error_log("ID Inventaire créé: " . $idInventaire);
        
        // Vérifier si le fichier de destination existe
        if (!file_exists('inventaire_liste.php')) {
            error_log("ERREUR: Fichier inventaire_liste.php introuvable");
            throw new Exception("Fichier inventaire_liste.php introuvable");
        }
        
        // Vérifier les headers
        if (headers_sent($file, $line)) {
            error_log("ERREUR: Headers déjà envoyés depuis $file ligne $line");
            // Fallback si les headers sont déjà envoyés
            echo "<script>window.location.href = '" . $redirect_url . "';</script>";
            echo "<meta http-equiv='refresh' content='0;url=" . $redirect_url . "'>";
            exit;
        }
        
        // Redirection sécurisée
        header("Location: " . $redirect_url);
        error_log("Redirection réussie vers: " . $redirect_url);
        
        // Message de debug temporaire (à supprimer après test)
        echo "<div style='background: green; color: white; padding: 10px; margin: 10px;'>";
        echo "✅ Inventaire créé avec succès ! ID: " . $idInventaire . "<br>";
        echo "🔄 Redirection vers: " . $redirect_url . "<br>";
        echo "<a href='" . $redirect_url . "' style='color: white; text-decoration: underline;'>Cliquez ici si la redirection ne fonctionne pas</a>";
        echo "</div>";
        
        exit;

    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        
        // --- JOURNALISATION : Erreur lancement inventaire ---
        if (function_exists('logSystemAction')) {
            logSystemAction(
                $cnx,
                'ERREUR_LANCEMENT_INVENTAIRE',
                'INVENTAIRE',
                'inventaire_lancement.php',
                'Erreur lors du lancement de l\'inventaire : ' . $e->getMessage(),
                [
                    'nom_inventaire' => $_POST['nom_inventaire'] ?? '---',
                    'utilisateur' => $_SESSION['nom_utilisateur'] ?? '---',
                    'erreur' => $e->getMessage(),
                    'transaction_rollback' => true
                ],
                null,
                'HIGH',
                'FAILED',
                null
            );
        }
        // --- FIN JOURNALISATION ---
        
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Démarrer un Inventaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container { 
            max-width: 800px; 
            margin: 2rem auto; 
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        h2 { 
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: center;
        }
        .form-control { 
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
            border-color: #80bdff;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background-color: #3498db;
            border: none;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background-color: #95a5a6;
            border: none;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
            transform: translateY(-1px);
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .card-body {
            padding: 2rem;
        }
    </style>
  
</head>
<body>
    <?php include('includes/user_indicator.php'); ?>
    <?php include('includes/navigation_buttons.php'); ?>
      <!-- Système de thème sombre/clair -->
      <?php include('includes/theme_switcher.php'); ?>

<div class="container">
    <div class="card">
        <div class="card-body">
            <h2><i class="fas fa-clipboard-list me-2"></i>Démarrer un nouvel inventaire</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <div class="mb-4">
                    <label class="form-label">Nom de l'inventaire</label>
                    <input type="text" 
                           name="nom_inventaire" 
                           class="form-control" 
                           required 
                           placeholder="Ex: Inventaire mensuel - Janvier 2024"
                           pattern=".{3,100}"
                           title="Le nom doit contenir entre 3 et 100 caractères">
                    <div class="invalid-feedback">
                        Veuillez entrer un nom d'inventaire valide (3-100 caractères).
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="inventaire_liste.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play me-2"></i>Démarrer l'inventaire
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validation du formulaire
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>
</body>
</html>