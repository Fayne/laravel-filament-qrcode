<?php

namespace Fayne\QrCode\Support;

use Illuminate\Validation\ValidationException;

final class QrcodeTenancy
{
    public static function enabled(): bool
    {
        return (bool) config('filament-cms.tenancy.enabled', false);
    }
    public static function id(): int|string
    {
        if (!self::enabled()) {
            return 0;
        }
        $id = app(\Fayne\CMS\Support\TenantContext::class)->id();
        if (!$id) {
            throw ValidationException::withMessages(['tenant_id' => 'A tenant context is required for QR codes.']);
        }

        return $id;
    }
    public static function assertOwner($model): void
    {
        if ((string) ($model->getAttribute('tenant_id') ?? 0) !== (string) self::id()) {
            throw ValidationException::withMessages(['tenant_id' => 'The record belongs to another tenant.']);
        }
    }
    public static function storagePath(string $path): string
    {
        if (!self::enabled()) {
            return $path;
        }
        $id = self::id();
        if (str_contains($path, '..') || str_contains($path, '\\')) {
            throw ValidationException::withMessages(['path' => 'Invalid storage path.']);
        }

        return trim($path, '/') . '/tenants/' . $id . '/' . hash('sha256', (string) config('easywechat.mini_app.default.app_id', 'web'));
    }
}
