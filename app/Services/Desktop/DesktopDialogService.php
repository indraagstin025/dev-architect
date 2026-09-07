<?php

namespace App\Services\Desktop;

use App\Services\ProjectService;
use Native\Desktop\Dialog;

class DesktopDialogService
{
    public function __construct(
        protected ProjectService $projectService
    ) {}

    /**
     * Membuka dialog native Windows Explorer untuk memilih folder proyek.
     *
     * @return array{
     *     cancelled: bool,
     *     path: ?string,
     *     valid: bool,
     *     message: string,
     *     framework?: ?string,
     *     framework_label?: ?string,
     *     project_name?: ?string
     * }
     */
    public function pickProjectFolder(?string $defaultPath = null): array
    {
        $dialog = Dialog::new()
            ->title('Pilih Direktori Proyek Backend')
            ->button('Pilih Folder Ini')
            ->folders();

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

        // Validasi dan deteksi tipe framework & dialek database
        $validation = $this->projectService->validateFolder($selectedPath);
        $framework = $validation['framework'] ?? null;

        return [
            'cancelled' => false,
            'path' => $selectedPath,
            'valid' => $validation['valid'],
            'message' => $validation['message'],
            'project_name' => $validation['project_name'],
            'framework' => $framework?->value,
            'framework_label' => $framework?->label(),
            'dialect' => $validation['dialect'] ?? null,
            'dialect_label' => $validation['dialect_label'] ?? null,
        ];
    }
}
