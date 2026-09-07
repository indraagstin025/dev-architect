<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScaffoldJob extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'scaffold_jobs';

    protected $fillable = [
        'template',
        'project_name',
        'parent_path',
        'target_path',
        'options',
        'status',
        'log',
        'error',
        'project_id',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function appendLog(string $line): void
    {
        // Bersihkan escape code ANSI jika ada
        $clean = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $line);
        // Normalisasi CRLF dan CR tunggal (progress bar terminal) menjadi newline
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);

        $current = $this->log ?? '';
        $merged = $current === '' ? $clean : $current . "\n" . $clean;

        // Pisahkan baris, buang baris kosong berlebih
        $rawLines = explode("\n", $merged);
        $lines = [];
        foreach ($rawLines as $l) {
            $trimmed = rtrim($l);
            if ($trimmed !== '') {
                $lines[] = $trimmed;
            }
        }

        // Batasi log agar baris DB tidak membengkak (ambil 350 baris terakhir).
        if (count($lines) > 350) {
            $lines = array_slice($lines, -350);
        }

        $this->update(['log' => implode("\n", $lines)]);
    }
}
