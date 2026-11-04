<?php

namespace Fayne\QrCode\Actions;

use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Support\QrcodeTypes;
use Fayne\QrCode\Support\WebQrcodeGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Gate;

class GenerateWebQrcode
{
    public static function make(): Action
    {
        return Action::make('generateWebQrcode')
            ->label(__('filament-qrcode::qrcode.generate_web'))
            ->visible(fn (Qrcode $record): bool => $record->type === QrcodeTypes::WEB_URL && QrcodeTypes::supports(QrcodeTypes::WEB_URL))
            ->schema([
                TextInput::make('width')->label(__('filament-qrcode::qrcode.width'))->numeric()->integer()->minValue(280)->maxValue(1280)->default(430)->required(),
                Select::make('disk')->label(__('filament-qrcode::qrcode.disk'))->options(array_combine(config('qrcode.storage_disks'), config('qrcode.storage_disks')))->default('public')->required(),
                TextInput::make('path')->label(__('filament-qrcode::qrcode.storage_path'))->default(config('qrcode.default_path', 'images/qrcodes'))->required(),
            ])
            ->action(function (Qrcode $record, array $data): void {
                Gate::authorize('update', $record);
                abort_unless($record->type === QrcodeTypes::WEB_URL && QrcodeTypes::supports(QrcodeTypes::WEB_URL), 403);

                $url = WebQrcodeGenerator::handle($record->target_url, $data['disk'], $data['path'], (int) $data['width']);
                $record->update(['urls' => [...($record->urls ?? []), ((int) $data['width']) . '_web' => $url]]);
                \Fayne\QrCode\Support\QrcodeOperation::generated($record, $data);
            });
    }
}
