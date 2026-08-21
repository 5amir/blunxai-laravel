<?php

namespace Blunx\AI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Blunx\AI\Services\BlunxApiClient;
use Blunx\AI\Support\Database\DatabaseDriverFactory;
use Blunx\AI\Support\BlunxDatabase;

/**
 * blunx:edit — edit the schema manually or re-index it differentially.
 *
 * Differential re-indexing detects new/removed tables and columns and asks
 * the cloud to enrich only the new elements, preserving existing descriptions.
 */
class EditCommand extends Command
{
    protected $signature   = 'blunx:edit';
    protected $description = 'Edit the Blunx AI schema — manual edit or differential re-indexing';

    protected $driver;

    public function __construct(protected BlunxApiClient $api)
    {
        parent::__construct();
        $this->driver = DatabaseDriverFactory::fromEnv();
    }

    public function handle(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $path = storage_path('app/blunx_schema.json');

        if (!File::exists($path)) {
            $this->error("❌ Schema file not found. Run first: php artisan blunx:init");
            return;
        }

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('🧠 <info>Blunx AI — Schema Editor</info>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $options = [
            '✏️  Manual edit — open and edit the JSON file directly',
            '🔄  Differential re-indexing — sync with database changes',
        ];

        $choice = $this->choice('What do you want to do?', $options, 0);

        if ($choice === $options[0]) {
            $this->handleManualEdit($path);
        } elseif ($choice === $options[1]) {
            $this->handleDiffReindex($path);
        } else {
            $this->error('Invalid choice.');
        }
    }

    // ══════════════════════════════════════════════════════════════
    // MODE 1 — Manual edit
    // ══════════════════════════════════════════════════════════════

    protected function handleManualEdit(string $path): void
    {
        $this->line('');
        $this->line('🔓 <info>Unlocking schema...</info>');

        chmod($path, 0644);

        $data = json_decode(File::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ JSON file is corrupted. Re-run blunx:init to regenerate it.');
            return;
        }

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->warn('📂 File ready for editing: storage/app/blunx_schema.json');
        $this->info('💡 Edit the "description" fields for each column, then come back here.');
        $this->line('');

        if (!$this->confirm('Done with your edits?', true)) {
            $this->warn('⚠️  Changes not validated. File remains unlocked (0644).');
            return;
        }

        $updated = json_decode(File::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ JSON syntax error in your edits. Fix and re-run.');
            chmod($path, 0644);
            return;
        }

        File::put($path, json_encode($updated, JSON_UNESCAPED_UNICODE));
        chmod($path, 0444);

        // Vider le cache
        DB::connection(BlunxDatabase::mainConnection())->table('blunx_cache')->delete();

        $this->info('⚡ Schema optimized and re-locked successfully!');
    }

    // ══════════════════════════════════════════════════════════════
    // MODE 2 — Differential re-indexing
    // ══════════════════════════════════════════════════════════════

    protected function handleDiffReindex(string $path): void
    {
        $this->line('');
        $this->info('🔍 <info>Analyzing differences...</info>');
        $this->line('');

        $database = $this->getDatabase();
        $excluded = $this->excludedTables();

        chmod($path, 0644);
        $existing = json_decode(File::get($path), true) ?? [];

        $currentTables  = $this->driver->getTables($database, $excluded);
        $existingTables = array_keys($existing);

        $newTables     = array_diff($currentTables, $existingTables);
        $removedTables = array_diff($existingTables, $currentTables);

        $newColumns     = [];
        $removedColumns = [];

        foreach (array_intersect($currentTables, $existingTables) as $table) {
            $currentCols  = Schema::connection(BlunxDatabase::mainConnection())->getColumnListing($table);
            $existingCols = collect($existing[$table]['columns'] ?? $existing[$table] ?? [])
                ->pluck('name')
                ->toArray();

            $added   = array_diff($currentCols, $existingCols);
            $removed = array_diff($existingCols, $currentCols);

            if (!empty($added))   $newColumns[$table]     = array_values($added);
            if (!empty($removed)) $removedColumns[$table] = array_values($removed);
        }

        // Summary
        $unchanged = count($currentTables) - count($newTables) - count(array_keys($newColumns));
        $this->line("  ✅ <info>{$unchanged} table(s) unchanged</info> — descriptions preserved");

        foreach ($newTables as $t)
            $this->line("  ➕ New table: <comment>{$t}</comment>");
        foreach ($removedTables as $t)
            $this->line("  ➖ Removed table: <comment>{$t}</comment>");
        foreach ($newColumns as $t => $cols)
            $this->line("  ➕ New columns in <comment>{$t}</comment>: " . implode(', ', $cols));
        foreach ($removedColumns as $t => $cols)
            $this->line("  ➖ Removed columns in <comment>{$t}</comment>: " . implode(', ', $cols));

        $this->line('');

        if (empty($newTables) && empty($removedTables) && empty($newColumns) && empty($removedColumns)) {
            $this->info('✅ Schema is already in sync with the database. No changes needed.');
            $this->resyncRelations($existing, $currentTables, $database);
            File::put($path, json_encode($existing, JSON_UNESCAPED_UNICODE));
            chmod($path, 0444);
            return;
        }

        if (!$this->confirm('Apply these changes?', true)) {
            $this->warn('Cancelled.');
            chmod($path, 0444);
            return;
        }

        // 1. Remove removed tables
        foreach ($removedTables as $t) {
            unset($existing[$t]);
        }

        // 2. Remove removed columns
        foreach ($removedColumns as $table => $cols) {
            if (isset($existing[$table]['columns'])) {
                $existing[$table]['columns'] = collect($existing[$table]['columns'])
                    ->filter(fn($c) => !in_array($c['name'], $cols))
                    ->values()
                    ->toArray();
            }
        }

        // 3. Prepare new elements for the server
        $toEnrich = [];

        foreach ($newTables as $table) {
            $toEnrich[$table] = $this->getRawColumns($table, $database);
        }

        foreach ($newColumns as $table => $cols) {
            $toEnrich["__partial__{$table}"] = collect($cols)
                ->map(fn($col) => $this->getRawColumn($table, $col, $database))
                ->toArray();
        }

        // 4. Enrich via server (new elements only)
        if (!empty($toEnrich)) {
            $this->line('');
            $this->info('🤖 Sending new elements to BlunxAI server...');

            $enriched = $this->enrichWithServer($toEnrich);

            foreach ($newTables as $table) {
                $existing[$table] = [
                    'columns'   => $enriched[$table] ?? $toEnrich[$table],
                    'relations' => $this->driver->getRelations($table, $database),
                ];
            }

            foreach ($newColumns as $table => $cols) {
                $key     = "__partial__{$table}";
                $newCols = $enriched[$key] ?? $toEnrich[$key];

                if (isset($existing[$table]['columns'])) {
                    foreach ($newCols as $newCol) {
                        $existing[$table]['columns'][] = $newCol;
                    }
                }
            }
        }

        // 5. Re-sync relations
        $this->resyncRelations($existing, $currentTables, $database);

        // 6. Save readable JSON for review
        File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->warn('📂 Schema updated: storage/app/blunx_schema.json');
        $this->info('💡 Review the new AI-generated descriptions before locking.');
        $this->line('');

        if (!$this->confirm('Validate and re-lock the schema?', true)) {
            $this->warn('⚠️  Schema not locked. Re-run blunx:edit (manual edit) to lock later.');
            return;
        }

        $final = json_decode(File::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ JSON syntax error. Fix the file and re-run blunx:edit (manual edit).');
            return;
        }

        File::put($path, json_encode($final, JSON_UNESCAPED_UNICODE));
        chmod($path, 0444);

        $this->line('');
        DB::connection(BlunxDatabase::mainConnection())->table('blunx_cache')->delete();

        $this->info('🚀 Schema synced, optimized and re-locked successfully!');
    }

    // ── Helpers ───────────────────────────────────────────────────

    protected function enrichWithServer(array $toEnrich): array
    {
        // 1. Send only the "columns" part of each table
        $forServer = $toEnrich;

        // 2. Split the tables into batches of 10
        $chunks = array_chunk($forServer, 10, true);
        $enriched = [];
        $totalChunks = count($chunks);

        $this->line("📦 Schema split into {$totalChunks} batch(es) for processing.");

        foreach ($chunks as $index => $chunk) {
            $currentBatch = $index + 1;
            $this->line("   🚀 Sending batch <comment>{$currentBatch}/{$totalChunks}</comment> (" . count($chunk) . " tables)...");

            try {
                $enrichedBatch = $this->api->schemaEnrich($chunk);
                $enriched = array_merge($enriched, $enrichedBatch);
            } catch (\Throwable $e) {
                $this->warn("   ⚠️  Batch {$currentBatch}/{$totalChunks} failed: " . $e->getMessage());
                $this->warn('   💡 Partial enrichment: some tables will stay empty.');
                $enriched = array_merge($enriched, $chunk);
            }
        }

        // 3. Reinject the enriched columns (normalize ['columns' => [...]] → flat list)
        foreach ($enriched as $table => $columns) {
            if (isset($toEnrich[$table])) {
                $colDef = is_array($columns) && isset($columns['columns']) ? $columns['columns'] : $columns;
                $toEnrich[$table] = $colDef;
            }
        }

        if ($enriched === $forServer) {
            $this->warn('⚠️  Server returned input unchanged — descriptions left empty.');
        }

        return $toEnrich;
    }

    protected function resyncRelations(array &$schema, array $tables, string $database): void
    {
        foreach ($tables as $table) {
            if (!isset($schema[$table])) continue;

            if (!isset($schema[$table]['columns'])) {
                $schema[$table] = [
                    'columns'   => $schema[$table],
                    'relations' => [],
                ];
            }

            $schema[$table]['relations'] = $this->driver->getRelations($table, $database);
        }
    }

    protected function getRawColumns(string $table, string $database): array
    {
        return collect(Schema::connection(BlunxDatabase::mainConnection())->getColumnListing($table))
            ->map(fn($col) => $this->getRawColumn($table, $col, $database))
            ->toArray();
    }

    protected function getRawColumn(string $table, string $col, string $database): array
    {
        $type = $this->driver->getColumnType($table, $col, $database);
        return ['name' => $col, 'type' => $type, 'description' => ''];
    }

    protected function getDatabase(): string
    {
        return config('database.connections.' . config('database.default') . '.database');
    }

    protected function excludedTables(): array
    {
        return [
            'migrations', 'failed_jobs', 'cache', 'cache_locks',
            'jobs', 'job_batches', 'password_reset_tokens', 'sessions',
            'personal_access_tokens', 'blunx_conversations', 'blunx_messages',
            'blunx_dashboards', 'blunx_dashboard_widgets', 'blunx_insights',
            'blunx_widget_insight_settings', 'blunx_feedbacks', 'blunx_cache',
        ];
    }
}