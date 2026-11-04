<?php

namespace Fayne\QrCode\Facades;

use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\MiniApp\AccessToken;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AccessTokenAwareClient getClient()
 * @method static AccessToken getAccessToken()
 */
class MiniApp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mini-app';
    }
}
