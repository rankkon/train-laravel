@extends('layouts.app')

@section('title', 'Danh sách người dùng')
@section('body-class', 'leave-list-page leave-list-screen user-list-screen')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leave_list.css') }}?v={{ filemtime(public_path('css/leave_list.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/user_list.css') }}?v={{ filemtime(public_path('css/user_list.css')) }}">
@endpush

@section('content')
    <main class="leave-list">
        <p id="user-message" role="alert" hidden></p>
        <div id="user-list" class="leave-list-content" data-bulk-url="{{ route('users.bulk-lock') }}">
            @include('users.partials.list')
        </div>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/users.js') }}" defer></script>
@endpush
