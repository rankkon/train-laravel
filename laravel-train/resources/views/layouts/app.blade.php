@extends('layouts.base')

@section('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('body-attributes')
    data-login-url="{{ route('login') }}"
@endsection

@section('body')
    @include('layouts.header')
    @include('components.sidebar')
    @yield('content')
    @include('components.confirmation')
    <script src="{{ asset('js/app.js') }}" defer></script>
@endsection
