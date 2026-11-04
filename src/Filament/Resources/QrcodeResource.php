<?php

namespace Fayne\QrCode\Filament\Resources;

use Fayne\QrCode\Actions\GenerateMiniAppQrcode;
use Fayne\QrCode\Actions\GenerateWebQrcode;
use Fayne\QrCode\Filament\Resources\QrcodeResource\Pages;
use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Support\QrcodeTypes;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QrcodeResource extends Resource
{
    protected static bool $isScopedToTenant = false;
    public static function getNavigationLabel(): string
    {
        return __('filament-qrcode::navigation.qrcodes');
    }

    public static function getModelLabel(): string
    {
        return __('filament-qrcode::qrcode.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-qrcode::qrcode.plural');
    }

    protected static ?string $model = Qrcode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    public static function form(Schema $schema): Schema
    {
        $types = QrcodeTypes::allowed();

        return $schema->components([
            TextInput::make('internal_name')->label(__('filament-qrcode::qrcode.internal_name'))->required()->maxLength(100)->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', \Fayne\QrCode\Support\QrcodeTenancy::id())),
            Select::make('type')->label(__('filament-qrcode::qrcode.type'))
                ->options([
                    QrcodeTypes::WECHAT_MINI_APP => __('filament-qrcode::qrcode.wechat_mini_app'),
                    QrcodeTypes::WEB_URL => __('filament-qrcode::qrcode.web_url'),
                ])
                ->default(count($types) === 1 ? $types[0] : null)
                ->disabled(fn (?Qrcode $record): bool => $record !== null)
                ->dehydrated()
                ->required()
                ->in($types),
            TextInput::make('scene')->label(__('filament-qrcode::qrcode.scene'))
                ->visible(fn (Get $get): bool => $get('type') === QrcodeTypes::WECHAT_MINI_APP)
                ->required(fn (Get $get): bool => $get('type') === QrcodeTypes::WECHAT_MINI_APP)
                ->maxLength(32)
                ->regex("/^[A-Za-z0-9!#$&'()*+,\/:;=?@\\-._~]+$/"),
            TextInput::make('path')->label(__('filament-qrcode::qrcode.path'))
                ->visible(fn (Get $get): bool => $get('type') === QrcodeTypes::WECHAT_MINI_APP)
                ->required(fn (Get $get): bool => $get('type') === QrcodeTypes::WECHAT_MINI_APP)
                ->maxLength(255),
            TextInput::make('target_url')->label(__('filament-qrcode::qrcode.target_url'))
                ->visible(fn (Get $get): bool => $get('type') === QrcodeTypes::WEB_URL)
                ->required(fn (Get $get): bool => $get('type') === QrcodeTypes::WEB_URL)
                ->url()
                ->maxLength(2048),
            KeyValue::make('additional_data')->label(__('filament-qrcode::qrcode.additional_data'))->keyLabel(__('filament-qrcode::qrcode.key'))->valueLabel(__('filament-qrcode::qrcode.value')),
            DateTimePicker::make('expired_at')->label(__('filament-qrcode::qrcode.expired_at'))->helperText(__('filament-qrcode::qrcode.expiry_help')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('internal_name')->label(__('filament-qrcode::qrcode.internal_name'))->searchable()->sortable(),
            TextColumn::make('type')->label(__('filament-qrcode::qrcode.type'))->formatStateUsing(fn (string $state): string => match ($state) {
                QrcodeTypes::WECHAT_MINI_APP => __('filament-qrcode::qrcode.wechat_mini_app'),
                QrcodeTypes::WEB_URL => __('filament-qrcode::qrcode.web_url'),
                default => $state,
            })->badge(),
            TextColumn::make('target_url')->label(__('filament-qrcode::qrcode.target_url'))->limit(50),
            TextColumn::make('created_at')->label(__('filament-qrcode::qrcode.created_at'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('type')->label(__('filament-qrcode::qrcode.type'))->options([
                QrcodeTypes::WECHAT_MINI_APP => __('filament-qrcode::qrcode.wechat_mini_app'),
                QrcodeTypes::WEB_URL => __('filament-qrcode::qrcode.web_url'),
            ]),
        ])->recordActions([
            GenerateMiniAppQrcode::make(),
            GenerateWebQrcode::make(),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQrcodes::route('/'),
            'create' => Pages\CreateQrcode::route('/create'),
            'edit' => Pages\EditQrcode::route('/{record}/edit'),
        ];
    }
}
