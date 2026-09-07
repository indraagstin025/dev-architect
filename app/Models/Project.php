<?php

namespace App\Models;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\File;

class Project extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'projects';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_name',
        'absolute_path',
        'framework_type',
        'database_dialect',
        'is_draft',
        'doc_project_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'framework_type' => TargetFramework::class,
            'database_dialect' => DatabaseDialect::class,
            'is_draft' => 'boolean',
        ];
    }

    /**
     * Get all generations for this project.
     *
     * @return HasMany<Generation, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class, 'project_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest generation for this project.
     */
    public function latestGeneration(): HasOne
    {
        return $this->hasOne(Generation::class, 'project_id')->latestOfMany();
    }

    /**
     * Relasi ke proyek dokumen arsitektur AI (jika dibuat dari Asisten AI).
     */
    public function docProject(): BelongsTo
    {
        return $this->belongsTo(DocProject::class, 'doc_project_id');
    }

    /**
     * Apakah proyek masih berstatus draft virtual (belum memiliki folder fisik di disk).
     */
    public function isDraft(): bool
    {
        if ($this->is_draft) {
            return true;
        }

        if (empty($this->absolute_path)) {
            return true;
        }

        return !File::isDirectory($this->absolute_path);
    }

    /**
     * Apakah proyek sudah memiliki rancangan skema database / ERD.
     */
    public function hasErd(): bool
    {
        $hasGenerationErd = $this->generations()
            ->whereNotNull('erd_mermaid_text')
            ->where('erd_mermaid_text', '!=', '')
            ->exists();

        if ($hasGenerationErd) {
            return true;
        }

        if ($this->doc_project_id && $this->docProject) {
            if ($this->docProject->stage === 'sysdesign' || $this->docProject->stage === 'done') {
                return true;
            }
            return $this->docProject->versions()
                ->whereIn('doc_type', ['sysdesign', 'erd'])
                ->exists();
        }

        return false;
    }

    /**
     * Apakah direktori fisik proyek benar-benar ada di komputer pengguna.
     */
    public function hasPhysicalFolder(): bool
    {
        return !empty($this->absolute_path) && File::isDirectory($this->absolute_path);
    }

    /**
     * Apakah migrasi database sudah pernah berhasil diinjeksi ke proyek.
     */
    public function hasInjectedMigrations(): bool
    {
        return $this->generations()
            ->where('status', 'injected')
            ->exists();
    }

    /**
     * Apakah terdapat file migrasi yang siap diinjeksi namun belum disuntikkan.
     */
    public function hasPendingMigrations(): bool
    {
        return $this->generations()
            ->where('status', 'draft')
            ->whereNotNull('migration_files')
            ->exists();
    }

    /**
     * Mendeteksi apakah skema ERD diperbarui pasca-injeksi pertama (Skenario 4: Perlu Injeksi Ulang).
     */
    public function needsReinjection(): bool
    {
        $hasInjected = $this->hasInjectedMigrations();
        if (!$hasInjected) {
            return false;
        }

        $latest = $this->latestGeneration;
        if ($latest && $latest->status === 'draft') {
            return true;
        }

        if ($this->doc_project_id && $this->docProject) {
            $lastInjected = $this->generations()
                ->where('status', 'injected')
                ->latest('updated_at')
                ->first();

            if ($lastInjected) {
                return $this->docProject->versions()
                    ->whereIn('doc_type', ['sysdesign', 'erd'])
                    ->where('updated_at', '>', $lastInjected->updated_at)
                    ->exists();
            }
        }

        return false;
    }

    /**
     * Menghitung progres siklus hidup proyek (1 s.d 4).
     */
    public function getLifecycleProgressAttribute(): int
    {
        if (!$this->hasPhysicalFolder() && !$this->hasErd()) {
            return 1; // 1/4: Ide & Dokumen Fitur
        }

        if (!$this->hasPhysicalFolder() && $this->hasErd()) {
            return 2; // 2/4: Dokumen ERD Siap
        }

        if ($this->hasPhysicalFolder() && (!$this->hasInjectedMigrations() || $this->needsReinjection())) {
            return 3; // 3/4: Proyek Terpasang di Komputer
        }

        return 4; // 4/4: Selesai / Siap Koding
    }

    /**
     * Mendapatkan identifier teks tahap siklus hidup.
     */
    public function getLifecycleStageAttribute(): string
    {
        return match ($this->lifecycle_progress) {
            1 => 'ideation',
            2 => 'erd_ready',
            3 => 'scaffolded',
            4 => 'completed',
        };
    }

    /**
     * Mendapatkan label tahap untuk antarmuka pengguna.
     */
    public function getLifecycleStageLabelAttribute(): string
    {
        return match ($this->lifecycle_progress) {
            1 => 'Ide & Dokumen Fitur',
            2 => 'Dokumen ERD Siap',
            3 => 'Proyek Terpasang',
            4 => 'Selesai',
        };
    }

    /**
     * Logika Tombol Aksi Dinamis (Smart Decision Engine).
     *
     * @return array{
     *     text: string,
     *     url: string,
     *     action_type: string,
     *     editor?: string,
     *     tooltip: string,
     *     re_injection?: bool
     * }
     */
    public function getNextActionAttribute(): array
    {
        $progress = $this->lifecycle_progress;

        if ($progress === 1) {
            return [
                'text' => 'Rancang Dokumen ERD →',
                'url' => url('/generator' . ($this->doc_project_id ? '?from_doc=' . $this->doc_project_id : '?project_id=' . $this->id)),
                'action_type' => 'link',
                'tooltip' => 'Rancang skema database & diagram ERD berdasarkan dokumen fitur.',
                're_injection' => false,
            ];
        }

        if ($progress === 2) {
            return [
                'text' => 'Buat & Instal Proyek Ini →',
                'url' => '#',
                'action_type' => 'modal_scaffold',
                'tooltip' => 'Instal struktur folder proyek ini ke komputer Anda.',
                're_injection' => false,
            ];
        }

        if ($progress === 3) {
            if ($this->needsReinjection()) {
                return [
                    'text' => 'Update & Injeksi Ulang →',
                    'url' => url('/generator?project_id=' . $this->id),
                    'action_type' => 'link',
                    'tooltip' => 'Terdapat perubahan pada diagram ERD yang belum disuntikkan ke proyek.',
                    're_injection' => true,
                ];
            }

            return [
                'text' => 'Injeksi Kode Migrasi →',
                'url' => url('/generator?project_id=' . $this->id),
                'action_type' => 'link',
                'tooltip' => 'Suntikkan berkas migrasi database ke direktori proyek lokal.',
                're_injection' => false,
            ];
        }

        return [
            'text' => 'Buka di VS Code →',
            'url' => '#',
            'action_type' => 'open_editor',
            'editor' => 'vscode',
            'tooltip' => 'Buka proyek langsung di Visual Studio Code.',
            're_injection' => false,
        ];
    }
}
