<?php

namespace Fayne\QrCode\Filament\Resources\QrcodeResource\Pages;

use Fayne\QrCode\Filament\Resources\QrcodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQrcode extends EditRecord
{
    protected static string $resource = QrcodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
