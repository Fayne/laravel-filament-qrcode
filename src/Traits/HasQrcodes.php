<?php

namespace Fayne\QrCode\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Support\Generator;
use Fayne\QrCode\Support\QrcodeTypes;
use Fayne\QrCode\Support\WebQrcodeGenerator;

trait HasQrcodes
{
    /**
     * Get all of the qrcodes.
     */
    public function qrcodes(): MorphMany
    {
        \Fayne\QrCode\Support\QrcodeTenancy::assertOwner($this);

        return $this->morphMany(Qrcode::class, 'entity');
    }

    public function generateWebQrcode(
        string $name,
        string $url,
        ?array $additionalData = [],
        ?array $options = []
    ): Qrcode {
        \Fayne\QrCode\Support\QrcodeTenancy::assertOwner($this);

        if (! QrcodeTypes::supports(QrcodeTypes::WEB_URL)) {
            throw new \InvalidArgumentException('Web URL qrcodes are not enabled by configuration.');
        }

        QrcodeTypes::validate([
            'type' => QrcodeTypes::WEB_URL,
            'internal_name' => $name,
            'target_url' => $url,
        ]);

        $imageUrl = WebQrcodeGenerator::handle(
            url: $url,
            disk: $options['disk'] ?? null,
            path: $options['path'] ?? null,
            width: (int) ($options['width'] ?? 430)
        );

        return $this->qrcodes()->create([
            'type' => QrcodeTypes::WEB_URL,
            'internal_name' => $name,
            'target_url' => $url,
            'additional_data' => $additionalData,
            'user_id' => $options['user_id'] ?? null,
            'urls' => [(int) ($options['width'] ?? 430) . '_web' => $imageUrl],
        ]);
    }

    /**
     * @param string $name
     * @param string $page
     * @param string|null $scene
     * @param array|null $additionalData
     * @param array|null $styles
     * @param array|null $options
     * @param \Closure|null $callback
     * @return array|mixed
     *
     * @throws \Exception
     */
    public function generateQrcode(
        string $name,
        string $page,
        ?string $scene,
        ?array $additionalData = [],
        ?array $styles = [],
        ?array $options = [],
        ?\Closure $callback = null)
    {
        \Fayne\QrCode\Support\QrcodeTenancy::assertOwner($this);

        if (! QrcodeTypes::supports(QrcodeTypes::WECHAT_MINI_APP)) {
            throw new \InvalidArgumentException('Mini App qrcodes are not enabled by configuration.');
        }

        $qrcode = $this->qrcodes()->create([
            'type' => QrcodeTypes::WECHAT_MINI_APP,
            'internal_name' => $name,
            'scene' => $scene ?? Str::random(32),
            'path' => $page,
            'additional_data' => $additionalData,
            'expired_at' => $options['expired_at'] ?? null,
            'user_id' => $options['user_id'] ?? null,
        ]);

        $result = Generator::handle(
            scene: $qrcode->scene,
            page: $page,
            disk: $options['disk'] ?? 'public',
            path: $options['path'] ?? config('qrcode.default_path', 'images/qrcodes'),
            styles: $styles,
            checkPath: false,
            callback: function (string $url) use ($qrcode, $styles) {
                $key = sprintf('%s_%s', $styles['width'] ?? '430', config('easywechat.mini_app.qrcode.env_version', 'release'));
                $urls = $qrcode->urls;
                $qrcode->update([
                    'urls' => array_merge($urls ?: [], [$key => $url]),
                ]);
            });

        if ($callback) {
            return $callback($qrcode, $result);
        } else {
            return compact('qrcode', 'result');
        }
    }
}
