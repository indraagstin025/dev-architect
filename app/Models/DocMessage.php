<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocMessage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'doc_messages';

    protected $fillable = [
        'doc_project_id',
        'role',
        'content',
        'stage',
        'ai_model',
        'job_status',
        'job_error',
        'is_archived',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DocProject::class, 'doc_project_id');
    }

    public function isPending(): bool
    {
        return in_array($this->job_status, ['queued', 'processing'], true);
    }
}
