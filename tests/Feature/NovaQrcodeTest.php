<?php

use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Policies\QrcodePolicy;
use Fayne\QrCode\Support\QrcodeTypes;
use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Admin;
use Tests\Support\PackageDatabase;

beforeEach(fn () => PackageDatabase::prepare());

it('authorizes QR updates through the host permission model', function () {
    $policy = new QrcodePolicy;
    $admin = Admin::query()->create(['name' => 'QR Admin', 'email' => 'policy@example.test', 'password' => 'password']);

    expect($policy->update(new GenericUser(['id' => 1]), new Qrcode))->toBeFalse();

    $admin->givePermissionTo(Permission::query()->create(['name' => 'updateQrcode', 'guard_name' => 'admin']));

    expect($policy->update($admin, new Qrcode))->toBeTrue();
});

it('rejects type-specific values that are not valid for the selected QR type', function () {
    expect(fn () => QrcodeTypes::validate([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Home page',
        'target_url' => 'https://example.test',
        'scene' => 'forged-mini-scene',
    ]))->toThrow(ValidationException::class);
});

it('keeps historical QR types filterable when creation is restricted', function () {
    config(['qrcode.allowed_types' => [QrcodeTypes::WEB_URL]]);

    expect(QrcodeTypes::allowed())->toBe([QrcodeTypes::WEB_URL])
        ->and(QrcodeTypes::supports(QrcodeTypes::WECHAT_MINI_APP, [QrcodeTypes::WEB_URL]))->toBeFalse();
});

it('requires each QR type to provide only its own generation fields', function () {
    expect(fn () => QrcodeTypes::validate([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Home page',
        'target_url' => 'https://example.test',
        'scene' => 'not-allowed',
    ]))->toThrow(ValidationException::class);

    expect(QrcodeTypes::validate([
        'type' => QrcodeTypes::WECHAT_MINI_APP,
        'internal_name' => 'Mini app',
        'scene' => 'store-1',
        'path' => 'pages/store/index',
    ]))->toBe(QrcodeTypes::WECHAT_MINI_APP);
});

it('rejects generating a disabled type while preserving existing records', function () {
    config(['qrcode.allowed_types' => [QrcodeTypes::WEB_URL]]);

    expect(QrcodeTypes::supports(QrcodeTypes::WECHAT_MINI_APP))->toBeFalse()
        ->and(QrcodeTypes::validate([
            'internal_name' => 'Existing mini app',
            'scene' => 'store-1',
            'path' => 'pages/store/index',
        ], QrcodeTypes::WECHAT_MINI_APP))->toBe(QrcodeTypes::WECHAT_MINI_APP);
});
