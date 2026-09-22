<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per setting in `church_settings` (key / value). */
class ChurchSetting extends Model
{
    public const CREATED_AT = null;

    protected $table = 'church_settings';

    protected $primaryKey = 'setting_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * The church's identity, as shown on the Church Settings page. Form field => setting key.
     * `church_name` and `congregation_name` came with the old system; presbytery and district are new.
     */
    public const IDENTITY = [
        'church_name' => 'church_name',
        'presbytery' => 'presbytery_name',
        'district' => 'district_name',
        'congregation' => 'congregation_name',
    ];

    /** @param  list<string>  $keys  @return array<string, ?string> */
    public static function values(array $keys): array
    {
        return static::whereIn('setting_key', $keys)->pluck('setting_value', 'setting_key')->all();
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['setting_key' => $key], ['setting_value' => $value]);
    }
}
