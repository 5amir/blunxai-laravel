# Changelog

All notable changes to `blunx/ai` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
versioning follows [SemVer](https://semver.org/).

## [1.0.3] - 2026-09-05

### Changed
- ** Request timeout raised to a configurable 600 s (10 min)**:
  `SseClient` (SSE streams) and `BlunxApiClient` (JSON endpoints: insights,
  schema, reference) are no longer cut at 150 s when the LLM takes a long time
  to reason. New `blunx.request_timeout` key (`BLUNX_REQUEST_TIMEOUT`, default
  `600`; `0` = no timeout — an SSE stream then ends only when the server closes
  it or emits an `error` event). The SSE proxy's `set_time_limit`
  (`BlunxApiController`) follows the same value. Parity kept with the Node
  package (`requestTimeout`).

## [1.0.0] - 2026-08-11

First public release.

### Added
- **Full conversational pipeline**: natural-language question → generated SQL →
  local read-only execution → validation → final synthesis, streamed over SSE
  via `POST /api/blunx/stream`.
- **REST API** under `/api/blunx`: conversations, messages, dashboards, widgets,
  insights, feedback, SQL execution and widget execution.
- **`DataExecutorAgent`**: local query execution with a preview (statistical
  summary) or full results.
- **Defense in depth**:
  - `SqlGuard`: strict read-only `SELECT`/`WITH` validation (DML/DDL keywords,
    dangerous system functions, `SELECT ... INTO`).
  - Dedicated **read-only** DB connection (role `SELECT`/`USAGE` only),
    supported via connection URL (DSN) or separate credentials.
  - `AccessRules`: negative-approach RBAC (forbidden tables, columns and rows),
    with the `USER_ID` placeholder replaced at execution time.
- **Dashboards & widgets**: create, edit, delete, run queries and generate
  reference queries through the server.
- **Automated insights**:
  - `GenerateWidgetInsightJob` (9 steps) with automatic rescheduling
    (daily / weekly / monthly) and a one-pending-job-per-widget limit.
  - **PDF reports** (dompdf) and **emails** with the PDF attached, conditional
    notification based on the priority threshold.
  - `InsightPdfGenerator` + pure `PdfTemplate` / `MailTemplate` templates.
- **Multi-database schema scanning**: MySQL/MariaDB, PostgreSQL, SQLite and SQL
  Server drivers via `DatabaseDriverFactory`, with server-side column
  enrichment (batched).
- **Artisan commands**: `blunx:init`, `blunx:edit`, `blunx:setup`,
  `blunx:feedback`, `blunx:run-insights`.
- **Hourly scheduler** for "due" insights (`blunx-insights`).
- **Hub client** (`BlunxApiClient`): insight analysis, report, schema
  enrichment and reference-query generation.
- **SSE consumption** (`SseClient`) with error handling and `SCHEMA_NOT_FOUND`
  retry (automatic resend without `schema_id`).
- **Schema metadata cache** in database (`blunx_cache`, 48 h).
- **French and English translations** (`errors`, `insight`, `mail`, `pdf`,
  `data`).

### Fixed
- `InsightPdfGenerator::generate()`: HTML extracted into
  `PdfTemplate::buildHtml()` with an injectable date for deterministic
  rendering.

### Security
- API keys are never sent in HTTP request bodies: they travel in the
  `X-Blunx-Key` and `X-Blunx-LLM-Key` headers.
- SSL certificate verification enabled on all outbound calls.
