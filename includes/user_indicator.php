<?php
// Vérifier si l'utilisateur est connecté
if (isset($_SESSION['nom_utilisateur'])) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">';
    echo '<style>
        .user-info {
            position: fixed !important;
            top: 15px !important;
            left: 15px !important;
            z-index: 1000 !important;
            pointer-events: none !important;
            max-width: 200px !important;
        }
        
        .user-info-content {
            display: inline-flex !important;
            align-items: center !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 6px 12px !important;
            border-radius: 15px !important;
            font-size: 0.8em !important;
            font-weight: 500 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
            border: none !important;
            white-space: nowrap !important;
        }
        
        .user-info i {
            margin-right: 6px !important;
            font-size: 0.9em !important;
            color: #fff !important;
        }
        
        .user-info span {
            font-size: 0.8em !important;
            color: #fff !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .user-info {
                top: 10px !important;
                left: 10px !important;
                max-width: 150px !important;
            }
            
            .user-info-content {
                padding: 4px 8px !important;
                font-size: 0.7em !important;
            }
            
            .user-info i {
                margin-right: 4px !important;
                font-size: 0.8em !important;
            }
            
            .user-info span {
                font-size: 0.7em !important;
            }
        }
    </style>';
    
    echo '<div class="user-info">';
    echo '<div class="user-info-content">';
    echo '<i class="fas fa-user"></i>';
    echo '<span>' . htmlspecialchars($_SESSION['nom_utilisateur']) . '</span>';
    echo '</div>';
    echo '</div>';
}
?> 