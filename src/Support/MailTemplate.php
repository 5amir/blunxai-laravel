<?php

namespace Blunx\AI\Support;

/**
 * HTML template for the insight email.
 *
 * Pure function: it only depends on its arguments (locale, dashboard URL,
 * injected date), so the output is deterministic and testable.
 */
class MailTemplate
{
    /**
     * Build the insight email HTML.
     *
     * @param  array  $insight      ['severity' => string, 'type' => string, 'message' => string]
     * @param  array  $widget       ['title' => string]
     * @param  array  $dashboard    ['name' => string, 'uuid' => string]
     * @param  string $date         Formatted date 'dd/mm/YYYY à HH:MM' (injected, deterministic).
     */
    public static function buildHtml(
        array $insight,
        array $widget,
        array $dashboard,
        string $locale,
        string $dashboardUrl,
        string $date,
    ): string {
        $severity = $insight['severity'] ?? 'info';
        $type     = $insight['type'] ?? 'info';
        $message  = $insight['message'] ?? '';

        $badgeClass = match ($severity) {
            'critical' => 'badge-critical',
            'warning'  => 'badge-warning',
            default    => 'badge-info',
        };
        $badgeLabel = match ($severity) {
            'critical' => __('blunx::mail.badge.critical'),
            'warning'  => __('blunx::mail.badge.warning'),
            default    => __('blunx::mail.badge.info'),
        };
        $typeLabel = match ($type) {
            'anomaly' => __('blunx::mail.type.anomaly'),
            'trend'   => __('blunx::mail.type.trend'),
            'record'  => __('blunx::mail.type.record'),
            default   => __('blunx::mail.type.info'),
        };

        $dashboardName = $dashboard['name'] ?? '';
        $widgetTitle   = $widget['title'] ?? '';

        // Pre-compute (method calls are not interpolated in heredocs).
        $title          = self::e(__('blunx::mail.title'));
        $badge          = self::e($badgeLabel) . ' — ' . self::e($typeLabel);
        $msg            = self::e($message);
        $labelDash      = self::e(__('blunx::mail.dashboard'));
        $labelWidget    = self::e(__('blunx::mail.widget'));
        $labelGenAt     = self::e(__('blunx::mail.generated_at'));
        $dashName       = self::e($dashboardName);
        $wTitle         = self::e($widgetTitle);
        $dateStr        = self::e($date);
        $attachTitle    = self::e(__('blunx::mail.attachment_title'));
        $attachDesc     = self::e(__('blunx::mail.attachment_desc'));
        $dashUrl        = self::attr($dashboardUrl);
        $viewDash       = self::e(__('blunx::mail.view_dashboard'));
        $footerLine1    = self::e(__('blunx::mail.footer_line1'));
        $footerLine2    = self::e(__('blunx::mail.footer_line2'));

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { margin:0; padding:0; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f3f4f6; }
        .wrapper { max-width:560px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
        .header { background:#0f172a; padding:24px 32px; }
        .header-logo { color:#fff; font-size:20px; font-weight:700; }
        .header-logo span { color:#6366f1; }
        .body { padding:32px; }
        .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; margin-bottom:16px; }
        .badge-critical { background:#fee2e2; color:#dc2626; }
        .badge-warning  { background:#fef3c7; color:#d97706; }
        .badge-info     { background:#dbeafe; color:#2563eb; }
        .message { font-size:17px; font-weight:600; color:#111827; line-height:1.5; margin-bottom:20px; }
        .context { font-size:13px; color:#6b7280; margin-bottom:24px; }
        .context strong { color:#374151; }
        .pj-box { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:28px; display:flex; align-items:center; gap:12px; }
        .pj-icon { font-size:28px; }
        .pj-text { font-size:13px; color:#374151; }
        .pj-text strong { display:block; font-size:14px; color:#111827; margin-bottom:2px; }
        .btn { display:inline-block; background:#6366f1; color:#ffffff !important; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600; font-size:14px; }
        .footer { background:#f9fafb; padding:16px 32px; border-top:1px solid #e5e7eb; font-size:11px; color:#9ca3af; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <div class="header-logo">Blunx<span>AI</span></div>
    </div>

    <div class="body">

        <span class="badge {$badgeClass}">{$badge}</span>

        <div class="message">{$msg}</div>

        <div class="context">
            {$labelDash} : <strong>{$dashName}</strong><br>
            {$labelWidget} : <strong>{$wTitle}</strong><br>
            {$labelGenAt} : <strong>{$dateStr}</strong>
        </div>

        <div class="pj-box">
            <div class="pj-icon">📄</div>
            <div class="pj-text">
                <strong>{$attachTitle}</strong>
                {$attachDesc}
            </div>
        </div>

        <a href="{$dashUrl}" class="btn">
            {$viewDash} →
        </a>

    </div>

    <div class="footer">
        {$footerLine1}
        {$footerLine2}
    </div>

</div>
</body>
</html>
HTML;
    }

    /** Escape a value for HTML text content (blade {{ }} equivalent). */
    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }

    /** Escape a value for a double-quoted HTML attribute. */
    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
