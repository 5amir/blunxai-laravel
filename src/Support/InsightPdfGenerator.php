<?php

namespace Blunx\AI\Support;

use Barryvdh\DomPDF\PDF;
use Blunx\AI\DTO\InsightResult;
use Blunx\AI\Models\BlunxDashboardWidget;
use Blunx\AI\Models\BlunxDashboard;

/**
 * Generates the insight PDF report (A4 portrait) with dompdf.
 */
class InsightPdfGenerator
{
    /**
     * Render the report HTML into a PDF binary string.
     */
    public function generate(
        string               $reportHtml,
        InsightResult        $insightResult,
        BlunxDashboardWidget $widget,
        BlunxDashboard       $dashboard,
        ?string              $date = null,
    ): string {
        $severity = $insightResult->severity;
        $type     = $insightResult->type;

        $date ??= now()->format('d/m/Y - H:i');

        $fullHtml = PdfTemplate::buildHtml(
            $reportHtml,
            $severity,
            $type,
            $insightResult->message,
            $widget->title,
            $dashboard->name,
            $this->locale(),
            $date,
        );

        /** @var PDF $pdf */
        $pdf = app(PDF::class);

        return $pdf->loadHTML($fullHtml)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false
            ])
            ->output();
    }

    protected function locale(): string
    {
        return config('blunx.locale', 'fr');
    }
}
