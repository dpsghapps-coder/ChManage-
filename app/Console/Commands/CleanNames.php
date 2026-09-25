<?php

namespace App\Console\Commands;

use App\Support\NameFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Rewrites people's names in Title Case across the database. Safe to run again: names that are already tidy are left alone. */
class CleanNames extends Command
{
    protected $signature = 'names:clean {--dry-run : Show what would change without changing anything}';

    protected $description = 'Put people\'s names in Title Case and tidy their spacing';

    /**
     * Person-name columns: table => [primary key, columns]. Places, organisations and account names are not touched.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const COLUMNS = [
        'members' => ['id', ['full_name', 'first_name', 'last_name', 'maiden_name', 'spouse_name', 'father_name', 'mother_name']],
        'young_members' => ['id', ['first_name', 'last_name', 'other_names']],
        'young_member_guardians' => ['id', ['name']],
        'member_next_of_kin' => ['member_id', ['name']],
        'member_children' => ['id', ['name']],
        'member_beneficiaries' => ['id', ['name']],
        'member_sacraments' => ['id', ['minister']],
        'staff' => ['id', ['full_name', 'emergency_contact_name']],
        'users' => ['id', ['first_name', 'last_name']],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $total = 0;
        $rows = [];

        DB::transaction(function () use ($dry, &$total, &$rows) {
            foreach (self::COLUMNS as $table => [$key, $columns]) {
                $changed = array_fill_keys($columns, 0);
                $samples = [];

                DB::table($table)->select([$key, ...$columns])->orderBy($key)->chunk(500, function ($chunk) use ($table, $key, $columns, $dry, &$changed, &$samples) {
                    foreach ($chunk as $row) {
                        $updates = [];

                        foreach ($columns as $column) {
                            $clean = NameFormatter::titleCase($row->{$column});

                            if ($clean !== $row->{$column}) {
                                $updates[$column] = $clean;
                                $changed[$column]++;
                                $samples[$column] ??= [$row->{$column}, $clean];
                            }
                        }

                        if ($updates && ! $dry) {
                            DB::table($table)->where($key, $row->{$key})->update($updates);
                        }
                    }
                });

                foreach ($columns as $column) {
                    $total += $changed[$column];
                    $rows[] = [$table, $column, $changed[$column], isset($samples[$column]) ? '"'.$samples[$column][0].'" → "'.$samples[$column][1].'"' : ''];
                }
            }
        });

        $this->table(['Table', 'Column', $dry ? 'Would change' : 'Changed', 'Example'], $rows);
        $this->info(($dry ? 'Dry run: ' : '').number_format($total).' name values '.($dry ? 'would be' : 'were').' updated.');

        return self::SUCCESS;
    }
}
