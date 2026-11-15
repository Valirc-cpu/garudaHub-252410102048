<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BidController extends Controller
{
    /**
     * Ajukan penawaran baru pada proyek (khusus developer).
     */
    public function store(Request $request): RedirectResponse
    {
        $projectId = (int) $request->input('project_id');

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'harga'      => ['required', 'integer', 'min:1', 'max:999999999999'],
            'durasi'     => ['required', 'integer', 'min:1', 'max:3650'],
            'proposal'   => ['required', 'string', 'max:5000'],
        ], [
            'harga.min'         => 'Harga harus bilangan bulat positif yang wajar.',
            'durasi.min'        => 'Durasi harus 1 sampai 3650 hari.',
            'durasi.max'        => 'Durasi harus 1 sampai 3650 hari.',
            'proposal.required' => 'Proposal wajib diisi (maksimal 5000 karakter).',
            'proposal.max'      => 'Proposal wajib diisi (maksimal 5000 karakter).',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        if (!$project->isOpen()) {
            return redirect()->route('projects.show', $project->id)
                ->with('flash', ['Lelang sudah ditutup; penawaran tidak dapat diajukan.', 'err']);
        }

        $existingBid = Bid::where('project_id', $project->id)
            ->where('developer_id', Auth::id())
            ->exists();

        if ($existingBid) {
            return redirect()->route('projects.show', $project->id)
                ->with('flash', ['Anda sudah mengajukan penawaran pada proyek ini.', 'err']);
        }

        Bid::create([
            'project_id'   => $project->id,
            'developer_id' => Auth::id(),
            'harga'        => (int) $validated['harga'],
            'durasi'       => (int) $validated['durasi'],
            'proposal'     => trim($validated['proposal']),
            'is_winner'    => false,
        ]);

        return redirect()->route('projects.show', $project->id)
            ->with('flash', ['Penawaran berhasil dikirim.', 'ok']);
    }

    /**
     * Pilih penawaran pemenang (khusus klien pemilik proyek).
     */
    public function pickWinner(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'bid_id'     => ['required', 'integer', 'exists:bids,id'],
        ]);

        $projectId = (int) $validated['project_id'];
        $bidId = (int) $validated['bid_id'];
        $clientId = Auth::id();

        $error = DB::transaction(function () use ($projectId, $bidId, $clientId) {
            $project = Project::where('id', $projectId)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->first();

            if (!$project) {
                return 'Proyek tidak ditemukan atau bukan milik Anda.';
            }

            if ($project->status !== 'terbuka') {
                return 'Pemenang sudah dipilih atau lelang sudah ditutup.';
            }

            $bid = Bid::where('id', $bidId)
                ->where('project_id', $projectId)
                ->first();

            if (!$bid) {
                return 'Penawaran tidak valid untuk proyek ini.';
            }

            $bid->update(['is_winner' => true]);
            $project->update(['status' => 'ditutup']);

            return null;
        });

        if ($error) {
            return redirect()->route('projects.show', $projectId)
                ->with('flash', [$error, 'err']);
        }

        return redirect()->route('projects.show', $projectId)
            ->with('flash', ['Pemenang berhasil dipilih.', 'ok']);
    }
}
