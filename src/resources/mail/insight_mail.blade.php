<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('blunx::mail.title') }}</title>
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

        @php
            $badgeClass = match($insight->severity) { 'critical' => 'badge-critical', 'warning' => 'badge-warning', default => 'badge-info' };
            $badgeLabel = match($insight->severity) { 'critical' => __('blunx::mail.badge.critical'), 'warning' => __('blunx::mail.badge.warning'), default => __('blunx::mail.badge.info') };
            $typeLabel  = match($insight->type) { 'anomaly' => __('blunx::mail.type.anomaly'), 'trend' => __('blunx::mail.type.trend'), 'record' => __('blunx::mail.type.record'), default => __('blunx::mail.type.info') };
        @endphp

        <span class="badge {{ $badgeClass }}">{{ $badgeLabel }} — {{ $typeLabel }}</span>

        <div class="message">{{ $insight->message }}</div>

        <div class="context">
            {{ __('blunx::mail.dashboard') }} : <strong>{{ $dashboard->name }}</strong><br>
            {{ __('blunx::mail.widget') }} : <strong>{{ $widget->title }}</strong><br>
            {{ __('blunx::mail.generated_at') }} : <strong>{{ $insight->generated_at->format('d/m/Y à H:i') }}</strong>
        </div>

        <div class="pj-box">
            <div class="pj-icon">📄</div>
            <div class="pj-text">
                <strong>{{ __('blunx::mail.attachment_title') }}</strong>
                {{ __('blunx::mail.attachment_desc') }}
            </div>
        </div>

        <a href="{{ route('blunx.dashboard', $dashboard->uuid) }}" class="btn">
            {{ __('blunx::mail.view_dashboard') }} →
        </a>

    </div>

    <div class="footer">
        {{ __('blunx::mail.footer_line1') }}
        {{ __('blunx::mail.footer_line2') }}
    </div>

</div>
</body>
</html>