@php use App\Support\Display; @endphp
@forelse ($previews as $preview)
    <div style="margin-top: 12px">
        <strong>{{ Display::value($preview['environment']['attributes']['name'] ?? null) }}</strong>
        <p class="muted" style="margin: 0">
            Slug <code>{{ $preview['slug'] }}</code>
            · Vanity <code>{{ Display::value($preview['environment']['attributes']['vanity_domain'] ?? null) }}</code>
        </p>

        @forelse ($preview['domains'] as $domain)
            @php
                $name = Display::value($domain['attributes']['name'] ?? null);
                $statuses = collect(['hostname_status', 'ssl_status', 'origin_status'])
                    ->mapWithKeys(fn ($key) => [$key => Display::status($domain['attributes'][$key] ?? null)]);
            @endphp
            <div class="row">
                <a href="https://{{ $name }}">{{ $name }}</a>
                @if ($statuses->every(fn ($status) => $status === 'verified'))
                    <span class="badge live">live</span>
                @endif
                @foreach ($statuses as $key => $status)
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
