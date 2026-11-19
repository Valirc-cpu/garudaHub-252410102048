<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'Proyek') - Garuda-Hub</title>
<link rel="stylesheet" href="/css/style.css"></head><body>
<header><div class="in"><a class="brand" href="{{ route('home') }}">Garuda-Hub</a>
<a href="{{ route('home') }}">Proyek</a>
@auth
  @if (auth()->user()->role === 'klien')<a href="{{ route('projects.create') }}">Pasang Proyek</a>@endif
  @if (auth()->user()->role !== 'admin')<a href="{{ route('dashboard') }}">Dasbor</a>@endif
  @if (auth()->user()->role === 'admin')<a href="{{ route('admin.index') }}">Admin</a>@endif
  <span>{{ auth()->user()->nama }} ({{ auth()->user()->role }})</span>
  <form class="inline" method="post" action="{{ route('logout') }}">@csrf<button class="sm sec" type="submit">Keluar</button></form>
@else
  <a href="{{ route('login') }}">Masuk</a><a href="{{ route('register') }}">Daftar</a>
@endauth
</div></header>
<main>
@if (session('flash'))
  <div class="flash {{ session('flash')[1] }}">{{ session('flash')[0] }}</div>
@endif
@if ($errors->any())
  <div class="flash err">{{ $errors->first() }}</div>
@endif
@yield('content')
</main>
<footer>Garuda-Hub - aplikasi pembelajaran. Hanya data dummy; jangan dipasang di server publik.</footer>
</body></html>
