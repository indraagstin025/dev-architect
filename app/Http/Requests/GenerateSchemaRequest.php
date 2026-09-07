<?php

namespace App\Http\Requests;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSchemaRequest extends FormRequest 
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'prompt_text' => ['required', 'string', 'min:10', 'max:5000'],
            'target_framework' => ['required', Rule::enum(TargetFramework::class)],
            'database_dialect' => ['required', Rule::enum(DatabaseDialect::class)],
            'target_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Tolak kombinasi target yang tidak kompatibel dengan framework proyek
     * (mis. output Prisma untuk proyek Laravel) sebelum masuk antrean.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Opsi B: blokir keras — tidak ada pengecualian/bypass.
            $project = Project::find($this->input('project_id'));
            $target = TargetFramework::tryFrom((string) $this->input('target_framework'));

            if (! $project || ! $target) {
                return;
            }

            $allowed = app(ProjectService::class)->allowedTargetFrameworks($project);

            if (! in_array($target, $allowed, true)) {
                $options = collect($allowed)->map(fn (TargetFramework $f) => $f->label())->join(', ');
                $validator->errors()->add(
                    'target_framework',
                    "Target [{$target->label()}] tidak kompatibel dengan proyek {$project->project_name} ({$project->framework_type->label()}). Pilihan yang didukung: {$options}."
                );
            }

            // Validasi Dialek Database: Jika proyek sudah memiliki dialek yang terkunci, tolak keras jika mismatch
            $requestedDialect = DatabaseDialect::tryFrom((string) $this->input('database_dialect'));
            if ($project->database_dialect !== null && $requestedDialect && $requestedDialect !== $project->database_dialect) {
                $validator->errors()->add(
                    'database_dialect',
                    "Dialek database [{$requestedDialect->label()}] tidak cocok dengan konfigurasi proyek {$project->project_name} ({$project->database_dialect->label()}). Dialek telah dikunci sesuai proyek."
                );
            }
        });
    }
}