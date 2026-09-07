<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\ScaffoldJob;
use App\Services\ProjectService;
use App\Services\ScaffoldCancelledException;
use App\Services\ScaffoldProjectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class ScaffoldRunCommand extends Command
{
    protected $signature = 'devarchitect:scaffold-run {jobId}';

    protected $description = 'Menjalankan proses pembuatan proyek (scaffold) di jendela konsol terminal native';

    public function handle(ScaffoldProjectService $service): int
    {
        $jobId = $this->argument('jobId');
        $job = ScaffoldJob::find($jobId);

        if (! $job) {
            $this->error("Scaffold job tidak ditemukan: [{$jobId}]");
            return 1;
        }

        if ($job->status === 'cancelled') {
            $this->warn('Scaffold job ini telah dibatalkan.');
            return 1;
        }

        $job->update(['status' => 'processing']);

        if (PHP_OS_FAMILY === 'Windows') {
            @cli_set_process_title("DEVArchitect — {$job->project_name}");
        }

        $this->output->writeln('');
        $this->output->writeln('<fg=cyan>===============================================================</>');
        $this->output->writeln('<fg=cyan;options=bold>        DEVArchitect — Real Console Project Scaffolder        </>');
        $this->output->writeln('<fg=cyan>===============================================================</>');
        $this->output->writeln("<fg=yellow>[+] Proyek   :</> {$job->project_name}");
        $this->output->writeln("<fg=yellow>[+] Template :</> {$job->template}");
        $this->output->writeln("<fg=yellow>[+] Parent   :</> {$job->parent_path}");
        $this->output->writeln("<fg=yellow>[+] Target   :</> {$job->target_path}");
        $this->output->writeln('<fg=cyan>---------------------------------------------------------------</>');
        $this->output->writeln('');

        $target = $job->target_path;

        try {
            $plan = $service->plan(
                $job->template,
                $job->project_name,
                $job->parent_path,
                $job->options ?? []
            );

            $target = $plan['target'];

            foreach ($plan['steps'] as $index => $step) {
                if ($job->fresh()->status === 'cancelled') {
                    throw new ScaffoldCancelledException('Dibatalkan oleh pengguna.');
                }

                $stepNum = $index + 1;
                $totalSteps = count($plan['steps']);
                $this->output->writeln("<fg=cyan;options=bold>[{$stepNum}/{$totalSteps}]</> <fg=white;options=bold>\$ {$step['label']}</>");
                $job->appendLog("\$ {$step['label']}");

                $this->executeStep($step, $job, $service);

                $this->output->writeln("<fg=green>✔ Selesai: {$step['label']}</>");
                $this->output->writeln('');
                $job->appendLog("✔ Selesai: {$step['label']}");
            }

            $this->output->writeln('<fg=yellow>$ Mendaftarkan proyek ke database DEVArchitect...</>');
            $job->appendLog('$ Mendaftarkan proyek ke database DEVArchitect...');

            $project = app(ProjectService::class)->registerProject(
                name: $job->project_name,
                absolutePath: $target
            );

            AppSetting::set('active_project_id', $project->id);
            $job->update(['status' => 'ready', 'project_id' => $project->id]);
            $job->appendLog("✔ Sukses! Proyek [{$project->project_name}] aktif.");
            $job->appendLog('Selesai.');

            $this->output->writeln('');
            $this->output->writeln('<fg=green;options=bold>===============================================================</>');
            $this->output->writeln("<fg=green;options=bold> [✔] SUKSES: Proyek [{$project->project_name}] berhasil dibuat & aktif! </>");
            $this->output->writeln('<fg=green;options=bold>===============================================================</>');
            $this->output->writeln("<fg=gray>Lokasi: {$target}</>");
            $this->output->writeln('');
            $this->output->writeln('<fg=white>Jendela ini dapat ditutup, atau tekan ENTER untuk keluar...</>');

            if (PHP_SAPI === 'cli') {
                @fgets(STDIN);
            }

            return 0;

        } catch (ScaffoldCancelledException $e) {
            $service->cleanupTarget($target);
            $this->output->writeln('');
            $this->output->writeln('<fg=yellow;options=bold>[!] Pembuatan proyek dibatalkan oleh pengguna.</>');
            $this->output->writeln('<fg=gray>Direktori target telah dibersihkan otomatis.</>');
            return 1;
        } catch (Throwable $e) {
            $service->cleanupTarget($target);
            $job->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            $job->appendLog('GAGAL: ' . $e->getMessage() . ' (Direktori target telah dibersihkan otomatis).');

            $this->output->writeln('');
            $this->output->writeln('<fg=red;options=bold>===============================================================</>');
            $this->output->writeln('<fg=red;options=bold> [✘] TERJADI KESALAHAN SAAT PEMBUATAN PROYEK </>');
            $this->output->writeln('<fg=red;options=bold>===============================================================</>');
            $this->output->writeln("<fg=red>{$e->getMessage()}</>");
            $this->output->writeln('<fg=gray>Direktori target telah dibersihkan otomatis.</>');
            $this->output->writeln('');
            $this->output->writeln('<fg=white>Tekan ENTER untuk menutup jendela ini...</>');

            if (PHP_SAPI === 'cli') {
                @fgets(STDIN);
            }

            return 1;
        }
    }

    protected function executeStep(array $step, ScaffoldJob $job, ScaffoldProjectService $service): void
    {
        switch ($step['kind']) {
            case 'mkdir':
                File::makeDirectory($step['path'], 0755, true);
                break;

            case 'process':
                $buffer = '';
                $lastFlush = microtime(true);

                $process = new Process(
                    command: $step['cmd'],
                    cwd: $step['cwd'],
                    env: $service->environment(),
                    timeout: $step['timeout'] ?? 600
                );

                $process->run(function (string $type, string $output) use ($job, &$buffer, &$lastFlush) {
                    // Tampilkan langsung di terminal native konsol
                    $this->output->write($output);

                    // Mirror ke database untuk polling UI dashboard DEVArchitect
                    $buffer .= $output;
                    $hasNewline = str_contains($output, "\n") || str_contains($output, "\r");
                    if ($hasNewline || (microtime(true) - $lastFlush >= 0.25) || strlen($buffer) >= 120) {
                        $clean = trim($buffer);
                        if ($clean !== '') {
                            $job->appendLog($clean);
                        }
                        $buffer = '';
                        $lastFlush = microtime(true);
                    }
                });

                if (trim($buffer) !== '') {
                    $job->appendLog(trim($buffer));
                }

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException(
                        "Perintah gagal [{$step['label']}]: " . mb_substr(trim($process->getErrorOutput() . ' ' . $process->getOutput()), 0, 1000)
                    );
                }
                break;

            case 'copy-stub':
                $this->output->writeln("<fg=gray>Menyalin struktur template [{$step['stub']}]...</>");
                $job->appendLog("Menyalin struktur template [{$step['stub']}]...");
                $service->copyStub($step['stub'], $step['target'], $step['vars'] ?? []);
                break;

            case 'write':
                File::put($step['path'], $step['content']);
                break;

            case 'download-spring':
                $this->output->writeln('<fg=cyan>Mengunduh starter Spring Boot dari https://start.spring.io...</>');
                $job->appendLog('Mengunduh starter Spring Boot dari https://start.spring.io...');
                $service->downloadSpringStarter(
                    $step['target'],
                    $step['group'],
                    $step['artifact'],
                    $job
                );
                break;

            default:
                throw new \RuntimeException("Langkah tidak dikenal: {$step['kind']}");
        }
    }
}
