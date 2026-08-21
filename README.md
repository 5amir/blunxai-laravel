<p align="center">
  <img src="https://blunxai.com/logo.png" width="120" alt="Blunx AI" />
</p>

<h1 align="center">Blunx AI</h1>

<p align="center">
  Business Intelligence AI Agent for Laravel.
  <br />
  Ask questions in natural language, get SQL, charts, tables and automated insights — directly from your database.
</p>

<p align="center">
  <a href="https://packagist.org/packages/blunx/ai"><img src="https://img.shields.io/packagist/v/blunx/ai" alt="Packagist version"></a>
  <a href="https://packagist.org/packages/blunx/ai"><img src="https://img.shields.io/packagist/dt/blunx/ai" alt="Packagist downloads"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/blunx/ai" alt="License"></a>
</p>

---

## About

**Blunx AI** turns your Laravel database into a conversational business intelligence tool.

- Users ask questions in **natural language**.
- Blunx generates and executes **read-only SQL** against your database.
- The answer comes back as a **rich interface**: charts, tables, and a written summary.
- Optionally, widgets can be monitored automatically and produce **scheduled insights** with **PDF reports** and email notifications.

All AI processing happens on the **BlunxAI cloud** through a secure proxy — your LLM API keys are never exposed to your users, and your database is never called directly by the AI.

## Features

- 🧠 Natural language → SQL → results pipeline (Server-Sent Events streaming)
- 🔒 Defense in depth: read-only database role + `SqlGuard` validation of every query
- 🛡️ Role-based access control (forbidden tables, columns, and row-level filtering)
- 📊 Conversations, dashboards and widgets (chart / table) stored locally
- 📈 Automated insights with severity scoring, PDF generation and email alerts
- 🗄️ MySQL, PostgreSQL, SQLite and SQL Server schema scanning
- 🌍 French and English localization

## Requirements

- PHP 8.1+
- Laravel 10 / 11
- A BlunxAI account (application API key)

## Installation

```bash
composer require blunx/ai
```

Laravel auto-discovers the package. Then publish the configuration:

```bash
php artisan vendor:publish --tag=blunx-config
```

## Configuration

Set the following environment variables in your `.env`:

```env
# BlunxAI application key (from your BlunxAI dashboard)
BLUNX_API_KEY=

# LLM provider key, forwarded by Blunx to the model provider
BLUNX_LLM_API_KEY=

# Database used by Blunx. Leave empty to use the default connection.
# A dedicated read-only role is strongly recommended:
BLUNX_DB_CONNECTION=
BLUNX_READONLY_DB_USERNAME=
BLUNX_READONLY_DB_PASSWORD=

# Optional: full connection URL (DSN) — overrides the connection above
#   mysql://user:pass@host:3306/db?sslmode=require
#   postgres://user:pass@host:5432/db?sslmode=verify-full&sslrootcert=ca.pem
#   sqlite:///var/data/blunx.sqlite
BLUNX_DB_URL=

# Defaults
BLUNX_LOCALE=fr
BLUNX_CURRENCY_CODE=EUR
BLUNX_CURRENCY_SYMBOL=€
BLUNX_CURRENCY_LOCALE=fr_FR
```

> **Security**: the queries executed by Blunx are always read-only. Configure a
> database user with only `SELECT`/`USAGE` privileges — this is the primary
> lock; `SqlGuard` is the second one.

### Access rules

Publish `config/blunx_access.php` and define, per role, what is **forbidden**:

```php
'vendeur' => [
    'forbidden_tables'  => ['salaires', 'comptabilite'],
    'forbidden_columns' => ['users' => ['salaire', 'numero_secu']],
    'row_level'         => [
        'orders' => ['column' => 'user_id', 'value' => 'USER_ID'],
    ],
],
```

`USER_ID` is replaced at runtime with the authenticated user's ID.

## Usage

### 1. Initialize the schema

Scan your database and generate the enriched schema (sent to Blunx for enrichment):

```bash
php artisan blunx:init
```

Review and complete the generated `storage/app/blunx_schema.json`, then:

```bash
php artisan blunx:setup
```

This creates the `blunx_*` tables and locks the schema.

### 2. API routes

The package exposes an authenticated REST API under `/api/blunx`:

| Method | Endpoint | Description |
| ------ | -------- | ----------- |
| GET / POST | `/api/blunx/conversations` | List / create conversations |
| GET | `/api/blunx/conversations/{uuid}/messages` | Conversation messages |
| POST | `/api/blunx/messages` | Save a message |
| POST | `/api/blunx/stream` | Ask a question (SSE streaming) |
| GET / POST | `/api/blunx/dashboards` | List / create dashboards |
| POST | `/api/blunx/widgets` | Create a widget |
| POST | `/api/blunx/widgets/{uuid}/execute` | Run a widget query |
| GET | `/api/blunx/insights/{dashUuid}` | Dashboard insights |
| POST | `/api/blunx/insight-settings` | Configure scheduled insights |
| POST | `/api/blunx/feedback` | Submit user feedback |

All routes require an authenticated session (the `blunx` middleware group uses your app's `auth` guard) and are rate-limited.

### 3. Scheduled insights

The package registers an hourly scheduler that runs due insights:

```bash
php artisan schedule:run
```

Insights with a high enough priority trigger an email containing a PDF report.
You can also run due insights manually:

```bash
php artisan blunx:run-insights
```

### Artisan commands

| Command | Description |
| ------- | ----------- |
| `php artisan blunx:init` | Scan the database and generate the schema |
| `php artisan blunx:edit` | Edit the schema or re-index it differentially |
| `php artisan blunx:setup` | Create the Blunx tables and lock the schema |
| `php artisan blunx:feedback` | Review unresolved user feedback |
| `php artisan blunx:run-insights` | Run due insight jobs now |

## How it works

```
User message ──▶ access rules + history (last 8 messages)
      └─▶ POST /api/v1/chat/queries        (Blunx generates SQL)
      └─▶ DataExecutorAgent.execute()      (local, SELECT only, preview)
      └─▶ POST /api/v1/chat/validate       (Blunx validates the queries)
      └─▶ DataExecutorAgent.execute()      (local, full results)
      └─▶ POST /api/v1/chat/synthesize     (Blunx builds the final answer)
      └─▶ Assistant message saved          (with rendered interface)
```

The client never talks to the AI directly — every call goes through the local
Blunx proxy, so API keys stay server-side.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details on the development
workflow and the [CODE OF CONDUCT](CODE_OF_CONDUCT.md) for the expected
community standards.

## Security

If you discover a security vulnerability, please follow the disclosure
procedure described in [SECURITY.md](SECURITY.md). Please do not open a public
issue for security problems.

## License

Blunx AI is open-sourced software licensed under the [MIT license](LICENSE).
