<?php

use App\Models\Member;
use Illuminate\Database\Migrations\Migration;

/**
 * The generational group is now worked out from age and sex (18–29 YPG, 30–39 YAF, 40 and over Men's or Women's
 * Fellowship). Fill it in for the members already on the register; the scheduler keeps it current after that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Member::syncGenerationalGroups();
    }

    public function down(): void
    {
        // Nothing to undo: the groups were empty before.
    }
};
