@extends('layouts.base')

@section('title', __('messages.page_error'))
@section('body-class', 'error-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/error.css') }}?v={{ filemtime(public_path('css/error.css')) }}">
@endpush

@section('body')
    @include('layouts.header')

    @include('components.sidebar')

    {{-- Chỉ hiển thị thông báo lỗi chung, mã HTTP và header được Handler quyết định. --}}
    <main class="error-content">
        <h1>{{ __('messages.page_error') }}</h1>
    </main>
@endsection
