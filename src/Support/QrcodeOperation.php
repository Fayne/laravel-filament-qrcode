<?php

namespace Fayne\QrCode\Support;

use Fayne\QrCode\Models\Qrcode;

final class QrcodeOperation
{
    public static function generated(Qrcode $record, array $options): void
    {
        $operations = \Fayne\CMS\Support\Operations\Operations::class;
        if (!class_exists($operations)) {
            return;
        }
        app($operations)->completed(
            $record->isWebUrl() ? 'qrcode.generate_web' : 'qrcode.generate_mini_app',
            $record->operationAuditPayload($options),
            $record,
            ['source' => 'filament'],
            $record->getConnectionName(),
        );
    }
}
