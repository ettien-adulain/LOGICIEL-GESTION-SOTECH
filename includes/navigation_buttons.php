<?php
// Déterminer la page de retour appropriée
$current_page = basename($_SERVER['PHP_SELF']);
$return_page = 'index.php'; // Par défaut

// Mapping des pages de retour - Navigation logique et cohérente
$return_mapping = [
    // === PAGES PRINCIPALES DU MENU ===
    'articles.php' => 'index.php',
    'vente.php' => 'index.php',
    'commande.php' => 'index.php',
    'parametre.php' => 'index.php',
    'utilisateur.php' => 'index.php',
    'comptabilite.php' => 'index.php',
    'inventaire.php' => 'index.php',
    
    // === SOUS-MENUS ===
    'menu_chiffre_daffaire.php' => 'index.php',
    'menu_facture.php' => 'index.php',
    'menu_entree_stock.php' => 'index.php',
    'menu_vente_credit.php' => 'vente.php', // Retour vers vente.php au lieu d'index.php
    
    // === CAISSE ===
    'caisse.php' => 'vente.php', // Correct : caisse vient de vente
    
    // === GESTION DES ARTICLES ===
    'creation_d_article.php' => 'articles.php',
    'liste_article.php' => 'articles.php',
    'categorie_article.php' => 'articles.php',
    'liste_numeroserie.php' => 'articles.php',
    'generateur_d_etiquette.php' => 'articles.php',
    'journal.php' => 'articles.php',
    
    // === GESTION DES VENTES ===
    'listes_vente.php' => 'vente.php',
    'vente_jour.php' => 'vente.php',
    'vente_credit.php' => 'menu_vente_credit.php', // Correct : vient du menu vente crédit
    'suivi_vente_credit.php' => 'menu_vente_credit.php',
    'versement.php' => 'vente.php',
    
    // === GESTION DES COMMANDES ===
    'liste_commande.php' => 'commande.php',
    'bon_commande.php' => 'commande.php',
    'facture_proforma.php' => 'commande.php',
    'liste_proforma.php' => 'commande.php',
    
    // === GESTION DES STOCKS ===
    'entre_stock.php' => 'menu_entree_stock.php',
    'liste_entree_stock.php' => 'menu_entree_stock.php',
    'correction_stock.php' => 'menu_entree_stock.php',
    'liste_correction_stock.php' => 'menu_entree_stock.php',
    'liste_stock.php' => 'menu_entree_stock.php',
    'entrer_numero.php' => 'menu_entree_stock.php',
    
    // === GESTION DES PARAMÈTRES ===
    'fournisseur.php' => 'parametre.php',
    'mode_reglement.php' => 'parametre.php',
    'parametre_entreprise.php' => 'parametre.php',
    'parametre_general.php' => 'parametre.php',
    'repertoire_client.php' => 'parametre.php',
    'meilleur_client.php' => 'parametre.php',
    'rapports.php' => 'parametre.php',
    'parametre_message.php' => 'parametre.php',
    'parametre_e-mail.php' => 'parametre.php',
    'page_message.php' => 'parametre.php',
    'création_Messages_personnalisés.php' => 'parametre.php',
    'motif_correction_stock.php' => 'parametre_general.php',
    
    // === GESTION DES UTILISATEURS ===
    'creer_compte_utilisateur.php' => 'utilisateur.php',
    'liste_utilisateurs.php' => 'utilisateur.php',
    'modifier_parametre_utilisateur.php' => 'utilisateur.php',
    'droit_acces.php' => 'utilisateur.php',
    
    // === CHIFFRES D'AFFAIRES ===
    'chiffre_daffaire_horaire.php' => 'menu_chiffre_daffaire.php',
    'chiffre_daffaire_mensuel.php' => 'menu_chiffre_daffaire.php',
    'chiffre_daffaire_annuel.php' => 'menu_chiffre_daffaire.php',
    'CA_annuel.php' => 'menu_chiffre_daffaire.php',
    
    // === COMMUNICATION ===
    'sms.php' => 'index.php',
    'e_mail.php' => 'index.php',
    'envoyer_sms.php' => 'sms.php',
    'envoyer_email.php' => 'e_mail.php',
    'suivi_sms.php' => 'sms.php',
    'suivi_email.php' => 'e_mail.php',
    'parametre_email.php' => 'e_mail.php',
    'parametre_sms.php' => 'sms.php',
    'sms_personnalise.php' => 'creation_messages_personnalises.php',
    'e_mail_personalise.php' => 'creation_messages_personnalises.php',
    'creation_messages_personnalises.php' => 'parametre.php',
    
    // === INVENTAIRES ===
    'inventaire_liste.php' => 'index.php', // Retour vers inventaire.php au lieu d'index.php
    'inventaire_lancement.php' => 'inventaire_liste.php',
    'inventaire_impression.php' => 'inventaire_liste.php',
    'inventaire_saisie.php' => 'inventaire_liste.php',
    
    // === PAGES D'IMPRESSION ===
    'print_ticket_caisse.php' => 'caisse.php',
    'print_facture_standard.php' => 'caisse.php',
    'print_facture_tva.php' => 'caisse.php',
    'print_ticket_caissecredit.php' => 'vente_credit.php',
    'print_facture_standardcredit.php' => 'vente_credit.php',
    'print_facture_tvacredit.php' => 'vente_credit.php',
    
    // === PAGES D'EXPORT ===
    
    // === PAGES DE GESTION ===
    'gestion_stock.php' => 'menu_entree_stock.php',
    'suivi_commande.php' => 'commande.php',
    'historique_vente.php' => 'vente.php',
    
    // === PAGES DE RAPPORTS ===
    'rapport_vente.php' => 'menu_chiffre_daffaire.php',
    'rapport_stock.php' => 'menu_entree_stock.php',
    'rapport_client.php' => 'parametre.php'
];

if (isset($return_mapping[$current_page])) {
    $return_page = $return_mapping[$current_page];
}

// Style CSS pour les boutons modernes
echo '<style>
/* Boutons de navigation modernes */
.nav-buttons {
    position: fixed;
    top: 80px;
    left: 20px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    font-size: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.nav-btn::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.nav-btn:hover::before {
    left: 100%;
}

.nav-btn:hover {
    transform: translateY(-3px) scale(1.1);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.nav-btn:active {
    transform: translateY(-1px) scale(1.05);
}

.btn-home {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-back {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.btn-home:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}

.btn-back:hover {
    background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
}

/* Tooltip */
.nav-btn::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 70px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 1001;
}

.nav-btn::after {
    content: "";
}

.nav-btn:hover::after {
    opacity: 1;
    visibility: visible;
}

/* Animation d\'entrée */
@keyframes slideInLeft {
    from {
        transform: translateX(-100px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.nav-buttons {
    animation: slideInLeft 0.5s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
    .nav-buttons {
        top: 70px;
        left: 10px;
    }
    
    .nav-btn {
        width: 50px;
        height: 50px;
        font-size: 18px;
    }
}

/* Alternative: Barre de navigation horizontale */
.nav-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 10px 20px;
    z-index: 999;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.nav-bar-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
}

.nav-bar-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    padding: 8px 16px;
    border-radius: 25px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.nav-bar-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
    color: white;
    text-decoration: none;
}

/* Boutons avec effet 3D */
.btn-3d {
    background: linear-gradient(145deg, #e6e6e6, #ffffff);
    border: none;
    border-radius: 15px;
    padding: 15px 25px;
    font-size: 16px;
    font-weight: bold;
    color: #333;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 5px 5px 10px #d1d1d1, -5px -5px 10px #ffffff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-3d:hover {
    box-shadow: inset 5px 5px 10px #d1d1d1, inset -5px -5px 10px #ffffff;
    transform: translateY(2px);
    color: #333;
    text-decoration: none;
}

.btn-3d:active {
    box-shadow: inset 5px 5px 10px #d1d1d1, inset -5px -5px 10px #ffffff;
    transform: translateY(4px);
}
</style>';

// Boutons de navigation flottants
// UX : Le bouton retour redirige TOUJOURS vers la page logique mappée, jamais l'historique navigateur
// Cela garantit une navigation prévisible et professionnelle, même après un rafraîchissement ou une navigation complexe

echo '<div class="nav-buttons">';
echo '<a href="index.php" class="nav-btn btn-home" data-tooltip="Accueil" title="Accueil">';
echo '<i class="fas fa-home"></i>';
echo '</a>';
echo '<a href="' . $return_page . '" class="nav-btn btn-back" data-tooltip="Retour" title="Retour">';
echo '<i class="fas fa-arrow-left"></i>';
echo '</a>';
echo '</div>';


?> 