<?php

namespace Tests\Support;

use Spatie\Permission\PermissionRegistrar;

final class PackageDatabase
{
    public static function prepare(): void
    {
        $package = dirname(__DIR__, 2);
        (require $package . '/tests/database/migrations/0001_create_admins_table.php')->up();
        (require $package . '/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();

        foreach (glob($package . '/database/migrations/*.php') ?: [] as $migration) {
            (require $migration)->up();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
