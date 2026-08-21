<?php

namespace Blunx\AI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Blunx\AI\Services\BlunxApiClient;
use Blunx\AI\Support\Database\DatabaseDriverFactory;
use Blunx\AI\Support\BlunxDatabase;
use Illuminate\Support\Facades\Log;

/**
 * blunx:init — scan the database and generate the enriched schema.
 *
 * Columns are enriched by the Blunx cloud in batches; the result is written
 * to storage/app/blunx_schema.json.
 */
class InitCommand extends Command
{
    protected $signature   = 'blunx:init';
    protected $description = 'Scan the database and generate the Blunx AI schema';

    protected $driver;

    public function __construct(protected BlunxApiClient $api)
    {
        parent::__construct();
        $this->driver = DatabaseDriverFactory::fromEnv();
    }

    public function handle(): void
    {
        // Remove the PHP execution time limit for this command.
        set_time_limit(0);

        // Optional: raise memory if the database has thousands of columns.
        ini_set('memory_limit', '512M');

        $path = storage_path('app/blunx_schema.json');

        if (File::exists($path)) {
            $this->warn('⚠️  Schema already exists: storage/app/blunx_schema.json');
            $this->line('     → blunx:edit  to update descriptions');
            $this->line('     → blunx:setup to lock and create tables');
            return;
        }

        $this->renderWelcomeBanner();

        $database = $this->getDatabase();
        $tables   = $this->driver->getTables($database, $this->excludedTables());

        if (empty($tables)) {
            $this->error("❌ No tables found in database: {$database}.");
            return;
        }

        $this->line('');
        $this->info("🔍 Scanning database: <comment>{$database}</comment>");

        $schemaData = [];

        foreach ($tables as $tableName) {
            $this->line("  📋 <info>{$tableName}</info>");
            $schemaData[$tableName] = [
                'columns'   => $this->getColumns($tableName, $database),
                'relations' => $this->driver->getRelations($tableName, $database),
            ];
        }

        // ── Enrichissement via serveur ──
        $this->line('');
        $this->info('🤖 Sending schema to BlunxAI server for enrichment...');

        $schemaData = $this->enrichWithServer($schemaData);

        File::put($path, json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->info('✅ Schema generated: <comment>storage/app/blunx_schema.json</comment>');
        $this->line('');
        $this->warn('❗ IMPORTANT: Review and enrich descriptions with your business rules.');
        $this->line('   Relations were detected automatically from the database.');
        $this->line('');
        $this->line('👉 When ready, run: <info>php artisan blunx:setup</info>');
        $this->line('');
    }

    protected function enrichWithServer(array $schemaData): array
    {
        // 1. Send only the "columns" part of each table.
        $forServer = collect($schemaData)->map(fn($t) => $t['columns'])->toArray();

        // 2. Split the tables into batches of 10 (keys preserved).
        $chunks = array_chunk($forServer, 10, true);
        $enriched = [];
        $totalChunks = count($chunks);

        $this->line("📦 Schema split into {$totalChunks} batch(es) for processing.");

        foreach ($chunks as $index => $chunk) {
            $currentBatch = $index + 1;
            $this->line("   🚀 Sending batch <comment>{$currentBatch}/{$totalChunks}</comment> (" . count($chunk) . " tables)...");

            try {
                // Send the batch of tables to the server
                $enrichedBatch = $this->api->schemaEnrich($chunk);
                
                // Merge the enriched result into the global array
                $enriched = array_merge($enriched, $enrichedBatch);
                
            } catch (\Throwable $e) {
                $this->warn("   ⚠️  Batch {$currentBatch}/{$totalChunks} failed: " . $e->getMessage());
                $this->warn('   💡 Partial enrichment: some tables will stay empty.');
                
                // Fallback: keep the original non-enriched columns on failure
                $enriched = array_merge($enriched, $chunk);
            }
        }

        // 3. Reinject the enriched (or original) columns into $schemaData
        foreach ($enriched as $table => $columns) {
            if (isset($schemaData[$table])) {
                // The server returns each table as ['columns' => [...]] —
                // normalize to a flat array (expected schema structure).
                $colDef = is_array($columns) && isset($columns['columns']) ? $columns['columns'] : $columns;
                $schemaData[$table]['columns'] = $colDef;
            }
        }

        return $schemaData;
    }

    protected function getColumns(string $table, string $database): array
    {
        return collect(Schema::connection(BlunxDatabase::mainConnection())->getColumnListing($table))
            ->map(function ($col) use ($table, $database) {
                $type = $this->driver->getColumnType($table, $col, $database);
                return ['name' => $col, 'type' => $type, 'description' => ''];
            })
            ->toArray();
    }

    protected function getDatabase(): string
    {
        return config('database.connections.' . BlunxDatabase::mainConnection() . '.database');
    }

    protected function excludedTables(): array
    {
        return config('blunx.excluded_tables', []);
    }


private function renderWelcomeBanner(): void
    {
        $this->newLine();
        // ASCII Art pour "BLUNX AI" en Cyan
        $this->line('<fg=cyan>██████╗ ██╗     ██╗   ██╗███╗   ██╗██╗   ██╗    █████╗ ██╗</fg=cyan>');
        $this->line('<fg=cyan>██╔══██╗██║     ██║   ██║████╗  ██║╚██╗ ██╔╝   ██╔══██╗██║</fg=cyan>');
        $this->line('<fg=cyan>██████╔╝██║     ██║   ██║██╔██╗ ██║ ╚███╔╝     ███████║██║</fg=cyan>');
        $this->line('<fg=cyan>██╔══██╗██║     ██║   ██║██║╚██╗██║ ██╔██╗     ██╔══██║██║</fg=cyan>');
        $this->line('<fg=cyan>██████╔╝███████╗╚██████╔╝██║ ╚████║██╔╝ ██╗    ██║  ██║██║</fg=cyan>');
        $this->line('<fg=cyan>╚═════╝ ╚══════╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝    ╚═╝  ╚═╝╚═╝</fg=cyan>');
        $this->newLine();
        $this->line('<fg=green> Business Intelligence AI Agent for Laravel</fg=green>');
        $this->line('<fg=gray>  Version ' . config('app.version', '1.0.0') . '  •  https://blunxai.com</fg=gray>');
        $this->newLine();
        $this->line('<fg=yellow>═════════════════════════════════════════════════════════════</fg=yellow>');
        $this->line('<fg=yellow>           Welcome to the BlunxAI Installation</fg=yellow>');
        $this->line('<fg=yellow>═════════════════════════════════════════════════════════════</fg=yellow>');
        $this->newLine();
    }

}