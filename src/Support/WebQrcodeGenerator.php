<?php

namespace Fayne\QrCode\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class WebQrcodeGenerator
{
    public static function getQrcodeOutput(string $url, int $width = 430, string $outputType = 'stream'): string
    {
        if (! QrcodeTypes::supports(QrcodeTypes::WEB_URL)) {
            throw new \InvalidArgumentException('Web URL qrcodes are disabled by configuration.');
        }

        $parts = parse_url($url) ?: [];

        if (strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('A valid HTTP or HTTPS URL is required.');
        }

        if ($width < 280 || $width > 1280) {
            throw new \InvalidArgumentException('Width must be between 280 and 1280.');
        }

        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $width,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $result = (new PngWriter)->write($qrCode);

        if (! in_array($outputType, ['stream', 'dataUrl'], true)) {
            throw new \InvalidArgumentException('Output type must be stream or dataUrl.');
        }

        return $outputType === 'dataUrl' ? $result->getDataUri() : $result->getString();
    }

    public static function handle(string $url, ?string $disk = null, ?string $path = null, int $width = 430): string
    {
        $disk ??= 'public';
        $path ??= config('qrcode.default_path', 'images/qrcodes');
        $path = QrcodeTenancy::storagePath($path);
        $file = ltrim(trim($path, '/') . '/' . md5('web_' . $url . $width) . '.png', '/');
        if (! Storage::disk($disk)->put($file, static::getQrcodeOutput($url, $width))) {
            throw new \RuntimeException('Unable to store the generated web qrcode.');
        }

        return Storage::disk($disk)->url($file);
    }
}
