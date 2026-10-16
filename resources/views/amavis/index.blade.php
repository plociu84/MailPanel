@extends('layouts.app')

@section('title', 'Zarządzanie Amavis')
@section('header-title', 'Zarządzanie Amavis')

@section('content')
    <!-- Powiadomienia -->
    <x-flash-alerts />

    @include('amavis.partials.stats')

    @include('amavis.partials.notices')

    <div class="row" id="amavisRow">
        @include('amavis.partials.columns.quarantine')
        @include('amavis.partials.columns.logs')
    </div>

    @include('amavis.partials.modals.preview')
@endsection

@push('styles')
<link href="{{ asset('assets/css/amavis.css') }}?v={{ filemtime(public_path('assets/css/amavis.css')) }}" rel="stylesheet">
@endpush

@push('scripts')
<script src="{{ asset('assets/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('assets/js/amavis.js') }}?v={{ filemtime(public_path('assets/js/amavis.js')) }}"></script>
@endpush
