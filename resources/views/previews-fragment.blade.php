@foreach ($applications as $application)
    <div data-previews="{{ $application['id'] }}">
        @include('partials.previews', ['previews' => $application['previews']])
    </div>
@endforeach
