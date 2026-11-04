<?php

namespace Fayne\QrCode\Support;

use Illuminate\Support\Facades\Storage;
use Fayne\QrCode\Enums\WechatQrcodeErrors;
use Fayne\QrCode\Exceptions\WechatException;
use Fayne\QrCode\Facades\MiniApp;
use EasyWeChat\Kernel\Exceptions\BadMethodCallException;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Generator
{
    /**
     * @return bool|string
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public static function handle(
        string $scene,
        string $page,
        string $disk,
        string $path,
        ?array $styles = [],
        bool $checkPath = true,
        ?string $env = null,
        ?\Closure $callback = null
    ) {
        $env = $env ?: config('easywechat.mini_app.qrcode.env_version', 'release');

        $path = QrcodeTenancy::storagePath($path);

        $stream = static::getQrcodeOutput($scene, $page, $styles, $checkPath, $env);

        $fieldString = $scene . $page . json_encode($styles) . $env;
        $qrcodePath = sprintf('%s/%s.png', trim($path, '/'), md5($fieldString));
        if (! Storage::disk($disk)->put($qrcodePath, $stream)) {
            throw new \RuntimeException('Unable to store the generated Mini App qrcode.');
        }
        $url = Storage::disk($disk)->url($qrcodePath);

        if ($callback) {
            return $callback($url);
        } else {
            return $url;
        }
    }

    /**
     * @param string $scene
     * @param string $page
     * @param array|null $styles
     * @param bool $checkPath
     * @param string|null $env
     * @param 'stream'|'json'|'psrResponse'|'dataUrl' $outputType
     * @param \Closure|null $callback
     * @return mixed
     *
     * @throws BadMethodCallException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Throwable
     */
    public static function getQrcodeOutput(
        string $scene,
        string $page,
        ?array $styles = [],
        bool $checkPath = true,
        ?string $env = null,
        string $outputType = 'stream',
        ?\Closure $callback = null
    ) {
        if (! QrcodeTypes::supports(QrcodeTypes::WECHAT_MINI_APP)) {
            throw new \InvalidArgumentException('Mini App qrcodes are disabled by configuration.');
        }

        return retry(2, function () use ($callback, $scene, $page, $styles, $checkPath, $env, $outputType) {
            /** @var Response $response */
            $response = MiniApp::getClient()->postJson('/wxa/getwxacodeunlimit', [
                'scene' => $scene,
                'page' => $page,
                'width' => $styles['width'] ?? 430,
                'check_path' => $checkPath,
                'env_version' => $env,
                'auto_color' => $styles['auto_color'] ?? false,
                'line_color' => $styles['line_color'] ?? ['r' => 0, 'g' => 0, 'b' => 0],
                'is_hyaline' => $styles['is_hyaline'] ?? false,
            ]);

            if ($response->isSuccessful()) {
                switch ($outputType) {
                    case 'stream':
                        $stream = $response->toStream(false);
                        $result = json_decode(stream_get_contents($stream), true);

                        if ($result && ($errorCode = (int) ($result['errcode'] ?? 0))) {
                            WechatException::throw($result['errmsg'] ?? '', $errorCode);
                        }

                        if (is_resource($stream) && stream_get_meta_data($stream)['seekable']) {
                            rewind($stream);
                        }

                        if ($callback) {
                            $callback($stream);
                        }

                        return $stream;
                    case 'json':
                        return $response->toJson();
                    case 'psrResponse':
                        return $response->toPsrResponse();
                    case 'dataUrl':
                    default:
                        return $response->toDataUrl();
                }
            }

            $responseArray = json_decode($response->toJson(), true);

            throw new WechatException($response->toJson(), $responseArray['errcode'] ?? 500);
        }, 100, function (\Throwable $e) {
            if ($e->getCode() === WechatQrcodeErrors::INVALID_ACCESS_TOKEN->value) {
                $accessToken = MiniApp::getAccessToken();
                $accessToken->refresh();

                return true;
            }

            return false;
        });
    }
}
