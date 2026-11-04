<?php

namespace Fayne\QrCode\Exceptions;

use Exception;
use Fayne\QrCode\Enums\WechatQrcodeErrors;

class WechatException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @return void
     *
     * @throws \Throwable
     */
    public static function throw(string $message, int $code): void
    {
        throw_if(WechatQrcodeErrors::serverError($code), new MiniAppServerException($message, $code));
        throw_if(WechatQrcodeErrors::clientError($code), new MiniAppClientException($message, $code));
    }
}
