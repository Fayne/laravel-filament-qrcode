<?php

use Fayne\QrCode\Contracts\Qrcodeable;
use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Exceptions\MiniAppClientException;
use Fayne\QrCode\Events\CacheableUpdatedEvent;
use Fayne\QrCode\Support\QrcodeTypes;
use Fayne\QrCode\Traits\HasQrcodes;
use Tests\Fixtures\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use EasyWeChat\Kernel\HttpClient\Response;
use Tests\Support\PackageDatabase;

beforeEach(function () {
    PackageDatabase::prepare();
    Schema::create('qrcode_test_entities', function ($table) {
        $table->id();
        $table->timestamps();
    });
    config(['qrcode.allowed_types' => ['wechat_mini_app', 'web_url']]);
});

afterEach(function () {
    Schema::dropIfExists('qrcode_test_entities');
});

function qrcodeableEntity(): Model
{
    return new class extends Model implements Qrcodeable
    {
        use HasQrcodes;

        protected $table = 'qrcode_test_entities';

        protected $guarded = [];
    };
}

it('accepts each supported QR type when both and only that type are enabled', function (array $allowed, string $type) {
    config(['qrcode.allowed_types' => $allowed]);

    expect(QrcodeTypes::supports($type))->toBeTrue();
})->with([
    'both types' => [['wechat_mini_app', 'web_url'], 'wechat_mini_app'],
    'mini app only' => [['wechat_mini_app'], 'wechat_mini_app'],
    'web only' => [['web_url'], 'web_url'],
]);

it('rejects empty, unknown, and duplicate allowed type configuration', function (array $allowed) {
    expect(fn () => QrcodeTypes::allowed($allowed))->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => [[]],
    'unknown' => [['web_url', 'custom']],
    'duplicate' => [['web_url', 'web_url']],
]);

it('persists a generated web QR image and its polymorphic owner', function () {
    Storage::fake('public');
    $entity = qrcodeableEntity();
    $entity->save();

    $qrcode = $entity->generateWebQrcode('Store website', 'https://example.test/store');

    expect($qrcode->exists)->toBeTrue()
        ->and($qrcode->entity()->is($entity))->toBeTrue()
        ->and($qrcode->type)->toBe(QrcodeTypes::WEB_URL)
        ->and($qrcode->urls)->toHaveKey('430_web');

    $path = 'images/qrcodes/' . md5('web_https://example.test/store430') . '.png';
    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('public')->get($path))->toStartWith("\x89PNG\r\n\x1a\n");
});

it('rejects invalid URLs and editing generation inputs of a disabled existing type', function () {
    $entity = qrcodeableEntity();
    $entity->save();

    expect(fn () => $entity->qrcodes()->create([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Invalid URL',
        'target_url' => 'javascript:alert(1)',
    ]))->toThrow(ValidationException::class);

    $qrcode = $entity->qrcodes()->create([
        'type' => QrcodeTypes::WECHAT_MINI_APP,
        'internal_name' => 'Mini QR',
        'scene' => 'store-1',
        'path' => 'pages/store/index',
    ]);
    config(['qrcode.allowed_types' => [QrcodeTypes::WEB_URL]]);
    $qrcode->path = 'pages/store/other';

    expect(fn () => $qrcode->save())->toThrow(ValidationException::class);
});

it('clears generated web image URLs when the target changes', function () {
    $entity = qrcodeableEntity();
    $entity->save();
    $qrcode = $entity->qrcodes()->create([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Store QR',
        'target_url' => 'https://example.test/one',
        'urls' => ['430_web' => 'https://cdn.example.test/old.png'],
    ]);

    $qrcode->target_url = 'https://example.test/two';
    $qrcode->save();

    expect($qrcode->fresh()->urls)->toBeNull();
});

it('does not record a Mini App image URL when WeChat rejects generation', function () {
    Storage::fake('public');
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, '{"errcode":40129,"errmsg":"invalid scene"}');
    rewind($stream);

    $response = Mockery::mock(Response::class);
    $response->shouldReceive('isSuccessful')->andReturnTrue();
    $response->shouldReceive('toStream')->andReturn($stream);
    $client = Mockery::mock();
    $client->shouldReceive('postJson')->once()->andReturn($response);
    $miniApp = Mockery::mock();
    $miniApp->shouldReceive('getClient')->andReturn($client);
    app()->instance('mini-app', $miniApp);

    $entity = qrcodeableEntity();
    $entity->save();

    expect(fn () => $entity->generateQrcode('Mini QR', 'pages/store/index', 'store-1'))
        ->toThrow(MiniAppClientException::class);

    expect($entity->qrcodes()->firstOrFail()->urls)->toBeNull();
});

it('persists the Mini App image URL only after generation returns one', function () {
    Storage::fake('public');
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "\x89PNG\r\n\x1a\nmock-image");
    rewind($stream);

    $response = Mockery::mock(Response::class);
    $response->shouldReceive('isSuccessful')->andReturnTrue();
    $response->shouldReceive('toStream')->andReturn($stream);
    $client = Mockery::mock();
    $client->shouldReceive('postJson')->once()->andReturn($response);
    $miniApp = Mockery::mock();
    $miniApp->shouldReceive('getClient')->andReturn($client);
    app()->instance('mini-app', $miniApp);

    $entity = qrcodeableEntity();
    $entity->save();
    $result = $entity->generateQrcode('Mini QR', 'pages/store/index', 'store-1');

    expect($result['qrcode']->fresh()->urls)->toHaveKey('430_release');
});

it('enforces qrcode resource permissions on direct panel URLs', function () {
    $admin = Admin::query()->create([
        'name' => 'QR Admin',
        'email' => 'qr-admin@example.test',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin/qrcodes')
        ->assertForbidden();

    $admin->givePermissionTo(Permission::query()->create([
        'name' => 'viewAnyQrcode',
        'guard_name' => 'admin',
    ]));
    $admin->givePermissionTo(Permission::query()->create([
        'name' => 'createQrcode',
        'guard_name' => 'admin',
    ]));

    $this->get('/admin/qrcodes')->assertOk();
    $this->get('/admin/qrcodes/create')->assertOk();
});

it('dispatches cache update events for create, update, and delete', function () {
    Event::fake([CacheableUpdatedEvent::class]);
    $entity = qrcodeableEntity();
    $entity->save();
    $qrcode = $entity->qrcodes()->create([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Event QR',
        'target_url' => 'https://example.test/event',
    ]);
    $qrcode->update(['internal_name' => 'Updated Event QR']);
    $qrcode->delete();

    Event::assertDispatchedTimes(CacheableUpdatedEvent::class, 3);
    Event::assertDispatched(CacheableUpdatedEvent::class, fn (CacheableUpdatedEvent $event): bool => $event->getModel() instanceof Qrcode);
});
