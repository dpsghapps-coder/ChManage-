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
     * `church_name` and `congregation_name` came with the old system; presbytery, district and city are new.
     * The city decides which neighbourhoods the member form suggests for Residence.
     */
    public const IDENTITY = [
        'church_name' => 'church_name',
        'presbytery' => 'presbytery_name',
        'district' => 'district_name',
        'congregation' => 'congregation_name',
        'city' => 'city_name',
    ];

    /** Days without a visit or a lesson after which someone still coming is flagged on the Newcomers overview. */
    public const FOLLOWUP_DAYS = 'newcomer_followup_days';

    public const DEFAULT_FOLLOWUP_DAYS = 60;

    public static function followUpDays(): int
    {
        $days = (int) (static::values([self::FOLLOWUP_DAYS])[self::FOLLOWUP_DAYS] ?? 0);

        return $days > 0 ? $days : self::DEFAULT_FOLLOWUP_DAYS;
    }

    /** Days before a committee term ends that it is flagged for renewal. */
    public const TERM_WARNING_DAYS = 'committee_term_warning_days';

    public const DEFAULT_TERM_WARNING_DAYS = 90;

    public static function termWarningDays(): int
    {
        $days = (int) (static::values([self::TERM_WARNING_DAYS])[self::TERM_WARNING_DAYS] ?? 0);

        return $days > 0 ? $days : self::DEFAULT_TERM_WARNING_DAYS;
    }

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
