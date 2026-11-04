<?php

use Fayne\QrCode\Support\WebQrcodeGenerator;
use Fayne\QrCode\Support\Generator;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

beforeEach(function () {
    $app = new Container;
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    $app->instance('config', new Repository(['qrcode' => ['allowed_types' => ['wechat_mini_app', 'web_url']]]));
});

it('rejects non-http URLs', function () {
    expect(fn () => WebQrcodeGenerator::getQrcodeOutput('javascript:alert(1)'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects URLs containing credentials', function () {
    expect(fn () => WebQrcodeGenerator::getQrcodeOutput('https://user:password@example.com'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects image widths outside the supported range', function () {
    expect(fn () => WebQrcodeGenerator::getQrcodeOutput('https://example.com', 279))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects web generation when the type is disabled', function () {
    config()->set('qrcode.allowed_types', ['wechat_mini_app']);

    expect(fn () => WebQrcodeGenerator::getQrcodeOutput('https://example.com'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects mini app generation when the type is disabled before using WeChat', function () {
    config()->set('qrcode.allowed_types', ['web_url']);

    expect(fn () => Generator::getQrcodeOutput('scene', 'pages/index'))
        ->toThrow(InvalidArgumentException::class);
});
