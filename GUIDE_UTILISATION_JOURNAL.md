# 📖 Guide d'Utilisation du Journal Unifié - LOGICIEL_SOTECH

## 🎯 Introduction

Ce guide vous accompagne dans l'utilisation du nouveau système de journalisation unifié. Il remplace progressivement les anciennes tables de journalisation par une solution centralisée et performante.

## 🚀 Démarrage Rapide

### 1. Première Utilisation

1. **Accéder au journal** : `journal.php`
2. **Voir les données** : Sélectionner un module dans la sidebar
3. **Filtrer** : Utiliser les filtres de date et d'action
4. **Exporter** : Cliquer sur le bouton d'export CSV

### 2. Navigation dans l'Interface

```
📊 Journal Unifié
├── 📦 Articles - Actions sur les articles
├── 👥 Clients - Gestion des clients  
├── 📦 Stock - Entrées/Sorties de stock
├── 🛒 Ventes - Transactions de vente
├── 🔐 Connexions - Connexions utilisateurs
├── 📋 Commandes - Gestion des commandes
├── 🏷️ Numéros de série - Suivi des séries
└── 💰 Comptabilité - Opérations comptables
```

## 🔍 Utilisation des Filtres

### Filtres de Base
- **Recherche** : Tapez dans la barre de recherche
- **Date début** : Sélectionnez la date de début
- **Date fin** : Sélectionnez la date de fin
- **Action** : Filtrez par type d'action

### Filtres Avancés
- **Module** : Sélectionnez le module concerné
- **Utilisateur** : Filtrez par utilisateur
- **Période** : Utilisez les raccourcis de période

## 📊 Comprendre les Données

### Colonnes Principales
- **Date/Heure** : Quand l'action a eu lieu
- **Module** : Dans quel module (article, client, etc.)
- **Action** : Type d'action (création, modification, etc.)
- **Utilisateur** : Qui a effectué l'action
- **Description** : Détails de l'action

### Types d'Actions
- 🟢 **CREATION** : Création d'un nouvel élément
- 🟡 **MODIFICATION** : Modification d'un élément existant
- 🔴 **SUPPRESSION** : Suppression d'un élément
- ⬆️ **ENTREE** : Entrée en stock
- ⬇️ **SORTIE** : Sortie de stock
- 🔐 **CONNEXION** : Connexion utilisateur
- 🚪 **DECONNEXION** : Déconnexion utilisateur

## 🎨 Codes Couleur

### Actions
- **Vert** : Créations, Entrées
- **Jaune** : Modifications, Corrections
- **Rouge** : Suppressions, Sorties
- **Bleu** : Connexions, Validations
- **Violet** : Actions spéciales

### Modules
- **Articles** : Gestion des produits
- **Clients** : Gestion de la clientèle
- **Stock** : Gestion des inventaires
- **Ventes** : Transactions commerciales
- **Connexions** : Sécurité et accès

## 🔧 Fonctionnalités Avancées

### Export CSV
1. **Filtrer** les données souhaitées
2. **Cliquer** sur le bouton d'export (📥)
3. **Télécharger** le fichier CSV
4. **Ouvrir** dans Excel ou autre tableur

### Recherche Rapide
- **Ctrl + F** : Recherche dans la page
- **Barre de recherche** : Recherche en temps réel
- **Filtres** : Recherche par critères

### Raccourcis Clavier
- **Ctrl + R** : Rafraîchir les données
- **Ctrl + E** : Exporter en CSV
- **Ctrl + F** : Rechercher

## 📈 Statistiques et Rapports

### Tableaux de Bord
- **Entrées trouvées** : Nombre de résultats
- **Module actuel** : Module sélectionné
- **Date du jour** : Date actuelle
- **Migration** : Lien vers la migration

### Métriques Importantes
- **Volume d'activité** : Nombre d'actions par jour
- **Utilisateurs actifs** : Qui utilise le système
- **Modules populaires** : Quels modules sont utilisés
- **Erreurs** : Actions qui ont échoué

## 🛠️ Administration

### Migration des Données
1. **Accéder** à `migration_journal_unifie.php`
2. **Vérifier** les données existantes
3. **Lancer** la migration
4. **Vérifier** les résultats

### Tests et Validation
1. **Accéder** à `test_journal_unifie.php`
2. **Exécuter** tous les tests
3. **Vérifier** les résultats
4. **Corriger** les erreurs si nécessaire

### Nettoyage
- **Anciennes entrées** : Supprimer après migration
- **Données de test** : Nettoyer les tests
- **Logs d'erreur** : Surveiller les erreurs

## 🔒 Sécurité et Audit

### Traçabilité
- **Qui** : Utilisateur qui a effectué l'action
- **Quand** : Date et heure précise
- **Quoi** : Action effectuée
- **Où** : Module concerné
- **Comment** : Description détaillée

### Contrôle d'Accès
- **Droits utilisateur** : Basés sur le système existant
- **Sessions** : Vérification de la connexion
- **Validation** : Contrôle des paramètres

## 📱 Utilisation Mobile

### Interface Responsive
- **Sidebar** : Se replie sur mobile
- **Tableaux** : Défilement horizontal
- **Boutons** : Taille adaptée au tactile
- **Filtres** : Interface simplifiée

### Optimisations Mobile
- **Chargement** : Données limitées par défaut
- **Recherche** : Interface tactile optimisée
- **Export** : Format adapté aux mobiles

## 🚨 Dépannage

### Problèmes Courants

#### "Aucune donnée trouvée"
- Vérifier les filtres de date
- Vérifier le module sélectionné
- Vérifier les permissions

#### "Erreur d'export"
- Vérifier les données à exporter
- Vérifier les permissions de fichier
- Réessayer l'export

#### "Interface lente"
- Réduire la période de recherche
- Utiliser les filtres
- Vérifier la connexion réseau

### Solutions
1. **Rafraîchir** la page (F5)
2. **Vérifier** les filtres
3. **Consulter** les logs d'erreur
4. **Contacter** l'administrateur

## 📞 Support

### Ressources
- **Documentation** : `DOCUMENTATION_JOURNAL_UNIFIE.md`
- **Tests** : `test_journal_unifie.php`
- **Migration** : `migration_journal_unifie.php`

### Contact
- **Administrateur** : Pour les problèmes techniques
- **Formation** : Pour l'apprentissage
- **Support** : Pour les questions d'utilisation

## 🎯 Bonnes Pratiques

### Utilisation Quotidienne
1. **Vérifier** les connexions quotidiennes
2. **Surveiller** les actions importantes
3. **Exporter** les rapports réguliers
4. **Nettoyer** les anciennes données

### Maintenance
1. **Sauvegarder** régulièrement
2. **Monitorer** les performances
3. **Mettre à jour** le système
4. **Former** les utilisateurs

### Sécurité
1. **Vérifier** les accès utilisateurs
2. **Surveiller** les actions suspectes
3. **Auditer** régulièrement
4. **Protéger** les données sensibles

---

**Note** : Ce guide est évolutif et sera mis à jour selon les retours d'utilisation.
