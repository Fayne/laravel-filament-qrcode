<?php

namespace Fayne\QrCode;

use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Policies\QrcodePolicy;
use Fayne\QrCode\Rules\ValidMiniQrcode;
use Fayne\QrCode\Support\QrcodeTypes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as BaseValidator;
use Overtrue\LaravelWeChat\EasyWeChat;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use EasyWeChat\MiniApp\Application;

class QrcodeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('qrcode')
            ->hasConfigFile()
            ->hasCommand(\Fayne\QrCode\Console\InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->callAfterResolving(\Fayne\CMS\Support\Authorization\PermissionRegistry::class, function ($registry): void {
            $registry->register('qrcode', require __DIR__ . '/../resources/permissions.php');
        });
        config(['easywechat.mini_app.default' => array_replace([
            'app_id' => env('WECHAT_MINI_APP_APPID', ''),
            'secret' => env('WECHAT_MINI_APP_SECRET', ''),
        ], config('easywechat.mini_app.default', config('easywechat.mini_app', [])) ?? [])]);
        $this->app->singleton('mini-app', function () {
            return EasyWeChat::miniApp();
        });
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'qrcode-migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-qrcode');
        if (! $this->app->bound('easywechat.mini_app.default')) {
            $this->app->singleton('easywechat.mini_app.default', function ($app) {
                $miniApp = new Application(array_merge(
                    config('easywechat.defaults', []),
                    config('easywechat.mini_app.default', []),
                ));
                $miniApp->setCache($app['cache.store']);
                $miniApp->setRequestFromSymfonyRequest($app['request']);

                return $miniApp;
            });
        }

        Gate::policy(Qrcode::class, QrcodePolicy::class);
        QrcodeTypes::allowed();

        Str::macro('hexToRgb', function (?string $hexColor, bool $asString = false) {
            if (! $hexColor) {
                return null;
            }

            $hexColor = ltrim($hexColor, '#');
            $r = hexdec(substr($hexColor, 0, 2));
            $g = hexdec(substr($hexColor, 2, 2));
            $b = hexdec(substr($hexColor, 4, 2));

            return $asString ? "rgb($r, $g, $b)" : compact('r', 'g', 'b');
        });

        Validator::extend('valid_mini_qrcode', function ($attribute, $value, $parameters, BaseValidator $validator) {
            (new ValidMiniQrcode)->setData($validator->getData())->validate($attribute, $value, function ($message) use ($validator, $attribute) {
                $validator->errors()->add($attribute, $message instanceof \Throwable ? ValidMiniQrcode::message() : $message);
            });

            return ! $validator->errors()->has($attribute);
        }, ValidMiniQrcode::message());
    }
}
