<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PackageDatabase;

beforeEach(fn () => PackageDatabase::prepare());

it('creates the shared schema for both qrcode types with nullable type specific values', function () {
    expect(Schema::hasColumns('qrcodes', ['type', 'scene', 'path', 'target_url']))->toBeTrue();

    $now = now();
    DB::table('qrcodes')->insert([
        [
            'type' => 'wechat_mini_app', 'internal_name' => 'Mini', 'scene' => 'home',
            'path' => 'pages/index', 'target_url' => null, 'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'type' => 'web_url', 'internal_name' => 'Web', 'scene' => null,
            'path' => null, 'target_url' => 'https://example.com', 'created_at' => $now, 'updated_at' => $now,
        ],
    ]);

    expect(DB::table('qrcodes')->count())->toBe(2);
});
