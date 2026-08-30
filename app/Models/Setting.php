<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// A generic key/value store - no settings/config table existed anywhere in
// this app before. "Current school year" is the first real use (one row,
// key = 'current_school_year'), kept generic so a future setting doesn't
// need its own migration.
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
