<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocProject extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'doc_projects';

    /**
     * Urutan tahap Human-in-the-Loop. `brief` = pengumpulan ide,
     * `done` = seluruh dokumen disetujui.
     */
    public const STAGES = ['brief', 'urd', 'prd', 'srs', 'sysdesign', 'done'];

    protected $fillable = [
        'title',
        'description',
        'target_framework',
        'stage',
        'ai_model',
        'status',
        'code_project_id',
    ];

    public function codeProject(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Project::class, 'code_project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DocMessage::class, 'doc_project_id')->orderBy('created_at');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocVersion::class, 'doc_project_id')->orderBy('created_at');
    }

    /**
     * Tahap berikutnya, atau null bila sudah `done`.
     */
    public function nextStage(): ?string
    {
        $i = array_search($this->stage, self::STAGES, true);

        if ($i === false || $i >= count(self::STAGES) - 1) {
            return null;
        }

        return self::STAGES[$i + 1];
    }

    /**
     * Apakah versi ini boleh di-approve sekarang (Q7: berurutan wajib —
     * tipe harus sama dengan tahap berjalan atau tepat tahap berikutnya)?
     */
    public function canApprove(DocVersion $version): bool
    {
        if ($version->doc_project_id !== $this->id || $version->status !== 'draft') {
            return false;
        }

        $current = array_search($this->stage, self::STAGES, true);
        $type = array_search($version->doc_type, self::STAGES, true);

        return $type !== false && $current !== false && ($type === $current || $type === $current + 1);
    }

    /**
     * Approve versi + majukan tahap ke sesudah tipe dokumennya.
     */
    public function approveVersion(DocVersion $version): bool
    {
        if (! $this->canApprove($version)) {
            return false;
        }

        $typeIdx = array_search($version->doc_type, self::STAGES, true);

        $version->update(['status' => 'approved']);
        $this->update(['stage' => self::STAGES[$typeIdx + 1]]);

        return true;
    }

    /**
     * Maju satu tahap. Mengembalikan false bila sudah `done`.
     */
    public function advanceStage(): bool
    {
        $next = $this->nextStage();

        if (! $next) {
            return false;
        }

        $this->update(['stage' => $next]);

        return true;
    }

    /**
     * Versi approved terakhir untuk tipe dokumen tertentu.
     */
    public function approvedVersion(string $docType): ?DocVersion
    {
        return $this->versions()
            ->where('doc_type', $docType)
            ->where('status', 'approved')
            ->orderBy('version', 'desc')
            ->first();
    }
}
