<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'generations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'prompt_text',
        'erd_mermaid_text',
        'migration_files',
        'status',
        'target_version',
        'ai_driver',
        'database_dialect',
        'target_framework',
        'doc_version_id',
        'job_status',
        'job_error',
        'job_warnings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'migration_files' => 'array',
            'job_warnings' => 'array',
            'status' => GenerationStatus::class,
            'database_dialect' => \App\Enums\DatabaseDialect::class,
            'target_framework' => \App\Enums\TargetFramework::class,
        ];
    }

    /**
     * Get the project that owns the generation.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Check if the generation is still a dry-run draft.
     */
    public function isDraft(): bool
    {
        return $this->status === GenerationStatus::DRAFT;
    }

    /**
     * Check if the generation has been injected to the local project.
     */
    public function isInjected(): bool
    {
        return $this->status === GenerationStatus::INJECTED;
    }
}
