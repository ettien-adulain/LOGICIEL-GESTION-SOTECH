# 📋 Documentation du Journal Unifié - LOGICIEL_SOTECH

## 🎯 Vue d'ensemble

Le système de journalisation unifié remplace progressivement les multiples tables de journalisation par une seule table centralisée `journal_unifie`. Cette amélioration offre une meilleure performance, une recherche simplifiée et une maintenance facilitée.

## 🏗️ Architecture

### Tables impliquées
- **Nouvelle table** : `journal_unifie` (table principale)
- **Anciennes tables** : `journal_article`, `journal_client`, `journal_connexion`, etc. (conservées pour compatibilité)

### Fichiers principaux
- `fonction_traitement/JournalUnifie.php` - Classe principale
- `journal.php` - Interface utilisateur
- `integration_journal_unifie.php` - Fonctions de remplacement
- `migration_journal_unifie.php` - Script de migration
- `export_journal_csv.php` - Export CSV
- `test_journal_unifie.php` - Tests et validation

## 🚀 Installation et Configuration

### 1. Créer la table journal_unifie
```sql
-- Exécuter le fichier create_journal_unifie.sql
source create_journal_unifie.sql;
```

### 2. Migrer les données existantes
```php
// Accéder à migration_journal_unifie.php
// Suivre les instructions à l'écran
```

### 3. Tester le système
```php
// Accéder à test_journal_unifie.php
// Vérifier que tous les tests passent
```

## 📖 Utilisation

### Utilisation de la classe JournalUnifie

```php
require_once 'fonction_traitement/JournalUnifie.php';

// Initialisation
$journalUnifie = new JournalUnifie($cnx);

// Journalisation simple
$journalUnifie->logAction('article', 123, 'article', 'CREATION', 'Nouvel article créé');

// Journalisation avec données supplémentaires
$donnees = [
    'IDARTICLE' => 123,
    'stock_avant' => 0,
    'stock_apres' => 10,
    'MontantTotal' => 50000.00
];
$journalUnifie->logAction('vente', 456, 'vente', 'SORTIE', 'Vente effectuée', $donnees);
```

### Méthodes spécialisées

```php
// Articles
$journalUnifie->logArticle($idArticle, 'CREATION', 'Article créé', $donnees);

// Clients
$journalUnifie->logClient($idClient, 'MODIFICATION', 'Client modifié', $donnees);

// Stock
$journalUnifie->logStock($idStock, 'ENTREE', 'Entrée en stock', $donnees);

// Ventes
$journalUnifie->logVente($idVente, 'SORTIE', 'Vente effectuée', $donnees);

// Connexions
$journalUnifie->logConnexion('CONNEXION', 'Utilisateur connecté');
```

### Récupération des données

```php
// Journal complet
$journal = $journalUnifie->getJournalComplet(['limit' => 100]);

// Journal d'un module
$articles = $journalUnifie->getJournalModule('article', ['date_debut' => '2025-01-01']);

// Historique d'une entité
$historique = $journalUnifie->getHistoriqueEntite('article', 123);

// Statistiques
$stats = $journalUnifie->getStatistiques(['date_debut' => '2025-01-01']);

// Recherche avancée
$resultats = $journalUnifie->rechercherAvancee([
    'recherche' => 'iPhone',
    'module' => 'article',
    'date_debut' => '2025-01-01'
]);
```

## 🔄 Migration Progressive

### Étape 1 : Utiliser les fonctions de remplacement

```php
// Au lieu de journaliserAction()
journaliserActionUnifie($cnx, $idArticle, $idUtilisateur, $idStock, $action, $description);

// Au lieu de journaliserVente()
journaliserVenteUnifie($cnx, $idVente, $idUtilisateur, $action, $description);

// Au lieu de journaliserConnexion()
journaliserConnexionUnifie($cnx, $idUtilisateur, $description);
```

### Étape 2 : Remplacer progressivement

1. **Identifier les fichiers** qui utilisent les anciennes fonctions
2. **Remplacer les appels** par les nouvelles fonctions
3. **Tester** chaque modification
4. **Déployer** progressivement

### Étape 3 : Nettoyage (optionnel)

```php
// Nettoyer les anciennes entrées (après migration complète)
$journalUnifie->nettoyerJournal(365); // Garder 1 an d'historique
```

## 🎨 Interface Utilisateur

### Accès au journal
- **URL** : `journal.php`
- **Fonctionnalités** :
  - Navigation par modules
  - Filtres avancés
  - Recherche en temps réel
  - Export CSV
  - Statistiques

### Modules disponibles
- Articles
- Clients
- Stock (Entrées/Sorties)
- Ventes
- Connexions
- Commandes
- Numéros de série
- Comptabilité

## 📊 Fonctionnalités Avancées

### Export CSV
```php
// Export automatique
$journalUnifie->exporterCSV($filters, 'journal_export.csv');
```

### Migration des données
```php
// Migration des anciennes tables
$totalMigre = $journalUnifie->migrerAnciennesTables();
```

### Nettoyage
```php
// Supprimer les anciennes entrées
$supprimees = $journalUnifie->nettoyerJournal(365);
```

## 🔧 Configuration

### Variables d'environnement
```php
// Mode debug
define('DEBUG_MODE', true);

// Configuration de la base de données
// (utilise la configuration existante)
```

### Personnalisation
```php
// Ajouter de nouveaux modules
$modulesValides = ['article', 'client', 'stock', 'vente', 'nouveau_module'];

// Ajouter de nouvelles actions
$actionsValides = ['CREATION', 'MODIFICATION', 'NOUVELLE_ACTION'];
```

## 🐛 Dépannage

### Problèmes courants

1. **Table journal_unifie n'existe pas**
   - Solution : Exécuter `create_journal_unifie.sql`

2. **Erreur de journalisation**
   - Vérifier les paramètres obligatoires
   - Vérifier la connexion à la base de données
   - Consulter les logs d'erreur

3. **Migration échoue**
   - Vérifier que les anciennes tables existent
   - Vérifier les permissions de la base de données
   - Exécuter les tests de validation

### Logs et débogage

```php
// Activer le mode debug
define('DEBUG_MODE', true);

// Consulter les logs
tail -f error.log
```

## 📈 Performance

### Optimisations
- **Index** sur les champs fréquemment utilisés
- **Pagination** pour les grandes quantités de données
- **Nettoyage** régulier des anciennes entrées

### Monitoring
```php
// Vérifier les performances
$startTime = microtime(true);
$journalUnifie->logAction(...);
$duration = microtime(true) - $startTime;
```

## 🔒 Sécurité

### Contrôle d'accès
- Utilise le système de droits existant
- Vérification des sessions utilisateur
- Validation des paramètres d'entrée

### Audit
- Toutes les actions sont journalisées
- Traçabilité complète des modifications
- Export des logs pour audit externe

## 📞 Support

### Tests de validation
1. Accéder à `test_journal_unifie.php`
2. Vérifier que tous les tests passent
3. Consulter les logs en cas d'erreur

### Migration
1. Accéder à `migration_journal_unifie.php`
2. Suivre les instructions
3. Vérifier les données migrées

### Interface
1. Accéder à `journal.php`
2. Tester les différentes fonctionnalités
3. Vérifier l'affichage des données

## 🎯 Prochaines Étapes

1. **Migration complète** des données existantes
2. **Remplacement progressif** des anciennes fonctions
3. **Formation** des utilisateurs
4. **Monitoring** en production
5. **Optimisation** selon l'usage

---

**Note** : Ce système est conçu pour être compatible avec l'existant. Les anciennes fonctions continuent de fonctionner pendant la transition.
