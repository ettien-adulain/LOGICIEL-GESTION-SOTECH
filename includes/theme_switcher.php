<?php
// Système de thème sombre uniquement
?>
<style>
/* Mode sombre uniquement */
[data-theme="dark"] {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --text-primary: #ffffff;
    --text-secondary: #cccccc;
    --border-color: #404040;
    --card-bg: #2d2d2d;
    --menu-bg: #2d2d2d;
    --header-bg: #1a1a1a;
    --shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    --nav-btn-home: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    --nav-btn-back: linear-gradient(135deg, #718096 0%, #4a5568 100%);
    --user-info-bg: #2d2d2d;
    --user-info-text: #ffffff;
    --user-info-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
}

/* Application des variables CSS uniquement en mode sombre */
[data-theme="dark"] body {
    background-color: var(--bg-primary) !important;
    color: var(--text-primary) !important;
    transition: background-color 0.3s ease, color 0.3s ease;
}

[data-theme="dark"] .container, 
[data-theme="dark"] .container-fluid {
    background-color: var(--bg-primary) !important;
}

[data-theme="dark"] .card, 
[data-theme="dark"] .menu-item {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
    box-shadow: var(--shadow) !important;
}

[data-theme="dark"] .menu {
    background-color: var(--menu-bg) !important;
}

[data-theme="dark"] header, 
[data-theme="dark"] nav {
    background-color: var(--header-bg) !important;
    color: var(--text-primary) !important;
}

/* Adaptation des boutons de navigation en mode sombre */
[data-theme="dark"] .nav-btn.btn-home {
    background: var(--nav-btn-home) !important;
}

[data-theme="dark"] .nav-btn.btn-back {
    background: var(--nav-btn-back) !important;
}

/* Adaptation de l'indicateur utilisateur en mode sombre */
[data-theme="dark"] .user-info {
    background-color: var(--user-info-bg) !important;
    color: var(--user-info-text) !important;
    box-shadow: var(--user-info-shadow) !important;
}

/* Bouton de changement de thème */
.theme-switcher {
    position: fixed;
    bottom: 20px;
    left: 20px;
    z-index: 9999;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.theme-switcher:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.theme-switcher:active {
    transform: scale(0.95);
}

/* Animation d'apparition */
@keyframes slideInUp {
    from {
        transform: translateY(100px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.theme-switcher {
    animation: slideInUp 0.5s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
    .theme-switcher {
        bottom: 15px;
        left: 15px;
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
}

/* Adaptation des éléments Bootstrap en mode sombre */
[data-theme="dark"] .alert {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}

[data-theme="dark"] .btn {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}

[data-theme="dark"] .btn-secondary {
    background-color: var(--bg-secondary) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}

/* Adaptation des tableaux en mode sombre */
[data-theme="dark"] .table {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
}

[data-theme="dark"] .table th,
[data-theme="dark"] .table td {
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}

/* Adaptation des formulaires en mode sombre */
[data-theme="dark"] .form-control,
[data-theme="dark"] .form-select {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}

[data-theme="dark"] .form-control:focus,
[data-theme="dark"] .form-select:focus {
    background-color: var(--card-bg) !important;
    border-color: #667eea !important;
    color: var(--text-primary) !important;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
}

/* Adaptation des modales en mode sombre */
[data-theme="dark"] .modal-content {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
}

[data-theme="dark"] .modal-header,
[data-theme="dark"] .modal-footer {
    border-color: var(--border-color) !important;
}

/* Adaptation des dropdowns en mode sombre */
[data-theme="dark"] .dropdown-menu {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
}

[data-theme="dark"] .dropdown-item {
    color: var(--text-primary) !important;
}

[data-theme="dark"] .dropdown-item:hover {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
}
</style>

<!-- Bouton de changement de thème -->
<button class="theme-switcher" id="theme-switcher" title="Activer le mode sombre">
    <i class="fas fa-moon" id="theme-icon"></i>
</button>

<script>
// Système de gestion du thème sombre uniquement
class ThemeManager {
    constructor() {
        this.themeSwitcher = document.getElementById('theme-switcher');
        this.themeIcon = document.getElementById('theme-icon');
        this.currentTheme = this.getStoredTheme();
        
        this.init();
    }
    
    init() {
        // Appliquer le thème au chargement
        this.applyTheme(this.currentTheme);
        
        // Écouter les clics sur le bouton
        this.themeSwitcher.addEventListener('click', () => {
            this.toggleTheme();
        });
        
        // Écouter les changements de thème depuis d'autres pages
        window.addEventListener('storage', (e) => {
            if (e.key === 'theme') {
                this.applyTheme(e.newValue);
            }
        });
    }
    
    getStoredTheme() {
        return localStorage.getItem('theme') || 'light';
    }
    
    setStoredTheme(theme) {
        localStorage.setItem('theme', theme);
    }
    
    applyTheme(theme) {
        const html = document.documentElement;
        
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
            this.themeIcon.className = 'fas fa-sun';
            this.themeSwitcher.title = 'Désactiver le mode sombre';
        } else {
            html.removeAttribute('data-theme');
            this.themeIcon.className = 'fas fa-moon';
            this.themeSwitcher.title = 'Activer le mode sombre';
        }
        
        this.currentTheme = theme;
        this.setStoredTheme(theme);
    }
    
    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
        
        // Animation du bouton
        this.themeSwitcher.style.transform = 'scale(0.9)';
        setTimeout(() => {
            this.themeSwitcher.style.transform = 'scale(1)';
        }, 150);
    }
}

// Initialiser le gestionnaire de thème
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});

// Fonction globale pour changer le thème depuis d'autres scripts
window.changeTheme = function(theme) {
    if (window.themeManager) {
        window.themeManager.applyTheme(theme);
    }
};
</script> 