# Sécurité

Nous prenons la sécurité de `blunx/ai` au sérieux. Merci de suivre cette
procédure pour signaler une vulnérabilité.

## Versions supportées

| Version | Supporté |
|---|---|
| dernière `MAJEURE.MINEURE` | ✅ |
| versions précédentes | ❌ |

## Signaler une vulnérabilité

**Ne créez pas d'issue publique.** Pour signaler un problème de sécurité,
contactez l'équipe à : `support@blunxai.com`

Dans votre rapport, incluez si possible :

- le type de vulnérabilité (injection SQL, exfiltration de données, etc.) ;
- le fichier / module concerné ;
- les étapes de reproduction minimales ;
- l'impact potentiel ;
- une proposition de correctif (facultatif).

## Délai de traitement

- **Accusé de réception** : sous 48 h ouvrées.
- **Mise à jour de statut** : sous 5 jours ouvrés.
- **Correctif** : publié dès que possible, selon la gravité.

Nous vous remercions de ne pas divulguer publiquement le problème avant la
publication du correctif.

## Périmètre

Sont considérés hors périmètre (non traités) :

- l'abus de l'API du Hub côté serveur (clés volées, quota dépassé) ;
- les failles des dépendances tierces (dompdf, guzzle) — à signaler à leurs
  mainteneurs respectifs ;
- une configuration inappropriée de l'application hôte (utilisateur BDD en
  écriture, clés API exposées côté client).

## Bonnes pratiques recommandées

- Configurez un **utilisateur BDD en lecture seule** (`SELECT`/`USAGE` uniquement)
  pour l'exécution des requêtes Blunx — c'est le verrou de sécurité principal.
- Ne diffusez jamais `BLUNX_API_KEY` / `BLUNX_LLM_API_KEY` côté client : ces
  clés restent côté serveur et transitent par en-têtes HTTP.
- Filtrez les accès par rôle dans `config/blunx_access.php` (tables, colonnes,
  lignes interdites).
