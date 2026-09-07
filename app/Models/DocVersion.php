<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocVersion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'doc_versions';

    public const TYPES = ['urd', 'prd', 'srs', 'sysdesign'];

    protected $fillable = [
        'doc_project_id',
        'doc_type',
        'version',
        'content_markdown',
        'status',
        'parent_version_id',
        'ai_model',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DocProject::class, 'doc_project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_version_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Buat versi saudara berikutnya (regenerasi tanpa menghapus yang lama).
     */
    public static function nextVersionNumber(string $projectId, string $docType): int
    {
        return (int) (static::where('doc_project_id', $projectId)
            ->where('doc_type', $docType)
            ->max('version') ?? 0) + 1;
    }
}
