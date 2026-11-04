<?php

namespace Fayne\QrCode\Enums;

enum WechatQrcodeErrors: int
{
    case SYSTEM_ERROR = -1;
    case INVALID_ACCESS_TOKEN = 40001;
    case INVALID_SCENE = 40129;
    case INVALID_SCENE2 = 40169;
    case INVALID_PAGE = 41030;
    case NOT_ALLOWED = 85096;
    case INVALID_ARGS = 40097;

    /**
     * @param int|null $code
     * @return array|bool
     */
    public static function serverError(?int $code = null): array|bool
    {
        $conditions = [
            self::SYSTEM_ERROR->value,
            self::INVALID_ACCESS_TOKEN->value,
        ];

        if (!is_null($code)) {
            return in_array($code, $conditions);
        } else {
            return $conditions;
        }
    }

    /**
     * @param int|null $code
     * @return array|bool
     */
    public static function clientError(?int $code = null): array|bool
    {
        $conditions = [
            self::INVALID_SCENE->value,
            self::INVALID_SCENE2->value,
            self::INVALID_PAGE->value,
            self::NOT_ALLOWED->value,
            self::INVALID_ARGS->value,
        ];

        if (!is_null($code)) {
            return in_array($code, $conditions);
        } else {
            return $conditions;
        }
    }
}
