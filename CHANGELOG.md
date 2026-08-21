# Changelog

Toutes les modifications notables de `blunx/ai` sont documentées ici.
Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le
versionnage suit [SemVer](https://semver.org/lang/fr/).

## [1.0.0] - 2026-08-11

Première publication publique.

### Ajouté
- **Pipeline conversationnel complet** : question en langage naturel → SQL
  généré → exécution locale en lecture seule → validation → synthèse finale,
  streamé en SSE via `POST /api/blunx/stream`.
- **REST API** sous `/api/blunx` : conversations, messages, dashboards,
  widgets, insights, feedback, exécution SQL et exécution de widget.
- **`DataExecutorAgent`** : exécution locale des requêtes avec aperçu
  (résumé statistique) ou résultats complets.
- **Sécurité en profondeur** :
  - `SqlGuard` : validation stricte SELECT/WITH en lecture seule (mots-clés
    DML/DDL, fonctions système dangereuses, `SELECT ... INTO`).
  - Connexion BDD dédiée en **lecture seule** (rôle `SELECT`/`USAGE` uniquement),
    supportée via URL de connexion (DSN) ou identifiants séparés.
  - `AccessRules` : RBAC à approche négative (tables, colonnes et lignes
    interdites), remplacement du placeholder `USER_ID` à l'exécution.
- **Dashboards & widgets** : création, édition, suppression, exécution des
  requêtes, et génération de requêtes de référence via le serveur.
- **Insights automatisés** :
  - `GenerateWidgetInsightJob` (9 étapes) avec re-planification automatique
    (quotidien / hebdomadaire / mensuel) et limite d'un job en attente par widget.
  - Génération de **rapports PDF** (dompdf) et **emails** avec PDF en pièce
    jointe, notification conditionnelle selon le seuil de priorité.
  - `InsightPdfGenerator` + templates purs `PdfTemplate` / `MailTemplate`.
- **Scan de schéma multi-base** : drivers MySQL/MariaDB, PostgreSQL, SQLite et
  SQL Server via `DatabaseDriverFactory`, enrichissement des colonnes par le
  serveur (envoi par lots).
- **Commandes Artisan** : `blunx:init`, `blunx:edit`, `blunx:setup`,
  `blunx:feedback`, `blunx:run-insights`.
- **Planificateur horaire** pour les insights « due » (`blunx-insights`).
- **Client Hub** (`BlunxApiClient`) : analyse d'insights, rapport, enrichissement
  de schéma et génération de requêtes de référence.
- **Consommation SSE** (`SseClient`) avec gestion des erreurs et du
  `SCHEMA_NOT_FOUND` (renvoi automatique sans `schema_id`).
- **Cache** des métadonnées de schéma en base (`blunx_cache`, 48 h).
- **Traductions** françaises et anglaises (`errors`, `insight`, `mail`,
  `pdf`, `data`).

### Corrigé
- `InsightPdfGenerator::generate()` : extraction du HTML dans
  `PdfTemplate::buildHtml()` avec date injectable pour un rendu déterministe.

### Sécurité
- Les clés API ne sont jamais transmises dans le corps des requêtes HTTP :
  elles passent par les en-têtes `X-Blunx-Key` et `X-Blunx-LLM-Key`.
- Vérification des certificats SSL activée sur tous les appels sortants.
