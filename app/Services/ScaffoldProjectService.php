<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ScaffoldJob;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * TASK-606: Pembuatan proyek baru dari terminal (scaffold).
 *
 * Semua perintah dibangun sebagai array argumen (Symfony Process) — tidak ada
 * interpolasi string shell sehingga nama proyek tidak bisa lolos menjadi
 * injeksi perintah. Unduhan Spring via HTTP client Laravel (tanpa curl CLI).
 */
class ScaffoldProjectService
{
    public const TEMPLATES = [
        'laravel',
        'express_prisma',
        'express_drizzle',
        'springboot_hibernate',
        'raw_sql',
    ];

    public const SPRING_BOOT_VERSION = '3.4.1';

    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9-_]{1,60}$/';

    /**
     * Direktori parent default (P3): ~/Documents/DEVArchitect-Projects.
     */
    public static function defaultParentDir(): string
    {
        $home = getenv('USERPROFILE') ?: (getenv('HOME') ?: sys_get_temp_dir());
        $documents = $home . DIRECTORY_SEPARATOR . 'Documents';

        $base = (is_dir($documents) ? $documents : $home)
            . DIRECTORY_SEPARATOR . 'DEVArchitect-Projects';

        if (! File::isDirectory($base)) {
            @File::makeDirectory($base, 0755, true);
        }

        return realpath($base) ?: $base;
    }

    /**
     * Mengecek apakah path direktori bisa ditulis, dengan penanganan khusus Windows OS.
     * Di Windows, is_writable() pada folder khusus (seperti Documents) mengembalikan false
     * karena atribut ReadOnly shell, meskipun proses PHP memiliki izin tulis penuh.
     */
    public static function isWritable(string $path): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return is_writable($path);
        }

        if (is_dir($path)) {
            $testFile = rtrim($path, '\\/') . DIRECTORY_SEPARATOR . '.devarchitect_probe_' . uniqid() . '.tmp';
            $fp = @fopen($testFile, 'w');
            if ($fp === false) {
                return false;
            }
            fclose($fp);
            @unlink($testFile);

            return true;
        }

        return is_writable($path);
    }

    /**
     * Status ketersediaan tool. $checkInternet=false untuk unit test (tanpa jaringan).
     *
     * @return array{php: array{ok: bool, version: ?string}, composer: array{ok: bool, version: ?string}, git: array{ok: bool, version: ?string}, node: array{ok: bool, version: ?string}, npm: array{ok: bool, version: ?string}, java: array{ok: bool, version: ?string}, zip: array{ok: bool, version: ?string}, internet: array{ok: bool, version: ?string}, parent_default: string}
     */
    public function prerequisites(bool $checkInternet = true): array
    {
        return [
            'php' => $this->probe(['php', '-v']),
            'composer' => $this->probe(['composer', '-V']),
            'git' => $this->probe(['git', '--version']),
            'node' => $this->probe(['node', '-v']),
            'npm' => $this->probe(['npm', '-v']),
            'java' => $this->probe(['java', '-version']),
            'zip' => [
                'ok' => class_exists(ZipArchive::class),
                'version' => class_exists(ZipArchive::class) ? 'php-zip' : null,
            ],
            'internet' => $checkInternet ? $this->probeInternet() : ['ok' => null, 'version' => null],
            'parent_default' => self::defaultParentDir(),
        ];
    }

    /**
     * Validasi nama + parent, kembalikan path absolut yang ternormalisasi.
     *
     * Parent yang belum ada dibuat otomatis SATU level (mis. folder kerja
     * default) selama kakeknya valid + writable — path typo berlapis tetap ditolak.
     *
     * @return array{parent: string, target: string}
     */
    public function validateTarget(string $name, string $parent): array
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new RuntimeException(
                'Nama proyek tidak valid: huruf kecil, angka, strip/underscore, 2–61 karakter, diawali huruf/angka.'
            );
        }

        $parentClean = rtrim(trim($parent), "\\/");
        $realParent = realpath($parentClean);

        if (! $realParent) {
            $realParent = $this->ensureParentExists($parentClean);
        }

        if (! $realParent || ! File::isDirectory($realParent) || ! self::isWritable($realParent)) {
            throw new RuntimeException("Direktori parent tidak valid atau tidak bisa ditulis: {$parent}");
        }

        $target = $realParent . DIRECTORY_SEPARATOR . $name;

        if (File::exists($target)) {
            throw new RuntimeException("Target sudah ada: {$target}. Pilih nama lain atau hapus dulu.");
        }

        return ['parent' => $realParent, 'target' => $target];
    }

    /**
     * Buat direktori parent yang belum ada (maksimal satu level).
     *
     * @throws RuntimeException
     */
    protected function ensureParentExists(string $parent): ?string
    {
        $parentClean = rtrim(trim($parent), "\\/");
        $grandparent = dirname($parentClean);
        $realGrandparent = realpath($grandparent);

        if (! $realGrandparent
            || ! File::isDirectory($realGrandparent)
            || ! self::isWritable($realGrandparent)
        ) {
            return null;
        }

        try {
            File::makeDirectory($parentClean, 0755, false);
        } catch (\Throwable $e) {
            return null;
        }

        return realpath($parentClean) ?: null;
    }

    /**
     * Susun rencana langkah murni (tanpa eksekusi) — unit-testable.
     *
     * @return array{parent: string, target: string, steps: array<int, array<string, mixed>>}
     */
    public function plan(string $template, string $name, string $parent, array $options = []): array
    {
        if (! in_array($template, self::TEMPLATES, true)) {
            throw new RuntimeException("Template [{$template}] tidak didukung.");
        }

        ['parent' => $realParent, 'target' => $target] = $this->validateTarget($name, $parent);
        
        // Catatan: Template Laravel dibuat langsung oleh Composer create-project
        // tanpa mkdir terlebih dahulu agar tidak memicu lock file/permission collision di OS.
        $steps = [];
        if ($template !== 'laravel') {
            $steps[] = ['kind' => 'mkdir', 'path' => $target, 'label' => "Buat folder {$name}"];
        }

        switch ($template) {
            case 'laravel':
                $steps[] = [
                    'kind' => 'process',
                    'cmd' => ['composer', 'create-project', 'laravel/laravel', $name, '--prefer-dist', '--no-interaction'],
                    'cwd' => $realParent,
                    'timeout' => 600,
                    'label' => 'composer create-project laravel/laravel',
                ];
                break;

            case 'express_prisma':
            case 'express_drizzle':
                $stub = $template === 'express_prisma' ? 'express-prisma' : 'express-drizzle';
                $steps[] = [
                    'kind' => 'copy-stub',
                    'stub' => $stub,
                    'target' => $target,
                    'vars' => ['__SLUG__' => $name],
                    'label' => "Salin template {$stub}",
                ];
                $steps[] = [
                    'kind' => 'process',
                    'cmd' => ['npm', 'install', '--no-audit', '--no-fund'],
                    'cwd' => $target,
                    'timeout' => 600,
                    'label' => 'npm install',
                ];
                break;

            case 'springboot_hibernate':
                $group = $options['spring_group'] ?? 'com.example';
                $artifact = $options['spring_artifact'] ?? $name;
                $this->validateSpringCoords($group, $artifact);
                $steps[] = [
                    'kind' => 'download-spring',
                    'target' => $target,
                    'group' => $group,
                    'artifact' => $artifact,
                    'label' => 'Unduh starter Spring Boot (Initializr API)',
                ];
                break;

            case 'raw_sql':
                $steps[] = [
                    'kind' => 'write',
                    'path' => $target . DIRECTORY_SEPARATOR . 'README.md',
                    'content' => "# {$name}\n\nProyek SQL universal — skema AI DEVArchitect akan disuntikkan sebagai `schema.sql`.\n",
                    'label' => 'Tulis README awal',
                ];
                break;
        }

        return ['parent' => $realParent, 'target' => $target, 'steps' => $steps];
    }

    /**
     * Eksekusi rencana untuk sebuah baris scaffold_jobs (dipanggil Job).
     */
    public function run(ScaffoldJob $job, callable $isCancelled): void
    {
        $plan = $this->plan(
            $job->template,
            $job->project_name,
            $job->parent_path,
            $job->options ?? []
        );

        // Parent bisa berubah/dihapus setelah antre — validasi ulang target.
        $target = $plan['target'];
        if (File::exists($target)) {
            throw new RuntimeException("Target sudah ada saat eksekusi: {$target}");
        }

        try {
            foreach ($plan['steps'] as $i => $step) {
                if ($isCancelled()) {
                    throw new ScaffoldCancelledException('Dibatalkan pengguna.');
                }

                $job->appendLog('$ ' . $step['label']);
                $this->runStep($step, $job, $isCancelled);
                $job->appendLog('✔ Selesai: ' . $step['label']);
            }

            $job->appendLog('$ Mendaftarkan proyek ke database DEVArchitect...');
            $project = app(ProjectService::class)->registerProject(
                name: $job->project_name,
                absolutePath: $target
            );

            AppSetting::set('active_project_id', $project->id);

            $job->update(['project_id' => $project->id]);
            $job->appendLog("✔ Sukses! Proyek [{$project->project_name}] aktif.");
        } catch (\Throwable $e) {
            // Auto-cleanup rollback: Jika terjadi kegagalan/pembatalan, hapus folder target yang setengah jadi
            $this->cleanupTarget($target);
            throw $e;
        }
    }

    /**
     * Membersihkan direktori target jika pembuatan proyek gagal atau dibatalkan.
     */
    public function cleanupTarget(string $targetPath): void
    {
        try {
            if (File::isDirectory($targetPath)) {
                File::deleteDirectory($targetPath);
            } elseif (File::exists($targetPath)) {
                File::delete($targetPath);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal membersihkan direktori target [{$targetPath}]: " . $e->getMessage());
        }
    }

    protected function runStep(array $step, ?ScaffoldJob $job = null, ?callable $isCancelled = null): void
    {
        switch ($step['kind']) {
            case 'mkdir':
                File::makeDirectory($step['path'], 0755, true);
                break;

            case 'process':
                $buffer = '';
                $lastFlush = microtime(true);

                $result = Process::timeout($step['timeout'] ?? 600)
                    ->path($step['cwd'])
                    ->env($this->environment())
                    ->run($step['cmd'], function (string $type, string $output) use ($job, &$buffer, &$lastFlush, $isCancelled) {
                        if ($isCancelled && $isCancelled()) {
                            throw new ScaffoldCancelledException('Dibatalkan pengguna.');
                        }

                        $buffer .= $output;

                        // Flush output terminal secara real-time: jika ada newline/CR, atau setiap 0.25s, atau buffer >= 120 bytes
                        $hasNewline = str_contains($output, "\n") || str_contains($output, "\r");
                        $timeExceeded = (microtime(true) - $lastFlush) >= 0.25;
                        $sizeExceeded = strlen($buffer) >= 120;

                        if ($job && ($hasNewline || $timeExceeded || $sizeExceeded)) {
                            $clean = trim($buffer);
                            if ($clean !== '') {
                                $job->appendLog($clean);
                            }
                            $buffer = '';
                            $lastFlush = microtime(true);
                        }
                    });

                if ($job && trim($buffer) !== '') {
                    $job->appendLog(trim($buffer));
                }

                if ($result->failed()) {
                    throw new RuntimeException(
                        "Perintah gagal [{$step['label']}]: " . mb_substr(trim($result->errorOutput() . ' ' . $result->output()), 0, 1000)
                    );
                }
                break;

            case 'copy-stub':
                if ($job) {
                    $job->appendLog("Menyalin struktur template [{$step['stub']}] ke direktori target...");
                }
                $this->copyStub($step['stub'], $step['target'], $step['vars'] ?? []);
                break;

            case 'write':
                File::put($step['path'], $step['content']);
                break;

            case 'download-spring':
                if ($job) {
                    $job->appendLog("Mengunduh starter Spring Boot dari https://start.spring.io...");
                }
                $this->downloadSpringStarter(
                    $step['target'],
                    $step['group'],
                    $step['artifact'],
                    $job
                );
                break;

            default:
                throw new RuntimeException("Langkah tidak dikenal: {$step['kind']}");
        }
    }

    /**
     * Membuka jendela konsol native (PowerShell di Windows) untuk menjalankan proses scaffold secara interaktif.
     */
    public static function launchExternalConsole(ScaffoldJob $job): bool
    {
        $basePath = base_path();
        $jobId = escapeshellarg($job->id);

        if (PHP_OS_FAMILY === 'Windows') {
            $phpBinary = escapeshellarg(PHP_BINARY);
            $psCommand = '& { '
                . "[Console]::Title = 'DEVArchitect — {$job->project_name}'; "
                . "cd '{$basePath}'; "
                . "& {$phpBinary} artisan devarchitect:scaffold-run {$jobId}; "
                . '}';

            // start powershell.exe membuka jendela konsol baru yang independen di desktop
            $cmd = 'start powershell.exe -NoExit -ExecutionPolicy Bypass -Command ' . escapeshellarg($psCommand);

            $handle = @popen($cmd, 'r');
            if ($handle) {
                @pclose($handle);

                return true;
            }
        }

        return false;
    }

    public function copyStub(string $stub, string $target, array $vars): void
    {
        $source = resource_path("stubs/{$stub}");

        if (! File::isDirectory($source)) {
            throw new RuntimeException("Template stub tidak ditemukan: {$stub}");
        }

        foreach (File::allFiles($source) as $file) {
            $relative = $file->getRelativePathname();
            $dest = $target . DIRECTORY_SEPARATOR . $relative;
            File::ensureDirectoryExists(dirname($dest));

            $content = File::get($file->getPathname());
            foreach ($vars as $key => $value) {
                $content = str_replace($key, $value, $content);
            }
            File::put($dest, $content);
        }
    }

    public function downloadSpringStarter(string $target, string $group, string $artifact, ?ScaffoldJob $job = null): void
    {
        $package = strtolower($group . '.' . $artifact);
        $zipPath = $target . DIRECTORY_SEPARATOR . 'starter.zip';

        $url = 'https://start.spring.io/starter.zip?' . http_build_query([
            'type' => 'maven-project',
            'language' => 'java',
            'bootVersion' => self::SPRING_BOOT_VERSION,
            'baseDir' => $artifact,
            'groupId' => $group,
            'artifactId' => $artifact,
            'name' => $artifact,
            'packageName' => $package,
            'packaging' => 'jar',
            'javaVersion' => '17',
            'dependencies' => 'web,data-jpa,h2',
        ]);

        $response = Http::timeout(120)->sink($zipPath)->get($url);

        if ($response->failed() || ! File::exists($zipPath) || File::size($zipPath) < 1024) {
            throw new RuntimeException('Gagal mengunduh starter Spring Boot. Periksa koneksi internet.');
        }

        if ($job) {
            $job->appendLog('Unduhan starter selesai (' . round(File::size($zipPath) / 1024, 1) . ' KB).');
            $job->appendLog('Mengekstrak berkas struktur Maven & Spring Boot...');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Berkas starter Spring Boot rusak (zip tidak valid).');
        }

        $realTarget = realpath($target);
        if (! $realTarget) {
            File::ensureDirectoryExists($target);
            $realTarget = realpath($target) ?: $target;
        }

        // Deteksi apakah berkas di dalam zip diawali root folder (misal "$artifact/")
        $prefix = $artifact . '/';
        $hasPrefix = true;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! str_starts_with($name, $prefix) && $name !== $artifact && $name !== $prefix) {
                $hasPrefix = false;
                break;
            }
        }

        // Ekstrak dengan perataan prefix dan guard zip-slip
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $subPath = $hasPrefix && str_starts_with($entry, $prefix)
                ? substr($entry, strlen($prefix))
                : $entry;

            if ($subPath === '' || $subPath === false) {
                continue;
            }

            $dest = $realTarget . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subPath);

            // Guard zip-slip: semua entri harus di dalam target
            if (! str_starts_with($dest, $realTarget . DIRECTORY_SEPARATOR)) {
                $zip->close();
                throw new RuntimeException("Entri zip tidak aman: {$entry}");
            }

            if (str_ends_with($entry, '/')) {
                File::ensureDirectoryExists($dest);
            } else {
                File::ensureDirectoryExists(dirname($dest));
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    File::put($dest, $content);
                }
            }
        }

        $zip->close();
        File::delete($zipPath);

        if (! File::exists($realTarget . DIRECTORY_SEPARATOR . 'pom.xml')) {
            throw new RuntimeException('Ekstraksi Spring Boot tidak menghasilkan pom.xml.');
        }
    }

    protected function validateSpringCoords(string $group, string $artifact): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $group)) {
            throw new RuntimeException('GroupId tidak valid (contoh: com.example).');
        }

        if (! preg_match('/^[a-z0-9][a-z0-9-_]{1,60}$/', $artifact)) {
            throw new RuntimeException('ArtifactId tidak valid (huruf kecil/angka/strip, 2–61 karakter).');
        }
    }

    /**
     * Membentuk variabel lingkungan lengkap (PATH, SystemRoot, dsb.)
     * untuk subprocess di Windows & Unix, mencegah Symfony Process memotong PATH.
     */
    public function environment(): array
    {
        $env = getenv();
        $path = getenv('PATH') ?: (getenv('Path') ?: '');

        if (PHP_OS_FAMILY === 'Windows') {
            $extraDirs = [
                dirname(PHP_BINARY),
                'C:\\laragon\\bin\\composer',
                'C:\\ProgramData\\ComposerSetup\\bin',
                (getenv('APPDATA') ? getenv('APPDATA') . '\\Composer\\vendor\\bin' : ''),
                'C:\\nvm4w\\nodejs',
                'C:\\Program Files\\nodejs',
                'C:\\Program Files\\Git\\cmd',
                'C:\\Program Files\\Git\\bin',
            ];

            $currentDirs = array_map('strtolower', explode(PATH_SEPARATOR, $path));
            $toPrepend = [];

            foreach ($extraDirs as $dir) {
                if ($dir !== '' && is_dir($dir) && ! in_array(strtolower($dir), $currentDirs, true)) {
                    $toPrepend[] = $dir;
                    $currentDirs[] = strtolower($dir);
                }
            }

            if (! empty($toPrepend)) {
                $path = implode(PATH_SEPARATOR, $toPrepend) . ($path !== '' ? PATH_SEPARATOR . $path : '');
            }

            $env['PATH'] = $path;
            $env['Path'] = $path;

            if (empty($env['SystemRoot'])) {
                $env['SystemRoot'] = getenv('SystemRoot') ?: 'C:\\Windows';
            }
            if (empty($env['TEMP'])) {
                $env['TEMP'] = getenv('TEMP') ?: (getenv('TMP') ?: 'C:\\Windows\\Temp');
            }
            if (empty($env['TMP'])) {
                $env['TMP'] = $env['TEMP'];
            }
            if (empty($env['USERPROFILE'])) {
                $env['USERPROFILE'] = getenv('USERPROFILE') ?: (getenv('HOME') ?: 'C:\\Users\\Indra');
            }
            if (empty($env['PATHEXT'])) {
                $env['PATHEXT'] = getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD;.VBS;.VBE;.JS;.JSE;.WSF;.WSH;.MSC';
            }
            if (empty($env['ComSpec'])) {
                $env['ComSpec'] = getenv('ComSpec') ?: 'C:\\Windows\\system32\\cmd.exe';
            }
        }

        return $env;
    }

    protected function probe(array $cmd): array
    {
        try {
            $result = Process::timeout(15)
                ->env($this->environment())
                ->run($cmd);

            if ($result->successful()) {
                $firstLine = explode("\n", trim($result->output() . "\n" . $result->errorOutput()))[0] ?? null;

                return ['ok' => true, 'version' => mb_substr($firstLine ?? '', 0, 120) ?: null];
            }
        } catch (\Throwable $e) {
            // tool tidak tersedia
        }

        return ['ok' => false, 'version' => null];
    }

    protected function probeInternet(): array
    {
        try {
            $response = Http::timeout(8)->get('https://start.spring.io');

            return ['ok' => $response->ok(), 'version' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'version' => null];
        }
    }
}
