<?php

namespace App\Providers;

use App\Events\ToggleWindowVisibility;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\GlobalShortcut;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // 1. Konfigurasi Jendela Utama Aplikasi Desktop
        Window::open('main')
            ->title('DEVArchitect - Universal AI Database Architect')
            ->width(1360)
            ->height(860)
            ->minWidth(1024)
            ->minHeight(700)
            ->rememberState();

        // 2. Registrasi Hotkey Global (Ctrl+Alt+A)
        GlobalShortcut::key('Control+Alt+A')
            ->event(ToggleWindowVisibility::class)
            ->register();

        // 3. Konfigurasi System Tray (TASK-405)
        MenuBar::create()
            ->tooltip('DEVArchitect - Universal AI Database Architect')
            ->onlyShowContextMenu()
            ->withContextMenu(
                Menu::make(
                    Menu::label('DEVArchitect v2.0'),
                    Menu::separator(),
                    Menu::link(url('/'), 'Buka DEVArchitect'),
                    Menu::separator(),
                    Menu::quit('Keluar dari Aplikasi')
                )
            );
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
