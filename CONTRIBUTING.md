# Contribuer à blunx/ai

Merci de contribuer ! Ce guide décrit comment installer le projet, les
conventions à respecter et le processus de contribution.

## Table des matières

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Structure du projet](#structure-du-projet)
- [Conventions de code](#conventions-de-code)
- [Documentation](#documentation)
- [Tester](#tester)
- [Processus de pull request](#processus-de-pull-request)
- [Signaler un bug](#signaler-un-bug)

## Prérequis

- PHP ≥ 8.1
- Composer ≥ 2
- Une application Laravel 10 / 11 pour les tests manuels

## Installation

```bash
composer install
```

> `vendor/` et `composer.lock` sont exclus du dépôt (`gitignore`) : c'est une
> librairie, les dépendances sont résolues chez l'installateur via
> `composer require blunx/ai`.

## Structure du projet

```
blunx-package/
├── config/
│   ├── blunx.php          # Configuration principale (env BLUNX_*)
│   └── blunx_access.php   # Règles d'accès RBAC (approche négative)
├── src/
│   ├── BlunxServiceProvider.php   # Enregistrement du package
│   ├── Agents/                    # DataExecutorAgent (exécution SQL locale)
│   ├── Console/                   # Commandes Artisan blunx:*
│   ├── DTO/                       # QueryItem, QueryResult, ExecutionResult, InsightResult
│   ├── Http/Controllers/          # BlunxApiController (REST + pipeline SSE)
│   ├── Jobs/                      # GenerateWidgetInsightJob
│   ├── Mail/                      # BlunxInsightMail
│   ├── models/                    # Modèles Eloquent blunx_*
│   ├── resources/                 # Traductions (fr/en), vues email
│   ├── Routes/                    # api.php, console.php
│   ├── Services/                  # BlunxApiClient (client Hub)
│   └── Support/                   # SqlGuard, BlunxDatabase, AccessRules, …
└── composer.json
```

## Conventions de code

- **Langue** : code et documentation en anglais (concis) ; les traductions
  utilisateur vivent dans `src/resources/lang/` (fr par défaut, en).
- **PHP 8+** : promotion de propriétés de constructeur, propriétés `readonly`,
  arguments nommés, types union.
- **Namespace** : `Blunx\AI\` (PSR-4, autoload depuis `src/`).
- **Modèles Eloquent** : trait `HasUuids` + `getRouteKeyName()` → `'uuid'` +
  `uniqueIds()` ; `$hidden` exclut les `id` et clés étrangères internes.
- **DTOs** : promotion `readonly` + méthode `toArray()`.
- **Documentation** : chaque classe porte un docblock court décrivant son rôle ;
  les méthodes publiques ont un docblock concis quand utile.
- **HTTP** : utiliser la façade Laravel `Http`. Ne jamais envoyer de clés API
  dans le corps des requêtes — en-têtes `X-Blunx-Key` / `X-Blunx-LLM-Key`.

## Documentation

Toute nouvelle fonctionnalité ou changement d'API doit être documenté :

- dans le code (docblocks) ;
- dans le `README.md` si l'API publique change ;
- dans le `CHANGELOG.md` sous la rubrique de la version en cours (Keep a
  Changelog + SemVer).

## Tester

Il n'existe pas encore de suite de tests automatisée dans ce dépôt. Avant de
soumettre une contribution, vérifiez au minimum :

```bash
php -l <fichier.php>        # syntaxe PHP valide
composer validate           # composer.json valide
```

Puis testez manuellement dans une application Laravel :

```bash
composer require blunx/ai:dev-main
php artisan vendor:publish --tag=blunx-config
php artisan blunx:init
php artisan blunx:setup
```

## Processus de pull request

1. Créez une branche descriptive (`fix/…`, `feat/…`, `docs/…`).
2. Faites des commits atomiques avec des messages clairs.
3. Mettez à jour la documentation et le `CHANGELOG.md` si nécessaire.
4. Ouvrez la pull request vers `main` en décrivant :
   - le problème résolu / la fonctionnalité ajoutée ;
   - les changements d'API (si rupture, justifiez) ;
   - comment tester.

Toute contribution doit respecter le [Code de conduite](CODE_OF_CONDUCT.md).

## Signaler un bug

Ouvrez une issue avec :

- la version du package et de Laravel / PHP ;
- les étapes de reproduction minimales ;
- le comportement attendu vs observé ;
- un extrait de configuration ou de code si possible.

Pour les **vulnérabilités de sécurité**, ne créez pas d'issue publique :
suivez la procédure de [`SECURITY.md`](SECURITY.md).
