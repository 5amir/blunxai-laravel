<?php

namespace Blunx\AI\Support;

/**
 * HTML template for the insight PDF report.
 *
 * Pure function: it only depends on its arguments (no current date, no
 * container), so the output is deterministic and testable.
 */
class PdfTemplate
{
    /**
     * Build the full A4 portrait PDF HTML.
     *
     * @param  string $reportHtml    Rendered report body.
     * @param  string $severity      critical | warning | info
     * @param  string $type          anomaly | trend | record | info
     * @param  string $message       Insight message.
     * @param  string $widgetTitle   Widget title.
     * @param  string $dashboardName Dashboard name.
     * @param  string $locale        Language (fr|en).
     * @param  string $date          Formatted date 'dd/mm/YYYY - HH:MM' (injected
     *                               to stay deterministic / testable).
     */
    public static function buildHtml(
        string $reportHtml,
        string $severity,
        string $type,
        string $message,
        string $widgetTitle,
        string $dashboardName,
        string $locale,
        string $date,
    ): string {
        $severityColor = match ($severity) {
            'critical' => '#dc2626',
            'warning'  => '#d97706',
            default    => '#2563eb',
        };

        $severityLabel = match ($severity) {
            'critical' => __('blunx::insight.severity.critical'),
            'warning'  => __('blunx::insight.severity.warning'),
            default    => __('blunx::insight.severity.info'),
        };

        $typeLabel = match ($type) {
            'anomaly' => __('blunx::insight.type.anomaly'),
            'trend'   => __('blunx::insight.type.trend'),
            'record'  => __('blunx::insight.type.record'),
            default   => __('blunx::insight.type.info'),
        };

        $message = htmlspecialchars($message, ENT_QUOTES);
        $dbName  = htmlspecialchars($dashboardName, ENT_QUOTES);
        $wTitle  = htmlspecialchars($widgetTitle, ENT_QUOTES);

        $reportTitle    = __('blunx::insight.report_title');
        $generatedAt    = __('blunx::insight.generated_at');
        $footerText     = __('blunx::insight.footer');
        $labelDashboard = __('blunx::pdf.dashboard');
        $labelWidget    = __('blunx::pdf.widget');
        $labelDate      = __('blunx::pdf.date');

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
<head>
<meta charset="UTF-8">
<title>{$reportTitle} — {$wTitle}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:13px; color:#1f2937; line-height:1.6; }

.header { background:#0f172a; color:#fff; padding:24px 32px; }
.header-logo { font-size:22px; font-weight:700; }
.header-logo span { color:#6366f1; }
.header-sub { font-size:11px; color:#94a3b8; margin-top:4px; }

.banner { background:{$severityColor}; color:#fff; padding:14px 32px; margin-bottom:24px; }
.banner .label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.85; }
.banner .msg { font-size:15px; font-weight:700; margin-top:4px; }

.meta-bar { display:table; width:100%; padding:0 32px 16px; border-bottom:1px solid #e5e7eb; margin-bottom:24px; }
.meta-item { display:table-cell; padding-right:32px; }
.meta-item label { font-size:10px; text-transform:uppercase; color:#9ca3af; display:block; letter-spacing:0.5px; }
.meta-item span { font-size:13px; font-weight:600; color:#374151; }

.body { padding:0 32px 32px; }
.body h2 { font-size:14px; font-weight:700; color:#111827; margin-top:22px; margin-bottom:8px;
           border-left:3px solid #6366f1; padding-left:10px; }
.body p { margin-bottom:10px; color:#374151; }
.body ul { margin:8px 0 12px 20px; }
.body li { margin-bottom:4px; color:#374151; }
.body strong { color:#111827; }

table { width:100%; border-collapse:collapse; margin:12px 0; font-size:12px; }
th { background:#f1f5f9; color:#374151; font-weight:700; padding:8px 10px;
     text-align:left; border:1px solid #e2e8f0; }
td { padding:7px 10px; border:1px solid #e2e8f0; color:#1f2937; }
tr:nth-child(even) td { background:#f9fafb; }

.score-low      { color:#6b7280; font-weight:600; }
.score-medium   { color:#d97706; font-weight:600; }
.score-high     { color:#dc2626; font-weight:600; }
.score-critical { color:#7c3aed; font-weight:700; }

.footer { border-top:1px solid #e5e7eb; margin-top:32px; padding:14px 32px;
          font-size:10px; color:#9ca3af; }
</style>
</head>
<body>

<div class="header">
    <div class="header-logo">Blunx<span>AI</span></div>
    <div class="header-sub">{$generatedAt} {$date}</div>
</div>

<div class="banner">
    <div class="label">{$typeLabel} &middot; {$severityLabel}</div>
    <div class="msg">{$message}</div>
</div>

<div class="meta-bar">
    <div class="meta-item"><label>{$labelDashboard}</label><span>{$dbName}</span></div>
    <div class="meta-item"><label>{$labelWidget}</label><span>{$wTitle}</span></div>
    <div class="meta-item"><label>{$labelDate}</label><span>{$date}</span></div>
</div>

<div class="body">
    {$reportHtml}
</div>

<div class="footer">
    {$footerText}
</div>

</body>
</html>
HTML;
    }
}
