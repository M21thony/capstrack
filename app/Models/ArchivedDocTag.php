<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedDocTag extends Model
{
    protected $primaryKey = 'tag_id';

    public $timestamps = false;

    protected $fillable = [
        'tag_name',
        'tag_color',
    ];

    /**
     * The archive's tags for these project tags, made where missing.
     *
     * The archive uses the same list a team picks from on its pre-proposal
     * (ProjectTags::OPTIONS), so Analytics counts both the same way.
     *
     * @param  string[]  $names
     * @return int[] tag ids
     */
    public static function idsFor(array $names): array
    {
        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn ($name) => (int) static::firstOrCreate(['tag_name' => $name])->tag_id)
            ->values()
            ->all();
    }
}
