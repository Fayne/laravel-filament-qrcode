<?php

use Fayne\QrCode\Models\Qrcode;
use Illuminate\Validation\ValidationException;
use Tests\Support\PackageDatabase;

beforeEach(fn () => PackageDatabase::prepare());

beforeEach(function () {
    config()->set('qrcode.allowed_types', ['wechat_mini_app', 'web_url']);
});

it('creates web qrcodes with null mini app fields', function () {
    $qrcode = Qrcode::create([
        'type' => 'web_url',
        'internal_name' => 'Home page',
        'target_url' => 'https://example.com',
        'scene' => null,
        'path' => null,
    ]);

    expect($qrcode->exists)->toBeTrue();
});

it('creates mini app qrcodes with a null web URL', function () {
    $qrcode = Qrcode::create([
        'type' => 'wechat_mini_app',
        'internal_name' => 'Mini page',
        'scene' => 'home',
        'path' => 'pages/index',
        'target_url' => null,
    ]);

    expect($qrcode->exists)->toBeTrue();
});

it('does not allow changing a saved qrcode type', function () {
    $qrcode = Qrcode::create([
        'type' => 'web_url',
        'internal_name' => 'Home page',
        'target_url' => 'https://example.com',
    ]);
    $qrcode->type = 'wechat_mini_app';
    $qrcode->scene = 'home';
    $qrcode->path = 'pages/index';

    expect(fn () => $qrcode->save())->toThrow(ValidationException::class);
});

it('clears generated image URLs when the web target changes', function () {
    $qrcode = Qrcode::create([
        'type' => 'web_url',
        'internal_name' => 'Home page',
        'target_url' => 'https://example.com',
        'urls' => ['430_web' => 'https://cdn.example.com/old.png'],
    ]);

    $qrcode->target_url = 'https://new.example.com';
    $qrcode->save();

    expect($qrcode->fresh()->urls)->toBeNull();
});

it('does not clear image URLs when only the name changes', function () {
    $qrcode = Qrcode::create([
        'type' => 'web_url',
        'internal_name' => 'Home page',
        'target_url' => 'https://example.com',
        'urls' => ['430_web' => 'https://cdn.example.com/home.png'],
    ]);

    $qrcode->internal_name = 'Landing page';
    $qrcode->save();

    expect($qrcode->fresh()->urls)->toBe(['430_web' => 'https://cdn.example.com/home.png']);
});

it('creates factory records with valid mini app defaults', function () {
    $qrcode = Qrcode::factory()->create();

    expect($qrcode->type)->toBe('wechat_mini_app')
        ->and(strlen($qrcode->scene))->toBeLessThanOrEqual(32)
        ->and($qrcode->path)->toStartWith('pages/');
});

it('creates valid web URL factory records', function () {
    $qrcode = Qrcode::factory()->webUrl()->create();

    expect($qrcode->type)->toBe('web_url')
        ->and($qrcode->target_url)->toStartWith('https://')
        ->and($qrcode->scene)->toBeNull()
        ->and($qrcode->path)->toBeNull();
});

it('allows metadata edits but locks generation inputs for a disabled historical type', function () {
    $qrcode = Qrcode::create([
        'type' => 'wechat_mini_app',
        'internal_name' => 'Mini page',
        'scene' => 'home',
        'path' => 'pages/index',
    ]);

    config()->set('qrcode.allowed_types', ['web_url']);
    $qrcode->internal_name = 'Archived mini page';
    $qrcode->save();

    $qrcode->scene = 'changed';
    expect(fn () => $qrcode->save())->toThrow(ValidationException::class);
});
