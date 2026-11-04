@push('styles')
    <link rel="stylesheet" href="{{ asset('css/tahfidz.css') }}?v={{ filemtime(public_path('css/tahfidz.css')) }}">
@endpush
