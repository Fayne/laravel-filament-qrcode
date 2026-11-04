<?php

namespace Fayne\QrCode\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Qrcodeable
{
    /**
     * Get all of the qrcodes.
     */
    public function qrcodes(): MorphMany;
}
