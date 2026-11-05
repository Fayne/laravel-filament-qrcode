<?php

namespace Fayne\QrCode\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

final class InstallCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'cms:qrcode:install {--force : Allow installation in production}';

    protected $description = 'Install QR code tables, publish missing configuration and initialize permission definitions';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if (! (app()->bound(\Fayne\CMS\Support\InstallationContext::class) && app(\Fayne\CMS\Support\InstallationContext::class)->prepared)) {
            $status = $this->call('vendor:publish', ['--tag' => ['qrcode-config', 'qrcode-migrations'], '--no-interaction' => true]);
            if ($status !== self::SUCCESS) {
                return $status;
            }
            if (! is_file(config_path('qrcode.php'))) {
                $this->error('QR code configuration could not be published.');

                return self::FAILURE;
            }
            $status = $this->call('migrate', ['--force' => true]);
            if ($status !== self::SUCCESS) {
                return $status;
            }
        }

        $permission = config('permission.models.permission', 'Spatie\\Permission\\Models\\Permission');
        if (class_exists($permission) && ($model = new $permission)->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
            $cms = class_exists(\Fayne\CMS\Support\Authorization\PermissionRegistry::class);
            $definition = require __DIR__ . '/../../resources/permissions.php';
            $guard = config('filament-cms.guard', config('auth.defaults.guard', 'admin'));
            $model->getConnection()->transaction(function () use ($permission, $definition, $guard, $cms): void {
                foreach ($definition['resources'] as $resource) {
                    foreach ($definition['resource_actions'][$resource] as $ability) {
                        $permission::firstOrCreate(
                            ['name' => ($cms ? 'tenant.' : '') . $ability . $resource, 'guard_name' => $guard],
                            $cms ? ['scope' => 'tenant'] : [],
                        );
                    }
                }
            });
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('QR code permission definitions initialized. Existing role grants were preserved.');
        } else {
            $this->warn('Permission tables are unavailable. Configure host authorization before exposing QR administration.');
        }

        $this->info('QR code installed. Register QrcodePlugin on your panel and configure storage / Mini App credentials as needed.');
        $this->comment('Existing configuration was preserved. No credentials, roles, demo records or QR images were created.');

        return self::SUCCESS;
    }
}
