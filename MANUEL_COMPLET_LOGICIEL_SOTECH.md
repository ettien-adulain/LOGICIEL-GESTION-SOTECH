# 📚 MANUEL COMPLET DU LOGICIEL SOTECH
## Système de Gestion Commerciale Intégré

---

## 📋 TABLE DES MATIÈRES

### 1. [INTRODUCTION](#1-introduction)
- 1.1 Présentation du logiciel
- 1.2 Architecture du système
- 1.3 Prérequis techniques

### 2. [INSTALLATION ET CONFIGURATION](#2-installation-et-configuration)
- 2.1 Installation locale (WAMP/XAMPP)
- 2.2 Déploiement sur serveur (Hostinger)
- 2.3 Configuration de la base de données
- 2.4 Configuration des paramètres

### 3. [CONNEXION ET SÉCURITÉ](#3-connexion-et-sécurité)
- 3.1 Première connexion
- 3.2 Gestion des utilisateurs
- 3.3 Système de droits d'accès
- 3.4 Sécurité et authentification

### 4. [INTERFACE PRINCIPALE](#4-interface-principale)
- 4.1 Tableau de bord
- 4.2 Navigation
- 4.3 Thèmes et personnalisation
- 4.4 Système d'alertes

### 5. [GESTION DES ARTICLES](#5-gestion-des-articles)
- 5.1 Création d'articles
- 5.2 Liste des articles
- 5.3 Catégories d'articles
- 5.4 Générateur d'étiquettes
- 5.5 Numéros de série

### 6. [GESTION DES CLIENTS](#6-gestion-des-clients)
- 6.1 Répertoire client
- 6.2 Ajout rapide de clients
- 6.3 Historique des achats
- 6.4 Communication (SMS/Email)

### 7. [POINT DE VENTE](#7-point-de-vente)
- 7.1 Interface de caisse
- 7.2 Processus de vente
- 7.3 Modes de paiement
- 7.4 Impression des tickets

### 8. [GESTION DES VENTES](#8-gestion-des-ventes)
- 8.1 Liste des ventes
- 8.2 Ventes à crédit
- 8.3 Suivi des ventes crédit
- 8.4 Ventes du jour

### 9. [GESTION DES COMMANDES](#9-gestion-des-commandes)
- 9.1 Création de commandes
- 9.2 Liste des commandes
- 9.3 Validation des commandes
- 9.4 Impression des bons de commande

### 10. [GESTION DU STOCK](#10-gestion-du-stock)
- 10.1 Entrées de stock
- 10.2 Sorties de stock
- 10.3 Inventaire
- 10.4 Corrections de stock

### 11. [COMPTABILITÉ](#11-comptabilité)
- 11.1 Suivi comptable
- 11.2 Modes de règlement
- 11.3 Versements
- 11.4 Rapports financiers

### 12. [COMMUNICATION](#12-communication)
- 12.1 Envoi de SMS
- 12.2 Envoi d'emails
- 12.3 Suivi des communications
- 12.4 Messages personnalisés

### 13. [SERVICE APRÈS-VENTE](#13-service-après-vente)
- 13.1 Gestion du SAV
- 13.2 Suivi des interventions
- 13.3 Rapports SAV

### 14. [RAPPORTS ET ANALYSES](#14-rapports-et-analyses)
- 14.1 Chiffre d'affaires
- 14.2 Analyses des ventes
- 14.3 Rapports de stock
- 14.4 Export des données

### 15. [ADMINISTRATION](#15-administration)
- 15.1 Gestion des utilisateurs
- 15.2 Droits d'accès
- 15.3 Paramètres système
- 15.4 Journal système

### 16. [DÉPANNAGE](#16-dépannage)
- 16.1 Problèmes courants
- 16.2 Solutions techniques
- 16.3 Support et maintenance

---

## 1. INTRODUCTION

### 1.1 Présentation du logiciel

Le **LOGICIEL SOTECH** est un système de gestion commerciale complet et intégré, conçu pour optimiser la gestion des entreprises commerciales. Il combine toutes les fonctionnalités essentielles pour une gestion efficace :

- **Gestion des articles** : Catalogue complet avec catégories et numéros de série
- **Gestion des clients** : Répertoire client avec historique des achats
- **Point de vente** : Interface de caisse moderne et intuitive
- **Gestion des ventes** : Suivi complet des transactions
- **Gestion du stock** : Contrôle des entrées/sorties et inventaire
- **Comptabilité** : Suivi financier intégré
- **Communication** : SMS et email intégrés
- **Rapports** : Analyses et statistiques détaillées

### 1.2 Architecture du système

Le logiciel est développé en **PHP** avec une base de données **MySQL**, utilisant :
- **Frontend** : HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend** : PHP 8.0+, PDO pour la base de données
- **Base de données** : MySQL avec tables optimisées
- **Sécurité** : Système de droits d'accès granulaire
- **Interface** : Design responsive et moderne

### 1.3 Prérequis techniques

**Serveur local (développement) :**
- WAMP/XAMPP/LAMP
- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur
- Apache/Nginx

**Serveur de production :**
- Hébergement PHP/MySQL (Hostinger, OVH, etc.)
- SSL/HTTPS recommandé
- Espace disque : 100 MB minimum

---

## 2. INSTALLATION ET CONFIGURATION

### 2.1 Installation locale (WAMP/XAMPP)

#### Étape 1 : Préparation de l'environnement
```bash
# Démarrer WAMP/XAMPP
# Vérifier que Apache et MySQL sont actifs
# Accéder à phpMyAdmin : http://localhost/phpmyadmin
```

#### Étape 2 : Installation des fichiers
1. **Télécharger** le logiciel dans le dossier `www` de WAMP
2. **Extraire** tous les fichiers dans un dossier (ex: `LOGICIEL_SOTECH`)
3. **Accéder** à l'application : `http://localhost/LOGICIEL_SOTECH`

#### Étape 3 : Configuration de la base de données
1. **Créer** une base de données MySQL
2. **Importer** le fichier SQL fourni
3. **Configurer** les paramètres de connexion dans `db/connecting.php`

### 2.2 Déploiement sur serveur (Hostinger)

#### Étape 1 : Préparation du serveur
```bash
# Vérifier les prérequis
- PHP 8.0+
- MySQL 5.7+
- SSL activé
```

#### Étape 2 : Upload des fichiers
1. **Compresser** tous les fichiers du logiciel
2. **Uploader** via le File Manager de Hostinger
3. **Extraire** les fichiers sur le serveur

#### Étape 3 : Configuration de la base de données
1. **Créer** une base de données MySQL dans le panel Hostinger
2. **Importer** le fichier SQL
3. **Modifier** `db/connecting.php` avec les paramètres de production

#### Étape 4 : Configuration des permissions
```bash
# Permissions recommandées
- Dossiers : 755
- Fichiers PHP : 644
- Fichiers de logs : 666
```

### 2.3 Configuration de la base de données

#### Fichier de connexion (`db/connecting.php`)
```php
<?php
$host = 'localhost';
$dbname = 'logiciel_sotech';
$username = 'votre_utilisateur';
$password = 'votre_mot_de_passe';

try {
    $cnx = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
```

### 2.4 Configuration des paramètres

#### Paramètres généraux
- **Nom de l'entreprise** : Configuré dans `parametre.php`
- **Adresse et contacts** : Paramètres d'entreprise
- **Devise** : FCFA par défaut
- **Fuseau horaire** : Afrique/Abidjan

---

## 3. CONNEXION ET SÉCURITÉ

### 3.1 Première connexion

#### Création du compte administrateur
1. **Accéder** à la page de connexion
2. **Utiliser** les identifiants par défaut :
   - **Utilisateur** : `admin`
   - **Mot de passe** : `admin123`
3. **Changer** immédiatement le mot de passe

#### Première configuration
1. **Paramètres d'entreprise** : Remplir les informations
2. **Utilisateurs** : Créer les comptes utilisateurs
3. **Droits d'accès** : Configurer les permissions

### 3.2 Gestion des utilisateurs

#### Création d'un utilisateur
1. **Accéder** à `utilisateur.php`
2. **Cliquer** sur "Créer un compte"
3. **Remplir** les informations :
   - Nom d'utilisateur
   - Mot de passe
   - Nom complet
   - Email
   - Rôle

#### Types d'utilisateurs
- **Administrateur** : Accès complet
- **Gestionnaire** : Gestion des ventes et stock
- **Caissier** : Point de vente uniquement
- **Vendeur** : Ventes et clients

### 3.3 Système de droits d'accès

#### Modules disponibles
- **Articles** : Gestion des produits
- **Clients** : Répertoire client
- **Vente** : Point de vente
- **Stock** : Gestion des stocks
- **Comptabilité** : Suivi financier
- **Communication** : SMS/Email
- **Rapports** : Analyses et statistiques

#### Actions par module
- **Voir** : Consultation
- **Ajouter** : Création
- **Modifier** : Édition
- **Supprimer** : Suppression
- **Imprimer** : Impression
- **Exporter** : Export de données

### 3.4 Sécurité et authentification

#### Sécurité des mots de passe
- **Longueur minimum** : 8 caractères
- **Complexité** : Lettres, chiffres, symboles
- **Changement obligatoire** : Premier login

#### Sessions sécurisées
- **Timeout** : 30 minutes d'inactivité
- **Déconnexion automatique** : Sécurité renforcée
- **Logs de connexion** : Traçabilité

---

## 4. INTERFACE PRINCIPALE

### 4.1 Tableau de bord

#### Page d'accueil (`index.php`)
Le tableau de bord principal présente :
- **Modules principaux** : Accès rapide aux fonctionnalités
- **Statistiques** : Chiffre d'affaires, ventes du jour
- **Alertes** : Notifications système
- **Navigation** : Menu principal

#### Modules disponibles
```
📦 Articles          🛒 Vente              📋 Commandes
⚙️ Paramètres        📊 Inventaire         🔧 SAV
📈 Chiffre d'affaires 👥 Utilisateurs      🏪 Gestion Stock
💰 Comptabilité      📱 Communication      📊 Rapports
```

### 4.2 Navigation

#### Menu principal
- **Accueil** : Retour au tableau de bord
- **Articles** : Gestion des produits
- **Vente** : Point de vente
- **Clients** : Répertoire client
- **Stock** : Gestion des stocks
- **Rapports** : Analyses et statistiques

#### Navigation contextuelle
- **Breadcrumb** : Fil d'Ariane
- **Boutons d'action** : Actions rapides
- **Filtres** : Recherche et tri

### 4.3 Thèmes et personnalisation

#### Thème clair/sombre
- **Basculement** : Bouton dans l'interface
- **Préférence** : Sauvegardée par utilisateur
- **Responsive** : Adaptation mobile

#### Personnalisation
- **Couleurs** : Thème personnalisable
- **Layout** : Disposition des modules
- **Notifications** : Préférences d'alerte

### 4.4 Système d'alertes

#### Alertes système
- **Cloche d'alerte** : Icône en bas à droite
- **Notifications** : Erreurs système, stock bas
- **Temps réel** : Mise à jour automatique

#### Types d'alertes
- **Erreurs** : Problèmes système
- **Avertissements** : Stock bas, paiements en retard
- **Informations** : Nouvelles fonctionnalités

---

## 5. GESTION DES ARTICLES

### 5.1 Création d'articles

#### Interface de création (`creation_d_article.php`)
1. **Informations de base** :
   - Nom de l'article
   - Description
   - Catégorie
   - Code article (auto-généré)

2. **Prix et stock** :
   - Prix de vente HT
   - Prix de vente TTC
   - Stock initial
   - Stock minimum

3. **Détails avancés** :
   - Numéro de série
   - Code-barres
   - Image du produit

#### Code article automatique
```php
// Format : CAT-YYYY-NNNN
// Exemple : ELEC-2024-0001
$code = $categorie . '-' . date('Y') . '-' . sprintf('%04d', $numero);
```

### 5.2 Liste des articles

#### Interface de gestion (`liste_article.php`)
- **Recherche** : Par nom, code, catégorie
- **Filtres** : Catégorie, stock, prix
- **Actions** : Modifier, supprimer, ajouter au panier
- **Export** : Excel, CSV, PDF

#### Fonctionnalités avancées
- **Tri** : Par nom, prix, stock
- **Pagination** : Navigation dans les résultats
- **Sélection multiple** : Actions groupées

### 5.3 Catégories d'articles

#### Gestion des catégories (`categorie_article.php`)
1. **Création** : Nouvelle catégorie
2. **Modification** : Édition des catégories
3. **Suppression** : Avec vérification des articles

#### Hiérarchie des catégories
```
📦 Électronique
├── 📱 Téléphones
├── 💻 Ordinateurs
└── 🎧 Accessoires
```

### 5.4 Générateur d'étiquettes

#### Interface d'impression (`generateur_d_etiquette.php`)
- **Sélection** : Articles à étiqueter
- **Format** : Taille et disposition
- **Impression** : Directe ou PDF

#### Types d'étiquettes
- **Prix** : Prix de vente
- **Code-barres** : Code produit
- **Stock** : Informations de stock

### 5.5 Numéros de série

#### Gestion des séries (`liste_numeroserie.php`)
- **Attribution** : Numéros automatiques
- **Suivi** : Historique des ventes
- **Recherche** : Par numéro de série

---

## 6. GESTION DES CLIENTS

### 6.1 Répertoire client

#### Interface principale (`repertoire_client.php`)
- **Liste des clients** : Vue d'ensemble
- **Recherche** : Par nom, téléphone
- **Filtres** : Date d'inscription, achats
- **Actions** : SMS, Email, modification

#### Informations client
- **Données personnelles** : Nom, téléphone, email
- **Historique** : Articles achetés
- **Statistiques** : Montant total, nombre d'achats

### 6.2 Ajout rapide de clients

#### Formulaire simplifié
1. **Nom complet** : Obligatoire
2. **Téléphone** : Numéro de contact
3. **Email** : Optionnel
4. **Enregistrement** : Sauvegarde automatique

#### Validation des données
- **Téléphone** : Format international
- **Email** : Validation de format
- **Doublons** : Vérification automatique

### 6.3 Historique des achats

#### Vue détaillée par client
- **Articles achetés** : Liste complète
- **Dates d'achat** : Chronologie
- **Montants** : Totaux par période
- **Modes de paiement** : Espèces, crédit, etc.

### 6.4 Communication (SMS/Email)

#### Envoi de SMS (`envoyer_sms.php`)
1. **Sélection** : Client(s) destinataire(s)
2. **Message** : Texte personnalisé
3. **Envoi** : Individuel ou groupé
4. **Suivi** : Statut de livraison

#### Envoi d'emails (`envoyer_email.php`)
1. **Destinataires** : Sélection multiple
2. **Sujet** : Objet du message
3. **Contenu** : Message personnalisé
4. **Pièces jointes** : Documents optionnels

---

## 7. POINT DE VENTE

### 7.1 Interface de caisse

#### Interface principale (`caisse.php`)
- **Recherche d'articles** : Code-barres, nom
- **Panier** : Articles sélectionnés
- **Calculs** : Totaux automatiques
- **Paiement** : Modes multiples

#### Fonctionnalités de caisse
- **Scan** : Code-barres
- **Recherche** : Nom d'article
- **Quantités** : Modification rapide
- **Suppression** : Retrait d'articles

### 7.2 Processus de vente

#### Étapes de vente
1. **Sélection client** : Optionnel
2. **Ajout d'articles** : Recherche et sélection
3. **Vérification** : Contrôle des quantités
4. **Paiement** : Choix du mode
5. **Finalisation** : Impression du ticket

#### Gestion du panier
- **Ajout** : Quantités multiples
- **Modification** : Changement de quantité
- **Suppression** : Retrait d'articles
- **Vide** : Remise à zéro

### 7.3 Modes de paiement

#### Modes disponibles
- **Espèces** : Paiement comptant
- **Carte** : Paiement par carte
- **Crédit** : Vente à crédit
- **Mixte** : Combinaison de modes

#### Gestion des paiements
- **Montant exact** : Calcul automatique
- **Rendu** : Calcul de la monnaie
- **Validation** : Contrôle des montants

### 7.4 Impression des tickets

#### Types de tickets
- **Ticket de caisse** : Standard
- **Facture** : Avec TVA
- **Reçu** : Simple reçu

#### Configuration d'impression
- **Imprimante** : Sélection automatique
- **Format** : Taille et disposition
- **Contenu** : Informations à inclure

---

## 8. GESTION DES VENTES

### 8.1 Liste des ventes

#### Interface de consultation (`listes_vente.php`)
- **Filtres** : Date, client, montant
- **Recherche** : Par numéro de vente
- **Tri** : Par date, montant, client
- **Actions** : Voir, imprimer, exporter

#### Informations affichées
- **Numéro de vente** : Identifiant unique
- **Date/Heure** : Moment de la vente
- **Client** : Nom du client
- **Montant** : Total de la vente
- **Mode de paiement** : Espèces, carte, crédit

### 8.2 Ventes à crédit

#### Gestion du crédit (`vente_credit.php`)
1. **Sélection client** : Client avec crédit
2. **Articles** : Sélection des produits
3. **Conditions** : Échéances, taux
4. **Validation** : Contrôle des conditions

#### Suivi des crédits (`suivi_vente_credit.php`)
- **Liste des crédits** : Ventes en attente
- **Échéances** : Dates de paiement
- **Relances** : SMS/Email automatiques
- **Paiements** : Règlement partiel/total

### 8.3 Suivi des ventes crédit

#### Tableau de bord crédit
- **En cours** : Crédits non réglés
- **Échus** : Paiements en retard
- **Régulés** : Crédits soldés
- **Statistiques** : Montants, délais

#### Actions disponibles
- **Relance** : Contact client
- **Paiement** : Enregistrement partiel
- **Report** : Modification d'échéance
- **Annulation** : Retour en stock

### 8.4 Ventes du jour

#### Résumé quotidien (`vente_jour.php`)
- **Chiffre d'affaires** : Total des ventes
- **Nombre de ventes** : Transactions
- **Moyenne** : Panier moyen
- **Modes de paiement** : Répartition

#### Détails par période
- **Heure par heure** : Évolution
- **Top articles** : Meilleures ventes
- **Clients** : Nouveaux vs récurrents

---

## 9. GESTION DES COMMANDES

### 9.1 Création de commandes

#### Interface de commande (`bon_commande.php`)
1. **Sélection client** : Choix du client
2. **Articles** : Sélection des produits
3. **Quantités** : Demandes client
4. **Conditions** : Délais, remises
5. **Validation** : Enregistrement

#### Types de commandes
- **Standard** : Commande normale
- **Urgente** : Priorité haute
- **Prévente** : Avant réception stock

### 9.2 Liste des commandes

#### Interface de gestion (`liste_commande.php`)
- **Statuts** : En attente, validée, livrée
- **Filtres** : Date, client, statut
- **Actions** : Modifier, valider, imprimer
- **Suivi** : Évolution des commandes

#### Statuts des commandes
- **Brouillon** : En cours de création
- **Validée** : Confirmée par le client
- **En préparation** : Articles en cours
- **Livrée** : Commande terminée
- **Annulée** : Commande annulée

### 9.3 Validation des commandes

#### Processus de validation
1. **Vérification stock** : Disponibilité articles
2. **Calcul prix** : Totaux et remises
3. **Confirmation client** : Validation finale
4. **Mise à jour stock** : Réservation articles

#### Contrôles automatiques
- **Stock suffisant** : Vérification quantités
- **Prix cohérents** : Contrôle tarifs
- **Client valide** : Vérification données

### 9.4 Impression des bons de commande

#### Formats d'impression
- **Bon de commande** : Standard
- **Devis** : Avec prix et conditions
- **Proforma** : Facture proforma

#### Personnalisation
- **En-tête** : Logo et informations
- **Pied de page** : Conditions générales
- **Mise en page** : Disposition personnalisée

---

## 10. GESTION DU STOCK

### 10.1 Entrées de stock

#### Interface d'entrée (`entre_stock.php`)
1. **Sélection article** : Choix du produit
2. **Quantité** : Nombre d'unités
3. **Prix d'achat** : Coût unitaire
4. **Fournisseur** : Source d'approvisionnement
5. **Validation** : Mise à jour du stock

#### Types d'entrées
- **Achat** : Approvisionnement normal
- **Retour** : Retour client
- **Correction** : Ajustement stock
- **Transfert** : Entre magasins

### 10.2 Sorties de stock

#### Gestion des sorties
- **Vente** : Sortie par vente
- **Transfert** : Vers autre magasin
- **Perte** : Articles endommagés
- **Don** : Articles offerts

#### Contrôles automatiques
- **Stock suffisant** : Vérification avant sortie
- **Alertes** : Stock minimum atteint
- **Traçabilité** : Historique des mouvements

### 10.3 Inventaire

#### Lancement d'inventaire (`inventaire_lancement.php`)
1. **Sélection période** : Date de l'inventaire
2. **Choix articles** : Sélection des produits
3. **Lancement** : Début de l'inventaire
4. **Saisie** : Comptage physique

#### Saisie d'inventaire (`inventaire_saisie.php`)
- **Liste articles** : Produits à compter
- **Quantités** : Saisie des comptages
- **Écarts** : Différences avec le stock théorique
- **Validation** : Finalisation de l'inventaire

### 10.4 Corrections de stock

#### Interface de correction (`correction_stock.php`)
- **Sélection article** : Produit à corriger
- **Écart** : Différence constatée
- **Motif** : Raison de la correction
- **Validation** : Mise à jour du stock

#### Types de corrections
- **Ajustement** : Correction d'écart
- **Perte** : Articles endommagés
- **Vol** : Articles volés
- **Erreur** : Erreur de saisie

---

## 11. COMPTABILITÉ

### 11.1 Suivi comptable

#### Interface comptable (`comptabilite.php`)
- **Écritures** : Mouvements comptables
- **Comptes** : Plan comptable
- **Soldes** : Balances des comptes
- **Rapports** : États financiers

#### Types d'écritures
- **Ventes** : Enregistrement des ventes
- **Achats** : Enregistrement des achats
- **Paiements** : Règlements clients
- **Charges** : Frais généraux

### 11.2 Modes de règlement

#### Configuration (`mode_reglement.php`)
- **Espèces** : Paiement comptant
- **Carte bancaire** : Paiement par carte
- **Virement** : Transfert bancaire
- **Chèque** : Paiement par chèque

#### Paramètres par mode
- **Nom** : Libellé du mode
- **Compte** : Compte comptable
- **Actif** : Mode disponible
- **Ordre** : Ordre d'affichage

### 11.3 Versements

#### Gestion des versements (`versement.php`)
- **Enregistrement** : Nouveau versement
- **Client** : Sélection du client
- **Montant** : Somme versée
- **Mode** : Mode de règlement
- **Date** : Date du versement

#### Suivi des versements
- **Historique** : Tous les versements
- **Relances** : Versements en retard
- **Statistiques** : Montants par période

### 11.4 Rapports financiers

#### Types de rapports
- **Journal des ventes** : Détail des ventes
- **Journal des achats** : Détail des achats
- **Balance** : Soldes des comptes
- **Grand livre** : Mouvements détaillés

#### Export des rapports
- **PDF** : Impression directe
- **Excel** : Tableur
- **CSV** : Données brutes

---

## 12. COMMUNICATION

### 12.1 Envoi de SMS

#### Interface SMS (`envoyer_sms.php`)
1. **Sélection destinataires** : Clients ou groupes
2. **Message** : Texte à envoyer
3. **Personnalisation** : Variables dynamiques
4. **Envoi** : Lancement des SMS

#### Types de SMS
- **Promotionnel** : Offres commerciales
- **Information** : Nouvelles, horaires
- **Relance** : Paiements en retard
- **Personnalisé** : Messages individuels

### 12.2 Envoi d'emails

#### Interface email (`envoyer_email.php`)
- **Destinataires** : Sélection multiple
- **Sujet** : Objet du message
- **Contenu** : Corps du message
- **Pièces jointes** : Documents

#### Templates d'emails
- **Promotionnel** : Offres commerciales
- **Facture** : Envoi de factures
- **Relance** : Paiements en retard
- **Personnalisé** : Messages libres

### 12.3 Suivi des communications

#### Suivi SMS (`suivi_sms.php`)
- **Statut** : Envoyé, livré, échec
- **Date** : Heure d'envoi
- **Coût** : Tarif par SMS
- **Historique** : Tous les envois

#### Suivi emails (`suivi_email.php`)
- **Statut** : Envoyé, ouvert, cliqué
- **Bounces** : Emails non livrés
- **Statistiques** : Taux d'ouverture

### 12.4 Messages personnalisés

#### Création de messages (`creation_messages_personnalises.php`)
- **Templates** : Modèles de messages
- **Variables** : Données dynamiques
- **Langues** : Messages multilingues
- **Scheduling** : Envoi programmé

---

## 13. SERVICE APRÈS-VENTE

### 13.1 Gestion du SAV

#### Interface SAV (`sav.php`)
- **Nouveau ticket** : Création d'intervention
- **Liste des tickets** : Suivi des interventions
- **Statuts** : En cours, résolu, fermé
- **Priorités** : Urgent, normal, bas

#### Types d'interventions
- **Réparation** : Réparation d'articles
- **Échange** : Échange de produits
- **Remboursement** : Remboursement client
- **Conseil** : Assistance technique

### 13.2 Suivi des interventions

#### Interface de suivi (`sav_suivi.php`)
- **Détails** : Informations complètes
- **Historique** : Évolution du ticket
- **Pièces** : Pièces détachées
- **Coûts** : Frais d'intervention

#### Statuts des tickets
- **Ouvert** : Nouveau ticket
- **En cours** : Intervention en cours
- **En attente** : En attente de pièces
- **Résolu** : Problème résolu
- **Fermé** : Ticket fermé

### 13.3 Rapports SAV

#### Types de rapports
- **Tickets par période** : Volume d'interventions
- **Temps de résolution** : Délais moyens
- **Coûts** : Frais d'intervention
- **Satisfaction** : Retours clients

#### Export des rapports
- **PDF** : Rapports imprimables
- **Excel** : Données détaillées
- **Graphiques** : Visualisations

---

## 14. RAPPORTS ET ANALYSES

### 14.1 Chiffre d'affaires

#### Menu chiffre d'affaires (`menu_chiffre_daffaire.php`)
- **Période** : Sélection de la période
- **Vue** : Quotidien, mensuel, annuel
- **Comparaison** : Évolution par rapport à l'année précédente
- **Détails** : Ventilation par catégorie

#### Types d'analyses
- **Évolution** : Croissance du CA
- **Saisonnalité** : Variations saisonnières
- **Top produits** : Meilleures ventes
- **Top clients** : Clients les plus importants

### 14.2 Analyses des ventes

#### Rapports de vente
- **Ventes par période** : Évolution temporelle
- **Ventes par produit** : Performance des articles
- **Ventes par client** : Segmentation client
- **Ventes par vendeur** : Performance commerciale

#### Métriques clés
- **Panier moyen** : Montant moyen par vente
- **Fréquence d'achat** : Nombre d'achats par client
- **Taux de conversion** : Ventes/Visites
- **Marge** : Profitabilité

### 14.3 Rapports de stock

#### États de stock
- **Stock actuel** : Quantités en stock
- **Mouvements** : Entrées et sorties
- **Valeurs** : Valeur du stock
- **Rotation** : Vitesse de rotation

#### Alertes stock
- **Stock minimum** : Articles en rupture
- **Stock maximum** : Surstockage
- **Périmés** : Articles périmés
- **Lents** : Articles peu vendus

### 14.4 Export des données

#### Formats d'export
- **Excel** : Tableurs
- **CSV** : Données brutes
- **PDF** : Rapports imprimables
- **JSON** : Données structurées

#### Types d'exports
- **Ventes** : Historique des ventes
- **Clients** : Base de données clients
- **Stock** : État des stocks
- **Comptabilité** : Écritures comptables

---

## 15. ADMINISTRATION

### 15.1 Gestion des utilisateurs

#### Interface utilisateurs (`utilisateur.php`)
- **Liste des utilisateurs** : Tous les comptes
- **Création** : Nouveaux utilisateurs
- **Modification** : Édition des profils
- **Suppression** : Désactivation des comptes

#### Profils utilisateurs
- **Administrateur** : Accès complet
- **Gestionnaire** : Gestion opérationnelle
- **Caissier** : Point de vente
- **Vendeur** : Ventes et clients

### 15.2 Droits d'accès

#### Configuration des droits (`droit_acces.php`)
- **Modules** : Accès aux fonctionnalités
- **Actions** : Permissions par action
- **Pages** : Accès aux pages spécifiques
- **Données** : Accès aux données sensibles

#### Niveaux d'accès
- **Lecture seule** : Consultation uniquement
- **Écriture** : Modification autorisée
- **Suppression** : Suppression autorisée
- **Administration** : Gestion complète

### 15.3 Paramètres système

#### Configuration générale (`parametre.php`)
- **Entreprise** : Informations société
- **Contacts** : Coordonnées
- **Devise** : Monnaie utilisée
- **Fuseau horaire** : Zone géographique

#### Paramètres techniques
- **Base de données** : Configuration DB
- **Sauvegarde** : Fréquence des backups
- **Logs** : Niveau de journalisation
- **Sécurité** : Paramètres de sécurité

### 15.4 Journal système

#### Interface du journal (`journal_systeme.php`)
- **Activités** : Toutes les actions
- **Filtres** : Par utilisateur, date, action
- **Recherche** : Recherche dans les logs
- **Export** : Export des logs

#### Types d'événements
- **Connexions** : Connexions utilisateurs
- **Modifications** : Changements de données
- **Suppressions** : Suppressions d'éléments
- **Erreurs** : Erreurs système

---

## 16. DÉPANNAGE

### 16.1 Problèmes courants

#### Erreurs de connexion
**Problème** : Impossible de se connecter
**Solutions** :
1. Vérifier les paramètres de base de données
2. Contrôler la connexion internet
3. Redémarrer les services (Apache/MySQL)

#### Erreurs d'affichage
**Problème** : Pages qui ne s'affichent pas correctement
**Solutions** :
1. Vider le cache du navigateur
2. Vérifier les permissions des fichiers
3. Contrôler les erreurs PHP

#### Problèmes de performance
**Problème** : Lenteur de l'application
**Solutions** :
1. Optimiser la base de données
2. Nettoyer les logs anciens
3. Vérifier l'espace disque

### 16.2 Solutions techniques

#### Diagnostic système
```bash
# Vérifier les logs d'erreur
tail -f /var/log/apache2/error.log

# Vérifier l'espace disque
df -h

# Vérifier les processus PHP
ps aux | grep php
```

#### Maintenance de la base de données
```sql
-- Optimiser les tables
OPTIMIZE TABLE journal_systeme;

-- Vérifier l'intégrité
CHECK TABLE client, article, vente;

-- Nettoyer les logs anciens
DELETE FROM journal_systeme WHERE date_action < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### 16.3 Support et maintenance

#### Logs à consulter
- **Logs Apache** : Erreurs serveur
- **Logs PHP** : Erreurs d'application
- **Logs MySQL** : Erreurs base de données
- **Logs application** : Journal système

#### Procédures de maintenance
1. **Sauvegarde quotidienne** : Base de données
2. **Nettoyage hebdomadaire** : Logs anciens
3. **Mise à jour mensuelle** : Sécurité
4. **Contrôle trimestriel** : Performance

#### Contact support
- **Email** : support@sotech.com
- **Téléphone** : +225 XX XX XX XX
- **Documentation** : https://docs.sotech.com
- **Forum** : https://forum.sotech.com

---

## 📞 SUPPORT ET CONTACT

### Informations de contact
- **Email** : support@logiciel-sotech.com
- **Téléphone** : +225 XX XX XX XX
- **Site web** : https://www.logiciel-sotech.com
- **Documentation** : https://docs.logiciel-sotech.com

### Ressources supplémentaires
- **Forum utilisateurs** : https://forum.logiciel-sotech.com
- **Tutoriels vidéo** : https://youtube.com/logiciel-sotech
- **FAQ** : https://faq.logiciel-sotech.com
- **Mises à jour** : https://updates.logiciel-sotech.com

---

*Manuel rédigé pour LOGICIEL SOTECH - Version 1.0*
*Dernière mise à jour : [Date actuelle]*
