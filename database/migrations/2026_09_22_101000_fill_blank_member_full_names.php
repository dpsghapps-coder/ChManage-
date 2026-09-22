<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Some migrated members have an empty full_name although their first name and surname are on file, so the list showed no name.
 * The register writes names as "Surname Firstname", so rebuild them from the two parts. Names that are already there are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE members
            SET full_name = TRIM(CONCAT_WS(' ', NULLIF(TRIM(last_name), ''), NULLIF(TRIM(first_name), '')))
            WHERE TRIM(full_name) = ''
              AND (TRIM(COALESCE(first_name, '')) <> '' OR TRIM(COALESCE(last_name, '')) <> '')
        SQL);
    }

    public function down(): void
    {
        // The rebuilt names cannot be told apart from the original ones, and nothing depends on them being blank.
    }
};
