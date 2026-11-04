<?php

use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Support\QrcodeTenancy;
use Illuminate\Validation\ValidationException;
use Tests\Support\PackageDatabase;

beforeEach(function () {
    PackageDatabase::prepare();
    config(['filament-cms.tenancy.enabled' => false]);
});
function tenantQr(): Qrcode
{
    return Qrcode::create(['type' => 'web_url', 'internal_name' => 'same', 'target_url' => 'https://example.com']);
}
function qrTenant(?int $id): void
{
    config(['filament-cms.tenancy.enabled' => true]);
    app()->instance('Fayne\CMS\Support\TenantContext', new class($id)
    {
        public function __construct(private ?int $value) {} public function id()
        {
            return $this->value;
        }
    });
}
it('preserves standalone single site behavior', function () {
    $q = tenantQr();
    expect($q->tenant_id)->toBe(0)->and(Qrcode::count())->toBe(1)->and(QrcodeTenancy::storagePath('images/qrcodes'))->toBe('images/qrcodes');
});
it('isolates two tenants with the same internal name', function () {
    qrTenant(1);
    $a = tenantQr();
    qrTenant(2);
    $b = tenantQr();
    expect(Qrcode::find($a->id))->toBeNull()->and(Qrcode::count())->toBe(1);
    qrTenant(1);
    expect(Qrcode::find($b->id))->toBeNull();
});
it('requires context in multi tenant mode', function () {
    qrTenant(null);
    tenantQr();
})->throws(ValidationException::class);
it('rejects forged ownership', function () {
    qrTenant(1);
    Qrcode::create(['tenant_id' => 2, 'type' => 'web_url', 'internal_name' => 'forged', 'target_url' => 'https://example.com']);
})->throws(ValidationException::class);
it('rejects cross tenant updates and deletes', function () {
    qrTenant(1);
    $q = tenantQr();
    qrTenant(2);
    expect(fn () => $q->update(['internal_name' => 'changed']))->toThrow(ValidationException::class);
    expect(fn () => $q->delete())->toThrow(ValidationException::class);
});
it('enforces tenant name uniqueness', function () {
    qrTenant(1);
    tenantQr();
    tenantQr();
})->throws(Illuminate\Database\QueryException::class);
it('separates file paths and single site data', function () {
    qrTenant(1);
    tenantQr();
    $a = QrcodeTenancy::storagePath('images');
    qrTenant(2);
    expect(QrcodeTenancy::storagePath('images'))->not->toBe($a);
    config(['filament-cms.tenancy.enabled' => false]);
    expect(Qrcode::count())->toBe(0);
});

it('keeps shared Mini App scenes globally unique', function () {
    qrTenant(1);
    Qrcode::create(['type' => 'wechat_mini_app', 'internal_name' => 'one', 'scene' => 'shared', 'path' => 'pages/index']);
    qrTenant(2);
    Qrcode::create(['type' => 'wechat_mini_app', 'internal_name' => 'two', 'scene' => 'shared', 'path' => 'pages/index']);
})->throws(Illuminate\Database\QueryException::class);

it('rejects a foreign association owner before generation', function () {
    qrTenant(2);
    $owner = new class extends Illuminate\Database\Eloquent\Model
    {
        use Fayne\QrCode\Traits\HasQrcodes;
    };
    $owner->setAttribute('tenant_id', 1);
    expect(fn () => $owner->generateWebQrcode('foreign', 'https://example.com'))->toThrow(ValidationException::class);
});
