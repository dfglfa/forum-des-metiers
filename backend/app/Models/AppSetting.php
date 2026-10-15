<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $setting = static::find($key);
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    public static function currentPhase(): string
    {
        return static::get('current_phase', 'preparation');
    }

    public static function isPreparationPhase(): bool
    {
        return static::currentPhase() === 'preparation';
    }

    public static function isSelectionPhase(): bool
    {
        return static::currentPhase() === 'selection';
    }

    public static function isConferencePhase(): bool
    {
        return static::currentPhase() === 'conference';
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key);
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function graduationYearMax(): int
    {
        return min((int) static::get('graduation_year_max', 2050), now()->year);
    }
}
