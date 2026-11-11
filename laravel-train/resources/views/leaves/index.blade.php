@extends('layouts.app')

@section('title', 'Danh sách đơn')
@section('body-class', 'leave-list-page leave-list-screen')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leave_list.css') }}?v={{ filemtime(public_path('css/leave_list.css')) }}">
@endpush

@section('content')
    <main class="leave-list">
        <p id="leave-message" role="alert" hidden></p>
        <div id="leave-list" class="leave-list-content">
            @include('leaves.partials.list')
        </div>
    </main>
    @include('leaves.partials.cancel-dialog')
@endsection

@push('scripts')
    <script src="{{ asset('js/leaves.js') }}" defer></script>
@endpush
