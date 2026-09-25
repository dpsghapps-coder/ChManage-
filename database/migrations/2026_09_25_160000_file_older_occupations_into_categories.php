<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Files the older occupations (from before the categories) into the categories of resources/data/occupations.json, so
 * none is left uncategorised. Members keep their occupation: nothing is renamed, merged or removed.
 */
return new class extends Migration
{
    private const CATEGORY_OF = [
        'Demographics & Non-Working Status' => ['Student', 'Unemployed', 'House Wife'],
        'Trade, Commerce & Sales' => ['Trading', 'Store Management', 'Sales & Marketing', 'Business Woman', 'Business Man'],
        'Information Technology & Engineering' => ['IT', 'Engineering', 'Architect', 'Architectural Draftman'],
        'Healthcare & Medical Services' => ['Nurse', 'Medical'],
        'Education & Academia' => ['Teaching', 'Education'],
        'Business, Finance & Administration' => ['Office & Admin', 'Banker', 'Accountancy', 'Human Resources', 'Management', 'Customer Services', 'Secretary'],
        'Public, Security & Uniformed Services' => ['Public Servant', 'Civil Servant', 'Security', 'Military', 'Legal Services'],
        'Hospitality, Catering & Food Services' => ['Catering', 'Tourism', 'House Keeping'],
        'Transport, Driving & Logistics' => ['Driver', 'Logistics'],
        'Artisans, Crafts & Technical Vocations' => [
            'Fashion Designer', 'Construction', 'Technician (Elec.)', 'Carpentry', 'Auto Electrician', 'Painting & Decoration',
            'Shoe Repairer', 'Mason', 'Technician (Refrigeration)',
        ],
        'Media, Arts & Entertainment' => ['Media', 'Sound Engineer'],
        'Clergy, Religious & Community Work' => ['Minister of Religion', 'Catechist'],
    ];

    public function up(): void
    {
        foreach (self::CATEGORY_OF as $category => $names) {
            DB::table('professions')->whereNull('category')->whereIn('name', $names)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        // Left filed: the categories may have been edited since.
    }
};
