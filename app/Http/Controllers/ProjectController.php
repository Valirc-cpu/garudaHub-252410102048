<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Tampilkan daftar proyek dengan opsi pencarian.
     */
    public function index(Request $request): View
    {
        $rawQuery = $request->query('q', '');
        $query = is_string($rawQuery) ? mb_substr(trim($rawQuery), 0, 100) : '';
        // Sanitasi karakter: hanya izinkan karakter alfanumerik, spasi, dan tanda baca aman
        $query = preg_replace('/[^\p{L}\p{N}\s\-_.@]/u', '', $query) ?? '';

        $projects = Project::with('client')
            ->withCount('bids')
            ->search($query)
            ->orderBy('id', 'desc')
            ->get();

        return view('projects.index', compact('projects', 'query'));
    }

    /**
     * Tampilkan detail proyek beserta daftar penawaran.
     */
    public function show(int $id): View
    {
        $project = Project::with(['client', 'bids' => function ($query) {
            $query->orderBy('is_winner', 'desc')
                  ->orderBy('harga', 'asc')
                  ->orderBy('id', 'asc')
                  ->with('developer');
        }])->findOrFail($id);

        return view('projects.show', compact('project'));
    }

    /**
     * Tampilkan formulir pasang proyek baru (khusus klien).
     */
    public function create(): View
    {
        return view('projects.create');
    }

    /**
     * Simpan proyek baru ke database (khusus klien).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'judul'     => ['required', 'string', 'max:200'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'kategori'  => ['required', 'string', 'max:50'],
            'bmin'      => ['required', 'integer', 'min:0', 'max:999999999999'],
            'bmax'      => ['required', 'integer', 'min:0', 'max:999999999999', 'gte:bmin'],
            'batas'     => ['required', 'date', 'after_or_equal:today'],
        ], [
            'judul.required'     => 'Judul wajib diisi (maksimal 200 karakter).',
            'deskripsi.required' => 'Deskripsi wajib diisi (maksimal 5000 karakter).',
            'kategori.required'  => 'Kategori wajib diisi (maksimal 50 karakter).',
            'bmin.min'           => 'Budget harus berupa angka bulat tidak negatif.',
            'bmax.gte'           => 'Budget tidak valid (maks harus >= min).',
            'batas.after_or_equal' => 'Batas lelang harus tanggal yang valid dan belum lewat.',
        ]);

        Project::create([
            'client_id'    => Auth::id(),
            'judul'        => trim($validated['judul']),
            'deskripsi'    => trim($validated['deskripsi']),
            'kategori'     => trim($validated['kategori']),
            'budget_min'   => (int) $validated['bmin'],
            'budget_max'   => (int) $validated['bmax'],
            'batas_lelang' => $validated['batas'],
            'status'       => 'terbuka',
        ]);

        return redirect()->route('dashboard')
            ->with('flash', ['Proyek berhasil dipasang.', 'ok']);
    }
}
