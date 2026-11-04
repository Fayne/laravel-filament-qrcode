<?php

use Fayne\QrCode\Actions\GenerateMiniAppQrcode;
use Fayne\QrCode\Actions\GenerateWebQrcode;
use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Filament\Resources\QrcodeResource;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Admin;
use Tests\Support\PackageDatabase;

beforeEach(fn () => PackageDatabase::prepare());

it('renders list and create screens in the selected language', function (string $locale, string $name, string $type, string $expiry) {
    app()->setLocale($locale);
    $admin = Admin::create(['name' => 'QR Admin', 'email' => 'locale@example.test', 'password' => 'password']);
    foreach (['viewAnyQrcode', 'createQrcode', 'updateQrcode'] as $permission) {
        $admin->givePermissionTo(Permission::create(['name' => $permission, 'guard_name' => 'admin']));
    }
    Qrcode::create(['internal_name' => 'Example', 'type' => 'web_url', 'target_url' => 'https://example.com']);
    $this->actingAs($admin, 'admin')->get('/admin/qrcodes')->assertOk()->assertSee($name)->assertSee($type);
    $this->get('/admin/qrcodes/create')->assertOk()->assertSee($name)->assertSee($expiry);
    expect(QrcodeResource::getModelLabel())->toBe($locale === 'zh_CN' ? '二维码' : 'QR code');
    expect(GenerateWebQrcode::make()->getLabel())->toBe($locale === 'zh_CN' ? '生成网页二维码' : 'Generate web QR code');
    expect(GenerateMiniAppQrcode::make()->getLabel())->toBe($locale === 'zh_CN' ? '生成小程序码' : 'Generate Mini App QR code');
})->with([
    ['zh_CN', '内部名称', '网页网址', '过期时间'],
    ['en', 'Internal name', 'Web URL', 'Expires at'],
]);
