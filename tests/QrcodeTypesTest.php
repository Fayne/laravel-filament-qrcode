<?php

use Fayne\QrCode\Support\QrcodeTypes;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $app = new Container;
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    $app->instance('config', new Repository(['qrcode' => ['allowed_types' => ['wechat_mini_app', 'web_url']]]));
    $translator = new Translator(new ArrayLoader, 'en');
    $app->instance('validator', new Factory($translator, $app));
});

it('uses the configured allowed qrcode types', function () {
    $allowed = QrcodeTypes::allowed(['web_url']);

    expect($allowed)->toBe(['web_url'])
        ->and(QrcodeTypes::supports('web_url', $allowed))->toBeTrue()
        ->and(QrcodeTypes::supports('wechat_mini_app', $allowed))->toBeFalse();
});

it('rejects an empty allowed type configuration', function () {
    expect(fn () => QrcodeTypes::allowed([]))->toThrow(InvalidArgumentException::class);
});

it('rejects unknown allowed types', function () {
    expect(fn () => QrcodeTypes::allowed(['unknown']))->toThrow(InvalidArgumentException::class);
});

it('requires the web URL for a web qrcode', function () {
    expect(fn () => QrcodeTypes::validate([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Home page',
    ]))->toThrow(ValidationException::class);
});

it('accepts a valid web qrcode payload', function () {
    expect(QrcodeTypes::validate([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Home page',
        'target_url' => 'https://example.com/path?q=1',
    ]))->toBe(QrcodeTypes::WEB_URL);
});

it('uses the sole allowed type when a type is not submitted', function () {
    config()->set('qrcode.allowed_types', [QrcodeTypes::WEB_URL]);

    expect(QrcodeTypes::validate([
        'internal_name' => 'Home page',
        'target_url' => 'https://example.com',
    ]))->toBe(QrcodeTypes::WEB_URL);
});

it('rejects creating a type disabled by configuration', function () {
    config()->set('qrcode.allowed_types', [QrcodeTypes::WEB_URL]);

    expect(fn () => QrcodeTypes::validate([
        'type' => QrcodeTypes::WECHAT_MINI_APP,
        'internal_name' => 'Mini App page',
        'scene' => 'scene',
        'path' => 'pages/index',
    ]))->toThrow(ValidationException::class);
});

it('keeps a historical disabled type unchanged on update', function () {
    config()->set('qrcode.allowed_types', [QrcodeTypes::WEB_URL]);

    expect(QrcodeTypes::validate([
        'type' => QrcodeTypes::WECHAT_MINI_APP,
        'internal_name' => 'Historical mini code',
        'scene' => 'scene',
        'path' => 'pages/index',
    ], QrcodeTypes::WECHAT_MINI_APP))->toBe(QrcodeTypes::WECHAT_MINI_APP);
});

it('does not allow changing the qrcode type after creation', function () {
    expect(fn () => QrcodeTypes::validate([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Web code',
        'target_url' => 'https://example.com',
    ], QrcodeTypes::WECHAT_MINI_APP))->toThrow(ValidationException::class);
});

it('accepts null values for fields belonging to the other qrcode type', function () {
    expect(QrcodeTypes::validate([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Home page',
        'target_url' => 'https://example.com/path?q=1',
        'scene' => null,
        'path' => null,
    ]))->toBe(QrcodeTypes::WEB_URL)
        ->and(QrcodeTypes::validate([
            'type' => QrcodeTypes::WECHAT_MINI_APP,
            'internal_name' => 'Mini page',
            'scene' => 'home',
            'path' => 'pages/index',
            'target_url' => null,
        ]))->toBe(QrcodeTypes::WECHAT_MINI_APP);
});

it('returns a validation error for a non-string URL', function () {
    expect(fn () => QrcodeTypes::validate([
        'type' => QrcodeTypes::WEB_URL,
        'internal_name' => 'Home page',
        'target_url' => ['https://example.com'],
    ]))->toThrow(ValidationException::class);
});
