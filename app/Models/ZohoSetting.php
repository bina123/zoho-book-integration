<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton table holding app-level Zoho configuration (selected organization,
 * future preferences). Always operated on with id = 1.
 */
class ZohoSetting extends Model
{
    public const SINGLETON_ID = 1;

    protected $fillable = [
        'organization_id',
        'organization_name',
    ];

    public static function singleton(): self
    {
        return static::firstOrCreate(['id' => self::SINGLETON_ID]);
    }
}
