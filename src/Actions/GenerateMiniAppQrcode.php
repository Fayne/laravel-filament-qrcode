<?php

namespace Fayne\QrCode\Actions;

use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Support\Generator;
use Fayne\QrCode\Support\QrcodeTypes;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class GenerateMiniAppQrcode
{
    public static function make(): Action
    {
        return Action::make('generateMiniAppQrcode')
            ->label(__('filament-qrcode::qrcode.generate_mini_app'))
            ->visible(fn (Qrcode $record): bool => $record->type === QrcodeTypes::WECHAT_MINI_APP && QrcodeTypes::supports(QrcodeTypes::WECHAT_MINI_APP))
            ->schema([
                TextInput::make('width')->label(__('filament-qrcode::qrcode.width'))->numeric()->integer()->minValue(280)->maxValue(1280)->default(430)->required(),
                Toggle::make('auto_color')->label(__('filament-qrcode::qrcode.auto_color'))->default(false),
                ColorPicker::make('line_color')->label(__('filament-qrcode::qrcode.line_color'))->visible(fn (Get $get): bool => ! $get('auto_color')),
                Toggle::make('is_hyaline')->label(__('filament-qrcode::qrcode.is_hyaline'))->default(false),
                Select::make('disk')->label(__('filament-qrcode::qrcode.disk'))->options(array_combine(config('qrcode.storage_disks'), config('qrcode.storage_disks')))->default('public')->required(),
                TextInput::make('path')->label(__('filament-qrcode::qrcode.storage_path'))->default(config('qrcode.default_path', 'images/qrcodes'))->required(),
                Toggle::make('check_path')->label(__('filament-qrcode::qrcode.check_path'))->default(true),
            ])
            ->action(function (Qrcode $record, array $data): void {
                Gate::authorize('update', $record);
                abort_unless($record->type === QrcodeTypes::WECHAT_MINI_APP && QrcodeTypes::supports(QrcodeTypes::WECHAT_MINI_APP), 403);

                $styles = [
                    'width' => (int) $data['width'],
                    'auto_color' => (bool) $data['auto_color'],
                    'line_color' => $data['auto_color'] ? null : Str::hexToRgb($data['line_color'] ?? '#000000'),
                    'is_hyaline' => (bool) $data['is_hyaline'],
                ];

                Generator::handle(
                    scene: $record->scene,
                    page: $record->path,
                    disk: $data['disk'],
                    path: $data['path'],
                    styles: $styles,
                    checkPath: (bool) $data['check_path'],
                    callback: function (string $url) use ($record, $data): void {
                        $key = sprintf('%s_%s', $data['width'], config('easywechat.mini_app.qrcode.env_version', 'release'));
                        $record->update(['urls' => [...($record->urls ?? []), $key => $url]]);
                    },
                );
                \Fayne\QrCode\Support\QrcodeOperation::generated($record, $data);
            });
    }
}
