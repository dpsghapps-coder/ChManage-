<?php

namespace App\Support;

use App\Models\Permission;

/**
 * Reads config/permissions.php and keeps the `permissions` table in step with it.
 */
class PermissionRegistry
{
    /** @return array<string, array{module: string, description: string}> keyed by permission name */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (config('permissions.modules') as $module => $meta) {
            foreach ($meta['actions'] as $action => $description) {
                $definitions["{$module}.{$action}"] = ['module' => $module, 'description' => $description];
            }
        }

        return $definitions;
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::definitions());
    }

    /** @return array<string, string> module => label */
    public static function moduleLabels(): array
    {
        return collect(config('permissions.modules'))->map(fn ($meta) => $meta['label'])->all();
    }

    /**
     * Expand patterns such as "*", "members.*" and "members.view" into concrete permission names.
     * Unknown names are dropped so a typo in the config can never grant something unintended.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public static function expand(array $patterns): array
    {
        $all = self::names();
        $out = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return $all;
            }

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                array_push($out, ...array_filter($all, fn ($name) => str_starts_with($name, $prefix)));
            } elseif (in_array($pattern, $all, true)) {
                $out[] = $pattern;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Upsert every registered permission and remove ones no longer in the registry.
     *
     * @return array{created: int, updated: int, removed: int}
     */
    public static function sync(): array
    {
        $definitions = self::definitions();
        $created = $updated = 0;

        foreach ($definitions as $name => $meta) {
            $permission = Permission::firstOrNew(['name' => $name]);
            $isNew = ! $permission->exists;

            $permission->fill($meta);

            if ($isNew) {
                $created++;
            } elseif ($permission->isDirty()) {
                $updated++;
            }

            $permission->save();
        }

        $removed = Permission::whereNotIn('name', array_keys($definitions))->delete();

        return compact('created', 'updated', 'removed');
    }
}
