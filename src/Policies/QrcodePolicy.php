<?php

namespace Fayne\QrCode\Policies;

use Fayne\QrCode\Models\Qrcode;
use Illuminate\Contracts\Auth\Authenticatable;

class QrcodePolicy
{
    public function before($admin, string $ability, $record = null): ?bool
    {
        if ($record instanceof Qrcode) {
            try {
                \Fayne\QrCode\Support\QrcodeTenancy::assertOwner($record);
            } catch (\Illuminate\Validation\ValidationException) {
                return false;
            }
        }

        return null;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.viewAnyQrcode');
    }

    public function view(Authenticatable $user, Qrcode $qrcode): bool
    {
        return $this->allows($user, 'tenant.viewQrcode');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.createQrcode');
    }

    public function update(Authenticatable $user, Qrcode $qrcode): bool
    {
        return $this->allows($user, 'tenant.updateQrcode');
    }

    public function delete(Authenticatable $user, Qrcode $qrcode): bool
    {
        return $this->allows($user, 'tenant.deleteQrcode');
    }

    private function allows(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'can') && ($user->can($permission) || (! \Fayne\QrCode\Support\QrcodeTenancy::enabled() && $user->can(substr($permission, strlen('tenant.')))));
    }
}
