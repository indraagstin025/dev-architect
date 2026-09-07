<!-- Modal: Konfirmasi Hapus Proyek (Radix Monochrome) -->
<div id="delete-project-modal"
    class="hidden fixed inset-0 z-50 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl max-w-sm w-full p-5 shadow-2xl space-y-3.5 transition-colors">
        <div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Hapus Proyek Ini?</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Tindakan ini hanya menghapus metadata dari DEVArchitect, berkas fisik di disk Anda tetap aman.</p>
        </div>

        <p class="text-xs text-zinc-800 dark:text-zinc-300 font-semibold bg-zinc-100 dark:bg-zinc-950 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-800 truncate"
            id="delete-project-name"></p>

        <div class="flex items-center justify-end gap-2.5 pt-2">
            <button type="button" onclick="closeDeleteModal()"
                class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                Batal
            </button>
            <button type="button" id="btn-confirm-delete" onclick="executeDeleteProject()"
                class="px-4 py-1.5 bg-zinc-100 dark:bg-zinc-900 hover:bg-red-50 dark:hover:bg-zinc-800 text-red-600 dark:text-red-400 border border-red-300 dark:border-red-500/30 font-semibold rounded-lg text-xs transition-colors shadow-xs">
                Ya, Hapus
            </button>
        </div>
    </div>
</div>
