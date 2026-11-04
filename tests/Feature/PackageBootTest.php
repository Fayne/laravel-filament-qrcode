<?php

use Fayne\QrCode\QrcodeServiceProvider;

it('boots the QR Code package independently and registers its Filament plugin', function () {
    expect(app()->getLoadedProviders())->toHaveKey(QrcodeServiceProvider::class)
        ->and(Filament\Facades\Filament::getPanel('admin')->getPlugins())->toHaveKey('qrcode');
});
