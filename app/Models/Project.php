<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'framework_type' => \App\Enums\TargetFramework::class,
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
    public function latestGeneration()
    {
        return $this->hasOne(Generation::class, 'project_id')->latestOfMany();
    }
}
