<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDesktopLoopbackAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();

        // 1. Verifikasi Loopback IP (Hanya izinkan 127.0.0.1, ::1, dan subnet 127.0.0.0/8)
        $isLoopback = $clientIp === '127.0.0.1' || 
                      $clientIp === '::1' || 
                      str_starts_with($clientIp ?? '', '127.');

        if (!$isLoopback) {
            return response()->json([
                'success' => false,
                'message' => 'Akses API ditolak: Layanan DEVArchitect hanya dapat diakses melalui koneksi lokal loopback (127.0.0.1). Akses jaringan eksternal diblokir demi keamanan.',
            ], 403);
        }

        // 2. Proteksi Cross-Origin Browser (Cegah serangan SOP / CSRF dari website luar di browser)
        $origin = $request->header('Origin');
        if ($origin) {
            $parsed = parse_url($origin);
            $host = strtolower($parsed['host'] ?? '');
            $scheme = strtolower($parsed['scheme'] ?? '');
            $allowedHosts = ['127.0.0.1', 'localhost', 'tauri.localhost'];
            $isAllowedOrigin = in_array($host, $allowedHosts, true) || $scheme === 'tauri';

            if (!$isAllowedOrigin) {
                return response()->json([
                    'success' => false,
                    'message' => "Akses API ditolak: Permintaan lintas asal (Cross-Origin) dari [{$origin}] tidak diizinkan.",
                ], 403);
            }
        }

        // 3. Verifikasi Token Bridge Desktop
        // Pada mode testing Laravel, izinkan otomatis kecuali bila pengujian secara spesifik menguji penolakan token
        if (app()->environment('testing')) {
            $testEnforce = $request->header('X-Test-Enforce-Bridge-Key');
            if ($testEnforce) {
                $providedKey = $request->header('X-DEVArchitect-Bridge-Key');
                $expectedKey = AppSetting::getOrCreateDesktopBridgeKey();
                if (!$providedKey || !hash_equals($expectedKey, $providedKey)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akses API ditolak: Token bridge desktop tidak valid.',
                    ], 403);
                }
            }
            return $next($request);
        }

        $expectedBridgeKey = AppSetting::getOrCreateDesktopBridgeKey();
        $providedBridgeKey = $request->header('X-DEVArchitect-Bridge-Key');
        $hasValidBridgeKey = $providedBridgeKey && hash_equals($expectedBridgeKey, $providedBridgeKey);

        $hasValidCsrf = false;
        $csrfHeader = $request->header('X-CSRF-TOKEN');
        if ($csrfHeader && hash_equals($request->session()->token() ?? '', $csrfHeader)) {
            $hasValidCsrf = true;
        }

        if (!$hasValidBridgeKey && !$hasValidCsrf) {
            return response()->json([
                'success' => false,
                'message' => 'Akses API ditolak: Token autentikasi bridge desktop tidak valid atau tidak disertakan.',
            ], 403);
        }

        return $next($request);
    }
}
