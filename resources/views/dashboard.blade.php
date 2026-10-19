@php use App\Support\Display; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Preview Router</title>
    <style>
        :root { --bg: #fafaf9; --fg: #1c1917; --muted: #78716c; --card: #fff; --line: #e7e5e4; --accent: #f53003; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0c0a09; --fg: #fafaf9; --muted: #a8a29e; --card: #1c1917; --line: #292524; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.5 ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 48px 16px; }
        h1 { font-size: 28px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 0 0 4px; }
        p { color: var(--muted); margin: 0 0 24px; }
        a { color: var(--accent); word-break: break-all; }
        code { font-family: ui-monospace, monospace; font-size: 14px; }
        .box { padding: 16px 20px; background: var(--card); border: 1px solid var(--line); border-radius: 10px; margin-bottom: 12px; }
        .muted { color: var(--muted); font-size: 14px; margin: 0 0 12px; }
        .row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 6px; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 13px; border: 1px solid var(--line); }
        .badge.verified { border-color: #16a34a; color: #16a34a; }
        .badge.failed { border-color: #dc2626; color: #dc2626; }
        .badge.pending { border-color: #d97706; color: #d97706; }
        .error { padding: 12px 16px; border-radius: 8px; border: 1px solid #dc2626; color: #dc2626; margin-bottom: 16px; }
    </style>
</head>
<body>
<main>
    <h1>Preview Router</h1>
    <p>Preview environments and their <code>{section}.{slug}.{{ config('router.root_domain') }}</code> domains. Refreshes every minute.</p>

    @if ($error)
        <div class="error">{{ $error }}</div>
    @endif

    @forelse ($previews as $preview)
        <div class="box">
            <h2>{{ Display::value($preview['application']['attributes']['name'] ?? null) }} · {{ Display::value($preview['environment']['attributes']['name'] ?? null) }}</h2>
            <p class="muted">
                Slug <code>{{ $preview['slug'] }}</code>
                · Vanity <code>{{ Display::value($preview['environment']['attributes']['vanity_domain'] ?? null) }}</code>
            </p>

            @forelse ($preview['domains'] as $domain)
                @php($name = Display::value($domain['attributes']['name'] ?? null))
                <div class="row">
                    <a href="https://{{ $name }}">{{ $name }}</a>
                    @foreach (['hostname_status', 'ssl_status', 'origin_status'] as $key)
                        @php($status = Display::status($domain['attributes'][$key] ?? null))
                        <span class="badge {{ $status }}">{{ str($key)->before('_') }}: {{ $status }}</span>
                    @endforeach
                </div>
            @empty
                <p class="muted" style="margin: 0">No custom domains yet.</p>
            @endforelse
        </div>
    @empty
        @unless ($error)
            <p>No preview environments right now.</p>
        @endunless
    @endforelse
</main>
</body>
</html>
