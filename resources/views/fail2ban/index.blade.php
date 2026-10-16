@extends('layouts.app')

@section('title', 'Zarządzanie Fail2Ban')
@section('header-title', 'Zarządzanie Fail2Ban')

@section('content')
    <!-- Powiadomienia -->
    <x-flash-alerts />

    @include('fail2ban.partials.stats')

    @include('fail2ban.partials.filters')

    @include('fail2ban.partials.tabs-nav')

    <div class="tab-content">
        @include('fail2ban.partials.tabs.active')
        @include('fail2ban.partials.tabs.history')
        @include('fail2ban.partials.tabs.repeat')
        @include('fail2ban.partials.tabs.fortigate')
        @include('fail2ban.partials.tabs.logs')
        @include('fail2ban.partials.tabs.list')
    </div>

    @include('fail2ban.partials.modals.ip-history')
    @include('fail2ban.partials.modals.note')
    @include('fail2ban.partials.modals.feed-preview')
@endsection

@push('styles')
<link href="{{ asset('assets/css/fail2ban.css') }}?v={{ filemtime(public_path('assets/css/fail2ban.css')) }}" rel="stylesheet">
@endpush

@push('scripts')
<script>
    window.F2B = {
        csrf: '{{ csrf_token() }}',
        activeTab: '{{ $activeTab }}',
        routes: {
            refresh: '{{ route('fail2ban.refresh') }}',
            status: '{{ route('fail2ban.status') }}',
            index: '{{ route('fail2ban.index') }}',
            feedPreview: '{{ route('fail2ban.fortigate-feed.preview') }}',
            feedSync: '{{ route('fail2ban.fortigate-feed.sync') }}',
        },
    };
</script>
<script src="{{ asset('assets/js/fail2ban.js') }}?v={{ filemtime(public_path('assets/js/fail2ban.js')) }}"></script>
@endpush
