<?php

namespace Fayne\QrCode\Filament\Resources\QrcodeResource\Pages;

use Fayne\QrCode\Filament\Resources\QrcodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQrcodes extends ListRecords
{
    protected static string $resource = QrcodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
