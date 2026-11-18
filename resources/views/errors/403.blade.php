@extends('layouts.app')
@section('title', 'Galat 403')
@section('content')
<div class="card"><h1>403</h1><p>{{ $exception->getMessage() ?: 'Anda tidak berhak mengakses halaman ini.' }} <a href="{{ route('home') }}">Kembali ke beranda</a>.</p></div>
@endsection
