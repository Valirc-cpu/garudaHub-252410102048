<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'judul',
        'deskripsi',
        'kategori',
        'budget_min',
        'budget_max',
        'batas_lelang',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'batas_lelang' => 'date',
            'budget_min' => 'integer',
            'budget_max' => 'integer',
        ];
    }

    /**
     * Relasi ke klien yang memiliki proyek ini.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Relasi ke seluruh penawaran yang diajukan pada proyek ini.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'project_id');
    }

    /**
     * Cek apakah lelang masih terbuka dan belum melewati batas waktu.
     */
    public function isOpen(): bool
    {
        return $this->status === 'terbuka' && $this->batas_lelang >= date('Y-m-d');
    }

    /**
     * Scope untuk pencarian berdasarkan judul atau kategori.
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (!empty($keyword)) {
            $keyword = trim($keyword);
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('judul', 'like', "%{$keyword}%")
                    ->orWhere('kategori', 'like', "%{$keyword}%");
            });
        }
        return $query;
    }
}
