<?php

namespace Fayne\QrCode\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Fayne\QrCode\Database\Factories\QrcodeFactory;
use Fayne\QrCode\Support\QrcodeTypes;
use Fayne\QrCode\Events\CacheableUpdatedEvent;
use Illuminate\Validation\ValidationException;

class Qrcode extends Model
{
    use HasFactory;

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Fayne\CMS\Models\Tenant::class);
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'qrcodes';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = false;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type' => 'string',
        'urls' => 'array',
        'additional_data' => 'array',
        'expired_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', fn ($query) => $query->where($query->getModel()->qualifyColumn('tenant_id'), \Fayne\QrCode\Support\QrcodeTenancy::id()));
        static::creating(function (self $record): void {
            $id = \Fayne\QrCode\Support\QrcodeTenancy::id();
            if (isset($record->tenant_id) && (string) $record->tenant_id !== (string) $id) {
                throw ValidationException::withMessages(['tenant_id' => 'Invalid QR code tenant.']);
            }
            $record->tenant_id = $id;
        });
        static::updating(function (self $record): void {
            \Fayne\QrCode\Support\QrcodeTenancy::assertOwner($record);
            if ($record->isDirty('tenant_id')) {
                throw ValidationException::withMessages(['tenant_id' => 'QR code ownership cannot be changed.']);
            }
        });
        static::deleting(fn (self $record) => \Fayne\QrCode\Support\QrcodeTenancy::assertOwner($record));

        static::saved(function (self $qrcode): void {
            CacheableUpdatedEvent::dispatch($qrcode, app()->bound('request') ? request() : null);
        });

        static::deleted(function (self $qrcode): void {
            CacheableUpdatedEvent::dispatch($qrcode, app()->bound('request') ? request() : null);
        });

        static::saving(function (self $qrcode) {
            $existingType = $qrcode->exists ? $qrcode->getOriginal('type') : null;
            $type = QrcodeTypes::validate($qrcode->getAttributes(), $existingType);

            if ($qrcode->exists && ! QrcodeTypes::supports($type)) {
                foreach (['scene', 'path', 'target_url'] as $field) {
                    if ($qrcode->getAttribute($field) !== $qrcode->getOriginal($field)) {
                        throw ValidationException::withMessages([
                            $field => 'The generation input cannot be changed while this qrcode type is disabled.',
                        ]);
                    }
                }
            }

            $qrcode->type = $type;

            if ($type === QrcodeTypes::WEB_URL) {
                $qrcode->scene = null;
                $qrcode->path = null;

                if ($qrcode->exists && $qrcode->isDirty('target_url')) {
                    $qrcode->urls = null;
                }
            } else {
                $qrcode->target_url = null;
            }
        });
    }

    public function operationAuditPayload(array $payload): array
    {
        return \Illuminate\Support\Arr::only($payload, ['internal_name', 'type', 'expired_at', 'width', 'disk', 'auto_color', 'line_color', 'is_hyaline', 'check_path']);
    }

    public function isWebUrl(): bool
    {
        return $this->type === 'web_url';
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return QrcodeFactory::new();
    }

    /**
     * Get the parent qrcodeable model.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
