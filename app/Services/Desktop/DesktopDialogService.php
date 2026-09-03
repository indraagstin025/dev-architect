<?php

namespace App\Services\Desktop;

use App\Services\ProjectService;
use Native\Desktop\Dialog;

class DesktopDialogService
{
    public function __construct(
        protected ProjectService $projectService = new ProjectService()
    ) {}

    /**
     * Membuka dialog native Windows Explorer untuk memilih folder proyek.
     *
     * @return array{
     *     cancelled: bool,
     *     path: ?string,
     *     valid: bool,
     *     message: string,
     *     framework?: \App\Enums\TargetFramework,
     *     project_name?: string
     * }
     */
    public function pickProjectFolder(?string $defaultPath = null): array
    {
        $dialog = Dialog::new()
            ->title('Pilih Direktori Proyek Backend')
            ->button('Pilih Folder Ini')
            ->folders(); // Khusus direktori

        if (!empty($defaultPath)) {
            $dialog->defaultPath($defaultPath);
        }

        // Buka dialog OS native (Windows Explorer)
        $selectedPath = $dialog->open();

        // Jika pengguna menekan tombol Batal/Cancel
        if (empty($selectedPath)) {
            return [
                'cancelled' => true,
                'path' => null,
                'valid' => false,
                'message' => 'Pemilihan folder dibatalkan oleh pengguna.',
            ];
        }

        // Validasi dan deteksi tipe framework
        $validation = $this->projectService->validateFolder($selectedPath);

        return array_merge([
            'cancelled' => false,
            'path' => $selectedPath,
        ], $validation);
    }
}
