<?php

namespace Fayne\QrCode\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class CacheableUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private ?Model $model = null,
        private ?Request $request = null,
    ) {}

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }
}
