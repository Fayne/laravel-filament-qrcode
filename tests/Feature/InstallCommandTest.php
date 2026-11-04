<?php

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->qrcodeConfigPath = sys_get_temp_dir() . '/qrcode-install-' . bin2hex(random_bytes(6));
    mkdir($this->qrcodeConfigPath);
    app()->useConfigPath($this->qrcodeConfigPath);
    (new ReflectionMethod(\Fayne\QrCode\QrcodeServiceProvider::class, 'publishes'))->invoke(app()->getProvider(\Fayne\QrCode\QrcodeServiceProvider::class), [dirname(__DIR__, 2) . '/config/qrcode.php' => config_path('qrcode.php')], 'qrcode-config');
});

afterEach(function () {
    @unlink($this->qrcodeConfigPath . '/qrcode.php');
    @rmdir($this->qrcodeConfigPath);
});

it('installs fresh tables and missing configuration without requiring permissions or tenant context', function () {
    config(['filament-cms.tenancy.enabled' => false]);
    $this->artisan('cms:qrcode:install')->assertSuccessful();
    expect(Schema::hasColumns('qrcodes', ['tenant_id', 'internal_name', 'scene']))->toBeTrue()
        ->and(is_file(config_path('qrcode.php')))->toBeTrue();
    file_put_contents(config_path('qrcode.php'), '<?php return ["custom" => true];');
    $this->artisan('cms:qrcode:install')->assertSuccessful();
    expect(file_get_contents(config_path('qrcode.php')))->toContain('custom');
});

it('initializes permission definitions idempotently without granting roles', function () {
    (require dirname(__DIR__, 2) . '/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    $this->artisan('cms:qrcode:install')->assertSuccessful();
    $count = Permission::count();
    expect($count)->toBe(9);
    $this->artisan('cms:qrcode:install')->assertSuccessful();
    expect(Permission::count())->toBe($count)
        ->and(Illuminate\Support\Facades\DB::table('role_has_permissions')->count())->toBe(0);
});

it('requires explicit confirmation in production when non interactive', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('cms:qrcode:install', ['--no-interaction' => true])->assertFailed();
    expect(Schema::hasTable('qrcodes'))->toBeFalse();
    $this->artisan('cms:qrcode:install', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
});
