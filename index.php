<?php
require_once 'fonction_traitement/fonction.php';

try {
    include('db/connecting.php');
  
require_once 'fonction_traitement/fonction.php';
check_access(); // Protection automatique selon $DROITS_PAGES

} catch (\Throwable $th) {
    $erreur = 'Erreur lors de la récupération des ' . $tableName;
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $referer . '?error=' . urlencode($erreur));
    exit(); 
}// OPTIMISATION : Cache des alertes de stock (5 minutes)
$cache_file = 'cache/stock_alerts.cache';
$cache_duration = 300; // 5 minutes

$articles = [];

// Vérifier le cache
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
    $articles = json_decode(file_get_contents($cache_file), true);
} else {
    // Définir le seuil de stock  
    $seuil_de_stock = 2;
    
    // Requête SQL optimisée avec LIMIT pour éviter de charger trop de données
    $stmt = $cnx->prepare("SELECT a.libelle, s.StockActuel
                            FROM article a
                            JOIN stock s ON a.IDARTICLE = s.IDARTICLE
                            WHERE s.StockActuel <= ?
                            ORDER BY s.StockActuel ASC
                            LIMIT 20"); // Limiter à 20 articles max
    
    $stmt->execute([$seuil_de_stock]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer le dossier cache s'il n'existe pas
    if (!is_dir('cache')) {
        mkdir('cache', 0755, true);
    }
    
    // Sauvegarder dans le cache
    file_put_contents($cache_file, json_encode($articles));
}

// Affichage des alertes de stock uniquement si le seuil est atteint
if ($articles) {
    echo '<div class="alert alert-warning" style="overflow: hidden; white-space: nowrap;">';
    echo '<marquee style="color: red; animation: blink 1s infinite;">';
    foreach ($articles as $article) {
        echo htmlspecialchars($article['libelle']) . ' - Quantité restante : ' . htmlspecialchars($article['StockActuel']) . ' &nbsp;&nbsp;&nbsp;';
    }
    echo '</marquee>';
    echo '</div>';
} 

?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SO-TECH - Système de Gestion</title>
    <!-- CSS Optimisé -->
    <link rel="stylesheet" href="css/index-optimized.css">
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- Lien vers les icônes FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- Lien vers les bootstraps -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="/favicon.png">
    
    <!-- Google Fonts - Optimisé -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS inline supprimé - maintenant dans css/index-optimized.css -->
    
    <!-- CSS pour le crédit entreprise -->
    <style>
        .company-credit {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            animation: fadeInUp 1s ease-out 2s both;
        }
        
        .credit-circle {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .credit-circle:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.4);
        }
        
        .credit-content {
            text-align: center;
            color: white;
            font-family: 'Inter', sans-serif;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .company-logo {
            width: 90px;
            height: 90px;
            margin-bottom: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .logo-img:hover {
            transform: scale(1.05);
        }
        
        .credit-title {
            font-size: 10px;
            font-weight: 400;
            opacity: 0.9;
            line-height: 1;
            margin-bottom: 3px;
        }
        
        .credit-company {
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .company-credit {
                bottom: 15px;
                right: 15px;
            }
            
            .credit-circle {
                width: 110px;
                height: 110px;
            }
            
            .company-logo {
                width: 75px;
                height: 75px;
            }
            
            .logo-img {
                width: 65px;
                height: 65px;
            }
            
            .credit-title {
                font-size: 9px;
            }
            
            .credit-company {
                font-size: 11px;
            }
        }
        
        /* Animation de pulsation subtile */
        .credit-circle {
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            }
            50% {
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            }
        }
    </style>
</head>
<body id="index">
    <!-- Slider d'images de fond -->
    <div class="background-slider">
        <div class="background-slide bg-slide-1 active"></div>
        <div class="background-slide bg-slide-2"></div>
        <div class="background-slide bg-slide-3"></div>
        <div class="background-slide bg-slide-4"></div>
        <div class="background-slide bg-slide-5"></div>
        <div class="background-slide bg-slide-6"></div>
    </div>
    
    <!-- Indicateurs du slider -->
    <div class="slider-indicators">
        <div class="indicator active" data-slide="0"></div>
        <div class="indicator" data-slide="1"></div>
        <div class="indicator" data-slide="2"></div>
        <div class="indicator" data-slide="3"></div>
        <div class="indicator" data-slide="4"></div>
        <div class="indicator" data-slide="5"></div>
    </div>
    
    
    
    <!-- Overlay pour améliorer la lisibilité -->
    <div class="background-overlay"></div>
    
    <!-- Barre de chargement -->
    <div class="loader-wrapper" id="loader">
        <div class="loader">
            <div class="logo" aria-label="Logo SO-TECH"></div>
        </div>
    </div>

    <!-- Header moderne -->
    <header class="modern-header">
        <div class="header-content">
           
            
            <div class="date-section">
        <?PHP
            setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra');
            date_default_timezone_set('Africa/Abidjan');
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
                echo '<div class="current-date"><i class="fas fa-calendar-alt me-2"></i>' . $fmt->format(new DateTime()) . '</div>';
                ?>
            </div>
            
            <div class="logout-section">
                <?php include('includes/user_indicator.php'); ?>
            <form action="fonction_traitement/request.php" method="post" style="display:inline;">
                    <button type="submit" name="deconnexion_admin" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                    </button>
            </form>
            </div>
        </div>
    </header>
    
    <?php include('includes/theme_switcher.php'); ?>
    <main>
        <div class="main-container">
            <!-- Section de bienvenue -->
            <div class="welcome-section">
                <h1 class="welcome-title">Bienvenue sur SO-TECH</h1>
                <p class="welcome-subtitle">Système de gestion intégré pour votre entreprise</p>
            </div>

            <!-- Messages d'alerte -->
            <?php
                if (isset($_GET['success'])) {
                    $successMessage = htmlspecialchars($_GET['success']);
                echo '<div id="success-alert" class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>' . $successMessage . '
                      </div>';
                }
                if (isset($_GET['error'])) {
                    $errorMessage = htmlspecialchars($_GET['error']);
                echo '<div id="error-alert" class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>' . $errorMessage . '
                      </div>';
            }
            ?>

            <!-- Grille des modules -->
            <div class="modules-grid">
                <a href="articles.php" class="module-card article">
                    <div class="module-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h3 class="module-title">Articles</h3>
                    <p class="module-description">Gestion des produits et catalogues</p>
                    </a>

                <a href="vente.php" class="module-card vente">
                    <div class="module-icon">
                        <i class="fas fa-shopping-cart"></i>
                </div>
                    <h3 class="module-title">Vente</h3>
                    <p class="module-description">Point de vente et transactions</p>
                </a>

                <a href="commande.php" class="module-card commande">
                    <div class="module-icon">
                        <i class="fas fa-shopping-basket"></i>
                </div>
                    <h3 class="module-title">Commandes</h3>
                    <p class="module-description">Gestion des commandes clients</p>
                </a>

                <a href="parametre.php" class="module-card parametre">
                    <div class="module-icon">
                        <i class="fas fa-cogs"></i>
                </div>
                    <h3 class="module-title">Paramètres</h3>
                    <p class="module-description">Configuration du système</p>
                </a>

                <a href="inventaire_liste.php" class="module-card inventaire">
                    <div class="module-icon">
                        <i class="fas fa-boxes"></i>
                </div>
                    <h3 class="module-title">Inventaire</h3>
                    <p class="module-description">Contrôle et suivi des stocks</p>
                </a>

                <a href="sav.php" class="module-card facture">
                    <div class="module-icon">
                        <i class="fas fa-tools"></i>
                </div>
                    <h3 class="module-title">SAV</h3>
                    <p class="module-description">Service après-vente</p>
                </a>

                <a href="menu_chiffre_daffaire.php" class="module-card ca">
                    <div class="module-icon">
                        <i class="fas fa-chart-line"></i>
                </div>
                    <h3 class="module-title">Chiffre d'Affaires</h3>
                    <p class="module-description">Analyses et rapports financiers</p>
                </a>

                <a href="utilisateur.php" class="module-card utilisateur">
                    <div class="module-icon">
                        <i class="fas fa-users"></i>
                </div>
                    <h3 class="module-title">Utilisateurs</h3>
                    <p class="module-description">Gestion des comptes utilisateurs</p>
                </a>

                <a href="menu_entree_stock.php" class="module-card gestion-stock">
                    <div class="module-icon">
                        <i class="fas fa-warehouse"></i>
                </div>
                    <h3 class="module-title">Gestion Stock</h3>
                    <p class="module-description">Entrées et sorties de stock</p>
                </a>

                <a href="comptabilite.php" class="module-card comptabilite">
                    <div class="module-icon">
                        <i class="fas fa-calculator"></i>
                </div>
                    <h3 class="module-title">Comptabilité</h3>
                    <p class="module-description">Suivi comptable et financier</p>
                </a>

                <a href="sms.php" class="module-card sms">
                    <div class="module-icon">
                        <i class="fas fa-sms"></i>
                </div>
                    <h3 class="module-title">SMS</h3>
                    <p class="module-description">Communication par SMS</p>
                </a>

                <a href="e_mail.php" class="module-card email">
                    <div class="module-icon">
                        <i class="fas fa-envelope"></i>
                </div>
                    <h3 class="module-title">Email</h3>
                    <p class="module-description">Communication par email</p>
                </a>
            </div>
        </div>
    </main>

    <!-- Crédit entreprise en bas à droite -->
    <div class="company-credit">
            <div class="credit-content">
                <!-- Logo de l'entreprise -->
                <div class="company-logo">
                    <img src="bbe-dev-logo.jpg" alt="B.B.E Dev Logo" class="logo-img" onerror="this.style.display='none';">
                </div>
                <div class="credit-title">Créé par</div>
                <div class="credit-company">BBE Dev</div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Gestion des alertes
        setTimeout(function() {
            var errorAlert = document.getElementById('error-alert');
            var successAlert = document.getElementById('success-alert');
            
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }
            if (successAlert) {
                successAlert.style.display = 'none';
            }

            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                url.searchParams.delete('success');
                window.history.replaceState(null, null, url);
            }
        }, 5000);

        // Chargement de la page - OPTIMISÉ
        window.addEventListener('load', () => {
            // Réduire le temps d'attente
            setTimeout(() => {
                document.getElementById('loader').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('loader').style.display = 'none';
                }, 300); // Réduit de 500ms à 300ms
            }, 500); // Réduit de 1000ms à 500ms
        });
        
        // Chargement immédiat si la page est déjà chargée
        if (document.readyState === 'complete') {
            document.getElementById('loader').style.display = 'none';
        }

        // Animation d'entrée des cartes - VERSION OPTIMISÉE
        function animateCards() {
            const cards = document.querySelectorAll('.module-card');
            
            // Utiliser Intersection Observer pour les animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)'; /* Réduit de 50px à 30px */
                card.style.transition = 'opacity 0.4s ease, transform 0.4s ease'; /* Simplifié */
                observer.observe(card);
            });
        }

        // Slider d'images en arrière-plan - VERSION ULTRA-OPTIMISÉE
        function initBackgroundSlider() {
            const slides = document.querySelectorAll('.background-slide');
            const indicators = document.querySelectorAll('.indicator');
            let currentSlide = 0;
            let sliderInterval;
            let isInitialized = false;
            
            function showSlide(slideIndex) {
                slides[currentSlide].classList.remove('active');
                indicators[currentSlide].classList.remove('active');
                
                currentSlide = slideIndex;
                
                slides[currentSlide].classList.add('active');
                indicators[currentSlide].classList.add('active');
            }
            
            function showNextSlide() {
                const nextSlide = (currentSlide + 1) % slides.length;
                showSlide(nextSlide);
            }
            
            // Chargement progressif et non-bloquant
            function preloadImages() {
                const imageUrls = ['fond1.jpg', 'fond2.jpg', 'fond3.jpg', 'fond4.jpg', 'fond5.jpg', 'fond6.jpg'];
                let loadedCount = 0;
                let hasStarted = false;
                
                // Démarrer le slider immédiatement avec la première image
                if (!hasStarted) {
                    hasStarted = true;
                    sliderInterval = setInterval(showNextSlide, 6000); // Réduit à 6 secondes
                }
                
                // Charger les images en arrière-plan
                imageUrls.forEach((url, index) => {
                    const img = new Image();
                    img.onload = () => {
                        loadedCount++;
                        // Pas besoin d'attendre toutes les images
                    };
                    img.onerror = () => {
                        console.warn('Image non chargée:', url);
                    };
                    img.src = url;
                });
            }
            
            // Démarrer immédiatement
            preloadImages();
            
            // Gestion des clics sur les indicateurs (optimisée)
            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    // Arrêter l'intervalle automatique
                    if (sliderInterval) {
                        clearInterval(sliderInterval);
                    }
                    showSlide(index);
                    // Redémarrer l'intervalle après 3 secondes
                    setTimeout(() => {
                        sliderInterval = setInterval(showNextSlide, 8000);
                    }, 3000);
                });
            });
            
            // Optimisation : arrêter le slider quand la page n'est pas visible
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (sliderInterval) clearInterval(sliderInterval);
                } else {
                    sliderInterval = setInterval(showNextSlide, 8000);
                }
            });
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            animateCards();
            initBackgroundSlider();
        });
    </script>
</body>
</html>
