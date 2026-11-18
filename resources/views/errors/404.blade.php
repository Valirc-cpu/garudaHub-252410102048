@extends('layouts.app')
@section('title', 'Galat 404')
@section('content')
<div class="card"><h1>404</h1><p>{{ $exception->getMessage() ?: 'Halaman tidak ditemukan.' }} <a href="{{ route('home') }}">Kembali ke beranda</a>.</p></div>
@endsection
