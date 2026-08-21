<?php

namespace Blunx\AI\Agents;

use Illuminate\Support\Facades\Log;
use Blunx\AI\DTO\QueryResult;
use Blunx\AI\DTO\QueryItem;
use Blunx\AI\DTO\ExecutionResult;
use Blunx\AI\Support\BlunxDatabase;
use Blunx\AI\Support\DataSummarizer;
use Blunx\AI\Support\SqlGuard;

/**
 * Executes AI-generated SQL locally, in read-only mode.
 *
 * Every query is checked by SqlGuard before execution. In preview mode the
 * results are summarized (statistics + first rows); otherwise full rows are
 * returned. Blocked or failed queries produce warnings instead of aborting.
 */
class DataExecutorAgent
{
    /**
     * Execute all queries of a QueryResult.
     *
     * @param  QueryResult $queryResult Queries to run.
     * @param  bool        $previewOnly When true, return a statistical summary
     *                                  and only the first 5 rows.
     * @return ExecutionResult
     */
    public function execute(QueryResult $queryResult, bool $previewOnly = true): ExecutionResult
    {
        $results  = [];
        $warnings = [];
        $meta     = [];
        $index    = 0;

        foreach ($queryResult->queries as $queryItem) {
            $sql    = $queryItem->sql;
            $uiHint = $queryItem->uiHint;

            // Safety net: never run a query that is not a read-only SELECT/WITH,
            // even if it was altered between generation and execution.
            $blockReason = SqlGuard::validate($sql);
            if ($blockReason !== null) {
                Log::warning('[Blunx AI] Query blocked (not read-only)', [
                    'sql'    => $sql,
                    'reason' => $blockReason,
                ]);
                $warnings[] = __("blunx::errors.query_skipped_not_read_only");
                continue;
            }

            try {
                $data      = collect(BlunxDatabase::select($sql))->map(fn($item) => (array)$item)->toArray();
                $totalRows = count($data);

                if ($previewOnly) {
                    $summaryData = DataSummarizer::summarize($data);
                    $rows        = array_slice($data, 0, 5);
                    $truncated   = $totalRows > 5;
                } else {
                    $rows        = $data;
                    $truncated   = false;
                    $summaryData = [];
                }

                $results[] = [
                    'index'                => $index,
                    'query'                => $sql,
                    'ui_hint'              => $uiHint,
                    'chart_type'           => $queryItem->chartType,
                    'rows'                 => $rows,
                    'summary_data'         => $summaryData,
                    'total_rows_available' => $totalRows,
                    'is_rows_truncated'    => $truncated,
                    'is_rows_empty'        => empty($data),
                ];

                $meta[] = ['query' => $sql, 'ui_hint' => $uiHint, 'chart_type' => $queryItem->chartType];
                $index++;

            } catch (\Throwable $e) {
                Log::error('[Blunx AI] SQL Error', [
                    'sql'   => $sql,
                    'error' => $e->getMessage()
                ]);
                $warnings[] = __("blunx::errors.sql_error", ["message" => $e->getMessage()]);
            }
        }

        return new ExecutionResult($results, $warnings, $meta);
    }
}