<?php

namespace Fayne\QrCode\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QrcodeTypes
{
    public const WECHAT_MINI_APP = 'wechat_mini_app';
    public const WEB_URL = 'web_url';

    public static function allowed(?array $types = null): array
    {
        $types ??= config('qrcode.allowed_types', []);
        $known = [self::WECHAT_MINI_APP, self::WEB_URL];

        if (! is_array($types) || $types === [] || count(array_filter($types, 'is_string')) !== count($types)
            || array_diff($types, $known) || count($types) !== count(array_unique($types))) {
            throw new \InvalidArgumentException('qrcode.allowed_types must contain one or more unique supported types.');
        }

        return array_values($types);
    }

    public static function supports(string $type, ?array $allowed = null): bool
    {
        return in_array($type, self::allowed($allowed), true);
    }

    public static function validate(array $data, ?string $existingType = null): string
    {
        $allowed = self::allowed();
        $type = $data['type'] ?? $existingType ?? (count($allowed) === 1 ? $allowed[0] : null);

        if (! is_string($type) || ! in_array($type, [self::WECHAT_MINI_APP, self::WEB_URL], true)
            || (! in_array($type, $allowed, true) && $type !== $existingType)
            || ($existingType !== null && $type !== $existingType)) {
            throw ValidationException::withMessages(['type' => 'Choose an enabled qrcode type.']);
        }

        $typesForValidation = $type === $existingType ? [...$allowed, $existingType] : $allowed;

        $rules = [
            'internal_name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'in:' . implode(',', $typesForValidation)],
            'scene' => $type === self::WECHAT_MINI_APP
                ? ['required', 'string', 'max:32', "regex:|^[A-Za-z0-9!#$&'()*+,/:;=?@\\-._~]+$|"]
                : ['prohibited'],
            'path' => $type === self::WECHAT_MINI_APP
                ? ['required', 'string', 'max:255']
                : ['prohibited'],
            'target_url' => $type === self::WEB_URL
                ? ['required', 'string', 'url:http,https', 'max:2048']
                : ['prohibited'],
        ];

        $validator = Validator::make($data, $rules);
        $validator->after(function ($validator) use ($data, $type) {
            if ($type === self::WEB_URL && is_string($data['target_url'] ?? null)) {
                if (strlen($data['target_url']) > 2048 || preg_match('/[\\x00-\\x20\\x7F]/', $data['target_url'])) {
                    $validator->errors()->add('target_url', 'The target URL is invalid.');
                }

                $parts = parse_url($data['target_url']);
                if (isset($parts['user']) || isset($parts['pass'])) {
                    $validator->errors()->add('target_url', 'URLs containing credentials are not allowed.');
                }
            }
        });

        $validator->validate();

        return $type;
    }
}
