<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Helper to retrieve a setting value by key with caching.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return \Illuminate\Support\Facades\Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            if ($setting->is_encrypted && !empty($setting->value)) {
                try {
                    return decrypt($setting->value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    \Illuminate\Support\Facades\Log::warning("Gagal mendekripsi setting [{$key}]: Kunci enkripsi aplikasi mungkin telah berubah.");
                    return $default;
                } catch (\Throwable $e) {
                    return $default;
                }
            }

            return $setting->value ?? $default;
        });
    }

    /**
     * Helper to save a setting key-value pair and clear cache.
     */
    public static function set(string $key, mixed $value, bool $encrypt = false): self
    {
        $storedValue = ($encrypt && !empty($value)) ? encrypt($value) : $value;

        $record = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $encrypt,
            ]
        );

        \Illuminate\Support\Facades\Cache::forget("setting:{$key}");

        return $record;
    }

    /**
     * Mengambil atau membuat token rahasia bridge desktop untuk otentikasi API internal.
     */
    public static function getOrCreateDesktopBridgeKey(): string
    {
        $key = static::get('desktop_bridge_token');
        if (empty($key)) {
            $key = \Illuminate\Support\Str::random(64);
            static::set('desktop_bridge_token', $key);
        }

        return $key;
    }
}
