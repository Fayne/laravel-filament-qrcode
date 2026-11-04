<?php

namespace Fayne\QrCode\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Fayne\QrCode\Filament\Resources\QrcodeResource;

class QrcodePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'qrcode';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([QrcodeResource::class]);
    }

    public function boot(Panel $panel): void {}
}
