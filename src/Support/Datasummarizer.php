<?php

namespace Blunx\AI\Support;

/**
 * Compacts result rows into small statistical summaries.
 *
 * Used to describe a dataset to the AI without sending thousands of rows:
 * numeric columns are summarized (sum, avg, median, min/max, top/bottom 5)
 * and categorical columns are reduced to their most frequent values.
 */
class DataSummarizer
{
    /**
     * Summarize a dataset into compact statistics, regardless of row count.
     *
     * @param  array $rows   Raw data (array of arrays).
     * @param  int   $sample Number of sample rows to include.
     * @return array         Compact summary.
     */
    public static function summarize(array $rows): array
    {
        if (empty($rows)) {
            return ['row_count' => 0, 'columns' => [], 'numerics' => new \stdClass(), 'categoricals' => new \stdClass()];
        }

        $columns  = array_keys($rows[0]);
        $numerics = [];
        $cats     = [];

        // Classify each column: numeric or categorical.
        foreach ($columns as $col) {
            $values = array_column($rows, $col);
            $numericValues = array_filter($values, fn($v) => is_numeric($v) && $v !== '');

            if (count($numericValues) / count($values) >= 0.8) {
                // Numeric column
                $nums = array_map('floatval', $numericValues);
                sort($nums);
                $count = count($nums);
                $sum   = array_sum($nums);
                $avg   = $count > 0 ? $sum / $count : 0;

                // Top 5 and bottom 5 values
                $sorted = $nums;
                rsort($sorted);
                $top5 = array_slice($sorted, 0, 5);
                $bottom5 = array_slice($nums, 0, 5);

                // Median
                $mid    = (int) floor($count / 2);
                $median = $count % 2 === 0 && $count > 0
                    ? ($nums[$mid - 1] + $nums[$mid]) / 2
                    : ($nums[$mid] ?? 0);

                $minVal = round(min($nums), 2);
                $maxVal = round(max($nums), 2);

                // Find the full row for min and max
                $minRow = null;
                $maxRow = null;
                foreach ($rows as $row) {
                    if (isset($row[$col])) {
                        $floatVal = (float) $row[$col];
                        if ($floatVal == $minVal && $minRow === null) {
                            $minRow = $row;
                        }
                        if ($floatVal == $maxVal && $maxRow === null) {
                            $maxRow = $row;
                        }
                    }
                }

                $numerics[$col] = [
                    'sum'     => round($sum, 2),
                    'avg'     => round($avg, 2),
                    'median'  => round($median, 2),
                    'min'     => $minVal,
                    'max'     => $maxVal,
                    'top5'    => array_map(fn($v) => round($v, 2), $top5),
                    'bottom5' => array_map(fn($v) => round($v, 2), $bottom5),
                    'min_row' => $minRow,
                    'max_row' => $maxRow,
                ];
            } else {
                // Categorical column — count distinct values
                $counts = array_count_values(array_map('strval', $values));
                arsort($counts);
                // Keep only the 10 most frequent values
                $cats[$col] = array_slice($counts, 0, 10, true);
            }
        }

        return [
            'row_count'    => count($rows),
            'columns'      => $columns,
            'numerics'     => (object) $numerics,
            'categoricals' => (object) $cats,
        ];
    }

    /**
     * Compute structural concentration over ALL rows.
     *
     * Runs locally so thousands of rows never leave the server.
     *
     * @param  array $rows All raw rows.
     * @return array       [col => [total, top1_share, top3_share, top5_share, n_entities]]
     */
    public static function computeConcentration(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $columns    = array_keys($rows[0]);
        $concentration = [];

        foreach ($columns as $col) {
            $values = array_column($rows, $col);
            $numericValues = array_filter($values, fn($v) => is_numeric($v) && $v !== '');

            if (count($numericValues) / count($values) < 0.8) {
                continue; // not a numeric column
            }

            $nums = array_map('floatval', $numericValues);
            $total = array_sum($nums);

            if ($total == 0) {
                continue;
            }

            rsort($nums);
            $nEntities = count($nums);

            $top1Share = $nums[0] / $total;

            $top3Sum = 0;
            for ($i = 0; $i < min(3, $nEntities); $i++) {
                $top3Sum += $nums[$i];
            }
            $top3Share = $top3Sum / $total;

            $top5Sum = 0;
            for ($i = 0; $i < min(5, $nEntities); $i++) {
                $top5Sum += $nums[$i];
            }
            $top5Share = $top5Sum / $total;

            $concentration[$col] = [
                'total'      => round($total, 2),
                'top1_share' => round($top1Share, 4),
                'top3_share' => round(min($top3Share, 1.0), 4),
                'top5_share' => round(min($top5Share, 1.0), 4),
                'n_entities' => $nEntities,
            ];
        }

        return $concentration;
    }

}