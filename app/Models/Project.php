<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $client_id
 * @property string $judul
 * @property string $deskripsi
 * @property string $kategori
 * @property int $budget_min
 * @property int $budget_max
 * @property Carbon|string $batas_lelang
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
            'budget_min'   => 'integer',
            'budget_max'   => 'integer',
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
        $batas = $this->batas_lelang instanceof Carbon
            ? $this->batas_lelang->format('Y-m-d')
            : (is_string($this->batas_lelang) ? $this->batas_lelang : Carbon::parse($this->batas_lelang)->format('Y-m-d'));

        return $this->status === 'terbuka' && $batas >= date('Y-m-d');
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
