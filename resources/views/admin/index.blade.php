@extends('layouts.app')
@section('title', 'Admin')
@section('content')
<div class="card"><h1>Admin</h1></div>
<div class="card">
  <h2>Kelola User</h2>
  <table>
    <tr><th>ID</th><th>Nama</th><th>Email</th><th>Peran</th><th>Aksi</th></tr>
    @foreach ($users as $r)
    <tr>
      <td>{{ $r->id }}</td>
      <td>{{ $r->nama }}</td>
      <td>{{ $r->email }}</td>
      <td>{{ $r->role }}</td>
      <td>
        @if ($r->id === auth()->id())
          <span class="muted">(akun Anda)</span>
        @else
          <form class="inline" method="post" action="{{ route('admin.role') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $r->id }}">
            <select name="role">
              @foreach (['klien', 'developer', 'admin'] as $ro)
                <option value="{{ $ro }}"{{ $ro === $r->role ? ' selected' : '' }}>{{ $ro }}</option>
              @endforeach
            </select>
            <button class="sm" type="submit">Ubah</button>
          </form>
          <form class="inline" method="post" action="{{ route('admin.deleteUser') }}" onsubmit="return confirm('Hapus user beserta proyek dan penawarannya?')">
            @csrf
            <input type="hidden" name="user_id" value="{{ $r->id }}">
            <button class="sm sec" type="submit">Hapus</button>
          </form>
        @endif
      </td>
    </tr>
    @endforeach
  </table>
</div>
<div class="card">
  <h2>Kelola Proyek</h2>
  <table>
    <tr><th>ID</th><th>Judul</th><th>Klien</th><th>Status</th><th>Aksi</th></tr>
    @foreach ($projects as $p)
    <tr>
      <td>{{ $p->id }}</td>
      <td><a href="{{ route('projects.show', $p->id) }}">{{ $p->judul }}</a></td>
      <td>{{ $p->client->nama }}</td>
      <td><span class="badge {{ $p->status === 'terbuka' ? 'open' : 'closed' }}">{{ $p->status }}</span></td>
      <td>
        <form class="inline" method="post" action="{{ route('admin.deleteProject') }}" onsubmit="return confirm('Hapus proyek beserta penawarannya?')">
          @csrf
          <input type="hidden" name="project_id" value="{{ $p->id }}">
          <button class="sm sec" type="submit">Hapus</button>
        </form>
      </td>
    </tr>
    @endforeach
  </table>
</div>
@endsection
