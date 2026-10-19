@php use App\Support\Display; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        h2 { font-size: 20px; margin: 0 0 12px; }
        h3 { font-size: 16px; margin: 20px 0 4px; }
        p { color: var(--muted); margin: 0 0 24px; }
        a { color: var(--accent); word-break: break-all; }
        code { font-family: ui-monospace, monospace; font-size: 14px; }
        label { display: block; font-size: 14px; color: var(--muted); margin-bottom: 4px; }
        input, button { font: inherit; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--bg); color: var(--fg); }
        input { flex: 1; min-width: 0; }
        button { cursor: pointer; background: var(--fg); color: var(--bg); border-color: var(--fg); }
        .box { padding: 20px; background: var(--card); border: 1px solid var(--line); border-radius: 10px; margin-bottom: 16px; }
        .muted { color: var(--muted); font-size: 14px; margin: 0 0 8px; }
        .row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 6px; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 13px; border: 1px solid var(--line); }
        .badge.verified { border-color: #16a34a; color: #16a34a; }
        .badge.failed { border-color: #dc2626; color: #dc2626; }
        .badge.pending { border-color: #d97706; color: #d97706; }
        .flash { padding: 12px 16px; border-radius: 8px; border: 1px solid var(--line); background: var(--card); margin-bottom: 16px; }
        .flash.error, .field-error { color: #dc2626; border-color: #dc2626; }
        .field-error { font-size: 14px; margin: 6px 0 0; }
    </style>
</head>
<body>
<main>
    <h1>Preview Router</h1>
    <p>
        Choose the subdomain sections each application's previews get. A preview gets
        <code>{section}.{slug}.{{ config('router.root_domain') }}</code> for each one, when it runs
        <code>php artisan preview:register</code> on deploy or on the next scheduled sync.
    </p>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @if ($error)
        <div class="flash error">{{ $error }}</div>
    @endif

    @foreach ($applications as $application)
        <section class="box">
            <h2>{{ $application['name'] }}</h2>

            <form method="POST" action="{{ route('sections.update', $application['id']) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="application_name" value="{{ $application['name'] }}">
                <label for="sections-{{ $application['id'] }}">Sections (comma separated)</label>
                <div class="row" style="margin-top: 0">
                    <input id="sections-{{ $application['id'] }}" name="sections"
                           value="{{ implode(', ', $application['sections']) }}" placeholder="admin, booking, portal">
                    <button type="submit">Save</button>
                </div>
                @foreach ($errors->getBag($application['id'])->all() as $message)
                    <p class="field-error">{{ $message }}</p>
                @endforeach
                <p class="muted" style="margin: 8px 0 0">
                    Removing a section stops new domains being added. It doesn't delete domains previews already have.
                </p>
            </form>

            <h3>Previews</h3>
            @forelse ($application['previews'] as $preview)
                <div style="margin-top: 12px">
                    <strong>{{ Display::value($preview['environment']['attributes']['name'] ?? null) }}</strong>
                    <p class="muted" style="margin: 0">
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
                <p class="muted" style="margin: 0">No preview environments right now.</p>
            @endforelse
        </section>
    @endforeach

    @if (! $error && empty($applications))
        <p>The Cloud API token can't see any applications.</p>
    @endif
</main>
</body>
</html>
